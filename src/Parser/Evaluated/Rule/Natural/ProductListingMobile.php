<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\SerpFeaturesVersions;

class ProductListingMobile extends SerpFeaturesVersions
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2', 'version3'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->hasClass('commercial-unit-mobile-top') ||
            $node->hasClass('commercial-unit-mobile-bottom')
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false)
    {
        $productsNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' pla-unit ')] ", $node);

        if ($productsNodes->length == 0) {
            return;
        }

        foreach ($productsNodes as $productNode) {
            $productUrl = $productNode->getAttribute('data-dtld');
            $items[]    = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($productUrl)];
        }

        $resultSet->addItem(
            new BaseResult(NaturalResultType::PRODUCT_LISTING_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }

    public function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $productsNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' jp-css-link ')] ",
            $node);

        if ($productsNodes->length == 0) {
            return;
        }

        $items[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($productsNodes->item(0)->getAttribute('data-dtld'))];

        $resultSet->addItem(
            new BaseResult(NaturalResultType::PRODUCT_LISTING_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }

    public function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $productsNodes = $googleDOM->getXpath()->query("descendant::span[contains(concat(' ', normalize-space(@class), ' '), ' WJMUdc rw5ecc ')] ",
            $node);

        if ($productsNodes->length == 0) {
            return;
        }

        foreach ($productsNodes as $productNode) {
            $productUrl = $productNode->getNodeValue();
            $items[]    = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($productUrl)];
        }

        $resultSet->addItem(
            new BaseResult(NaturalResultType::PRODUCT_LISTING_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }
}
