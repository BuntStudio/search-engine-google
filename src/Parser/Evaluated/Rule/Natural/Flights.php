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

class Flights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    private $isNewFlight = false;

    /**
     * Parser mode constants for self-healing parser integration.
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB-driven detection. Match rules run with the candidate element as the
        // context node, so they are stored in self:: axis form (§9.2). Candidate
        // testing (mode 3) consults the heal candidate so a renamed container can
        // validate; mode 1 uses the live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['flights_match', 'flights_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('flights_match'),
                    RuleLoaderService::getRulesForFeature('flights_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                // The isNewFlight extraction branch is hardcoded flow control and is
                // left disabled under DB detection — the integrated primary extraction
                // is the descendant::a path.
                $this->isNewFlight = false;
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded detection.
        }

        // Hardcoded fallback detection (always kept as a safety net).
        $class = $node->getAttribute('class');
        if (!empty($class) && strpos($class, 'LQQ1Bd') !== false && $node->getChildren()->count() != 0) {
            return self::RULE_MATCH_MATCHED;
        }

        if (!empty($class) && strpos($class, 'BNeawe DwrKqd') !== false) {
            $this->isNewFlight = true;
            return self::RULE_MATCH_MATCHED;
        }

        if (!empty($class) && strpos($class, 'kp-wholepage') !== false && $dom->xpathQuery(".//div[@id='kp-wp-tab-cont-AIRFARES']", $node)->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        // New Google flights-prices widget (jscontroller='es75Cc' / class 'mA0j1c'). Hardcoded had no
        // branch for it, so the legacy path under-detected flights on flight-ticket queries while the
        // DB rule (flights_match #552) detected it — mode-2 parity, site 336052
        // 'ankara singapur uçak bileti'. Mirrors rule 552 so hardcoded and DB agree.
        if ($node->getAttribute('jscontroller') === 'es75Cc'
            || (!empty($class) && strpos($class, 'mA0j1c') !== false)) {
            return self::RULE_MATCH_MATCHED;
        }

        /*        if ($node->getAttribute('class') == 'IuoSj') {
                    return self::RULE_MATCH_MATCHED;
                }*/

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // Flights EXTRACTION is intentionally NOT part of the self-healing DB-rule system: the only
        // DB rule it ever had was the generic `descendant::a` plain-link extractor, which carries no
        // healable structure. Extraction is therefore always hardcoded. Container DETECTION still
        // runs through the DB-driven `flights_match` / `flights_mobile_match` gate in match() above,
        // which IS self-healing / disaster-testable. (Plain-link feature removed 2026-06-17 — see
        // migrations/serp_parser_remove_flights_plainlink_feature_2026-06-17.sql.)
        $item = $this->parseHardcoded($dom, $node);

        if ($item === false) {
            return false;
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    /**
     * Original hardcoded extraction (covers both the isNewFlight tbody path and the
     * default descendant::a path, including the accordion / bCOlv exclusions).
     * Returns the item array, or false when the node should be skipped.
     *
     * @return array|false
     */
    protected function parseHardcoded(GoogleDom $dom, \DomElement $node)
    {
        $item = [];

        if ($this->isNewFlight) {
            $urls = $dom->getXpath()->query('ancestor::tbody/descendant::a', $node->firstChild);

            if ($urls->length > 0) {
                foreach ($urls as $urlNode) {
                    $item['flights_names'][] = ['name' => $urlNode->firstChild->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($urlNode->getAttribute('href'))];
                }
            }
        } else {
            if ($dom->xpathQuery("ancestor::g-accordion-expander", $node)->length > 0) {
                return false;
            }

            //bCOlv - this is a kowledge used in things to know/people also ask. these are not flights results
            if (
                $dom->xpathQuery("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' bCOlv ')]", $node)->length > 0
            ) {
                return false;
            }

            $urls = $dom->getXpath()->query('descendant::a', $node->firstChild);

            if ($urls->length > 0) {
                foreach ($urls as $urlNode) {
                    $item['flights_names'][] = ['name' => $urlNode->firstChild->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($urlNode->getAttribute('href'))];
                }
            }
        }

        return $item;
    }

}
