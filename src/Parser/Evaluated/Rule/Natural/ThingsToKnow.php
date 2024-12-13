<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class ThingsToKnow implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'EyBRub') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $thingsToKnowNodes = $dom->getXpath()->query('descendant::div[contains(concat(\' \', normalize-space(@class), \' \'), \' trNcde \')]', $node);
        if ($thingsToKnowNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $thingsToKnowNodes->length; $i++) {
                if (!empty($thingsToKnowNodes->item($i))) {
                    $item = $thingsToKnowNodes->item($i);
                    $items[] = $item->getNodeValue();//preg_replace('/#:~:text.*?$/i','', $thingsToKnowNodes->item($i)->getElementsByTagName('a')->item(0)->getAttribute('href'));
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::THINGS_TO_KNOW, $items, $node, $this->hasSerpFeaturePosition));
            }
        }
    }
}
