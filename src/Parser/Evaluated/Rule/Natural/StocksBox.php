<?php


namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;

class StocksBox implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    public $hasSerpFeaturePosition = true;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->getAttribute('class') == 'wDYxhc') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $googleDOM, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false)
    {
        $companyNameNode =  $googleDOM->getXpath()->query('descendant::span[contains(concat(\' \', normalize-space(@data-attrid), \' \'), \' Company Name \')]', $node)->item(0);
        if (!empty($companyNameNode)) {
            $companyName = $companyNameNode->textContent;
            $resultSet->addItem(new BaseResult(NaturalResultType::STOCKS_BOX, [$companyName], $node, $this->hasSerpFeaturePosition));
        }
    }
}
