<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser;

use Monolog\Logger;
use Serps\Core\Serp\CompositeResultSet;
use Serps\SearchEngine\Google\Page\GoogleDom;

abstract class AbstractAdwordsParser implements ParserInterface
{

    /**
     * @var ParserInterface[]
     */
    private $parsers = null;

    /**
     * @var Logger|null
     */
    protected $logger;

    public function __construct(Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Generate a list of parsers to be used when parsing dom
     * @return ParserInterface[]
     */
    abstract public function generateParsers();

    /**
     * @return ParserInterface[]
     */
    public function getParsers()
    {
        if (null == $this->parsers) {
            $this->parsers = $this->generateParsers();
        }
        return $this->parsers;
    }

    /**
     * @param array $doNotRemoveSrsltidForDomains
     * @inheritdoc
     */
    public function parse(GoogleDom $googleDom, array $doNotRemoveSrsltidForDomains = [])
    {
        $resultsSets = new CompositeResultSet();

        $parsers = $this->getParsers();

        foreach ($parsers as $parser) {
            $resultsSets->addResultSet(
                $parser->parse($googleDom, $doNotRemoveSrsltidForDomains)
            );
        }

        return $resultsSets;
    }
}
