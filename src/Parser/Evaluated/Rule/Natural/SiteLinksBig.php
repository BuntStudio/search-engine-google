<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class SiteLinksBig implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        return self::RULE_MATCH_MATCHED;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $doNotRemoveSrsltidForDomain = '')
    {
        $siteLinksNodes = $dom->xpathQuery("descendant::div[@class='usJj9c']", $node);

        if ($siteLinksNodes->length == 0) {
            return;
        }

        $items = [];

        foreach ($siteLinksNodes as $siteLinksNode) {
            $aNode   = $dom->xpathQuery("descendant::a", $siteLinksNode)->item(0);

            if ($aNode === null) {
                continue;
            }

            $items[] = ['title' => $aNode->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($aNode->getAttribute('href'))];
        }

        $resultSet->addItem(
            new BaseResult(NaturalResultType::SITE_LINKS_BIG, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }
}
