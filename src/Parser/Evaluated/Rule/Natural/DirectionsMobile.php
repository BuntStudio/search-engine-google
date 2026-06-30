<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class DirectionsMobile implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Directions is a presence-only feature integrated as a single top-level
     * feature per device (no `_match` child). This separate mobile class
     * resolves `directions_mobile` so the seeded mobile rows are not inert.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'directions_mobile' : 'directions';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; other DB modes use live rules. Desktop +
            // mobile share an identical gate, so union both families.
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['directions', 'directions_mobile'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('directions'),
                    RuleLoaderService::getRulesForFeature('directions_mobile')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($node->getAttribute('id') == 'lud-ed'
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('jscontroller') == 'h7XEsd'
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
            $resultSet->addItem(new BaseResult(NaturalResultType::DIRECTIONS_MOBILE, [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
