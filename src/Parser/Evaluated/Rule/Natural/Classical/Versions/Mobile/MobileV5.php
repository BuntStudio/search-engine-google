<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

/*
 * Mobile classical result with no description. only with title
 */
class MobileV5 implements ParsingRuleByVersionInterface
{

    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        /* @var $aTag \DOMElement */
        $aTag = $dom->xpathQuery("descendant::*[@class='sXtWJb']", $organicResult);

        if (empty($aTag) && $organicResultObject->getLink() === null) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        if(empty($aTag->item(0)) && $organicResultObject->getLink() === null) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        if ($organicResultObject->getLink() === null) {
            $link = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($dom->getUrl()->resolveAsString($aTag->item(0)->getAttribute('href'))),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            $organicResultObject->setLink($link);
        }

        if($organicResultObject->getTitle() === null) {
            $organicResultObject->setTitle($aTag->textContent);
        }
    }

}
