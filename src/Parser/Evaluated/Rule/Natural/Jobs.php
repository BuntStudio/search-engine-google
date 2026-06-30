<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class Jobs implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Jobs is a presence-only feature; the single Jobs class is registered in BOTH the desktop
     * (NaturalParser) and mobile (MobileNaturalParser) parsers (§9.6 shape (a)), so match() has no
     * $isMobile context. Desktop + mobile therefore share one match family — like Definitions.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'jobs_mobile' : 'jobs';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed container can
            // validate; other DB modes use live rules. match() has no $isMobile, so union both
            // device match families (the same class serves desktop + mobile).
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['jobs', 'jobs_mobile'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('jobs'),
                    RuleLoaderService::getRulesForFeature('jobs_mobile')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($node->hasClass('gws-plugins-horizon-jobs__li-ed')) {
            return self::RULE_MATCH_MATCHED;
        }

        if (strpos($node->getAttribute('jscontroller'), 'G42bz') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if (
            strpos($node->getAttribute('jscontroller'), 'b11o3b') !== false ||
            strpos($node->parentNode->getAttribute('jscontroller'), 'b11o3b') !== false
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::JOBS_MOBILE : NaturalResultType::JOBS;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) {
            return;
        }

        // Presence-only feature: emit a BaseResult flagging the block, with an empty results array
        // (no per-result sub-node extraction). Nothing to vary by DB mode in parse() — the only
        // healable rule is the container gate, handled in match().
        $resultSet->addItem(
            new BaseResult($this->getType($isMobile), [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }
}
