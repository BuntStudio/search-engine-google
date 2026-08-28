<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated;

/**
 * Compiles the DB match rules that NaturalParser / MobileNaturalParser append to
 * the getParsableItems catch-all into cheap PHP tests, so hardcoded-mode's fast
 * selector can be used in DB-rules mode (1 and 3) as well.
 *
 * WHY THIS EXISTS
 *
 * getParsableItems builds `//*[ <~50 static branches> or <~40 DB rule branches> ]`.
 * libxml evaluates every or-branch against every element of the page, so the cost
 * is the branch count times the node count: measured on parsedc1 over 80 live
 * SERPs, 136ms (mobile) / 180ms (desktop) per page in MODE_DATABASE, which is the
 * single largest item in the parse. FastParsableItemsSelector already replaces the
 * static half with an attribute prefilter plus a PHP pass; this class does the same
 * for the DB half, which since the 2026-08-26 removal of the mode enum is the half
 * production actually runs.
 *
 * WHAT IT GUARANTEES
 *
 * The compiler is deliberately conservative and total: every rule it is given ends
 * up in exactly one of four buckets, and the union of the four reproduces the
 * legacy or-chain's node set exactly.
 *
 *   1. atoms      - single attribute tests, folded into hash sets / one compiled
 *                   regex per attribute per kind, evaluated in the PHP pass.
 *   2. conj       - conjunctions of those tests (e.g. the videos_mobile_match
 *                   three-class rules), evaluated as short lists in the PHP pass.
 *   3. hoist      - a rule that is exactly `descendant::<step>`: the equivalent
 *                   node set is computed ONCE for the page and the ancestors of
 *                   the matches are marked, instead of re-running a subtree scan
 *                   per element. visual_digest_mobile alone measured 13.0ms/page.
 *   4. residual   - anything the grammar below does not cover, verbatim, in one
 *                   small XPath query. Correctness never depends on the compiler
 *                   understanding a rule: not recognised means not compiled.
 *
 * THE GRAMMAR
 *
 * A rule compiles when it is a `|`-union of `self::<tag>[pred]...` steps whose
 * predicates are an `or` of `and`s of these atoms, and nothing else:
 *
 *   @a                                                  attribute exists
 *   @a='v'   @a="v"                                     equality
 *   contains(@a, 'v')                                   substring
 *   starts-with(@a, 'v')                                prefix
 *   contains(concat(' ', normalize-space(@a), ' '), 'v')  padded, normalised
 *   contains(concat(' ', @a, ' '), 'v')                   padded, raw
 *   child::tag                                          has an immediate child
 *   descendant::step   .//step                          has such a descendant
 *   ancestor::step                                      has such an ancestor
 *   not(<any of the above>)                             negation
 *
 * A parenthesised `or` nested inside an `and` is NOT distributed (that is where
 * featured_snippet_match lands) - the whole rule goes residual instead.
 *
 * WHY THE AXIS ATOMS PAY
 *
 * `@jscontroller='U6XW6' and descendant::div[starts-with(@id,'hepl-')]` costs 1.05ms
 * as an or-branch because libxml runs the subtree scan on every element that fails
 * the attribute test. As a conjunction it costs nothing: the atoms are evaluated in
 * source order with short-circuit, so the scan is reached only by the handful of
 * U6XW6 elements. The descendant sets are precomputed once per page anyway (the
 * ancestors of one global query's matches), and ancestor tests walk parentNode from
 * the element, which is bounded by tree depth.
 *
 * A conjunction must contain at least one POSITIVE attribute or child atom, or it
 * is refused: without one, an element carrying no attributes at all could satisfy
 * it, and such an element never enters the prefilter node set. Rules that are a
 * bare `descendant::...` are exempt because they go through the hoist bucket, whose
 * results are merged separately (see FastParsableItemsSelector).
 *
 * The two padded forms keep XPath's exact semantics: the needle is matched against
 * the value with a space glued to each end, so `' X '` is a whole-word test and
 * `'X'` stays a plain substring test. normalize-space collapses runs of XML
 * whitespace (space, tab, CR, LF) and trims - reproduced here rather than
 * approximated with a word-boundary regex, because several needles are multi-word
 * (' lr_container yc7KLc mBNN3d ') and would not survive the approximation.
 *
 * Rules come from the DB through RuleLoaderService and change without a deploy
 * (self-healing writes them; Redis caches them for 60s), so the compiler is driven
 * entirely by the rule text - there is no per-rule table to keep in sync. A rule
 * shape nobody anticipated costs one residual branch, never a wrong node set.
 */
class DbMatchRuleCompiler
{
    /** Compiled rule sets, keyed by a hash of the rule list. */
    private static $cache = array();

    /** Rule lists are per-device and change at most every 60s; 8 is plenty. */
    const CACHE_LIMIT = 8;

    /**
     * @param string[] $rules raw xpath_rule strings, in the order the parser appends them
     * @return array compiled form, see the class docblock
     */
    public static function compile(array $rules)
    {
        if (empty($rules)) {
            return self::emptyProgram();
        }
        $key = md5(implode("\x00", $rules));
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $p = self::emptyProgram();
        foreach ($rules as $rule) {
            if (!self::compileRule($rule, $p)) {
                $p['residual'][] = $rule;
            }
        }
        self::finalize($p);

        if (count(self::$cache) >= self::CACHE_LIMIT) {
            self::$cache = array();
        }
        self::$cache[$key] = $p;
        return $p;
    }

    private static function emptyProgram()
    {
        return array(
            'attrs' => array(),     // attribute name => true, for the prefilter
            'exists' => array(),    // attribute name => true
            'eq' => array(),        // attribute => [value => 1]
            'sub' => array(),       // attribute => [needle, ...] -> regex after finalize
            'pre' => array(),       // attribute => [needle, ...] -> regex after finalize
            'padn' => array(),      // attribute => [needle, ...] -> regex after finalize
            'padr' => array(),      // attribute => [needle, ...] -> regex after finalize
            'childTags' => array(),      // child element name => 1, a match ON ITS OWN
            'childIndexTags' => array(), // every child element name mentioned anywhere,
                                         // so the pass can build the index and widen the
                                         // prefilter without those names also counting as
                                         // standalone matches
            'conj' => array(),      // [['tag' => string|null, 'atoms' => [[kind, attr, needle, negated], ...]], ...]
            'descSteps' => array(), // absolute xpath => 1, precomputed as "has such a descendant"
            'ancSteps' => array(),  // absolute xpath => 1, precomputed as a match set to walk up into
            'hoist' => array(),     // absolute xpath whose matches' ancestors are hits
            'residual' => array(),  // uncompiled rule text, verbatim
            'empty' => true,
        );
    }

    /** Turn the collected needle lists into one compiled regex per attribute. */
    private static function finalize(array &$p)
    {
        foreach (array('sub', 'pre', 'padn', 'padr') as $kind) {
            foreach ($p[$kind] as $attr => $needles) {
                $alt = array();
                foreach (array_unique($needles) as $needle) {
                    $alt[] = preg_quote($needle, '/');
                }
                $anchor = ($kind === 'pre') ? '\A' : '';
                $p[$kind][$attr] = '/' . $anchor . '(?:' . implode('|', $alt) . ')/S';
            }
        }
        $p['empty'] = empty($p['attrs']) && empty($p['childTags']) && empty($p['childIndexTags'])
            && empty($p['conj']) && empty($p['hoist']) && empty($p['residual']);
    }

    /**
     * @return bool true if the rule was fully absorbed into $p
     */
    private static function compileRule($rule, array &$p)
    {
        $rule = self::collapseWhitespaceOutsideQuotes($rule);
        if ($rule === '') {
            return true; // nothing to select
        }

        // A rule that is exactly `descendant::<step>` is a "does this element have
        // such a descendant" test; hoist it (see class docblock, bucket 3).
        if (strncmp($rule, 'descendant::', 12) === 0) {
            return self::compileHoist(substr($rule, 12), $p);
        }

        $staged = self::emptyProgram();
        foreach (self::splitTop($rule, '|') as $alternative) {
            if (!self::compileStep(trim($alternative), $staged)) {
                return false;
            }
        }
        self::mergeProgram($staged, $p);
        return true;
    }

    /** `descendant::*[...]` -> `//*[...]`, matches' ancestors become hits. */
    private static function compileHoist($step, array &$p)
    {
        $q = self::stepToQuery($step, true);
        if ($q === null) {
            return false;
        }
        $p['hoist'][] = $q;
        return true;
    }

    /**
     * Rewrite a relative step as the absolute query with the same matches.
     *
     * Sound because the axis is only ever used as an existence test: the set of
     * elements having a `descendant::S` is exactly the set of ancestors of `//S`,
     * and an element has an `ancestor::S` exactly when one of its parentNode chain
     * is in `//S`. That equivalence breaks if the step is position-dependent or
     * refers to the context node, so those are refused.
     *
     * @param bool $allowPath descendant paths may have further steps
     *        (`descendant::div[..]/div[..]`); ancestor steps may not.
     * @return string|null
     */
    private static function stepToQuery($step, $allowPath)
    {
        $step = trim($step);
        if ($step === ''
            || strpos($step, 'position(') !== false
            || strpos($step, 'last(') !== false
            || strpos($step, '::') !== false
            || preg_match('/(?<![\w.-])\.\.?(?![\w-])/', $step)
        ) {
            return null;
        }
        if (!$allowPath && strpos($step, '/') !== false) {
            return null;
        }
        $query = '//' . $step;
        // Validate once, here, so the selector can build these sets lazily at
        // runtime without having to handle a malformed rule mid-pass.
        return self::isValidQuery($query) ? $query : null;
    }

    /** @return bool whether libxml can compile the expression at all. */
    private static function isValidQuery($query)
    {
        static $probe = null;
        if ($probe === null) {
            $doc = new \DOMDocument();
            $doc->appendChild($doc->createElement('root'));
            $probe = new \DOMXPath($doc);
        }
        $previous = libxml_use_internal_errors(true);
        $ok = @$probe->query($query) !== false;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $ok;
    }

    /** One `self::tag[pred][pred]` step. */
    private static function compileStep($step, array &$p)
    {
        if (strncmp($step, 'self::', 6) !== 0) {
            return false;
        }
        $rest = substr($step, 6);
        $bracket = strpos($rest, '[');
        if ($bracket === false) {
            $tag = $rest;
            $preds = array();
        } else {
            $tag = substr($rest, 0, $bracket);
            $preds = self::splitPredicates(substr($rest, $bracket));
            if ($preds === null) {
                return false;
            }
        }
        if ($tag !== '*' && !preg_match('/\A[A-Za-z][\w-]*\z/', $tag)) {
            return false;
        }
        $tag = ($tag === '*') ? null : $tag;

        if (empty($preds)) {
            return false; // `self::*` alone selects every element; never a real rule
        }

        // Chained predicates are an implicit AND; a top-level `or` inside any of
        // them splits the step into independent alternatives.
        if (count($preds) === 1) {
            $branches = self::splitTop($preds[0], 'or');
        } else {
            $branches = array(implode(' and ', array_map(function ($x) {
                return '(' . $x . ')';
            }, $preds)));
        }

        foreach ($branches as $branch) {
            $atoms = array();
            foreach (self::splitTop($branch, 'and') as $factor) {
                $factor = self::unwrapParens(trim($factor));
                $negated = false;
                if (strncmp($factor, 'not(', 4) === 0 && substr($factor, -1) === ')') {
                    $inner = self::unwrapParens(trim(substr($factor, 4, -1)));
                    // not() over a node set is "empty" and over a boolean is
                    // negation; for every atom below the two coincide.
                    if (count(self::splitTop($inner, 'or')) === 1
                        && count(self::splitTop($inner, 'and')) === 1
                    ) {
                        $factor = $inner;
                        $negated = true;
                    }
                }
                // A nested `or` under an `and` would need distribution; refuse.
                if (count(self::splitTop($factor, 'or')) > 1) {
                    return false;
                }
                $atom = self::parseAtom($factor);
                if ($atom === null) {
                    return false;
                }
                $atom[3] = $negated;
                $atoms[] = $atom;
            }
            if (empty($atoms) || !self::addBranch($tag, $atoms, $p)) {
                return false;
            }
        }
        return true;
    }

    /** Attribute atoms that pin an element into the prefilter node set. */
    private static $ANCHORING = array('eq' => 1, 'sub' => 1, 'pre' => 1, 'padn' => 1, 'padr' => 1, 'exists' => 1);

    /**
     * A single positive attribute branch folds into the hash sets; anything longer
     * stays a list evaluated in source order.
     *
     * @return bool false when the branch cannot be admitted (see the class
     *         docblock: every conjunction needs a positive attribute or child atom,
     *         or an attribute-less element could satisfy it outside the prefilter).
     */
    private static function addBranch($tag, array $atoms, array &$p)
    {
        $anchored = false;
        foreach ($atoms as $atom) {
            if ($atom[3]) {
                continue;
            }
            if ($atom[0] === 'child' || isset(self::$ANCHORING[$atom[0]])) {
                $anchored = true;
                break;
            }
        }
        if (!$anchored) {
            return false;
        }

        if ($tag === null && count($atoms) === 1 && !$atoms[0][3]) {
            list($kind, $attr, $needle) = $atoms[0];
            if ($kind === 'child') {
                // The only case where having such a child is, by itself, a match.
                $p['childTags'][$needle] = 1;
                $p['childIndexTags'][$needle] = 1;
                return true;
            }
            $p['attrs'][$attr] = true;
            if ($kind === 'exists') {
                $p['exists'][$attr] = true;
            } elseif ($kind === 'eq') {
                $p['eq'][$attr][$needle] = 1;
            } else {
                $p[$kind][$attr][] = $needle;
            }
            return true;
        }

        foreach ($atoms as $atom) {
            switch ($atom[0]) {
                case 'child':
                    // Index only. Putting it in childTags would make "has such a
                    // child" a match on its own and drop the rest of the
                    // conjunction: `[@jscontroller='X' and child::span]` would
                    // then select every element on the page with a span child.
                    $p['childIndexTags'][$atom[2]] = 1;
                    break;
                case 'desc':
                    $p['descSteps'][$atom[1]] = 1;
                    break;
                case 'anc':
                    $p['ancSteps'][$atom[1]] = 1;
                    break;
                default:
                    $p['attrs'][$atom[1]] = true;
            }
        }
        $p['conj'][] = array('tag' => $tag, 'atoms' => $atoms);
        return true;
    }

    /**
     * @return array{0:string,1:string,2:string,3:bool}|null
     *         [kind, attribute-or-query, needle, negated]. The negated flag is
     *         filled in by the caller.
     */
    private static function parseAtom($s)
    {
        $m = array();

        // Axis atoms carry a query in the attribute slot rather than a name.
        if (strncmp($s, 'descendant::', 12) === 0) {
            $q = self::stepToQuery(substr($s, 12), true);
            return $q === null ? null : array('desc', $q, '', false);
        }
        if (strncmp($s, './/', 3) === 0) {
            $q = self::stepToQuery(substr($s, 3), true);
            return $q === null ? null : array('desc', $q, '', false);
        }
        if (strncmp($s, 'ancestor::', 10) === 0) {
            $q = self::stepToQuery(substr($s, 10), false);
            return $q === null ? null : array('anc', $q, '', false);
        }

        if (preg_match('/\A@([A-Za-z_][\w-]*)\z/', $s, $m)) {
            return array('exists', $m[1], '', false);
        }
        if (preg_match('/\A@([A-Za-z_][\w-]*) ?= ?\'([^\']*)\'\z/', $s, $m)
            || preg_match('/\A@([A-Za-z_][\w-]*) ?= ?"([^"]*)"\z/', $s, $m)
        ) {
            return array('eq', $m[1], $m[2], false);
        }
        if (preg_match('/\Acontains\(@([A-Za-z_][\w-]*), ?\'([^\']*)\'\)\z/', $s, $m)
            || preg_match('/\Acontains\(@([A-Za-z_][\w-]*), ?"([^"]*)"\)\z/', $s, $m)
        ) {
            return array('sub', $m[1], $m[2], false);
        }
        if (preg_match('/\Astarts-with\(@([A-Za-z_][\w-]*), ?\'([^\']*)\'\)\z/', $s, $m)
            || preg_match('/\Astarts-with\(@([A-Za-z_][\w-]*), ?"([^"]*)"\)\z/', $s, $m)
        ) {
            return array('pre', $m[1], $m[2], false);
        }
        if (preg_match('/\Acontains\(concat\(\' \', ?normalize-space\(@([A-Za-z_][\w-]*)\), ?\' \'\), ?\'([^\']*)\'\)\z/', $s, $m)) {
            return array('padn', $m[1], $m[2], false);
        }
        if (preg_match('/\Acontains\(concat\(\' \', ?@([A-Za-z_][\w-]*), ?\' \'\), ?\'([^\']*)\'\)\z/', $s, $m)) {
            return array('padr', $m[1], $m[2], false);
        }
        if (preg_match('/\Achild::([A-Za-z][\w-]*)\z/', $s, $m)) {
            return array('child', '', $m[1], false);
        }
        return null;
    }

    private static function mergeProgram(array $from, array &$into)
    {
        foreach (array('exists', 'childTags', 'childIndexTags') as $k) {
            foreach ($from[$k] as $key => $_) {
                $into[$k][$key] = true;
            }
        }
        foreach ($from['attrs'] as $attr => $_) {
            $into['attrs'][$attr] = true;
        }
        foreach ($from['eq'] as $attr => $vals) {
            foreach ($vals as $v => $_) {
                $into['eq'][$attr][$v] = 1;
            }
        }
        foreach (array('sub', 'pre', 'padn', 'padr') as $kind) {
            foreach ($from[$kind] as $attr => $needles) {
                foreach ($needles as $needle) {
                    $into[$kind][$attr][] = $needle;
                }
            }
        }
        foreach ($from['conj'] as $c) {
            $into['conj'][] = $c;
        }
        foreach (array('descSteps', 'ancSteps') as $k) {
            foreach ($from[$k] as $q => $_) {
                $into[$k][$q] = 1;
            }
        }
        foreach ($from['hoist'] as $h) {
            $into['hoist'][] = $h;
        }
    }

    // ---- text handling -------------------------------------------------------

    /**
     * Collapse whitespace runs, but only outside string literals: the needles
     * themselves are significant (' images universal ') and must survive intact.
     */
    private static function collapseWhitespaceOutsideQuotes($s)
    {
        $out = '';
        $quote = null;
        $len = strlen($s);
        $pendingSpace = false;
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($quote !== null) {
                $out .= $c;
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                if ($pendingSpace) {
                    $out .= ' ';
                    $pendingSpace = false;
                }
                $quote = $c;
                $out .= $c;
                continue;
            }
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") {
                $pendingSpace = ($out !== '');
                continue;
            }
            if ($pendingSpace) {
                $out .= ' ';
                $pendingSpace = false;
            }
            $out .= $c;
        }
        return $out;
    }

    /**
     * Split on a top-level operator: outside quotes, outside () and [].
     * $op is 'or', 'and' or '|'.
     */
    private static function splitTop($s, $op)
    {
        $isWord = ($op !== '|');
        $needle = $isWord ? ' ' . $op . ' ' : '|';
        $nlen = strlen($needle);
        $parts = array();
        $depth = 0;
        $quote = null;
        $start = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($quote !== null) {
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                continue;
            }
            if ($c === '(' || $c === '[') {
                $depth++;
                continue;
            }
            if ($c === ')' || $c === ']') {
                $depth--;
                continue;
            }
            if ($depth === 0 && $c === $needle[0] && substr($s, $i, $nlen) === $needle) {
                $parts[] = substr($s, $start, $i - $start);
                $i += $nlen - 1;
                $start = $i + 1;
            }
        }
        $parts[] = substr($s, $start);
        return $parts;
    }

    /**
     * `[a][b]` -> ['a', 'b']; null when the brackets do not balance.
     * @return array|null
     */
    private static function splitPredicates($s)
    {
        $preds = array();
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            if ($s[$i] !== '[') {
                return null;
            }
            $depth = 0;
            $quote = null;
            $start = $i + 1;
            for (; $i < $len; $i++) {
                $c = $s[$i];
                if ($quote !== null) {
                    if ($c === $quote) {
                        $quote = null;
                    }
                    continue;
                }
                if ($c === "'" || $c === '"') {
                    $quote = $c;
                    continue;
                }
                if ($c === '[') {
                    $depth++;
                } elseif ($c === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $preds[] = substr($s, $start, $i - $start);
                        $i++;
                        break;
                    }
                }
            }
            if ($depth !== 0) {
                return null;
            }
        }
        return $preds;
    }

    private static function unwrapParens($s)
    {
        while (strlen($s) > 1 && $s[0] === '(' && substr($s, -1) === ')') {
            $inner = substr($s, 1, -1);
            // only unwrap when those two parens actually pair with each other
            $depth = 0;
            $quote = null;
            $ok = true;
            for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
                $c = $inner[$i];
                if ($quote !== null) {
                    if ($c === $quote) {
                        $quote = null;
                    }
                    continue;
                }
                if ($c === "'" || $c === '"') {
                    $quote = $c;
                } elseif ($c === '(') {
                    $depth++;
                } elseif ($c === ')') {
                    if (--$depth < 0) {
                        $ok = false;
                        break;
                    }
                }
            }
            if (!$ok || $depth !== 0) {
                return $s;
            }
            $s = trim($inner);
        }
        return $s;
    }
}
