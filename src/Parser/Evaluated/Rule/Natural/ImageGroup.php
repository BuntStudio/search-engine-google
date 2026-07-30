<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Media\MediaFactory;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ImageGroup implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $steps = ['version1', 'version2'];
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
     */
    protected static function getFeatureName(bool $isMobile): string
    {
        return $isMobile ? 'images_mobile' : 'images';
    }

    /**
     * Get the hardcoded XPath expressions used to extract images from a matched node.
     * These are the XPaths from version1 and version2 combined.
     */
    protected function getHardcodedImageXPaths(): array
    {
        return [
            'descendant::div[@data-lpage]',
            'ancestor::div[contains(concat(" ", @class, " "), " MjjYud ")]/descendant::div[@data-lpage]',
            'descendant::div[contains(concat(" ", @class, " "), " w43QB ")]',
        ];
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // Maps exclusion — always hardcoded (structural check, not feature-specific)
        $mapsRule = new Maps();
        if ($mapsRule->match($dom, $node) === self::RULE_MATCH_MATCHED) {
            return self::RULE_MATCH_NOMATCH;
        }

        $mapsRule = new MapsMobile();
        if ($mapsRule->match($dom, $node) === self::RULE_MATCH_MATCHED) {
            return self::RULE_MATCH_NOMATCH;
        }

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // DB rules replace all hardcoded match checks (iur+jsmodel, data-attrid, IZE3Td).
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; mode 1 uses live rules as before.
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['images_match', 'images_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('images_match'),
                    RuleLoaderService::getRulesForFeature('images_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded match checks
        if ($node->getAttribute('id') == 'iur' &&
            (   // Mobile
                $node->parentNode->hasAttribute('jsmodel') ||
                // Desktop
                $node->parentNode->parentNode->hasAttribute('jsmodel')  ||
                // New Mobile
                $node->parentNode->parentNode->parentNode->parentNode->parentNode->hasAttribute('jsmodel')
            )
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('data-attrid') == 'images universal') {
            return self::RULE_MATCH_MATCHED;
        }

        if (strpos($node->getAttribute('class'), 'IZE3Td') !== false) {
            $images = $dom->getXpath()->query('descendant::div[contains(concat(" ", @data-attrid, " "), " images universal ")]', $node);
            if ($images->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $featureName = self::getFeatureName($isMobile);

        if ($useDbRules === self::MODE_DATABASE) {
            $singleRuleId = is_int($additionalRule) ? $additionalRule : null;
            $structuredRules = RuleLoaderService::getRulesForFeatureWithChildren($featureName, false, $singleRuleId);
            if (!empty($structuredRules['parent']) || !empty($structuredRules['children'])) {
                $images = $this->parseWithDbRulesHierarchical($dom, $node, $structuredRules, $isMobile);
            } else {
                Logger::error("No DB rules found for {$featureName}, falling back to hardcoded");
                $images = $this->parseHardcoded($dom, $node, $isMobile);
            }
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($additionalRule !== null && is_array($additionalRule)) {
                // A heal candidate may belong to THIS feature ('images'/'images_mobile') OR to one of
                // its PARSE children ('{prefix}_parse_fallback' / '{prefix}_parse_v2'). Filtering by the
                // parent feature id alone silently drops a child-feature candidate (its serp_feature_id
                // != the parent's), so a parse-child heal could never be validated — e.g. on desktop,
                // image results are detected entirely by the parse_v2 'w43QB' rule, and a renamed-class
                // candidate for images_parse_v2 was dropped, falling back to the corrupted live rule.
                // Resolve across the parse family (the same child names used by
                // parseWithDbRulesHierarchical). The _match child is intentionally excluded — match()
                // handles match-feature candidates via getCandidateMatchRulesForFeatures().
                $parseFamily = [$featureName, $featureName . '_parse_fallback', $featureName . '_parse_v2'];
                $rules = [];
                foreach ($parseFamily as $familyFeature) {
                    $rules = array_merge($rules, RuleLoaderService::getRulesByIdsForFeature($additionalRule, $familyFeature));
                }
                $rules = array_values(array_unique($rules));
                if (!empty($rules)) {
                    $images = $this->parseWithDbRules($dom, $node, $rules, $isMobile);
                } else {
                    // Rules don't belong to this feature — use mode 1 behavior (hierarchical DB rules)
                    $structuredRules = RuleLoaderService::getRulesForFeatureWithChildren($featureName);
                    if (!empty($structuredRules['parent']) || !empty($structuredRules['children'])) {
                        $images = $this->parseWithDbRulesHierarchical($dom, $node, $structuredRules, $isMobile);
                    } else {
                        $images = $this->parseHardcoded($dom, $node, $isMobile);
                    }
                }
            } else {
                Logger::error('No rule IDs provided for ImageGroup mode 3');
                $images = $this->parseHardcoded($dom, $node, $isMobile);
            }
        } else {
            // MODE_HARDCODED (default)
            $images = $this->parseHardcoded($dom, $node, $isMobile);
        }

        $this->addImagesToResultSet($resultSet, $images, $isMobile, $node);
    }

    /**
     * Parse images using the original hardcoded steps (version1 + version2).
     * Returns a flat array of image URL arrays: [['url' => '...'], ...]
     */
    protected function parseHardcoded(GoogleDom $dom, \DomElement $node, $isMobile = false): array
    {
        $allImages = [];

        // version1: descendant::div[@data-lpage]
        try {
            $images = $dom->getXpath()->query('descendant::div[@data-lpage]', $node);

            if ($images->length == 0) {
                $images = $dom->getXpath()->query('ancestor::div[contains(concat(" ", @class, " "), " MjjYud ")]/descendant::div[@data-lpage]', $node);
            }

            if ($images->length > 0) {
                foreach ($images as $imageNode) {
                    $allImages[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($this->parseItem($imageNode))];
                }
            }
        } catch (\Exception $e) {
            // Continue to version2
        }

        // version2: descendant::div[w43QB]
        try {
            $images = $dom->getXpath()->query('descendant::div[contains(concat(" ", @class, " "), " w43QB ")]', $node);

            if ($images->length > 0) {
                foreach ($images as $imageNode) {
                    $itemsImg = $dom->getXpath()->query('descendant::a', $imageNode);
                    if ($itemsImg->length !== 0 && $itemsImg->item(0) && $itemsImg->item(0)->getAttribute('href')) {
                        $allImages[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($itemsImg->item(0)->getAttribute('href'))];
                    } else {
                        $allImages[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($this->parseItem($imageNode))];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return $allImages;
    }

    /**
     * Parse images using DB rules with parent-child hierarchy.
     *
     * Step structure:
     * 1. Parent rules: primary image detection (e.g. descendant::div[@data-lpage])
     * 2. Fallback child (images[_mobile]_parse_fallback): CONDITIONAL — only if parent found nothing
     * 3. V2 child (images[_mobile]_parse_v2): ADDITIVE — always run, merge results
     */
    protected function parseWithDbRulesHierarchical(GoogleDom $dom, \DomElement $node, array $structuredRules, bool $isMobile = false): array
    {
        $allImages = [];
        $featurePrefix = $isMobile ? 'images_mobile' : 'images';

        // Step 1: Parent rules (primary detection)
        $parentRules = $structuredRules['parent'] ?? [];
        if (!empty($parentRules)) {
            $parentXpath = implode(' | ', $parentRules);
            $allImages = $this->queryImageNodes($dom, $node, $parentXpath);
        }

        // Step 2: Conditional fallback child — only if parent found nothing
        $fallbackChildName = $featurePrefix . '_parse_fallback';
        $fallbackRules = $structuredRules['children'][$fallbackChildName] ?? [];
        if (empty($allImages) && !empty($fallbackRules)) {
            $fallbackXpath = implode(' | ', $fallbackRules);
            $allImages = $this->queryImageNodes($dom, $node, $fallbackXpath);
        }

        // Step 3: Additive child — always run, merge results
        $v2ChildName = $featurePrefix . '_parse_v2';
        $v2Rules = $structuredRules['children'][$v2ChildName] ?? [];
        if (!empty($v2Rules)) {
            $v2Xpath = implode(' | ', $v2Rules);
            $v2Images = $this->queryImageNodes($dom, $node, $v2Xpath);
            $allImages = array_merge($allImages, $v2Images);
        }

        return $allImages;
    }

    /**
     * Query image nodes with a given XPath and extract URLs.
     */
    private function queryImageNodes(GoogleDom $dom, \DomElement $node, string $xpath): array
    {
        $images = [];
        try {
            $nodes = $dom->getXpath()->query($xpath, $node);
            foreach ($nodes as $imageNode) {
                $url = $imageNode->getAttribute('data-lpage');
                if (!empty($url)) {
                    $images[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($url)];
                    continue;
                }
                $links = $dom->getXpath()->query('descendant::a', $imageNode);
                if ($links->length > 0 && $links->item(0) && $links->item(0)->getAttribute('href')) {
                    $images[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($links->item(0)->getAttribute('href'))];
                }
            }
        } catch (\Exception $e) {
            Logger::error('ImageGroup XPath query failed', ['xpath' => $xpath, 'error' => $e->getMessage()]);
        }
        return $images;
    }

    /**
     * Parse images using DB rules (flat — all rules combined).
     * Used for MODE_CANDIDATE_TESTING where rules are explicitly specified.
     * Returns a flat array of image URL arrays: [['url' => '...'], ...]
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, array $rules, $isMobile = false): array
    {
        $allImages = [];

        // Combine all rules with XPath union operator
        $dynamicXpath = implode(' | ', $rules);

        try {
            $images = $dom->getXpath()->query($dynamicXpath, $node);

            if ($images->length > 0) {
                foreach ($images as $imageNode) {
                    // Try data-lpage first (version1 pattern)
                    $url = $imageNode->getAttribute('data-lpage');
                    if (!empty($url)) {
                        $allImages[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($url)];
                        continue;
                    }

                    // Fall back to descendant <a> href (version2 pattern)
                    $links = $dom->getXpath()->query('descendant::a', $imageNode);
                    if ($links->length !== 0 && $links->item(0) && $links->item(0)->getAttribute('href')) {
                        $allImages[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($links->item(0)->getAttribute('href'))];
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error('ImageGroup DB rule XPath failed', [
                'xpath' => $dynamicXpath,
                'error' => $e->getMessage(),
                'feature' => self::getFeatureName($isMobile),
            ]);
        }

        return $allImages;
    }

    /**
     * Add parsed images to the result set as a BaseResult.
     */
    protected function addImagesToResultSet(IndexedResultSet $resultSet, ?array $images, $isMobile, \DomElement $node): void
    {
        if (empty($images)) {
            return;
        }

        $item = ['images' => $images];
        $resultSet->addItem(
            new BaseResult($this->getType($isMobile), $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::IMAGE_GROUP_MOBILE : NaturalResultType::IMAGE_GROUP;
    }

    /**
     * @param \DOMElement $imgNode
     *
     * @return string
     */
    private function parseItem( \DOMElement $imgNode)
    {
        return $imgNode->getAttribute('data-lpage');
    }
}
