<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class FeaturedSnipped implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    protected $steps = ['version1', 'version2'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('class'), 'xpdopen') !== false || strpos($node->getAttribute('class'), 'xpdbox') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if (strpos($node->getAttribute('class'), 'CWesnb') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::FEATURED_SNIPPED_MOBILE : NaturalResultType::FEATURED_SNIPPED;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile, $doNotRemoveSrsltidForDomains]);
        }
    }

    public function version1(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = []
    ) {
        $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $node);

        if ($naturalResultNodes->length == 0) {
            $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' SALvLe ')]", $node);
            if ($naturalResultNodes->length == 0) {
                // this older class is still valid
                $naturalResultNodes = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' V3FYCf ')]", $node);
                if ($naturalResultNodes->length == 0) {
                    return;
                }
            }
        }

        $results = [];

        foreach ($naturalResultNodes  as $featureSnippetNode) {
            $isHidden = $googleDOM->getXpath()->query("ancestor::g-accordion-expander", $featureSnippetNode);
            if ($isHidden->length >  0) {
                continue;
            }

            $aTag = $googleDOM->getXpath()->query("descendant::a", $featureSnippetNode);
            $h3Tag = $googleDOM->getXpath()->query("descendant::h3", $featureSnippetNode);//title
            $description = $googleDOM->getXpath()->query("preceding-sibling::div/descendant::div[@class='LGOjhe']", $featureSnippetNode);//description
            if ($description->length == 0)  {
                $description = $googleDOM->getXpath()->query("descendant::div[@class='LGOjhe']", $featureSnippetNode);//description

            }
            if ($aTag->length == 0) {
                continue;
            }

            $object              = new \StdClass();

            $object->url         = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->item(0)->getAttribute('href')),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );

            $object->description = (!empty($description) && !empty($description->item(0)) && !empty($description->item(0)->textContent)) ? $description->item(0)->textContent : '';
            $object->title       = (!empty($h3Tag) && !empty($h3Tag->item(0)) && !empty($h3Tag->item(0)->textContent)) ? $h3Tag->item(0)->textContent : '';

            $results[] = $object;
        }

        if(!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }

    public function version2(
        GoogleDom $googleDOM,
        \DomElement $node,
        IndexedResultSet $resultSet,
        $isMobile = false,
        array $doNotRemoveSrsltidForDomains = []
    ) {
        if (!$isMobile) {
            return;
        }
        $results = [];

        $aTag = $googleDOM->getXpath()->query("descendant::a", $node);
        $description = $googleDOM->getXpath()->query("descendant::div[@class='LGOjhe']", $node);//description

        foreach ($aTag as $item) {
            $url = \Utils::removeParamFromUrl(
                \SM_Rank_Service::getUrlFromGoogleTranslate($item->getAttribute('href')),
                'srsltid',
                $doNotRemoveSrsltidForDomains
            );
            //if valid url has hostname and is not google
            if (parse_url($url, PHP_URL_HOST) && strpos(parse_url($url, PHP_URL_HOST), 'google') === false) {
                $object              = new \StdClass();
                $object->url         = $url;
                $object->description = (!empty($description) && !empty($description->item(0)) && !empty($description->item(0)->textContent)) ? $description->item(0)->textContent : '';
                $object->title       = $item->textContent;
                $results[] = $object;
                break;
            }

        }

//        if (!empty($aTag) && !empty($aTag->item(1))) {
//            $object              = new \StdClass();
//            $object->url         = $aTag->item(1)->getAttribute('href');
//            $object->description = (!empty($description) && !empty($description->item(1)) && !empty($description->item(1)->textContent)) ? $description->item(1)->textContent : '';
//            $object->title       = $aTag->item(1)->textContent;
//            $results[] = $object;
//        } else if (!empty($aTag) && !empty($aTag->item(0))) {
//            $object              = new \StdClass();
//            $object->url         = $aTag->item(0)->getAttribute('href');
//            $object->description = (!empty($description) && !empty($description->item(0)) && !empty($description->item(0)->textContent)) ? $description->item(0)->textContent : '';
//            $object->title       = $aTag->item(0)->textContent;
//            $results[] = $object;
//        }

        if(!empty($results)) {
            $resultSet->addItem(
                new BaseResult($this->getType($isMobile), $results, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
            );
        }
    }
}
