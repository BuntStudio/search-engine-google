<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Media\MediaFactory;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

/**
 * This rule extracts video groups as present on desktop results (the "videos" widget).
 *
 * Self-healing parser integration (2026-06-18): the container-detection (videos_match) and the
 * primary video-link extraction (videos parent) are mirrored into DB rules, with the hardcoded
 * selectors kept as a fallback. The separate VideoCarousel.php widget is NOT part of this class.
 */
class Videos implements ParsingRuleInterface
{

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
     * Get the feature name based on mobile flag.
     * The desktop class is only reached for desktop, but resolve via the flag for symmetry.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'videos_mobile' : 'videos';
    }

    /**
     * Get the match feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'videos_mobile_match' : 'videos_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded e4xoPb container check. The parsable-node
        // selection unions desktop + mobile match rules upstream; here we consult both match
        // features so a renamed container still resolves. Candidate testing (mode 3) consults the
        // heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['videos_match', 'videos_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('videos_match'),
                    RuleLoaderService::getRulesForFeature('videos_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net). Mirrors the videos_match DB tokens —
        // e4xoPb (legacy) + vtSz8d (current carousel container) + a <video-voyager> child (legacy
        // custom element) — folded in from the former standalone VideoCarousel rule (no longer
        // registered; it detected independently of the DB rules and masked their staleness). Because
        // this fallback now keys on the SAME tokens as the DB videos_match rule, a real Google rename
        // breaks BOTH together => detection drops to 0 => the self-healer sees it.
        if ($node->hasClass('e4xoPb') || $node->hasClass('vtSz8d')) {
            return self::RULE_MATCH_MATCHED;
        }
        if ($dom->getXpath()->query('child::video-voyager', $node)->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary video-link extraction (descendant::a[@class="X5OiLe"])
        // lives in the 'videos'/'videos_mobile' parent feature. Videos has no parse children, so
        // candidate rules resolve by this feature only.
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
                // DB rules matched nothing — fall through to hardcoded.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded.
        }

        // Hardcoded fallback — mirrors the videos DB extraction tokens (X5OiLe legacy + rIRoqf current),
        // folded in from the former VideoCarousel::parse(). Same tokens as the DB rule, so the two break
        // together on a real Google rename.
        $aHrefs = $dom->getXpath()->query(
            'descendant::a[contains(concat(" ", normalize-space(@class), " "), " X5OiLe ")'
            . ' or contains(concat(" ", normalize-space(@class), " "), " rIRoqf ")]',
            $node
        );

        if ($aHrefs->length == 0) {
            return;
        }

        $items = [];

        foreach ($aHrefs as $url) {
            $href = $url->getAttribute('href');
            if (trim($href) === '') {
                continue;
            }
            $items[] = [
                'url'    => \SM_Rank_Service::getUrlFromGoogleTranslate($href),
                'height' => '',
            ];
        }

        if (empty($items)) {
            return;
        }

        // See parseWithDbRules(): emit VIDEO_CAROUSEL so TranslateService keeps serpf_videos at 1
        // (carousel = one occurrence). Position normalises to 'videos' either way.
        $resultSet->addItem(new BaseResult(NaturalResultType::VIDEO_CAROUSEL, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    /**
     * Extract video-link anchors using DB rules (primary extractor).
     * Returns true when at least one video item was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $aHrefs = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('Videos DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($aHrefs->length == 0) {
            return false;
        }

        $items = [];

        foreach ($aHrefs as $url) {
            $href = $url->getAttribute('href');
            if (trim($href) === '') {
                continue;
            }
            $items[] = [
                'url'    => \SM_Rank_Service::getUrlFromGoogleTranslate($href),
                'height' => '',
            ];
        }

        if (empty($items)) {
            return false;
        }

        // Emit VIDEO_CAROUSEL (not VIDEOS): TranslateService folds a VIDEO_CAROUSEL into $page['videos']
        // taking only the FIRST item, so a carousel counts as ONE occurrence (serpf_videos stays 1) —
        // preserving the long-standing stored-count semantics while this DB-gated rule replaces the
        // former always-on VideoCarousel detector. Position normalises to 'videos' either way.
        $resultSet->addItem(new BaseResult(NaturalResultType::VIDEO_CAROUSEL, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));

        return true;
    }
}
