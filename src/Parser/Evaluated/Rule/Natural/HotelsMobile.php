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

class HotelsMobile implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{

    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        // Fast path: known hotels class
        if (str_contains($node->getAttribute('class'), 'hNKF2b')) {
            return self::RULE_MATCH_MATCHED;
        }

        // Defensive validation: dGwZHb container must have guest picker (lz6svf) as descendant
        if ($node->getAttribute('jscontroller') === 'dGwZHb') {
            $xpath = $dom->getXpath();
            $hasGuestPicker = $xpath->query("descendant::*[@jscontroller='lz6svf']", $node);
            
            if ($hasGuestPicker->length > 0) {
                return self::RULE_MATCH_MATCHED;
            }
        }

        return self::RULE_MATCH_NOMATCH;
    }


    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        $xpath = $dom->getXpath();

        // We combine your original class 'BTPx6e' with the new class 'HjGrCb' found in your HTML files.
        // To avoid generic design classes and language issues, we specifically target
        // elements that are marked as 'heading' level 3, which Google uses for hotel titles.
        $hotels = $xpath->query(
            "descendant-or-self::*[
            (
                (
                    contains(concat(' ', normalize-space(@class), ' '), ' HjGrCb ') and (@role='heading' or @aria-level='3')
                ) or
                contains(concat(' ', normalize-space(@class), ' '), ' BTPx6e ')
            )]",
            $node
        );

        $item = [];
        $uniqueNames = [];

        if($hotels->length> 0) {
            foreach ($hotels as $urlNode) {
                try {
                    $name = trim($urlNode->nodeValue);

                    // Filter out empty strings or duplicate names (Google sometimes repeats names in the map/list)
                    if ($name !== '' && !in_array($name, $uniqueNames)) {
                        $item['hotels_names'][] = ['name' => $name];
                        $uniqueNames[] = $name;
                    }
                } catch (\Exception $e) {
                    // Fail silently for individual items
                }
            }

            if (!empty($item['hotels_names'])) {
                $resultSet->addItem(new BaseResult(
                    NaturalResultType::HOTELS_MOBILE,
                    $item,
                    $node,
                    $this->hasSerpFeaturePosition,
                    $this->hasSideSerpFeaturePosition
                ));
            }
        }


    }
}
