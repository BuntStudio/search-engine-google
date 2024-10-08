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

class ImageGroup implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('id') == 'iur' &&
            (   // Mobile
                $node->parentNode->hasAttribute('jsmodel') ||
                // Desktop
                $node->parentNode->parentNode->hasAttribute('jsmodel')  ||
                // New Mobile
                $node->parentNode->parentNode->parentNode->parentNode->parentNode->hasAttribute('jsmodel')
            )
        ) {
            return self::RULE_MATCH_MATCHED;
        }

        if (strpos($node->getAttribute('class'), 'IZE3Td') !== false) {
            $child = $node;
            for ($i = 0; $i < 5; $i++) {
                $child = $child->getChildren()->item(0);
                if (empty($child)) {
                    return self::RULE_MATCH_NOMATCH;
                }
            }
            if ($child->getAttribute('data-attrid') == 'images universal') {
                return self::RULE_MATCH_MATCHED;
            }
        }

        if ($node->getAttribute('data-attrid') == 'images universal') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $images = $googleDOM->getXpath()->query('descendant::div[@data-lpage]', $node);
        $item   = [];

        if ($images->length == 0) {
            //TODO FIX THIS
            $images = $googleDOM->getXpath()->query('ancestor::div[contains(concat(" ", @class, " "), " MjjYud ")]/descendant::div[@data-lpage]', $node);
        }

        if ($images->length > 0) {
            foreach ($images as $imageNode) {
                $item['images'][] = ['url'=>$this->parseItem( $imageNode)];
            }
        }

        $resultSet->addItem(
            new BaseResult($this->getType($isMobile), $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition)
        );
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::IMAGE_GROUP_MOBILE : NaturalResultType::IMAGE_GROUP;
    }

    /**
     * @param \DOMElement $imgNode
     *
     * @return string
     */
    private function parseItem( \DOMElement $imgNode)
    {
        return $imgNode->getAttribute('data-lpage');
    }
}
