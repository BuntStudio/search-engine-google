<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Basic;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\BasicLayout;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

/**
 * Organic card of Google's basic (no-JS) layout, see BasicLayout. Purely
 * structural: title = the h3 inside the /url? anchor, link = that anchor's
 * redirect destination, description = the longest link-free text block of the
 * card outside the anchor (skips the "În stoc" badge and inline sitelink rows).
 */
class BasicV1 implements ParsingRuleByVersionInterface
{
    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        $xpath = $dom->getXpath();

        $anchor = $xpath->query(BasicLayout::TITLE_ANCHOR_XPATH, $organicResult);
        if ($anchor->length === 0) {
            throw new InvalidDOMException('Cannot parse a basic-layout result.');
        }
        $anchor = $anchor->item(0);

        if ($organicResultObject->getLink() === null) {
            $destination = BasicLayout::unwrapRedirect($anchor->getAttribute('href'));
            if ($destination === null) {
                throw new InvalidDOMException('Basic-layout result link is not a Google redirect.');
            }

            $organicResultObject->setLink(\Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($destination),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            ));
        }

        if ($organicResultObject->getTitle() === null) {
            $title = trim($xpath->query('descendant::h3', $anchor)->item(0)->textContent);
            if ($title !== '') {
                $organicResultObject->setTitle($title);
            }
        }

        if ($organicResultObject->getDescription() === null) {
            $description = '';
            $blocks = $xpath->query(
                'descendant::div[not(descendant::div)][not(ancestor::a)][not(descendant::a)]',
                $organicResult
            );
            foreach ($blocks as $block) {
                $text = trim($block->textContent);
                if (mb_strlen($text) > mb_strlen($description)) {
                    $description = $text;
                }
            }

            if ($description !== '') {
                $organicResultObject->setDescription($description);
            }
        }
    }
}
