<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Media\MediaFactory;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class HotelsMobile implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
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
     * Separate mobile class — always resolves to the mobile feature names.
     */
    protected static function getFeatureName($isMobile)
    {
        return 'hotels_mobile';
    }

    protected static function getMatchFeatureName($isMobile)
    {
        return 'hotels_mobile_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container gate (class 'hNKF2b' / jscontroller
        // 'dGwZHb' / jscontroller 'U6XW6'). The seeded rules are the bare self:: container
        // predicates; the descendant guards (guest-picker / hotel-link) stay hardcoded as a
        // fallback safety net below. Both mobile + desktop match features are consulted so a
        // renamed container still resolves; candidate testing (mode 3) consults the heal candidate.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['hotels_mobile_match', 'hotels_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('hotels_mobile_match'),
                    RuleLoaderService::getRulesForFeature('hotels_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        // Fast path: known hotels class
        if (str_contains($node->getAttribute('class'), 'hNKF2b')) {
            return self::RULE_MATCH_MATCHED;
        }

        // Defensive validation: dGwZHb container must have guest picker (lz6svf) as descendant
        if ($node->getAttribute('jscontroller') === 'dGwZHb') {
            $xpath = $dom->getXpath();
            $hasGuestPicker = $xpath->query("descendant::*[@jscontroller='lz6svf']", $node);

            if ($hasGuestPicker->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        // Defensive validation: U6XW6 container must have a /travel/hotels/ link as descendant
        if ($node->getAttribute('jscontroller') === 'U6XW6') {
            $xpath = $dom->getXpath();
            $hasHotelLink = $xpath->query("descendant::a[starts-with(@href, '/travel/hotels/')]", $node);

            if ($hasHotelLink->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary mobile property-name extraction (HjGrCb heading | BTPx6e
        // selector) lives in the 'hotels_mobile' parent feature. No parse children, so candidate
        // rules resolve by this feature only (§9.1 N/A).
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
                // DB rules matched nothing — fall through to hardcoded below.
            }
            // No DB rules (or candidate not ours) — fall through to hardcoded.
        }

        // Hardcoded fallback
        $xpath = $dom->getXpath();

        // We combine your original class 'BTPx6e' with the new class 'HjGrCb' found in your HTML files.
        // To avoid generic design classes and language issues, we specifically target
        // elements that are marked as 'heading' level 3, which Google uses for hotel titles.
        $hotels = $xpath->query(
            "descendant-or-self::*[
            (
                (
                    contains(concat(' ', normalize-space(@class), ' '), ' HjGrCb ') and (@role='heading' or @aria-level='3')
                ) or
                contains(concat(' ', normalize-space(@class), ' '), ' BTPx6e ')
            )]",
            $node
        );

        $this->addHotelsResult($hotels, $node, $resultSet);
    }

    /**
     * Extract mobile property names using DB rules, mirroring the mobile hardcoded walk
     * (trimmed nodeValue, de-duplicated). Returns true when at least one name was added.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $hotels = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('HotelsMobile DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($hotels->length == 0) {
            return false;
        }

        return $this->addHotelsResult($hotels, $node, $resultSet);
    }

    /**
     * Shared mobile result builder: trim + de-dup property names, then add a single BaseResult.
     * Returns true when at least one name was added.
     */
    protected function addHotelsResult($hotels, \DomElement $node, IndexedResultSet $resultSet)
    {
        $item = [];
        $uniqueNames = [];

        if($hotels->length> 0) {
            foreach ($hotels as $urlNode) {
                try {
                    $name = trim($urlNode->nodeValue);

                    // Filter out empty strings or duplicate names (Google sometimes repeats names in the map/list)
                    if ($name !== '' && !in_array($name, $uniqueNames)) {
                        $item['hotels_names'][] = ['name' => $name];
                        $uniqueNames[] = $name;
                    }
                } catch (\Exception $e) {
                    // Fail silently for individual items
                }
            }

            if (!empty($item['hotels_names'])) {
                $resultSet->addItem(new BaseResult(
                    NaturalResultType::HOTELS_MOBILE,
                    $item,
                    $node,
                    $this->hasSerpFeaturePosition,
                    $this->hasSideSerpFeaturePosition
                ));
                return true;
            }
        }

        return false;
    }
}
