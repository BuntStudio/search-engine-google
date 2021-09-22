<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google;

abstract class NaturalResultType
{

    const CLASSICAL = 'classical';
    const CLASSICAL_LARGE = 'classical_large';
    const CLASSICAL_VIDEO = 'classical_video';
    const CLASSICAL_SITELINK = 'classical_sitelink';
    const CLASSICAL_ILLUSTRATED = 'classical_illustrated';

    const KNOWLEDGE = 'knowledge';
    const AdsTop = 'ads_top';
    const AdsDOWN = 'ads_down';

    const PEOPLE_ALSO_ASK = 'people_also_ask';
    const PAA_QUESTION = 'paa_question';

    const IMAGE_GROUP = 'image_group';
    const FEATURED_SNIPPED = 'pos_zero';
    const QUESTIONS = 'questions';
    const JOBS = 'jobs';
    const PRODUCT_LISTING = 'pla';
    const RECIPES_GROUP = 'recipes';
    const TOP_STORIES_GROUP = 'top_stories';
    const IMAGE_GROUP_IMAGE = 'image_group_image';

    const VIDEO_GROUP = 'video_group';
    const VIDEO_GROUP_VIDEO = 'video_group_video';

    const IN_THE_NEWS = 'in_the_news';
    const TOP_STORIES = 'top_stories';
    const TOP_STORIES_NEWS_VERTICAL = 'top_stories_news_vertical';
    const TOP_STORIES_NEWS_CAROUSEL = 'top_stories_news_carousel';
    const TOP_STORIES_COMPOSED = 'top_stories_composed';
    const TWEETS_CAROUSEL= 'tweets_carousel';

    const MAP = 'maps';
    const MAP_PLACE = 'map_place';

    const FLIGHTS = 'flights';
    const KNOWLEDGE_GRAPH = 'knowledge_graph';

    const ANSWER_BOX = 'answer_box';
}
