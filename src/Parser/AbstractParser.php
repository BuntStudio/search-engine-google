<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser;

use Monolog\Logger;
use Serps\Core\Dom\DomNodeList;
use Serps\Core\Serp\IndexedResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;

abstract class AbstractParser implements ParserInterface
{

    /**
     * @var ParsingRuleInterface[]
     */
    private $rules = null;

    /**
     * @var Logger|null
     */
    protected $logger = null;

    public function __construct(Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @return ParsingRuleInterface[]
     */
    abstract protected function generateRules();

    /**
     * @param GoogleDom $googleDom
     * @return DomNodeList
     */
    abstract protected function getParsableItems(GoogleDom $googleDom);


    /**
     * @return ParsingRuleInterface[]
     */
    public function getRules()
    {
        if (null == $this->rules) {
            $this->rules = $this->generateRules();
        }
        return $this->rules;
    }

    /**
     * Parses the given google dom
     * @param GoogleDom $googleDom
     * @param array $doNotRemoveSrsltidForDomains
     * @param int $useDbRules 0 = hardcoded only, 1 = DB rules only, 2 = both (compare)
     * @param int|null $additionalRule Optional rule ID to test (for candidate rules)
     * @return IndexedResultSet
     */
    public function parse(GoogleDom $googleDom, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        $elementGroups = $this->getParsableItems($googleDom);

        $resultSet = $this->createResultSet($googleDom);
        return $this->parseGroups($elementGroups, $resultSet, $googleDom, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule);
    }

    /**
     * Defines what resultset to use for results
     * @param GoogleDom $googleDom
     * @return IndexedResultSet
     */
    protected function createResultSet(GoogleDom $googleDom)
    {
        $startingAt = (int) $googleDom->getUrl()->getParamValue('start', 0);
        return new IndexedResultSet($startingAt + 1);
    }

    /**
     * @param DomNodeList $elementGroups
     * @param IndexedResultSet $resultSet
     * @param $googleDom
     * @param array $doNotRemoveSrsltidForDomains
     * @param int $useDbRules 0 = hardcoded only, 1 = DB rules only, 2 = both (compare)
     * @param int|null $additionalRule Optional rule ID to test (for candidate rules)
     * @return IndexedResultSet
     */
    protected function parseGroups(DomNodeList $elementGroups, IndexedResultSet $resultSet, $googleDom, array $doNotRemoveSrsltidForDomains = [], $useDbRules = 0, $additionalRule = null)
    {
        $rules = $this->getRules();

        foreach ($elementGroups as $group) {
            if (!($group instanceof \DOMElement)) {
                continue;
            }

            if(in_array($group->tagName, ['hr', 'g-more-link'])) {
                continue;
            }

            foreach ($rules as $rule) {

                $match = $rule->match($googleDom, $group);

                if ($match instanceof \DOMNodeList) {
                    $this->parseGroups(new DomNodeList($match, $googleDom), $resultSet, $googleDom, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule);
                    break;
                } elseif ($match instanceof DomNodeList) {
                    $this->parseGroups($match, $resultSet, $googleDom, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule);
                    break;
                } else {

                    switch ($match) {
                        case ParsingRuleInterface::RULE_MATCH_MATCHED:
                            $rule->parse($googleDom, $group, $resultSet, $this->isMobile, $doNotRemoveSrsltidForDomains, $useDbRules, $additionalRule);
                            break 2;
                        case ParsingRuleInterface::RULE_MATCH_STOP:
                            break 2;
                    }
                }
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        return $resultSet;
    }
}
