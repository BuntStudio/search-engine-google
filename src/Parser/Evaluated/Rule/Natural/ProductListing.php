<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ProductListing implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Get the feature name based on mobile flag.
     *
     * This class is the DESKTOP variant of Product Listing / PLA. The mobile
     * variant lives in ProductListingMobile.php (a fully separate class,
     * integrated as 'product_listing_mobile' in batch 2026-06-10).
     */
    protected static function getFeatureName($isMobile)
    {
        return 'product_listing';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path (live DB rules in mode 1, heal candidate in mode 3).
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['product_listing_match'])
                : RuleLoaderService::getRulesForFeature('product_listing_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                if ($matchResult->length > 0) {
                    // Side-position detection stays hardcoded flow control: a PLA unit in
                    // the right rail (cu-container) is flagged via the ancestor walk
                    // regardless of which rule matched the container.
                    if (str_contains($node->getAttribute('class'), 'cu-container')) {
                        $this->checkIfSidePosition($node);
                    }
                    return self::RULE_MATCH_MATCHED;
                }
                return self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if (str_contains($node->getAttribute('class'),  'commercial-unit-desktop-top') || str_contains($node->getAttribute('class'),  'cu-container')) {
            if (str_contains($node->getAttribute('class'), 'cu-container')) {
                //$this->hasSideSerpFeaturePosition = true;
                $this->checkIfSidePosition($node);
            }
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $featureName = self::getFeatureName($isMobile);

        // DB-rule extraction path. The primary product-node selector (pla-unit / mnr-c
        // divs) is integrated; the li[@data-offer-surface] fallback and the WJMUdc
        // seller-span per-node fallback remain hardcoded.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // A heal candidate may belong to THIS feature ('product_listing').
                // Resolve the candidate rule ids filtered to this feature; if none belong
                // to us the candidate isn't ours → use the live rules (mode-1 behaviour).
                $rules = is_array($additionalRule)
                    ? RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName)
                    : [];
                if (empty($rules)) {
                    $rules = RuleLoaderService::getRulesForFeature($featureName);
                }
            } else {
                $singleRuleId = is_int($additionalRule) ? $additionalRule : null;
                $rules = RuleLoaderService::getRulesForFeature($featureName, false, $singleRuleId);
            }

            if (!empty($rules)) {
                if ($this->parseWithDbRules($dom, $node, $resultSet, $rules)) {
                    return;
                }
                // DB rules matched nothing — fall through to the hardcoded chain below
                // (same selector retried, then the li[@data-offer-surface] fallback).
            } else {
                Logger::error("No DB rules found for {$featureName}, falling back to hardcoded extraction");
            }
        }

        // Hardcoded fallback (primary selector + li[@data-offer-surface] fallback chain)
        $productsNodes = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' pla-unit ') or
        contains(concat(' ', normalize-space(@class), ' '), ' mnr-c ')]", $node);

        if ($productsNodes->length == 0) {

            $productsNodes = $dom->getXpath()->query("descendant::li[contains(concat(' ', normalize-space(@data-offer-surface), ' '), ' search-result-surface ')]", $node);
            if ($productsNodes->length == 0) {
                return;
            }
        }

        $items = $this->extractItemsFromProductNodes($dom, $productsNodes);

        if (!empty($items)) {
            $resultSet->addItem(
                new BaseResult(NaturalResultType::PRODUCT_LISTING, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    /**
     * Extract product URLs using DB-supplied XPath rules (primary extraction rule).
     * Returns true when at least one product was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        $dynamicXpath = implode(' | ', $rules);

        try {
            $productsNodes = $dom->getXpath()->query($dynamicXpath, $node);
        } catch (\Exception $e) {
            Logger::error('ProductListing DB rule XPath failed', [
                'xpath' => $dynamicXpath,
                'error' => $e->getMessage(),
                'feature' => 'product_listing',
            ]);
            return false;
        }

        if ($productsNodes->length == 0) {
            return false;
        }

        $items = $this->extractItemsFromProductNodes($dom, $productsNodes);

        if (!empty($items)) {
            $resultSet->addItem(
                new BaseResult(NaturalResultType::PRODUCT_LISTING, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
            return true;
        }

        return false;
    }

    /**
     * Desktop per-product-node walk: the product anchor is childNodes[1] (or
     * childNodes[0]); when no anchor exists the WJMUdc seller span supplies the
     * domain. Shared by the hardcoded chain and the DB-rules path so the
     * extraction semantics are identical in every mode.
     */
    protected function extractItemsFromProductNodes(GoogleDom $dom, $productsNodes)
    {
        $items = [];

        foreach ($productsNodes as $productNode) {
            $aHrefProduct = $productNode->childNodes[1];
            if (!empty($aHrefProduct) && $aHrefProduct->getTagName() != 'a') {
                $aHrefProduct = $productNode->childNodes[0];
            }
            $seller = false;
            if (!$aHrefProduct instanceof DomElement || (!empty($aHrefProduct) && $aHrefProduct->getTagName() != 'a')) {
                $seller = $dom->getXpath()->query("descendant::span[@class='WJMUdc rw5ecc']", $productNode)->item(0);
                if (empty($seller)) {
                    continue;
                }
            }
            if (!$seller) {
                $productUrl = $aHrefProduct->getAttribute('href');
            } else {
                $productUrl = explode('-',$seller->textContent)[0];
            }

            $items[]    = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($productUrl)];
        }

        return $items;
    }

    private function checkIfSidePosition(\Serps\Core\Dom\DomElement $node) {
        //this could be used for all side position elements
        if ($node->getAttribute('id') === 'center_col') {
            //item is in results list
            return false;
        }
        while ($node->parentNode !== null) {
            if ($node->parentNode instanceof \DOMDocument) {
                break;
            }

            if ( $node->parentNode->getAttribute('id') === 'center_col') {
                //item is in results list
                break;
            }

            if ($node->parentNode->getAttribute('role') === 'complementary') {
                $this->hasSideSerpFeaturePosition = true;
            }

            $node = $node->parentNode;
        }
    }
}
