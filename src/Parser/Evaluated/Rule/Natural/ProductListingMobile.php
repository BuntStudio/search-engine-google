<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\SerpFeaturesVersions;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ProductListingMobile extends SerpFeaturesVersions
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2', 'version3'];

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
     * PLA / Product Listing is a MOBILE-only feature in the self-healing parser
     * (this class is the mobile variant). The desktop variant lives in
     * ProductListing.php and is not integrated here.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'product_listing_mobile';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path (live DB rules in mode 1, heal candidate in mode 3).
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['product_listing_mobile_match'])
                : RuleLoaderService::getRulesForFeature('product_listing_mobile_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->hasClass('commercial-unit-mobile-top') ||
            $node->hasClass('commercial-unit-mobile-bottom')
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $featureName = self::getFeatureName($isMobile);

        // DB-rule extraction path. The primary extraction rule (version1's `pla-unit`
        // anchors) is integrated; version2/version3 remain hardcoded fallbacks and
        // always run after, regardless of mode, so behaviour is never reduced.
        $handled = false;

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // A heal candidate may belong to THIS feature ('product_listing_mobile').
                // Resolve the candidate rule ids filtered to this feature; if none belong
                // to us the candidate isn't ours → fall through to mode-1 behaviour.
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
                $items = $this->parseWithDbRules($dom, $node, $rules);
                if (!empty($items)) {
                    $resultSet->addItem(
                        new BaseResult(NaturalResultType::PRODUCT_LISTING_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
                    );
                }
                $handled = true;
            } else {
                Logger::error("No DB rules found for {$featureName}, falling back to hardcoded version1");
            }
        }

        // Hardcoded primary extraction (version1) — only when DB rules did not handle it.
        if (!$handled) {
            $this->version1($dom, $node, $resultSet, $isMobile);
        }

        // version2 / version3 remain hardcoded fallback chain members (not integrated).
        $this->version2($dom, $node, $resultSet, $isMobile);
        $this->version3($dom, $node, $resultSet, $isMobile);
    }

    /**
     * Extract product URLs using DB-supplied XPath rules (primary extraction rule).
     * Mirrors version1: each matched anchor's `data-dtld` attribute is the product URL.
     *
     * @return array Flat array of ['url' => '...'] items.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, array $rules)
    {
        $items = [];
        $dynamicXpath = implode(' | ', $rules);

        try {
            $productsNodes = $dom->getXpath()->query($dynamicXpath, $node);
            if ($productsNodes->length == 0) {
                return $items;
            }

            foreach ($productsNodes as $productNode) {
                $productUrl = $productNode->getAttribute('data-dtld');
                $items[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($productUrl)];
            }
        } catch (\Exception $e) {
            Logger::error('ProductListingMobile DB rule XPath failed', [
                'xpath' => $dynamicXpath,
                'error' => $e->getMessage(),
                'feature' => 'product_listing_mobile',
            ]);
        }

        return $items;
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
