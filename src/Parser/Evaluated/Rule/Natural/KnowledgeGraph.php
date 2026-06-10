<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use SM\Backend\SerpParser\RuleLoaderService;

class KnowledgeGraph implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    /**
     * Parser mode constants for self-healing parser integration.
     */
    const MODE_HARDCODED = 0;
    const MODE_DATABASE = 1;
    const MODE_COMPARISON = 2;
    const MODE_CANDIDATE_TESTING = 3;

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = true;

    /**
     * Get the feature name based on the mobile flag.
     * KnowledgeGraphMobile extends this class and runs the same match()/parse() with $isMobile=true.
     */
    protected static function getFeatureName($isMobile)
    {
        return $isMobile ? 'knowledge_graph_mobile' : 'knowledge_graph';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            // Candidate testing (mode 3) consults the heal candidate so a renamed
            // container can validate; mode 1 uses live rules as before.
            // We query both the desktop and mobile match features because match() does not
            // know $isMobile, and KnowledgeGraphMobile reuses this exact method.
            //
            // NOTE on axis: the parsable context node here is the SERP container that wraps the
            // panel (#rhs on desktop, an osrp-blk container on mobile — see NaturalParser /
            // MobileNaturalParser::getParsableItems), NOT the panel element itself. The live
            // hardcoded path uses cssQuery('.kp-wholepage-osrp', $node), which is descendant-scoped
            // relative to that container, so the seeded DB rule is the descendant-or-self::-form of
            // the same selector. Do not "correct" it to plain self:: — it would never match.
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures(['knowledge_graph_match', 'knowledge_graph_mobile_match'])
                : array_unique(array_merge(
                    RuleLoaderService::getRulesForFeature('knowledge_graph_match'),
                    RuleLoaderService::getRulesForFeature('knowledge_graph_mobile_match')
                ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded.
        }

        if (
            $dom->cssQuery('.kp-wholepage-osrp', $node)->length == 1
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        // Check for data-kpid attribute starting with "vise:"
        // Left hardcoded: this is a conditional/exclusion branch (vise: prefix AND absence of the
        // finance-summary panel) — too tied to flow control to heal in isolation.
        $dataKpid = $node->getAttribute('data-kpid');
        if (
            $dataKpid &&
            str_starts_with($dataKpid, 'vise:') &&
            ($dom->getXpath()->query("//div[@id='knowledge-finance-wholepage__entity-summary']"))->length == 0
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::KNOWLEDGE_GRAPH_MOBILE : NaturalResultType::KNOWLEDGE_GRAPH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        $data = [];

        // Primary official-site link extraction — DB-backed (self-healing) with hardcoded fallback.
        $primaryLink = $this->extractPrimaryLink($dom, $node, $useDbRules, $isMobile, $additionalRule);
        $links = $primaryLink['nodeList'];
        if ($primaryLink['href'] !== null) {
            $data['link'] = $primaryLink['href'];
        }

        // Link fallback chain — left hardcoded (fallback relationships are the logic, §3 "what NOT to
        // integrate"). Each only fires when the prior selector found nothing.
        if ($links->length == 0) {
            $links = $dom->cssQuery("a[class='n1obkb mI8Pwc'], a[class='P6Deab']", $node);
            if ($links->length > 0){
                $data['link'] = $links->item(0)->getAttribute('href');
            }
        }

        if ($links->length == 0) {
            $links = $dom->cssQuery("a[class='sXtWJb']", $node);
            if ($links->length > 0){
                $data['link'] = $links->item(0)->getAttribute('href');
            }
        }
        if ($links->length == 0) {
            $links = $dom->cssQuery("a[class='ab_button']", $node);
            if ($links->length > 0){
                $data['link'] = $links->item(0)->getAttribute('href');
            }
        }
        /** @var \DomElement $titleNode */
        $titleNode = $dom->cssQuery("div[data-attrid='subtitle']", $node)->item(0);

        if ($titleNode instanceof \DomElement) {
            $data['title'] = $titleNode->textContent;
            $subtitle = $dom->cssQuery("*[class='E5BaQ']", $titleNode);
            if ($subtitle->length >0) {
                $data['title'] =  $subtitle->item(0)->textContent;
            }

        } else {
            $titleNode = $dom->getXpath()->query("descendant::h2[contains(concat(' ', normalize-space(@class), ' '), ' kno-ecr-pt ')]", $node);

            if($titleNode->length >0) {
                $data['title'] = $titleNode->item(0)->firstChild->textContent;
            } else {
                $titleNode = $dom->cssQuery("h2[data-attrid='title']", $node)->item(0);
                if ($titleNode instanceof \DomElement) {
                    $data['title'] = $titleNode->textContent;
                }
            }
        }

        // Has no definition -> take "general presentation" text
        if(empty($data)) {
            $data['title']= $this->detectGeneralPresentationText($dom, $node);
        }

        $inResults = $dom->getXpath()->query("ancestor-or-self::div[contains(concat(' ', normalize-space(@class), ' '), ' MjjYud ')]", $node);

        if ($isMobile || $inResults->length) {
            $this->hasSideSerpFeaturePosition = false;
        }

        $resultSet->addItem(new BaseResult($this->getType($isMobile), $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    /**
     * Extract the primary official-site link from the knowledge panel.
     *
     * Returns ['nodeList' => DomNodeList, 'href' => string|null] where nodeList is the matched
     * element set (so the caller's `$links->length` fallback chain keeps working unchanged) and href
     * is the first match's href (or null when nothing matched).
     *
     * DB-backed (self-healing) with the hardcoded `a[data-attrid='visit_official_site']` selector as
     * fallback. This is the single primary extraction rule integrated for this feature.
     */
    protected function extractPrimaryLink($dom, $node, $useDbRules = self::MODE_HARDCODED, $isMobile = false, $additionalRule = null)
    {
        $featureName = self::getFeatureName($isMobile);
        $linkFeature = $featureName . '_primary_link';

        $rules = [];
        if ($useDbRules === self::MODE_DATABASE) {
            $rules = RuleLoaderService::getRulesForFeature($linkFeature);
        } elseif ($useDbRules === self::MODE_CANDIDATE_TESTING) {
            // A heal candidate for the primary-link child does not carry the parent feature id, so
            // resolve it by the child feature name (parse-family resolution, §9.1). If the candidate
            // isn't ours, fall back to the live DB rule, then to hardcoded.
            if ($additionalRule !== null && is_array($additionalRule)) {
                $rules = RuleLoaderService::getRulesByIdsForFeature($additionalRule, $linkFeature);
            }
            if (empty($rules)) {
                $rules = RuleLoaderService::getRulesForFeature($linkFeature);
            }
        }

        if (!empty($rules)) {
            $xpath = implode(' | ', $rules);
            $found = $dom->getXpath()->query($xpath, $node);
            if ($found->length > 0) {
                return ['nodeList' => $found, 'href' => $found->item(0)->getAttribute('href')];
            }
            // DB rule matched nothing — fall through to hardcoded so extraction degrades gracefully.
        }

        // Hardcoded fallback (always kept as safety net).
        $found = $dom->cssQuery("a[data-attrid='visit_official_site']", $node);
        $href = $found->length > 0 ? $found->item(0)->getAttribute('href') : null;

        return ['nodeList' => $found, 'href' => $href];
    }

    protected function detectGeneralPresentationText(GoogleDom $googleDOM, \DomElement $group)
    {
        $aHrefs = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' KYeOtb ')]", $group);

        if ($aHrefs->length > 0) {
            return $aHrefs->item(0)->textContent;
        }

        $title = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' BkwXh ')]", $group);

        if ($title->length > 0) {
            return $title->item(0)->textContent;
        }

        return '';
    }
}
