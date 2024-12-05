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

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $onlyRemoveSrsltidForDomain = '')
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

                if (empty($links) || empty(array_column($links, 'url'))) {
                    $links[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($href->getAttribute('href'))];
                } elseif (!in_array($href->getAttribute('href'), array_column($links, 'url'))) {
                    $links[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($href->getAttribute('href'))];
                }
            }
        }

        if (!empty($links)) {

            if ($node->getAttribute('id') == self::ADS_TOP_CLASS) {
                $resultSet->addItem(new BaseResult(NaturalResultType::AdsTOP_MOBILE, $links, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }

            if ($node->getAttribute('id') == self::ADS_DOWN_CLASS) {
                $resultSet->addItem(new BaseResult(NaturalResultType::AdsDOWN_MOBILE, $links, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }
    }
}
