<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions\Desktop;

use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\OrganicResultObject;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class DesktopV2 implements ParsingRuleByVersionInterface
{
    public function parseNode(GoogleDom $dom, \DomElement $organicResult, OrganicResultObject $organicResultObject, array $doNotRemoveSrsltidForDomains = [])
    {
        if ($organicResultObject->getDescription() === null) {

            $descriptionTag = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' yXK7lf ')]", $organicResult);

            if ($descriptionTag->length >0 ) {
                $organicResultObject->setDescription($descriptionTag->item(0)->textContent);
            } else {
                $descriptionTag = $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' xcQxib ')]", $organicResult);
                if ($descriptionTag->length >0 ) {
                    $organicResultObject->setDescription($descriptionTag->item(0)->textContent);
                }

            }
        }
    }
}
