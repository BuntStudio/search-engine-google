<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class MobileV7Goto implements ParsingRuleByVersionInterface
{

    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        if (strpos($organicResultObject->getLink(), '/goto?url=') !== false) {
            /* @var $aTag \DOMElement */
            $aTag = $dom->xpathQuery("descendant::span[@role='text' and starts-with(text(), 'https://')]", $organicResult);

            if (!empty($aTag) && $aTag->length > 0) {
                $link = \Utils::removeParamFromUrl(
                    \SM_Rank_Service::getUrlFromGoogleTranslate($dom->getUrl()->resolveAsString($aTag->item(0)->getNodeValue())),
                    'srsltid',
                    $doNotRemoveSrsltidForDomains
                );

                $organicResultObject->setLink($link);
                $organicResultObject->setUsedGotoDomainLink(true);
            }
        }
    }

}
