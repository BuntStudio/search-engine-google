<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\NaturalResultType;

class Recipes implements \Serps\SearchEngine\Google\Parser\ParsingRuleInterface
{
    protected $hasSerpFeaturePosition = true;
    protected $hasSideSerpFeaturePosition = false;

    public function match(GoogleDom $dom, \Serps\Core\Dom\DomElement $node)
    {
        if (strpos($node->getAttribute('jsname'), 'MGJTwe') !== false) {
            return self::RULE_MATCH_MATCHED;
        }

        if ($node->getAttribute('data-attrid') === 'SupercatRecipeClusterTitle') {
            return self::RULE_MATCH_MATCHED;
        }

        return self::RULE_MATCH_NOMATCH;
    }

    public function parse(GoogleDom $dom, \DomElement $node, IndexedResultSet $resultSet, $isMobile = false, array $doNotRemoveSrsltidForDomains = [])
    {
        try {
            $urls = $dom->getXpath()->query('descendant::g-link', $node);
            $item = [];

            $urlOnAttribute  = false;

            // If this is a SupercatRecipeClusterTitle, look for g-link in the next sibling of the parent
            if ($urls->length == 0 && $node->getAttribute('data-attrid') === 'SupercatRecipeClusterTitle') {
                $parent = $node->parentNode;
                if ($parent && $parent->nextSibling) {
                    $urls = $dom->getXpath()->query('descendant::g-link', $parent->nextSibling);

                    // Mobile SERP variant: recipe links use <a class="ddkIM"> instead of g-link
                    if ($urls->length == 0) {
                        $urls = $dom->getXpath()->query("descendant::a[contains(@class, 'ddkIM')]", $parent->nextSibling);
                        $urlOnAttribute = 'ddkIM';
                    }
                }
            }

            if ($urls->length == 0) {
                $urlOnAttribute =  true;
                $urls = $dom->getXpath()->query('descendant::a[@data-rl]', $node);
            }

            if ($urls->length == 0) {
                $urlOnAttribute = false;
                $urls = $dom->getXpath()->query("descendant::div[@jsname='Gbzile']", $node);
            }

            if ($urls->length > 0) {
                foreach ($urls as $urlNode) {
                    if ($urlOnAttribute === 'ddkIM') {
                        $item['recipes_links'][] = ['link' => $urlNode->getAttribute('href')];
                    } elseif ($urlOnAttribute) {
                        $item['recipes_links'][] = ['link' => $urlNode->getAttribute('data-rl')];
                    } else {
                        // firstChild may be a DOMText (whitespace) rather than the anchor — look it up via XPath instead.
                        $anchor = $dom->getXpath()->query('descendant::a[@href]', $urlNode)->item(0);
                        if ($anchor instanceof \DOMElement) {
                            $item['recipes_links'][] = ['link' => $anchor->getAttribute('href')];
                        }
                    }

                }

                if (!empty($item['recipes_links'])) {
                    $resultSet->addItem(new BaseResult(NaturalResultType::RECIPES_GROUP , $item, $node, $this->hasSerpFeaturePosition, $this->hasSideSerpFeaturePosition));
                }
            }
        } catch (\Throwable $e) {
            // Recipe SERPFeature parsing must never break the rest of SERP processing.
            if (function_exists('\\Sentry\\captureException')) {
                \Sentry\captureException($e);
            }
        }
    }
}
