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
use seekquarry\yioop\library\UrlParser;

/**
 * Used to create crawl summary information
 * for XML files (those served as text/xml)
 *
 * @author Chris Pollett
 */
class XmlProcessor extends TextProcessor
{
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
        self::$indexed_file_types[] = "xml";
        self::$mime_processor["text/xml"] = "XmlProcessor";
        self::$mime_processor["application/xml"] = "XmlProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of rss news feed data.
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
        $summary = null;
        if (is_string($page)) {
            self::closeDanglingTags($page);
            $dom = self::dom($page);
            $root_name = isset($dom->documentElement->nodeName) ?
                $dom->documentElement->nodeName : "";
            unset($dom);
            $XML_PROCESSORS = [
                "rss" => "RssProcessor", "html" => "HtmlProcessor",
                "sitemapindex" => "SitemapProcessor",
                "urlset" => "SitemapProcessor", "svg" => "SvgProcessor"
            ];
            if (isset($XML_PROCESSORS[$root_name])) {
                $processor_name = C\NS_PROCESSORS . $XML_PROCESSORS[$root_name];
                $processor = new $processor_name($this->plugin_instances);
                $summary = $processor->process($page, $url);
            } else {
                $summary = parent::process($page, $url);
            }
        }
        return $summary;
    }
    /**
     * Return a document object based on a string containing the contents of
     * an XML page
     *
     * @param string $page   a web page
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($page);
        return $dom;
    }
}
