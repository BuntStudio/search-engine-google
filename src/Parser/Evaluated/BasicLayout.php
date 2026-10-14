<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated;

use Serps\SearchEngine\Google\Page\GoogleDom;

/**
 * Google's basic (no-JS) SERP layout.
 *
 * Served to clients Google treats as JS-less (the page carries the
 * "/httpservice/retry/enablejs" notice). It has none of the JS layout's anchors:
 * no #rso, #center_col, #botstuff, MjjYud or schema.org/SearchResultsPage, and
 * <body> carries no srp class. Every block is a card directly under div#main:
 *
 *   div#main
 *     div.Gx5Zad                       <- one card per block
 *       div.sHTlR                      <- header
 *         a[href="/url?q=<dest>&sa=U&ved=…&usg=…"]
 *           h3                         <- title
 *           div                        <- breadcrumb ("www.site.ro › …")
 *       div                            <- body (snippet, "În stoc" badge, date,
 *                                         inline sitelinks)
 *
 * AI Overview, Places ("/searchviewer/" links), "People also search for" and the
 * footer are cards too, but none of them has an h3 inside a /url? link, so the
 * organic fingerprint is a #main with no #rso inside it and at least one h3
 * inside a /url? anchor. 360 JS-layout fixtures also carry id="main" (wrapping
 * their #rso) and none has such an anchor, so #main on its own proves nothing.
 *
 * Reference pages (Oxylabs, 2026-10-01): ai-cto docs/oxylabs-no-rso/.
 */
class BasicLayout
{
    /**
     * Title anchors of organic cards, relative to div#main.
     */
    const TITLE_ANCHOR_XPATH = "descendant::a[starts-with(@href, '/url?')][descendant::h3]";

    /**
     * The organic card wrapper. Only a hint: when Google renames it the parent of
     * the header (anchor -> header -> card) is used instead, see cardOf().
     */
    const CARD_XPATH = "ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' Gx5Zad ')][1]";

    /**
     * Every outermost card of a container, in document order. The footer is a
     * Gx5Zad too and is skipped by its tag.
     */
    const CARDS_XPATH = "descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' Gx5Zad ')][not(ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' Gx5Zad ')])][not(ancestor::footer)]";

    // Card kinds, see classifyCard(). Markers are structural where Google
    // offers one; the AIO card has none, its ids/classes were identical on the
    // UK and RO samples (NwHcK = the "can't generate" notice, frRrnc = the
    // answer, accdef_* = the collapsed remainder filled by jsl.dh).
    const CARD_ORGANIC = 'organic';
    const CARD_AIO = 'aio';
    const CARD_PLACES = 'places';
    const CARD_RELATED = 'related';
    const CARD_OTHER = 'other';

    const AIO_CARD_TEST = "descendant::*[@id='NwHcK' or contains(concat(' ', normalize-space(@class), ' '), ' frRrnc ') or starts-with(@id, 'accdef_')]";
    // Local listings: /searchviewer/ links each holding an h3 (the business name).
    const PLACES_LISTING_XPATH = "descendant::a[starts-with(@href, '/searchviewer/')][descendant::h3]";
    // Query pills of the "People also search for" card: plain /search? links,
    // no h3. The hidden search-tools card (#st-card: "Past hour", "Verbatim",
    // language filter) is /search? links too, all carrying source=lnt.
    const RELATED_QUERY_XPATH = "descendant::a[starts-with(@href, '/search?')][not(contains(@href, 'source=lnt'))][not(descendant::h3)]";

    /**
     * @param GoogleDom $googleDom
     * @param \DOMElement $card
     * @return string one of the CARD_* constants
     */
    public static function classifyCard(GoogleDom $googleDom, \DOMElement $card)
    {
        $xpath = $googleDom->getXpath();

        if ($card->getAttribute('id') === 'st-card' || preg_match('/display\s*:\s*none/i', $card->getAttribute('style')) === 1) {
            return self::CARD_OTHER;
        }
        if ($xpath->query(self::PLACES_LISTING_XPATH, $card)->length > 0) {
            return self::CARD_PLACES;
        }
        if ($xpath->query(self::AIO_CARD_TEST, $card)->length > 0) {
            return self::CARD_AIO;
        }
        if ($xpath->query(self::TITLE_ANCHOR_XPATH, $card)->length > 0) {
            return self::CARD_ORGANIC;
        }
        if ($xpath->query('descendant::h3', $card)->length === 0
            && $xpath->query(self::RELATED_QUERY_XPATH, $card)->length >= 2
        ) {
            return self::CARD_RELATED;
        }

        return self::CARD_OTHER;
    }

    /**
     * A div#main holding basic-layout organic cards and no #rso. Per container,
     * not per document: a stitched multi-page archive (one DOM on the rank path)
     * repeats #main once per page and can mix layouts, e.g. a JS page 1 with a
     * basic page 4. A JS page's #main always wraps its own #rso.
     */
    const MAIN_CONTAINER_XPATH = "//div[@id='main'][not(descendant::*[@id='rso'])][descendant::a[starts-with(@href, '/url?')][descendant::h3]]";

    /**
     * Every basic-layout div#main of the document, in document order; empty for
     * a JS-layout page.
     *
     * @param GoogleDom $googleDom
     * @return \DOMElement[]
     */
    public static function mainContainers(GoogleDom $googleDom)
    {
        $containers = [];
        foreach ($googleDom->getXpath()->query(self::MAIN_CONTAINER_XPATH) as $node) {
            $containers[] = $node;
        }

        return $containers;
    }

    /**
     * @param GoogleDom $googleDom
     * @return bool
     */
    public static function isBasicLayout(GoogleDom $googleDom)
    {
        return self::mainContainers($googleDom) !== [];
    }

    /**
     * @param GoogleDom $googleDom
     * @param \DOMElement $node
     * @return bool
     */
    public static function isMainContainer(GoogleDom $googleDom, \DOMElement $node)
    {
        if ($node->getAttribute('id') !== 'main') {
            return false;
        }

        foreach (self::mainContainers($googleDom) as $container) {
            if ($container->isSameNode($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add the basic-layout div#main containers to a parser's parsable items, so
     * ClassicalResultBasic (first in both rule lists) can claim them. Merged in
     * document order: results are ranked in the order their groups are parsed,
     * so on a mixed stitched archive a basic page must not jump ahead of the JS
     * pages before it. A page without such a container gets its items back
     * untouched.
     *
     * @param GoogleDom $googleDom
     * @param \DOMElement[]|\Traversable $items
     * @return \DOMElement[]|\Traversable
     */
    public static function withMainContainers(GoogleDom $googleDom, $items)
    {
        $containers = self::mainContainers($googleDom);
        if ($containers === []) {
            return $items;
        }

        $merged = $containers;
        foreach ($items as $item) {
            if (!($item instanceof \DOMElement)) {
                continue;
            }
            foreach ($containers as $container) {
                if ($container->isSameNode($item)) {
                    continue 2;
                }
            }
            $merged[] = $item;
        }

        // Document order. PHP's DOM has no compareDocumentPosition, so rank every
        // element once - only on basic-layout pages, which are small.
        $order = new \SplObjectStorage();
        $position = 0;
        foreach ($googleDom->getXpath()->query('//*') as $element) {
            $order[$element] = $position++;
        }
        usort($merged, function ($a, $b) use ($order) {
            return ($order->contains($a) ? $order[$a] : PHP_INT_MAX) <=> ($order->contains($b) ? $order[$b] : PHP_INT_MAX);
        });

        return $merged;
    }

    /**
     * The card holding a title anchor: the Gx5Zad wrapper when present, else the
     * header's parent.
     *
     * @param GoogleDom $googleDom
     * @param \DOMElement $anchor
     * @return \DOMElement|null
     */
    public static function cardOf(GoogleDom $googleDom, \DOMElement $anchor)
    {
        $card = $googleDom->getXpath()->query(self::CARD_XPATH, $anchor);
        if ($card->length > 0) {
            return $card->item(0);
        }

        $header = $anchor->parentNode;
        if ($header instanceof \DOMElement && $header->parentNode instanceof \DOMElement) {
            return $header->parentNode;
        }

        return null;
    }

    /**
     * Destination of a Google redirect href: /url?q=<dest> on this layout, or
     * /url?url=<dest> as on the JS layout's redirect links. Null when neither
     * holds an absolute http(s) URL. Not FILTER_VALIDATE_URL: it rejects
     * destinations with raw non-ASCII characters, which real result URLs carry.
     *
     * @param string $href
     * @return string|null
     */
    public static function unwrapRedirect($href)
    {
        if (!is_string($href) || $href === '') {
            return null;
        }

        $query = parse_url($href, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }

        $params = [];
        parse_str($query, $params);

        foreach (['q', 'url'] as $key) {
            if (isset($params[$key]) && is_string($params[$key])
                && preg_match('#^https?://#i', $params[$key]) === 1
                && (string)parse_url($params[$key], PHP_URL_HOST) !== ''
            ) {
                return $params[$key];
            }
        }

        return null;
    }
}
