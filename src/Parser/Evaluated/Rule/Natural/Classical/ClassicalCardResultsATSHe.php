<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeInterface;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;


class ClassicalCardResultsATSHe  implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, DomElement $node)
    {
        $res = $dom->cssQuery('.TzHB6b .sATSHe .qDOt0b', $node);
        // TODO consider removing .ZINbbc (replaced with .O9g5cc in august 2018)

        if ($res->length == 1) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $classicalData = $this->parseNode($dom, $node);

        $resultTypes = [NaturalResultType::CLASSICAL];

        $item = new BaseResult($resultTypes, $classicalData);
        $resultSet->addItem($item);
    }

    protected function parseNode(GoogleDom $dom, DomNodeInterface $node)
    {
        return [
            'title' => function () use ($dom, $node) {
                return $dom
                    ->cssQuery('a.sXtWJb', $node)
                    ->getNodeAt(0)
                    ->getNodeValue();
            },
            'isAmp' => function () use ($dom, $node) {
                return $dom
                        ->cssQuery('.ZseVEf', $node)
                        ->length > 0;
            },
            'url' => function () use ($dom, $node) {
                return $dom
                    ->cssQuery('a.sXtWJb', $node)
                    ->getNodeAt(0)
                    ->getAttribute('href');
            },
            'destination' => function () use ($dom, $node) {
                return $dom
                    ->cssQuery('span.QHTnWc, span.qzEoUe', $node) // TODO ".QHTnWc" appears to be outdated
                    ->getNodeAt(0)
                    ->getNodeValue();
            },
            'description' => function () use ($dom, $node) {
                // TODO remove BC with ".JTuIPc:not(a)>.MUxGbd"
                return $dom
                    ->cssQuery('.JTuIPc:not(a)>.MUxGbd, div.BmP5tf>div.MUxGbd, div.LZ8hH>div.MUxGbd, span.hgKElc', $node)
                    ->getNodeAt(0)
                    ->getNodeValue();
            }
        ];
    }
}
