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
        // Keep a clone of the real DOM; We're transforming the node, so we need to keep the original for later use
        $originalDom = clone $dom->getDom();

        $sgec = $this->transformNode($dom, clone($node));
        $node = $this->transformNode($dom, $node, $this->removeStyles, $this->removeScripts);
        $data = [
            NaturalResultType::SGE_WIDGET_BASE    => $sgec->ownerDocument->saveHTML($sgec),
            NaturalResultType::SGE_WIDGET_CONTENT => $node->ownerDocument->saveHTML($node),
            NaturalResultType::SGE_WIDGET_LOADED  => $this->isWidgetLoaded($dom, $node),
            NaturalResultType::SGE_WIDGET_LINKS   => [],
        ];
        $linkElements0 = $dom->xpathQuery('descendant::div[@data-attrid="SGEAttributionFeedback"]', $node);

        $linkElements1 = $dom->xpathQuery('descendant::*[@class="BOThhc"]//descendant::*[@class="LLtSOc"]', $node);

        $linkElements2 = $dom->xpathQuery('descendant::*[@jscontroller="g4PEk"]//descendant::*[@class="LLtSOc"]', $node);

        $linkElements3 = $dom->xpathQuery('descendant::*[@class="uVhVib"]', $node);

        $urls = [];

        if ($linkElements0->length > 0) {
            $this->processLinkElements($dom, $linkElements0, $urls, $data);
        }

        if ($linkElements1->length > 0) {
            $this->processLinkElements($dom, $linkElements1, $urls, $data);
        }

        if ($linkElements2->length > 0) {
            $this->processLinkElements($dom, $linkElements2, $urls, $data);
        }

        if ($linkElements3->length > 0) {
            $this->processLinkElements($dom, $linkElements3, $urls, $data);
        }

        if (!empty($urls)) {
            $this->processScriptElements($originalDom, $urls, $data);
        }

        if (!empty($urls)) {
            $this->processMagiFeature($originalDom, $urls, $data);
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

        // Disabled for now, as requested in #86991adgz
        // Remove the "Show more" button
//        $showMoreButton = $dom->xpathQuery('descendant::div[@jsname="rPRdsc"]', $node);
//        foreach ($showMoreButton as $button) $button->parentNode->removeChild($button);

        // Remove the overlay div
        $overlayDiv = $dom->xpathQuery('.//div[contains(@class, "RDmXvc")]', $node);
        foreach ($overlayDiv as $overlay) $overlay->parentNode->removeChild($overlay);

        // Return the node
        return $node;
    }

    private function processLinkElements($dom, $elements, &$urls, &$data) {
        foreach ($elements as $cage) {
            $link = $dom->xpathQuery('descendant::a', $cage)->item(0);
            if (empty($link)) {
                $link = $cage;
            }

            $title = $link->getAttribute('aria-label');
            if (empty($title)) {
                $title = $dom->xpathQuery('descendant::*[@class="mNme1d tNxQIb"]', $cage);
                if (!empty($title) && !empty($title->length)) {
                    $title = $title->item(0)->textContent;
                } else {
                    $title = $cage->textContent;
                }
            }

            $url = \SM_Rank_Service::getUrlFromGoogleTranslate($link->getAttribute('href'));

            if (in_array($url, $urls)) {
                continue;
            }

            $urls[] = $url;

            $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                'title' => $title,
                'url' => $url,
                'html' => $cage->ownerDocument->saveHTML($cage),
            ];
        }
    }

    private function processScriptElements($dom, array &$urls, array &$data)
    {
        $xpath = new \DOMXPath($dom);
        $scripts = $xpath->query('//script[contains(., "//' . parse_url($urls[0], PHP_URL_HOST) . '") and contains(.,"AI Overview")]/text()');

        if ($scripts->length > 0) {
            $scriptContent = $scripts[0]->nodeValue;

            // Find section with AI Overview, with variable spacing around the marker and surrounding quotes
            $overviewRegex = '/data-fburl="([^"#]*)(?:#:~:text=([^"&]*))?"/Uis';

            if (preg_match_all($overviewRegex, stripcslashes($scriptContent), $overviewMatches)) {
                foreach ($overviewMatches[1] as $key => $url) {
                    if (in_array($url, $urls)) {
                        continue;
                    }

                    $urls[] = $url;
                    $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                        'title' => rawurldecode($overviewMatches[2][$key]), // TODO: Maybe detect this, too?
                        'url' => $url,
                        'html' => '',
                    ];
                }
            } else {
                // TODO: Maybe log?
            }
        } else {
            // TODO: Maybe log?
        }
    }

    private function processMagiFeature($originalDom, array &$urls, array &$data)
    {
        $scriptContent = $originalDom->saveHTML();

        // This regex looks for array patterns that start with "MAGI_FEATURE"
        // It handles the specific format of these arrays
        $magiFeaturePattern = '/\],\"C[a-zA-Z0-9]{5}[^:]*:.*\["MAGI_FEATURE".*\[null,1,(\[[^[]*\])/Uis';

        // Extract all arrays that match our pattern
        preg_match_all($magiFeaturePattern, $scriptContent, $foundArrays);
        $magiFeatureArrays = $foundArrays[1] ?? [];

        // Extract URLs from the MAGI_FEATURE arrays
        $urlPattern = '/(https?:\/\/[^\s"\',]+)/';
        $extractedUrls = [];

        foreach ($magiFeatureArrays as $json) {
            $array = json_decode($json, true);

            if (empty($array)) {
                continue;
            }

            if (!isset($array[4]) || !isset($array[6])) {
                continue;
            }

            $title = $array[4];
            $url = $array[6];

            if (in_array($url, $urls)) {
                continue;
            }

            $urls[] = $url;
            $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                'title' => $title,
                'url' => $url,
                'html' => '',
            ];
        }
    }
}
