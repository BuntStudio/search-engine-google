<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;

class ThingsToKnow implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
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
     * Things to Know is desktop-only (no ThingsToKnowMobile sibling), so both resolve to the
     * desktop name; the mobile name is kept symmetric for the parse() helper only.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'things_to_know_mobile' : 'things_to_know';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; mode 1 uses live rules. The match() context node is
            // the candidate element itself, so rules use the self:: axis (§9.2).
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['things_to_know_match'])
                : RuleLoaderService::getRulesForFeature('things_to_know_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules found — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('class') == 'EyBRub') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Primary extraction rule: per-topic item divs (default 'descendant::div[... trNcde ...]').
        // ThingsToKnow has no parse children, so a candidate (mode 3) is resolved by the parent
        // feature id alone — there is no parse-family to widen across (cf. §9.1).
        $itemXpath = 'descendant::div[contains(concat(\' \', normalize-space(@class), \' \'), \' trNcde \')]';

        $primaryRules = $this->getPrimaryExtractionRules($useDbRules, $isMobile, $additionalRule);
        if (!empty($primaryRules)) {
            $itemXpath = implode(' | ', $primaryRules);
        }

        $thingsToKnowNodes = $dom->getXpath()->query($itemXpath, $node);
        if ($thingsToKnowNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $thingsToKnowNodes->length; $i++) {
                if (!empty($thingsToKnowNodes->item($i))) {
                    $item = $thingsToKnowNodes->item($i);
                    $items[] = $item->getNodeValue();//preg_replace('/#:~:text.*?$/i','', $thingsToKnowNodes->item($i)->getElementsByTagName('a')->item(0)->getAttribute('href'));
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::THINGS_TO_KNOW, $items, $node, $this->hasSerpFeaturePosition));
            }
        }
    }

    /**
     * Resolve the primary topic-item extraction rule(s) for the current parser mode.
     * Returns an empty array when there are no applicable DB rules (or in hardcoded mode),
     * which makes parse() fall back to the hardcoded 'trNcde' selector.
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
