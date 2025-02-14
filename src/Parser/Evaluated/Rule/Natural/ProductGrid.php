<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class ProductGrid implements ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->getAttribute('jscontroller') == 'wuEeed') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        //get all ul li
        $productGridNodes = $dom->getXpath()->query('.//li', $node);
        if ($productGridNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $productGridNodes->length; $i++) {
                if (!empty($productGridNodes->item($i))){
//                    $items[] = $productGridNodes->item($i)->getNodeValue();
                    $items[] = $this->getNoteTextValue($productGridNodes->item($i));
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PRODUCT_GRID, $items, $node, $this->hasSerpFeaturePosition));
            }
        }
    }

    public function getNoteTextValue($node) {
        $text = '';
        if (get_class($node) != 'Serps\Core\Dom\DomElement') {
            if (get_class($node) == 'DOMText') {
                $text = $node->nodeValue;
            }
        } else if ($node->getChildren()->length == 0) {
            $text = $node->getNodeValue();
        } else {
            foreach ($node->getChildren() as $child) {
                $text .= ' ' . $this->getNoteTextValue($child);
            }
        }
        return trim($text);
    }
}
