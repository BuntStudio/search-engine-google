<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class VideosMobile implements ParsingRuleInterface
{
    protected $steps = ['version1', 'version2', 'version3', 'version4', 'version5', 'version6'];
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->hasClass('cawG4b') && $node->hasClass('OvQkSb')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('uVMCKf') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('uVMCKf') && $node->hasClass('Ww4FFb')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('HD8Pae') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('YJpHnb') && $node->hasClass('mnr-c')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('vtSz8d') && $node->hasClass('Ww4FFb') && $node->hasClass('vt6azd')) {
            return self::RULE_MATCH_MATCHED;
        }

        //todo implement later - https://app.clickup.com/t/8698fyc6a
//        if ($node->hasClass('EDblX') && $node->hasClass('HG5ZQb')) {
//            //this is a general list, search for video children
//            $videosPlayers = $dom->getXpath()->query('descendant::div[@class="XRVJtc bnmjfe aKByQb"]', $node);
//            if ($videosPlayers->length > 0) {
//                return self::RULE_MATCH_MATCHED;
//            }
//        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false, array $doNotRemoveSrsltidForDomains = [])
    {
        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    protected function version1(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $data = [];

        if($node->parentNode->tagName !='a') {
            return;
        }

        $data[] = ['url' => \SM_Rank_Service::getUrlFromGoogleTranslate($node->parentNode->getAttribute('href'))];

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version2(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosContainer = $googleDOM->getXpath()->query("descendant::video-voyager", $node);

        if ($videosContainer->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosContainer as $videoNode) {
            $url = $googleDOM->getXpath()->query("descendant::a", $videoNode)->item(0);

            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($url->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version3(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::span[@class="OPkOif"]', $node);

        if ($videosPlayerBtns->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $url = $videoBtn->parentNode->parentNode->parentNode->parentNode->parentNode;

            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($url->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version4(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::a[@class="BG7Pyb"]', $node);

        if ($videosPlayerBtns->length ==0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($videoBtn->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version5(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $videosPlayerBtns = $googleDOM->getXpath()->query('descendant::a[@class="ddkIM c30Ztd"]', $node);

        if ($videosPlayerBtns->length == 0) {
            return;
        }

        $data = [];

        foreach ($videosPlayerBtns as $videoBtn) {
            $data[] = ['url'=> \SM_Rank_Service::getUrlFromGoogleTranslate($videoBtn->getAttribute('href'))];
        }

        $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function version6(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        if (!($node->hasClass('vtSz8d') && $node->hasClass('Ww4FFb') && $node->hasClass('vt6azd'))) {
            return;
        }

        $urls = [];

        $elements = $googleDOM->getXpath()->query('descendant::a|descendant::div[@data-sulr or @data-curl]', $node);

        foreach ($elements as $element) {
            // For 'a' elements, check the href attribute
            if ($element->nodeName === 'a') {
                $href = $element->getAttribute('href');
                if ($href !== '#' && !str_starts_with($href, '#')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($href);
                }
            }
            // For 'div' elements, check for data-sulr or data-curl attributes
            else if ($element->nodeName === 'div') {
                if ($element->hasAttribute('data-sulr')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-sulr'));
                }
                if ($element->hasAttribute('data-curl')) {
                    $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-curl'));
                }
            }
        }
        $urls = array_unique($urls);
        if (!empty($urls)) {
            $data = [];
            foreach ($urls as $url) {
                $data[] = ['url' => $url];
            }
            $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }

    protected function version7(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        //todo implement later - https://app.clickup.com/t/8698fyc6a
        $videosPlayers = $googleDOM->getXpath()->query('descendant::div[@class="XRVJtc bnmjfe aKByQb"]', $node);
        if ($videosPlayers->length == 0) {
            return;
        }

        $urls = [];


        foreach ($videosPlayers as $videosPlayer) {
            $elements = $googleDOM->getXpath()->query('descendant::a|descendant::div[@data-sulr or @data-curl]', $videosPlayer);

            foreach ($elements as $element) {
                // For 'a' elements, check the href attribute
                if ($element->nodeName === 'a') {
                    $href = $element->getAttribute('href');
                    if ($href !== '#' && !str_starts_with($href, '#')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($href);
                    }
                }
                // For 'div' elements, check for data-sulr or data-curl attributes
                else if ($element->nodeName === 'div') {
                    if ($element->hasAttribute('data-sulr')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-sulr'));
                    }
                    if ($element->hasAttribute('data-curl')) {
                        $urls[] = \SM_Rank_Service::getUrlFromGoogleTranslate($element->getAttribute('data-curl'));
                    }
                }
            }
        }

        $urls = array_unique($urls);
        if (!empty($urls)) {
            $data = [];
            foreach ($urls as $url) {
                $data[] = ['url' => $url];
            }
            $resultSet->addItem(new BaseResult([NaturalResultType::VIDEOS_MOBILE], $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
        }

    }
}
