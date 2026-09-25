<?php
/**
 * @license see LICENSE
 */

namespace Serps\SearchEngine\Google\Page;

use Psr\Http\Message\ResponseInterface;
use Serps\Core\Dom\WebPage;
use Serps\Core\Http\Proxy;
use Serps\Core\Http\ProxyInterface;
use Serps\Core\UrlArchive;
use Serps\SearchEngine\Google\GoogleUrl;
use Serps\SearchEngine\Google\GoogleUrlArchive;
use Serps\SearchEngine\Google\GoogleUrlInterface;

class GoogleDom extends WebPage
{

    protected $xpath;

    /**
     * @var \DOMDocument
     */
    protected $dom;

    /**
     * @var GoogleUrlInterface
     */
    protected $url;

    /**
     * store parsed json from parseJsonNode
     * @var array
     */
    private $parsedJsonStore = [];

    public function __construct($domString, GoogleUrlInterface $url)
    {
        $currentEncoding = $url->getParamValue('oe');
        if (!$currentEncoding) {
            $currentEncoding = 'UTF-8';
        }

        parent::__construct($this->cleanDomString($domString), $url, $currentEncoding);
    }


    /**
     * Get a property from a google json node.
     * Google json nodes are invisible dom nodes that contain json text (found in mobile carousels for instance)
     *
     * @param string $propertyName name of the property to get
     * @param \DOMNode $node
     * @return mixed
     */
    public function getJsonNodeProperty($propertyName, \DOMNode $node)
    {
        $hash = spl_object_hash($node);

        if (!isset($this->parsedJsonStore[$hash])) {
            $nodeValue = $node->nodeValue;
            $nodeValue = trim($nodeValue, '"');

            $this->parsedJsonStore[$hash] = json_decode($nodeValue, true);
        }

        $item = $this->parsedJsonStore[$hash];

        if ($item && is_array($item) && isset($item[$propertyName])) {
            return $item[$propertyName];
        } else {
            return null;
        }
    }

    /**
     * Cleans up the dom string , the head contains <div> tags,
     * we need the <meta> tags to identify if it is mobile or not
     * @param strind $domString the html string
     * @return  string
    */
    public function cleanDomString($domString)
    {
        preg_match_all('/(?:<meta(.*)>)/Uis',$domString, $metaMatches);
        $metaString = '';
        if(!empty($metaMatches[0])) {
            foreach($metaMatches[0] as $match) {
                $metaString .=  $match;
            }
        }
        preg_match_all('/(?:<head>(?:.*))(<div(.*)<\/div>)(?:(?:.*)<\/head>)/Uis',$domString, $matches);
        if(!empty($matches[0])) {
            foreach($matches[0] as $match) {
                $domString =  str_replace($match, '<head>'.$metaString.'</head>', $domString);
            }
        }
        return $this->escapeJslScriptBodies($domString);
    }

    /**
     * Keep jsl.dh() payloads inside their <script>.
     *
     * Google now ships AI Overview templates as raw HTML inside jsl.dh('id','<div>…</div>')
     * scripts. libxml 2.9 (the HTML4 parser) ends a <script> at the first "</" followed by a
     * letter, so the rest of the payload spills into the page as live markup: the block then
     * appears twice in the AIO (spilled + injected by SGEWidget) together with its escaped
     * "\x3c!--…-->" framework comments as visible text.
     *
     * "\x3c/" is the JS escape of "</" (same string at runtime) and is exactly what
     * SGEWidget::decodeJslHtml() already turns back into "<". Only scripts carrying jsl.dh(
     * are touched; SGEWidget is their only reader.
     *
     * @param string $domString
     * @return string
     */
    protected function escapeJslScriptBodies($domString)
    {
        if (strpos($domString, 'jsl.dh(') === false) {
            return $domString;
        }

        $out = '';
        $pos = 0;
        while (($open = stripos($domString, '<script', $pos)) !== false) {
            $bodyStart = strpos($domString, '>', $open);
            if ($bodyStart === false) {
                break;
            }
            $bodyStart++;
            $close = stripos($domString, '</script', $bodyStart);
            if ($close === false) {
                break;
            }

            $body = substr($domString, $bodyStart, $close - $bodyStart);
            if (strpos($body, 'jsl.dh(') !== false) {
                $body = str_replace('</', '\x3c/', $body);
            }

            $out .= substr($domString, $pos, $bodyStart - $pos) . $body;
            $pos = $close;
        }

        return $out . substr($domString, $pos);
    }
}
