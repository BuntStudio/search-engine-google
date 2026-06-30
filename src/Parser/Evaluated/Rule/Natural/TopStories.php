<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class TopStories implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    private $hasSerpFeaturePosition = true;
    private $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2', 'version3', 'version4'];

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
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'top_stories_mobile' : 'top_stories';
    }

    /**
     * Get the match feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'top_stories_mobile_match' : 'top_stories_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container checks (g-section-with-header.yG4QQe /
        // g-expandable-container[jscontroller=QE1bwd] / id=kp-wp-tab-cont-Latest). Union the desktop
        // + mobile match features so a renamed container still resolves (mirrors Maps.php).
        // Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['top_stories_match', 'top_stories_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('top_stories_match'),
                    RuleLoaderService::getRulesForFeature('top_stories_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if (($node->parentNode->hasAttribute('jscontroller') &&
                $node->parentNode->getAttribute('jscontroller') == 'QE1bwd' &&
                $node->parentNode->tagName == 'g-expandable-container') ||
            ($node->tagName == 'g-section-with-header' && $node->hasClass('yG4QQe'))

        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if(
            $node->tagName == 'g-section-with-header' &&
            $node->hasClass('yG4QQe') &&
            $node->hasClass('TBC9ub')
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasAttribute('id') && $node->getAttribute('id') == 'kp-wp-tab-cont-Latest') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the live story-link extraction variants are now ALL expressed as DB
        // rules on the 'top_stories'/'top_stories_mobile' parent feature: version1 (g-inner-card
        // card), version2/4 (a.WlydOe anchor — the layout Google currently serves) and version3
        // (g-card+g-img card). parseWithDbRules() handles both card-level and anchor-level rules.
        //
        // STRICT: DB mode (and candidate testing) MUST NOT fall through to the hardcoded
        // version1-4 chain. Falling through would let the legacy hardcoded path silently re-detect
        // when the DB rule matches nothing, which (a) masks a dead/broken DB rule from mode-2
        // parity, (b) lets a candidate rule that matches 0 nodes falsely "pass" validation
        // (failure-mode-D), and (c) makes the feature impossible to disaster-test. The hardcoded
        // version1-4 steps are retained below ONLY for hardcoded/comparison modes (0/2).
        // See docs/self-healing-serp-parser/disaster-tests/DISASTER_TEST_top_stories_2026-06-29_01.md
        // and ClickUp 869dwwa23.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // Top Stories has no parse children — resolve candidate rules by this feature only.
                $rules = (is_array($additionalRule))
                    ? RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName)
                    : [];
            } else {
                $rules = RuleLoaderService::getRulesForFeature($featureName);
            }

            if (!empty($rules)) {
                // Run the DB rules and return regardless of how many stories they matched — no
                // fallthrough to the hardcoded chain (that is what STRICT means).
                $this->parseWithDbRules($dom, $node, $resultSet, $rules, $isMobile);
                return;
            }
            // Only when the feature has NO DB rules at all (not yet integrated) do we fall through
            // to the hardcoded extraction below.
        }

        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    /**
     * Extract story links using DB rules.
     *
     * Each rule may select EITHER a story card (e.g. g-inner-card / g-card — take the first
     * descendant anchor's href) OR the story anchor itself (e.g. a.WlydOe — take its own href).
     * Results are de-duplicated by final URL so a union rule whose selectors overlap on the same
     * story (a card and the anchor inside it) does not double-count.
     *
     * Returns true when at least one story was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules, $isMobile)
    {
        try {
            $xpath = implode(' | ', $rules);
            $stories = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('TopStories DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($stories->length == 0) {
            return false;
        }

        $items = [];
        $seen  = [];

        foreach ($stories as $story) {
            // Anchor-level rule (a.WlydOe): the matched node IS the story link.
            // Card-level rule (g-inner-card / g-card): take the first descendant anchor's href.
            if (strtolower($story->tagName) === 'a') {
                $link = $story->getAttribute('href');
            } else {
                $aNode = $dom->getXpath()->query('descendant::a', $story);
                if (!($aNode instanceof DomNodeList) || $aNode->length === 0) {
                    continue;
                }
                $link = $aNode->item(0)->getAttribute('href');
            }

            $url = \SM_Rank_Service::getUrlFromGoogleTranslate($link);
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $items['news'][] = ['url' => $url];
        }

        if (!empty($items)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
            return true;
        }

        return false;
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'e2BEnf q8U8x')]", $node);
        if ($storiesIcon->length == 0) {
            return;
        }

        $stories = $googleDOM->getXpath()->query('descendant::g-inner-card', $node);
        $items   = [];

        if ($stories->length == 0) {
            return;
        }

        foreach ($stories as $urlNode) {
            $aNode = $googleDOM->getXpath()->query('descendant::a', $urlNode);

            if ($aNode instanceof DomNodeList && $aNode->length > 0) {
                $link            = $aNode->item(0)->getAttribute('href');
                $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($link)];
            }
        }

        if (!empty($items)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::TOP_STORIES_MOBILE : NaturalResultType::TOP_STORIES;
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::span[contains(@class, 'rq6B5b VDgVie')]", $node);
        if (!$isMobile && $storiesIcon->length == 0) {
            return;
        }
        $hrefsNodes = $googleDOM->getXpath()->query("descendant::a[contains(@class,'WlydOe')]", $node);

        if (!$hrefsNodes instanceof DomNodeList) {
            return;
        }

        if ($hrefsNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($hrefsNodes as $hrefNode) {
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNode->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        // Leave this "if" here
        // It is possible to find results based on third condition by id "kp-wp-tab-cont-Latest" and identify urls with "version2" method
        // And it's not necessarily to add twice this type of results.
        if($resultSet->hasType($this->getType($isMobile))) {
            return;
        }

        $cards = $googleDOM->getXpath()->query("descendant::g-card", $node);

        if (!$cards instanceof DomNodeList) {
            return;
        }

        if ($cards->length == 0) {
            return;
        }

        $items = [];

        foreach ($cards as $story) {
            $imgNode = $googleDOM->getXpath()->query("descendant::g-img", $story);

            if($imgNode->length ==0) {
                continue;
            }
            $hrefNodes = $googleDOM->getXpath()->query("descendant::a", $story);

            if($hrefNodes->length == 0) {
                continue;
            }
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNodes->item(0)->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version4(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $storiesIcon = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'e2BEnf q8U8x')]", $node);
        if ($storiesIcon->length == 0) {
            return;
        }

        $whatPeopleAreSaying = $googleDOM->getXpath()->query("descendant::div[contains(@class, 'OSrXXb rbYSKb LfVVr esJEyb')]", $node);
        if ($whatPeopleAreSaying->length > 0) {
            return;
        }

        $hrefsNodes = $googleDOM->getXpath()->query("descendant::a[contains(@class,'WlydOe')]", $node);

        if (!$hrefsNodes instanceof DomNodeList) {
            return;
        }

        if ($hrefsNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($hrefsNodes as $hrefNode) {
            /** @var $hrefNode DomElement */
            $items['news'][] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefNode->getAttribute('href'))];
        }

        if (!empty($items)) {
            $resultSet->addItem(new BaseResult($this->getType($isMobile), $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }
}
