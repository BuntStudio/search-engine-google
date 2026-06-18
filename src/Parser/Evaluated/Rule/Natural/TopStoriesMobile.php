<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class TopStoriesMobile extends TopStories
{
    protected $steps = ['version1', 'version2'];

    /**
     * Mobile-only class — always resolves to the mobile feature.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'top_stories_mobile';
    }

    /**
     * Mobile-only class — always resolves to the mobile match feature.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return 'top_stories_mobile_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded positive container class gates (xSoq1 / lU8tTd).
        // Union desktop + mobile match features so a renamed container still resolves (mirrors
        // MapsMobile.php). The lU8tTd EXCLUSION logic (social-media / perspectives / "what people
        // are saying") stays hardcoded below and only runs in the hardcoded fallback path.
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

        if ($node->hasClass('xSoq1')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('lU8tTd')) {

            $whatPeopleAreSayingElement = $dom->getXpath()->query("descendant::div[contains(@class, 'koZ5uc')]", $node);
            if ($whatPeopleAreSayingElement->length > 0) {
                return;
            }

            $socialMediaElement = $dom->getXpath()->query('descendant::*[contains(concat(" ", @class, " "), " JJZKK ") and contains(concat(" ", @class, " "), " rsmgO ")]', $node);
            if ($socialMediaElement->length) {
                return self::RULE_MATCH_NOMATCH;
            }

            // New rule for jsname="K9a4Re" with no data-hveid
            $socialMediaJsnameElement = $dom->getXpath()->query('descendant::*[@jsname="K9a4Re" and not(@data-hveid)]', $node);
            $socialMediaCrustElement = $dom->getXpath()->query('ancestor-or-self::div[@data-crust-trigger="158133"]', $node);
            if ($socialMediaCrustElement->length) {
                return self::RULE_MATCH_NOMATCH;
            }

            $perspectivesElement = $dom->getXpath()->query("descendant::div[contains(@class, 'lSfe4c Qxqlrc')]", $node);
            $forContextElement = $dom->getXpath()->query("descendant::div[contains(@class, 'gpjNTe')]", $node);
            // for Context is news
            if (
                $perspectivesElement->length > 0 &&
                $forContextElement->length = 0
            ) {
                return;
            }

            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }
}
