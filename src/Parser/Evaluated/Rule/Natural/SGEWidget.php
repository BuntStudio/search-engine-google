<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class SGEWidget implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('jsname') == 'ZLxsqf' && $this->hasWidget($node)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') =='eKIzJc' && $this->hasWidget($node)) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::SGE_WIDGET_MOBILE : NaturalResultType::SGE_WIDGET;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) { return; }
        $resultSet->addItem(new BaseResult($this->getType($isMobile), $this->extractWidgetData($googleDOM, $node), $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function hasWidget(GoogleDom $dom, $node)
    {
        $generateButton = $dom->xpathQuery('descendant::div[data-attrid="SGEParagraphFeedback"]', $node);
        return true;
    }

    protected function extractWidgetData(GoogleDom $googleDOM, $node)
    {
        $data = [
            NaturalResultType::SGE_WIDGET_CONTENT => $node->textContent,
            NaturalResultType::SGE_WIDGET_LINKS   => [],
        ];
        $linkElements = $googleDOM->xpathQuery('descendant::div[@data-attrid="SGEAttributionFeedback"]', $node);
        foreach ($linkElements as $cage) {
            $link = $googleDOM->xpathQuery('descendant::a[@class="ddkIM"]', $cage);
            $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                'title'          => $link->getAttribute('aria-label'),
                'url'            => $link->getAttribute('href'),
                'container_text' => $cage->textContent,
            ];
        }
        return $data;
    }
}
