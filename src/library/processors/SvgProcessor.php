<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;

 /**
 * Used to create crawl summary information
 * for SVG files. This class is a little bit
 * weird in that it generates thumbs like the
 * image processor classes, but when it gives
 * up on the data it falls back to text
 * processor handling.
 *
 * @author Chris Pollett
 */
class SvgProcessor extends TextProcessor
{
    const MAX_THUMB_LEN = 5000;
    /**
     * Set-ups the any indexing plugins associated with this page
     * processor
     *
     * @param array $plugins an array of indexing plugins which might
     *     do further processing on the data handles by this page
     *     processor
     * @param int $max_description_len maximal length of a page summary
     * @param int $summarizer_option CRAWL_CONSTANT specifying what kind
     *      of summarizer to use self::BASIC_SUMMARIZER,
     *      self::GRAPH_BASED_SUMMARIZER and self::CENTROID_SUMMARIZER
     *      self::CENTROID_SUMMARIZER
     */
    public function __construct($plugins = [], $max_description_len = null,
        $summarizer_option = self::BASIC_SUMMARIZER)
    {
        parent::__construct($plugins, $max_description_len, $summarizer_option);
        /** Register File Types We Handle*/
        self::$indexed_file_types[] = "svg";
        self::$image_types[] = "svg";
        self::$mime_processor["image/svg+xml"] = "SvgProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of svg image. If the image is small
     * enough, an attempt is made to generate a thumbnail
     *
     * @param string $page   web-page contents
     * @param string $url   the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        if (is_string($page)) {
            self::closeDanglingTags($page);
            $dom = self::dom($page);
            if ($dom !== false && isset($dom->documentElement)) {
                $summary[self::TITLE] = "";
                $summary[self::DESCRIPTION] = self::description($dom);
                $summary[self::LINKS] = [];
                $summary[self::PAGE] =
                    "<html><body><div><img src='data:image/svg+xml;base64," .
                    base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                    "' /></div></body></html>";
                if (strlen($page) < self::MAX_THUMB_LEN) {
                    $thumb_string = self::createThumb($dom);
                    $summary[self::THUMB] = 'data:image/svg+xml;base64,'.
                        base64_encode($thumb_string);
                }
            }else {
                $summary = parent::process($page, $url);
            }
        }
        return $summary;
    }
    /**
     * Used to create an svg thumbnail from a dom object
     *
     * @param object $dom a dom svg image object
     *
     */
    public static function createThumb($dom)
    {
        $svg = $dom->documentElement;
        if ($svg->hasAttribute("width")) {
            $width = $svg->getAttribute("width");
        } else {
            $width = 600;
        }
        $width = L\convertPixels($width);
        if ($svg->hasAttribute("height")) {
            $height = $svg->getAttribute("height");
        } else {
            $height = 600;
        }
        $height = L\convertPixels($height);
        $svg->setAttributeNS("", "width", "150px");
        $svg->setAttributeNS("", "height", "150px");
        if (!$svg->hasAttribute("viewBox")) {
            $svg->setAttributeNS("", "viewBox", "0 0 $width $height");
        }
        return $dom->saveXML();
    }
    /**
     * Return a document object based on a string containing the contents of
     * an SVG page
     *
     * @param string $page a web page
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($page);
        return $dom;
    }
    /**
     * Returns html head title of a webpage based on its document object
     *
     * @param object $dom   a document object to extract a title from.
     * @return string  a title of the page
     *
     */
    public static function title($dom)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        $titles = $xpath->evaluate("/svg//desc");
        $title = "";
        foreach ($titles as $pre_title) {
            $title .= $pre_title->textContent;
        }
        return $title;
    }
    /**
     * Returns descriptive text concerning a svg page based on its document
     * object
     *
     * @param object $dom a document object to extract a description from.
     * @return string a description of the page
     */
    public static function description($dom)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        $description = "";
        /*
          concatenate the contents of then additional dom elements up to
          the limit of description length
        */
        $page_parts = ["/svg//desc", "/svg//text"];
        foreach ($page_parts as $part) {
            $doc_nodes = $xpath->evaluate($part);
            foreach ($doc_nodes as $node) {
                $description .= " ".$node->textContent;
                if (strlen($description) > self::$max_description_len){
                    break 2;
                }
            }
        }
        $description = mb_ereg_replace("(\s)+", " ",  $description);
        return $description;
    }
}
