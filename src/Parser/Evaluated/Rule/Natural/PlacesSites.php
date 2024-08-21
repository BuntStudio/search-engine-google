<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class PlacesSites implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'EyBnad') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $placesSitesNodes = $googleDOM->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' KTsHxd \')]', $node);
        if ($placesSitesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesSitesNodes->length; $i++) {
                if (!empty($placesSitesNodes->item($i))){
                    $items[] = $placesSitesNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES_SITES, $items));
            }
        }

    }
}
