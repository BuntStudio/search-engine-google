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
        
        // LQQ1Bd is a generic "show more" class - we need to check if this is actually flights
        // by looking for flight-specific content or attributes
        if (!empty($class) && strpos($class, 'LQQ1Bd') !== false && $node->getChildren()->count() != 0) {
            // Get text content
            $textContent = $node->textContent;
            
            // Collect URLs from links
            $urls = [];
            $links = $dom->getXpath()->query('descendant::a', $node);
            if ($links->length > 0) {
                foreach ($links as $link) {
                    $urls[] = $link->getAttribute('href');
                    // Also check link text as part of the content
                    $textContent .= ' ' . $link->textContent;
                }
            }
            
            // Use FlightDetector to check if this is flight-related content
            if (FlightDetector::isFlightContent($textContent, $urls)) {
                return self::RULE_MATCH_MATCHED;
            }
            
            // If no flight-specific content found, this is likely a different "show more" element
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
            if ($dom->xpathQuery("ancestor::g-accordion-expander", $node)->length > 0) {
                return false;
            }

            //bCOlv - this is a kowledge used in things to know/people also ask. these are not flights results
            if (
                $dom->xpathQuery("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' bCOlv ')]", $node)->length > 0
            ) {
                return false;
            }

            $urls = $dom->getXpath()->query('descendant::a', $node->firstChild);
            $item = [];

            if($urls->length> 0) {
                foreach ($urls as $urlNode) {
                    $item['flights_names'][] = ['name' => $urlNode->firstChild->textContent, 'url' => \SM_Rank_Service::getUrlFromGoogleTranslate($urlNode->getAttribute('href'))];
                }
            }
        }

        $resultSet->addItem(new BaseResult(NaturalResultType::FLIGHTS, $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));    }
}
