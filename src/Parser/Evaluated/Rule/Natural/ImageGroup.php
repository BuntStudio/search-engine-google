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
use SM\Backend\IncidentResponse\IncidentResponseClient;

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

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        $mapsRule = new Maps();
        if ($mapsRule->match($dom, $node) === self::RULE_MATCH_MATCHED) {
            return self::RULE_MATCH_NOMATCH;
        }

        $mapsRule = new MapsMobile();
        if ($mapsRule->match($dom, $node) === self::RULE_MATCH_MATCHED) {
            return self::RULE_MATCH_NOMATCH;
        }

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

        if (strpos($node->getAttribute('class'), 'IZE3Td') !== false) {
            $images = $dom->getXpath()->query('descendant::div[contains(concat(" ", @data-attrid, " "), " images universal ")]', $node);
            if ($images->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        if ($node->getAttribute('data-attrid') == 'images universal') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $featureName = self::getFeatureName($isMobile);
        $deviceLabel = $isMobile ? 'Mobile' : 'Desktop';

        // ============================================================================
        // STEP 1: Calculate hardcoded results (for MODE_HARDCODED and MODE_COMPARISON)
        // ============================================================================

        $imagesHardcoded = null;
        if ($useDbRules === self::MODE_HARDCODED || $useDbRules === self::MODE_COMPARISON) {
            $imagesHardcoded = $this->parseHardcoded($dom, $node, $isMobile);
        }

        // ============================================================================
        // STEP 2: Calculate DB-driven results (if applicable modes)
        // ============================================================================

        $imagesDb = null;

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_COMPARISON) {
            $singleRuleId = is_int($additionalRule) ? $additionalRule : null;
            $rules = RuleLoaderService::getRulesForFeature($featureName, false, $singleRuleId);
            if (!empty($rules)) {
                $imagesDb = $this->parseWithDbRules($dom, $node, $rules, $isMobile);
            }
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            // MODE_CANDIDATE_TESTING: Use ONLY the rule IDs specified in $additionalRule array
            if ($additionalRule !== null && is_array($additionalRule)) {
                $rules = RuleLoaderService::getRulesByIds($additionalRule);
                if (!empty($rules)) {
                    $imagesDb = $this->parseWithDbRules($dom, $node, $rules, $isMobile);
                }
            }
        }

        // ============================================================================
        // STEP 3: Select final results based on mode
        // ============================================================================

        if ($useDbRules === self::MODE_HARDCODED) {
            // MODE_HARDCODED: Production default — use hardcoded steps
            $this->addImagesToResultSet($resultSet, $imagesHardcoded, $isMobile, $node);

        } elseif ($useDbRules === self::MODE_DATABASE) {
            if ($imagesDb !== null) {
                $this->addImagesToResultSet($resultSet, $imagesDb, $isMobile, $node);
            } else {
                // Fallback: No DB rules found, use hardcoded
                Logger::error("No DB rules found for {$featureName}");
                $fallback = $this->parseHardcoded($dom, $node, $isMobile);
                $this->addImagesToResultSet($resultSet, $fallback, $isMobile, $node);

                try {
                    $alertTitle = "SERP Parser: DB rules fallback — Images {$deviceLabel}";
                    $oncallAlert = new IncidentResponseClient();
                    $oncallAlert->triggerOrResolveEvent(
                        IncidentResponseClient::SERVICE_PARSERS,
                        $alertTitle,
                        [
                            'title' => $alertTitle,
                            'description' => "MODE_DATABASE is active but no DB rules were found for {$featureName} ({$deviceLabel}). " .
                                'Parser fell back to hardcoded XPath rules. This means the DB rules pipeline is broken.',
                            'fields' => [
                                ['title' => 'Feature', 'value' => $featureName, 'short' => true],
                                ['title' => 'Mode', 'value' => 'MODE_DATABASE (1)', 'short' => true],
                                ['title' => 'Action Taken', 'value' => 'Fell back to hardcoded XPath', 'short' => false],
                                ['title' => 'Admin', 'value' => 'https://admin.seomonitor.com/developer/serp-parser/monitoring', 'short' => false],
                            ],
                        ],
                        IncidentResponseClient::STATUS_TRIGGER,
                        'sev1',
                        'p1'
                    );
                } catch (\Throwable $e) {
                    Logger::error('Failed to send SERP parser on-call alert', ['error' => $e->getMessage()]);
                }
            }

        } elseif ($useDbRules === self::MODE_COMPARISON) {
            // Always use hardcoded for production
            $this->addImagesToResultSet($resultSet, $imagesHardcoded, $isMobile, $node);

            // Compare actual results (URLs), not just counts
            $hardcodedUrls = $imagesHardcoded !== null ? array_column($imagesHardcoded, 'url') : [];
            $dbUrls = $imagesDb !== null ? array_column($imagesDb, 'url') : [];

            $hardcodedCount = count($hardcodedUrls);
            $dbCount = count($dbUrls);

            // Find differences
            $missingFromDb = array_values(array_diff($hardcodedUrls, $dbUrls));
            $extraInDb = array_values(array_diff($dbUrls, $hardcodedUrls));
            $hasMismatch = !empty($missingFromDb) || !empty($extraInDb);

            $hasMismatch = true; //todo remove
            if ($imagesDb !== null && $hasMismatch) {
                $queryString = '';
                $pageTitle = '';
                if (!empty($dom)) {
                    if (!empty($dom->getUrl()) && !empty($dom->getUrl()->getQueryString())) {
                        $queryString = $dom->getUrl()->getQueryString();
                    }
                    try {
                        $titleNodes = $dom->getDom()->getElementsByTagName('title');
                        if ($titleNodes->length > 0) {
                            $pageTitle = $titleNodes->item(0)->nodeValue;
                        }
                    } catch (\Exception $e) {
                        $pageTitle = '';
                    }
                }
                Logger::error('ImageGroup XPath rule mismatch detected', [
                    'query_string' => $queryString,
                    'page_title' => $pageTitle,
                    'hardcoded_count' => $hardcodedCount,
                    'db_count' => $dbCount,
                    'missing_from_db' => array_slice($missingFromDb, 0, 5),
                    'extra_in_db' => array_slice($extraInDb, 0, 5),
                    'feature' => $featureName,
                    'additional_rule_id' => $additionalRule,
                ]);

                try {
                    $mismatchSummary = [];
                    if (!empty($missingFromDb)) {
                        $mismatchSummary[] = count($missingFromDb) . ' URLs found by hardcoded but not DB';
                    }
                    if (!empty($extraInDb)) {
                        $mismatchSummary[] = count($extraInDb) . ' URLs found by DB but not hardcoded';
                    }

                    $alertTitle = "SERP Parser: ImageGroup mismatch — {$deviceLabel}";
                    $oncallAlert = new IncidentResponseClient();
                    $oncallAlert->triggerOrResolveEvent(
                        IncidentResponseClient::SERVICE_PARSERS,
                        $alertTitle,
                        [
                            'title' => $alertTitle,
                            'description' => "MODE_COMPARISON detected a mismatch between hardcoded and DB XPath rules for {$featureName} ({$deviceLabel}). " .
                                "Hardcoded found {$hardcodedCount} images, DB rules found {$dbCount} images. " .
                                implode('. ', $mismatchSummary) . '. ' .
                                'Production parsing is unaffected (using hardcoded), but DB rules need investigation before switching to MODE_DATABASE.',
                            'fields' => [
                                ['title' => 'Query String', 'value' => $queryString, 'short' => false],
                                ['title' => 'Feature', 'value' => $featureName, 'short' => true],
                                ['title' => 'Mode', 'value' => 'MODE_COMPARISON (2)', 'short' => true],
                                ['title' => 'Hardcoded Count', 'value' => (string) $hardcodedCount, 'short' => true],
                                ['title' => 'DB Count', 'value' => (string) $dbCount, 'short' => true],
                                ['title' => 'Missing from DB', 'value' => !empty($missingFromDb) ? implode(', ', array_slice($missingFromDb, 0, 3)) : 'none', 'short' => false],
                                ['title' => 'Extra in DB', 'value' => !empty($extraInDb) ? implode(', ', array_slice($extraInDb, 0, 3)) : 'none', 'short' => false],
                                ['title' => 'Admin', 'value' => 'https://admin.seomonitor.com/developer/serp-parser/rules', 'short' => false],
                            ],
                        ],
                        IncidentResponseClient::STATUS_TRIGGER,
                        'sev2',
                        'p2'
                    );
                } catch (\Throwable $e) {
                    Logger::error('Failed to send SERP parser on-call alert', ['error' => $e->getMessage()]);
                }
            }

        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($imagesDb !== null) {
                $this->addImagesToResultSet($resultSet, $imagesDb, $isMobile, $node);
            } else {
                Logger::error('No rules found or provided for ImageGroup mode 3', [
                    'rule_ids' => $additionalRule,
                ]);
                $fallback = $this->parseHardcoded($dom, $node, $isMobile);
                $this->addImagesToResultSet($resultSet, $fallback, $isMobile, $node);
            }
        }
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
     * Parse images using DB rules. Each rule is an XPath to find image nodes.
     * For each matched node, extracts the URL via data-lpage or descendant <a> href.
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
