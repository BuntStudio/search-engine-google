<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class SGEWidget implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    protected $removeStyles = true;
    protected $removeScripts = true;

    protected $jslDhCallsCount = 0;
    protected $aioIdFound = false;

    /**
     * Get the feature name based on mobile flag.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'sge_widget_mobile' : 'sge_widget';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = array_unique(array_merge(
                RuleLoaderService::getRulesForFeature('sge_widget_match'),
                RuleLoaderService::getRulesForFeature('sge_widget_mobile_match')
            ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                if ($matchResult->length > 0) {
                    return $this->isWidget($dom, $node, $useDbRules) ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
                }
                return self::RULE_MATCH_NOMATCH;
            }
            // No DB rules found — fall through to hardcoded
        }

        if ($node->getAttribute('jsname') == 'ZLxsqf' && $this->isWidget($dom, $node, $useDbRules)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') =='eKIzJc' && $this->isWidget($dom, $node, $useDbRules)) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::SGE_WIDGET_MOBILE : NaturalResultType::SGE_WIDGET;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $localNode = clone $node;

        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) { return; }
        $resultSet->addItem(new BaseResult($this->getType($isMobile), $this->extractWidgetData($dom, $localNode, $useDbRules, $isMobile), $localNode, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function isWidget(GoogleDom $dom, $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $buttonRules = array_unique(array_merge(
                RuleLoaderService::getRulesForFeature('sge_widget_button_detection'),
                RuleLoaderService::getRulesForFeature('sge_widget_mobile_button_detection')
            ));

            if (!empty($buttonRules)) {
                $buttonXpath = implode(' | ', $buttonRules);
                $generateButton = $dom->xpathQuery($buttonXpath, $node);
                return $generateButton->length == 0;
            }
            // No DB rules — fall through to hardcoded
        }

        $generateButton = $dom->xpathQuery('descendant::div[@jsname="B76aWe"]', $node);
        return $generateButton->length == 0;
    }

    protected function isWidgetLoaded(GoogleDom $dom, $node, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        // Widget loaded checks remain hardcoded — they have complex positional semantics
        // (progressbar visibility → return false, folsrch presence → return true, bsmXxe fallback → return true)
        // that don't fit the "interchangeable rules" DB model. The rules are stored in DB for
        // tracking/investigation but the actual logic stays here.

        // Check if there's a visible progressbar inside a folsrch container (AIO content area)
        // The AIO progressbar lives in a folsrch-* sibling element, not inside the widget node itself
        $progressBar = $dom->xpathQuery('//div[starts-with(@id, "folsrch-")]//div[@role="progressbar"]', $node);
        if ($progressBar->length > 0) {
            // Check if any AIO progressbar is actually visible (not hidden by CSS)
            foreach ($progressBar as $bar) {
                if ($this->isElementVisible($bar)) {
                    return false;
                }
            }
        }

        // folsrch-* elements are siblings of the widget node, not descendants — search from document root
        $widgetContent = $dom->xpathQuery('//div[starts-with(@id, "folsrch-")]', $node);

        if ($widgetContent->length > 0) {
            return true;
        }

        // Fallback: if folsrch- is not found but bsmXxe elements were enriched with children, consider loaded
        $enrichedElements = $dom->xpathQuery('descendant::*[contains(@class, "bsmXxe") and ./*]', $node);
        if ($enrichedElements->length > 0) {
            return true;
        }

        return false;
    }

    protected function isElementVisible($element)
    {
        $current = $element;

        while ($current && $current instanceof \DOMElement) {
            // Check if element has style attribute with display:none or visibility:hidden
            if ($current->hasAttribute('style')) {
                $style = $current->getAttribute('style');

                // Check for display:none
                if (preg_match('/display\s*:\s*none/i', $style)) {
                    return false;
                }

                // Check for visibility:hidden
                if (preg_match('/visibility\s*:\s*hidden/i', $style)) {
                    return false;
                }
            }

            // Check if element has a class that might indicate it's hidden
            if ($current->hasAttribute('class')) {
                $classes = $current->getAttribute('class');

                // Common hidden classes in Google's UI
                $hiddenClasses = ['hidden', 'invisible', 'hide'];
                foreach ($hiddenClasses as $hiddenClass) {
                    if (strpos($classes, $hiddenClass) !== false) {
                        return false;
                    }
                }
            }

            // Traverse to the parent node
            $current = $current->parentNode;
        }

        // If no obvious hiding styles/classes found in the element or its ancestors, consider it visible
        return true;
    }

    protected function extractWidgetData($dom, $node, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        // Keep a clone of the real DOM; We're transforming the node, so we need to keep the original for later use
        $originalDom = clone $dom->getDom();

        $urls = [];
        $data = [
            NaturalResultType::SGE_WIDGET_LOADED  => false,
            NaturalResultType::SGE_WIDGET_LINKS   => [],
            NaturalResultType::SGE_WIDGET_BASE    => '',
            NaturalResultType::SGE_WIDGET_CONTENT => '',
            NaturalResultType::SGE_WIDGET_DIAGNOSTICS => [
                'jsl_dh_calls_count' => 0,
                'widget_not_loaded_reason' => null,
                'link_selectors_matched' => [],
                'content_length' => 0,
                'aio_id_found' => false,
            ],
        ];

        $this->jslDhCallsCount = 0;
        $this->aioIdFound = false;

        // First, enrich the content with all dynamic data
        $this->enrichContentWithDynamicData($dom, $node, $originalDom);

        // Check again if widget is loaded after enrichment (progressbar might be visible after enrichment)
        $data[NaturalResultType::SGE_WIDGET_LOADED] = $this->isWidgetLoaded($dom, $node, $useDbRules, $isMobile);

        // If widget is not loaded (progressbar visible), don't extract content
        if (!$data[NaturalResultType::SGE_WIDGET_LOADED]) {
            $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['widget_not_loaded_reason'] = 'progressbar_visible_or_no_folsrch';
            $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['jsl_dh_calls_count'] = $this->jslDhCallsCount;
            return $data;
        }

        // Remove display:none styles from all elements in the node
//        $this->removeDisplayNoneFromAllElements($dom, $node);

        // Hide specific element with ID folsrch-sqf-1 if it exists
        $this->hideElementById($dom, $node, 'folsrch-sqf-1');

        // Process AIO links from window.jsl.dh() calls
        $this->enrichAioLinksFromDynamicData($dom, $node, $originalDom, $urls, $data, $useDbRules, $isMobile);

        // Remove all button and svg elements
        $this->removeElements($dom, $node);

        $this->removeSvgElements($dom, $node);

        // Add display:block to OS7YA elements after everything is extracted
        $this->addDisplayBlockToOS7YA($dom, $node);

        // Remove specific classes from all elements
        $this->removeSpecificClasses($dom, $node);

        // Now save the base content with all enrichments but before style/script removal
        $baseNode = $this->transformNode($dom, clone($node), false, false, $useDbRules, $isMobile);
        $data[NaturalResultType::SGE_WIDGET_BASE] = $baseNode->ownerDocument->saveHTML($baseNode);

        // Now remove styles and scripts for the processed content
        $node = $this->transformNode($dom, $node, $this->removeStyles, $this->removeScripts, $useDbRules, $isMobile);

        // Save the processed content after style/script removal
        $data[NaturalResultType::SGE_WIDGET_CONTENT] = $node->ownerDocument->saveHTML($node);

        // Collect link elements AFTER AIO enrichment and node removal
        $this->extractLinkElements($dom, $node, $urls, $data, $useDbRules, $isMobile);

//        if (!empty($urls)) {
//            $this->processScriptElements($originalDom, $urls, $data);
//        }

        if (!empty($urls)) {
            $this->processMagiFeature($originalDom, $urls, $data);
        }

        // Populate final diagnostics
        $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['jsl_dh_calls_count'] = $this->jslDhCallsCount;
        $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['aio_id_found'] = $this->aioIdFound;
        $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['content_length'] = strlen($data[NaturalResultType::SGE_WIDGET_CONTENT]);

        return $data;
    }

    /**
     * Extract link elements using either DB rules or hardcoded selectors.
     * Each rule is run individually (not union) because order matters for deduplication.
     */
    /**
     * Hardcoded link selectors with their diagnostic labels.
     * Order matters — each rule is run individually for deduplication.
     */
    protected static function getHardcodedLinkSelectors()
    {
        return [
            'SGEAttributionFeedback' => 'descendant::div[@data-attrid="SGEAttributionFeedback"]',
            'BOThhc_LLtSOc' => 'descendant::*[@class="BOThhc"]//descendant::*[@class="LLtSOc"]',
            'g4PEk_LLtSOc' => 'descendant::*[@jscontroller="g4PEk"]//descendant::*[@class="LLtSOc"]',
            'uVhVib' => 'descendant::*[@class="uVhVib"]',
            'FqfzXd' => 'descendant::*[@class="FqfzXd"]',
            'NDNGvf' => 'descendant::*[@class="NDNGvf"]',
            'ZZh6Vb' => 'descendant::*[@class="ZZh6Vb"]',
            'target_self' => 'descendant::*[@target="_self"]',
        ];
    }

    /**
     * Extract link elements using either DB rules or hardcoded selectors.
     * Each rule is run individually (not union) because order matters for deduplication.
     */
    protected function extractLinkElements($dom, $node, &$urls, &$data, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        $hardcodedSelectors = self::getHardcodedLinkSelectors();

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featurePrefix = self::getFeatureName($isMobile);
            $linkRules = RuleLoaderService::getRulesForFeature($featurePrefix . '_link_selectors');

            if (!empty($linkRules)) {
                // Build a reverse map from XPath to diagnostic label for consistency
                $xpathToLabel = array_flip($hardcodedSelectors);
                $linkRulesFlip = array_flip($linkRules);

                // Pre-populate with hardcoded labels in correct order so key order matches,
                // but only for rules that still exist in DB (self-healing may deprecate old rules)
                $selectorMatches = [];
                foreach ($hardcodedSelectors as $label => $xpath) {
                    if (isset($linkRulesFlip[$xpath])) {
                        $selectorMatches[$label] = 0;
                    }
                }

                foreach ($linkRules as $index => $rule) {
                    $linkElements = $dom->xpathQuery($rule, $node);
                    // Use the hardcoded label if available, otherwise use indexed key
                    $label = isset($xpathToLabel[$rule]) ? $xpathToLabel[$rule] : 'db_selector_' . $index;
                    $selectorMatches[$label] = $linkElements->length;
                    if ($linkElements->length > 0) {
                        $this->processLinkElements($dom, $linkElements, $urls, $data);
                    }
                }
                $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['link_selectors_matched'] = $selectorMatches;
                return;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded link selectors
        $selectorMatches = [];
        foreach ($hardcodedSelectors as $label => $xpath) {
            $linkElements = $dom->xpathQuery($xpath, $node);
            $selectorMatches[$label] = $linkElements->length;
            if ($linkElements->length > 0) {
                $this->processLinkElements($dom, $linkElements, $urls, $data);
            }
        }
        $data[NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['link_selectors_matched'] = $selectorMatches;
    }

    protected function transformNode($dom, $node, $removeStyles = false, $removeScripts = false, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
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

        // DOM cleanup rules remain hardcoded — they have mixed positional semantics:
        // rule[0] removes a style attribute, rules[1-3] remove elements entirely.
        // The rules are stored in DB for tracking/investigation but logic stays here.

        // Remove min-height from inline style attribute
        $maxHeightDivs = $dom->xpathQuery('descendant::div[@class="h7Tj7e"]', $node);
        foreach ($maxHeightDivs as $st) $st->removeAttribute('style');

        // Remove the "Show more" button (Afișează mai multe)
        $showMoreButton = $dom->xpathQuery('descendant::div[@jsname="rPRdsc"]', $node);
        foreach ($showMoreButton as $button) $button->parentNode->removeChild($button);

        // Remove the privacy notice text (Află mai multe, inclusiv detalii despre date și confidențialitate)
        $privacyNotice = $dom->xpathQuery('descendant::span[@jsname="q9irQd"]', $node);
        foreach ($privacyNotice as $notice) $notice->parentNode->removeChild($notice);

        // Remove the overlay div
        $overlayDiv = $dom->xpathQuery('//div[contains(@class, "RDmXvc")]', $node);
        foreach ($overlayDiv as $overlay) $overlay->parentNode->removeChild($overlay);

        // Return the node
        return $node;
    }

    private function processLinkElements($dom, $elements, &$urls, &$data) {
        foreach ($elements as $cage) {
            // Skip if node has been removed from DOM
            if (!$cage->parentNode) {
                continue;
            }

            try {
                $link = $dom->xpathQuery('descendant::a', $cage)->item(0);
                if (empty($link)) {
                    $link = $cage;
                }

                $title = $link->getAttribute('aria-label');
                if (empty($title)) {
                    $titleElements = $dom->xpathQuery('descendant::*[@class="mNme1d tNxQIb"]', $cage);
                    if ($titleElements && $titleElements->length > 0) {
                        $title = $titleElements->item(0)->textContent;
                    } else {
                        $title = $cage->textContent;
                    }
                }

                $url = \SM_Rank_Service::getUrlFromGoogleTranslate($link->getAttribute('href'));

                // Clean URL hash to improve unique page identification
                $url = $this->cleanUrlHash($url);

                if (in_array($url, $urls)) {
                    continue;
                }

                $urls[] = $url;

                $data[NaturalResultType::SGE_WIDGET_LINKS][] = [
                    'title' => $title,
                    'url' => $url,
                    'html' => $cage->ownerDocument->saveHTML($cage),
                ];
            } catch (\Exception $e) {
                // Skip this element if any DOM operation fails
                continue;
            }
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

            // Clean URL hash to improve unique page identification
            $url = $this->cleanUrlHash($url);

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

    /**
     * Enrich content with data that would normally be loaded on click
     * by looking for elements with class 'bsmXxe', fetching their 'id' value,
     * and looking for 'window.jsl.dh()' calls with matching IDs
     * Recursively processes newly added content for additional bsmXxe elements
     */
    protected function enrichContentWithDynamicData($dom, $node, $originalDom)
    {
        // Get the original DOM content as string to search for jsl.dh() calls
        $originalContent = $originalDom->saveHTML();

        // Extract all jsl.dh() calls from the original content
        $jslCalls = $this->extractJslDhCalls($originalContent);

        // Track for diagnostics
        $this->jslDhCallsCount = count($jslCalls);

        // Process bsmXxe elements recursively
        $this->processBsmXxeElementsRecursively($dom, $node, $jslCalls, []);
    }

    /**
     * Recursively process all elements with IDs that have matching jsl.dh calls within the node
     * @param GoogleDom $dom
     * @param \DomElement $node
     * @param array $jslCalls All available jsl.dh calls
     * @param array $processedIds Track processed IDs to avoid infinite loops
     */
    protected function processBsmXxeElementsRecursively($dom, $node, $jslCalls, $processedIds)
    {
        // Find all elements with IDs within the node
        $elementsWithIds = $dom->xpathQuery('descendant::*[@id]', $node);

        if ($elementsWithIds->length === 0) {
            return;
        }

        $newlyProcessedIds = [];

        // Process each element with an ID
        foreach ($elementsWithIds as $element) {
            $elementId = $element->getAttribute('id');

            if (empty($elementId) || in_array($elementId, $processedIds)) {
                continue;
            }

            // Look for matching jsl.dh() call
            if (isset($jslCalls[$elementId])) {
                $htmlContent = $jslCalls[$elementId];
                $this->injectHtmlContent($dom, $element, $htmlContent);
                $newlyProcessedIds[] = $elementId;
            }
        }

        // If we processed any new IDs, recursively check for more elements with IDs
        if (!empty($newlyProcessedIds)) {
            $updatedProcessedIds = array_merge($processedIds, $newlyProcessedIds);
            $this->processBsmXxeElementsRecursively($dom, $node, $jslCalls, $updatedProcessedIds);
        }
    }

    /**
     * Extract window.jsl.dh() calls from HTML content
     * Returns array with ID as key and HTML content as value
     */
    protected function extractJslDhCalls($htmlContent)
    {
        $jslCalls = [];

        // Pattern 1: Direct jsl.dh('id', 'html') calls
        $pattern1 = '/jsl\.dh\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"](.*)[\'"]\);/U';

        if (preg_match_all($pattern1, $htmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = $match[1];
                $htmlData = $match[2];

                // URL decode and handle escaped characters
                $decodedHtml = $this->decodeJslHtml($htmlData);

                if (!empty($decodedHtml)) {
                    $jslCalls[$id] = $decodedHtml;
                }
            }
        }

        // Pattern 2: [{id:'__ID__'},function(){jsl.dh(this.id, '__CONTENT__')}] calls
        $pattern2 = '/\[{id:[\'"]([^\'"]+)[\'"]},function\(\){(?:window\.)?jsl\.dh\(this\.id,[\'"]([^\']*)[\'"]\)/';

        if (preg_match_all($pattern2, $htmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $id = $match[1];
                $htmlData = $match[2];

                // URL decode and handle escaped characters
                $decodedHtml = $this->decodeJslHtml($htmlData);

                if (!empty($decodedHtml)) {
                    $jslCalls[$id] = $decodedHtml;
                }
            }
        }

            return $jslCalls;
    }

    /**
     * Decode and clean HTML content from jsl.dh() calls
     */
    protected function decodeJslHtml($encodedHtml)
    {
        // Handle JavaScript string escaping (e.g., \x3c becomes <)
        $decoded = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($matches) {
            return chr(hexdec($matches[1]));
        }, $encodedHtml);

        // Handle Unicode escapes (e.g., \u021b becomes ț)
        $decoded = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
            $codepoint = hexdec($matches[1]);
            return mb_chr($codepoint, 'UTF-8');
        }, $decoded);

        // Handle other common JavaScript escapes
        $decoded = str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $decoded);

        // Handle URL-encoded characters (e.g., %C8%9B becomes ț, %C3%AE becomes î)
        $decoded = urldecode($decoded);

        // Handle potential double-encoding or HTML entity issues
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Fix common Romanian diacritic encoding issues
        $decoded = str_replace([
            'Ã¢', 'Ã¡', 'Ã ', 'Ã£', 'Ã¤', 'Ã¦', 'Ã§', 'Ã¨', 'Ã©', 'Ã«', 'Ã¬', 'Ã­', 'Ã¯',
            'Ã±', 'Ã²', 'Ã³', 'Ã´', 'Ã¶', 'Ã¸', 'Ã¹', 'Ã»', 'Ã¼', 'Ã½', 'Ã¿',
            'Ã€', 'Ã‚', 'Ãƒ', 'Ã„', 'Ã…', 'Ã†', 'Ã‡', 'Ãˆ', 'Ã‰', 'ÃŠ', 'Ã‹', 'ÃŒ', 'ÃŽ',
            'Ã\'', 'Ã\'', 'Ã"', 'Ã"', 'Ã•', 'Ã–', 'Ã˜', 'Ã™', 'Ãš', 'Ã›', 'Ãœ', 'Ãž'
        ], [
            'â', 'á', 'à', 'ã', 'ä', 'æ', 'ç', 'è', 'é', 'ë', 'ì', 'í', 'ï',
            'ñ', 'ò', 'ó', 'ô', 'ö', 'ø', 'ù', 'û', 'ü', 'ý', 'ÿ',
            'À', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Î',
            'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Þ'
        ], $decoded);

        // Specific fixes for Romanian diacritics
        $decoded = str_replace([
            'Ã¢', 'Ã¤', 'Ã¢', 'Ã®', 'Ã¯', 'Ã¡', 'Ã ', 'Ã£',
            'Ã‚', 'Ã„', 'Ã‚', 'ÃŽ', 'Ã', 'Ã\'', 'Ã€', 'Ãƒ'
        ], [
            'â', 'ă', 'â', 'î', 'ï', 'á', 'à', 'ã',
            'Â', 'Ă', 'Â', 'Î', 'Í', 'Ó', 'À', 'Ã'
        ], $decoded);

        return $decoded;
    }

    /**
     * Inject HTML content into an element
     */
    protected function injectHtmlContent($dom, $element, $htmlContent)
    {
        try {
            // Convert bsmXxe div element to li element with K3KsMc class if needed
//            $element = $this->convertBsmXxeElementToLi($element);

            // Remove display:none style from the target element
            //$this->removeDisplayNoneFromElement($element);

            // Remove display:none styles before injecting
            $htmlContent = $this->removeDisplayNoneStyles($htmlContent);

            // Process HTML to add required elements and classes
            $htmlContent = $this->processHtmlForInjection($htmlContent);

            // Create a temporary document to parse the HTML
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');

            // Wrap content in a div to ensure proper parsing
            $wrappedContent = '<div>' . $htmlContent . '</div>';
            $tempDoc->loadHTML('<?xml encoding="UTF-8">' . $wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

            // Get the wrapper div and process its children
            $wrapperDiv = $tempDoc->documentElement;
            if ($wrapperDiv && $wrapperDiv->hasChildNodes()) {
                // Import and append the nodes to the target element
                foreach ($wrapperDiv->childNodes as $childNode) {
                    $importedNode = $element->ownerDocument->importNode($childNode, true);
                    $element->appendChild($importedNode);
                }
            }
        } catch (\Exception $e) {
            // If parsing fails, insert as text content to avoid breaking the document
            $element->textContent = strip_tags($htmlContent);
        }
    }

    /**
     * Process AIO links from window.jsl.dh() calls
     * Find the first ID before data-subtree="msc" and inject the AIO content
     * Then remove AIO links from MSC section
     * Following the same pattern as enrichContentWithDynamicData()
     */
    protected function enrichAioLinksFromDynamicData($dom, $node, $originalDom, &$urls, &$data, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        // Get the original DOM content as string to search for patterns
        $originalContent = $originalDom->saveHTML();

        // Find the first ID before data-subtree="msc" using regex
        $aioId = $this->findFirstIdBeforeMsc($originalContent);

        if (empty($aioId)) {
            return;
        }

        // Track that we found the AIO ID for diagnostics
        $this->aioIdFound = true;

        // Extract all jsl.dh() calls from the original content
        $jslCalls = $this->extractJslDhCalls($originalContent);

        // Check if we have the AIO content for this ID
        if (!isset($jslCalls[$aioId])) {
            return;
        }

        // Find the MSC element where we'll inject the content
        $mscElements = $dom->xpathQuery('descendant::*[@data-subtree="msc"]', $node);

        if ($mscElements->length === 0) {
            return;
        }

        $mscElement = $mscElements->item(0);
        $htmlContent = $jslCalls[$aioId];

        // Clear the MSC element's current content
        while ($mscElement->hasChildNodes()) {
            $mscElement->removeChild($mscElement->firstChild);
        }

        // Remove display:none style from the MSC element if present
        if ($mscElement->hasAttribute('style')) {
            $style = $mscElement->getAttribute('style');
            $newStyle = preg_replace('/display\s*:\s*none\s*;?/i', '', $style);
            $newStyle = trim($newStyle);

            if (empty($newStyle)) {
                $mscElement->removeAttribute('style');
            } else {
                $mscElement->setAttribute('style', $newStyle);
            }
        }

        // Inject HTML content into the MSC element
        $this->injectHtmlContent($dom, $mscElement, $htmlContent);

        // Parse the HTML content to extract AIO links
        $aioLinks = $this->extractAioLinksFromHtml($htmlContent, $useDbRules, $isMobile);

        foreach ($aioLinks as $aioLink) {
            $url = $aioLink['url'];
            $title = $aioLink['title'];

            // Clean URL hash to improve unique page identification
            $url = $this->cleanUrlHash($url);

            // Skip if URL is empty or already processed
            if (empty($url) || in_array($url, $urls)) {
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

    /**
     * Extract AIO links from HTML content
     * Look for anchor tags with the specific AIO link class "KEVENd"
     */
    protected function extractAioLinksFromHtml($htmlContent, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        $links = [];

        $aioLinkXpath = '//a[@class="KEVENd"][@href]';

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featurePrefix = self::getFeatureName($isMobile);
            $dbRules = RuleLoaderService::getRulesForFeature($featurePrefix . '_aio_link_class');

            if (!empty($dbRules)) {
                $aioLinkXpath = implode(' | ', $dbRules);
            }
        }

        try {
            // Create a temporary document to parse the HTML
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');
            $tempDoc->loadHTML('<?xml encoding="UTF-8">' . $htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new \DOMXPath($tempDoc);

            // Look for anchor tags with the AIO link selector
            $anchorTags = $xpath->query($aioLinkXpath);

            foreach ($anchorTags as $anchor) {
                $href = $anchor->getAttribute('href');
                $title = $anchor->getAttribute('aria-label');

                // If no aria-label, try to get title from text content
                if (empty($title)) {
                    $title = trim($anchor->textContent);
                }

                // Process the URL through Google's translation service
                $url = \SM_Rank_Service::getUrlFromGoogleTranslate($href);

                // Clean URL hash to improve unique page identification
                $url = $this->cleanUrlHash($url);

                // Skip invalid URLs
                if (empty($url) || $url === '#' || strpos($url, 'javascript:') === 0) {
                    continue;
                }

                $links[] = [
                    'url' => $url,
                    'title' => $title,
                ];
            }

        } catch (\Exception $e) {
            // If parsing fails, fall back to regex extraction
            $this->extractAioLinksWithRegex($htmlContent, $links);
        }

        return $links;
    }

    /**
     * Fallback method to extract AIO links using regex when DOM parsing fails
     */
    protected function extractAioLinksWithRegex($htmlContent, &$links)
    {
        // Regex pattern to match anchor tags with class="KEVENd" specifically
        $anchorPattern = '/<a\s+[^>]*class=["\']KEVENd["\'][^>]*href=["\']([^"\']+)["\'][^>]*(?:aria-label=["\']([^"\']*)["\'])?[^>]*>(.*?)<\/a>/is';

        if (preg_match_all($anchorPattern, $htmlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = $match[1];
                $ariaLabel = isset($match[2]) ? $match[2] : '';
                $textContent = isset($match[3]) ? strip_tags($match[3]) : '';

                // Use aria-label if available, otherwise use text content
                $title = !empty($ariaLabel) ? $ariaLabel : trim($textContent);

                // Process the URL through Google's translation service
                $url = \SM_Rank_Service::getUrlFromGoogleTranslate($href);

                // Clean URL hash to improve unique page identification
                $url = $this->cleanUrlHash($url);

                // Skip invalid URLs
                if (empty($url) || $url === '#' || strpos($url, 'javascript:') === 0) {
                    continue;
                }

                $links[] = [
                    'url' => $url,
                    'title' => $title,
                ];
            }
        }
    }

    /**
     * Find the first ID that appears before data-subtree="msc" in the content
     */
    protected function findFirstIdBeforeMsc($originalContent)
    {
        preg_match('/data-subtree="msc"/', $originalContent, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches)) {
            preg_match('/data-subtree="mfc"/', $originalContent, $matches, PREG_OFFSET_CAPTURE);
        }

        // Find the position of data-subtree="msc"
        if (empty($matches)) {
            return null;
        }

        $mscPosition = $matches[0][1];

        // Look backwards from the msc position to find the first ID pattern
        // Search in a reasonable area before the msc marker (e.g., 10,000 characters)
        $searchStart = max(0, $mscPosition - 10000);
        $searchArea = substr($originalContent, $searchStart, $mscPosition - $searchStart);

        // Find all ID patterns in the search area, working backwards
        if (preg_match_all('/_[a-zA-Z0-9_-]+_\d+/', $searchArea, $idMatches, PREG_OFFSET_CAPTURE)) {
            // Get the last match (closest to msc position)
            $lastMatch = end($idMatches[0]);
            $potentialId = $lastMatch[0];

            // Verify this ID has a corresponding jsl.dh() call with AIO content
            if ($this->isAioJslId($originalContent, $potentialId)) {
                return $potentialId;
            }
        }

        return null;
    }

    /**
     * Check if a specific ID corresponds to an AIO jsl.dh() call
     */
    protected function isAioJslId($originalContent, $id)
    {
        // Look for jsl.dh() call with this ID
        if (preg_match('/jsl\.dh\(\s*[\'"]' . preg_quote($id, '/') . '[\'"]\s*,\s*[\'"](.*)[\'"]\);}\)\(\);/U', $originalContent, $match)) {
            $content = $match[1];

            // Decode the content to check for AIO indicators
            $decodedContent = $this->decodeJslHtml($content);

            // Check for the specific AIO pattern: jscontroller="g4PEk" AND class="LLtSOc"
            return (strpos($decodedContent, 'jscontroller="g4PEk"') !== false &&
                    strpos($decodedContent, 'class="LLtSOc"') !== false);
        }

        return false;
    }


    /**
     * Remove display:none styles from HTML content
     */
    protected function removeDisplayNoneStyles($htmlContent)
    {
        // Remove inline style display:none with various spacing patterns
        $patterns = [
            // Remove style="display:none" and similar patterns
            '/style\s*=\s*["\']\s*display\s*:\s*none\s*;?\s*["\']/',
            // Remove style="...display:none..." keeping other styles
            '/display\s*:\s*none\s*;?/',
            // Remove empty style attributes that might be left
            '/style\s*=\s*["\']\s*["\']/',
        ];

        foreach ($patterns as $pattern) {
            $htmlContent = preg_replace($pattern, '', $htmlContent);
        }

        // Also handle cases where display:none might be in CSS classes
        // by parsing the DOM and removing the style
        try {
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');
            @$tempDoc->loadHTML('<?xml encoding="UTF-8"><div>' . $htmlContent . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new \DOMXPath($tempDoc);
            // Find all elements with style attributes
            $elements = $xpath->query('//*[@style]');

            foreach ($elements as $elem) {
                $style = $elem->getAttribute('style');
                // Remove display:none from the style
                $newStyle = preg_replace('/display\s*:\s*none\s*;?/i', '', $style);
                $newStyle = trim($newStyle);

                if (empty($newStyle)) {
                    $elem->removeAttribute('style');
                } else {
                    $elem->setAttribute('style', $newStyle);
                }
            }

            // Get the cleaned HTML (remove the wrapper div we added)
            $cleanedHtml = '';
            $wrapper = $tempDoc->documentElement;
            if ($wrapper) {
                foreach ($wrapper->childNodes as $child) {
                    $cleanedHtml .= $tempDoc->saveHTML($child);
                }
            }

            return $cleanedHtml ?: $htmlContent;

        } catch (\Exception $e) {
            // If DOM parsing fails, return with basic regex cleaning
            return $htmlContent;
        }
    }

    /**
     * Remove display:none style from a DOM element
     */
    protected function removeDisplayNoneFromElement($element)
    {
        if ($element->hasAttribute('style')) {
            $style = $element->getAttribute('style');
            $newStyle = preg_replace('/display\s*:\s*none\s*;?/i', '', $style);
            $newStyle = trim($newStyle);

            if (empty($newStyle)) {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $newStyle);
            }
        }
    }

    /**
     * Remove display:none styles from all elements within the given node
     */
    protected function removeDisplayNoneFromAllElements($dom, $node)
    {
        // Find all elements with style attributes within the node
        $elementsWithStyles = $dom->xpathQuery('descendant::*[@style]', $node);

        foreach ($elementsWithStyles as $element) {
            $this->removeDisplayNoneFromElement($element);
        }
    }

    /**
     * Hide a specific element by ID if it exists
     */
    protected function hideElementById($dom, $node, $elementId)
    {
        // Find element with the specific ID within the node
        $elements = $dom->xpathQuery('descendant::*[@id="' . $elementId . '"]', $node);

        if ($elements->length > 0) {
            $element = $elements->item(0);
            $existingStyle = $element->getAttribute('style');
            $hideStyle = 'display:none !important;';

            if (!empty($existingStyle)) {
                // If there's existing style, append display:none
                $element->setAttribute('style', $existingStyle . ' ' . $hideStyle);
            } else {
                // If no existing style, just add display:none
                $element->setAttribute('style', $hideStyle);
            }
        }
    }

    /**
     * Add display:block style to elements with OS7YA class after everything is extracted
     */
    protected function addDisplayBlockToOS7YA($dom, $node)
    {
        $overrideClass = 'OS7YA';

        // Find all elements with the override class within the node
        $os7yaElements = $dom->xpathQuery('descendant::*[contains(concat(" ", normalize-space(@class), " "), " ' . $overrideClass . ' ")]', $node);

        foreach ($os7yaElements as $element) {
            $existingStyle = $element->getAttribute('style');
            $displayBlockStyle = 'display:block !important;';

            if (!empty($existingStyle)) {
                // If there's existing style, append display:block
                $element->setAttribute('style', $existingStyle . ' ' . $displayBlockStyle);
            } else {
                // If no existing style, just add display:block
                $element->setAttribute('style', $displayBlockStyle);
            }
        }
    }

    /**
     * Remove all elements from the node
     */
    protected function removeElements($dom, $node)
    {
        $elementTypes = [
            'button',
            'g-scrolling-carousel',
            'omnient-visibility-control',
        ];

        foreach ($elementTypes as $elementType) {
            // Find all button elements within the node
            $elements = $dom->xpathQuery('descendant::' . $elementType, $node);
            foreach ($elements as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
    }

    /**
     * Remove svg elements from the node, except those under specific classes
     */
    protected function removeSvgElements($dom, $node)
    {
        $exclusionClasses = ['BMebGe', 'iPjmzb', 'nk9vdc', 'Sb7k4e'];

        // Build the not(ancestor::...) clause dynamically from class list
        $exclusionParts = [];
        foreach ($exclusionClasses as $className) {
            $exclusionParts[] = 'ancestor::*[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]';
        }
        $exclusionXpath = 'descendant::svg[not(' . implode(' or ', $exclusionParts) . ')]';

        $svgElements = $dom->xpathQuery($exclusionXpath, $node);

        foreach ($svgElements as $svg) {
            if ($svg->parentNode) {
                $svg->parentNode->removeChild($svg);
            }
        }
    }

    /**
     * Remove specific classes (dSKvsb and RDmXvc) from all elements
     */
    protected function removeSpecificClasses($dom, $node)
    {
        $classesToRemove = ['dSKvsb', 'RDmXvc', 'Hw7y8e', 'okxdqe'];

        foreach ($classesToRemove as $className) {
            // Find all elements with the specific class within the node
            $elements = $dom->xpathQuery('descendant::*[contains(concat(" ", normalize-space(@class), " "), " ' . $className . ' ")]', $node);

            foreach ($elements as $element) {
                $classAttr = $element->getAttribute('class');

                if (!empty($classAttr)) {
                    // Remove the specific class from the class attribute
                    $classes = explode(' ', $classAttr);
                    $filteredClasses = array_filter($classes, function($class) use ($className) {
                        return trim($class) !== $className;
                    });

                    $newClassAttr = implode(' ', $filteredClasses);
                    $newClassAttr = trim($newClassAttr);

                    if (empty($newClassAttr)) {
                        // Remove the class attribute entirely if no classes remain
                        $element->removeAttribute('class');
                    } else {
                        // Update the class attribute with the filtered classes
                        $element->setAttribute('class', $newClassAttr);
                    }
                }
            }
        }
    }

    /**
     * Process HTML content for injection - add required div and classes
     */
    protected function processHtmlForInjection($htmlContent)
    {
        try {
            // Create a temporary document to parse and modify the HTML
            $tempDoc = new \DOMDocument('1.0', 'UTF-8');
            @$tempDoc->loadHTML('<?xml encoding="UTF-8"><div>' . $htmlContent . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

            $xpath = new \DOMXPath($tempDoc);

            // Add display:block style to elements with OS7YA class to override any display:none from CSS
            $os7yaElements = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " OS7YA ")]');
            foreach ($os7yaElements as $elem) {
                $existingStyle = $elem->getAttribute('style');
                $displayBlockStyle = 'display:block !important;';

                if (!empty($existingStyle)) {
                    // If there's existing style, append display:block
                    $elem->setAttribute('style', $existingStyle . ' ' . $displayBlockStyle);
                } else {
                    // If no existing style, just add display:block
                    $elem->setAttribute('style', $displayBlockStyle);
                }
            }

            // Find all ul elements
            $ulElements = $xpath->query('//ul');

            foreach ($ulElements as $ul) {
                // Add classes to ul elements
                $existingClass = $ul->getAttribute('class');
                $newClasses = 'zVKf0d Cgh8Qc';

                if (!empty($existingClass)) {
                    $ul->setAttribute('class', $existingClass . ' ' . $newClasses);
                } else {
                    $ul->setAttribute('class', $newClasses);
                }

                // Add div with suSUf class before the ul element
                $suSUfDiv = $tempDoc->createElement('div');
                $suSUfDiv->setAttribute('class', 'suSUf');

                // Insert the div before the ul element
                $ul->parentNode->insertBefore($suSUfDiv, $ul);
            }

            // Get the processed HTML (remove the wrapper div we added)
            $processedHtml = '';
            $wrapper = $tempDoc->documentElement;
            if ($wrapper) {
                foreach ($wrapper->childNodes as $child) {
                    $processedHtml .= $tempDoc->saveHTML($child);
                }
            }

            return $processedHtml ?: $htmlContent;

        } catch (\Exception $e) {
            // If DOM processing fails, return original content
            return $htmlContent;
        }
    }

    /**
     * Remove text fragment hash from URL to improve unique page identification
     * Removes #:~:text=... patterns from URLs
     */
    protected function cleanUrlHash($url)
    {
        // Remove text fragment identifiers (#:~:text=...)
        return preg_replace('/#:~:text=.*$/', '', $url);
    }

    /**
     * Convert bsmXxe div element to li element with K3KsMc class
     */
    protected function convertBsmXxeElementToLi($element)
    {
        try {
            // Check if this element has the bsmXxe class and is a div
            if ($element->nodeName === 'div' && $element->hasAttribute('class')) {
                $classes = explode(' ', $element->getAttribute('class'));
                if (in_array('bsmXxe', $classes)) {
                    // Create a new li element
                    $li = $element->ownerDocument->createElement('li');
                    $li->setAttribute('class', 'K3KsMc');

                    // Copy all child nodes from div to li
                    while ($element->firstChild) {
                        $li->appendChild($element->firstChild);
                    }

                    // Copy all attributes from div to li (except class which we're replacing)
                    if ($element->hasAttributes()) {
                        foreach ($element->attributes as $attr) {
                            if ($attr->nodeName !== 'class') {
                                $li->setAttribute($attr->nodeName, $attr->nodeValue);
                            }
                        }
                    }

                    // Replace the div with the li
                    $element->parentNode->replaceChild($li, $element);
                    return $li;
                }
            }

            return $element;
        } catch (\Exception $e) {
            // If conversion fails, return original element
            return $element;
        }
    }

}
