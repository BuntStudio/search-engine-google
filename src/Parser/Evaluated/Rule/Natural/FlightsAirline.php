<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

/**
 * Flights — Airlines sub-module (FLA).
 *
 * NOTE (2026-06-30): this feature was REMOVED from the self-healing parser (SHP) — it had never
 * been detected on a single tracked keyword in the full historical record, and its selectors
 * (gate `sATSHe` / links `s2sa1c`) do not appear in real Google flights SERPs (Google retired the
 * inline airline-links sub-module). The SHP DB-rule wiring was stripped and the feature/rule rows
 * deleted (migrations/serp_parser_remove_flights_airlines_feature_2026-06-30.sql). The legacy
 * HARDCODED detection below is intentionally KEPT as-is (it produces has_flights_airlines for the
 * rank plumbing, currently always 0) but is no longer DB-driven / healable.
 *
 * The match()/parse() signatures still accept the $useDbRules / $additionalRule params that
 * AbstractParser passes to every rule, but they are ignored here (hardcoded-only).
 */
class FlightsAirline implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = 0)
    {
        // Hardcoded detection (no longer DB-driven — removed from SHP 2026-06-30).
        if ($node->getAttribute('class') == 'sATSHe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        // Hardcoded extraction (no longer DB-driven — removed from SHP 2026-06-30).
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
}
