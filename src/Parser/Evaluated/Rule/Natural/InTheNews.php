<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class InTheNews implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        $child = $node->firstChild;
        if (!$child || !($child instanceof \DOMElement)) {
            return self::RULE_MATCH_NOMATCH;
        }
        if ($child->getAttribute('class') == 'mnr-c _yE') {
            return self::RULE_MATCH_MATCHED;
        }
        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $doNotRemoveSrsltidForDomain = '')
    {
        $item = [
            'news' => []
        ];
        $xpathCards = "div/div[contains(concat(' ',normalize-space(@class),' '),' card-section ')]";
        $cardNodes = $dom->getXpath()->query($xpathCards, $node);

        foreach ($cardNodes as $cardNode) {
            $item['news'][] = $this->parseItem($dom, $cardNode);
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::IN_THE_NEWS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
    /**
     * @param GoogleDOM $googleDOM
     * @param \DomElement $node
     * @return array
     */
    protected function parseItem(GoogleDom $googleDOM, \DomElement $node)
    {
        $card = [];
        $xpathTitle = "descendant::a[@class = '_Dk']";
        $aTag = $googleDOM->getXpath()->query($xpathTitle, $node)->item(0);
        if ($aTag) {
            $card['title'] = $aTag->nodeValue;
            $card['url'] = \SM_Rank_Service::getUrlFromGoogleTranslate($aTag->getAttribute('href'));
            $card['description'] = function () use ($googleDOM, $node) {
                $span = $googleDOM->getXpath()->query("descendant::span[@class='_dwd st s std']", $node);
                if ($span && $span->length > 0) {
                    return  $span->item(0)->nodeValue;
                }
                return null;
            };
        }
        return new BaseResult('', $card);
    }
}
