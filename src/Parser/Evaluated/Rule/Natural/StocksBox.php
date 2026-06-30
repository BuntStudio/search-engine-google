<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;

class StocksBox implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    public $hasSerpFeaturePosition = true;

    /**
     * Get the feature name. StocksBox is desktop-only (registered in NaturalParser, not
     * MobileNaturalParser; no StocksBoxMobile sibling), so this always resolves to the
     * desktop feature.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'stocks_box';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; mode 1 uses live rules. The match() context node is
            // the candidate element itself, so rules use the self:: axis (§9.2).
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['stocks_box_match'])
                : RuleLoaderService::getRulesForFeature('stocks_box_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules found — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net). The stocks box is a `wDYxhc`
        // container; emission is gated downstream by parse() finding the price node, so the
        // gate keys ONLY on the container class — deliberately NOT on `data-attrid='Price'`.
        // Coupling the gate to the same token parse() extracts on means a single token break
        // (e.g. Google renaming `data-attrid`) disables BOTH rules at once, and the self-healer
        // — which heals one rule at a time — can't restore detection (mode-D, stocks_box
        // disaster test 2026-06-26_03). The bare class gate may match other `wDYxhc` answer
        // blocks, but parse() emits nothing for those (no price node), so the result is the same
        // and the feature stays self-healable.
        if ($node->getAttribute('class') == 'wDYxhc') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // ----------------------------------------------------------------
        // Primary extraction rule: the company-name span. Default hardcoded selector is
        // the data-attrid='Company Name' span. StocksBox has no parse children, so a
        // candidate (mode 3) is resolved by the parent feature id alone — no parse-family
        // widening needed (cf. §9.1).
        // ----------------------------------------------------------------
        $companyNameNode = null;

        $primaryRules = $this->getPrimaryExtractionRules($useDbRules, $isMobile, $additionalRule);
        if (!empty($primaryRules)) {
            $companyNameNode = $dom->getXpath()->query(implode(' | ', $primaryRules), $node)->item(0);
        }

        // Hardcoded fallback (always kept as safety net). Google replaced the old
        // `data-attrid='Company Name'` span with the finance stock-price module, so that
        // selector matches nothing on current SERPs. The stable, unique per-box anchor is
        // the stock-price quote (`data-attrid='Price'`); its presence is what makes this a
        // stocks box. (Company name has no stable selector in the current markup; the
        // extracted value is internal-only — TOP5_COMPARE_INTERNAL_ONLY — so the price
        // label is sufficient to record presence.)
        if (empty($companyNameNode)) {
            $companyNameNode = $dom->getXpath()->query("descendant::*[@data-attrid='Price']", $node)->item(0);
        }

        if (!empty($companyNameNode)) {
            $companyName = trim($companyNameNode->textContent);
            $resultSet->addItem(new BaseResult(NaturalResultType::STOCKS_BOX, [$companyName], $node, $this->hasSerpFeaturePosition));
        }
    }

    /**
     * Resolve the primary company-name extraction rule(s) for the current parser mode.
     * Returns an empty array when there are no applicable DB rules (or in hardcoded mode),
     * which makes parse() fall back to the hardcoded 'Company Name' span selector.
     *
     * StocksBox has no parse children, so a candidate (mode 3) is resolved by the parent
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
