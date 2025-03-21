<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Media\MediaFactory;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class ImageGroupCarousel implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($dom->cssQuery('._ekh image-viewer-group g-scrolling-carousel', $node)->length == 1) {
            return self::RULE_MATCH_MATCHED;
        } else {
            return self::RULE_MATCH_NOMATCH;
        }
    }
    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $item = [
            'images' => function () use ($node, $dom) {
                $items = [];

                $imageNodes = $dom->cssQuery('.rg_ul>._sqh g-inner-card', $node);
                foreach ($imageNodes as $imageNode) {
                    $items[] = $this->parseItem($dom, $imageNode);
                }

                return $items;
            },
            'isCarousel' => true,
            'moreUrl' => function () use ($node, $dom) {
                $a = $dom->cssQuery('g-tray-header ._Nbi a');
                $a = $a->item(0);
                if ($a instanceof \DOMElement) {
                    return $dom->getUrl()->resolveAsString($a->getAttribute('href'));
                }
                return null;
            }
        ];

        $resultSet->addItem(new BaseResult(NaturalResultType::IMAGE_GROUP, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
    /**
     * @param GoogleDOM $googleDOM
     * @param \DOMElement $imgNode
     * @return array
     *
     */
    private function parseItem(GoogleDom $googleDOM, \DOMElement $imgNode)
    {
        $data =  [
            'sourceUrl' => function () use ($imgNode, $googleDOM) {
                $node = $googleDOM->cssQuery('.rg_meta', $imgNode)->item(0);
                if (!$node) {
                    return null;
                }
                $url = $googleDOM->getJsonNodeProperty('ru', $node);
                return $url;
            },
            'targetUrl' => function () use ($imgNode, $googleDOM) {
                // not available for mobile results
                return null;
            },
            'image' => function () use ($imgNode, $googleDOM) {
                // TODO: maybe parse from javascript source
                $img = $googleDOM->cssquery('.iuth>img')->item(0);
                if (!$img) {
                    return null;
                }
                return MediaFactory::createMediaFromSrc($img->getattribute('src'));
            },
        ];

        return new BaseResult(NaturalResultType::IMAGE_GROUP_IMAGE, $data);
    }
}
