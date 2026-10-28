<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Parser\Evaluated\Rule\Natural;

use Serps\SearchEngine\Google\Page\GoogleDom;

/**
 * Fill a widget's empty placeholders with their deferred jsl.dh() payloads, as Google's JS does in
 * a browser.
 *
 * Google ships some widget bodies only as script payloads — [{id:'X'},function(){jsl.dh(this.id,
 * "<div>…</div>")}] — aimed at an empty <div id="X"> inside the widget, and a payload can carry
 * further empty placeholders for other payloads. The collapsed desktop Hotels block (investigation
 * #3512) holds its property cards two levels deep this way, so the rules saw an empty container.
 *
 * Only EMPTY targets inside $node are filled, so a server-rendered block is never duplicated.
 * Payload <script>s are dropped. SGEWidget keeps its own AIO-specific variant.
 */
trait HydratesDeferredBlocks
{
    /** @var array<string,array<string,string>> page-html hash => id => decoded payload */
    private static $deferredPayloadCache = [];

    /**
     * @param GoogleDom $dom
     * @param \DOMElement $node
     * @return int number of placeholders filled
     */
    protected function hydrateDeferredBlocks(GoogleDom $dom, \DOMElement $node)
    {
        $payloads = $this->deferredPayloads($dom);
        if (empty($payloads)) {
            return 0;
        }

        $filled = 0;
        $done = [];
        // A payload can itself carry placeholders for other payloads; a few passes settle it.
        for ($pass = 0; $pass < 4; $pass++) {
            $filledThisPass = 0;
            $targets = [];
            foreach ($dom->getXpath()->query('descendant::*[@id]', $node) as $element) {
                $targets[] = $element;
            }
            foreach ($targets as $element) {
                $id = $element->getAttribute('id');
                if (!isset($payloads[$id]) || isset($done[$id]) || trim($element->textContent) !== '' || $element->getElementsByTagName('*')->length > 0) {
                    continue;
                }
                $done[$id] = true;
                if ($this->appendDeferredHtml($element, $payloads[$id])) {
                    $filledThisPass++;
                }
            }
            if ($filledThisPass === 0) {
                break;
            }
            $filled += $filledThisPass;
        }

        return $filled;
    }

    /**
     * id => decoded HTML for every wrapper-form jsl.dh() call on the page, cached per page.
     * The body is matched lazily up to the wrapper's real terminator (quote + ");}"), because
     * Google leaves bare quotes inside payloads (src=\"" with the second quote unescaped).
     *
     * @param GoogleDom $dom
     * @return array<string,string>
     */
    private function deferredPayloads(GoogleDom $dom)
    {
        $html = $dom->getDom()->saveHTML();
        if (strpos($html, 'jsl.dh(') === false) {
            return [];
        }

        $key = md5($html);
        if (isset(self::$deferredPayloadCache[$key])) {
            return self::$deferredPayloadCache[$key];
        }

        $payloads = [];
        $pattern = '/\[\{id:[\'"]([^\'"]+)[\'"]\},function\(\)\{(?:window\.)?jsl\.dh\(this\.id,(["\'])(.*?)\2\);?\}/s';
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                if (!isset($payloads[$m[1]]) && $m[3] !== '') {
                    $payloads[$m[1]] = self::decodeJsString($m[3]);
                }
            }
        }

        // One page at a time is all a parse ever needs.
        self::$deferredPayloadCache = [$key => $payloads];

        return $payloads;
    }

    /**
     * Decode a JS string literal body: \xNN, \uNNNN and backslash escapes.
     *
     * @param string $s
     * @return string
     */
    private static function decodeJsString($s)
    {
        $s = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function ($m) {
            return chr(hexdec($m[1]));
        }, $s);
        $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
            return mb_chr(hexdec($m[1]), 'UTF-8');
        }, $s);

        return str_replace(['\\"', "\\'", '\\/', '\\\\'], ['"', "'", '/', '\\'], $s);
    }

    /**
     * @param \DOMElement $element
     * @param string $html
     * @return bool
     */
    private function appendDeferredHtml(\DOMElement $element, $html)
    {
        $html = preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $html);
        if ($html === null || trim($html) === '') {
            return false;
        }

        $tmp = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $loaded = $tmp->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $wrapper = $tmp->documentElement;
        if (!$loaded || !$wrapper instanceof \DOMElement || !$wrapper->hasChildNodes()) {
            return false;
        }
        foreach (iterator_to_array($wrapper->childNodes) as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }

        return true;
    }
}
