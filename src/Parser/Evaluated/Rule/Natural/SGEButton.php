<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;

class SGEButton implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = false;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node, $useDbRules = 0)
    {
        if ($useDbRules > 0) {
            $matchRules = array_unique(array_merge(
                RuleLoaderService::getRulesForFeature('sge_widget_match'),
                RuleLoaderService::getRulesForFeature('sge_widget_mobile_match')
            ));

            if (!empty($matchRules)) {
                $matchXpath = implode(' | ', $matchRules);
                $matchResult = $dom->getXpath()->query($matchXpath, $node);
                if ($matchResult->length > 0) {
                    return $this->isButton($dom, $node, $useDbRules) ? self::RULE_MATCH_MATCHED : self::RULE_MATCH_NOMATCH;
                }
                return self::RULE_MATCH_NOMATCH;
            }
            // No DB rules — fall through to hardcoded
        }

        if ($node->getAttribute('jsname') == 'ZLxsqf' && $this->isButton($dom, $node, $useDbRules)) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('id') =='eKIzJc' && $this->isButton($dom, $node, $useDbRules)) {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function getType($isMobile)
    {
        return $isMobile ? NaturalResultType::SGE_BUTTON_MOBILE : NaturalResultType::SGE_BUTTON;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        if (!empty($resultSet->getResultsByType($this->getType($isMobile))->getItems())) {
            return;
        }
        $resultSet->addItem(new BaseResult($this->getType($isMobile), [], $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }

    protected function isButton(GoogleDom $dom, $node, $useDbRules = 0)
    {
        if ($useDbRules > 0) {
            $buttonRules = array_unique(array_merge(
                RuleLoaderService::getRulesForFeature('sge_widget_button_detection'),
                RuleLoaderService::getRulesForFeature('sge_widget_mobile_button_detection')
            ));

            if (!empty($buttonRules)) {
                $buttonXpath = implode(' | ', $buttonRules);
                $generateButton = $dom->xpathQuery($buttonXpath, $node);
                return $generateButton->length > 0;
            }
            // No DB rules — fall through to hardcoded
        }

        $generateButton = $dom->xpathQuery('descendant::div[@jsname="B76aWe"]', $node);
        return $generateButton->length > 0;
    }
}
