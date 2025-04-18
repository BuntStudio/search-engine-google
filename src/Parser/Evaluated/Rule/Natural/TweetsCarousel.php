<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class TweetsCarousel implements ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {

        if ($dom->cssQuery('.g ._BOf', $node)->length) {
            return self::RULE_MATCH_MATCHED;
        }
        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $xpath = $dom->getXpath();

        /* @var $aTag \DOMElement */
        $aTag=$xpath
            ->query("descendant::h3[@class='r'][1]//a", $node)
            ->item(0);

        if ($aTag) {
            $title = $aTag->nodeValue;

            preg_match('/@([A-Za-z0-9_]{1,15})/', $title, $match);

            $data = [
                'title'   => $title,
                'url'     => \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->getAttribute('href')),
                'user'    => isset($match[0]) ? $match[0] : null
            ];

            $item = new BaseResult(NaturalResultType::TWEETS_CAROUSEL, $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition);
            $resultSet->addItem($item);
        }
    }
}
