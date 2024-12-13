<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class FlightAirlineOptions implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('jscontroller'), 'hKbgK') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $flightAirlineOptions = $dom->getXpath()->query('descendant::div[@role="list"]/descendant::*[@role="listitem"]', $node);

        if ($flightAirlineOptions->length > 1) {
            $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHT_AIRLINE_OPTIONS, ['count' => $flightAirlineOptions->length], $node));
        }
    }
}
