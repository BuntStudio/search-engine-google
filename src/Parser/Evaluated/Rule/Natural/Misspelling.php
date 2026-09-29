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

class Misspelling implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    const MODE_HARDCODED         = 0;
    const MODE_DATABASE          = 1;
    const MODE_CANDIDATE_TESTING = 3;

    protected static function getFeatureName()
    {
        return 'misspelling_match';
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        $useDbRules = (int) $useDbRules;
        // 869f8c5k4: the self-healing feature owns the gate; the seeded rules mirror the hardcoded
        // pair below (QRYxYe, then the guarded #oFNiHe fallback). No DB rules -> hardcoded.
        if ($useDbRules === self::MODE_DATABASE || $useDbRules === self::MODE_CANDIDATE_TESTING) {
            $matchRules = ($useDbRules === self::MODE_CANDIDATE_TESTING)
                ? RuleLoaderService::getCandidateMatchRulesForFeatures([self::getFeatureName()])
                : RuleLoaderService::getRulesForFeature(self::getFeatureName());

            if (!empty($matchRules)) {
                $matchResult = $dom->getXpath()->query(implode(' | ', $matchRules), $node);
                return $matchResult->length > 0 ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
            }
        }

        // 869f3z5qr: the block's own class is the gate - #oFNiHe is an empty stub on many
        // desktop pages. The id stays as a fallback for older stored SERPs, but only when it
        // does NOT wrap a QRYxYe element, or both gates fire and parse() runs twice.
        $class = $node->getAttribute('class');
        if ($class !== '' && preg_match('/(?:^|[ \t\r\n])QRYxYe(?:[ \t\r\n]|$)/S', $class)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') == 'oFNiHe'
            && $dom->getXpath()->query(
                "descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' QRYxYe ')]",
                $node
            )->length === 0
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        // The corrected-query anchor is the only one carrying spell=1 in either layout
        // ("Did you mean:" and "These are results for"); "Search instead for" carries
        // nfpr=1, so this never picks the original term back up. Verified 869f3z5qr.
        $correctedLink = $dom->getXpath()->query("descendant::a[contains(@href, 'spell=1')]", $node);

        if ($correctedLink->length > 0) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MISSPELLING, [
                $correctedLink->item(0)->textContent
            ]));
            return;
        }

        $mispellingNode = $dom->getXpath()->query("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' card-section KDCVqf ')]", $node);

        if ($mispellingNode->length > 0) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MISSPELLING, [
                $dom->getXpath()->query("descendant::a", $mispellingNode->item(0))->item(0)->textContent
            ]));
        } else {
            // Try to find by ID
            $mispellingNode = $dom->getXpath()->query("descendant::*[@id='fprs']", $node);

            if ($mispellingNode->length > 0) {
                // Specifically look for the anchor with ID "fprsl"
                $correctedTermLink = $dom->getXpath()->query("descendant::a[@id='fprsl']", $mispellingNode->item(0));

                if ($correctedTermLink->length > 0) {
                    $resultSet->addItem(new BaseResult(NaturalResultType::MISSPELLING, [
                        $correctedTermLink->item(0)->textContent
                    ]));
                }
            }
        }
    }
}
