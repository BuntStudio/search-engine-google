<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

/**
 * Mobile sibling of Videos.php — the "videos" widget on mobile results.
 *
 * Self-healing parser integration (2026-06-18, §9.6 shape (c) — separate mobile class wired):
 * the container-detection (videos_mobile_match) and the primary video-link extraction
 * (videos_mobile parent — the version2 video-voyager anchor walk) are mirrored into DB rules, with
 * the 7-version hardcoded fallback chain kept intact behind them.
 */
class VideosMobile implements ParsingRuleInterface
{
    protected $steps = ['version1', 'version2', 'version3', 'version4', 'version5', 'version6', 'version7'];
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
     * Get the feature name based on mobile flag. This is the mobile-only class, so it resolves to
     * the mobile feature regardless of the flag.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'videos_mobile';
    }

    /**
     * Get the match feature name (mobile-only class).
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return 'videos_mobile_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded class-combo container checks. Mode 3 consults the
        // heal candidate; mode 1 uses live rules. (Mobile-only class, so the videos_mobile_match
        // feature alone.)
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['videos_mobile_match'])
                : RuleLoaderService::getRulesForFeature('videos_mobile_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($node->hasClass('cawG4b') && $node->hasClass('OvQkSb')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('uVMCKf') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('uVMCKf') && $node->hasClass('Ww4FFb')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('HD8Pae') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('YJpHnb') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('vtSz8d') && $node->hasClass('Ww4FFb') && $node->hasClass('vt6azd')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('EDblX') && $node->hasClass('HG5ZQb')) {
            //this is a general list, search for video children
            $videosPlayers = $dom->getXpath()->query('descendant::div[@class="oj7Mub eVNxY"]', $node);
            if ($videosPlayers->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary mobile video-link extraction (the version2 video-voyager
        // anchor walk) lives in the 'videos_mobile' parent feature. VideosMobile has no parse
        // children, so candidate rules resolve by this feature only. The 7-version hardcoded
        // fallback chain below is preserved.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                $rules = (is_array($additionalRule))
                    ? RuleLoaderService::getRulesByIdsForFeature($additionalRule, $featureName)
                    : [];
            } else {
                $rules = RuleLoaderService::getRulesForFeature($featureName);
            }

            if (!empty($rules)) {
                if ($this->parseWithDbRules($dom, $node, $resultSet, $rules)) {
                    return;
                }
                // DB rules matched nothing — fall through to hardcoded chain.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded chain.
        }

        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    /**
     * Extract video-link anchors using DB rules (primary mobile extractor — the version2
     * video-voyager anchor walk generalized to a DB selector). Returns true when at least one
     * video item was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $aHrefs = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('VideosMobile DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($aHrefs->length == 0) {
            return false;
        }

        $data = [];

        foreach ($aHrefs as $url) {
            $href = $url->getAttribute('href');
            if (empty($href)) {
                continue;
            }
            $data[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($href)];
        }

        if (empty($data)) {
            return false;
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));

        return true;
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $data = [];

        if($node->parentNode->tagName !='a') {
            return;
        }

        $data[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($node->parentNode->getAttribute('href'))];

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosContainer = $googleDOM->getXpath()->query("descendant::video-voyager", $node);

        if ($videosContainer->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosContainer as $videoNode) {
            $url = $googleDOM->getXpath()->query("descendant::a", $videoNode)->item(0);

            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($url->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::span[@class="OPkOif"]', $node);

        if ($videosPlayerBtns->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $url = $videoBtn->parentNode->parentNode->parentNode->parentNode->parentNode;

            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($url->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version4(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::a[@class="BG7Pyb"]', $node);

        if ($videosPlayerBtns->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($videoBtn->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version5(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::a[@class="ddkIM c30Ztd"]', $node);

        if ($videosPlayerBtns->length == 0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($videoBtn->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version6(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        if (!($node->hasClass('vtSz8d') && $node->hasClass('Ww4FFb') && $node->hasClass('vt6azd'))) {
            return;
        }

        $urls = [];

        $elements = $googleDOM->getXpath()->query('descendant::a|descendant::div[@data-sulr or @data-curl]', $node);

        foreach ($elements as $element) {
            // For 'a' elements, check the href attribute
            if ($element->nodeName === 'a') {
                $href = $element->getAttribute('href');
                if ($href !== '#' && !str_starts_with($href, '#')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($href);
                }
            }
            // For 'div' elements, check for data-sulr or data-curl attributes
            else if ($element->nodeName === 'div') {
                if ($element->hasAttribute('data-sulr')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-sulr'));
                }
                if ($element->hasAttribute('data-curl')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-curl'));
                }
            }
        }
        $urls = array_unique($urls);
        if (!empty($urls)) {
            $data = [];
            foreach ($urls as $url) {
                $data[] = ['url' => $url];
            }
            $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }

    protected function version7(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayers = $googleDOM->getXpath()->query('descendant::div[@class="oj7Mub eVNxY"]', $node);
        if ($videosPlayers->length == 0) {
            return;
        }

        $urls = [];


        foreach ($videosPlayers as $videosPlayer) {
            $elements = $googleDOM->getXpath()->query('descendant::a|descendant::div[@data-sulr or @data-curl]', $videosPlayer);

            foreach ($elements as $element) {
                // For 'a' elements, check the href attribute
                if ($element->nodeName === 'a') {
                    $href = $element->getAttribute('href');
                    if ($href !== '#' && !str_starts_with($href, '#')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($href);
                    }
                }
                // For 'div' elements, check for data-sulr or data-curl attributes
                else if ($element->nodeName === 'div') {
                    if ($element->hasAttribute('data-sulr')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-sulr'));
                    }
                    if ($element->hasAttribute('data-curl')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-curl'));
                    }
                }
            }
        }

        $urls = array_unique($urls);
        if (!empty($urls)) {
            $data = [];
            foreach ($urls as $url) {
                $data[] = ['url' => $url];
            }
            $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }
}
