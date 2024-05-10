<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class PlacesSites implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('class'), 'KYLHhb') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $placesSitesItems = $googleDOM->getXpath()->query('descendant::*[contains( @jscontroller,"QQ51Ce" )]   ', $node);

        if ($placesSitesItems->length > 1) {
            $resultSet->addItem(new BaseResult(NaturalResultType::PLACES_SITES, ['places_count' => $placesSitesItems->length], $node));
        }
    }
}
