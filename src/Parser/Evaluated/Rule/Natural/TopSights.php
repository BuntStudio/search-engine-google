<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class TopSights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'jhtnKe') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $topSightsNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), 'ddkIM')]", $node);
//        if ($topSightsNodes->length > 0) {
//            $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
//        }

        if ($topSightsNodes->length > 0 ) {
            $items = [];
            for ($i = 0; $i < $topSightsNodes->length; $i++) {
                if (!empty($topSightsNodes->item($i))){
                    $item = $topSightsNodes->item($i);
                    $parent = $item->parentNode;
                    $nameNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' yVCOtc ')]", $parent);
                    if ($nameNodes->length > 0) {
                        $name = trim($nameNodes->item(0)->textContent);
                    } else {
                        $name = trim($parent->textContent);
                    }

                    $urlNodes = $googleDOM->getXpath()->query("descendant::a[contains(concat(' ', normalize-space(@class), ' '), ' hHB9mc ')]", $parent);
                    if ($urlNodes->length > 0) {
                        $url = $urlNodes->item(0)->getAttribute('href');
                    } else {
                        $url = $item->getAttribute('href');
                    }
                    if (!empty($url)) {
                        $items[] = ['name' => $name, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($url)];
                    }
                }
            }

            if (count($items)) {
                $resultSet->addItem(new BaseResult(NaturalResultType::TOP_SIGHTS, $items, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
            }
        }

    }
}
