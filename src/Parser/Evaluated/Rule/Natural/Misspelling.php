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

class Misspelling implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
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
