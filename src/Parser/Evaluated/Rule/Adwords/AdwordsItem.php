<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Adwords;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\AdwordsResultType;
use Serps\Core\Dom\Css;
use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class AdwordsItem implements ParsingRuleInterface
{

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'ads-ad') {
            return self::RULE_MATCH_MATCHED;
        }
        return self::RULE_MATCH_NOMATCH;
    }
    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $doNotRemoveSrsltidForDomain = '')
    {
        $item = [
            'title' => function () use ($dom, $node) {
                $aTag = $dom->getXpath()->query('descendant::h3/a[2]', $node)->item(0);
                if (!$aTag) {
                    $aTag = $dom->getXpath()->query('descendant::h3', $node)->item(0);
                    if (!$aTag) {
                        return null;
                    }
                }
                return $aTag->nodeValue;
            },
            'url' => function () use ($node, $dom) {
                $aTag = $dom->getXpath()->query('descendant::h3/a[2]', $node)->item(0); // TODO remove
                if (!$aTag) {
                    $aTag = $dom->cssQuery('a', $node)->item(0);
                    if (!$aTag) {
                        throw new InvalidDOMException('Cannot find ads anchor');
                    }
                }

                return $dom->getUrl()->resolveAsString($aTag->getAttribute('href'));
            },
            'visurl' => function () use ($node, $dom) {
                $aTag = $dom->getXpath()->query(
                    Css::toXPath('div.ads-visurl>cite'),
                    $node
                )->item(0);

                if (!$aTag) {
                    return null;
                }
                return $aTag->nodeValue;
            },
            'description' => function () use ($node, $dom) {
                $aTag = $dom->getXpath()->query(
                    Css::toXPath('div.ads-creative'),
                    $node
                )->item(0);

                if (!$aTag) {
                    return null;
                }
                return $aTag->nodeValue;
            },
        ];

        $resultSet->addItem(new BaseResult(AdwordsResultType::AD, $item));
    }
}
