<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;

class VisualDigestMobile implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_COMPARISON        = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Mobile Visual Digest is a fully separate class (implements
     * ParsingRuleInterface, not extends VisualDigest) with its OWN gate selector
     * (a descendant div.Enb9pe, distinct from the desktop e8Ck0d node class), so it
     * resolves the mobile-only top-level feature name. Single-gate shape — no
     * _match child.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'visual_digest_mobile';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ((int)$useDbRules > 0) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; other DB modes use live rules.
            $matchRules = ((int)$useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['visual_digest_mobile'])
                : RuleLoaderService::getRulesForFeature('visual_digest_mobile');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($dom->getXpath()->query(".//div[contains(@class, 'Enb9pe')]", $node)->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Per-item extraction is left hardcoded (conservative Step 2 selection):
        // it keys off the semantic, rarely-changing data-attrid="VisualDigest" token.
        // This is the MOBILE extraction walk; it emits VISUAL_DIGEST_MOBILE results.
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

            $resultSet->addItem(new BaseResult(NaturalResultType::VISUAL_DIGEST_MOBILE , $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }
}
