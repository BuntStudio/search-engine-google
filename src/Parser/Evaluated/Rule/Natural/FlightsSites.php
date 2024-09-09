<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class FlightsSites implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public $hasSerpFeaturePosition = true;
    
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'XNfAUb') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $urlsNodes = $googleDOM->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' ddkIM \')]', $node);
        if ($urlsNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $urlsNodes->length; $i++) {
                if (!empty($urlsNodes->item($i))) {
                    $items[] = $urlsNodes->item($i)->getAttribute('href');
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS_SITES, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }
}
