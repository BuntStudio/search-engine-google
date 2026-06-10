<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class FeaturedSnipped implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2'];

    /**
     * Parser mode constants for self-healing parser integration.
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Get the feature name based on mobile flag.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'featured_snippet_mobile' : 'featured_snippet';
    }

    /**
     * Get the _match detection feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'featured_snippet_mobile_match' : 'featured_snippet_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — the _match feature replaces the hardcoded detection checks below.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['featured_snippet_match', 'featured_snippet_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('featured_snippet_match'),
                    RuleLoaderService::getRulesForFeature('featured_snippet_mobile_match')
                ));

            if (!empty($matchRules)) {
                // Match rules use the self:: axis: they run with the candidate element as
                // the context node (see INTEGRATING_NEW_SERP_FEATURES.md §9.2).
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        $isCandidate = false;

        if (strpos($node->getAttribute('class'), 'xpdopen') !== false || strpos($node->getAttribute('class'), 'xpdbox') !== false) {
            $isCandidate = true;
        }

        if (strpos($node->getAttribute('class'), 'CWesnb') !== false) {
            $isCandidate = true;
        }

        if ($dom->getXpath()->query('.//div[@class="MjjYud"]/div[@class="pxiwBd GqJbWc M6ON8"]', $node)->length > 0) {
            $isCandidate = true;
        }

        if (!$isCandidate) {
            return self::RULE_MATCH_NOMATCH;
        }

        // Confirm this is actually a featured snippet, not another xpdopen block
        // (e.g. "See results about", expandable sections, etc.)

        // 1. Text-based detection (most robust, resistant to CSS class rotation):
        //    Real featured snippets have a hidden h2 with text "Featured snippet from the web"
        $headings = $dom->getXpath()->query(
            'descendant::*[self::h2 or self::h3]', $node
        );
        foreach ($headings as $heading) {
            if (stripos($heading->textContent, 'featured snippet') !== false) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        // 2. Feedback URL detection (Google includes a "About featured snippets" link):
        //    The href contains "p=featured_snippets"
        $feedbackLinks = $dom->getXpath()->query(
            'descendant::a[contains(@href, "featured_snippets")]', $node
        );
        if ($feedbackLinks->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        // 3. Class-based fallback (less stable, but covers edge cases):
        //    bNg8Rb = hidden screen-reader heading class
        //    V3FYCf = snippet content wrapper class
        $hasFeaturedSnippetLabel = $dom->getXpath()->query(
            'descendant::h2[contains(@class, "bNg8Rb")]', $node
        )->length > 0;

        $hasV3FYCf = $dom->getXpath()->query(
            'descendant::div[contains(@class, "V3FYCf")]', $node
        )->length > 0;

        if ($hasFeaturedSnippetLabel || $hasV3FYCf) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::FEATURED_SNIPPED_MOBILE : NaturalResultType::FEATURED_SNIPPED;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array(
                [$this, $functionName],
                [$dom, $node, $resultSet, $isMobile, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule]
            );
        }
    }

    /**
     * Resolve the primary featured-snippet result nodes via DB rules when running in a
     * DB mode, falling back to null so the caller uses the hardcoded fallback chain.
     *
     * FeaturedSnipped has no SHP parse children — it is a single-extraction feature — so
     * candidate testing (mode 3) resolves the heal candidate against the parent feature
     * name directly (the parse-family resolution of §9.1 does not apply here).
     *
     * @return \DOMNodeList|null
     */
    protected function queryPrimaryResultNodes(GoogleDom $googleDOM, \DomElement $node, $isMobile, $useDbRules, $additionalRule)
    {
        if ($useDbRules !== self::MODE_DATABASE && $useDbRules !== self::MODE_CANDIDATE_TESTING) {
            return null;
        }

        $featureName = self::getFeatureName($isMobile);

        if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            $rules = (is_array($additionalRule))
                ? RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName)
                : [];
        } else {
            $rules = RuleLoaderService::getRulesForFeature($featureName);
        }

        if (empty($rules)) {
            // Candidate isn't ours / no DB rules — let the caller use the hardcoded fallback.
            return null;
        }

        try {
            $xpath = implode(' | ', array_values(array_unique($rules)));
            return $googleDOM->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function version1(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = [],
        $useDbRules = self::MODE_HARDCODED,
        $additionalRule = null
    ) {
        // Primary extraction rule: prefer DB rules when available, else hardcoded fallback chain.
        $naturalResultNodes = $this->queryPrimaryResultNodes($googleDOM, $node, $isMobile, $useDbRules, $additionalRule);

        if ($naturalResultNodes === null || $naturalResultNodes->length == 0) {
            $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $node);
        }

        if ($naturalResultNodes->length == 0) {
            $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' SALvLe ')]", $node);
            if ($naturalResultNodes->length == 0) {
                // this older class is still valid
                $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' V3FYCf ')]", $node);
                if ($naturalResultNodes->length == 0) {
                    return;
                }
            }
        }

        $results = [];

        foreach ($naturalResultNodes  as $featureSnippetNode) {
            $isHidden = $googleDOM->getXpath()->query("ancestor::g-accordion-expander", $featureSnippetNode);
            if ($isHidden->length >  0) {
                continue;
            }

            $aTag = $googleDOM->getXpath()->query("descendant::a", $featureSnippetNode);
            $h3Tag = $googleDOM->getXpath()->query("descendant::h3", $featureSnippetNode);//title
            // Description: prefer semantic data-attrid, fall back to CSS class
            $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@data-attrid='wa:/description']", $featureSnippetNode);
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("descendant::div[@data-attrid='wa:/description']", $featureSnippetNode);
            }
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@class='LGOjhe']", $featureSnippetNode);
            }
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query("descendant::div[@class='LGOjhe']", $featureSnippetNode);
            }
            if ($aTag->length == 0) {
                continue;
            }

            $object              = new \StdClass();

            $object->url         = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->item(0)->getAttribute('href')),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            $object->description = (!empty($description) && !empty($description->item(0)) && !empty($description->item(0)->textContent)) ? $description->item(0)->textContent : '';
            $object->title       = (!empty($h3Tag) && !empty($h3Tag->item(0)) && !empty($h3Tag->item(0)->textContent)) ? $h3Tag->item(0)->textContent : '';

            $results[] = $object;
        }

        if(!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    public function version2(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = [],
        $useDbRules = self::MODE_HARDCODED,
        $additionalRule = null
    ) {
        // version2 (alternate layout: sXtWJb / jsname="UWckNb" + outbound-link fallback chain)
        // is intentionally LEFT HARDCODED — its fallback chain is the logic, not a single rule
        // (see INTEGRATING_NEW_SERP_FEATURES.md §3 "what NOT to integrate"). Args accepted for
        // signature compatibility with the step dispatch in parse().
        $results = [];

        $object = new \StdClass();

        // Try the primary source URL XPath (class-based)
        $sourceUrls = $googleDOM->getXpath()->query('.//a[@class="sXtWJb"]/@href', $node);

        // If not found, try the alternative XPath
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query('.//h3[@class="yuRUbf JtGQ40d MBeuO q8U8x"]//a/@href', $node);
        }

        // Try class-based result link
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query('.//div[contains(@class, "yuRUbf")]//a/@href', $node);
        }

        // Semantic fallback: first outbound link that isn't a Google-internal URL
        if ($sourceUrls->length == 0) {
            $sourceUrls = $googleDOM->getXpath()->query(
                './/a[@href and not(contains(@href, "google.com")) and not(starts-with(@href, "/search"))]/@href',
                $node
            );
        }

        // If we found a URL, process it
        if ($sourceUrls->length > 0) {
            $object->url = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($sourceUrls->item(0)->getNodeValue()),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            // Title: try class-based first, then semantic h3 fallback
            $titleElements = $googleDOM->getXpath()->query('.//a[@class="sXtWJb" and @jsname="UWckNb"]', $node);
            if ($titleElements->length == 0) {
                $titleElements = $googleDOM->getXpath()->query('.//h3', $node);
            }
            $object->title = ($titleElements->length > 0) ? trim($titleElements->item(0)->textContent) : '';

            // Direct answer: use Google's TTS marker (e.g. phone numbers, dates)
            $ttsAnswer = $googleDOM->getXpath()->query('.//div[@data-tts="answers"]', $node);
            $object->callout = ($ttsAnswer->length > 0) ? trim($ttsAnswer->item(0)->textContent) : '';

            // Description: prefer semantic data-attrid, fall back to CSS class
            $description = $googleDOM->getXpath()->query('.//div[@data-attrid="wa:/description"]', $node);
            if ($description->length == 0) {
                $description = $googleDOM->getXpath()->query('.//div[@class="LGOjhe"]', $node);
            }
            $object->description = ($description->length > 0) ? trim($description->item(0)->textContent) : '';

            $results[] = $object;
        }

        if (!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }
}
