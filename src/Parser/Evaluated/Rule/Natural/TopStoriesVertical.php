<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\Serp\ResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class TopStoriesVertical implements ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($dom->cssQuery('h3._MRj', $node)->length == 1
            && $dom->cssQuery('g-scrolling-carousel._Ncr', $node)->length == 0
        ) {
            return self::RULE_MATCH_MATCHED;
        }


        return self::RULE_MATCH_NOMATCH;
    }

    private function parseNode(GoogleDom $dom, $node)
    {
        return [
            'isCarousel' => false,
            'isVertical' => true,
            'news' => function () use ($dom, $node) {

                $news = [];
                $nodes = $dom->cssQuery('._KBh', $node);

                foreach ($nodes as $newsNode) {
                    $news[] = new BaseResult(NaturalResultType::TOP_STORIES_NEWS_VERTICAL, [
                        'title' => function () use ($dom, $newsNode) {
                            $el = $dom->cssQuery('a', $newsNode)->item(0);
                            return $el->nodeValue;
                        },
                        'url' => function () use ($dom, $newsNode) {
                            $el = $dom->cssQuery('a', $newsNode)->item(0);
                            return \SM_Rank_Service::getUrlFromGoogleTranslate($el->getAttribute('href'));
                        }
                    ]);
                }

                $resultSet = new ResultSet();
                $resultSet->addItems($news);

                return $resultSet;
            }
        ];
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $item = new BaseResult(
            [NaturalResultType::TOP_STORIES],
            $this->parseNode($dom, $node),
            $node,
            $this->hasSerpFeaturePosition,
            $this->hasSideSerpFeaturePosition
        );
        $resultSet->addItem($item);
    }
}
