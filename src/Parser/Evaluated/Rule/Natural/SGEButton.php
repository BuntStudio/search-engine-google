<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class SGEButton implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('jsname') == 'ZLxsqf' && $this->hasButton($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') =='eKIzJc' && $this->hasButton($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::SGE_BUTTON_MOBILE : NaturalResultType::SGE_BUTTON;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) {
            return;
        }
        $resultSet->addItem(new BaseResult($this->getType($isMobile), [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function hasButton(GoogleDom $dom, $node)
    {
        $generateButton = $dom->xpathQuery('descendant::div[@jsname="B76aWe"]', $node);
        return $generateButton->length > 0;
    }
}
