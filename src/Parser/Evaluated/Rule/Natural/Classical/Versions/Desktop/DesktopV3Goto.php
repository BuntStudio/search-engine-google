<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Desktop;

use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class DesktopV3Goto implements ParsingRuleByVersionInterface
{
    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        if (strpos($organicResultObject->getLink(), '/goto?url=') !== false) {
            /* @var $aTag \DOMElement */
            $aTag = $dom->xpathQuery("descendant::cite[@role='text' and starts-with(text(), 'https://')]", $organicResult);

            if (!empty($aTag) && $aTag->length > 0) {
                $citeValue = $aTag->item(0)->getNodeValue();
                $citeValue = explode(' › ', $citeValue)[0];
                $citeValue = rtrim($citeValue, ' .');

                $link = \Utils::removeParamFromUrl(
                    \SM_Rank_Service::getUrlFromGoogleTranslate($dom->getUrl()->resolveAsString($citeValue)),
                    'srsltid',
                    $doNotRemoveSrsltidForDomains
                );

                $organicResultObject->setLink($link);
                $organicResultObject->setUsedGotoDomainLink(true);
            }
        }
    }
}
