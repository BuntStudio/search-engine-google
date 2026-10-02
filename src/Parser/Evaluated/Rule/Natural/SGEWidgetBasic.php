<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\BasicLayout;

/**
 * AI Overview card of Google's basic (no-JS) layout, see BasicLayout.
 *
 * The card ships a visible first paragraph (div.frRrnc) and a collapsed
 * remainder behind "Show more": an empty div#accdef_<id> that the page's own
 * jsl.dh('accdef_<id>', "<html>") script fills. That script holds the rest of
 * the answer and the citation strip (a.CGJcbf cards: title div + source div,
 * href="/url?q=<dest>"). SGEWidget's enrichment already fills any id'd element
 * from the jsl.dh payloads, so the parent extraction is reused whole; only
 * three things differ on this layout and are handled here:
 *
 *  - loaded-ness: there is no folsrch/aimc/bsmXxe marker, the answer text is
 *    the proof;
 *  - citation hrefs are /url?q= redirects, which getUrlFromGoogleTranslate
 *    (url= only) leaves relative and isGoogleInternalUrl then drops - they are
 *    unwrapped on the card before extraction, with the title div copied to
 *    aria-label so processLinkElements reads a clean title;
 *  - none of the citation selectors (hardcoded or DB) know these cards, so
 *    the unwrapped accdef anchors are collected after them.
 *
 * Not a registered parser rule: ClassicalResultBasic calls parse() directly
 * for the card it classified as AIO, keeping document order.
 */
class SGEWidgetBasic extends SGEWidget
{
    const CITATION_ANCHOR_XPATH = "descendant::*[starts-with(@id, 'accdef_')]//a[starts-with(@href, 'http')]";

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = self::MODE_HARDCODED)
    {
        // Dispatched by ClassicalResultBasic, never matched from the rule loop.
        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = self::MODE_HARDCODED, $additionalRule = null)
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) {
            return;
        }

        $localNode = clone $node;
        $data = $this->extractWidgetData($dom, $localNode, $useDbRules, $isMobile);
        $data[\Serps\SearchEngine\Google\NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['layout'] = 'basic';

        $resultSet->addItem(new BaseResult($this->getType($isMobile), $data, $localNode, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function isWidgetLoaded(GoogleDom $dom, $node, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        // extractWidgetData calls this right after the jsl.dh enrichment (the
        // citation anchors exist only from then on) and before SGE_WIDGET_BASE
        // is serialised: the one hook where the unwrap reaches both the stored
        // HTML and the link extraction.
        $this->unwrapRedirectAnchors($dom, $node);

        if (parent::isWidgetLoaded($dom, $node, $useDbRules, $isMobile)) {
            return true;
        }

        // Enrichment ran before this check: a filled accdef_* means the full
        // answer is in; the visible paragraph alone is still an answer.
        if ($dom->xpathQuery("descendant::*[starts-with(@id, 'accdef_')]/*", $node)->length > 0) {
            return true;
        }

        $answer = $dom->xpathQuery("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' frRrnc ')]", $node);
        foreach ($answer as $paragraph) {
            if (mb_strlen(trim($paragraph->textContent)) >= self::MIN_CONTENT_TEXT_LENGTH) {
                return true;
            }
        }

        return false;
    }

    protected function extractLinkElements($dom, $node, &$urls, &$data, $useDbRules = self::MODE_HARDCODED, $isMobile = false)
    {
        parent::extractLinkElements($dom, $node, $urls, $data, $useDbRules, $isMobile);

        $citations = $dom->xpathQuery(self::CITATION_ANCHOR_XPATH, $node);
        $data[\Serps\SearchEngine\Google\NaturalResultType::SGE_WIDGET_DIAGNOSTICS]['link_selectors_matched']['basic_accdef'] = $citations->length;
        if ($citations->length > 0) {
            $this->processLinkElements($dom, $citations, $urls, $data);
        }
    }

    /**
     * Rewrite every /url?q= (or url=) anchor of the card to its destination and
     * give citation cards an aria-label holding the title line only.
     */
    protected function unwrapRedirectAnchors($dom, $node)
    {
        foreach ($dom->xpathQuery("descendant::a[starts-with(@href, '/url?')]", $node) as $anchor) {
            $destination = BasicLayout::unwrapRedirect($anchor->getAttribute('href'));
            if ($destination === null) {
                continue;
            }
            $anchor->setAttribute('href', $destination);

            if ($anchor->getAttribute('aria-label') === '') {
                // Citation card: first leaf div is the title, second the source.
                $title = $dom->xpathQuery('descendant::div[not(descendant::div)][1]', $anchor);
                if ($title->length > 0 && trim($title->item(0)->textContent) !== '') {
                    $anchor->setAttribute('aria-label', trim($title->item(0)->textContent));
                }
            }
        }
    }
}
