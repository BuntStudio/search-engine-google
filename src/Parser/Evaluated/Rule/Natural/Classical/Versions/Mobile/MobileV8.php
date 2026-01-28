<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Mobile;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class MobileV8 implements ParsingRuleByVersionInterface
{

    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        /* @var $aTag \DOMElement */
        $aTag = $dom->xpathQuery("descendant::a", $organicResult);

        if (empty($aTag) && $organicResultObject->getLink() === null) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        if(empty($aTag->item(0)) && $organicResultObject->getLink() === null) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        if($organicResultObject->getLink() === null) {
            $link = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($dom->getUrl()->resolveAsString($aTag->item(0)->getAttribute('href'))),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            $organicResultObject->setLink($link);
        }

        $titleNode = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' v7jaNc')]",
            $aTag->item(0));

        if($titleNode->length >0 && $organicResultObject->getTitle() === null) {
            $organicResultObject->setTitle($titleNode->item(0)->textContent);
        }

        $descriptionNode = $dom->getXpath()->query(
            "descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' VwiC3b')]",
            $aTag->item(0)
        );

        if ($descriptionNode->length > 0 && $organicResultObject->getDescription() === null) {
            $organicResultObject->setDescription($descriptionNode->item(0)->textContent);
        }
    }

}
