<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Page;

use Monolog\Logger;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\Exception;
use Serps\SearchEngine\Google\Exception\InvalidDOMException;
use Serps\SearchEngine\Google\GoogleUrlInterface;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Parser\Evaluated\AdwordsParser;
use Serps\SearchEngine\Google\Parser\Evaluated\MobileNaturalParser;
use Serps\SearchEngine\Google\Parser\Evaluated\NaturalParser;
use Serps\SearchEngine\Google\Parser\Evaluated\MobileAdwordsParser;
use Serps\Stubs\RelatedSearch;

class GoogleSerp extends GoogleDom
{
    /**
     * @var Logger|null
     */
    protected $logger;

    public function __construct($domString, GoogleUrlInterface $url, Logger $logger = null)
    {
        if (strpos($domString, 'search?q=') === false) {
            throw new InvalidDOMException('Invalid page. No search link.');
        }

        parent::__construct($domString, $url);
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        $this->logger = $logger;
    }

    /**
     * Get the location detected by google
     * @return string
     */
    public function getLocation()
    {
        $locationRegexp = '#"uul_text":"([^"]+)"#';

        preg_match($locationRegexp, $this->dom->C14N(), $matches);

        if ($matches && isset($matches[1])) {
            return $matches[1];
        }
        return null;
    }

    /**
     * @param bool $mobile
     * @param array $doNotRemoveSrsltidForDomains
     * @return IndexedResultSet|void
     * @throws InvalidDOMException
     */
    public function getNaturalResults($mobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        if ($this->javascriptIsEvaluated()) {
            if ($this->isMobile() || $mobile) {
                $parser = new MobileNaturalParser($this->logger);
            } else {
                $parser = new NaturalParser($this->logger);
            }
        } else {
            $parser = new NaturalParser($this->logger);
            (new IndexedResultSet())->addItem(new BaseResult(NaturalResultType::CLASSICAL, []));
        }

        return $parser->parse($this, $doNotRemoveSrsltidForDomains);
    }


    /**
     * @return \Serps\Core\Serp\CompositeResultSet
     * @throws Exception
     * @throws InvalidDOMException
     */
    public function getAdwordsResults()
    {
        if ($this->javascriptIsEvaluated()) {
            if ($this->isMobile()) {
                $parser = new MobileAdwordsParser($this->logger);
            } else {
                $parser = new AdwordsParser($this->logger);
            }

            return $parser->parse($this);
        } else {
            throw new InvalidDOMException('Raw dom is not supported, please provide an evaluated version of the dom');
        }
    }

    /**
     * Get the total number of results available for the search terms
     * @return int the number of results
     * @throws InvalidDOMException
     */
    public function getNumberOfResults()
    {
        $item = $this->cssQuery('#resultStats');
        if ($item->length != 1) {
            return null;
        }

        // number of results is followed by time, we want to targets the first node (text node) that is the number of
        // results
        $nodeValue = $item->getNodeAt(0)->getChildren()->getNodeAt(0)->getNodeValue();

        if (!$nodeValue) {
            return null;
        }


        // WARNING: The number of result is explained in different format according to the country. Fon instance:
        // UK:  6,200,000
        // FR:  6 200 000
        // DE:  2.200.000
        // IN:  62,00,000
        // We have to use a global matcher
        $matched = preg_match_all('/([0-9]+[^0-9]?)+/u', $nodeValue, $countMatch);

        if (!$matched) {
            return null;
        }

        // get the last count, when we use pagination the first count is the page number
        // see https://github.com/serp-spider/search-engine-google/issues/100
        $count = $countMatch[0][count($countMatch[0]) - 1];

        return (int) preg_replace('/[^0-9]/', '', $count);
    }

    public function javascriptIsEvaluated()
    {
        $body = $this->getXpath()->query('//body');

        if ($body->length != 1) {
            return false;
        }

        $body = $body->item(0);
        /** @var $body \DOMElement */
        $class = $body->getAttribute('class');

        if ($class=='hsrp') {
            return false;
        }

        if (strstr($class, 'srp') || strstr($class, 'qs-i')) {
            return true;
        }

        throw new InvalidDOMException('Unable to check javascript status.');

    }

    /**
     * @return array|RelatedSearch[]
     */
    public function getRelatedSearches()
    {
        $relatedSearches = [];
        if ($this->isMobile()) {
            $items = $this->cssQuery('#botstuff div:not(#bres) a.QsZ7bb');

            if ($items->length == 0) {
                // TODO BC version to remove
                $items = $this->cssQuery('#botstuff div:not(#bres)>._Qot>div>a');
            }

            if ($items->length > 0) {
                foreach ($items as $item) {
                    /* @var $item \DOMElement */
                    $result = new \stdClass();
                    $result->title = $item->childNodes->item(0)->nodeValue;
                    $result->url = $this->getUrl()->resolveAsString($item->getAttribute('href'));
                    $relatedSearches[] = $result;
                }
            }
        } else {
            $items = $this->cssQuery('#brs ._e4b>a, #brs .card-section a'); // TODO ._ed4 is outdated
            if ($items->length > 0) {
                foreach ($items as $item) {
                    /* @var $item \DOMElement */
                    $result = new \stdClass();
                    $result->title = $item->nodeValue;
                    $result->url = $this->getUrl()->resolveAsString($item->getAttribute('href'));
                    $relatedSearches[] = $result;
                }
            }
        }


        return $relatedSearches;
    }

    public function isMobile()
    {
        $item = $this->cssQuery('head meta[name="viewport"]');
        return $item->length == 1;
    }

    public function translate($results)
    {
        $template = [
            'site_links' => '',
            'spell' => '',
            'ads' => [],
            'ads_up' => [],
            'ads_down' => [],
            'images' => [],
            'news' => [],
            'videos' => [],
            'knowledge_graph' => '',
            'maps_links' => false,
            'has_maps' => false,
            'lat' => false,
            'long' => false,
            'pos_zero' => false,
            'pla' => [],
            'questions' => [],
            'flights' => [],
            'directions' => [],
            'definition' => [],
            'jobs' => [],
            'app_pack' => false,
            'hotels_names' => false,
            'hotels' => false,
            'recipes_links' => false,
            'recipes' => false,
            'no_results' => 0,
            'xpath' => 0,
        ];

        if (empty($results->getItems())) {
            return json_encode($template);
        }

        foreach ($results->getItems() as $item) {
            switch ($item->getTypes()[0]) {
                case 'app_pack':
                    $template['app_pack'] = true;
                    break;
                case 'misspelling':
                    $template['spell'] = 1;
                    break;
                case 'maps':
                    $template['has_maps'] = true;
                    foreach ($item->getData()['title'] as $title) {
                        $template['maps_links'][] = ['title' => $title];
                    }
                    break;
                case 'videos':
                    $template['video'] = $item->getData();
                    break;
                case 'knowledge_graph':
                    $template['knowledge_graph'] = $item->getData();
                    break;
                case 'recipes':
                    $template['recipes_links'] = $item->getData()['recipes_links'];
                    $template['recipes'] = true;
                    break;
            }
        }
    }
}
