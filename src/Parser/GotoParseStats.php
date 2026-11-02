<?php

namespace Serps\SearchEngine\Google\Parser;

/**
 * Per-parse tally of /goto?url= outcomes the parsed result set cannot show.
 *
 * A domain fallback is visible afterwards (the result carries
 * used_goto_domain_link, AIO citations carry sge_widget_diagnostics), but an
 * organic result dropped by ClassicalResultEngine's goto backstop leaves no
 * trace in the result set. The rules know nothing about the crawl (provider,
 * device), so they only count here; the crawl consumer calls reset() before
 * getNaturalResults() and take() after it, and owns the reporting.
 *
 * Static on purpose: a CLI consumer parses one page at a time, and the
 * parsing rules are built deep inside the parser with no context to pass a
 * collector through.
 */
class GotoParseStats
{
    /** Organic results still wrapped in /goto?url= after every rule ran: dropped, link lost. */
    public static $organicDropped = 0;

    public static function reset()
    {
        self::$organicDropped = 0;
    }

    /**
     * Current counts, then reset.
     *
     * @return array{organic_dropped: int}
     */
    public static function take()
    {
        $counts = ['organic_dropped' => self::$organicDropped];
        self::reset();

        return $counts;
    }
}
