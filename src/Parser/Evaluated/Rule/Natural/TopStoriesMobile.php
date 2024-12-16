<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class TopStoriesMobile extends TopStories
{
    protected $steps = ['version1', 'version2'];

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if ($node->hasClass('xSoq1')) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->hasClass('lU8tTd')) {

            $whatPeopleAreSayingElement = $dom->getXpath()->query("descendant::div[contains(@class, 'koZ5uc')]", $node);
            if ($whatPeopleAreSayingElement->length > 0) {
                return;
            }

            $perspectivesElement = $dom->getXpath()->query("descendant::div[contains(@class, 'lSfe4c Qxqlrc')]", $node);
            $forContextElement = $dom->getXpath()->query("descendant::div[contains(@class, 'gpjNTe')]", $node);
            // for Context is news
            if (
                $perspectivesElement->length > 0 &&
                $forContextElement->length = 0
            ) {
                return;
            }

            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }
}
