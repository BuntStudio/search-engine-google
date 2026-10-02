<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\BasicLayout;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Basic\BasicV1;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

/**
 * Organic results of Google's basic (no-JS) layout, see BasicLayout.
 *
 * Matches only the basic-layout div#main containers that
 * BasicLayout::withMainContainers() hands to the parsers (one per page of a
 * stitched archive), so JS-layout SERPs, which also carry id="main", never reach
 * it. Cards are looked up relative to the container, so each page's results are
 * parsed exactly once. First in both rule lists: it must claim #main before any
 * DB match rule with a descendant:: test does.
 *
 * Hardcoded in every parser mode — there is no DB feature for this layout yet.
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
        $seenCards = [];
        $k = 0;

        foreach ($xpath->query(BasicLayout::TITLE_ANCHOR_XPATH, $node) as $anchor) {
            $card = BasicLayout::cardOf($dom, $anchor);
            if ($card === null) {
                continue;
            }

            $cardPath = $card->getNodePath();
            if (isset($seenCards[$cardPath])) {
                continue;
            }
            $seenCards[$cardPath] = true;

            // An organic card has exactly one title. A card with several is a
            // feature block (Places renders two h3 under one card); its links are
            // not organic positions.
            if ($xpath->query('descendant::h3', $card)->length !== 1) {
                continue;
            }

            // Ads: same gates as ClassicalResultMobile::skiResult. The sample
            // pages ship empty ad shells (#bottomads > #tadsb > GUyUUb), so the
            // real ad card shape on this layout is unseen; guard anyway.
            if ($xpath->query(self::AD_ANCESTOR_XPATH, $card)->length > 0
                || $xpath->query(self::AD_CLICK_LINK_XPATH, $card)->length > 0
            ) {
                continue;
            }

            $k++;
            if ($this->parseNodeWithRules($dom, $card, $resultSet, $k, $doNotRemoveSrsltidForDomains) !== null) {
                $this->parseInlineSitelinks($dom, $card, $resultSet);
            }
        }

        if ($k === 0) {
            $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, ['end_of_results' => false], $node));
            $this->monolog->error('Cannot identify results in basic-layout html page', ['class' => self::class]);
        }
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
}
