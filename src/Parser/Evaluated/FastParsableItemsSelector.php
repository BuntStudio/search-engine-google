<?php
namespace Serps\SearchEngine\Google\Parser\Evaluated;

use Serps\SearchEngine\Google\Page\GoogleDom;

/**
 * Fast equivalent of the NaturalParser / MobileNaturalParser catch-all
 * getParsableItems XPath.
 *
 * The legacy selector is a single //*[p1 or p2 or ... or pN] query: libxml
 * evaluates every or-branch against every element, which measured ~50ms per SERP
 * for the static branches alone, and 136ms (mobile) / 180ms (desktop) once the DB
 * match rules are appended - about half of the whole parse. This class computes
 * the same node set as one cheap attribute-existence prefilter query plus a PHP
 * pass with hash lookups and compiled regexes.
 *
 * Two halves, because the query has two halves:
 *
 *   - the static branches hardcoded in the two parsers, mirrored by the config
 *     tables below (see PARSABLE_ITEMS_SYNC);
 *   - the DB match rules the parsers append in modes 1 and 3, handed in as
 *     $dbRules and compiled by DbMatchRuleCompiler. Rules it does not recognise
 *     stay in a small residual XPath query, so an unfamiliar rule shape costs one
 *     branch rather than correctness.
 *
 * Correctness of the static half rests on two invariants:
 *
 * 1. COMPLETENESS OF THE PREFILTER: every or-branch requires the element to carry
 *    one of the attributes listed in the config (id / class / jscontroller /
 *    jsname / data-attrid / data-kpid) or to have one of the listed child elements
 *    (product-viewer-group, video-voyager, inline-video) - with exactly two
 *    exceptions, probed below. The prefilter is widened at runtime with any
 *    attribute the compiled DB rules reference, so the same invariant holds for
 *    them by construction.
 *
 * 2. RARE-FEATURE FALLBACK: the two branches whose matches may carry none of those
 *    attributes (mobile `descendant::*[contains(@data-attrid,'VisualDigest')]`
 *    selects bare ancestors; desktop kp-wholepage+AIRFARES likewise) are handled by
 *    probing for the underlying feature marker first. If the marker is on the page,
 *    select() returns null and the caller MUST run the legacy query.
 *
 * Nodes that only the residual query or a hoisted descendant test can find may sit
 * outside the prefilter set; they are merged back in document order (rare path,
 * see mergeInDocumentOrder).
 *
 * DUAL-MAINTENANCE WARNING: the legacy XPath strings in NaturalParser /
 * MobileNaturalParser remain the source of truth for the fallback and for the
 * static half. Any new *static* selector added there MUST also be added to the
 * matching config here, or parsing will silently stop selecting that container.
 * Grep marker: PARSABLE_ITEMS_SYNC. DB rules need no such care - they are compiled
 * from their own text at runtime.
 *
 * Iteration note: the PHP pass iterates a DOMXPath snapshot, never
 * DOMDocument::getElementsByTagName('*') - the latter is a live list whose item(i)
 * re-walks the tree (O(n^2) overall, measured 11ms just to iterate a 950-element
 * SERP).
 */
class FastParsableItemsSelector
{
    /**
     * @param GoogleDom $googleDom
     * @param bool $mobile which parser's selector semantics to reproduce
     * @param string[] $dbRules DB match rules the caller would have appended to
     *        the legacy or-chain, in the same order
     * @return \DOMElement[]|null node set in document order, or null when a probed
     *         rare feature is present and the caller must run the legacy XPath.
     */
    public static function select(GoogleDom $googleDom, $mobile, array $dbRules = array())
    {
        $xpath = new \DOMXPath($googleDom->getDom());
        // Several of the queries below repeat (the mobile rare-feature probe and
        // the visual_digest_mobile hoist are the same expression), and a full-page
        // scan is expensive enough to be worth not doing twice.
        $queryCache = array();

        if ($mobile) {
            // Rare-feature probe (see class docblock, invariant 2).
            if (self::runQuery($xpath, "//*[contains(@data-attrid,'VisualDigest')]", $queryCache)->length > 0) {
                return null;
            }
            // PARSABLE_ITEMS_SYNC: mirrors MobileNaturalParser::getParsableItems.
            $ids = array(
                'iur' => 1, 'sports-app' => 1, 'center_col' => 1, 'tads' => 1, 'tadsb' => 1,
                'bottomads' => 1, 'oFNiHe' => 1, 'lud-ed' => 1, 'ofr' => 1, 'rso' => 1,
                'botstuff' => 1, 'eKIzJc' => 1,
            );
            $dataAttrid = array('images universal' => 1, 'SupercatRecipeClusterTitle' => 1);
            $jscontroller = array('G42bz' => 1, 'dGwZHb' => 1, 'U6XW6' => 1, 'h7XEsd' => 1, 'wuEeed' => 1);
            $jsname = array('MGJTwe' => 1, 'ZLxsqf' => 1);
            $jsnameContains = null;
            $classEquals = array(
                'C7r6Ue' => 1, 'xpdopen' => 1, 'BNeawe DwrKqd' => 1, 'IuoSj' => 1, 'xSoq1' => 1,
                'lU8tTd' => 1, 'cawG4b OvQkSb' => 1, 'uVMCKf mnr-c' => 1, 'pxiwBd' => 1,
            );
            $classContainsRe = '/scm-c|related-question-pair|qixVud|xxAJT|commercial-unit-mobile-top'
                . '|commercial-unit-mobile-bottom|osrp-blk|qs-io|ki5rnd|CWesnb'
                . '|gws-plugins-horizon-jobs__li-ed|L5NwLd|LQQ1Bd|uVMCKf Ww4FFb|HD8Pae mnr-c'
                . '|YJpHnb mnr-c|vtSz8d Ww4FFb vt6azd|EDblX HG5ZQb|hNKF2b'
                . '|lr_container wDYxhc yc7KLc|lr_container yc7KLc mBNN3d|kp-wholepage|e8Ck0d/S';
            $classWordRe = null;
            $checkKpid = true;
            $checkGSection = false;
            $checkIze = true;
            $baseChildTags = array('product-viewer-group', 'video-voyager', 'inline-video');
            $baseAttrs = array('id', 'class', 'jscontroller', 'jsname', 'data-attrid', 'data-kpid');
        } else {
            // Rare-feature probe (see class docblock, invariant 2).
            if (self::runQuery($xpath, "//div[@id='kp-wp-tab-cont-AIRFARES']", $queryCache)->length > 0) {
                return null;
            }
            // PARSABLE_ITEMS_SYNC: mirrors NaturalParser::getParsableItems.
            $ids = array(
                'rso' => 1, 'botstuff' => 1, 'rhs' => 1, 'iur' => 1, 'tads' => 1, 'tadsb' => 1,
                'tvcap' => 1, 'extabar' => 1, 'Odp5De' => 1, 'kp-wp-tab-cont-Latest' => 1,
                'oFNiHe' => 1, 'result-stats' => 1, 'kp-wp-tab-Latest' => 1, 'lud-ed' => 1,
                'ofr' => 1, 'bres' => 1, 'knowledge-currency__updatable-data-column' => 1,
                'eKIzJc' => 1,
            );
            $dataAttrid = array('SupercatRecipeClusterTitle' => 1);
            $jscontroller = array('h7XEsd' => 1, 'wuEeed' => 1, 'hKbgK' => 1, 'es75Cc' => 1);
            $jsname = array('MGJTwe' => 1, 'ZLxsqf' => 1);
            $jsnameContains = 'YWd0ec';
            $classEquals = array(
                'C7r6Ue' => 1, 'e4xoPb' => 1, 'WVGKWb' => 1, 'Qq3Lb' => 1, 'xpdopen' => 1,
                'BNeawe DwrKqd' => 1, 'H93uF' => 1, 'vqkKIe wHYlTd' => 1, 'x3SAYd' => 1,
                'ixix9e' => 1, 'RyIFgf' => 1, 'aviV4d' => 1, 'EyBRub' => 1, 'jhtnKe' => 1,
                'wDYxhc' => 1, 'XNfAUb' => 1, 'Ww4FFb' => 1, 'sATSHe' => 1,
            );
            $classContainsRe = '/lr_container yc7KLc mBNN3d|LQQ1Bd|CH6Bmd|zaTIWc|VT5Tde'
                . '|commercial-unit-desktop-top|cu-container|related-question-pair'
                . '|gws-plugins-horizon-jobs__li-ed|L5NwLd|e8Ck0d|vtSz8d|zJUuqf|KYLHhb|EDblX/S';
            // XPath contains(concat(' ', normalize-space(@class), ' '), ' X ') = whole-word
            // match with XPath whitespace (space/tab/CR/LF) as the delimiter set.
            $classWordRe = '/(?:^|[ \t\r\n])(?:eqAnXb|mA0j1c)(?:[ \t\r\n]|$)/S';
            $checkKpid = false;
            $checkGSection = true;
            $checkIze = false;
            $baseChildTags = array('video-voyager');
            $baseAttrs = array('id', 'class', 'jscontroller', 'jsname', 'data-attrid');
        }

        $db = DbMatchRuleCompiler::compile($dbRules);

        // The prefilter must admit every element any branch can select, so it is
        // widened with whatever the compiled DB rules look at.
        $attrs = $baseAttrs;
        foreach ($db['attrs'] as $attr => $_) {
            if (!in_array($attr, $attrs, true)) {
                $attrs[] = $attr;
            }
        }
        // Index and prefilter cover every child element name any branch mentions;
        // whether having such a child is a match ON ITS OWN is a separate question,
        // answered by $db['childTags'] alone (see matchesDb).
        $childTags = $baseChildTags;
        foreach (array($db['childTags'], $db['childIndexTags']) as $set) {
            foreach ($set as $tag => $_) {
                if (!in_array($tag, $childTags, true)) {
                    $childTags[] = $tag;
                }
            }
        }
        $prefilterParts = array();
        foreach ($attrs as $attr) {
            $prefilterParts[] = '@' . $attr;
        }
        foreach ($childTags as $tag) {
            $prefilterParts[] = $tag;
        }
        $prefilter = '//*[' . implode(' or ', $prefilterParts) . ']';

        // Parents matched through a has-child branch may carry no attributes at
        // all, so they enter via the child tests in the prefilter and this set.
        $childParents = new \SplObjectStorage();
        $childByTag = array();
        foreach ($childTags as $tag) {
            $childByTag[$tag] = new \SplObjectStorage();
        }
        $childNodes = $xpath->query('//' . implode('|//', $childTags));
        if ($childNodes === false) {
            return null; // let the legacy query be the judge
        }
        $baseChildSet = array_flip($baseChildTags);
        foreach ($childNodes as $node) {
            if (!($node->parentNode instanceof \DOMElement)) {
                continue;
            }
            // Per-tag index, consulted only by the branch that asked for that tag.
            $childByTag[$node->tagName]->attach($node->parentNode);
            // $childParents is the STATIC chain's has-child test. It must see only
            // the tags the static chain names: a DB rule mentioning child::span
            // would otherwise make every element with a span child a static match.
            if (isset($baseChildSet[$node->tagName])) {
                $childParents->attach($node->parentNode);
            }
        }

        // Everything the PHP pass cannot decide for itself: the uncompiled rules,
        // verbatim, plus the hoisted descendant tests.
        $extra = new \SplObjectStorage();
        if (!empty($db['residual'])) {
            $residual = $xpath->query(
                '//*[' . implode(' or ', $db['residual']) . '][not(self::script) and not(self::style)]'
            );
            if ($residual === false) {
                return null; // malformed rule text: let the legacy query be the judge
            }
            foreach ($residual as $node) {
                $extra->attach($node);
            }
        }
        // The walk memo must be SEPARATE from $extra. "Already marked" may only
        // short-circuit on a node some walk reached from below, because only then
        // is its whole ancestor chain known to be marked too. Residual matches are
        // attached to $extra without their ancestors, so memoizing against $extra
        // would stop a walk at a residual match and silently drop every ancestor
        // above it - a smaller node set than the legacy query, with no fallback.
        $walked = new \SplObjectStorage();
        foreach ($db['hoist'] as $query) {
            $targets = self::runQuery($xpath, $query, $queryCache);
            if ($targets === false) {
                return null;
            }
            foreach ($targets as $node) {
                for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
                    if ($walked->contains($p)) {
                        break; // this whole ancestor chain has been walked already
                    }
                    $walked->attach($p);
                    $tag = $p->tagName;
                    if ($tag !== 'script' && $tag !== 'style') {
                        $extra->attach($p);
                    }
                }
            }
        }

        // Axis-atom sets are built on FIRST USE, not up front: an axis atom is
        // always guarded by a cheaper attribute atom in the same conjunction, so on
        // a page with no U6XW6 element the hotels subtree scan never runs at all.
        // Eager construction measured 9.6ms/page on desktop for sets that were
        // mostly never consulted. DbMatchRuleCompiler has already validated every
        // one of these expressions, so a build here cannot fail.
        $axisSets = array();

        $hasDb = !$db['empty'];
        $extraSeen = 0;
        $out = array();
        $candidates = $xpath->query($prefilter);
        if ($candidates === false) {
            // Returning the empty array here would look like "this page has no
            // parsable items" to the caller and skip the fallback entirely.
            return null;
        }
        foreach ($candidates as $el) {
            $tag = $el->tagName;
            if ($tag === 'script' || $tag === 'style') {
                continue;
            }

            $vals = array();
            foreach ($attrs as $attr) {
                $vals[$attr] = $el->getAttribute($attr);
            }
            $class = $vals['class'];

            // --- static half ------------------------------------------------
            $hit = false;
            $id = $vals['id'];
            if ($id !== '' && isset($ids[$id])) {
                $hit = true;
            }
            if (!$hit && $class !== '') {
                if (isset($classEquals[$class]) || preg_match($classContainsRe, $class)) {
                    $hit = true;
                } elseif ($classWordRe !== null && preg_match($classWordRe, $class)) {
                    $hit = true;
                } elseif ($checkIze && strpos($class, 'IZE3Td') !== false
                    && $xpath->query(".//div[@data-attrid='images universal']", $el)->length > 0
                ) {
                    $hit = true;
                } elseif ($checkGSection && $tag === 'g-section-with-header'
                    && strpos($class, 'yG4QQe TBC9ub') !== false
                ) {
                    $hit = true;
                }
            }
            if (!$hit) {
                $value = $vals['data-attrid'];
                if ($value !== '' && isset($dataAttrid[$value])) {
                    $hit = true;
                }
                if (!$hit && $checkKpid) {
                    $value = $vals['data-kpid'];
                    if ($value !== '' && strncmp($value, 'vise:', 5) === 0) {
                        $hit = true;
                    }
                }
                if (!$hit) {
                    $value = $vals['jscontroller'];
                    if ($value !== '' && isset($jscontroller[$value])) {
                        $hit = true;
                    }
                }
                if (!$hit) {
                    $value = $vals['jsname'];
                    if ($value !== '' && (isset($jsname[$value])
                        || ($jsnameContains !== null && strpos($value, $jsnameContains) !== false))
                    ) {
                        $hit = true;
                    }
                }
                if (!$hit && $childParents->count() > 0 && $childParents->contains($el)) {
                    $hit = true;
                }
            }

            // --- DB half ----------------------------------------------------
            $inExtra = $extra->contains($el);
            if ($inExtra) {
                $extraSeen++;
            }
            if (!$hit && ($inExtra || ($hasDb && self::matchesDb($el, $tag, $vals, $db, $childByTag, $xpath, $axisSets, $queryCache)))) {
                $hit = true;
            }

            if ($hit) {
                $out[] = $el;
            }
        }

        if ($extraSeen < $extra->count()) {
            // A residual/hoisted node sat outside the prefilter set: re-establish
            // document order over the union rather than appending out of order.
            return self::mergeInDocumentOrder($xpath, $out, $extra);
        }
        return $out;
    }

    /**
     * @param \DOMElement $el
     * @param string $tag
     * @param string[] $vals prefetched attribute values, '' when absent
     * @param array $db compiled program
     * @param \SplObjectStorage[] $childByTag element => has a child of that tag
     * @param \SplObjectStorage[] $axisSets lazily built, keyed 'desc'/'anc' + query
     * @param \DOMNodeList[] $queryCache
     * @return bool
     */
    private static function matchesDb(
        \DOMElement $el,
        $tag,
        array $vals,
        array $db,
        array $childByTag,
        \DOMXPath $xpath,
        array &$axisSets,
        array &$queryCache
    ) {
        $norm = array();

        foreach ($db['eq'] as $attr => $set) {
            $v = $vals[$attr];
            if ($v !== '' && isset($set[$v])) {
                return true;
            }
        }
        foreach ($db['sub'] as $attr => $re) {
            if ($vals[$attr] !== '' && preg_match($re, $vals[$attr])) {
                return true;
            }
        }
        foreach ($db['pre'] as $attr => $re) {
            if ($vals[$attr] !== '' && preg_match($re, $vals[$attr])) {
                return true;
            }
        }
        foreach ($db['padr'] as $attr => $re) {
            if (preg_match($re, ' ' . $vals[$attr] . ' ')) {
                return true;
            }
        }
        foreach ($db['padn'] as $attr => $re) {
            if (!isset($norm[$attr])) {
                $norm[$attr] = self::normalizeSpace($vals[$attr]);
            }
            if (preg_match($re, ' ' . $norm[$attr] . ' ')) {
                return true;
            }
        }
        foreach ($db['exists'] as $attr => $_) {
            if ($el->hasAttribute($attr)) {
                return true;
            }
        }
        foreach ($db['childTags'] as $childTag => $_) {
            if (isset($childByTag[$childTag]) && $childByTag[$childTag]->contains($el)) {
                return true;
            }
        }

        foreach ($db['conj'] as $conj) {
            if ($conj['tag'] !== null && $conj['tag'] !== $tag) {
                continue;
            }
            $all = true;
            foreach ($conj['atoms'] as $atom) {
                list($kind, $attr, $needle, $negated) = $atom;
                switch ($kind) {
                    case 'eq':
                        $ok = ($vals[$attr] === $needle);
                        break;
                    case 'sub':
                        $ok = ($vals[$attr] !== '' && strpos($vals[$attr], $needle) !== false);
                        break;
                    case 'pre':
                        $ok = ($vals[$attr] !== '' && strncmp($vals[$attr], $needle, strlen($needle)) === 0);
                        break;
                    case 'padr':
                        $ok = (strpos(' ' . $vals[$attr] . ' ', $needle) !== false);
                        break;
                    case 'padn':
                        if (!isset($norm[$attr])) {
                            $norm[$attr] = self::normalizeSpace($vals[$attr]);
                        }
                        $ok = (strpos(' ' . $norm[$attr] . ' ', $needle) !== false);
                        break;
                    case 'exists':
                        $ok = $el->hasAttribute($attr);
                        break;
                    case 'child':
                        $ok = isset($childByTag[$needle]) && $childByTag[$needle]->contains($el);
                        break;
                    case 'desc':
                        $set = self::axisSet($xpath, 'desc', $attr, $axisSets, $queryCache);
                        $ok = $set->contains($el);
                        break;
                    case 'anc':
                        $ok = false;
                        $set = self::axisSet($xpath, 'anc', $attr, $axisSets, $queryCache);
                        if ($set->count() > 0) {
                            for ($a = $el->parentNode; $a instanceof \DOMElement; $a = $a->parentNode) {
                                if ($set->contains($a)) {
                                    $ok = true;
                                    break;
                                }
                            }
                        }
                        break;
                    default:
                        $ok = false;
                        $negated = false; // an unknown atom must never pass by negation
                }
                if ($negated) {
                    $ok = !$ok;
                }
                if (!$ok) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }
        return false;
    }

    /**
     * 'desc' => the elements that HAVE a match below them; 'anc' => the matches
     * themselves, for the caller to walk parentNode into. Built once per page.
     *
     * @return \SplObjectStorage
     */
    private static function axisSet(\DOMXPath $xpath, $axis, $query, array &$axisSets, array &$queryCache)
    {
        $key = $axis . ' ' . $query;
        if (isset($axisSets[$key])) {
            return $axisSets[$key];
        }
        $set = new \SplObjectStorage();
        $nodes = self::runQuery($xpath, $query, $queryCache);
        if ($nodes === false) {
            // Unreachable: DbMatchRuleCompiler validates every axis query before
            // it reaches the program. Kept so a future caller cannot trip on it.
            $axisSets[$key] = $set;
            return $set;
        }
        foreach ($nodes as $node) {
            if ($axis === 'anc') {
                $set->attach($node);
                continue;
            }
            for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
                if ($set->contains($p)) {
                    break; // this whole ancestor chain is already marked
                }
                $set->attach($p);
            }
        }
        $axisSets[$key] = $set;
        return $set;
    }

    /** @return \DOMNodeList|false */
    private static function runQuery(\DOMXPath $xpath, $query, array &$queryCache)
    {
        if (!isset($queryCache[$query])) {
            $queryCache[$query] = $xpath->query($query);
        }
        return $queryCache[$query];
    }

    /** XPath normalize-space(): collapse runs of XML whitespace, then trim. */
    private static function normalizeSpace($value)
    {
        if ($value === '') {
            return '';
        }
        return trim(preg_replace('/[ \t\r\n]+/', ' ', $value), " \t\r\n");
    }

    /**
     * @param \DOMElement[] $hits already in document order
     * @param \SplObjectStorage $extra may contain elements missing from $hits
     * @return \DOMElement[]
     */
    private static function mergeInDocumentOrder(\DOMXPath $xpath, array $hits, \SplObjectStorage $extra)
    {
        $selected = new \SplObjectStorage();
        foreach ($hits as $el) {
            $selected->attach($el);
        }
        foreach ($extra as $el) {
            $selected->attach($el);
        }
        $out = array();
        foreach ($xpath->query('//*') as $el) {
            if ($selected->contains($el)) {
                $out[] = $el;
            }
        }
        return $out;
    }
}
