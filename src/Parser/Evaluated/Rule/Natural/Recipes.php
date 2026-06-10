<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class Recipes implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Get the feature name based on mobile flag.
     * NOTE: match() is reached before $isMobile is known, so it resolves both the desktop
     * and mobile match features. This helper is used by parse() for the extraction rule.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'recipes_mobile' : 'recipes';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; mode 1 uses live rules as before. The match()
            // context node is the candidate element itself, so rules use the self:: axis.
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['recipes_match', 'recipes_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('recipes_match'),
                    RuleLoaderService::getRulesForFeature('recipes_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules found — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if (strpos($node->getAttribute('jsname'), 'MGJTwe') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('data-attrid') === 'SupercatRecipeClusterTitle') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        try {
            $item = [];

            $urls = null;          // populated by the primary extraction rule (DB or hardcoded)
            $urlOnAttribute = false;

            // ----------------------------------------------------------------
            // Primary extraction rule: recipe link nodes (default 'descendant::g-link').
            // Only the primary rule is integrated; the SupercatRecipeClusterTitle sibling-walk,
            // the ddkIM / data-rl / div[jsname='Gbzile'] fallback chain, and the deferred-load
            // parent::*//g-link carousel walk remain hardcoded below as a fallback chain.
            // ----------------------------------------------------------------
            $primaryRules = $this->getPrimaryExtractionRules($useDbRules, $isMobile, $additionalRule);
            if (!empty($primaryRules)) {
                $urls = $dom->getXpath()->query(implode(' | ', $primaryRules), $node);
            }

            if ($urls === null || $urls->length == 0) {
                $urls = $dom->getXpath()->query('descendant::g-link', $node);
            }

            // ---- Hardcoded fallback chain (left intentionally hardcoded) ----

            // If this is a SupercatRecipeClusterTitle, look for g-link in the next sibling of the parent
            if ($urls->length == 0 && $node->getAttribute('data-attrid') === 'SupercatRecipeClusterTitle') {
                $parent = $node->parentNode;
                if ($parent && $parent->nextSibling) {
                    $urls = $dom->getXpath()->query('descendant::g-link', $parent->nextSibling);

                    // Mobile SERP variant: recipe links use <a class="ddkIM"> instead of g-link
                    if ($urls->length == 0) {
                        $urls = $dom->getXpath()->query("descendant::a[contains(@class, 'ddkIM')]", $parent->nextSibling);
                        $urlOnAttribute = 'ddkIM';
                    }
                }
            }

            if ($urls->length == 0) {
                $urlOnAttribute =  true;
                $urls = $dom->getXpath()->query('descendant::a[@data-rl]', $node);
            }

            if ($urls->length == 0) {
                $urlOnAttribute = false;
                $urls = $dom->getXpath()->query("descendant::div[@jsname='Gbzile']", $node);
            }

            // Desktop deferred-load variant: the MGJTwe carousel container has no inline
            // anchors; the recipe links sit in sibling g-inner-card[jsname='WUSFrc'] cards
            // under the same parent (jscontroller='eLjrV'). Search the parent scope.
            if ($urls->length == 0 && $node->getAttribute('jsname') === 'MGJTwe') {
                $urlOnAttribute = false;
                $urls = $dom->getXpath()->query('parent::*//g-link', $node);
            }

            if ($urls->length > 0) {
                foreach ($urls as $urlNode) {
                    if ($urlOnAttribute === 'ddkIM') {
                        $item['recipes_links'][] = ['link' => $urlNode->getAttribute('href')];
                    } elseif ($urlOnAttribute) {
                        $item['recipes_links'][] = ['link' => $urlNode->getAttribute('data-rl')];
                    } else {
                        // firstChild may be a DOMText (whitespace) rather than the anchor — look it up via XPath instead.
                        $anchor = $dom->getXpath()->query('descendant::a[@href]', $urlNode)->item(0);
                        if ($anchor instanceof \DOMElement) {
                            $item['recipes_links'][] = ['link' => $anchor->getAttribute('href')];
                        }
                    }

                }

                if (!empty($item['recipes_links'])) {
                    $resultSet->addItem(new BaseResult(NaturalResultType::RECIPES_GROUP , $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
                }
            }
        } catch (\Throwable $e) {
            // Recipe SERPFeature parsing must never break the rest of SERP processing.
            if (function_exists('\\Sentry\\captureException')) {
                \Sentry\captureException($e);
            }
        }
    }

    /**
     * Resolve the primary recipe-link extraction rule(s) for the current parser mode.
     * Returns an empty array when there are no applicable DB rules (or in hardcoded mode),
     * which makes parse() fall back to the hardcoded 'descendant::g-link' selector and the
     * subsequent hardcoded fallback chain.
     *
     * Recipes has no parse children, so a candidate (mode 3) is resolved by the parent
     * feature id alone — there is no parse-family to widen across (cf. §9.1).
     */
    protected function getPrimaryExtractionRules($useDbRules, $isMobile, $additionalRule)
    {
        $featureName = self::getFeatureName($isMobile);

        if ($useDbRules === self::MODE_DATABASE) {
            $singleRuleId = is_int($additionalRule) ? $additionalRule : null;
            return RuleLoaderService::getRulesForFeature($featureName, false, $singleRuleId);
        }

        if ($useDbRules === self::MODE_CANDIDATE_TESTING && is_array($additionalRule)) {
            $rules = RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName);
            // If the candidate isn't ours, fall back to live parent rules (mode-1 behavior).
            if (empty($rules)) {
                $rules = RuleLoaderService::getRulesForFeature($featureName);
            }
            return $rules;
        }

        return [];
    }
}
