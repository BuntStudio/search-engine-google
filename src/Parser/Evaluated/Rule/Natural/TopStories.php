<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class TopStories implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    private $hasSerpFeaturePosition = true;
    private $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2', 'version3', 'version4'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {

        if (($node->parentNode->hasAttribute('jscontroller') &&
                $node->parentNode->getAttribute('jscontroller') == 'QE1bwd' &&
                $node->parentNode->tagName == 'g-expandable-container') ||
            ($node->tagName == 'g-section-with-header' && $node->hasClass('yG4QQe'))

        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if(
            $node->tagName == 'g-section-with-header' &&
            $node->hasClass('yG4QQe') &&
            $node->hasClass('TBC9ub')
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasAttribute('id') && $node->getAttribute('id') == 'kp-wp-tab-cont-Latest') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'e2BEnf q8U8x')]", $node);
        if ($storiesIcon->length == 0) {
            return;
        }

        $stories = $googleDOM->getXpath()->query('descendant::g-inner-card', $node);
        $items   = [];

        if ($stories->length == 0) {
            return;
        }

        foreach ($stories as $urlNode) {
            $aNode = $googleDOM->getXpath()->query('descendant::a', $urlNode);

            if ($aNode instanceof DomNodeList && $aNode->length > 0) {
                $link            = $aNode->item(0)->getAttribute('href');
                $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($link)];
            }
        }

        if (!empty($items)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::TOP_STORIES_MOBILE : NaturalResultType::TOP_STORIES;
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::span[contains(@class, 'rq6B5b VDgVie')]", $node);
        if (!$isMobile && $storiesIcon->length == 0) {
            return;
        }
        $hrefsNodes = $googleDOM->getXpath()->query("descendant::a[contains(@class,'WlydOe')]", $node);

        if (!$hrefsNodes instanceof DomNodeList) {
            return;
        }

        if ($hrefsNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($hrefsNodes as $hrefNode) {
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNode->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        // Leave this "if" here
        // It is possible to find results based on third condition by id "kp-wp-tab-cont-Latest" and identify urls with "version2" method
        // And it's not necessarily to add twice this type of results.
        if($resultSet->hasType($this->getType($isMobile))) {
            return;
        }

        $cards = $googleDOM->getXpath()->query("descendant::g-card", $node);

        if (!$cards instanceof DomNodeList) {
            return;
        }

        if ($cards->length == 0) {
            return;
        }

        $items = [];

        foreach ($cards as $story) {
            $imgNode = $googleDOM->getXpath()->query("descendant::g-img", $story);

            if($imgNode->length ==0) {
                continue;
            }
            $hrefNodes = $googleDOM->getXpath()->query("descendant::a", $story);

            if($hrefNodes->length == 0) {
                continue;
            }
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNodes->item(0)->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version4(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'e2BEnf q8U8x')]", $node);
        if ($storiesIcon->length == 0) {
            return;
        }

        $whatPeopleAreSaying = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'OSrXXb rbYSKb LfVVr esJEyb')]", $node);
        if ($whatPeopleAreSaying->length > 0) {
            return;
        }

        $hrefsNodes = $googleDOM->getXpath()->query("descendant::a[contains(@class,'WlydOe')]", $node);

        if (!$hrefsNodes instanceof DomNodeList) {
            return;
        }

        if ($hrefsNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($hrefsNodes as $hrefNode) {
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNode->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }
}
