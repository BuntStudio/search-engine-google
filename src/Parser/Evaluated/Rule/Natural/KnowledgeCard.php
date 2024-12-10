<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class KnowledgeCard implements ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->hasClass('mnr-c') && $node->hasClass('kno-kp')) {
            return self::RULE_MATCH_MATCHED;
        }
        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $doNotRemoveSrsltidForDomain = '')
    {

        $data = [
            'title' => function () use ($dom, $node) {
                $item = $dom->cssQuery('._OKe ._Q1n ._sdf');

                if (!$item->length) {
                    $item = $dom->cssQuery('.d1rFIf>.kno-ecr-pt>span');
                }

                return $item->getNodeAt(0)->getNodeValue();
            },
            'shortDescription' => function () use ($dom, $node) {
                $item = $dom->cssQuery('._OKe ._Q1n ._gdf', $node); // appears to be outdated

                if (!$item->length) {
                    $item = $dom->cssQuery('.sthby', $node);
                }

                return $item->getNodeAt(0)->getNodeValue();
            }
        ];

        $resultSet->addItem($a = new BaseResult(NaturalResultType::KNOWLEDGE, $data, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
