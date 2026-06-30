<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class TopSights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Get the parent (extraction) feature name. TopSights is desktop-only (no mobile parser
     * class), so this always resolves to the desktop feature.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'top_sights';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container check (class == 'jhtnKe').
        // TopSights.php is desktop-only (no mobile parser class), so we consult the desktop
        // match feature only. Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['top_sights_match'])
                : RuleLoaderService::getRulesForFeature('top_sights_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('class') == 'jhtnKe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary per-sight item anchor extraction
        // (descendant::a[... 'ddkIM' ...]) lives in the 'top_sights' parent feature. The
        // per-item name ('yVCOtc') / url ('hHB9mc') extraction and the textContent / href
        // positional fallbacks stay hardcoded.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // TopSights has no parse children — resolve candidate rules by this feature only.
                $rules = (is_array($additionalRule))
                    ? RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName)
                    : [];
            } else {
                $rules = RuleLoaderService::getRulesForFeature($featureName);
            }

            if (!empty($rules)) {
                if ($this->parseWithDbRules($dom, $node, $resultSet, $rules)) {
                    return;
                }
                // DB rules matched nothing — fall through to hardcoded.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded.
        }

        $this->parseHardcoded($dom, $node, $resultSet);
    }

    /**
     * Extract Top Sights items using DB rules for the primary item-anchor selector.
     * The surviving per-item name/url extraction (yVCOtc / hHB9mc) stays hardcoded.
     * Returns true when at least one sight was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $topSightsNodes = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('TopSights DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($topSightsNodes->length == 0) {
            return false;
        }

        $items = $this->extractItems($dom, $topSightsNodes);

        if (count($items)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            return true;
        }

        return false;
    }

    /**
     * Hardcoded fallback — original item-anchor selector + per-item extraction.
     */
    protected function parseHardcoded(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet)
    {
        $topSightsNodes = $dom->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), 'ddkIM')]", $node);

        if ($topSightsNodes->length > 0) {
            $items = $this->extractItems($dom, $topSightsNodes);

            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }
    }

    /**
     * Per-item name ('yVCOtc' div) + url ('hHB9mc' anchor) extraction with positional
     * fallbacks. Kept hardcoded (secondary extraction) and shared by both parse paths.
     */
    protected function extractItems(GoogleDom $dom, $topSightsNodes)
    {
        $items = [];
        for ($i = 0; $i < $topSightsNodes->length; $i++) {
            if (!empty($topSightsNodes->item($i))) {
                $item = $topSightsNodes->item($i);
                $parent = $item->parentNode;
                $nameNodes = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' yVCOtc ')]", $parent);
                if ($nameNodes->length > 0) {
                    $name = trim($nameNodes->item(0)->textContent);
                } else {
                    $name = trim($parent->textContent);
                }

                $urlNodes = $dom->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' hHB9mc ')]", $parent);
                if ($urlNodes->length > 0) {
                    $url = $urlNodes->item(0)->getAttribute('href');
                } else {
                    $url = $item->getAttribute('href');
                }
                if (!empty($url)) {
                    $items[] = ['name' => $name, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($url)];
                }
            }
        }

        return $items;
    }
}
