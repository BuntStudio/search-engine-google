<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class Questions implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * People Also Ask / Questions is integrated as a presence-only feature for the SHP:
     * a single top-level feature per device carries the container/gate rule directly
     * (no parent + `_match` child). One class handles both desktop and mobile via the
     * $isMobile flag, so it resolves to `questions` / `questions_mobile`.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'questions_mobile' : 'questions';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; other DB modes use live rules.
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['questions', 'questions_mobile'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('questions'),
                    RuleLoaderService::getRulesForFeature('questions_mobile')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($node->hasClass('related-question-pair')) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::QUESTIONS_MOBILE : NaturalResultType::QUESTIONS;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Extraction is intentionally left hardcoded (conservative Step 2 selection): the
        // generic first-anchor url + first-span title is not integrated as a DB rule. Only
        // the container/gate (match()) is self-healed for this presence-only feature.
        $urlsNodes  = $dom->getXpath()->query('descendant::a', $node);
        $qTextNodes = $dom->getXpath()->query('descendant::span', $node);
        $firstUrl = '';
        $qText = '';
        if ($urlsNodes->length > 0) {
            $firstUrl = $urlsNodes->item(0)->getAttribute('href');
        }
        if ($qTextNodes->length > 0) {
            $qText = $qTextNodes->item(0)->getNodeValue();
        }
        $resultSet->addItem(
            new BaseResult($this->getType($isMobile), ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($firstUrl), 'title' => $qText], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );

    }
}
