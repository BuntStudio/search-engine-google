<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Dom\DomElement;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\AbstractRuleDesktop;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksBig;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksSmall;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;
use SM\Backend\IncidentResponse\IncidentResponseClient;

class ClassicalResult extends AbstractRuleDesktop implements ParsingRuleInterface
{
    protected $gotoDomainLinkCount = 0;

    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;           // Production default - uses hardcoded XPath
    const MODE_DATABASE = 1;            // Uses database-managed rules (future production)
    const MODE_COMPARISON = 2;          // Comparison mode - validates DB rules vs hardcoded
    const MODE_CANDIDATE_TESTING = 3;   // Isolated testing - tests single candidate rule

    /**
     * Current site ID for context-aware XPath selection
     */
    protected static $currentSiteId = null;

    /**
     * Custom XPath queries mapped by variant key
     * Add new variants here as needed
     */
    protected static $customXPathVariants = [
        // 'variant_a' => "descendant::*[contains(@class, 'custom-class')]",
    ];

    /**
     * Set the current site ID for XPath selection
     *
     * @param int|null $siteId
     */
    public static function setCurrentSiteId(?int $siteId): void
    {
        self::$currentSiteId = $siteId;
    }

    /**
     * Get the current site ID
     *
     * @return int|null
     */
    public static function getCurrentSiteId(): ?int
    {
        return self::$currentSiteId;
    }

    /**
     * Clear the current site ID context
     */
    public static function clearCurrentSiteId(): void
    {
        self::$currentSiteId = null;
    }

    /**
     * Get the XPath query for natural results based on site configuration
     *
     * @return string
     */
    protected function getNaturalResultsXPath(): string
    {
        $defaultXPath = "descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' g ') or
        (
            (contains(concat(' ', normalize-space(@class), ' '), ' wHYlTd ') or
            contains(concat(' ', normalize-space(@class), ' '), ' vt6azd Ww4FFb ') or
            contains(concat(' ', normalize-space(@class), ' '), ' Ww4FFb vt6azd ')
        ) and
        not(contains(concat(' ', normalize-space(@class), ' '), ' k6t1jb ')) and
        not(contains(concat(' ', normalize-space(@class), ' '), ' jmjoTe '))) or contains(concat(' ', normalize-space(@class), ' '), ' MYVUIe ')]";

        if (self::$currentSiteId === null) {
            return $defaultXPath;
        }

        $xpathKey = \FeatureFlags::getCustomSerpXPathKeyDesktop(self::$currentSiteId);
        if ($xpathKey === null) {
            return $defaultXPath;
        }

        return self::$customXPathVariants[$xpathKey] ?? $defaultXPath;
    }

    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->getAttribute('id') == 'rso' || $node->getAttribute('id') == 'botstuff') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function parseNode(GoogleDom $dom, \DomElement $organicResult, IndexedResultSet $resultSet, $k, array $doNotRemoveSrsltidForDomains = [])
    {
        $organicResultObject = $this->parseNodeWithRules($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);

        if ($organicResultObject !== null && $organicResultObject->hasUsedGotoDomainLink()) {
            $this->gotoDomainLinkCount++;
        }

        if ($dom->xpathQuery("descendant::table[@class='jmjoTe']", $organicResult)->length > 0) {
            (new SiteLinksBig())->parse($dom, $organicResult, $resultSet, false);
        }

        $parentWithSameClass = $dom->xpathQuery("ancestor::div[@class='g']", $organicResult);


        if ($parentWithSameClass->length > 0) {
            if ($dom->xpathQuery("descendant::table[@class='jmjoTe']", $parentWithSameClass->item(0))->length > 0) {
                (new SiteLinksBig())->parse($dom, $parentWithSameClass->item(0), $resultSet, false);
            }
        }

        if ($dom->xpathQuery("descendant::div[@class='HiHjCd']", $organicResult)->length > 0) {
            (new SiteLinksSmall())->parse($dom, $organicResult, $resultSet, false);
        }

        if ($parentWithSameClass->length > 0) {
            if ($dom->xpathQuery("descendant::div[@class='HiHjCd']", $parentWithSameClass->item(0))->length > 0) {
                (new SiteLinksBig())->parse($dom, $parentWithSameClass->item(0), $resultSet, false);
            }
        }

        $parentWithClass = $dom->xpathQuery("ancestor::div[@class='BYM4Nd']", $organicResult);

        if ($parentWithClass->length > 0) {
            if ($dom->xpathQuery("descendant::table[contains(@class, 'jmjoTe')]", $parentWithClass->item(0))->length > 0) {
                (new SiteLinksBig())->parse($dom, $parentWithClass->item(0), $resultSet, false);
            }
        }

        $descendentWithClass = $dom->xpathQuery("descendant::div[@class='BYM4Nd']", $organicResult);

        if ($descendentWithClass->length > 0) {
            if ($dom->xpathQuery("descendant::table[contains(@class, 'jmjoTe')]", $descendentWithClass->item(0))->length > 0) {
                (new SiteLinksBig())->parse($dom, $descendentWithClass->item(0), $resultSet, false);
            }
        }
    }

    /**
     * Parse natural search results from SERP HTML
     * 
     * PARSER MODES (Self-Healing Parser Integration):
     * ================================================
     * 
     * MODE_HARDCODED (0 - Default/Production):
     * - Uses hardcoded XPath rules (via getNaturalResultsXPath())
     * - Site-specific custom XPath may be applied if configured via FeatureFlags
     * - No database rules fetched
     * - Use case: Current production behavior, zero overhead
     * 
     * MODE_DATABASE (1 - Database Rules/Future Production):
     * - Uses XPath rules from database (managed by self-healing parser)
     * - Falls back to hardcoded rules if DB rules not found
     * - If $additionalRule provided, it's prepended to live rules for testing
     * - Use case: After cache invalidation bug is fixed, enables dynamic rule updates
     * 
     * MODE_COMPARISON (2 - Comparison/Monitoring):
     * - Uses hardcoded XPath for actual parsing (production results unchanged)
     * - Fetches DB rules and compares result counts
     * - Logs error if counts mismatch (monitoring for rule accuracy)
     * - If $additionalRule provided, it's included in DB rules comparison
     * - Use case: Validate DB rules match hardcoded before Mode 1 rollout
     * 
     * MODE_CANDIDATE_TESTING (3 - Isolated Candidate Testing):
     * - Uses ONLY the $additionalRule provided (ignores all live DB rules)
     * - Falls back to hardcoded if additional rule fails
     * - Use case: Self-healing parser investigation workflow - test single candidate rule
     * - NOT for production crawling
     * 
     * SITE-SPECIFIC XPATH FEATURE:
     * =============================
     * - getNaturalResultsXPath() may return custom XPath based on site ID
     * - Controlled by FeatureFlags::getCustomSerpXPathKeyDesktop()
     * - Allows per-site XPath overrides without code changes
     * - Works with all modes (Mode 0 uses it directly, Modes 1-3 use it as fallback)
     * 
     * @param GoogleDom $dom The Google DOM object
     * @param \DomElement $node The node to parse
     * @param IndexedResultSet $resultSet Result set to populate
     * @param bool $isMobile Mobile detection flag
     * @param array $doNotRemoveSrsltidForDomains Domains to preserve rsltid parameter
     * @param int $useDbRules Parser mode (use MODE_* constants)
     * @param int|null $additionalRule Rule ID to test (behavior varies by mode)
     * @return void
     */
    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $this->gotoDomainLinkCount = 0;

        // ============================================================================
        // STEP 1: Calculate "reference" XPath results (hardcoded or site-custom)
        // ============================================================================
        // This is used by MODE_HARDCODED for production parsing, and by other modes as fallback.
        // Note: getNaturalResultsXPath() may return site-specific custom XPath if configured.

        $naturalResultsHardcoded = null;
        if ($useDbRules === self::MODE_HARDCODED || $useDbRules === self::MODE_COMPARISON) {
            $naturalResultsHardcoded = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
        }

        // ============================================================================
        // STEP 2: Calculate database-driven XPath results (if applicable modes)
        // ============================================================================

        $naturalResultsDb = null;

        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_COMPARISON) {
            // MODE_DATABASE & MODE_COMPARISON: Fetch all live DB rules for this feature
            // If $additionalRule is provided, it's prepended to the live rules array
            $rules = RuleLoaderService::getRulesForFeature('natural_results', false, $additionalRule);
            if (!empty($rules)) {
                // Combine all rules with XPath union operator (|)
                $dynamicXpath = implode(' | ', $rules);
                $naturalResultsDb = $dom->xpathQuery($dynamicXpath, $node);
            }
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            // MODE_CANDIDATE_TESTING: ISOLATED CANDIDATE TESTING
            // Use ONLY the $additionalRule, ignore all other live rules
            // Used by self-healing parser investigation workflow
            if ($additionalRule !== null) {
                $rules = RuleLoaderService::getRulesForFeature('natural_results', false, $additionalRule);
                if (!empty($rules)) {
                    // Extract only the additional rule (it's prepended as first element)
                    $additionalRuleXpath = reset($rules);
                    $naturalResultsDb = $dom->xpathQuery($additionalRuleXpath, $node);
                }
            }
        }

        // ============================================================================
        // STEP 3: Select final results based on mode
        // ============================================================================

        if ($useDbRules === self::MODE_HARDCODED) {
            // MODE_HARDCODED: Production default - use reference XPath only
            $naturalResults = $naturalResultsHardcoded;

        } elseif ($useDbRules === self::MODE_DATABASE) {
            // MODE_DATABASE: Database rules (future production)
            if ($naturalResultsDb !== null) {
                // Use DB rules for parsing
                $naturalResults = $naturalResultsDb;
            } else {
                // Fallback: No DB rules found, use reference XPath
                Logger::error('No DB rules found for natural_results');
                $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);

                // ON-CALL ALERT: DB rules pipeline is broken — parser had to fallback
                try {
                    $alertTitle = 'SERP Parser: DB rules fallback — Desktop';
                    $oncallAlert = new IncidentResponseClient();
                    $oncallAlert->triggerOrResolveEvent(
                        IncidentResponseClient::SERVICE_PARSERS,
                        $alertTitle,
                        [
                            'title' => $alertTitle,
                            'description' => 'MODE_DATABASE is active but no DB rules were found for natural_results (desktop). ' .
                                'Parser fell back to hardcoded XPath rules. This means the DB rules pipeline is broken.',
                            'fields' => [
                                ['title' => 'Feature', 'value' => 'natural_results', 'short' => true],
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
            // MODE_COMPARISON: Comparison/monitoring mode
            // Always use reference XPath for production results (no risk)
            $naturalResults = $naturalResultsHardcoded;

            // Compare with DB rules and log any mismatches
            if ($naturalResultsDb !== null && $naturalResultsHardcoded->length !== $naturalResultsDb->length) {
                Logger::error('XPath rule mismatch detected', [
                    'hardcoded_count' => $naturalResultsHardcoded->length,
                    'db_count' => $naturalResultsDb->length,
                    'difference' => abs($naturalResultsHardcoded->length - $naturalResultsDb->length),
                    'additional_rule_id' => $additionalRule
                ]);

                // ON-CALL ALERT: DB rules produce different results than hardcoded
                try {
                    $alertTitle = 'SERP Parser: XPath rule mismatch — Desktop';
                    $oncallAlert = new IncidentResponseClient();
                    $oncallAlert->triggerOrResolveEvent(
                        IncidentResponseClient::SERVICE_PARSERS,
                        $alertTitle,
                        [
                            'title' => $alertTitle,
                            'description' => 'MODE_COMPARISON detected a mismatch between hardcoded and DB XPath rules for natural_results (desktop). ' .
                                'Hardcoded rules found ' . $naturalResultsHardcoded->length . ' results, DB rules found ' . $naturalResultsDb->length . ' results ' .
                                '(difference: ' . abs($naturalResultsHardcoded->length - $naturalResultsDb->length) . '). ' .
                                'Production parsing is unaffected (using hardcoded), but DB rules need investigation before switching to MODE_DATABASE.',
                            'fields' => [
                                ['title' => 'Feature', 'value' => 'natural_results', 'short' => true],
                                ['title' => 'Mode', 'value' => 'MODE_COMPARISON (2)', 'short' => true],
                                ['title' => 'Hardcoded Count', 'value' => (string) $naturalResultsHardcoded->length, 'short' => true],
                                ['title' => 'DB Count', 'value' => (string) $naturalResultsDb->length, 'short' => true],
                                ['title' => 'Difference', 'value' => (string) abs($naturalResultsHardcoded->length - $naturalResultsDb->length), 'short' => true],
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
            // MODE_CANDIDATE_TESTING: Isolated candidate testing (investigation only)
            if ($naturalResultsDb !== null) {
                // Use the single candidate rule being tested
                $naturalResults = $naturalResultsDb;
            } else {
                // Fallback: Candidate rule failed or not provided
                Logger::error('No additional rule found or provided for mode 3', [
                    'additional_rule_id' => $additionalRule
                ]);
                $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
            }
        }

        if ($naturalResults->length == 0) {
            if ($node->getAttribute('id') == 'rso') {
                $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, [], $node));
            }

            return;
        }

        $k = 0;

        foreach ($naturalResults as $organicResult) {

            if ($this->skiResult($dom, $organicResult)) {
                continue;
            }

            $k++;
            $this->parseNode($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);
        }

        if ($this->gotoDomainLinkCount > 0) {
            $this->monolog->notice('Google goto link detected - using base domain link', [
                'device' => 'desktop',
                'count' => $this->gotoDomainLinkCount,
            ]);
        }
    }

    protected function skiResult(GoogleDom $googleDOM, DomElement $organicResult)
    {

        // Organic result is identified as top ads
        if ($googleDOM->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tads')]", $organicResult)->length > 0) {
            return true;
        }

        if ($googleDOM->xpathQuery("ancestor::g-accordion-expander ", $organicResult)->length > 0) {
            return true;
        }

        if ($googleDOM->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tvcap ')]", $organicResult)->length > 0) {
            return true;
        }

        // Recipes are identified as organic result
        if ($organicResult->getChildren()->hasClasses(['rrecc'])) {
            return true;
        }

        // This result is a featured snipped. It it has another div with class g that contains organic results -> avoid duplicates
        if ($organicResult->hasClasses(['mnr-c'])) {
            return true;
        }

        if ($organicResult->hasClasses(['g-blk'])) {
            return true;
        }

        $fsnParent = $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' xpdopen ')]", $organicResult);

        if ($fsnParent->length > 0) {

            return true;
        }


        // Avoid getting  results from questions (when clicking "Show more". When clicking "Show more" on questions)
        // The result under it looks exactly like a natural results
        $questionParent = $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' related-question-pair ')]", $organicResult);

        if ($questionParent->length > 0) {

            return true;
        }

        $hasParentG = $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $organicResult);
        $hasParentFxLDp = $googleDOM->getXpath()->query("ancestor::ul[contains(concat(' ', normalize-space(@class), ' '), ' FxLDp ')]", $organicResult);
        $hasSameChild = false;

        if ($organicResult->hasClasses(['MYVUIe'])) {
            $hasChildG = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $organicResult);

            if ($hasChildG->length > 0) {
                $hasSameChild = true;
            }
        }

        if (($hasParentG->length > 0 && $hasParentFxLDp->length == 0) || $hasSameChild) {
            return true;
        }

        $hasChildKp = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' kp-wholepage ')]", $organicResult);

        if ($hasChildKp->length > 0) {
            return true;
        }
        //
        $currencyPlayer = $googleDOM->getXpath()->query('descendant::div[@id="knowledge-currency__updatable-data-column"]', $organicResult);

        if ($currencyPlayer->length > 0) {
            return true;
        }

        return false;
    }
}
//
