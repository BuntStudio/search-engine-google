<?php
/**
 * @license see LICENSE
 */

namespace Serps\Test\TDD\SearchEngine\Google\Parser;

use Serps\SearchEngine\Google\Parser\GotoParseStats;

/**
 * @covers Serps\SearchEngine\Google\Parser\GotoParseStats
 */
class GotoParseStatsTest extends \PHPUnit_Framework_TestCase
{
    public function testTakeReturnsCountsAndResets()
    {
        GotoParseStats::reset();
        GotoParseStats::$organicDropped++;
        GotoParseStats::$organicDropped++;

        $this->assertSame(['organic_dropped' => 2], GotoParseStats::take());
        $this->assertSame(['organic_dropped' => 0], GotoParseStats::take());
    }

    public function testResetClearsAPreviousParse()
    {
        GotoParseStats::$organicDropped = 5;
        GotoParseStats::reset();

        $this->assertSame(0, GotoParseStats::$organicDropped);
    }
}
