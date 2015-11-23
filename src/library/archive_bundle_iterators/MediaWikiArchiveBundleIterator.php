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

use seekquarry\yioop\library as L;
use seekquarry\yioop\library\BZip2BlockIterator;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\WikiParser;

/** For crawlHash*/
require_once __DIR__."/../Utility.php";
/**
 * Used to iterate through a collection of .xml.bz2  media wiki files
 * stored in a WebArchiveBundle folder. Here these media wiki files contain the
 * kinds of documents used by wikipedia. Iteration would be
 * for the purpose making an index of these records
 *
 * @author Chris Pollett
 * @see WebArchiveBundle
 */
class MediaWikiArchiveBundleIterator extends TextArchiveBundleIterator
{
    /**
     * Used to define the styles we put on cache wiki pages
     */
    const WIKI_PAGE_STYLES = <<<EOD
<style type="text/css">
table.wikitable
{
    background:white;
    border:1px #aaa solid;
    border-collapse: scollapse
    margin:1em 0;
}
table.wikitable > tr > th,table.wikitable > tr > td,
table.wikitable > * > tr > th,table.wikitable > * > tr > td
{
    border:1px #aaa solid;
    padding:0.2em;
}
table.wikitable > tr > th,
table.wikitable > * > tr > th
{
    text-align:center;
    background:white;
    font-weight:bold
}
table.wikitable > caption
{
    font-weight:bold;
}
</style>
EOD;
    /**
     * Used to hold a WikiParser object that will be used for parsing
     * @var object
     */
    public $parser;
    /**
     * Creates a media wiki archive iterator with the given parameters.
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
        $ini = [ 'compression' => 'bzip2',
            'file_extension' => 'bz2',
            'encoding' => 'UTF-8',
            'start_delimiter' => '@page@'];
        parent::__construct($iterate_timestamp, $iterate_dir,
            $result_timestamp, $result_dir, $ini);
        $this->switch_partition_callback_name = "readMediaWikiHeader";
    }
    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return int a 4-bit number based on the log_2 size - 10 of the wiki
     *     entry (@see nextPage).
     */
    public function weight(&$site)
    {
        return min($site[self::WEIGHT], 15);
    }
    /**
     * Reads the siteinfo tag of the mediawiki xml file and extract data that
     * will be used in constructing page summaries.
     */
    public function readMediaWikiHeader()
    {
        $this->header = [];
        $site_info = $this->getNextTagData("siteinfo");
        $found_lang =
            preg_match('/lang\=\"(.*)\"/', $this->remainder, $matches);
        if ($found_lang) {
            $this->header['lang'] = $matches[1];
        }
        if ($site_info === false) {
            $this->bz2_iterator = null;
            return false;
        }
        $dom = new \DOMDocument();
        @$dom->loadXML($site_info);
        $this->header['sitename'] = $this->getTextContent($dom,
            "/siteinfo/sitename");
        $pre_host_name =
            $this->getTextContent($dom, "/siteinfo/base");
        $this->header['base_address'] = substr($pre_host_name, 0,
            strrpos($pre_host_name, "/") + 1);
        $url_parts = @parse_url($this->header['base_address']);
        $this->header['ip_address'] = gethostbyname($url_parts['host']);
        return true;
    }
    /**
     * Used to initialize the arrays of match/replacements used to format
     * wikimedia syntax into HTML (not perfectly since we are only doing
     * regexes)
     *
     * @param string $base_address base url for link substitutions
     */
    public function initializeSubstitutions($base_address)
    {
        $add_substitutions = [
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/{{([^}]*)({{([^{}]*)}})/', '{{$1$3' ],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ['/\[\[([^\]]*)(\[\[([^\[\]]*)\]\])/', "[[$1$3"],
            ["/\[\[Image:(.+?)(right\|)(.+?)\]\]/s", "[[Image:$1$3]]"],
            ["/\[\[Image:(.+?)(left\|)(.+?)\]\]/s", "[[Image:$1$3]]"],
            ["/\[\[Image:(.+?)(\|left)\]\]/s", "[[Image:$1]]"],
            ["/\[\[Image:(.+?)(\|right)\]\]/s", "[[Image:$1]]"],
            ["/\[\[Image:(.+?)(thumb\|)(.+?)\]\]/s", "[[Image:$1$3]]"],
            ["/\[\[Image:(.+?)(live\|)(.+?)\]\]/s", "[[Image:$1$3]]"],
            ["/\[\[Image:(.+?)(\s*\d*\s*(px|in|cm|".
                "pt|ex|em)\s*\|)(.+?)\]\]/s","[[Image:$1$4]]"],
            ["/\[\[Image:([^\|]+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\" >Image:$1</a>)"],
            ["/\[\[Image:(.+?)\|(.+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\">Image:$2</a>)"],
            ["/\[\[File:(.+?)\|(right\|)?thumb(.+?)\]\]/s",
                "(<a href=\"{$base_address}File:$1\">Image:$1</a>)"],
            ["/{{Redirect2?\|([^{}\|]+)\|([^{}\|]+)\|([^{}\|]+)}}/i",
                "<div class='indent'>\"$1\". ($2 &rarr;<a href=\"".
                $base_address."$3\">$3</a>)</div>"],
            ["/{{Redirect\|([^{}\|]+)}}/i",
                "<div class='indent'>\"$1\". (<a href=\"".
                $base_address. "$1_(disambiguation)\">$1???</a>)</div>"],
            ["/#REDIRECT:\s+\[\[(.+?)\]\]/",
                "<a href='{$base_address}$1'>$1</a>"],
            ["/{{pp-(.+?)}}/s", ""],
            ["/{{bot(.*?)}}/si", ""],
            ['/{{Infobox.*?\n}}/si', ""],
            ['/{{Clear.*?\n}}/si', ""],
            ["/{{clarify\|(.+?)}}/si", ""],
            ['/{{[^}]*}}/s', ""],
        ];
        $this->parser = new WikiParser($base_address, $add_substitutions);
    }
    /**
     * Restores the internal state from the file iterate_status.txt in the
     * result dir such that the next call to nextPages will pick up from just
     * after the last checkpoint. We also reset up our regex substitutions
     *
     * @return array the data serialized when saveCheckpoint was called
     */
    public function restoreCheckPoint()
    {
        $info = parent::restoreCheckPoint();
        if (!$this->iterate_dir) { // do on client not name server
            $this->initializeSubstitutions($this->header['base_address']);
        }
        return $info;
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
        if ($objects  && is_object($objects) && $objects->item(0) != null ) {
            return $objects->item(0)->textContent;
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
        static $minimal_regexes = false;
        static $first_call = true;
        if ($first_call) {
            if (!isset($this->header['base_address'])) {
                $this->header['base_address'] = "";
            }
            $this->initializeSubstitutions($this->header['base_address']);
        }
        $page_info = $this->getNextTagData("page");
        if ($no_process) { return $page_info; }
        $dom = new \DOMDocument();
        @$dom->loadXML($page_info);
        $site = [];
        $pre_url = $this->getTextContent($dom, "/page/title");
        $pre_url = str_replace(" ", "_", $pre_url);
        $site[self::URL] = $this->header['base_address'].$pre_url;
        $site[self::IP_ADDRESSES] = [$this->header['ip_address']];
        $pre_timestamp = $this->getTextContent($dom,
            "/page/revision/timestamp");
        $site[self::MODIFIED] = date("U", strtotime($pre_timestamp));
        $site[self::TIMESTAMP] = time();
        $site[self::TYPE] = "text/html";
        $site[self::HEADER] = "MediawikiBundleIterator extractor";
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = "UTF-8";
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $site[self::PAGE] = "<html lang='".$this->header['lang']."' >\n".
            "<head><title>$pre_url</title>\n".
            self::WIKI_PAGE_STYLES . "\n</head>\n".
            "<body><h1>$pre_url</h1>\n";
        $pre_page = $this->getTextContent($dom, "/page/revision/text");
        $current_hash = L\crawlHash($pre_page);
        if ($first_call) {
            $this->saveCheckPoint(); //ensure we remember to advance one on fail
            $first_call = false;
        }
        $pre_page = $this->parser->parse($pre_page, false, true);
        $pre_page = preg_replace("/{{Other uses}}/i",
                "<div class='indent'>\"$1\". (<a href='".
                $site[self::URL]. "_(disambiguation)'>$pre_url</a>)</div>",
                $pre_page);
        $site[self::PAGE] .= $pre_page;
        $site[self::PAGE] .= "\n</body>\n</html>";
        $site[self::HASH] = FetchUrl::computePageHash($site[self::PAGE]);
        $site[self::WEIGHT] = ceil(max(
            log(strlen($site[self::PAGE]) + 1, 2) - 10, 1));
        return $site;
    }
}
