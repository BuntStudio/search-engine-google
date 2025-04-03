<?php

namespace Serps\SearchEngine\Google;

abstract class NaturalResultType
{

    const CLASSICAL        = 'classical';
    const CLASSICAL_MOBILE = 'classical_mobile';

    const KNOWLEDGE       = 'knowledge';
    const AdsTOP          = 'ads_up';
    const ADS_OLD_VERSION = 'ads';
    const AdsTOP_MOBILE   = 'ads_top_mobile';
    const AdsDOWN         = 'ads_down';
    const AdsDOWN_MOBILE  = 'ads_down_mobile';

    const PEOPLE_ALSO_ASK = 'people_also_ask';
    const PAA_QUESTION    = 'paa_question';

    const IMAGE_GROUP             = 'images';
    const IMAGE_GROUP_MOBILE      = 'images_mobile';
    const FEATURED_SNIPPED        = 'pos_zero';
    const FEATURED_SNIPPED_MOBILE = 'pos_zero_mobile';
    const QUESTIONS               = 'questions';
    const QUESTIONS_MOBILE        = 'questions_mobile';
    const JOBS                    = 'jobs';
    const JOBS_MOBILE             = 'jobs_mobile';
    const JOBS_MINE                    = 'jobs_mine';
    const JOBS_MINE_MOBILE             = 'jobs_mine_mobile';

    const APP_PACK                = 'app_pack';
    const WIKI                    = 'has_wiki';
    const SITE_LINKS_BIG          = 'site_links_big';
    const SITE_LINKS              = 'site_links';
    const AMP                     = 'amp';
    const SITE_LINKS_BIG_MOBILE   = 'site_links_big_mobile';
    const SITE_LINKS_SMALL        = 'site_links_small';
    const APP_PACK_MOBILE         = 'app_pack_mobile';

    const PRODUCT_LISTING         = 'pla';
    //const PRODUCT_GRID            = 'pgr';
    const PRODUCT_LISTING_MOBILE  = 'pla_mobile';
    const RECIPES_GROUP           = 'recipes';
    const RECIPES_LINKS           = 'recipes_links';


    const VIDEOS        = 'videos';
    const VIDEOS_MINE        = 'videos_mine';
    const VIDEOS_LIST        = 'videos_list';
    const VIDEOS_MOBILE = 'videos_mobile';
    const VIDEOS_MINE_MOBILE = 'videos_mine_mobile';
    const VIDEO_CAROUSEL = 'video_carousel';
    const VIDEO_CAROUSEL_MOBILE = 'video_carousel_mobile';

    const TOP_STORIES             = 'top_stories';
    const TOP_STORIES_OLD_VERSION = 'news';
    const TOP_STORIES_MOBILE      = 'top_stories_mobile';
    const TWEETS_CAROUSEL         = 'tweets_carousel';

    const MAP              = 'maps';
    const MAPS_OLD_VERSION = 'has_map';
    const MAPS_LINKS            = 'maps_links';
    const MAPS_COORDONATES      = 'maps_coords';
    const MAP_MOBILE       = 'maps_mobile';
    const MAPS_LATITUDE       = 'lat';
    const MAPS_LONGITUTDE       = 'long';

    const FLIGHTS                = 'flights';
    const FLIGHTS_MOBILE                = 'flights_mobile';
    const FLIGHTS_MINE                = 'flights_mine';
    const FLIGHTS_MINE_MOBILE                = 'flights_mine_mobile';

    const KNOWLEDGE_GRAPH        = 'knowledge_graph';
    const KNOWLEDGE_GRAPH_LINK        = 'knowledge_graph_link';
    const KNOWLEDGE_GRAPH_MOBILE = 'knowledge_graph_mobile';

    const ANSWER_BOX = 'answer_box';

    const HOTELS        = 'hotels';
    const EXCEPTIONS        = 'exceptions';
    const HOTELS_NAMES  = 'hotels_names';
    const HOTELS_MOBILE = 'hotels_mobile';

    const DEFINITIONS        = 'definition';
    const DEFINITIONS_MOBILE = 'definition_mobile';
    const DEFINITIONS_MINE        = 'definition_mine';
    const DEFINITIONS_MINE_MOBILE = 'definition_mine_mobile';

    const MISSPELLING             = 'misspelling';
    const MISSPELLING_MINE             = 'misspelling_mine';
    const MISSPELLING_OLD_VERSION = 'spell';
    const MISSPELLING_OLD_VERSION_MINE = 'spell_mine';
    const MISSPELLING_OLD_VERSION_MOBILE = 'spell_mobile';
    const MISSPELLING_OLD_VERSION_MINE_MOBILE = 'spell_mine_mobile';
    const MISSPELING_MOBILE       = 'misspelling_mobile';
    const MISSPELING_MINE_MOBILE       = 'misspelling_mine_mobile';

    const RESULTS_NO = 'no_results';
    const DIRECTIONS = 'directions';
    const DIRECTIONS_MOBILE = 'directions_mobile';
    const DIRECTIONS_MINE = 'directions_mine';
    const DIRECTIONS_MINE_MOBILE = 'directions_mine_mobile';

    const KNOWLEDGE_GRAPH_MINE        = 'knowledge_graph_mine';
    const KNOWLEDGE_GRAPH_MINE_MOBILE = 'knowledge_graph_mine_mobile';
    const NO_MORE_RESULTS             = 'no_more_results';
    const VISUAL_DIGEST               = 'visual_digest';
    const HIGHLY_LOCALIZED            = 'highly_localized';
    const RELATED_SEARCHES            = 'related_searches';
    const SITES                       = 'sites';
    const FLIGHT_AIRLINE_OPTIONS      = 'flight_airline_options';

    const SGE_BUTTON         = 'sge_button';
    const SGE_WIDGET         = 'sge_widget';
    const SGE_BUTTON_MOBILE  = 'sge_button_mobile';
    const SGE_WIDGET_MOBILE  = 'sge_widget_mobile';
    const SGE_WIDGET_OPTIONS = 'sge_widget_options';
    const SGE_WIDGET_BASE    = 'sge_widget_base_content';
    const SGE_WIDGET_CONTENT = 'sge_widget_content';
    const SGE_WIDGET_LOADED  = 'sge_widget_content_loaded';
    const SGE_WIDGET_LINKS   = 'sge_widget_links';

    const CURRENCY_ANSWER             = 'currency_answer';
    const CURRENCY_ANSWER_MINE        = 'currency_answer_mine';
    const CURRENCY_ANSWER_MOBILE      = 'currency_answer_mobile';
    const CURRENCY_ANSWER_MOBILE_MINE = 'currency_answer_mobile_mine';

    const FLIGHTS_AIRLINE             = 'flights_airline';
    const FLIGHTS_AIRLINE_MINE        = 'flights_airline_mine';
    const FLIGHTS_AIRLINE_MOBILE      = 'flights_airline_mobile';
    const FLIGHTS_AIRLINE_MOBILE_MINE = 'flights_airline_mobile_mine';

    const FLIGHTS_SITES             = 'flights_sites';
    const FLIGHTS_SITES_MINE        = 'flights_sites_mine';
    const FLIGHTS_SITES_MOBILE      = 'flights_sites_mobile';
    const FLIGHTS_SITES_MOBILE_MINE = 'flights_sites_mobile_mine';

    const PLACES             = 'places';
    const PLACES_MINE        = 'places_mine';
    const PLACES_MOBILE      = 'places_mobile';
    const PLACES_MOBILE_MINE = 'places_mobile_mine';

    const PRODUCT_GRID             = 'product_grid';
    const PRODUCT_GRID_MINE        = 'product_grid_mine';
    const PRODUCT_GRID_MOBILE      = 'product_grid_mobile';
    const PRODUCT_GRID_MOBILE_MINE = 'product_grid_mobile_mine';

    const PLACES_SITES             = 'places_sites';
    const PLACES_SITES_MINE        = 'places_sites_mine';
    const PLACES_SITES_MOBILE      = 'places_sites_mobile';
    const PLACES_SITES_MOBILE_MINE = 'places_sites_mobile_mine';

    const STOCKS_BOX             = 'stocks_box';
    const STOCKS_BOX_MINE        = 'stocks_box_mine';
    const STOCKS_BOX_MOBILE      = 'stocks_box_mobile';
    const STOCKS_BOX_MOBILE_MINE = 'stocks_box_mobile_mine';

    const THINGS_TO_KNOW             = 'things_to_know';
    const THINGS_TO_KNOW_MINE        = 'things_to_know_mine';
    const THINGS_TO_KNOW_MOBILE      = 'things_to_know_mobile';
    const THINGS_TO_KNOW_MOBILE_MINE = 'things_to_know_mobile_mine';

    const TOP_SIGHTS             = 'top_sights';
    const TOP_SIGHTS_MINE        = 'top_sights_mine';
    const TOP_SIGHTS_MOBILE      = 'top_sights_mobile';
    const TOP_SIGHTS_MOBILE_MINE = 'top_sights_mobile_mine';

    const VISUAL_DIGEST_MOBILE      = 'visual_digest_mobile';
    const VISUAL_DIGEST_MOBILE_MINE = 'visual_digest_mobile_mine';

    const SERP_FEATURES_OLD_RESPONSE_TEMPLATE = [
        self::SITE_LINKS              => 0,
        self::MISSPELLING_OLD_VERSION => '',
        self::ADS_OLD_VERSION         => [],
        self::AdsDOWN                 => [],
        self::AdsTOP                  => [],
        self::IMAGE_GROUP             => [],
        self::TOP_STORIES_OLD_VERSION => [],
        self::VIDEOS                  => [],
        self::KNOWLEDGE_GRAPH         => '',
        self::MAPS_OLD_VERSION        => null,
        self::MAPS_LINKS              => null,
        self::MAPS_COORDONATES        => [],
        self::MAPS_LATITUDE           => false,
        self::MAPS_LONGITUTDE         => false,
        self::FEATURED_SNIPPED        => null,
        self::PRODUCT_LISTING         => [],
        self::QUESTIONS               => [],
        self::FLIGHTS                 => [],
        self::DEFINITIONS             => [],
        self::JOBS                    => [],
        self::APP_PACK                => null,
        self::HOTELS                  => null,
        self::HOTELS_NAMES            => [],
        self::RECIPES_GROUP           => null,
        self::RECIPES_LINKS           => null,
        self::DIRECTIONS              =>  [],
        self::RESULTS_NO              => null,
        self::WIKI              => 0,
        self::NO_MORE_RESULTS => null,
        self::VISUAL_DIGEST => null,
        self::KNOWLEDGE_GRAPH_LINK => null,
        self::HIGHLY_LOCALIZED => null,
        self::SITES => null,
        self::PRODUCT_GRID => null,
        self::CURRENCY_ANSWER => null,
        self::FLIGHTS_AIRLINE => null,
        self::FLIGHTS_SITES => null,
        self::PLACES => null,
        self::PLACES_SITES => null,
        self::STOCKS_BOX => null,
        self::THINGS_TO_KNOW => null,
        self::TOP_SIGHTS => null
    ];

    const SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS = [
        self::APP_PACK => self::APP_PACK,
        self::APP_PACK_MOBILE => self::APP_PACK,
        self::AdsTOP => self::AdsTOP,
        self::AdsTOP_MOBILE => self::AdsTOP,
        self::AdsDOWN => self::AdsDOWN,
        self::AdsDOWN_MOBILE => self::AdsDOWN,
        self::MISSPELLING => self::MISSPELLING_OLD_VERSION,
        self::MISSPELING_MOBILE => self::MISSPELLING_OLD_VERSION,
        self::HOTELS => self::HOTELS,
        self::HOTELS_MOBILE => self::HOTELS,
        self::KNOWLEDGE_GRAPH => self::KNOWLEDGE_GRAPH_MINE,
        self::KNOWLEDGE_GRAPH_MOBILE => self::KNOWLEDGE_GRAPH_MINE_MOBILE,
        self::FEATURED_SNIPPED => self::FEATURED_SNIPPED,
        self::FEATURED_SNIPPED_MOBILE => self::FEATURED_SNIPPED,
        self::RECIPES_GROUP => self::RECIPES_GROUP,
        self::RECIPES_LINKS => self::RECIPES_LINKS,
        self::PRODUCT_LISTING => self::PRODUCT_LISTING,
        self::PRODUCT_LISTING_MOBILE => self::PRODUCT_LISTING,
        self::QUESTIONS => self::QUESTIONS,
        self::QUESTIONS_MOBILE => self::QUESTIONS,
        self::FLIGHTS => self::FLIGHTS,
        self::FLIGHTS_MOBILE => self::FLIGHTS,
        self::DEFINITIONS => self::DEFINITIONS,
        self::DEFINITIONS_MOBILE => self::DEFINITIONS,
        self::JOBS => self::JOBS,
        self::JOBS_MOBILE => self::JOBS,
        self::DIRECTIONS => self::DIRECTIONS,
        self::DIRECTIONS_MOBILE => self::DIRECTIONS,
        self::IMAGE_GROUP => self::IMAGE_GROUP,
        self::IMAGE_GROUP_MOBILE => self::IMAGE_GROUP,
        self::TOP_STORIES => self::TOP_STORIES_OLD_VERSION,
        self::TOP_STORIES_MOBILE => self::TOP_STORIES_OLD_VERSION,
        self::MAPS_LINKS => self::MAPS_LINKS,
        self::VIDEOS => self::VIDEOS,
        self::VIDEOS_MOBILE => self::VIDEOS,
        self::VIDEO_CAROUSEL => self::VIDEOS,
        self::VIDEO_CAROUSEL_MOBILE => self::VIDEOS,
        self::MAP => self::MAP,
        self::MAP_MOBILE => self::MAP,
        self::PRODUCT_GRID => self::PRODUCT_GRID,
        self::PRODUCT_GRID_MOBILE => self::PRODUCT_GRID_MOBILE,
        self::CURRENCY_ANSWER => self::CURRENCY_ANSWER,
        self::CURRENCY_ANSWER_MOBILE => self::CURRENCY_ANSWER_MOBILE,
        self::FLIGHTS_AIRLINE => self::FLIGHTS_AIRLINE,
        self::FLIGHTS_AIRLINE_MOBILE => self::FLIGHTS_AIRLINE_MOBILE,
        self::FLIGHTS_SITES => self::FLIGHTS_SITES,
        self::FLIGHTS_SITES_MOBILE => self::FLIGHTS_SITES_MOBILE,
        self::PLACES => self::PLACES,
        self::PLACES_MOBILE => self::PLACES_MOBILE,
        self::PLACES_SITES => self::PLACES_SITES,
        self::PLACES_SITES_MOBILE => self::PLACES_SITES_MOBILE,
        self::STOCKS_BOX => self::STOCKS_BOX,
        self::STOCKS_BOX_MOBILE => self::STOCKS_BOX_MOBILE,
        self::THINGS_TO_KNOW => self::THINGS_TO_KNOW,
        self::THINGS_TO_KNOW_MOBILE => self::THINGS_TO_KNOW_MOBILE,
        self::TOP_SIGHTS => self::TOP_SIGHTS,
        self::TOP_SIGHTS_MOBILE => self::TOP_SIGHTS_MOBILE,
        self::VISUAL_DIGEST => self::VISUAL_DIGEST,
        self::VISUAL_DIGEST_MOBILE => self::VISUAL_DIGEST_MOBILE
    ];

}
