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
    protected $steps = ['version1', 'version2', 'version3', 'version4'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        // Original checks for commercial unit classes (older format)
        if ($node->hasClass('commercial-unit-mobile-top') ||
            $node->hasClass('commercial-unit-mobile-bottom')
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        // Google uses data-enable-product-traversal attribute for PLA mobile
        // Adding support with contextual validation to prevent false positives
        // Check if current node has data-enable-product-traversal
        if ($node->hasAttribute('data-enable-product-traversal')) {
            // Validate it's actually product listing content to avoid false positives
            if ($this->isProductListingContent($dom, $node)) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        // Check parent for data-enable-product-traversal (some PLAs have it on parent container)
        $parent = $node->parentNode;
        if ($parent && $parent instanceof \DOMElement && $parent->hasAttribute('data-enable-product-traversal')) {
            // Validate it's actually product listing content using parent
            if ($this->isProductListingContent($dom, $parent)) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        // Check children for data-enable-product-traversal (some PLAs have it on child elements)
        $traversalChildren = $dom->xpathQuery(".//*[@data-enable-product-traversal]", $node);
        if ($traversalChildren->length > 0) {
            // Validate it's actually product listing content
            if ($this->isProductListingContent($dom, $node)) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }

    /**
     * Validates if a node contains actual product listing content
     * This prevents false positives from non-shopping content that might have data-enable-product-traversal
     * 
     * @param GoogleDom $dom
     * @param DOMElement $node
     * @return bool
     */
    protected function isProductListingContent(GoogleDom $dom, $node)
    {
        // Check for shopping/product-specific patterns
        
        // 1. Look for product listing links (pla-unit class)
        $plaUnits = $dom->xpathQuery("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' pla-unit ')]", $node);
        if ($plaUnits->length > 0) {
            return true;
        }
        
        // 2. Look for jp-css-link (another product link pattern)
        $jpLinks = $dom->xpathQuery("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' jp-css-link ')]", $node);
        if ($jpLinks->length > 0) {
            return true;
        }
        
        // 3. Check for shopping URLs/domains (common shopping sites)
        $shoppingLinks = $dom->xpathQuery("descendant::a[contains(@href, '/shopping/') or contains(@href, 'shop.') or contains(@data-dtld, '.com') or contains(@data-dtld, '.net')]", $node);
        if ($shoppingLinks->length > 0) {
            // Additional validation - must have price or product-related text
            $priceElements = $dom->xpathQuery("descendant::*[contains(text(), '$') or contains(text(), '€') or contains(text(), '£')]", $node);
            if ($priceElements->length > 0) {
                return true;
            }
        }
        
        // 4. Look for product price classes (WJMUdc rw5ecc or similar)
        $priceSpans = $dom->xpathQuery("descendant::span[contains(@class, 'WJMUdc') or contains(@class, 'rw5ecc')]", $node);
        if ($priceSpans->length > 0) {
            return true;
        }
        
        // 5. Check if node has data-ft attribute with product info
        if ($node->hasAttribute('data-ft')) {
            $dataFt = $node->getAttribute('data-ft');
            if (strpos($dataFt, 'PRODUCTS') !== false || strpos($dataFt, 'APPAREL') !== false) {
                return true;
            }
        }
        
        // 6. Check for specific shopping domains
        $specificShops = $dom->xpathQuery("descendant::a[contains(@href, '6pm.com') or contains(@href, 'amazon.com') or contains(@href, 'walmart.com')]", $node);
        if ($specificShops->length > 0) {
            return true;
        }
        
        return false;
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

    /**
     * Version4: Process nodes with data-enable-product-traversal attribute
     * This version handles PLA format that doesn't use commercial-unit classes
     */
    public function version4(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        // For nodes with data-enable-product-traversal, extract product info differently
        if (!$node->hasAttribute('data-enable-product-traversal')) {
            return;
        }

        // Look for product links and prices in the content
        $items = [];
        
        // Find all links that look like product URLs
        $productLinks = $googleDOM->getXpath()->query("descendant::a[@href and (contains(@href, '6pm.com') or contains(@href, 'amazon.com') or contains(@href, 'walmart.com') or contains(@href, '/product/') or contains(@href, 'shop'))]", $node);
        
        if ($productLinks->length > 0) {
            foreach ($productLinks as $link) {
                $url = $link->getAttribute('href');
                if (!empty($url)) {
                    $items[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($url)];
                    if (count($items) >= 5) break; // Limit to 5 products
                }
            }
        }
        
        // If no product links found, try to get any shopping-related content
        if (empty($items)) {
            // Look for any links with data-dtld (domain) attribute
            $domainLinks = $googleDOM->getXpath()->query("descendant::a[@data-dtld]", $node);
            if ($domainLinks->length > 0) {
                foreach ($domainLinks as $link) {
                    $domain = $link->getAttribute('data-dtld');
                    if (!empty($domain)) {
                        $items[] = ['url' => $domain];
                        if (count($items) >= 5) break;
                    }
                }
            }
        }
        
        // If still no items, create a generic entry
        if (empty($items)) {
            $items[] = ['url' => 'shopping-content-detected'];
        }

        $resultSet->addItem(
            new BaseResult(NaturalResultType::PRODUCT_LISTING_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }
}
