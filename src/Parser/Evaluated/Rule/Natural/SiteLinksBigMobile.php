<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class SiteLinksBigMobile implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    /**
     * Mobile sitelink cell. `MUxGbd v0nnCb lyLwlc` is the pre-2026 form; Google now renders the cell
     * as `Va3FIb EE3Upf lVm3ye` (the leading `DkX4ue` of the old quad is gone). Token-safe contains()
     * so a further class drift can't break the gate the way the exact-equality form did.
     */
    const SITELINK_CELL_XPATH = "descendant::div["
        . "(contains(concat(' ', normalize-space(@class), ' '), ' MUxGbd ')"
        . " and contains(concat(' ', normalize-space(@class), ' '), ' v0nnCb ')"
        . " and contains(concat(' ', normalize-space(@class), ' '), ' lyLwlc '))"
        . " or (contains(concat(' ', normalize-space(@class), ' '), ' Va3FIb ')"
        . " and contains(concat(' ', normalize-space(@class), ' '), ' EE3Upf ')"
        . " and contains(concat(' ', normalize-space(@class), ' '), ' lVm3ye '))]";

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        return self::RULE_MATCH_MATCHED;
    }

    /**
     * @param string|null $cellXpath Cell selector resolved by the caller (live DB rules or a heal candidate);
     *                               null uses the hardcoded SITELINK_CELL_XPATH.
     */
    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $cellXpath = null)
    {
        $siteLinksNodes = $dom->xpathQuery($cellXpath ?: self::SITELINK_CELL_XPATH, $node);

        if ($siteLinksNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($siteLinksNodes as $siteLinksNode) {
            $aNode   = $dom->xpathQuery("descendant::a", $siteLinksNode)->item(0);

            if ($aNode === null) {
                continue;
            }

            $items[] = ['title' => $aNode->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($aNode->getAttribute('href'))];
        }

        $resultSet->addItem(
            new BaseResult(NaturalResultType::SITE_LINKS_BIG_MOBILE, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }
}
