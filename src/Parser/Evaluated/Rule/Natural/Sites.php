<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class Sites implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('class'), 'zJUuqf') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $onlyRemoveSrsltidForDomain = '')
    {
        $sitesTitle = null;
        $sitesTitleNodeList = $dom->getXpath()->query('descendant::span[contains(concat(" ", @class, " "), " mgAbYb OSrXXb RES9jf IFnjPb ")]', $node);
        if ($sitesTitleNodeList->length) {
            $sitesTitle = $sitesTitleNodeList->current()->textContent;
        }

        $sitesItems = $dom->getXpath()->query('following-sibling::div[@jscontroller="s0j7C"]/descendant::*[contains( @jscontroller,"QQ51Ce" )]', $node);

        if ($sitesItems->length > 1) {
            $resultSet->addItem(new BaseResult(NaturalResultType::SITES, ['count' => $sitesItems->length, 'title' => $sitesTitle], $node));
        }
    }
}
