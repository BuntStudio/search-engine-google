<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class TopSights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($dom->getXpath()->query(".//div[contains(@class, 'T6zPgb') and contains(@class, 'YC72Wc')]", $node)->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $topSightsNodes = $googleDOM->getXpath()->query('descendant::a[contains(concat(\' \', normalize-space(@class), \' \'), \' hHB9mc \')]', $node);
        if ($topSightsNodes->length > 0) {
            $items = [];
            for ($i = 0; $i < $topSightsNodes->length; $i++) {
                if (!empty($topSightsNodes->item($i))) {
                    $items[] = $topSightsNodes->item($i)->getAttribute('href');
                }
            }
            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }

    }
}
