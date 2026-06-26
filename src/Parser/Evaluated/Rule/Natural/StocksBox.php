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
        // The stocks box is a `wDYxhc` container that wraps the stock-price quote
        // (`data-attrid='Price'`). Require the price node: the bare class gate over-matches
        // other `wDYxhc` web-answer blocks, and Google dropped the `data-attrid='Company Name'`
        // span that parse() used to disambiguate, so the price quote is the reliable, unique
        // (one per box) anchor.
        if ($node->getAttribute('class') == 'wDYxhc'
            && $dom->getXpath()->query("descendant::*[@data-attrid='Price']", $node)->length > 0) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        // Google replaced the old `data-attrid='Company Name'` span with the finance
        // stock-price module, so that selector matches nothing on current SERPs. Anchor
        // extraction on the unique per-box stock-price quote (`data-attrid='Price'`); its
        // presence is what makes this a stocks box.
        $companyNameNode = $dom->getXpath()->query("descendant::*[@data-attrid='Price']", $node)->item(0);
        if (!empty($companyNameNode)) {
            $companyName = trim($companyNameNode->textContent);
            $resultSet->addItem(new BaseResult(NaturalResultType::STOCKS_BOX, [$companyName], $node, $this->hasSerpFeaturePosition));
        }
    }
}
