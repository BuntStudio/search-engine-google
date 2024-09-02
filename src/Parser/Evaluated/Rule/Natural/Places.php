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
        if ($node->getAttribute('class') == 'x3SAYd') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $placesNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' dbg0pd ')]", $node);
        $items = [];
        if ($placesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesNodes->length; $i++) {
                if (!empty($placesNodes->item($i))) {
                    $item = $placesNodes->item($i);
                    $parent = $item->parentNode;
                    $name = trim($item->textContent);
                    $urlNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), 'MRe4xd')]", $parent);
                    if ($urlNodes->length > 0) {
                        $url = $urlNodes->item(0)->getAttribute('href');
                        $items[] = ['name' => $name, 'url' => $url];
                    }
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES, $items));
            }
        }

    }
}
