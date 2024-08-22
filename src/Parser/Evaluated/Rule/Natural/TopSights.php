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
        if ($node->getAttribute('class') == 'RyIFgf') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $topSightsNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), 'XRVJtc')]", $node);
        if ($topSightsNodes->length > 0) {
            $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }
}
