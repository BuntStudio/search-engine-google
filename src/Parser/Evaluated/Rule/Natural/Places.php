<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class Places implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Get the feature name based on mobile flag.
     * Places is desktop-only (no mobile Places parser class), but keep the signature
     * consistent with the other integrated rule classes.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'places_mobile' : 'places';
    }

    /**
     * Get the match feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'places_mobile_match' : 'places';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container check (class 'ixix9e').
        // Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['places_match'])
                : RuleLoaderService::getRulesForFeature('places_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('class') == 'ixix9e') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary listing-detail extraction (rllt__details)
        // lives in the 'places' parent feature.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // Places has no parse children — resolve candidate rules by this feature only.
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
                // DB rules matched nothing — fall through to hardcoded below.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded.
        }

        // Hardcoded fallback
        $placesNodes = $dom->getXpath()->query(".//*[@class='rllt__details']", $node);
        $items         = [];
        if ($placesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesNodes->length; $i++) {
                if (!empty($placesNodes->item($i))){
                    $items[] = $placesNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }

    /**
     * Extract listing-detail values using DB rules (primary rllt__details pattern).
     * Returns true when at least one listing was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $placesNodes = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('Places DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($placesNodes->length == 0) {
            return false;
        }

        $items = [];
        for ($i = 0; $i < $placesNodes->length; $i++) {
            if (!empty($placesNodes->item($i))) {
                $items[] = $placesNodes->item($i)->getNodeValue();
            }
        }

        if (count($items)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::PLACES, $items, $node, $this->hasSerpFeaturePosition));
            return true;
        }

        return false;
    }
}
