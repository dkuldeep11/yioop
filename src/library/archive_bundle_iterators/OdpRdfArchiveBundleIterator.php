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
namespace seekquarry\yioop\library\archive_bundle_iterators;

use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;

/**
 * Used to iterate through the records of a collection of one or more open
 * directory RDF files stored in a WebArchiveBundle folder. Open Directory
 * file can be found at http://rdf.dmoz.org/ .  Iteration would be
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @see WebArchiveBundle
 */
class OdpRdfArchiveBundleIterator extends TextArchiveBundleIterator
{
    /**
     * Associative array containing global properties like base url of the
     * current open odp rdf file
     * @var array
     */
    public $header;
    /**
     * How many bytes to read into buffer from gzip stream in one go
     * @var int
     */
    const BLOCK_SIZE = 1024;
    /**
     * Creates an open directory rdf archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to
     *     iterate  over the pages of
     * @param string $iterate_dir folder of files to iterate over
     * @param string $result_timestamp timestamp of the arc archive bundle
     *     results are being stored in
     * @param string $result_dir where to write last position checkpoints to
     */
    public function __construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir)
    {
        $ini = [ 'compression' => 'gzip',
            'file_extension' => 'gz',
            'encoding' => 'UTF-8',
            'start_delimiter' => '@Topic|ExternalPage@',
            'end_delimiter' => '@/Topic|/ExternalPage@'];
        parent::__construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir, $ini);
        $this->header['base_address'] = "http://www.dmoz.org/";
        $url_parts = @parse_url($this->header['base_address']);
        $this->header['ip_address'] = gethostbyname($url_parts['host']);
    }
    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return int a 4-bit number based on the topic path of the odp entry
     *     (@see processTopic @see processExternalPage)
     */
    public function weight(&$site)
    {
        return min($site[self::WEIGHT], 15);
    }
    /**
     * Gets the text content of the first dom node satisfying the
     * xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     *
     * @return string text content of the given node if it exists
     */
    public function getTextContent($dom, $path)
    {
        $xpath = new \DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if ($objects  && is_object($objects) && $objects->item(0) != null) {
            return $objects->item(0)->textContent;
        }
        return "";
    }
    /**
     * Gets the value of the attribute $attribute for each dom node
     * satisfying the xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     * @param string $attribute name of the attribute to get the values for
     *
     * @return array of values of the given attribute
     */
    public function getAttributeValueAll($dom, $path, $attribute)
    {
        $values = [];
        $xpath = new \DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if ($objects  && is_object($objects)) {
            foreach ($objects as $object) {
                $value = $object->getAttribute($attribute);
                if ($value) {
                    $values[] = $value;
                }
            }
        }
        return $values;
    }
    /**
     * Gets the value of the attribute $attribute of the first dom node
     * satisfying the xpath expression $path in the dom document $dom
     *
     * @param object $dom DOMDocument to get the text from
     * @param $path xpath expression to find node with text
     * @param string $attribute name of the attribute to get the value for
     *
     * @return string value of the given attribute
     */
    public function getAttributeValue($dom, $path,  $attribute)
    {
        $xpath = new \DOMXPath($dom);
        $objects = $xpath->evaluate($path);
        if ($objects  && is_object($objects) && $objects->item(0) != null) {
            return $objects->item(0)->getAttribute($attribute);
        }
        return "";
    }
    /**
     * Gets the next doc from the iterator
     * @param bool $no_process do not do any processing on page data
     * @return array associative array for doc or string if no_process true
     */
    public function nextPage($no_process = false)
    {
        if (!$this->checkFileHandle()) return null;
        $tag_data = $this->getNextTagsData(
            ["Topic","ExternalPage"]);
        if (!$tag_data) {
            return false;
        }
        list($page_info, $tag) = $tag_data;
        if ($no_process) { return $page_info; }
        $page_info = str_replace("r:id","id", $page_info);
        $page_info = str_replace("r:resource","resource", $page_info);
        $page_info = str_replace("d:Title","Title", $page_info);
        $page_info = str_replace("d:Description","Description", $page_info);
        $dom = new \DOMDocument();
        @$dom->loadXML($page_info);
        $processMethod = "process".$tag;
        $site[self::IP_ADDRESSES] = [$this->header['ip_address']];
        $site[self::MODIFIED] = time();
        $site[self::TIMESTAMP] = time();
        $site[self::TYPE] = "text/html";
        $site[self::HEADER] = "odp_rdf_bundle_iterator extractor";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $this->$processMethod($dom, $site);
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        return $site;
    }
    /**
     * Computes an HTML page for a Topic tag parsed from the ODP RDF
     * document
     *
     * @param object $dom document object for one Topic tag tag
     * @param array& $site a reference to an array of header and page info
     *     for an html page
     */
    public function processTopic($dom, &$site)
    {
        $topic_path = $this->getAttributeValue($dom, "/Topic", "id");
        $site[self::URL] = $this->header['base_address'].$topic_path;

        $site[self::WEIGHT] = max(15 - substr_count($topic_path, "/"), 1);
        $title = str_replace("/", " ", $topic_path);
        $links = $this->computeTopicLinks($topic_path);

        $topic_link1 = $this->getAttributeValue($dom, "/Topic/link1",
            "resource");
        if ($topic_link1) {
            $links[$topic_link1] = $topic_link1." - ".$title;
        }

        $topic_links = $this->getAttributeValueAll($dom, "/Topic/link",
            "resource");
        if ($topic_links != null) {
            foreach ($topic_links as $topic_link) {
                $links[$topic_link] = $topic_link." - ".$title;
            }
        }
        $site[self::PAGE] = "<html>\n".
            "<head><title>$title</title></head>\n"
            ."<body><h1>$title</h1>\n";
        $site[self::PAGE] .= $this->linksToHtml($links);
        $site[self::PAGE] .= "</body></html>";
    }
    /**
     * Computes an HTML page for an ExternalPage tag parsed from the ODP RDF
     * document
     *
     * @param object $dom document object for one Topic tag tag
     * @param array& $site a reference to an array of header and page info
     *     for an html page
     */
    public function processExternalPage($dom, &$site)
    {
        $site[self::URL] = $this->getAttributeValue($dom,
            "/ExternalPage", "about");

        $topic_path = $this->getTextContent($dom, "/ExternalPage/topic");
        $site[self::WEIGHT] = max(14 - substr_count($topic_path, "/"), 1);

        $links = $this->computeTopicLinks($topic_path);
        $title = $this->getTextContent($dom, "/ExternalPage/Title");
        $title = "$title - ".str_replace("/", " ", $topic_path);
        $description = $this->getTextContent(
            $dom, "/ExternalPage/Description");

        $site[self::PAGE] = "<html>\n".
            "<head><title>$title</title></head>\n"
            ."<body><h1>$title</h1>\n";
        $site[self::PAGE] .= $this->linksToHtml($links);
        $site[self::PAGE] .= "<div>$description</div></body></html>";
    }
    /**
     * Computes links for prefix topics of an ODP topic path
     *
     * @param string $topic_path to compute links for
     * @return array url => text pairs for each prefix of path
     */
    public function computeTopicLinks($topic_path)
    {
        $links = [];
        $topic_parts = explode("/", $topic_path);
        $path = "";

        foreach ($topic_parts as $part){
            $path .= "/$part";
            $links[$this->header['base_address'].$path] = $part;
        }
        return $links;
    }
    /**
     * Makes an unordered HTML list out of an associative array of
     * url => link_text pairs.
     *
     * @param array $links url=>link_text pairs
     * @return string containing html for unorderlisted list of links
     */
    public function linksToHtml($links)
    {
        $html = "";
        if (count($links) > 0) {
            $html .= "<ul>\n";
            foreach ($links as $url => $text) {
                $html .= '<li><a href="'.
                    $url.'">'.$text.'</a></li>';
            }
            $html .= "</ul>\n";
        }
        return $html;
    }
}
