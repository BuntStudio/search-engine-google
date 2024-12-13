<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Desktop;

use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class DesktopV1 implements ParsingRuleByVersionInterface
{
    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, string $doNotRemoveSrsltidForDomain = '')
    {
        /* @var $aTag \DOMElement */

        $aTag = $dom->xpathQuery("descendant::*[(self::span)]/a", $organicResult)->item(0);
        if (!$aTag) {
            $aTag = $dom->xpathQuery("descendant::*[(self::div|self::g-link)]/a[not(contains(@class, 'fl'))]", $organicResult)->item(0);
        }
        if (!$aTag) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        $link = \Utils::removeParamFromUrl(
            \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->getAttribute('href')),
            'srsltid',
            $doNotRemoveSrsltidForDomains
        );

        $organicResultObject->setLink($link);

        $h3Tag = $dom->xpathQuery('descendant::h3', $organicResult)->item(0);

        if (!$h3Tag) {
            throw new InvalidDOMException('Cannot parse a classical result.');
        }

        $organicResultObject->setTitle($h3Tag->textContent);

        $descriptionTag = $dom->xpathQuery("descendant::div[@class='IsZvec']", $organicResult)->item(0);

        if ($descriptionTag) {
            $organicResultObject->setDescription($descriptionTag->textContent);
        }

    }
}
