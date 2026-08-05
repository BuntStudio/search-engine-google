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

        // Hardcoded fallback (version1 / version2 / version3 chain).
        // Mirror the desktop "first MAP wins" intent, but scoped to THIS node only: once a step
        // adds a MAP item, stop. Otherwise version3's broad span[@role='heading'] also scoops up
        // the "Centrul meu de anunțuri" / "My Ad Center" (aria-level=1) ads-disclosure overlay that
        // Google injects into the mobile local pack, over-counting maps_links vs the DB path
        // (mode-2 parity, site 104785 'casa de expeditii transport' 2026-06-29: hardcoded=5, DB=4).
        // Per-node scope (NOT the global hasType(MAP) guard desktop Maps uses) keeps multi-container
        // mobile consistent with the DB path, which sums every container.
        $beforeCount = $resultSet->count();
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
            if ($resultSet->count() > $beforeCount) {
                break;
            }
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
            // Mobile rllt__details layout: the business name sits two ELEMENT levels down
            // (mirrors version2). Element-aware — see firstElementChild().
            $title = $this->extractCardTitle($ratingStarNode);
            // Fallback: the matched node's own text content. This exists for the HEADING-shaped
            // rule (410, span[@role='heading' and @aria-level='3']), whose text content IS the
            // business name. It must NOT be applied to the CARD-shaped rule (398,
            // div[@class~='rllt__details']): a listing card's text content is the whole card
            // (reviews + category + service options), never just the name. Google sometimes serves
            // a card whose name span is empty (<div class="tNxQIb JIFdL lrl-obh"><span></span></div>),
            // and the unrestricted fallback turned that into a fabricated title such as
            // "Nicio recenzieMagazin de articole electriceCumparaturi in magazin·..." — a mode-2
            // value mismatch against hardcoded version2's '' (site 299282
            // 'magazin electrice constanta' Mobile 2026-08-05: hardcoded=6, DB=6, 3 titles differing).
            if (($title === '' || $title === null) && $ratingStarNode->nodeName === 'span') {
                $title = trim($ratingStarNode->textContent);
            }
            if ($title === '' || trim($title) === '') {
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
        // Restricted to aria-level=3 to mirror DB rule 410 exactly. An unrestricted
        // span[@role='heading'] also selects the "My Ad Centre" / "Centrul meu de anunțuri"
        // (aria-level=1) ads-disclosure overlay Google injects into the mobile local pack, and the
        // pack's structural heading spans (aria-level=2). The 2026-06-29 per-node break only
        // suppresses that when an EARLIER step succeeds — on a pack with no g-review-stars and no
        // rllt__details (version1 and version2 both select 0) the chain falls through to version3
        // and the overlay leaks in as two extra listings (mode-2 parity, site 306047
        // 'weekend craft outdoor furniture' Mobile 2026-08-04: hardcoded=10, DB=8).
        $ratingStars = $googleDOM->getXpath()->query("./descendant::span[@role='heading' and @aria-level='3']/text()", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            // `span[@role='heading']` is NOT restricted to aria-level=3 here, so this step also
            // selects the local pack's structural heading spans (aria-level=2), whose text nodes are
            // whitespace-only. Those produced junk maps_links entries (title "\n", url null) that the
            // DB path never emits — it filters to aria-level=3 (rule 410) AND skips empty titles in
            // parseWithDbRules(). The junk inflated getMapsBaseline()'s total_results and, worse,
            // occupied the leading top_5_results slots (mode-2 parity, site 340663
            // 'gourmet chutney pack' Mobile 2026-07-30: hardcoded=8, DB=6).
            $title = $ratingStarNode->textContent;
            if (trim($title) === '') {
                continue;
            }

            $spanElements[] = [
                'title' => $title,
                'href' => null, // TODO: find the href
            ];
        }

        // Mirror parseWithDbRules(): no listings extracted means no MAP item at all, rather than an
        // empty one that would still flag the local pack as present.
        if (empty($spanElements)) {
            return;
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    /**
     * First ELEMENT child of a node, skipping text/comment nodes.
     *
     * `firstChild` is whitespace-sensitive: whether it returns the name <div> or a "\n" text node
     * depends on whether that markup carries inter-element whitespace. Most does not, which is why
     * the old `firstChild->firstChild` walk worked for years — but where it DOES, the walk lands on
     * a text node and yields NULL plus a "Trying to get property 'textContent' of non-object"
     * notice for every listing in that pack, while the DB path's fallback degraded to the whole
     * card's text (name + rating + category + address + hours). The names are in the DOM throughout.
     *
     * The whitespace varies **per container, within a single document** — site 334290
     * 'unde sa mananci in lugano' Mobile 2026-08-04 has four identical
     * `xxAJT eDSE7e Ww4FFb vt6azd` containers where the first two (6 cards each) are whitespace-
     * separated and broken while the last two (3 cards each) are not: hardcoded returned 12 NULLs
     * followed by 6 correct names. So this is a property of the individual markup block, NOT of the
     * parse source and NOT of the render as a whole — never assume a sibling container is safe.
     * Also seen on site 341587 'aile şirketi iletişimi' Mobile 2026-08-04 (3 of 3 cards).
     *
     * @param \DOMNode|null $node
     * @return \DOMNode|null
     */
    private function firstElementChild($node)
    {
        if (!$node || !$node->childNodes) {
            return null;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Business name for an rllt__details listing card: two ELEMENT levels down
     * (div.rllt__details > div.tNxQIb > span). Returns '' when the name is genuinely absent, which
     * callers treat as "skip this listing" rather than falling back to the card's full text
     * (reviews + category + address + hours) — that fabricated a plausible-looking title on
     * site 299282, 2026-08-05.
     *
     * @param \DOMNode $cardNode
     * @return string
     */
    private function extractCardTitle($cardNode)
    {
        $nameWrapper = $this->firstElementChild($cardNode);
        $nameNode    = $this->firstElementChild($nameWrapper);

        if (!$nameNode) {
            return '';
        }

        return (string) $nameNode->textContent;
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' rllt__details')]", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            // Google sometimes serves a listing card whose name span is empty
            // (<div class="tNxQIb JIFdL lrl-obh"><span></span></div>) — the name is never in the
            // HTML for it. The unguarded walk turned those into ['title' => '', 'url' => null]
            // listings, which inflate getMapsBaseline()'s total_results and occupy top_5_results
            // slots with blanks. Same guard version3 already carries (2026-07-30).
            $title = $this->extractCardTitle($ratingStarNode);
            if (trim($title) === '') {
                continue;
            }

            $spanElements[] = [
                'title' => $title,
                'href' => null, // TODO: find the href
            ];
        }

        // Mirror parseWithDbRules()/version3(): no listings extracted means no MAP item at all,
        // rather than an empty one that would still flag the local pack as present.
        if (empty($spanElements)) {
            return;
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
