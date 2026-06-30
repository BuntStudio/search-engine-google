<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class VisualDigest implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Visual Digest is a single-gate feature (gate-only integrated; per-item
     * extraction left hardcoded). Desktop and mobile have DIFFERENT gate selectors
     * and are fully separate classes, so each resolves its OWN top-level feature
     * name — there is no shared match family.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'visual_digest_mobile' : 'visual_digest';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; other DB modes use live rules.
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['visual_digest'])
                : RuleLoaderService::getRulesForFeature('visual_digest');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if (strpos($node->getAttribute('class'), 'e8Ck0d') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Per-item extraction is left hardcoded (conservative Step 2 selection):
        // it keys off the semantic, rarely-changing data-attrid="VisualDigest" token.
        $visualDigestItems = $dom->getXpath()->query('descendant::*[contains( @data-attrid,"VisualDigest" )]   ', $node);
        $item = [];

        if ($visualDigestItems->length > 1) {
            foreach ($visualDigestItems as $visualDigestItem) {
                $visualDigestType = $visualDigestItem->getAttribute('data-attrid');
                $link = $dom->getXpath()->query('descendant::a', $visualDigestItem);
                $info = true;
                if (!empty($link->item(0))) {
                    $info = $link->item(0)->getAttribute('href');
                }
                $item[] = [$visualDigestType => $info];
            }

            $resultSet->addItem(new BaseResult(NaturalResultType::VISUAL_DIGEST , $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }
}
