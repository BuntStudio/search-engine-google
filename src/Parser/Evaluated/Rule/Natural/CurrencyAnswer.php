<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;

class CurrencyAnswer implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    public $hasSerpFeaturePosition = true;

    /**
     * Currency Answer (Direct Answer) is a presence-only, DESKTOP-ONLY feature: this class is
     * registered in NaturalParser only (no CurrencyAnswerMobile.php sibling, and
     * MobileNaturalParser does not parse it). So it resolves to a single top-level feature name
     * `currency_answer` (single-gate shape — no _match child, no mobile sibling). Precedent:
     * Definitions / Jobs (likewise presence-only single-gate).
     */
    protected static function getFeatureName($isMobile)
    {
        return 'currency_answer';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed container can
            // validate; other DB modes use live rules. Desktop-only feature, single match family.
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['currency_answer'])
                : RuleLoaderService::getRulesForFeature('currency_answer');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Match ONLY the currency node itself (self::), NOT any ancestor that merely contains it.
        // The former descendant probe (`.//div[@id="knowledge-currency__updatable-data-column"]`)
        // over-matched an ancestor wrapper that starts above the preceding organic result, so the
        // serp feature position was computed from the wrapper (position 1) instead of the currency
        // box's real location (position 2). Mirrors DB rule 598 (self::*[@id=...]). Operator-confirmed
        // there is one organic result above the converter → position 2 is correct (mode-2 parity,
        // site 125972 'nz forex' 2026-06-30). See project_shp_match_axis_position_overmatch.
        if ($node->getAttribute('id') == 'knowledge-currency__updatable-data-column') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Presence-only feature: emit a BaseResult flagging the block, with an empty results array
        // (no per-result sub-node extraction). Nothing to vary by DB mode in parse() — the only
        // healable rule is the container gate, handled in match().
        $resultSet->addItem(new BaseResult(NaturalResultType::CURRENCY_ANSWER, [], $node, $this->hasSerpFeaturePosition));
    }
}
