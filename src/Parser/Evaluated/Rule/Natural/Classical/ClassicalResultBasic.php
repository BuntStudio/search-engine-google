<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\BasicLayout;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Basic\BasicV1;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SGEWidgetBasic;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

/**
 * Google's basic (no-JS) layout, see BasicLayout: organic results plus the
 * features its cards carry.
 *
 * Matches only the basic-layout div#main containers that
 * BasicLayout::withMainContainers() hands to the parsers (one per page of a
 * stitched archive), so JS-layout SERPs, which also carry id="main", never reach
 * it. Cards are walked in document order, classified (BasicLayout::classifyCard)
 * and dispatched:
 *
 *   organic  -> CLASSICAL / CLASSICAL_MOBILE via BasicV1, inline links as
 *               SITE_LINKS_SMALL (same item SiteLinksSmall emits);
 *   aio      -> SGE_WIDGET / SGE_WIDGET_MOBILE via SGEWidgetBasic;
 *   places   -> MAP / MAP_MOBILE, one {title, href} per listing, the shape
 *               Maps / MapsMobile emit (href null as on mobile: the listing
 *               links are relative /searchviewer/ URLs, not a destination);
 *   related  -> RELATED_SEARCHES, the query strings of every "People also
 *               search for" card merged into ONE item (TranslateService keeps
 *               the last item of that type, so one per page). The JS layout's
 *               own PASF panel is not parsed - only its bottom #bres is - so
 *               this is the one place that feature maps to related searches.
 *
 * First in both rule lists: it must claim #main before any DB match rule with
 * a descendant:: test does. Hardcoded in every parser mode - there is no DB
 * feature for this layout yet.
 */
class ClassicalResultBasic extends ClassicalResultEngine implements ParsingRuleInterface
{
    protected $rulesForParsing;

    const AD_ANCESTOR_XPATH = "ancestor::*[@id='tads' or @id='tadsb' or @id='bottomads' or @data-text-ad]";
    const AD_CLICK_LINK_XPATH = "descendant::a[starts-with(@href, 'https://www.google.com/aclk') or starts-with(@data-rw, 'https://www.google.com/aclk')]";
    // Redirect links of the card that are not the title link (and not nested in it).
    const INLINE_SITELINK_XPATH = "descendant::a[starts-with(@href, '/url?')][not(descendant::h3)][not(ancestor::a)]";

    public function getRules()
    {
        if (null == $this->rulesForParsing) {
            $this->rulesForParsing = [new BasicV1()];
        }

        return $this->rulesForParsing;
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = 0)
    {
        return BasicLayout::isMainContainer($dom, $node) ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        $this->resultType = $isMobile ? NaturalResultType::CLASSICAL_MOBILE : NaturalResultType::CLASSICAL;

        $xpath = $dom->getXpath();
        $relatedQueries = [];
        $k = 0;

        foreach ($xpath->query(BasicLayout::CARDS_XPATH, $node) as $card) {
            switch (BasicLayout::classifyCard($dom, $card)) {
                case BasicLayout::CARD_ORGANIC:
                    if ($this->parseOrganicCard($dom, $card, $resultSet, $k + 1, $doNotRemoveSrsltidForDomains)) {
                        $k++;
                    }
                    break;

                case BasicLayout::CARD_AIO:
                    (new SGEWidgetBasic())->parse($dom, $card, $resultSet, $isMobile, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule);
                    break;

                case BasicLayout::CARD_PLACES:
                    $this->parsePlacesCard($dom, $card, $resultSet, $isMobile);
                    break;

                case BasicLayout::CARD_RELATED:
                    foreach ($xpath->query(BasicLayout::RELATED_QUERY_XPATH, $card) as $queryNode) {
                        $query = trim($queryNode->textContent);
                        if ($query !== '' && !in_array($query, $relatedQueries, true)) {
                            $relatedQueries[] = $query;
                        }
                    }
                    break;
            }
        }

        if ($relatedQueries !== []) {
            $resultSet->addItem(new BaseResult(NaturalResultType::RELATED_SEARCHES, $relatedQueries));
        }

        if ($k === 0) {
            $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, ['end_of_results' => false], $node));
            $this->monolog->error('Cannot identify results in basic-layout html page', ['class' => self::class]);
        }
    }

    /**
     * @return bool whether the card counted as an organic position
     */
    protected function parseOrganicCard(GoogleDom $dom, \DOMElement $card, IndexedResultSet $resultSet, $position, array $doNotRemoveSrsltidForDomains)
    {
        $xpath = $dom->getXpath();

        // An organic card has exactly one title. A card with several is a
        // feature block; its links are not organic positions.
        if ($xpath->query('descendant::h3', $card)->length !== 1) {
            return false;
        }

        // Ads: same gates as ClassicalResultMobile::skiResult. The sample
        // pages ship empty ad shells (#bottomads > #tadsb > GUyUUb), so the
        // real ad card shape on this layout is unseen; guard anyway.
        if ($xpath->query(self::AD_ANCESTOR_XPATH, $card)->length > 0
            || $xpath->query(self::AD_CLICK_LINK_XPATH, $card)->length > 0
        ) {
            return false;
        }

        if ($this->parseNodeWithRules($dom, $card, $resultSet, $position, $doNotRemoveSrsltidForDomains) !== null) {
            $this->parseInlineSitelinks($dom, $card, $resultSet);
        }

        return true;
    }

    /**
     * Inline sitelinks of a basic-layout card: the "What is X? · Y" row in the
     * card body, each entry an /url?q= redirect link. SiteLinksSmall::parse is
     * hardwired to the JS layout's div.HiHjCd, so the item is emitted here with
     * the same shape and flags (SITE_LINKS_SMALL, position-bearing) that
     * TranslateService already consumes.
     */
    protected function parseInlineSitelinks(GoogleDom $dom, \DOMElement $card, IndexedResultSet $resultSet)
    {
        $items = [];
        foreach ($dom->getXpath()->query(self::INLINE_SITELINK_XPATH, $card) as $aNode) {
            $url = BasicLayout::unwrapRedirect($aNode->getAttribute('href'));
            $title = trim($aNode->textContent);
            if ($url === null || $title === '') {
                continue;
            }
            $items[] = ['title' => $title, 'url' => $url];
        }

        if ($items === []) {
            return;
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::SITE_LINKS_SMALL, $items, $card, true, false));
    }

    /**
     * Local pack card: "Places" heading, map image, one /searchviewer/ link per
     * listing holding the business name as h3.
     */
    protected function parsePlacesCard(GoogleDom $dom, \DOMElement $card, IndexedResultSet $resultSet, $isMobile)
    {
        $listings = [];
        foreach ($dom->getXpath()->query(BasicLayout::PLACES_LISTING_XPATH, $card) as $listing) {
            $title = trim($dom->getXpath()->query('descendant::h3', $listing)->item(0)->textContent);
            if ($title === '') {
                continue;
            }
            $listings[] = ['title' => $title, 'href' => null];
        }

        if ($listings === []) {
            return;
        }

        $resultSet->addItem(new BaseResult(
            $isMobile ? NaturalResultType::MAP_MOBILE : NaturalResultType::MAP,
            $listings,
            $card,
            true,
            false
        ));
    }
}
