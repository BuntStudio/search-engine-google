<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class FlightsAirline implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'sATSHe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $onlyRemoveSrsltidForDomain = '')
    {
        $urlsNodes = $dom->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' s2sa1c \')]', $node);
        if ($urlsNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $urlsNodes->length; $i++) {
                if (!empty($urlsNodes->item($i))) {
                    $items[] = $urlsNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS_AIRLINE, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }
}
