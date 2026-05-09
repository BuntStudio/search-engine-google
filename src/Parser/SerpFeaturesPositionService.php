<?php

namespace Serps\SearchEngine\Google\Parser;

use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;

class SerpFeaturesPositionService
{
    protected $results;
    protected $processedCompetition = [];
    private $serpFeaturesPositionsIdentified = false;
    private $serpsPositions = [];
    public function __construct(IndexedResultSet $results, $processedCompetition = [])
    {
        $this->results = $results;
        $this->processedCompetition = $processedCompetition;
    }

    public function identifySerpPositions() {
        // Pre-build per-item cache: explode each eligible item's node path once
        // and pre-classify its type. Without this, the inner loop re-explodes
        // both the serp-feature path and every item's path on every (feature × item)
        // pair (O(features × items) explodes, and the same arrays were reallocated
        // for every serp feature even though they don't change between features).
        $itemsCache = [];
        foreach ($this->results->getItems() as $cachedItem) {
            $cachedIsClassical = $cachedItem->is(NaturalResultType::CLASSICAL) || $cachedItem->is(NaturalResultType::CLASSICAL_MOBILE);
            if (!$cachedIsClassical && !$cachedItem->is(NaturalResultType::EXCEPTIONS)) {
                continue;
            }
            $cachedNodePath = $cachedItem->getNodePath();
            if (empty($cachedNodePath)) {
                continue;
            }
            $itemsCache[] = [
                'item'        => $cachedItem,
                'pathArr'     => explode('/', $cachedNodePath),
                'pathLen'     => substr_count($cachedNodePath, '/') + 1,
                'isClassical' => $cachedIsClassical,
                'isException' => $cachedItem->is(NaturalResultType::EXCEPTIONS),
            ];
        }

        $serpResults = [];
        foreach ($this->results->getItems() as $itemPosition => $item) {
            if (!empty(array_diff($item->getTypes(), [NaturalResultType::CLASSICAL, NaturalResultType::CLASSICAL_MOBILE, NaturalResultType::EXCEPTIONS]))) {
                // result is serp
                $serpResults[] = $item;
            }
        }

        foreach ($serpResults as $serpResultsKey => $serpResultItem) {

            if (
                !$serpResultItem->serpFeatureHasPosition() ||
                empty($serpResultItem->getNodePath())
            ) {
                continue;
            }

            // Hoisted out of the inner loop — invariant for all items of this feature.
            $serpNodePath = $serpResultItem->getNodePath();
            $serpNodePathArr = explode('/', $serpNodePath);
            $serpNodePathLen = count($serpNodePathArr);

            $resultsWithSerps = [];
            $maxXpathSimilaritySize = 0;
            $maxXpathSimilarityPosition = 0;
            $position = 1;
            $previousItemUrl = '';
            //process serp position using all results
            foreach ($itemsCache as $cached) {
                $item = $cached['item'];
                $itemNodePathArr = $cached['pathArr'];

                // Early bail: array_intersect_assoc result size <= min(|a|,|b|),
                // and the contiguity prune below can only shrink it further. If the
                // upper bound can't beat the current best we skip the intersect/diff/
                // preg work entirely. Position counter at the bottom still runs.
                if (min($serpNodePathLen, $cached['pathLen']) >= $maxXpathSimilaritySize) {
                    $xpathSimilarity = array_intersect_assoc(
                        $serpNodePathArr,
                        $itemNodePathArr
                    );

                    $prevKey = false;
                    foreach ($xpathSimilarity as $xpathSimilarityKey => $xpathSimilarityVal) {
                        if ($prevKey === false) {
                            $prevKey = $xpathSimilarityKey;
                            continue;
                        }

                        if($xpathSimilarityKey == $prevKey + 1 ) {
                            $prevKey = $xpathSimilarityKey;
                            continue;
                        }

                        unset($xpathSimilarity[$xpathSimilarityKey]);
                    }

                    $xpathSimilaritySize = count($xpathSimilarity);

                    if(
                        $xpathSimilarity &&
                        $xpathSimilaritySize >= $maxXpathSimilaritySize
                    ) {
                        $serpArrDiff = array_values(array_diff_assoc($serpNodePathArr, $xpathSimilarity));
                        $itemArrDIff = array_values(array_diff_assoc($itemNodePathArr, $xpathSimilarity));

                        if (!empty($serpArrDiff) && !empty($itemArrDIff)) {
                            $nextItem = $itemArrDIff[0];
                            $nextSerp = $serpArrDiff[0];

                            // preg_replace mimics FILTER_SANITIZE_NUMBER_INT (keep digits,
                            // '+' and '-') without filter_var's options-parsing overhead.
                            $intNextItem = (int) preg_replace('/[^\d+-]/', '', $nextItem);
                            $intNextSerp = (int) preg_replace('/[^\d+-]/', '', $nextSerp);

                            if ($intNextItem && $intNextSerp) {

                                if($intNextItem <= $intNextSerp) {
                                    $maxXpathSimilaritySize = $xpathSimilaritySize;
                                    $maxXpathSimilarityPosition = $position + ($cached['isException'] ? 0 : 1);
                                } else {
                                    if (empty($maxXpathSimilaritySize)) {
                                        $maxXpathSimilaritySize = $xpathSimilaritySize;
                                        $maxXpathSimilarityPosition = $position;
                                    }
                                }

                            } else {
                                $maxXpathSimilaritySize = $xpathSimilaritySize;
                                $maxXpathSimilarityPosition = $position;
                            }
                        } else {
                            if (empty($maxXpathSimilaritySize)) {
                                $maxXpathSimilaritySize = $xpathSimilaritySize;
                                $maxXpathSimilarityPosition = $position;
                            }
                        }
                    }
                }

                if ($cached['isClassical']) {
                    if(empty($previousItemUrl)) {
                        $previousItemUrl = $item->url;
                    } else {
                        if($item->url == $previousItemUrl) {
                            // do not increase position for same url items
                            continue;
                        }
                    }

                    $processedUrl = $this->parseItemUrl($item->url);
                    if (
                        isset($this->processedCompetition[$position]) &&
                        $processedUrl == $this->processedCompetition[$position]['full_landing_page']
                    ) {
                        $position++;
                    }
                }
            }

            $serpResultItem->setSerpFeaturePositionOnPage($maxXpathSimilarityPosition);
            if (isset(NaturalResultType::SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS[$serpResultItem->getTypes()[0]])) {
                // if a serp feature appears multiple times we save its first position
                if (isset($this->serpsPositions[NaturalResultType::SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS[$serpResultItem->getTypes()[0]]])) {
                    if ($serpResultItem->getSerpFeaturePositionOnPage() < $this->serpsPositions[NaturalResultType::SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS[$serpResultItem->getTypes()[0]]]) {
                        $this->serpsPositions[NaturalResultType::SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS[$serpResultItem->getTypes()[0]]] = $serpResultItem->getSerpFeaturePositionOnPage();
                    }
                } else {
                    $this->serpsPositions[NaturalResultType::SERP_FEATURES_TYPE_TO_OLD_RESPONSE_FOR_POSITIONS[$serpResultItem->getTypes()[0]]] = $serpResultItem->getSerpFeaturePositionOnPage();
                }
            }

        }

        $this->serpFeaturesPositionsIdentified = true;
    }

    private function parseItemUrl($url) {
        if (empty(json_encode($url))) {
            $url = utf8_encode($url);
        }
        return $url;
    }

    public function outputSerpResultsForTest(
        $keyword,
        $mobile
    ) {
        $pos = 1;
        $output = '';
        foreach ($this->processedCompetition as $itemPosition => $item) {
            $output .= str_pad($itemPosition, 4) . ': ' . str_pad('result', 23) . ' | ' .  str_pad($item['node_path'], 180) . ' | ' . $item['full_landing_page'] . "\n";

            foreach ($this->results->getItems() as $itemPositionKey => $item) {

                if (
                    $item->is(NaturalResultType::CLASSICAL, NaturalResultType::CLASSICAL_MOBILE, NaturalResultType::EXCEPTIONS)
                ) {
                    continue;
                }

                if (
                    $item->serpFeatureHasPosition() &&
                    !$item->serpFeatureHasSidePosition() &&
                    $item->getSerpFeaturePositionOnPage() &&
                    (int)$item->getSerpFeaturePositionOnPage() == $itemPosition
                ) {
                    $output .= str_pad($item->getSerpFeaturePositionOnPage(), 4) . ': ' . str_pad($item->getTypes()[0], 23) . ' | ' . str_pad($item->getNodePath(), 180) .  "\n";
                }
            }
        }

        foreach ($this->results->getItems() as $itemPositionKey => $item) {

            if (
                $item->is(NaturalResultType::CLASSICAL, NaturalResultType::CLASSICAL_MOBILE, NaturalResultType::EXCEPTIONS)
            ) {
                continue;
            }

            if (
                $item->serpFeatureHasPosition() &&
                $item->getSerpFeaturePositionOnPage() &&
                (
                    (int)$item->getSerpFeaturePositionOnPage() > count($this->processedCompetition) ||
                    $item->serpFeatureHasSidePosition()
                )
            ) {
                $output .= str_pad($item->getSerpFeaturePositionOnPage(), 4) . ': ' . str_pad($item->getTypes()[0], 23) . ' | ' . str_pad($item->getNodePath(), 180) .  "\n";
            }
        }

        file_put_contents('results_'.$keyword.'_'.($mobile? 'm':'d').'_new.txt', $output);
    }

    public function getSerpFeaturesPositions () {
        if (!$this->serpFeaturesPositionsIdentified) {
            throw new \Exception('You need to identify the serp positions first');
        }

        return $this->serpsPositions;
    }
}
