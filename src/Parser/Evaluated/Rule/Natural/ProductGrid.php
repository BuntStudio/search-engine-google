<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use SM\Backend\SerpParser\RuleLoaderService;

class ProductGrid implements ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    public $hasSerpFeaturePosition = true;

    /**
     * Get the feature name based on mobile flag.
     * NOTE: match() is reached before $isMobile is known, so it resolves BOTH the desktop and
     * mobile match features. This helper is used by parse() for the extraction rule, where
     * $isMobile is available. A single ProductGrid class is registered in both NaturalParser
     * (desktop) and MobileNaturalParser (mobile), so it serves both devices.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'product_grid_mobile' : 'product_grid';
    }

    public function match(GoogleDom $dom, DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed container can
            // validate; mode 1 uses live rules. The match() context node is the candidate element
            // itself, so rules use the self:: axis.
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['product_grid_match', 'product_grid_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('product_grid_match'),
                    RuleLoaderService::getRulesForFeature('product_grid_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules found — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('jscontroller') == 'wuEeed') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // ----------------------------------------------------------------
        // Primary extraction rule: product-title spans inside grid cards.
        // Default hardcoded selector:
        //   .//li//span[contains(@class, "WJMUdc") and contains(@class, "rw5ecc")]
        // ProductGrid has no parse children and no fallback chain, so a candidate (mode 3) is
        // resolved by the parent feature id alone — there is no parse-family to widen across (§9.1).
        // ----------------------------------------------------------------
        $primaryRules = $this->getPrimaryExtractionRules($useDbRules, $isMobile, $additionalRule);

        if (!empty($primaryRules)) {
            $productGridNodes = $dom->getXpath()->query(implode(' | ', $primaryRules), $node);
        } else {
            $productGridNodes = $dom->getXpath()->query('.//li//span[contains(@class, "WJMUdc") and contains(@class, "rw5ecc")]', $node);
        }

        if ($productGridNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $productGridNodes->length; $i++) {
                if (!empty($productGridNodes->item($i))) {
                    $items[] = $this->getNoteTextValue($productGridNodes->item($i));
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PRODUCT_GRID, $items, $node, $this->hasSerpFeaturePosition));
            }
        }
    }

    /**
     * Resolve the primary product-title extraction rule(s) for the current parser mode.
     * Returns an empty array when there are no applicable DB rules (or in hardcoded mode), which
     * makes parse() fall back to the hardcoded title-span selector.
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

    public function getNoteTextValue($node) {
        $text = '';
        if (get_class($node) != 'Serps\Core\Dom\DomElement') {
            if (get_class($node) == 'DOMText') {
                $text = $node->nodeValue;
            }
        } else if ($node->getChildren()->length == 0) {
            $text = $node->getNodeValue();
        } else {
            foreach ($node->getChildren() as $child) {
                $text .= ' ' . $this->getNoteTextValue($child);
            }
        }
        return trim($text);
    }
}
