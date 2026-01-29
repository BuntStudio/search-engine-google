<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Serps\Core\Dom\DomElement;
use Serps\Core\Dom\DomNodeList;
use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\AbstractRuleMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SiteLinksBigMobile;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use SM\Backend\SerpParser\RuleLoaderService;
use SM\Backend\Log\Logger;

class ClassicalResultMobile extends AbstractRuleMobile implements ParsingRuleInterface
{
    protected $resultType = NaturalResultType::CLASSICAL_MOBILE;
    protected $gotoDomainLinkCount = 0;

    public function match(GoogleDom $dom, DomElement $node)
    {
        if ($node->getAttribute('id') == 'center_col' || $node->getAttribute('id') =='sports-app') {
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

        if ($dom->xpathQuery("descendant::div[@class='MUxGbd v0nnCb lyLwlc']",
                $organicResult->parentNode->parentNode)->length > 0) {
            (new SiteLinksBigMobile())->parse($dom, $organicResult->parentNode->parentNode, $resultSet, false, $doNotRemoveSrsltidForDomains);
        }


        if (
            $dom->xpathQuery(
                "descendant::form[@class='xBIiEf']",
                $organicResult->parentNode->parentNode->parentNode
            )->length > 0 &&
            $organicResult->parentNode->parentNode->parentNode->getAttribute('class') === 'BYM4Nd'
        ) {
            (new SiteLinksBigMobile())->parse($dom, $organicResult->parentNode->parentNode->parentNode, $resultSet, false);
        }
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        $this->gotoDomainLinkCount = 0;

        $hardcodedXpath = "descendant::
                    div[
                        contains(concat(' ', normalize-space(@class), ' '), ' mnr-c ') or
                        contains(concat(' ', normalize-space(@class), ' '), ' xpd EtOod ') or
                        contains(concat(' ', normalize-space(@class), ' '), ' svwwZ ') or
                        contains(concat(' ', normalize-space(@class), ' '), 'UDZeY fAgajc') or
                        (contains(concat(' ', normalize-space(@class), ' '), 'Ww4FFb') and
                        contains(concat(' ', normalize-space(@class), ' '), 'vt6azd')) or
                        (
                            contains(concat(' ', normalize-space(@class), ' '), 'kp-wholepage') and
                            contains(concat(' ', normalize-space(@class), ' '), 'kp-wholepage-osrp')
                        )
                        ] |
                    //a[
                        contains(concat(' ', normalize-space(@class), ' '), 'zwqzjb')
                    ]";

        // Calculate hardcoded results if needed (mode 0 or 2)
        $naturalResultsHardcoded = null;
        if ($useDbRules === 0 || $useDbRules === 2) {
            $naturalResultsHardcoded = $dom->xpathQuery($hardcodedXpath, $node);
        }

        // Calculate DB results if needed (mode 1, 2, or 3)
        $naturalResultsDb = null;
        if ($useDbRules === 1 || $useDbRules === 2) {
            $rules = RuleLoaderService::getRulesForFeature('natural_results_mobile', false, $additionalRule);
            if (!empty($rules)) {
                $dynamicXpath = implode(' | ', $rules);
                $naturalResultsDb = $dom->xpathQuery($dynamicXpath, $node);
            }
        } elseif ($useDbRules === 3) {
            // Mode 3: Use ONLY the additional rule, ignore all other validated rules
            if ($additionalRule !== null) {
                $rules = RuleLoaderService::getRulesForFeature('natural_results_mobile', false, $additionalRule);
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
                Logger::error('No DB rules found for natural_results_mobile');
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
            $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, [], $node));
            $this->monolog->error('Cannot identify results in html page', ['class' => self::class]);

            return;
        }

        $k = 0;

        foreach ($naturalResults as $organicResult) {

            if ($this->skiResult($dom, $organicResult)) {
                continue;
            }

            try {
                $k++;
                $this->parseNode($dom, $organicResult, $resultSet, $k, $doNotRemoveSrsltidForDomains);
            } catch (\Exception $exception) {

                // If first position detected with classical class it's not a results, do not decrement position
                if ($k > 1) {
                    $k--;
                }

                continue;
            }
        }

        if ($this->gotoDomainLinkCount > 0) {
            $this->monolog->notice('Google goto link detected - using base domain link', [
                'device' => 'mobile',
                'count' => $this->gotoDomainLinkCount,
            ]);
        }
    }

    protected function skiResult(GoogleDom $dom, DomElement $organicResult)
    {
        // Organic result is identified as top ads
        if($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tads')]", $organicResult)->length > 0) {
            return true;
        }

        if($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), ' tvcap ')]", $organicResult)->length > 0) {
            return true;
        }

        // Recipes are identified as organic result
        if ($organicResult->getChildren()->hasClasses(['Q9mvUc'])) {
            return true;
        }

        if($dom->xpathQuery("ancestor::*[contains(concat(' ', normalize-space(@id), ' '), '  mnr-c ')]", $organicResult)->length > 0) {
            return true;
        }
        if($dom->xpathQuery("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' xpd EtOod ')]", $organicResult)->length > 0) {
            return true;
        }
        if($dom->xpathQuery("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' zwqzjb ')]", $organicResult)->length > 0) {
            return true;
        }

        if($dom->xpathQuery("ancestor::div[@id='HbKV2c']", $organicResult)->length > 0) {
            //id related to ads
            return true;
        }

        if($dom->xpathQuery("ancestor::div[@data-text-ad]", $organicResult)->length > 0) {
            //ad result
            return true;
        }



        if($dom->xpathQuery("descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' zwqzjb ')]", $organicResult)->length > 0) {
            return true;
        }
        if ($organicResult->hasClass('zwqzjb') && $dom->xpathQuery("ancestor::g-expandable-container", $organicResult)->length > 0 ) {
            return true;
        }
        // Inside div with class= 'mnr-c xpd O9g5cc uUPGi' are more divs with 'mnr-c xpd O9g5cc uUPGi'
        // Should ignore from processing parent result and process only children and avoid duplicate results
        if($dom->xpathQuery("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' mnr-c ')]", $organicResult)->length >0) {
            return true;
        }

        // Ignore maps from results
        if($dom->xpathQuery("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' z3HNkc ')]", $organicResult)->length >0) {
            return true;
        }

        $questionParent =   $dom->getXpath()->query("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' related-question-pair ')]", $organicResult);

        if ($questionParent->length > 0) {

            return true;
        }

        // Avoid getting  results from questions (when clicking "Show more". When clicking "Show more" on questions)
        // The result under it looks exactly like a natural results
        if(
            $organicResult->parentNode->parentNode->parentNode->getAttribute('class') =='ymu2Hb' ||
            $organicResult->parentNode->parentNode->parentNode->getAttribute('class') =='dfiEbb' ||
            $organicResult->parentNode->parentNode->parentNode->parentNode->getAttribute('class') =='ymu2Hb') {

            return true;
        }

        // The organic result identified as "Find results on"

        $carouselNode = $dom->xpathQuery("descendant::g-scrolling-carousel", $organicResult);
        if ($carouselNode->length > 0 &&
            $dom->xpathQuery("descendant::g-inner-card", $organicResult)->length > 0) {

            // If the  direct parent of the carousel is the class from classical results -> meaning that there is no classical result here to be parsed.
            // If the  direct parent of the carousel is NOT the class from classical results -> this is a classical result and under it is a carousel. need to parse the node and identify title/url/description
            if(preg_match('/mnr\-c/', $carouselNode->item(0)->parentNode->getAttribute('class'))) {
                return true;
            }
        }

        // Result has carousel in it
        if ($dom->xpathQuery("descendant::g-scrolling-carousel", $organicResult)->length > 0 &&
            $dom->xpathQuery("descendant::svg", $organicResult)->length > 0 &&
            (  // And carousel have title like "Results in "
                $dom->getXpath()->query("descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' pxp6I MUxGbd ')]", $organicResult)->length > 0 ||
                // Temporary keep this
                $dom->getXpath()->query("descendant::table", $organicResult)->length > 0
            )) {
            return true;
        }

        // Avoid getting results  such as "people also ask" near a regular result; (it's not a "people also ask" but the functionality is exactly like "people also ask")
        // It's like an expander with click on a main text. The results under it looks like a regular classical result
        if( !empty($organicResult->firstChild) &&
            !$organicResult->firstChild instanceof \DOMText &&
            $organicResult->firstChild->getAttribute('class') =='g card-section') {

            return true;
        }

        return false;
    }
}
