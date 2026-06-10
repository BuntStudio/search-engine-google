<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class Maps implements ParsingRuleInterface
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
     * Get the feature name based on mobile flag.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'maps_mobile' : 'maps';
    }

    /**
     * Get the match feature name based on mobile flag.
     */
    protected static function getMatchFeatureName($isMobile)
    {
        return $isMobile ? 'maps_mobile_match' : 'maps_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // DB rules path — replace the hardcoded container checks (Odp5De / C7r6Ue / WVGKWb / Qq3Lb / VT5Tde).
        // Maps.php only handles the desktop class, but the parsable-node selection unions desktop +
        // mobile match rules upstream; here we consult both match features so a renamed container
        // still resolves. Candidate testing (mode 3) consults the heal candidate; mode 1 uses live rules.
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
        if ($node->getAttribute('id') == 'Odp5De' || $node->getAttribute('class') == 'C7r6Ue' || str_contains($node->getAttribute('class'),  'WVGKWb') || str_contains($node->getAttribute('class'),  'Qq3Lb')  || str_contains($node->getAttribute('class'),  'VT5Tde')) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        // DB rules path — the primary listing-title extraction (div[@class='rllt__details'])
        // lives in the 'maps'/'maps_mobile' parent feature. The legacy g-review-stars (version1)
        // layout and the positional href-sibling logic stay hardcoded as fallbacks.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $featureName = self::getFeatureName($isMobile);

            if ($useDbRules === self::MODE_CANDIDATE_TESTING) {
                // Maps has no parse children — resolve candidate rules by this feature only.
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

        // Hardcoded fallback (version1 / version2 / version3 chain)
        foreach ($this->steps as $functionName) {

            if ($resultSet->hasType(NaturalResultType::MAP)) {
                break 1;
            }

            try {
                call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
            } catch (\Exception $exception) {
                continue;
            }

        }
    }

    /**
     * Extract listing titles using DB rules (primary version2/version3 pattern).
     * Returns true when at least one listing was added to the result set.
     */
    protected function parseWithDbRules(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, array $rules)
    {
        try {
            $xpath = implode(' | ', $rules);
            $ratingStars = $dom->getXpath()->query($xpath, $node);
        } catch (\Exception $e) {
            Logger::error('Maps DB rule XPath failed', ['xpath' => implode(' | ', $rules), 'error' => $e->getMessage()]);
            return false;
        }

        if ($ratingStars->length == 0) {
            return false;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            if ($ratingStarNode->childNodes->length == 0) {
                continue;
            }

            $title = $ratingStarNode->childNodes->item(0)->textContent;
            if (empty($title) && !empty($ratingStarNode->parentNode->childNodes[1])) {
                $title = $ratingStarNode->parentNode->childNodes[1]->textContent;
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

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[@class='rllt__details']", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            if (empty($ratingStarNode->parentNode->childNodes[1])) {
                continue;
            }

            $spanElements[] = [
                'title' => $ratingStarNode->parentNode->childNodes[1]->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        if(!empty($spanElements)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[@class='rllt__details']", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            if($ratingStarNode->childNodes->length ==0) {
                continue;
            }

            $href = null;
            if ($ratingStarNode->parentNode->parentNode->parentNode->nextSibling !== null &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->hasAttribute('href') &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->hasAttribute('class') &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->getAttribute('class') === 'yYlJEf Q7PwXb L48Cpd brKmxb'
            ) {
                $href = $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->getAttribute('href');
            }

            $spanElements[] = [
                'title' => $ratingStarNode->childNodes->item(0)->textContent,
                'href' => $href,
            ];
        }

        if(!empty($spanElements)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
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
                'title' => $ratingStarNode->parentNode->parentNode->parentNode->childNodes[1]
                    ->childNodes[0]->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
