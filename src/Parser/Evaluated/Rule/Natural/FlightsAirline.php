<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class FlightsAirline implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    /**
     * Parser mode constants for self-healing parser integration.
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Feature name for this rule. Flights-airlines is DESKTOP ONLY (the class is registered only in
     * NaturalParser; there is no mobile sibling — §9.6), so there is no _mobile variant.
     */
    protected static function getFeatureName()
    {
        return 'flights_airlines';
    }

    /**
     * Match feature name (container gate). Desktop only.
     */
    protected static function getMatchFeatureName()
    {
        return 'flights_airlines_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB-driven detection. Match rules run with the candidate element as the context node, so they
        // are stored in self:: axis form (§9.2). Candidate testing (mode 3) consults the heal candidate
        // so a renamed container can validate; mode 1 uses the live rules. Desktop-only feature, so we
        // consult only the single flights_airlines_match gate.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures([self::getMatchFeatureName()])
                : RuleLoaderService::getRulesForFeature(self::getMatchFeatureName());

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded detection.
        }

        // Hardcoded fallback detection (always kept as a safety net).
        if ($node->getAttribute('class') == 'sATSHe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary airline-link extraction lives in the 'flights_airlines' parent
        // feature. FlightsAirline has no parse children, so candidate rules (mode 3) resolve by this
        // feature only. The hardcoded path below stays as a fallback.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName();

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
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

        // Hardcoded fallback extraction.
        $urlsNodes = $dom->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' s2sa1c \')]', $node);
        if ($urlsNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $urlsNodes->length; $i++) {
                if (!empty($urlsNodes->item($i))) {
                    $items[] = $urlsNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS_AIRLINE, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }

    /**
     * Extract airline-link node values using DB rules (the primary descendant::a s2sa1c selector).
     * Returns true when at least one airline link was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $urlsNodes = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('FlightsAirline DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($urlsNodes->length == 0) {
            return false;
        }

        $items = [];
        for ($i = 0; $i < $urlsNodes->length; $i++) {
            if (!empty($urlsNodes->item($i))) {
                $items[] = $urlsNodes->item($i)->getNodeValue();
            }
        }

        if (count($items)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS_AIRLINE, $items, $node, $this->hasSerpFeaturePosition));
            return true;
        }

        return false;
    }
}
