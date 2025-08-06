<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Media\MediaFactory;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleInterface;
use Serps\SearchEngine\Google\NaturalResultType;

class Flights implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;
    private $isNewFlight = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        $class = $node->getAttribute('class');
        
        if (!empty($class) && strpos($class, 'LQQ1Bd') !== false && $node->getChildren()->count() != 0) {
            if ($this->isFlightContent($dom, $node)) {
                return self::RULE_MATCH_MATCHED;
            }
            return self::RULE_MATCH_NOMATCH;
        }

        if (!empty($class) && strpos($class, 'BNeawe DwrKqd') !== false) {
            $this->isNewFlight = true;
            return self::RULE_MATCH_MATCHED;
        }

/*        if ($node->getAttribute('class') == 'IuoSj') {
            return self::RULE_MATCH_MATCHED;
        }*/

        return self::RULE_MATCH_NOMATCH;
    }

    protected function isFlightContent(GoogleDom $dom, $node)
    {
        // Exclude non-flight contexts first
        if ($dom->xpathQuery("ancestor::g-accordion-expander", $node)->length > 0) {
            return false;
        }
        
        if ($dom->xpathQuery("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' bCOlv ')]", $node)->length > 0) {
            return false;
        }
        
        // Check for flight-specific HTML structure patterns
        
        // 1. Look for flight widget containers
        $flightWidgets = $dom->xpathQuery(".//div[contains(@class, 'guN1z') or contains(@class, 'G6oEie')]", $node);
        if ($flightWidgets->length > 0) {
            return true;
        }
        
        // 2. Look for price elements specific to flights
        $priceElements = $dom->xpathQuery(".//div[contains(@class, 'n22NNe') or contains(@class, 'gCNaVb')]", $node);
        if ($priceElements->length > 0) {
            return true;
        }
        
        // 3. Check for Google Flights URLs
        $flightUrls = $dom->xpathQuery(".//a[contains(@href, 'google.com/travel/flights')]", $node);
        if ($flightUrls->length > 0) {
            return true;
        }
        
        // 4. Look for mobile flight indicators
        $mobileFlightData = $dom->xpathQuery(".//*[@data-is-mobile='true']", $node);
        if ($mobileFlightData->length > 0) {
            // Verify it's actually flight content by checking for accompanying flight elements
            $hasFlightElements = $dom->xpathQuery(".//div[contains(@class, 'guN1z') or contains(@class, 'G6oEie')]", $mobileFlightData->item(0));
            if ($hasFlightElements->length > 0) {
                return true;
            }
        }
        
        return false;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        if ($this->isNewFlight) {
            $urls = $dom->getXpath()->query('ancestor::tbody/descendant::a', $node->firstChild);
            $item = [];

            if($urls->length> 0) {
                foreach ($urls as $urlNode) {
                    $item['flights_names'][] = ['name' => $urlNode->firstChild->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($urlNode->getAttribute('href'))];
                }
            }
        } else {
            // For LQQ1Bd elements, we've already validated they contain flight content in match()
            $urls = $dom->getXpath()->query('descendant::a', $node->firstChild);
            $item = [];

            if($urls->length> 0) {
                foreach ($urls as $urlNode) {
                    $item['flights_names'][] = ['name' => $urlNode->firstChild->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($urlNode->getAttribute('href'))];
                }
            }
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
    }
}
