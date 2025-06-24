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
        if (strpos($node->getAttribute('class'), 'xpdopen') !== false || strpos($node->getAttribute('class'), 'xpdbox') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if (strpos($node->getAttribute('class'), 'CWesnb') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($dom->getXpath()->query('//div[@class="MjjYud"]/div[@class="pxiwBd GqJbWc M6ON8"]', $node)->length > 0) {
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
            $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@class='LGOjhe']", $featureSnippetNode);//description
            if ($description->length == 0)  {
                $description = $googleDOM->getXpath()->query("descendant::div[@class='LGOjhe']", $featureSnippetNode);//description

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

        // Try the primary source URL XPath
        $sourceUrls = $googleDOM->getXpath()->query('//a[@class="sXtWJb"]/@href', $node);

        // If not found, try the alternative XPath
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query('//h3[@class="yuRUbf JtGQ40d MBeuO q8U8x"]//a/@href', $node);
        }

        // If still not found, let's try a more general approach to find any link
        if ($sourceUrls->length == 0) {
            // Try to find standard result links
            $sourceUrls = $googleDOM->getXpath()->query('//div[contains(@class, "yuRUbf")]//a/@href', $node);
        }

        // If we found a URL, process it
        if ($sourceUrls->length > 0) {
            $object->url = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($sourceUrls->item(0)->getNodeValue()),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            // Try to get title from the combination XPath
            $titleElements = $googleDOM->getXpath()->query('//a[@class="sXtWJb" and @jsname="UWckNb"]', $node);

            // If not found, try a more general approach
            if ($titleElements->length == 0) {
                $titleElements = $googleDOM->getXpath()->query('//h3', $node);
            }

            $object->title = ($titleElements->length > 0) ? trim($titleElements->item(0)->textContent) : '';

            $results[] = $object;
        }

        if (!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }
}
