<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class PlacesSites implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'RyIFgf') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        //todo is we have already added places
        $placesSitesNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' ddkIM ')]", $node);
        if ($placesSitesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesSitesNodes->length; $i++) {
                if (!empty($placesSitesNodes->item($i))){
                    $item = $placesSitesNodes->item($i);
                    $parent = $item->parentNode;
                    $nameNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' VaiWld ')]", $parent);
                    if ($nameNodes->length > 0) {
                        $name = trim($nameNodes->item(0)->textContent);
                    } else {
                        $name = trim($parent->textContent);
                    }
                    $items[] = ['name' => $name, 'url' => $item->getAttribute('href')];
                }
            }

            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES_SITES, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }
}
