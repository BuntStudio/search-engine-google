<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class Maps implements ParsingRuleInterface
{
    protected $steps = ['version1', 'version2', 'version3'];
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('id') == 'Odp5De' || $node->getAttribute('class') == 'C7r6Ue' || str_contains($node->getAttribute('class'),  'WVGKWb') || str_contains($node->getAttribute('class'),  'Qq3Lb')  || str_contains($node->getAttribute('class'),  'VT5Tde')) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        foreach ($this->steps as $functionName) {

            if ($resultSet->hasType(NaturalResultType::MAP)) {
                break 1;
            }

            try {
                call_user_func_array([$this, $functionName], [$googleDOM, $node, $resultSet, $isMobile]);
            } catch (\Exception $exception) {
                continue;
            }

        }
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[@class='rllt__details']", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            if (empty($ratingStarNode->parentNode->childNodes[1])) {
                continue;
            }

            $spanElements[] = [
                'title' => $ratingStarNode->parentNode->childNodes[1]->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        if(!empty($spanElements)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query("descendant::div[@class='rllt__details']", $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            if($ratingStarNode->childNodes->length ==0) {
                continue;
            }

            $href = null;
            if ($ratingStarNode->parentNode->parentNode->parentNode->nextSibling !== null &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->hasAttribute('href') &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->hasAttribute('class') &&
                $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->getAttribute('class') === 'yYlJEf Q7PwXb L48Cpd brKmxb'
            ) {
                $href = $ratingStarNode->parentNode->parentNode->parentNode->nextSibling->getAttribute('href');
            }

            $spanElements[] = [
                'title' => $ratingStarNode->childNodes->item(0)->textContent,
                'href' => $href,
            ];
        }

        if(!empty($spanElements)) {
            $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }
    }


    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile)
    {
        $ratingStars = $googleDOM->getXpath()->query('descendant::g-review-stars', $node);

        if ($ratingStars->length == 0) {
            return;
        }

        $spanElements = [];

        foreach ($ratingStars as $ratingStarNode) {
            $spanElements[] = [
                'title' => $ratingStarNode->parentNode->parentNode->parentNode->childNodes[1]
                    ->childNodes[0]->textContent,
                'href' => null, // TODO: find the href
            ];
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::MAP, $spanElements, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
