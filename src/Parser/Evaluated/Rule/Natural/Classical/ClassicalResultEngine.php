<?php

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural\Classical;

use Monolog\Logger;
use Serps\Core\Serp\BaseResult;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\NaturalResultType;
use Serps\SearchEngine\Google\Page\GoogleDom;
use Serps\SearchEngine\Google\Parser\ParsingRuleByVersionInterface;

class ClassicalResultEngine
{
    use \Serps\SearchEngine\Google\Parser\Helper\Log;

    protected $resultType = NaturalResultType::CLASSICAL;

    /**
     * @param Logger|null $logger Monolog log channel dependency
     */
    public function __construct(Logger $logger = null)
    {
        $this->initLogger($logger);
    }

    protected function parseNode(GoogleDom $dom, \DomElement $organicResult, IndexedResultSet $resultSet, $k, array $doNotRemoveSrsltidForDomains = []) {}

    /**
     * @return OrganicResultObject|null Returns the parsed object or null if parsing failed
     */
    protected function parseNodeWithRules(GoogleDom $dom, \DomElement $organicResult, IndexedResultSet $resultSet, $k, array $doNotRemoveSrsltidForDomains = [])
    {
        $organicResultObject = new OrganicResultObject();

        /** @var ParsingRuleByVersionInterface $versionRule */
        foreach ($this->getRules() as $versionRule) {

            try {
                $versionRule->parseNode($dom, $organicResult, $organicResultObject, $doNotRemoveSrsltidForDomains);
            } catch (\Throwable $exception) {
                continue;
            }
            // A still-wrapped Google goto link (/goto?url=<opaque-token>) is not a
            // usable destination — the *Goto version rules resolve it from the
            // result's cite/role=text breadcrumb. An earlier rule (e.g. MobileV8)
            // can fill title+link+description with the goto link still in place;
            // treat that as incomplete so the loop falls through to the goto rule
            // instead of breaking and leaving google.com/goto as the result URL.
            if (!empty($organicResultObject->getDescription())
                && !empty($organicResultObject->getLink())
                && !empty($organicResultObject->getTitle())
                && strpos($organicResultObject->getLink(), '/goto?url=') === false) {
                break;
            }
        }

        if ($organicResultObject->getLink() === null || $organicResultObject->getTitle() === null) {

            $resultSet->addItem(new BaseResult(NaturalResultType::EXCEPTIONS, [], $organicResult));
            //$this->monolog->error('Cannot identify natural result', ['class' => self::class]);

            return null;
        }

        if (strpos($organicResultObject->getLink(), 'google.') !== false && strpos($organicResultObject->getLink(), 'https://developers.google.') !== 0 && strpos($organicResultObject->getLink(), '/search') !== false ) {
            return null;
        }
        // Backstop: a goto link the *Goto rule could not resolve (no usable
        // breadcrumb) is still a google.com/goto?url=<token> wrapper, never a
        // real competitor — drop it instead of storing google.com as the result.
        if (strpos($organicResultObject->getLink(), '/goto?url=') !== false) {
            return null;
        }
        $imbricatorParent = $dom->xpathQuery("ancestor::*[@class='FxLDp']", $organicResult);

        $reviewsAndPricingNodes = $dom->xpathQuery("descendant::*[@class='fG8Fp uo4vr']", $organicResult);
        $hasPricing = false;
        $reviewsAndPricing = false;
        if ($reviewsAndPricingNodes->length > 0) {
            $reviewsAndPricing = $reviewsAndPricingNodes->getNodeAt(0)->textContent;
            preg_match('([0-9,]+(\xC2\xA0)[A-Z]{0,3})',$reviewsAndPricingNodes->getNodeAt(0)->textContent, $priceMatches);
            if (!empty($priceMatches)) {
                $hasPricing = true;
            }
        }
        $hasArticleNodes = $dom->xpathQuery("descendant::*[@class='MUxGbd wuQ4Ob WZ8Tjf']", $organicResult);
        $hasArticleDate = false;
        if ($hasArticleNodes->length > 0) {
            $hasArticleDate = $hasArticleNodes->getNodeAt(0)->textContent;
        }
        $resultSet->addItem(new BaseResult(
            [$this->resultType],
            [
                'title'       => $organicResultObject->getTitle(),
                'url'         => $organicResultObject->getLink(),
                'description' => $organicResultObject->getDescription(),
                'imbricated'  => ($imbricatorParent->length > 0),
                'reviewsAndPricing' => $reviewsAndPricing,
                'hasPricing' => $hasPricing,
                'articleDate' => $hasArticleDate
            ],
            $organicResult
        ));

        return $organicResultObject;
    }
}
