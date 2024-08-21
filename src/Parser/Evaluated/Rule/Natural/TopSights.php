<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class TopSights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'RyIFgf' || $node->getAttribute('class') == 'jhtnKe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $topSightsNodes = $googleDOM->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' ddkIM \')]', $node);
        if ($topSightsNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $topSightsNodes->length; $i++) {
                if (!empty($topSightsNodes->item($i))) {
                    $items[] = $topSightsNodes->item($i)->getNodeValue();
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, $items, [], $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }

    }
}
