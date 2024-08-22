<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated;

use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\AbstractParser;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\AdsTop;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\ClassicalResult;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\CurrencyAnswer;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Definitions;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Directions;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\FeaturedSnipped;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Flight;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\FlightAirlineOptions;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Flights;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\FlightsAirline;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\FlightsSites;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\HighlyLocalized;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Hotels;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ImageGroup;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Jobs;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\KnowledgeGraph;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Maps;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\MapsCoords;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Misspelling;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\NoMoreResults;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Places;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\PlacesSites;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ProductGrid;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SGEButton;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SGEWidget;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Sites;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ProductListing;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Questions;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Recipes;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\RelatedSearches;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ResultsNo;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\StocksBox;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\TopSights;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\TopStories;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Videos;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VideoCarousel;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VisualDigest;

/**
 * Parses natural results from a google SERP
 */
class NaturalParser extends AbstractParser
{
    protected $isMobile = false;

    /**
     * @inheritdoc
     */
    protected function generateRules()
    {
        return [
            new ClassicalResult($this->logger),
            new ImageGroup(),
            new Videos(),
            new Maps(),
            new Flight(),
            new KnowledgeGraph(),
            new AdsTop(),
            new Recipes(),
            new TopStories(),
            new FeaturedSnipped(),
            new ProductGrid(),
            new ProductListing(),
            new Questions(),
            new Hotels(),
            new Definitions(),
            new Flights(),
            new Jobs(),
            new ResultsNo(),
            new Directions(),
            new MapsCoords(),
            new Misspelling(),
            new VideoCarousel(),
            new NoMoreResults(),
            new VisualDigest(),
            new HighlyLocalized(),
            new RelatedSearches(),
            new Sites(),
            new FlightAirlineOptions(),
            new SGEButton(),
            new SGEWidget(),
            new Places(), //done
            new PlacesSites(), //done
            new TopSights(), //done
            new StocksBox(), //done
            new FlightsSites(), //to be done seems similar - FlightAirlineOptions
            new FlightsAirline(), // to be done seems similar - Flights
            new CurrencyAnswer() //done
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getParsableItems(GoogleDom $googleDom)
    {
        // [@id='rso'] = results in position
        // [@id='rhs']  = knowledge graph
        // [@id='iur'] = images
        // [@id='tads']  = ads top
        // [@id='tadsb']  = ads bottom
        // [@id='tvcap']  = ads top carousel
        // [@id='extabar']  =app pack
        // [@class='C7r6Ue'] VT5Tde Qq3Lb WVGKWb = maps
        // [@id='Odp5De'] - maps
        // [@class='xpdopen']  = features snipped/position zero
        // [@class='e4xoPb']  = videos
        // [contains(@class, 'commercial-unit-desktop-top')]  = product listing
        // [contains(@data-enable-product-traversal, 'true')]  = product listing
        // [contains(@class, 'cu-container')]  = product listing on right, like ads
        // [contains(@class, 'related-question-pair')]  = questions
        // [contains(@class, 'gws-plugins-horizon-jobs__li-ed')]  = jobs
        // [contains(@class, 'L5NwLd')]  = jobs
        // [@class='xpdopen']  = features snipped/position zero
        //   @jsname='MGJTwe'   = recipes
        // //*[g-section-with-header[@class='yG4QQe TBC9ub']]]/child::* = top stories
        // [@id='kp-wp-tab-cont-Latest'] = top stories
        //div[@class='CH6Bmd']/div[@class='ntKMYc P2hV9e'] = hotels
        //div[@class='zaTIWc'] - new hotels desktop
        //@class='lr_container yc7KLc mBNN3d' - definitions
        // [contains(@class, 'LQQ1Bd')] - flights
        // [@class='BNeawe DwrKqd'] - new flights
        //@id = 'oFNiHe' - misspelings
        //@id = 'result-stats' - no of results
        //@id = 'lud-ed' - directions
        //return $googleDom->xpathQuery("//*[@id='result-stats']/*[not(self::script) and not(self::style)]/*");
        //@class = 'H93uF' - coords
         //@class = 'e8Ck0d SS4zp' //VisualDigest
        //@id= 'bres' -> related searches
        //@id= 'rcnt' or -> places
        //@class= 'EyBnad' or -> places sites
        //@class= 'aviV4d' or -> stocks box
        //@class= 'ULSxyf' or -> things to know
        //@class= 'RyIFgf' or -> top sights
        //@class= 'osrp-blk' or -> visual digest mobile
        //@class= 'ULSxyf' or -> currency answer
        //@class = 'zJUuqf' // sites
        //@jscontroller = 'hKbgK' // flight airline options
        return $googleDom->xpathQuery("//*[
            @id='rso' or
            @id='botstuff' or
            @id='rhs' or
            @id='iur' or
            @id='tads' or
            @id='tadsb' or
            @id='tvcap' or
            @id='extabar' or
            @jsname='MGJTwe' or
            @class='C7r6Ue' or
            @id='Odp5De' or
            @class='e4xoPb' or
            @class='WVGKWb' or
            @class='Qq3Lb' or
            @class='xpdopen' or
            contains(@class, 'lr_container yc7KLc mBNN3d') or
            contains(@class, 'LQQ1Bd') or
            @class='BNeawe DwrKqd' or
            contains(@class, 'CH6Bmd') or
            contains(@class, 'zaTIWc') or
            contains(@class, 'SuIj2') or
            contains(@class, 'VT5Tde') or
            contains(@class, 'commercial-unit-desktop-top') or
            contains(@data-enable-product-traversal, 'true') or
            contains(@class, 'cu-container') or
            contains(@class, 'related-question-pair') or
            contains(@class, 'gws-plugins-horizon-jobs__li-ed') or
            contains(@class, 'L5NwLd') or
            g-section-with-header[@class='yG4QQe TBC9ub'] or
            @id='kp-wp-tab-cont-Latest' or
            @id = 'oFNiHe' or
            @id='result-stats' or
            @id='kp-wp-tab-Latest' or
            @id = 'lud-ed' or
            @class = 'H93uF' or
            contains(@class, 'e8Ck0d') or
            video-voyager or
            @id= 'ofr' or
            @class = 'vqkKIe wHYlTd' or
            @id= 'bres' or
            @id= 'rcnt' or
            @class= 'EyBnad' or
            @class= 'aviV4d' or
            @class= 'ULSxyf' or
            @class= 'RyIFgf' or
            @class= 'osrp-blk' or
            @class= 'ULSxyf' or
            contains(@class, 'zJUuqf') or
            @jscontroller='hKbgK' or
            @id='eKIzJc' or
            @jsname='ZLxsqf'
        ][not(self::script) and not(self::style)]");
    }
}

