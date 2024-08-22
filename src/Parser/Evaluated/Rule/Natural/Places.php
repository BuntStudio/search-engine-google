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
        if ($node->getAttribute('id') == 'rcnt') {
            return self::RULE_MATCH_MATCHED;
        }

        if ( $node->getAttribute('class') == 'GyAeWb') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $placesNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' rllt__link') or
        contains(concat(' ', normalize-space(@class), ' '), 'vwVdIc ')]", $node);
        $items         = [];
        if ($placesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesNodes->length; $i++) {
                if (!empty($placesNodes->item($i))){
                    $value = $placesNodes->item($i)->getNodeValue();
                    if (!empty($value)) {
                        $items[] = $value;
                    }
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES, $items));
            }
        }

    }
}
