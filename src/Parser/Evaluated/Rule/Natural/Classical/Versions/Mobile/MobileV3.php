<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class MobileV3 implements ParsingRuleByVersionInterface
{
    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, string $doNotRemoveSrsltidForDomain = '')
    {
        /* @var $aTag \DOMElement */
        $aTag = $dom->xpathQuery("descendant::*[
            contains(concat(' ', normalize-space(@class), ' '), ' d5oMvf KJDcUb ') or
            contains(concat(' ', normalize-space(@class), ' '), ' tKdlvb KJDcUb ') or
            contains(concat(' ', normalize-space(@class), ' '), ' C8nzq BmP5tf ') or

             @class='KJDcUb'
         ]/a", $organicResult);

        if (empty($aTag->length)) {
            $aTag = $dom->xpathQuery("descendant::a[
            contains(concat(' ', normalize-space(@class), ' '), ' rTyHce jgWGIe ')
         ]", $organicResult);
        }

        if (empty($aTag) && $organicResultObject->getLink() === null) {
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

        $titleTag  = $dom->getXpath()->query("descendant::div[
        contains(concat(' ', normalize-space(@class), ' '), ' MUxGbd v0nnCb ')  or
        contains(concat(' ', normalize-space(@class), ' '), ' MBeuO ')

        ]", $organicResult);

        if ($titleTag->length ==0) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        if($organicResultObject->getTitle() === null) {
            $organicResultObject->setTitle($titleTag->item(0)->textContent);
        }

        $descriptionNodes = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' yDYNvb ')
        or contains(concat(' ', normalize-space(@class), ' '), ' Hdw6tb ')
        ]", $organicResult);

        if ($descriptionNodes->length > 0) {
            $organicResultObject->setDescription($descriptionNodes->item(0)->textContent);
        }
    }

}
