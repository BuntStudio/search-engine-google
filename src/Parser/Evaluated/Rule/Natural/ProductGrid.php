<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class ProductGrid implements ParsingRuleInterface
{
    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->getAttribute('data-enable-product-traversal') == true) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile=false)
    {
        $resultSet->addItem(
            new BaseResult(NaturalResultType::PRODUCT_GRID, []/*todo*/, $node)
        );
    }
}
