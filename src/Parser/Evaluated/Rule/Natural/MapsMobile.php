<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class MapsMobile implements ParsingRuleInterface
{
    protected $steps = ['version1', 'version2'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->hasClass('scm-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false)
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$googleDOM, $node, $resultSet, $isMobile]);
        }
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[@class='rllt__details']", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements['title'][] = $ratingStarNode->firstChild->firstChild->textContent;
        }

        $spanElements['title'] = array_unique($spanElements['title']);

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements));
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query('descendant::g-review-stars', $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements['title'][] = $ratingStarNode->parentNode->parentNode->childNodes[0]->childNodes[0]->textContent;
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP_MOBILE, $spanElements));
    }
}
