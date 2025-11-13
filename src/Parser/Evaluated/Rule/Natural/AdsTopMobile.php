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

class AdsTopMobile extends AdsTop
{

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $adsNodes = $dom->getXpath()->query(
            "descendant::div[
                        contains(concat(' ', normalize-space(@class), ' '), ' mnr-c ') or
                        contains(concat(' ', normalize-space(@class), ' '), ' Ww4FFb ') or
                        contains(concat(' ', normalize-space(@class), ' '), ' uEierd ') or
                        contains(concat(' ', normalize-space(@class), ' '), ' ozeYlc ')
                        ]",
            $node);

        $links    = [];

        if ($adsNodes->length == 0) {
            return;
        }

        foreach ($adsNodes as $adsNode) {

            $aHrefs = $dom->getXpath()->query("descendant::a[
        contains(concat(' ', normalize-space(@class), ' '), ' C8nzq BmP5tf ') or
        contains(concat(' ', normalize-space(@class), ' '), ' KO2aMe VNaOAc ') or
        @class='sXtWJb' or
        (
            contains(concat(' ', normalize-space(@class), ' '), ' BmP5tf ') and
            contains(concat(' ', normalize-space(@class), ' '), ' cz3goc ')
        ) or(
            contains(concat(' ', normalize-space(@class), ' '), ' OcpZAb ') and
            contains(concat(' ', normalize-space(@class), ' '), ' cz3goc ')
        ) or
        contains(concat(' ', normalize-space(@class), ' '), ' rhgcW ')

        ]", $adsNode);

            $pla = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' commercial-unit-mobile-top ')]", $adsNode);
            if ($pla->length != 0) {
                //these are product listings, not just ads
                continue;
            }

            foreach ($aHrefs as $href) {

                if (
                    !empty($href->getAttribute('style')) &&
                    strpos($href->getAttribute('style'), 'display:none') !== false
                ) {
                    continue;
                }

                if ($href->hasClass('gsrt')) {
                    continue;
                }

                if (preg_match('/googleadservices/', $href->getAttribute('href'))) {
                    continue;
                }

                $hrefAttribute = $href->getAttribute('href');

                if (!empty($href->getAttribute('data-rw'))) {
                    $dataRw = $href->getAttribute('data-rw');
                    $hrefAttribute = self::processDataRwUrl($hrefAttribute, $dataRw);
                }

                if (empty($links) || empty(array_column($links, 'url'))) {
                    $links[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefAttribute)];
                } elseif (!in_array($hrefAttribute, array_column($links, 'url'))) {
                    $links[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($hrefAttribute)];
                }
            }
        }

        if (!empty($links)) {

            if (
                $node->getAttribute('id') == self::ADS_TOP_CLASS ||
                (
                    $node->getAttribute('class') == 'IuoSj' &&
                    ($dom->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@id), ' '), ' tadsb ')]",$node))->length == 0
                )
            ) {
                $resultSet->addItem(new BaseResult(NaturalResultType::AdsTOP_MOBILE, $links, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }

            if ($node->getAttribute('id') == self::ADS_DOWN_CLASS) {
                $resultSet->addItem(new BaseResult(NaturalResultType::AdsDOWN_MOBILE, $links, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }
    }

    public static function processDataRwUrl($hrefAttribute, $dataRw) {
        // Add https:// if protocol is missing
        if (!preg_match("~^(?:f|ht)tps?://~i", $hrefAttribute)) {
            $hrefAttribute = "https://" . $hrefAttribute;
        }

        // Parse the URL into components
        $urlParts = parse_url($hrefAttribute);

        // Check if it's a valid URL and has a host
        if ($urlParts && isset($urlParts['host'])) {
            // Check if it's a Google domain
            if (preg_match("/(?:^|\.)google\.[a-z]{2,}$/i", $urlParts['host'])) {
                // Build new URL with Google domain but new path
                $newUrl = $urlParts['scheme'] . "://" . $urlParts['host'];

                // Add the new path, ensuring it starts with /
                $newPath = ltrim($dataRw, '/');
                $newUrl .= '/' . $newPath;

                return $newUrl;
            }
        }

        // Return original URL (with added protocol if that was missing)
        return $hrefAttribute;
    }
}
