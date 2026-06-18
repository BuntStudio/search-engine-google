<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class PlacesSites implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    public $hasSerpFeaturePosition = true;

    /**
     * Parser mode constants for self-healing parser integration
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    /**
     * Get the feature name based on mobile flag.
     * Places Sites is desktop-only (no mobile PlacesSites parser class), but keep the
     * signature consistent with the other integrated rule classes.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'places_sites_mobile' : 'places_sites';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container check (class 'XNfAUb').
        // Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['places_sites_match'])
                : RuleLoaderService::getRulesForFeature('places_sites_match');

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                // The 'mgAbYb' / 'Places sites' label-text gate stays hardcoded as an extra
                // confirmation guard (left hardcoded — conditional flow control, §3).
                if ($matchResult->length > 0 && $this->hasPlacesSitesLabel($dom)) {
                    return self::RULE_MATCH_MATCHED;
                }
                return self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        // Hardcoded fallback (always kept as safety net)
        if ($node->getAttribute('class') == 'XNfAUb') {
            if ($this->hasPlacesSitesLabel($dom)) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }

    /**
     * Hardcoded "Places sites" label-text gate (left hardcoded — page-absolute conditional
     * flow control, not a detection/extraction rule per §3).
     */
    protected function hasPlacesSitesLabel(GoogleDom $dom)
    {
        $placesText = $dom->getXpath()->evaluate('string(//*[contains(@class, "mgAbYb")])');
        return (!empty($placesText) && $placesText == 'Places sites');
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary link extraction (ddkIM anchors) lives in the
        // 'places_sites' parent feature.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // Places Sites has no parse children — resolve candidate rules by this feature only.
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
        //todo is we have already added places
        $placesSitesNodes = $dom->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' ddkIM ')]", $node);
        if ($placesSitesNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $placesSitesNodes->length; $i++) {
                if (!empty($placesSitesNodes->item($i))){
                    $item = $placesSitesNodes->item($i);
                    $parent = $item->parentNode;
                    $nameNodes = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' VaiWld ')]", $parent);
                    if ($nameNodes->length > 0) {
                        $name = trim($nameNodes->item(0)->textContent);
                    } else {
                        $name = trim($parent->textContent);
                    }
                    $items[] = ['name' => $name, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($item->getAttribute('href'))];
                }
            }

            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::PLACES_SITES, $items, $node, $this->hasSerpFeaturePosition));
            }
        }

    }

    /**
     * Extract Places Sites listing links using DB rules (primary ddkIM anchor pattern).
     * Mirrors the hardcoded extraction: each matched anchor yields a ['name' => ..., 'url' => ...]
     * entry, with name resolved from the sibling 'VaiWld' div (left hardcoded — secondary detail)
     * and url cleaned via getUrlFromGoogleTranslate. Returns true when at least one listing was added.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $placesSitesNodes = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('PlacesSites DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($placesSitesNodes->length == 0) {
            return false;
        }

        $items = [];
        for ($i = 0; $i < $placesSitesNodes->length; $i++) {
            if (!empty($placesSitesNodes->item($i))) {
                $item = $placesSitesNodes->item($i);
                $parent = $item->parentNode;
                $nameNodes = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' VaiWld ')]", $parent);
                if ($nameNodes->length > 0) {
                    $name = trim($nameNodes->item(0)->textContent);
                } else {
                    $name = trim($parent->textContent);
                }
                $items[] = ['name' => $name, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($item->getAttribute('href'))];
            }
        }

        if (count($items)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::PLACES_SITES, $items, $node, $this->hasSerpFeaturePosition));
            return true;
        }

        return false;
    }
}
