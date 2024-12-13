<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser;

use Serps\Core\Serp\ResultSetInterface;
use Serps\SearchEngine\Google\Page\GoogleDom;

interface ParserInterface
{

    /**
     * @param GoogleDom $googleDom
     * @param array $doNotRemoveSrsltidForDomains
     * @return ResultSetInterface
     */
    public function parse(GoogleDom $googleDom, array $doNotRemoveSrsltidForDomains = []);
}
