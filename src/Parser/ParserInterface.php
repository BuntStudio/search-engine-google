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
     * @param string $doNotRemoveSrsltidForDomain
     * @return ResultSetInterface
     */
    public function parse(GoogleDom $googleDom, string $doNotRemoveSrsltidForDomain = '');
}
