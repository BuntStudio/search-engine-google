<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\Versions;

use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;

class SerpFeaturesVersions implements ParsingRuleInterface
{
    protected $steps = [];

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, string $onlyRemoveSrsltidForDomain = '')
    {
        if(empty($this->steps)) {
            return;
        }

        foreach ($this->steps as $functionName) {
            call_user_func_array([$this, $functionName], [$dom, $node, $resultSet, $isMobile]);
        }
    }

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node){

    }
}
