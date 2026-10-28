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

class Hotels implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    use HydratesDeferredBlocks;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /** Set when the node's cards came from deferred payloads: one list per booking provider repeats hotels. */
    protected $dedupeNames = false;

    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Get the parent (extraction) feature name based on mobile flag.
     * Hotels.php only handles the desktop class; HotelsMobile.php is a separate class that
     * resolves 'hotels_mobile'. This always returns the desktop name.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'hotels_mobile' : 'hotels';
    }

    /**
     * Get the container match/gate feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'hotels_mobile_match' : 'hotels_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container checks (jsname='YWd0ec' / class='CH6Bmd'
        // / class='zaTIWc'). Hotels.php handles only the desktop class, but the parsable-node selection
        // unions desktop + mobile match rules upstream; here we consult both match features so a
        // renamed container still resolves. Candidate testing (mode 3) consults the heal candidate.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['hotels_match', 'hotels_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('hotels_match'),
                    RuleLoaderService::getRulesForFeature('hotels_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('jsname') == 'YWd0ec'
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('class') == 'CH6Bmd'
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('class') == 'zaTIWc'
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // The collapsed block ships its property cards as deferred jsl.dh() payloads (#3512).
        $this->dedupeNames = $this->hydrateDeferredBlocks($dom, $node) > 0;

        // DB rules path — the primary property-name extraction (descendant ' BTPx6e ' selector)
        // lives in the 'hotels' parent feature. Hotels has no parse children, so candidate rules
        // resolve by this feature only (§9.1 N/A).
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                $rules = RuleLoaderService::getCandidateRulesForFeature($additionalRule, $featureName);
            } else {
                $rules = RuleLoaderService::getRulesForFeature($featureName);
            }

            if (!empty($rules)) {
                if ($this->parseWithDbRules($dom, $node, $resultSet, $rules)) {
                    return;
                }
                // DB rules matched nothing — fall through to hardcoded below.
            }
            // No DB rules — fall through to hardcoded.
        }

        // Hardcoded fallback
        $hotels = $dom->getXpath()->query("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' BTPx6e')]", $node);
        $item = [];

        if($hotels->length> 0) {
            $item['hotels_names'] = $this->hotelNames($hotels);
            $resultSet->addItem(new BaseResult(NaturalResultType::HOTELS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }

    /**
     * Extract property names using DB rules (primary BTPx6e pattern).
     * Returns true when at least one property name was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $hotels = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('Hotels DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($hotels->length == 0) {
            return false;
        }

        $item = ['hotels_names' => $this->hotelNames($hotels)];

        if (!empty($item['hotels_names'])) {
            $resultSet->addItem(new BaseResult(NaturalResultType::HOTELS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            return true;
        }

        return false;
    }

    /**
     * Property names in page order. A hydrated pack holds one shortlist per booking provider, so the
     * same hotel repeats (#3512); there only its first occurrence is kept.
     *
     * @param \Traversable $nodes
     * @return array
     */
    protected function hotelNames($nodes)
    {
        $names = [];
        $seen = [];
        foreach ($nodes as $node) {
            $name = $node->nodeValue;
            if ($this->dedupeNames) {
                $key = trim($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
            }
            $names[] = ['name' => $name];
        }

        return $names;
    }
}
