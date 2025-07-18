<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated;

use Monolog\Logger;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\AbstractParser;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\AdsTopMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\AppPackMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\ClassicalResultMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical\ClassicalResultMobileV2;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\DefinitionsMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\DirectionsMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\FeaturedSnipped;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Flights;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\HotelsMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ImageGroup;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Jobs;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\KnowledgeGraphMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\MapsMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\MisspellingMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\NoMoreResults;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\PeopleAlsoAsk;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ProductGrid;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ProductListing;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\ProductListingMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Questions;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Recipes;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SGEButton;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\SGEWidget;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\TopStoriesMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VideoCarouselMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VideosMobile;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VisualDigest;
use Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\VisualDigestMobile;

/**
 * Parses natural results from a mobile google SERP
 */
class MobileNaturalParser extends AbstractParser
{

    protected $isMobile = true;

    /**
     * @inheritdoc
     */
    protected function generateRules()
    {
        return [
            new ClassicalResultMobile($this->logger),
            new ClassicalResultMobileV2($this->logger),
            new ImageGroup(),
            new MapsMobile(),
            new Questions(),
            new TopStoriesMobile(),
            new ProductGrid(),
            new ProductListingMobile(),
            new KnowledgeGraphMobile(),
            new AdsTopMobile(),
            new AppPackMobile(),
            new Recipes(),
            new Flights(),
            new Jobs(),
            new HotelsMobile(),
            new DefinitionsMobile(),
            new VideosMobile(),
            new MisspellingMobile(),
            new DirectionsMobile(),
            new VideoCarouselMobile(),
            new NoMoreResults(),
            new VisualDigestMobile(),
            new SGEButton(),
            new SGEWidget(),
            new FeaturedSnipped(),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getParsableItems(GoogleDom $googleDom)
    {
        // [@id='iur'] = images
        // @data-attrid='images universal' = images -- removed FIX IT!
        // [@id='sports-app'] = classical results
        // [contains(@class, 'scm-c')]  = maps
        // [contains(@class, 'qixVud')]  = maps
        // [contains(@class, 'xxAJT')]  = maps
        // [contains(@class, 'related-question-pair')] = questions
        // [@class='C7r6Ue']  = maps
        // [@class='qixVud']  = maps
        // [@class='xSoq1']  = top stories
        // [@class='lU8tTd']  = top stories
        //  @class='cawG4b OvQkSb' = videos
        //  @class='uVMCKf mnr-c' = videos
        //  @class='HD8Pae mnr-c' = videos
        //  @class='YJpHnb mnr-c' = videos (short)
        // [contains(@class, 'commercial-unit-mobile-top')]  = product listing
        // [contains(@class, 'commercial-unit-mobile-bottom')]  = product listing
        // [contains(@class, 'osrp-blk')]  =  knowledge graph
        // [@id='tads']  = ads top
        // [@id='tadsb']  = ads bottom
        // [@id='bottomads']  = ads bottom
        // [[contains(@class, 'qs-io')]]  =app pack
        // [[contains(@class, 'ki5rnd')]]  =app pack
        // [@class='xpdopen']  = features snipped/position zero
        // [contains(@class, 'CWesnb')]  = features snipped/position zero
        //[contains(@class, 'gws-plugins-horizon-jobs__li-ed')]  = jobs
        //@jscontroller='G42bz' = jobs
        //@jscontroller='wuEeed' = product listing
        //[contains(@class, 'LQQ1Bd')] - flights
        // [@class='BNeawe DwrKqd'] - new flights
        //div[@class='hNKF2b'] = hotels
        //div[@class='lr_container wDYxhc yc7KLc'] = definitions
        // @jsname='MGJTwe'  = recipes
        //@id='oFNiHe' - misspelings
        //@id='lud-ed' directions
        //@jscontroller='h7XEsd' directions
        //contains(@class, 'e8Ck0d') visual digest
        //contains(@class, 'Enb9pe') - visual digest mobile
        return $googleDom->xpathQuery("//*[@id='iur' or
            (contains(@class, 'IZE3Td') and .//div[@data-attrid='images universal']) or
            @id='sports-app' or
            @id='center_col' or
            @id='tads' or
            @id='tadsb' or
            @id='bottomads' or
            contains(@class, 'scm-c') or
            contains(@class, 'related-question-pair') or
            @class='C7r6Ue' or
            contains(@class, 'qixVud') or
            contains(@class, 'xxAJT') or
            contains(@class, 'commercial-unit-mobile-top') or
            contains(@class, 'commercial-unit-mobile-bottom') or
            product-viewer-group or
            contains(@class, 'osrp-blk') or
            contains(@class, 'qs-io') or
            contains(@class, 'ki5rnd') or
            @class='xpdopen' or
            contains(@class, 'CWesnb') or
            contains(@class, 'gws-plugins-horizon-jobs__li-ed') or
            contains(@class, 'L5NwLd') or
            @jscontroller='G42bz' or
            contains(@class, 'LQQ1Bd') or
            @class='BNeawe DwrKqd' or
            @class='IuoSj' or
            @class='xSoq1' or
            @class='lU8tTd' or
            @class='cawG4b OvQkSb' or
            @class='uVMCKf mnr-c' or
            contains(@class, 'uVMCKf Ww4FFb') or
            contains(@class, 'HD8Pae mnr-c') or
            contains(@class, 'YJpHnb mnr-c') or
            contains(@class, 'vtSz8d Ww4FFb vt6azd') or
            contains(@class, 'EDblX HG5ZQb') or
            contains(@class, 'hNKF2b') or
            contains(@class, 'lr_container wDYxhc yc7KLc') or
            @jsname='MGJTwe'  or
            contains(@class, 'kp-wholepage') or
            @id = 'oFNiHe' or
            @id='lud-ed' or
            @jscontroller='h7XEsd' or
            video-voyager or
            inline-video or
            @id= 'ofr' or
            contains(@class, 'e8Ck0d') or
            @id='eKIzJc' or
            @jsname='ZLxsqf' or
            @class='pxiwBd' or
            @jscontroller='wuEeed' or
            contains(@class, 'Enb9pe')
        ][not(self::script) and not(self::style)]");
    }
}
