<?php
/**
 * Flight detection utilities
 * Separated into its own class for reusability and testing
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

class FlightDetector
{
    /**
     * Flight-related URL patterns
     */
    protected static $flightUrlPatterns = [
        // Direct flight URLs
        '/flights',
        '/flight',
        '/travel/flights',
        '/travel/flight',
        'google.com/flights',
        'google.com/travel',
        '/travel/explore',
        'flights.google',
        'travel.google',
        
        // Major flight booking sites
        'kayak.com',
        'expedia.com',
        'expedia.co',
        'booking.com/flights',
        'skyscanner',
        'momondo',
        'kiwi.com',
        'cheapflights',
        'tripadvisor.com/Flights',
        'tripadvisor.com/CheapFlights',
        'priceline.com',
        'orbitz.com',
        'travelocity.com',
        'hopper.com',
        'trip.com',
        'agoda.com/flights',
        'opodo.com',
        'edreams.com',
        'gotogate.com',
        'cheapoair.com',
        'onetravel.com',
        'justfly.com',
        'airfarewatchdog.com',
        'scottscheapflights.com',
        'secretflying.com',
        'theflightdeal.com',
        'farecompare.com',
        'flightcentre.com',
        'flightcenter.com',
        'sta-travel.com',
        'studentuniverse.com',
        
        // Airline direct booking indicators
        '/book',
        '/booking',
        '/reservations',
        '/flight-search',
        '/air-booking',
        '/flight-booking',
        '/flights-search',
        'flightsearch',
        'flightresults',
        'air-travel',
        '/airfare',
        '/cheap-flights',
        '/last-minute-flights',
        '/flight-deals',
        
        // API and widget patterns
        'flightstats.com',
        'flightaware.com',
        'flightradar24.com',
        '/flight-tracker',
        '/flight-status',
        
        // Travel agency patterns
        'flights.lastminute.com',
        'travelsupermarket.com/flights',
        'iatatravelcentre.com'
    ];
    
    /**
     * Flight-related text patterns (airlines, flight terms, etc.)
     */
    protected static $flightTextPatterns = [
        // Flight-specific terms
        'flight', 'flights', 'fly', 'flying', 'flew', 'flown',
        'airplane', 'aircraft', 'plane', 'jet', 'aviation',
        'nonstop', 'non-stop', 'direct flight', 'connecting flight',
        'round trip', 'roundtrip', 'round-trip', 'one way', 'oneway', 'one-way',
        'return flight', 'outbound', 'inbound', 'multi-city', 'open-jaw',
        
        // Airport and travel terms
        'departure', 'departing', 'arrival', 'arriving', 
        'takeoff', 'take-off', 'landing', 'taxi', 'runway',
        'airport', 'terminal', 'gate', 'concourse', 'tarmac',
        'check-in', 'checkin', 'boarding', 'boarding pass', 'boarding time',
        'layover', 'stopover', 'connection', 'transit', 'transfer',
        'customs', 'immigration', 'security check', 'TSA', 'TSA precheck',
        
        // Cabin classes
        'economy', 'economy class', 'premium economy', 'premium eco',
        'business', 'business class', 'first class', 'first',
        'basic economy', 'main cabin', 'comfort+', 'economy plus',
        
        // Baggage terms
        'baggage', 'luggage', 'carry-on', 'carry on', 'carryon',
        'checked bag', 'checked baggage', 'hand luggage', 'cabin bag',
        'baggage allowance', 'excess baggage', 'lost luggage',
        'overhead bin', 'baggage claim', 'baggage carousel',
        
        // Booking and pricing terms
        'book flight', 'book flights', 'flight booking', 'reservation',
        'flight deals', 'cheap flights', 'discount flights', 'last minute flights',
        'flight prices', 'airfare', 'fare', 'ticket price',
        'flight search', 'compare flights', 'flight comparison',
        'flight schedule', 'timetable', 'flight status', 'flight tracker',
        'miles', 'frequent flyer', 'loyalty program', 'upgrade',
        'seat selection', 'seat assignment', 'exit row', 'aisle seat', 'window seat',
        
        // Time patterns that might indicate flight schedules
        'hr ', 'hrs ', 'hour ', 'hours ',
        'min ', 'mins ', 'minute ', 'minutes ',
        'duration', 'flight time', 'travel time',
        'red-eye', 'overnight flight', 'morning flight', 'evening flight',
        
        // Flight status terms
        'delayed', 'cancelled', 'on time', 'departed', 'landed',
        'scheduled', 'estimated', 'actual', 'diverted', 'gate change',
        'now boarding', 'final call', 'pre-boarding',
        
        // Aircraft types (common ones mentioned in flight searches)
        'Boeing', 'Airbus', 'A320', 'A321', 'A330', 'A350', 'A380',
        '737', '747', '757', '767', '777', '787', 'Dreamliner',
        'Embraer', 'Bombardier', 'ATR', 'turboprop', 'widebody', 'narrowbody',
        
        // General airline terms
        'airline', 'airlines', 'airways', 'air lines', 'carrier',
        'low-cost carrier', 'budget airline', 'flag carrier', 'charter flight',
        'codeshare', 'alliance', 'Star Alliance', 'OneWorld', 'SkyTeam'
    ];
    
    /**
     * Check if text contains flight-related content
     * 
     * @param string $textContent The text to check
     * @param array $urls Optional array of URLs to check
     * @return bool
     */
    public static function isFlightContent($textContent, array $urls = [])
    {
        $textContentLower = strtolower($textContent);
        
        // Check text patterns
        if (self::containsFlightTextPatterns($textContentLower)) {
            return true;
        }
        
        // Check URLs if provided
        foreach ($urls as $url) {
            if (self::isFlightUrl($url)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if text contains flight text patterns
     * 
     * @param string $text
     * @return bool
     */
    public static function containsFlightTextPatterns($text)
    {
        $textLower = strtolower($text);
        
        foreach (self::$flightTextPatterns as $pattern) {
            if (self::matchesPattern($textLower, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if URL is flight-related
     * 
     * @param string $url
     * @return bool
     */
    public static function isFlightUrl($url)
    {
        $urlLower = strtolower($url);
        
        foreach (self::$flightUrlPatterns as $urlPattern) {
            if (strpos($urlLower, strtolower($urlPattern)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Match pattern with word boundaries for short patterns
     * 
     * @param string $haystack
     * @param string $pattern
     * @return bool
     */
    protected static function matchesPattern($haystack, $pattern)
    {
        // For short patterns (3 chars or less), use word boundary regex
        if (strlen($pattern) <= 3) {
            return (bool)preg_match('/\b' . preg_quote(strtolower($pattern), '/') . '\b/i', $haystack);
        } else {
            // For longer patterns, use simple string matching
            return stripos($haystack, strtolower($pattern)) !== false;
        }
    }
    
    /**
     * Add custom flight patterns (for extensibility)
     * 
     * @param array $textPatterns
     * @param array $urlPatterns
     */
    public static function addPatterns(array $textPatterns = [], array $urlPatterns = [])
    {
        self::$flightTextPatterns = array_merge(self::$flightTextPatterns, $textPatterns);
        self::$flightUrlPatterns = array_merge(self::$flightUrlPatterns, $urlPatterns);
    }
    
    /**
     * Get current patterns (for testing/debugging)
     * 
     * @return array
     */
    public static function getPatterns()
    {
        return [
            'text' => self::$flightTextPatterns,
            'urls' => self::$flightUrlPatterns
        ];
    }
}