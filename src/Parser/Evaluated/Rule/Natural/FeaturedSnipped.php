<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class FeaturedSnipped implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        $isCandidate = false;

        if (strpos($node->getAttribute('class'), 'xpdopen') !== false || strpos($node->getAttribute('class'), 'xpdbox') !== false) {
            $isCandidate = true;
        }

        if (strpos($node->getAttribute('class'), 'CWesnb') !== false) {
            $isCandidate = true;
        }

        if ($dom->getXpath()->query('.//div[@class="MjjYud"]/div[@class="pxiwBd GqJbWc M6ON8"]', $node)->length > 0) {
            $isCandidate = true;
        }

        if (!$isCandidate) {
            return self::RULE_MATCH_NOMATCH;
        }

        // Confirm this is actually a featured snippet, not another xpdopen block
        // (e.g. "See results about", expandable sections, etc.)

        // 1. Text-based detection (most robust, resistant to CSS class rotation):
        //    Real featured snippets have a hidden h2 with text "Featured snippet from the web"
        $headings = $dom->getXpath()->query(
            'descendant::*[self::h2 or self::h3]', $node
        );
        foreach ($headings as $heading) {
            if (stripos($heading->textContent, 'featured snippet') !== false) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        // 2. Feedback URL detection (Google includes a "About featured snippets" link):
        //    The href contains "p=featured_snippets"
        $feedbackLinks = $dom->getXpath()->query(
            'descendant::a[contains(@href, "featured_snippets")]', $node
        );
        if ($feedbackLinks->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        // 3. Class-based fallback (less stable, but covers edge cases):
        //    bNg8Rb = hidden screen-reader heading class
        //    V3FYCf = snippet content wrapper class
        $hasFeaturedSnippetLabel = $dom->getXpath()->query(
            'descendant::h2[contains(@class, "bNg8Rb")]', $node
        )->length > 0;

        $hasV3FYCf = $dom->getXpath()->query(
            'descendant::div[contains(@class, "V3FYCf")]', $node
        )->length > 0;

        if ($hasFeaturedSnippetLabel || $hasV3FYCf) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::FEATURED_SNIPPED_MOBILE : NaturalResultType::FEATURED_SNIPPED;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile, $doNotRemoveSrsltidForDomains]);
        }
    }

    public function version1(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = []
    ) {
        $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $node);

        if ($naturalResultNodes->length == 0) {
            $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' SALvLe ')]", $node);
            if ($naturalResultNodes->length == 0) {
                // this older class is still valid
                $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' V3FYCf ')]", $node);
                if ($naturalResultNodes->length == 0) {
                    return;
                }
            }
        }

        $results = [];

        foreach ($naturalResultNodes  as $featureSnippetNode) {
            $isHidden = $googleDOM->getXpath()->query("ancestor::g-accordion-expander", $featureSnippetNode);
            if ($isHidden->length >  0) {
                continue;
            }

            $aTag = $googleDOM->getXpath()->query("descendant::a", $featureSnippetNode);
            $h3Tag = $googleDOM->getXpath()->query("descendant::h3", $featureSnippetNode);//title
            // Description: prefer semantic data-attrid, fall back to CSS class
            $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@data-attrid='wa:/description']", $featureSnippetNode);
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("descendant::div[@data-attrid='wa:/description']", $featureSnippetNode);
            }
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@class='LGOjhe']", $featureSnippetNode);
            }
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("descendant::div[@class='LGOjhe']", $featureSnippetNode);
            }
            if ($aTag->length == 0) {
                continue;
            }

            $object              = new \StdClass();

            $object->url         = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->item(0)->getAttribute('href')),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            $object->description = (!empty($description) && !empty($description->item(0)) && !empty($description->item(0)->textContent)) ? $description->item(0)->textContent : '';
            $object->title       = (!empty($h3Tag) && !empty($h3Tag->item(0)) && !empty($h3Tag->item(0)->textContent)) ? $h3Tag->item(0)->textContent : '';

            $results[] = $object;
        }

        if(!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    public function version2(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = []
    ) {
        $results = [];

        $object = new \StdClass();

        // Try the primary source URL XPath (class-based)
        $sourceUrls = $googleDOM->getXpath()->query('.//a[@class="sXtWJb"]/@href', $node);

        // If not found, try the alternative XPath
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query('.//h3[@class="yuRUbf JtGQ40d MBeuO q8U8x"]//a/@href', $node);
        }

        // Try class-based result link
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query('.//div[contains(@class, "yuRUbf")]//a/@href', $node);
        }

        // Semantic fallback: first outbound link that isn't a Google-internal URL
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query(
                './/a[@href and not(contains(@href, "google.com")) and not(starts-with(@href, "/search"))]/@href',
                $node
            );
        }

        // If we found a URL, process it
        if ($sourceUrls->length > 0) {
            $object->url = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($sourceUrls->item(0)->getNodeValue()),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            // Title: try class-based first, then semantic h3 fallback
            $titleElements = $googleDOM->getXpath()->query('.//a[@class="sXtWJb" and @jsname="UWckNb"]', $node);
            if ($titleElements->length == 0) {
                $titleElements = $googleDOM->getXpath()->query('.//h3', $node);
            }
            $object->title = ($titleElements->length > 0) ? trim($titleElements->item(0)->textContent) : '';

            // Direct answer: use Google's TTS marker (e.g. phone numbers, dates)
            $ttsAnswer = $googleDOM->getXpath()->query('.//div[@data-tts="answers"]', $node);
            $object->callout = ($ttsAnswer->length > 0) ? trim($ttsAnswer->item(0)->textContent) : '';

            // Description: prefer semantic data-attrid, fall back to CSS class
            $description = $googleDOM->getXpath()->query('.//div[@data-attrid="wa:/description"]', $node);
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query('.//div[@class="LGOjhe"]', $node);
            }
            $object->description = ($description->length > 0) ? trim($description->item(0)->textContent) : '';

            $results[] = $object;
        }

        if (!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }
}
