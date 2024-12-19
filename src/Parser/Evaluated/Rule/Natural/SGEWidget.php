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

    protected $removeStyles = true;
    protected $removeScripts = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('jsname') == 'ZLxsqf' && $this->isWidget($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('jscontroller') == 'FAhUS' && $this->isWidget($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') =='eKIzJc' && $this->isWidget($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::SGE_WIDGET_MOBILE : NaturalResultType::SGE_WIDGET;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) { return; }
        $resultSet->addItem(new BaseResult($this->getType($isMobile), $this->extractWidgetData($dom, $node), $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function isWidget(GoogleDom $dom, $node)
    {
        $generateButton = $dom->xpathQuery('descendant::div[@jsname="B76aWe"]', $node);
        return $generateButton->length == 0;
    }

    protected function isWidgetLoaded(GoogleDom $dom, $node)
    {
        $widgetContent = $dom->xpathQuery("descendant::*[contains(concat(' ', normalize-space(@id), ' '), 'folsrch-')]", $node);

        return $widgetContent->length > 0;
    }

    protected function extractWidgetData($dom, $node)
    {
        $sgec = $this->transformNode($dom, clone($node));
        $node = $this->transformNode($dom, $node, $this->removeStyles, $this->removeScripts);
        $data = [
            NaturalResultType::SGE_WIDGET_BASE    => $sgec->ownerDocument->saveHTML($sgec),
            NaturalResultType::SGE_WIDGET_CONTENT => $node->ownerDocument->saveHTML($node),
            NaturalResultType::SGE_WIDGET_LOADED  => $this->isWidgetLoaded($dom, $node),
            NaturalResultType::SGE_WIDGET_LINKS   => [],
        ];
        $linkElements = $dom->xpathQuery('descendant::div[@data-attrid="SGEAttributionFeedback"]', $node);
        if ($linkElements->length == 0) {
            $linkElements = $dom->xpathQuery('descendant::*[@class="BOThhc"]//descendant::*[@class="LLtSOc"]', $node);
        }
        if ($linkElements->length > 0) {
            foreach ($linkElements as $cage) {
                $link = $dom->xpathQuery('descendant::a', $cage)->item(0);
                if (empty($link)) {
                    $link = $cage;
                }
                $title = $link->getAttribute('aria-label');
                if (empty($title)) {
                    $title = $dom->xpathQuery('descendant::*[@class="mNme1d tNxQIb"]', $cage);
                    if (!empty($title) && !empty($title->length)) {
                        $title =  $title->item(0)->textContent;
                    }
                }
                $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                    'title' => $link ? $link->getAttribute('aria-label') : '',
                    'url'   => $link ? \SM_Rank_Service::getUrlFromGoogleTranslate($link->getAttribute('href')) : '',
                    'html'  => $cage->ownerDocument->saveHTML($cage),
                ];
            }
        }
        return $data;
    }

    protected function transformNode($dom, $node, $removeStyles = false, $removeScripts = false)
    {
        // Delete <style> tags
        if ($removeStyles) {
            $styleTags = $dom->xpathQuery('//style', $node);
            foreach ($styleTags as $styleTag) $styleTag->parentNode->removeChild($styleTag);
        }

        // Delete <script> tags
        if ($removeScripts) {
            $scriptTags = $dom->xpathQuery('//script', $node);
            foreach ($scriptTags as $scriptTag) $scriptTag->parentNode->removeChild($scriptTag);
        }

        // Remove min-height from inline style attribute
        $maxHeightDivs = $dom->xpathQuery('descendant::div[@class="h7Tj7e"]', $node);
        foreach ($maxHeightDivs as $st) $st->removeAttribute('style');

        // Remove the "Show more" button and overlay div
        $showMoreButton = $dom->xpathQuery('descendant::div[@jsname="rPRdsc"]', $node);
        foreach ($showMoreButton as $button) $button->parentNode->removeChild($button);
        $overlayDiv = $dom->xpathQuery('.//div[contains(@class, "RDmXvc")]', $node);
        foreach ($overlayDiv as $overlay) $overlay->parentNode->removeChild($overlay);

        // Return the node
        return $node;
    }
}
