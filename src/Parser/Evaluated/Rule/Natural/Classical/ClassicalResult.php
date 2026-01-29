<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Dom\DomElement;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\AbstractRuleDesktop;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksBig;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksSmall;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ClassicalResult extends AbstractRuleDesktop implements ParsingRuleInterface
{
    protected $gotoDomainLinkCount = 0;

    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->getAttribute('id') == 'rso' || $node->getAttribute('id') == 'botstuff') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    protected function parseNode(GoogleDom $dom, \DomElement $organicResult, IndexedResultSet $resultSet, $k, array $doNotRemoveSrsltidForDomains = [])
    {
        $organicResultObject = $this->parseNodeWithRules($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);

        if ($organicResultObject !== null && $organicResultObject->hasUsedGotoDomainLink()) {
            $this->gotoDomainLinkCount++;
        }

        if( $dom->xpathQuery("descendant::table[@class='jmjoTe']", $organicResult)->length >0) {
            (new SiteLinksBig())->parse($dom, $organicResult, $resultSet, false);
        }

        $parentWithSameClass = $dom->xpathQuery("ancestor::div[@class='g']", $organicResult);


        if($parentWithSameClass->length > 0) {
            if( $dom->xpathQuery("descendant::table[@class='jmjoTe']", $parentWithSameClass->item(0))->length >0) {
                (new SiteLinksBig())->parse($dom, $parentWithSameClass->item(0), $resultSet, false);
            }
        }

        if( $dom->xpathQuery("descendant::div[@class='HiHjCd']", $organicResult)->length >0) {
            (new SiteLinksSmall())->parse($dom, $organicResult, $resultSet, false);
        }

        if($parentWithSameClass->length > 0) {
            if( $dom->xpathQuery("descendant::div[@class='HiHjCd']", $parentWithSameClass->item(0))->length >0) {
                (new SiteLinksBig())->parse($dom, $parentWithSameClass->item(0), $resultSet, false);
            }
        }

        $parentWithClass = $dom->xpathQuery("ancestor::div[@class='BYM4Nd']", $organicResult);

        if($parentWithClass->length > 0) {
            if( $dom->xpathQuery("descendant::table[contains(@class, 'jmjoTe')]", $parentWithClass->item(0))->length >0) {
                (new SiteLinksBig())->parse($dom, $parentWithClass->item(0), $resultSet, false);
            }
        }

        $descendentWithClass = $dom->xpathQuery("descendant::div[@class='BYM4Nd']", $organicResult);

        if($descendentWithClass->length > 0) {
            if( $dom->xpathQuery("descendant::table[contains(@class, 'jmjoTe')]", $descendentWithClass->item(0))->length >0) {
                (new SiteLinksBig())->parse($dom, $descendentWithClass->item(0), $resultSet, false);
            }
        }
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        $this->gotoDomainLinkCount = 0;

        $hardcodedXpath = "descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' g ') or
        (
            (contains(concat(' ', normalize-space(@class), ' '), ' wHYlTd ') or
            contains(concat(' ', normalize-space(@class), ' '), ' vt6azd Ww4FFb ') or
            contains(concat(' ', normalize-space(@class), ' '), ' Ww4FFb vt6azd ')
        ) and
        not(contains(concat(' ', normalize-space(@class), ' '), ' k6t1jb ')) and
        not(contains(concat(' ', normalize-space(@class), ' '), ' jmjoTe '))) or contains(concat(' ', normalize-space(@class), ' '), ' MYVUIe ')]";

        // Calculate hardcoded results if needed (mode 0 or 2)
        $naturalResultsHardcoded = null;
        if ($useDbRules === 0 || $useDbRules === 2) {
            $naturalResultsHardcoded = $dom->xpathQuery($hardcodedXpath, $node);
        }

        // Calculate DB results if needed (mode 1, 2, or 3)
        $naturalResultsDb = null;
        if ($useDbRules === 1 || $useDbRules === 2) {
            $rules = RuleLoaderService::getRulesForFeature('natural_results', false, $additionalRule);
            if (!empty($rules)) {
                $dynamicXpath = implode(' | ', $rules);
                $naturalResultsDb = $dom->xpathQuery($dynamicXpath, $node);
            }
        } elseif ($useDbRules === 3) {
            // Mode 3: Use ONLY the additional rule, ignore all other validated rules
            if ($additionalRule !== null) {
                $rules = RuleLoaderService::getRulesForFeature('natural_results', false, $additionalRule);
                if (!empty($rules)) {
                    // Get ONLY the additional rule (first element - it's prepended by getRulesForFeature)
                    $additionalRuleXpath = reset($rules);
                    $naturalResultsDb = $dom->xpathQuery($additionalRuleXpath, $node);
                }
            }
        }

        // Determine which results to use
        if ($useDbRules === 0) {
            $naturalResults = $naturalResultsHardcoded;
        } elseif ($useDbRules === 1) {
            if ($naturalResultsDb !== null) {
                $naturalResults = $naturalResultsDb;
            } else {
                Logger::error('No DB rules found for natural_results');
                $naturalResults = $dom->xpathQuery($hardcodedXpath, $node);
            }
        } elseif ($useDbRules === 2) {
            // Use hardcoded but compare and log error if mismatch
            $naturalResults = $naturalResultsHardcoded;

            if ($naturalResultsDb !== null && $naturalResultsHardcoded->length !== $naturalResultsDb->length) {
                Logger::error('XPath rule mismatch detected', [
                    'hardcoded_count' => $naturalResultsHardcoded->length,
                    'db_count' => $naturalResultsDb->length,
                    'difference' => abs($naturalResultsHardcoded->length - $naturalResultsDb->length),
                    'additional_rule_id' => $additionalRule
                ]);
            }
        } elseif ($useDbRules === 3) {
            // Mode 3: Use ONLY the additional rule
            if ($naturalResultsDb !== null) {
                $naturalResults = $naturalResultsDb;
            } else {
                Logger::error('No additional rule found or provided for mode 3', [
                    'additional_rule_id' => $additionalRule
                ]);
                // Fallback to hardcoded to prevent empty results
                $naturalResults = $dom->xpathQuery($hardcodedXpath, $node);
            }
        }

        if ($naturalResults->length == 0) {
            if ($node->getAttribute('id') == 'rso') {
                $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, [], $node));
            }

            return;
        }

        $k=0;

        foreach ($naturalResults as $organicResult) {

            if($this->skiResult($dom,$organicResult)) {
                continue;
            }

            $k++;
            $this->parseNode($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);
        }

        if ($this->gotoDomainLinkCount > 0) {
            $this->monolog->notice('Google goto link detected - using base domain link', [
                'device' => 'desktop',
                'count' => $this->gotoDomainLinkCount,
            ]);
        }
    }

    protected function skiResult(GoogleDom $googleDOM, DomElement $organicResult)
    {

        // Organic result is identified as top ads
        if($googleDOM->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tads')]", $organicResult)->length > 0) {
            return true;
        }

        if ($googleDOM->xpathQuery("ancestor::g-accordion-expander ", $organicResult)->length >0) {
            return true;
        }

        if($googleDOM->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tvcap ')]", $organicResult)->length > 0) {
            return true;
        }

        // Recipes are identified as organic result
        if ($organicResult->getChildren()->hasClasses(['rrecc'])) {
            return true;
        }

        // This result is a featured snipped. It it has another div with class g that contains organic results -> avoid duplicates
        if( $organicResult->hasClasses(['mnr-c'])) {
            return true;
        }

        if( $organicResult->hasClasses(['g-blk'])) {
            return true;
        }

        $fsnParent =   $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' xpdopen ')]", $organicResult);

        if ($fsnParent->length > 0) {

            return true;
        }


        // Avoid getting  results from questions (when clicking "Show more". When clicking "Show more" on questions)
        // The result under it looks exactly like a natural results
        $questionParent =   $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' related-question-pair ')]", $organicResult);

        if ($questionParent->length > 0) {

            return true;
        }

        $hasParentG     = $googleDOM->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $organicResult);
        $hasParentFxLDp = $googleDOM->getXpath()->query("ancestor::ul[contains(concat(' ', normalize-space(@class), ' '), ' FxLDp ')]", $organicResult);
        $hasSameChild  = false;

        if ($organicResult->hasClasses(['MYVUIe'])) {
            $hasChildG = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' g ')]", $organicResult);

            if ($hasChildG->length > 0) {
                $hasSameChild = true;
            }
        }

        if ( ($hasParentG->length > 0 && $hasParentFxLDp->length ==0)  || $hasSameChild) {
            return true;
        }

        $hasChildKp = $googleDOM->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' kp-wholepage ')]", $organicResult);

        if ($hasChildKp->length > 0) {
            return true;
        }
        //
        $currencyPlayer = $googleDOM->getXpath()->query('descendant::div[@id="knowledge-currency__updatable-data-column"]', $organicResult);

        if($currencyPlayer->length>0) {
            return true;
        }

        return false;
    }
}
//
