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
        if (!empty($class) && strpos($class, 'LQQ1Bd') !== false && $node->getChildren()->count() != 0 && $this->isFlightContent($dom, $node)) {
            return self::RULE_MATCH_MATCHED;
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

    protected function isFlightContent(GoogleDom $dom, $node)
    {
        // Check for non-flight contexts (like SGEWidget checks for non-widget contexts)
        // Move the checks from parse() here, like SGEWidget pattern
        
        // Check if it's inside People Also Ask / Things to Know
        if ($dom->xpathQuery("ancestor::div[contains(concat(' ', normalize-space(@class), ' '), ' bCOlv ')]", $node)->length > 0) {
            return false;
        }
        
        // Check if it's inside accordion expander
        if ($dom->xpathQuery("ancestor::g-accordion-expander", $node)->length > 0) {
            return false;
        }
        
        // Check for flight-specific content
        $textContent = $node->textContent;
        $links = $dom->getXpath()->query('descendant::a', $node);
        
        // Flight-specific URL patterns (simple words)
        $flightUrlPatterns = [
            'google.com/travel/flights',
            'google.com/flights',
            'flight',
            'airfare',
            'airtravel',
            'flightsearch',
            'flightresults',
            'flighttracker',
            'flightstatus',
            'flightbooking',
            'flightschedule',
            'flightfinder',
            'flightcomparison',
            'flightreservation'
        ];
        
        // Looking in URLs with strpos
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            foreach ($flightUrlPatterns as $pattern) {
                if (strpos($href, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        // Flight-specific text patterns (without regex delimiters)
        $flightTextPatterns = [
            '\d+\s*€.*PRIX BAS',  // Price with "PRIX BAS" 
            'flight',
            'airline',
            'airport',
            'plane',
            'boarding pass',
            'boarding gate',
            'airfare',
            'aircraft',
            'airplane',
            'aviation',
            'takeoff',
            'take-off',
            'landing',
            'runway',
            'gate \d',
            'terminal \d',
            'layover',
            'baggage claim',
            'carry-on',
            'IATA',
            'ICAO',
            'pilot',
            'cockpit',
            'cabin crew',
            'flight attendant',
            'jet',
            'jetbridge',
            'jet bridge',
            'tarmac',
            'taxiway',
            'hangar',
            'air traffic',
            'altitude',
            'turbulence',
            'in-flight',
            'inflight',
            'pre-flight',
            'preflight',
            'fly to',
            'flying from',
            'frequent flyer',
            'sky miles',
            'air miles',
            'priority boarding',
            'overhead bin',
            'overhead compartment',
            'safety card',
            'oxygen mask',
            'life vest',
            'flotation device',
            'flight number',
            'flight code',
            'airline code',
            'departure lounge',
            'arrival hall',
            'duty free',
            'duty-free',
            'TSA',
            'security screening',
            'metal detector',
            'body scanner',
            'departure gate',
            'arrival gate',
            'connecting flight',
            'transit area',
            'airside',
            'landside',
            'control tower',
            'ground crew',
            'pushback',
            'de-icing',
            'deicing',
            'jet fuel',
            'wingspan',
            'fuselage',
            'cargo hold'
        ];
        
        // Looking in text content with regex
        foreach ($flightTextPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $textContent)) {
                return true;
            }
        }
        
        // If no strong flight indicators found, it's probably not flight content
        return false;
    }
}
