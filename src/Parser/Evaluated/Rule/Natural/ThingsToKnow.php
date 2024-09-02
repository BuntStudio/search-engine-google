<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class ThingsToKnow implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'EyBRub') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $thingsToKnowNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' PZPZlf ')]", $node);
        //$urlNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' csDOgf ')]", $node);
        if ($thingsToKnowNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $thingsToKnowNodes->length; $i++) {
                $item = $thingsToKnowNodes->item($i);
                //$url =  $urlNodes->item($i)->firstChild;//$urlNodes->item($i)->getAttribute('data-id');
                if (!empty($item)) {
                    $title = $item->firstChild->textContent;
                    $items[] = ['title' => $title];// todo add urls, 'url' => str_replace('atritem-', '', $url)
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::THINGS_TO_KNOW, $items));
            }
        }
    }
}
