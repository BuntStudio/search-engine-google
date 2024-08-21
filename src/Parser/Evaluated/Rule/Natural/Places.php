<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class Places implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'kuydt') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $placesNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' rllt__link') or
        contains(concat(' ', normalize-space(@class), ' '), 'vwVdIc ')]", $node);
        $items         = [];
        if ($placesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesNodes->length; $i++) {
                if (!empty($placesNodes->item($i))){
                    $items[] = $placesNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES, $items));
            }
        }

    }
}
