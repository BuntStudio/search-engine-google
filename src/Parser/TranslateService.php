<?php

namespace Serps\SearchEngine\Google\Parser;

use Aws\Panorama\PanoramaClient;
use Monolog\Logger;
use Serps\SearchEngine\Google\NaturalResultType;

/**
 * Class TranslateService
 *
 * @package Serps\SearchEngine\Google\Parser
 */
class TranslateService
{
    use \Serps\SearchEngine\Google\Parser\Helper\Log;

    const SERP_FEATURES_TO_RESEARCH = [
        NaturalResultType::SITES,
        NaturalResultType::FLIGHT_AIRLINE_OPTIONS,
        NaturalResultType::SGE_BUTTON,
        NaturalResultType::SGE_WIDGET,
    ];

    protected $siteHost = null,
        $mobile = false,
        $crawlSubdomains = false,
        $urlAlias = null,
        $response = [];

    const DEFAULT_POSITION = 666;

    /**
     * TranslateService constructor.
     *
     * @param string $siteHost
     * @param bool $crawlSubdomains
     * @param string|null $urlAlias
     * @param Logger|null $logger
     */
    public function __construct(string $siteHost, bool $crawlSubdomains = false, $urlAlias = null, Logger $logger = null)
    {
        $this->siteHost        = $this->extractDomain($siteHost);
        $this->crawlSubdomains = $crawlSubdomains;
        $this->urlAlias        = $urlAlias;

        $this->initLogger($logger);
    }

    /**
     * @param $url
     *
     * @return string
     */
    protected function extractDomain($url)
    {
        $url = strtolower(trim($url));
        $url = str_replace(['http://', 'https://'], ['', ''], $url);

        $url = preg_replace('/^www[0-9]*\./', '', $url);

        if (strpos($url, '/') != false) {
            $url = substr($url, 0, strpos($url, '/'));
        }

        $url = ltrim($url, '.');
        $url = ltrim($url, '/');

        return $url;
    }

    protected function removeProtocolAndPath($url)
    {
        $url = strtolower(trim($url));
        $url = str_replace(['http://', 'https://'], ['', ''], $url);
        if (strpos($url, '/') != false) {
            $url = substr($url, 0, strpos($url, '/'));
        }

        $url = ltrim($url, '.');
        $url = ltrim($url, '/');

        return $url;
    }

    /**
     * @param $item
     *
     * @return array
     */
    protected function matchSubdomainsOrUrlAlias($item)
    {
        $matchedSubdomains = [];

        $url = $this->removeProtocolAndPath($item->url);
        if ($this->crawlSubdomains || $this->mobile || $this->urlAlias) {
            if ($this->crawlSubdomains === false) {
                preg_match('/m\.' . str_replace('.', '\.', $this->siteHost) . '/', $url, $matchedSubdomains);

                if (empty($matchedSubdomains[0]) && $this->urlAlias) {
                    preg_match('/m\.' . str_replace('.', '\.', $this->urlAlias) . '/', $url, $matchedSubdomains);
                }

            } else {
                preg_match('/.*\.' . str_replace('.', '\.', $this->siteHost) . '/', $url, $matchedSubdomains);

                if (empty($matchedSubdomains[0]) && $this->urlAlias) {
                    preg_match('/.*\.' . str_replace('.', '\.', $this->urlAlias) . '/', $url,
                        $matchedSubdomains);
                }
            }
        }

        return $matchedSubdomains;
    }

    /**
     * @param $item
     * @param $rank
     */
    protected function processClassicalResult($item, &$rank, $rewritePositionFromPosZero = false)
    {
        $rank++;

        // Double check: ignore results when have duplicates. Do not put in competition the same result as previous result
        if (!empty($this->response['competition'][(int)$rank - 1]['url']) &&
            $this->response['competition'][(int)$rank - 1]['full_landing_page'] == $item->url) {
            $rank--;

            return;
        }
        $url = $this->parseItemUrl($item->url);
        $description =$item->description;
        if (empty(json_encode($item->description))) {
            $description = utf8_encode($item->description);
        }

        $title = $item->title;
        if (empty(json_encode($item->title))) {
            $title = utf8_encode($item->title);
        }

        if (empty($url)) {
            $rank--;
            return;
        }

        $matchedSubdomains = $this->matchSubdomainsOrUrlAlias($item);
        $domainName  = $this->extractDomain($url);

        if ($rank == 1 && strpos($url, 'wikipedia.org') !== false) {
            $this->response[NaturalResultType::WIKI] = 1;
        }

        if (
            ($this->response['position'] == self::DEFAULT_POSITION || $rewritePositionFromPosZero) &&
            ( $domainName === $this->siteHost || $domainName === $this->urlAlias || !empty($matchedSubdomains[0]))
        ) {
            $this->response['position']     = $rank;
            $this->response['landing_page'] = $url;
        }

        if (empty($this->response['list_of_urls'][0][$domainName])) {

            if($rewritePositionFromPosZero) {
                $this->response['list_of_urls'][0] = [$domainName=>$rank] + $this->response['list_of_urls'][0];
            } else {
                $this->response['list_of_urls'][0][$domainName] = $rank;
            }
        } else if ($rewritePositionFromPosZero) {
            unset($this->response['list_of_urls'][0][$domainName]);
            $this->response['list_of_urls'][0] = [$domainName=>$rank] + $this->response['list_of_urls'][0];
        }

        $competitionData = [
            "url"               => $domainName,
            "full_landing_page" => $url,
            "height"            => "0",
            "title"             => $title,
            "description"       => $description,
            "imbricated"        => $item->imbricated??false,
            "video"             => "",
            "amp"               => "",
            "node_path"         => method_exists($item, 'getNodePath') ? $item->getNodePath() : $item->nodePath,
            "reviews_and_pricing" => $item->reviewsAndPricing??false,
            "has_pricing"       =>  $item->hasPricing??false,
            "article_date"       =>  $item->articleDate??false
        ];

        $this->response['competition'][(string)$rank] = $competitionData;

        if($rewritePositionFromPosZero) {
            ksort($this->response['competition']);
        }
    }

    /**
     * @param $item
     */
    protected function processSerpFeatures($item)
    {
        if ($item->is(NaturalResultType::APP_PACK)) {
            $this->response[NaturalResultType::APP_PACK] = true;
        }

        if ($item->is(NaturalResultType::AdsTOP) || $item->is(NaturalResultType::AdsTOP_MOBILE)) {
            $this->response[NaturalResultType::AdsTOP] = $item->getData();
        }

        if ($item->is(NaturalResultType::AdsDOWN) || $item->is(NaturalResultType::AdsDOWN_MOBILE)) {
            $this->response[NaturalResultType::AdsDOWN] = $item->getData();
        }

        if ($item->is(NaturalResultType::APP_PACK_MOBILE)) {
            $this->response[NaturalResultType::APP_PACK] = true;
        }

        if ($item->is(NaturalResultType::MISSPELLING) || $item->is(NaturalResultType::MISSPELING_MOBILE)) {
            $this->response[NaturalResultType::MISSPELLING_OLD_VERSION] = $item->getData()[0];
        }

        if ($item->is(NaturalResultType::HOTELS) || $item->is(NaturalResultType::HOTELS_MOBILE)) {
            $this->response[NaturalResultType::HOTELS_NAMES] = $item->getData()['hotels_names'];
            $this->response[NaturalResultType::HOTELS]       = $item->getData()['hotels_names'];
        }


        if ($item->is(NaturalResultType::MAP) || $item->is(NaturalResultType::MAP_MOBILE)) {
            $this->response[NaturalResultType::MAP]              = true;
            $this->response[NaturalResultType::MAPS_OLD_VERSION] = true;

            foreach ($item->getData() as $mapResult) {
                $title = $mapResult['title'];
                $href = $mapResult['href'];

                $this->response[NaturalResultType::MAPS_LINKS][] = ['title' => $title, 'url' => $href];
            }
        }

        if ($item->is(NaturalResultType::VIDEOS) || $item->is(NaturalResultType::VIDEOS_MOBILE)) {
            $videoTypes = [
                NaturalResultType::VIDEOS,
                NaturalResultType::VIDEOS_LIST
            ];

            foreach ($videoTypes as $type) {
                if (isset($this->response[$type]) && !empty($this->response[$type])) {
                    $this->response[$type] = array_merge($this->response[$type], $item->getData());
                } else {
                    $this->response[$type] = $item->getData();
                }
            }
        }

        if ($item->is(NaturalResultType::KNOWLEDGE_GRAPH) || $item->is(NaturalResultType::KNOWLEDGE_GRAPH_MOBILE)) {
            $this->response[NaturalResultType::KNOWLEDGE_GRAPH] = $item->getData()['title']??'';
            $this->response[NaturalResultType::KNOWLEDGE_GRAPH_LINK] = $item->getData()['link']??'';
        }

        if ($item->is(NaturalResultType::RECIPES_GROUP)) {
            $this->response[NaturalResultType::RECIPES_GROUP] = true;
            $this->response[NaturalResultType::RECIPES_LINKS] = $item->getData()['recipes_links'];
        }

        if ($item->is(NaturalResultType::FEATURED_SNIPPED) || $item->is(NaturalResultType::FEATURED_SNIPPED_MOBILE)) {
            if (count($item->getData()) > 1) {
                $snippets    = $item->getData();
                $snippetsWithNodePath = [];
                foreach ($snippets as $key => $snippet) {
                    $snippetsWithNodePath[$key] = (object)array_merge((array)$snippet, ['nodePath' => $item->getNodePath()]);
                }
                $firstResult = array_shift($snippetsWithNodePath);

                $this->incrementCompetitionRanksAndDomainRanks($snippetsWithNodePath);

            } else {
                $firstResult = $item->getData()[0];
            }

            $this->response[NaturalResultType::FEATURED_SNIPPED] = $firstResult->url;
            $this->response[NaturalResultType::FEATURED_SNIPPED.'_title'] = isset($firstResult->title) ? $firstResult->title : '';
            $this->response[NaturalResultType::FEATURED_SNIPPED. '_description'] = isset($firstResult->description) ? $firstResult->description : '';
        }

        if ($item->is(NaturalResultType::PRODUCT_LISTING) || $item->is(NaturalResultType::PRODUCT_LISTING_MOBILE)) {
            $this->response[NaturalResultType::PRODUCT_LISTING] = $item->getData();
        }

        if ($item->is(NaturalResultType::QUESTIONS) || $item->is(NaturalResultType::QUESTIONS_MOBILE)) {
            $this->response[NaturalResultType::QUESTIONS][] = $item->getData();
        }

        if ($item->is(NaturalResultType::FLIGHTS)) {
            $this->response[NaturalResultType::FLIGHTS] = true;
        }

        if ($item->is(NaturalResultType::DEFINITIONS) || $item->is(NaturalResultType::DEFINITIONS_MOBILE)) {
            $this->response[NaturalResultType::DEFINITIONS] = true;
        }

        if ($item->is(NaturalResultType::JOBS) || $item->is(NaturalResultType::JOBS_MOBILE)) {
            $this->response[NaturalResultType::JOBS] = true;
        }

        if ($item->is(NaturalResultType::SITE_LINKS_BIG) || $item->is(NaturalResultType::SITE_LINKS_BIG_MOBILE)) {
            $this->response[NaturalResultType::SITE_LINKS] = 2;
        }

//        if ($item->is(NaturalResultType::SITE_LINKS_SMALL) || $item->is(NaturalResultType::SITE_LINKS)) {
//            $this->response[NaturalResultType::SITE_LINKS] = 1;
//        }

        if ($item->is(NaturalResultType::DIRECTIONS) || $item->is(NaturalResultType::DIRECTIONS_MOBILE)) {
            $this->response[NaturalResultType::DIRECTIONS] = true;
        }

        if ($item->is(NaturalResultType::RESULTS_NO)) {
            $this->response[NaturalResultType::RESULTS_NO] = $item->getData()[0];
        }

        if ($item->is(NaturalResultType::RELATED_SEARCHES)) {
            $this->response[NaturalResultType::RELATED_SEARCHES] = $item->getData();
        }

        if ($item->is(NaturalResultType::IMAGE_GROUP) || $item->is(NaturalResultType::IMAGE_GROUP_MOBILE) ) {
            $data = $item->getData();
            if (isset($data['images'])){
                $this->response[NaturalResultType::IMAGE_GROUP] = $item->getData()['images'];
            }

        }

        if ($item->is(NaturalResultType::TOP_STORIES) || $item->is(NaturalResultType::TOP_STORIES_MOBILE) ) {
            $this->response[NaturalResultType::TOP_STORIES_OLD_VERSION] = $item->getData()['news'];
        }

        if ($item->is(NaturalResultType::MAPS_COORDONATES)) {
            $this->response[NaturalResultType::MAP]              = true;
            $this->response[NaturalResultType::MAPS_OLD_VERSION] = true;
            $this->response[NaturalResultType::MAPS_LATITUDE] = $item->getData()['lat'];
            $this->response[NaturalResultType::MAPS_LONGITUTDE] = $item->getData()['long'];
            $this->response[NaturalResultType::MAPS_COORDONATES] = $item->getData();
        }

        if (
            !isset($this->response[NaturalResultType::VIDEOS]) &&
            ($item->is(NaturalResultType::VIDEO_CAROUSEL) || $item->is(NaturalResultType::VIDEO_CAROUSEL_MOBILE))
        ) {
            $this->response[NaturalResultType::VIDEOS][] = $item->getData()[0];
            $this->response[NaturalResultType::VIDEOS_LIST][] = $item->getData()[0];
        }

        if ($item->is(NaturalResultType::NO_MORE_RESULTS)) {
            $this->response[NaturalResultType::NO_MORE_RESULTS] = true;
        }

        if ($item->is(NaturalResultType::VISUAL_DIGEST)) {
            $this->response[NaturalResultType::VISUAL_DIGEST] =  $item->getData();
        }

        if ($item->is(NaturalResultType::HIGHLY_LOCALIZED)) {
            $this->response[NaturalResultType::HIGHLY_LOCALIZED] =  true;
        }

        if ($item->is(NaturalResultType::SITES)) {
            $this->response[NaturalResultType::SITES] =  $item->getData();
        }

        if ($item->is(NaturalResultType::FLIGHT_AIRLINE_OPTIONS)) {
            $this->response[NaturalResultType::FLIGHT_AIRLINE_OPTIONS] =  $item->getData();
        }

        if ($item->is(NaturalResultType::SGE_BUTTON) || $item->is(NaturalResultType::SGE_BUTTON_MOBILE)) {
            $this->response[NaturalResultType::SGE_BUTTON] = true;
        }

        if ($item->is(NaturalResultType::SGE_WIDGET) || $item->is(NaturalResultType::SGE_WIDGET_MOBILE)) {
            $this->response[NaturalResultType::SGE_WIDGET] = true;
            $this->response[NaturalResultType::SGE_WIDGET_OPTIONS] = $item->getData();
        }

    }

    /**
     * @param \Serps\Core\Serp\IndexedResultSet $results
     * @param array $options -> only for debug purposes
     * @return $this
     */
    public function intoOldResponse(\Serps\Core\Serp\IndexedResultSet $results, $options=[])
    {
        if (empty($results->getItems())) {
            $this->response = NaturalResultType::SERP_FEATURES_OLD_RESPONSE_TEMPLATE;

            $this->response['position']     = self::DEFAULT_POSITION;
            $this->response['list_of_urls'] = [];
            $this->response['competition']  = [];

            return $this;
        }

        if ($results->hasType([NaturalResultType::CLASSICAL_MOBILE])) {
            $this->mobile = true;
        }

        $rank = 0;
        $this->initSerpFeaturesDefaultResponse();
        $this->response['position'] = self::DEFAULT_POSITION;
        $processLast = [];
        foreach ($results->getItems() as $itemPosition => $item) {

            if ($item->is(NaturalResultType::CLASSICAL) || $item->is(NaturalResultType::CLASSICAL_MOBILE)) {
                $this->processClassicalResult($item, $rank);

                continue;
            }

            // Log parameters for debug if the parser has exceptions as result
            if ($item->is(NaturalResultType::EXCEPTIONS) || $item->is(NaturalResultType::EXCEPTIONS)) {
                continue;
            }

            if ($item->is(NaturalResultType::FEATURED_SNIPPED) || $item->is(NaturalResultType::FEATURED_SNIPPED_MOBILE)) {
                $processLast[] = $item;
                continue;
            }

            $this->processSerpFeatures($item);
        }

        if ($rank === 0) {
            $this->monolog->error('Cannot identify results in html page', ['class' => self::class, 'keyword' => $options['keyword_name'] ?? 'N/A']);
        }

        if (!empty($processLast)) {
            foreach ($processLast as $item) {
                $this->processSerpFeatures($item);
            }
        }

        if (!empty($this->response['competition'])) {
            $serpsPositionsService = new SerpFeaturesPositionService($results, $this->response['competition']);
            $serpsPositionsService->identifySerpPositions();
            $this->response['serp_features_positions'] = $serpsPositionsService->getSerpFeaturesPositions();
        } else {
            $this->response['serp_features_positions'] = [];
        }

        $this->response['list_of_urls'][0] = !empty($this->response['list_of_urls'][0]) ? array_reverse($this->response['list_of_urls'][0]):[];
        $this->response['competition'] = !empty($this->response['competition'])?array_reverse($this->response['competition'], true):[];

        foreach (self::SERP_FEATURES_TO_RESEARCH as $researchSerpFeature) {
            if (!empty($this->response[$researchSerpFeature])) {
                $this->monolog->notice($researchSerpFeature . ' SERP Feature found', ['keyword' => $options['keyword_name'] ?? 'N/A', 'site' => $this->siteHost, 'mobile' => ($options['mobile'] ?? false) ? '1' : '0', 'data' => $this->response[$researchSerpFeature]]);
            }
        }

        return $this;
    }

    /**
     *
     */
    protected function initSerpFeaturesDefaultResponse()
    {
        foreach (NaturalResultType::SERP_FEATURES_OLD_RESPONSE_TEMPLATE as $typeSerp => $value) {
            $this->response[$typeSerp] = $value;
        }
    }

    /**
     * @return array|bool|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    protected function incrementCompetitionRanksAndDomainRanks($snippets)
    {


        foreach (array_reverse($snippets) as $featureSnippet) {
            $rank        = 0;
            if (!empty($this->response['list_of_urls'][0])){
                foreach ($this->response['list_of_urls'][0] as $domainName => $domainPosition) {
                    $this->response['list_of_urls'][0][$domainName] = $domainPosition + 1;
                }
            }

            $naturalResults                = $this->response['competition'];
            $this->response['competition'] = [];

            foreach ($naturalResults as $currentRank => $data) {
                $this->response['competition'][$currentRank + 1] = $data;
            }

            $this->processClassicalResult($featureSnippet, $rank, true);
        }
    }

    private function parseItemUrl($url) {
        if (empty(json_encode($url))) {
            $url = utf8_encode($url);
        }
        return $url;
    }
}
