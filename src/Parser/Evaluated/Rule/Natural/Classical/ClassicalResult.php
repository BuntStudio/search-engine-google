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

class ClassicalResult extends AbstractRuleDesktop implements ParsingRuleInterface
{
    protected $gotoDomainLinkCount = 0;
    protected $currentUseDbRules = 0;

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

    public function match(GoogleDom $dom, DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE) {
            $matchRules = RuleLoaderService::getRulesForFeature('natural_results_match');
            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $result = $dom->getXpath()->query($matchXpath, $node);
                return $result->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules for match — fall through to hardcoded
        }

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

        // Sitelinks detection — hardcoded rules
        $sitelinksBigXpath = "descendant::table[@class='jmjoTe']";
        $sitelinksSmallXpath = "descendant::div[@class='HiHjCd']";

        if ($dom->xpathQuery($sitelinksBigXpath, $organicResult)->length > 0) {
            (new SiteLinksBig())->parse($dom, $organicResult, $resultSet, false);
        }

        $parentWithSameClass = $dom->xpathQuery("ancestor::div[@class='g']", $organicResult);

        if ($parentWithSameClass->length > 0) {
            if ($dom->xpathQuery($sitelinksBigXpath, $parentWithSameClass->item(0))->length > 0) {
                (new SiteLinksBig())->parse($dom, $parentWithSameClass->item(0), $resultSet, false);
            }
        }

        if ($dom->xpathQuery($sitelinksSmallXpath, $organicResult)->length > 0) {
            (new SiteLinksSmall())->parse($dom, $organicResult, $resultSet, false);
        }

        if ($parentWithSameClass->length > 0) {
            if ($dom->xpathQuery($sitelinksSmallXpath, $parentWithSameClass->item(0))->length > 0) {
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
     * @param GoogleDom $dom The Google DOM object
     * @param \DomElement $node The node to parse
     * @param IndexedResultSet $resultSet Result set to populate
     * @param bool $isMobile Mobile detection flag
     * @param array $doNotRemoveSrsltidForDomains Domains to preserve rsltid parameter
     * @param int $useDbRules Parser mode: 0=hardcoded, 1=DB rules, 3=candidate testing
     * @param array|int|null $additionalRule Rule ID(s) to test. Mode 3: array of all rule IDs to use. Mode 1: single rule ID to prepend.
     * @return void
     */
    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $this->gotoDomainLinkCount = 0;
        $this->currentUseDbRules = $useDbRules;

        if ($useDbRules === self::MODE_DATABASE) {
            $singleRuleId = is_int($additionalRule) ? $additionalRule : null;
            $rules = RuleLoaderService::getRulesForFeature('natural_results', false, $singleRuleId);
            if (!empty($rules)) {
                $dynamicXpath = implode(' | ', $rules);
                $naturalResults = $dom->xpathQuery($dynamicXpath, $node);
            } else {
                Logger::error('No DB rules found for natural_results, falling back to hardcoded');
                $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
            }
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($additionalRule !== null && is_array($additionalRule)) {
                // Only use rules that belong to this feature; fall back to mode 1 otherwise
                $rules = RuleLoaderService::getRulesByIdsForFeature($additionalRule, 'natural_results');
                if (!empty($rules)) {
                    $dynamicXpath = implode(' | ', $rules);
                    $naturalResults = $dom->xpathQuery($dynamicXpath, $node);
                } else {
                    // Rules don't belong to this feature — use live DB rules (mode 1 behavior)
                    $rules = RuleLoaderService::getRulesForFeature('natural_results');
                    if (!empty($rules)) {
                        $dynamicXpath = implode(' | ', $rules);
                        $naturalResults = $dom->xpathQuery($dynamicXpath, $node);
                    } else {
                        $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
                    }
                }
            } else {
                Logger::error('No rule IDs provided for mode 3');
                $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
            }
        } else {
            // MODE_HARDCODED (default)
            $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
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
        // Skip filters are always hardcoded — they use complex compound logic
        // (hasClasses, parentNode traversal, multi-condition checks) that can't be
        // reliably expressed as single XPath rules. They also rarely break since
        // Google changes result appearance more often than structural separation.

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
