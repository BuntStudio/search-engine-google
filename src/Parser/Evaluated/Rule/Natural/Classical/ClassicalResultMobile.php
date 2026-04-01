<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\AbstractRuleMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksBigMobile;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ClassicalResultMobile extends AbstractRuleMobile implements ParsingRuleInterface
{
    protected $resultType = NaturalResultType::CLASSICAL_MOBILE;
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
        // 'variant_a' => "(//div[@class='custom-selector'])",
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
        $defaultXPath = "(//div[@class='MjjYud' and not(ancestor::div[@id='bottomads' or @id='tadsb']) and .//*[contains(@class, 'MBeuO')]]) |
                    (//div[@class='MjjYud' and not(ancestor::div[@id='bottomads' or @id='tadsb']) and .//a[@jsname='UWckNb']]) |
                    (//div[@data-dsrp and not(ancestor::div[@id='bottomads' or @id='tadsb'])])";

        if (self::$currentSiteId === null) {
            return $defaultXPath;
        }

        $xpathKey = \FeatureFlags::getCustomSerpXPathKeyMobile(self::$currentSiteId);
        if ($xpathKey === null) {
            return $defaultXPath;
        }

        return self::$customXPathVariants[$xpathKey] ?? $defaultXPath;
    }

    public function match(GoogleDom $dom, DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE) {
            $matchRules = RuleLoaderService::getRulesForFeature('natural_results_mobile_match');
            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $result = $dom->getXpath()->query($matchXpath, $node);
                return $result->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules for match — fall through to hardcoded
        }

        if ($node->getAttribute('id') == 'center_col' || $node->getAttribute('id') == 'sports-app') {
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

        // Sitelinks detection — supports DB rules
        $sitelinksXpath1 = "descendant::div[@class='MUxGbd v0nnCb lyLwlc']";
        $sitelinksXpath2 = "descendant::form[@class='xBIiEf']";

        if ($this->currentUseDbRules === self::MODE_DATABASE) {
            $sitelinkRules = RuleLoaderService::getRulesForFeature('natural_results_mobile_sitelinks_detection');
            if (!empty($sitelinkRules)) {
                $sitelinksXpath1 = $sitelinkRules[0] ?? $sitelinksXpath1;
                if (count($sitelinkRules) > 1) {
                    $sitelinksXpath2 = $sitelinkRules[1] ?? $sitelinksXpath2;
                }
            }
        }

        if (
            $dom->xpathQuery(
                $sitelinksXpath1,
                $organicResult->parentNode->parentNode
            )->length > 0
        ) {
            (new SiteLinksBigMobile())->parse($dom, $organicResult->parentNode->parentNode, $resultSet, false, $doNotRemoveSrsltidForDomains);
        }


        if (
            $dom->xpathQuery(
                $sitelinksXpath2,
                $organicResult->parentNode->parentNode->parentNode
            )->length > 0 &&
            $organicResult->parentNode->parentNode->parentNode->getAttribute('class') === 'BYM4Nd'
        ) {
            (new SiteLinksBigMobile())->parse($dom, $organicResult->parentNode->parentNode->parentNode, $resultSet, false);
        }
    }

    /**
     * Parse natural search results from mobile SERP HTML
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
            $rules = RuleLoaderService::getRulesForFeature('natural_results_mobile', false, $singleRuleId);
            if (!empty($rules)) {
                $dynamicXpath = implode(' | ', $rules);
                $naturalResults = $dom->xpathQuery($dynamicXpath, $node);
            } else {
                Logger::error('No DB rules found for natural_results_mobile, falling back to hardcoded');
                $naturalResults = $dom->xpathQuery($this->getNaturalResultsXPath(), $node);
            }
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            if ($additionalRule !== null && is_array($additionalRule)) {
                // Only use rules that belong to this feature; fall back to mode 1 otherwise
                $rules = RuleLoaderService::getRulesByIdsForFeature($additionalRule, 'natural_results_mobile');
                if (!empty($rules)) {
                    $dynamicXpath = implode(' | ', $rules);
                    $naturalResults = $dom->xpathQuery($dynamicXpath, $node);
                } else {
                    // Rules don't belong to this feature — use live DB rules (mode 1 behavior)
                    $rules = RuleLoaderService::getRulesForFeature('natural_results_mobile');
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
            $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, [], $node));
            $this->monolog->error('Cannot identify results in html page', ['class' => self::class]);

            return;
        }

        $k = 0;

        foreach ($naturalResults as $organicResult) {

            if ($this->skiResult($dom, $organicResult)) {
                continue;
            }

            try {
                $k++;
                $this->parseNode($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);
            } catch (\Exception $exception) {

                // If first position detected with classical class it's not a results, do not decrement position
                if ($k > 1) {
                    $k--;
                }

                continue;
            }
        }

        if ($this->gotoDomainLinkCount > 0) {
            $this->monolog->notice('Google goto link detected - using base domain link', [
                'device' => 'mobile',
                'count' => $this->gotoDomainLinkCount,
            ]);
        }
    }

    protected function skiResult(GoogleDom $dom, DomElement $organicResult)
    {
        // Skip filters are always hardcoded — they use complex compound logic
        // (hasClasses, parentNode traversal, carousel checks, firstChild attribute checks)
        // that can't be reliably expressed as single XPath rules.

        // Organic result is identified as top ads
        if ($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tads ')]", $organicResult)->length > 0) {
            return true;
        }

        // Organic result is identified as bottom ads
        if ($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' bottomads ')]", $organicResult)->length > 0) {
            return true;
        }

        if ($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tvcap ')]", $organicResult)->length > 0) {
            return true;
        }

        // Recipes are identified as organic result
        if ($organicResult->getChildren()->hasClasses(['Q9mvUc'])) {
            return true;
        }

        if ($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), '  mnr-c ')]", $organicResult)->length > 0) {
            return true;
        }

        if ($dom->xpathQuery("ancestor::div[@id='HbKV2c']", $organicResult)->length > 0) {
            //id related to ads
            return true;
        }

        if ($dom->xpathQuery("ancestor::div[@data-text-ad]", $organicResult)->length > 0) {
            //ad result
            return true;
        }

        if ($dom->xpathQuery("descendant::*[@data-text-ad]", $organicResult)->length > 0) {
            //child is ad result
            return true;
        }

        if ($dom->xpathQuery("descendant::a[starts-with(@href, 'https://www.google.com/aclk') or starts-with(@data-rw, 'https://www.google.com/aclk')]", $organicResult)->length > 0) {
            //ad click link
            return true;
        }

        // Ignore top stories
        if ($organicResult->hasClass('zwqzjb') && $dom->xpathQuery("ancestor::g-expandable-container", $organicResult)->length > 0) {
            return true;
        }

        // Ignore maps from results
        if ($dom->xpathQuery("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' z3HNkc ')]", $organicResult)->length > 0) {
            return true;
        }

        $questionParent = $dom->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' related-question-pair ')]", $organicResult);

        if ($questionParent->length > 0) {
            return true;
        }
        $targetNode3 = $organicResult->parentNode->parentNode->parentNode;
        $targetNode4 = $organicResult->parentNode->parentNode->parentNode->parentNode;
        // Avoid getting  results from questions (when clicking "Show more". When clicking "Show more" on questions)
        // The result under it looks exactly like a natural results
        if (
            ($targetNode3 && $targetNode3 instanceof DOMElement && $targetNode3->getAttribute('class') == 'ymu2Hb') ||
            ($targetNode3 && $targetNode3 instanceof DOMElement && $targetNode3->getAttribute('class') == 'dfiEbb') ||
            ($targetNode4 && $targetNode4 instanceof DOMElement && $targetNode4->getAttribute('class') == 'ymu2Hb')
        ) {

            return true;
        }

        // The organic result identified as "Find results on"
        $carouselNode = $dom->xpathQuery("descendant::g-scrolling-carousel", $organicResult);
        if (
            $carouselNode->length > 0 &&
            $dom->xpathQuery("descendant::g-inner-card", $organicResult)->length > 0
        ) {

            // If the  direct parent of the carousel is the class from classical results -> meaning that there is no classical result here to be parsed.
            // If the  direct parent of the carousel is NOT the class from classical results -> this is a classical result and under it is a carousel. need to parse the node and identify title/url/description
            if (preg_match('/mnr\-c/', $carouselNode->item(0)->parentNode->getAttribute('class'))) {
                return true;
            }
        }

        // Result has carousel in it
        if (
            $dom->xpathQuery("descendant::g-scrolling-carousel", $organicResult)->length > 0 &&
            $dom->xpathQuery("descendant::svg", $organicResult)->length > 0 &&
            (  // And carousel have title like "Results in "
                $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' pxp6I MUxGbd ')]", $organicResult)->length > 0 ||
                // Temporary keep this
                $dom->getXpath()->query("descendant::table", $organicResult)->length > 0
            )
        ) {
            return true;
        }

        // Avoid getting results  such as "people also ask" near a regular result; (it's not a "people also ask" but the functionality is exactly like "people also ask")
        // It's like an expander with click on a main text. The results under it looks like a regular classical result
        if (
            !empty($organicResult->firstChild) &&
            !$organicResult->firstChild instanceof \DOMText &&
            ($organicResult->firstChild->getAttribute('class') == 'g card-section' ||
                strpos($organicResult->firstChild->getAttribute('class'), 'cUnQKe') !== false)
        ) {
            return true;
        }

        return false;
    }
}
