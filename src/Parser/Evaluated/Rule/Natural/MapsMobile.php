<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class MapsMobile implements ParsingRuleInterface
{
    protected $steps = ['version1', 'version2', 'version3'];
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
     * Mobile-only class — always resolves to the mobile feature.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'maps_mobile';
    }

    /**
     * Mobile-only class — always resolves to the mobile match feature.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return 'maps_mobile_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container checks (scm-c / qixVud / xxAJT).
        // Mirror Maps.php: union the desktop + mobile match features so a renamed container still
        // resolves. Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['maps_match', 'maps_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('maps_match'),
                    RuleLoaderService::getRulesForFeature('maps_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if (str_contains($node->getAttribute('class'),  'scm-c')|| str_contains($node->getAttribute('class'),  'qixVud') ||  str_contains($node->getAttribute('class'),  'xxAJT')) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary listing-title extraction (div[@class='rllt__details'])
        // lives in the 'maps_mobile' parent feature. The legacy g-review-stars (version1) layout
        // and the positional logic stay hardcoded as fallbacks.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // MapsMobile has no parse children — resolve candidate rules by this feature only.
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
                // DB rules matched nothing — fall through to hardcoded steps below.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded.
        }

        // Hardcoded fallback (version1 / version2 / version3 chain)
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    /**
     * Extract listing titles using DB rules (primary version2 rllt__details pattern).
     * Uses the MOBILE DOM walk (title two levels under the matched node, mirroring version2),
     * with a fallback to the matched node's own text. Returns true when at least one listing
     * was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $ratingStars = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('MapsMobile DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($ratingStars->length == 0) {
            return false;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            // Mobile rllt__details layout: the business name sits two levels down (mirrors version2).
            $title = '';
            if ($ratingStarNode->firstChild && $ratingStarNode->firstChild->firstChild) {
                $title = $ratingStarNode->firstChild->firstChild->textContent;
            }
            // Fallback: the matched node's own text content.
            if ($title === '' || $title === null) {
                $title = trim($ratingStarNode->textContent);
            }
            if ($title === '') {
                continue;
            }

            $spanElements[] = [
                'title' => $title,
                'href' => null,
            ];
        }

        if (!empty($spanElements)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            return true;
        }

        return false;
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("./descendant::span[@role='heading']/text()", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements[] = [
                'title' => $ratingStarNode->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' rllt__details')]", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements[] = [
                'title' => $ratingStarNode->firstChild->firstChild->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query('descendant::g-review-stars', $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements[] = [
                'title' => $ratingStarNode->parentNode->parentNode->childNodes[0]->childNodes[0]->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP_MOBILE, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
