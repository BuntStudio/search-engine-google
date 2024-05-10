<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class FlightSites implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('class'), 'zJUuqf') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $flightSitesItems = $googleDOM->getXpath()->query('ancestor::div[contains(concat(" ", @class, " "), " Ww4FFb ")]/descendant::div[@role="list"]/*[contains( @jscontroller,"QQ51Ce" )]', $node);

        if ($flightSitesItems->length > 1) {
            $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHT_SITES, ['count' => $flightSitesItems->length], $node));
        }
    }
}
