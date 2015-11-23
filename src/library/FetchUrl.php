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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 *
 * Code used to manage HTTP or Gopher requests from one or more URLS
 *
 * @author Chris Pollett
 */
class FetchUrl implements CrawlConstants
{
    /**
     * Make multi_curl requests for an array of sites with urls or onion urls
     *
     * @param array $sites  an array containing urls of pages to request
     * @param bool $timer  flag, true means print timing statistics to log
     * @param int $page_range_request maximum number of bytes to download/page
     *     0 means download all
     * @param string $temp_dir folder to store temporary ip header info
     * @param string $key  the component of $sites[$i] that has the value of
     *     a url to get defaults to URL
     * @param string $value component of $sites[$i] in which to store the
     *     page that was gotten
     * @param bool $minimal if true do a faster request of pages by not
     *     doing things like extract HTTP headers sent, etcs
     * @param array $post_data data to be POST'd to each site
     * @param bool $follow whether to follow redirects or not
     * @param string $tor_proxy url of a proxy that knows how to download
     *     .onion urls
     * @param array $proxy_servers if not [], then an array of proxy
     *     server to use rather than to directly download web pages from
     *     the current machine
     *
     * @return array an updated array with the contents of those pages
     */
    public static function getPages($sites, $timer = false,
        $page_range_request = C\PAGE_RANGE_REQUEST, $temp_dir = null,
        $key=CrawlConstants::URL, $value = CrawlConstants::PAGE, $minimal=false,
        $post_data = null, $follow = false, $tor_proxy = "",
        $proxy_servers=[])
    {
        static $agent_handler = null;
        static $handler_time = 0;
        if($agent_handler == null) {
            /* try to keep handler around between calls to allow for connection
                resuse
             */
            $agent_handler = curl_multi_init();
            $handler_time = microtime(true);
        }
        $active = null;
        $start_time = microtime(true);
        if (!$minimal && $temp_dir == null) {
            $temp_dir = C\CRAWL_DIR."/temp";
            if (!file_exists($temp_dir)) {
                mkdir($temp_dir);
            }
        }
        //Set-up requests
        $num_sites = count($sites);
        for ($i = 0; $i < $num_sites; $i++) {
            $is_gopher = false;
            $sites[$i][CrawlConstants::IS_GOPHER_URL] = $is_gopher;
            if (isset($sites[$i][$key])) {
                list($sites[$i][$key], $url, $headers) =
                    self::prepareUrlHeaders($sites[$i][$key], $minimal,
                    $proxy_servers);
                if ($headers == "gopher") {
                    $is_gopher = true;
                    $sites[$i][CrawlConstants::IS_GOPHER_URL] = $is_gopher;
                    $headers = [];
                }
                $sites[$i][0] = curl_init();
                if (!$minimal) {
                    $ip_holder[$i] = fopen("$temp_dir/tmp$i.txt", 'w+');
                    curl_setopt($sites[$i][0], CURLOPT_STDERR, $ip_holder[$i]);
                    curl_setopt($sites[$i][0], CURLOPT_VERBOSE, true);
                }
                curl_setopt($sites[$i][0], CURLOPT_USERAGENT, C\USER_AGENT);
                curl_setopt($sites[$i][0], CURLOPT_IPRESOLVE,
                    CURL_IPRESOLVE_WHATEVER);
                curl_setopt($sites[$i][0], CURLOPT_URL, $url);
                if (strcmp(substr($url,-10), "robots.txt") == 0 ) {
                    $sites[$i]['ROBOT'] = true;
                    $follow = true; /*wikipedia redirects their robot page. grr
                                      want to force this for robots pages
                                    */
                }
                curl_setopt($sites[$i][0], CURLOPT_FOLLOWLOCATION, $follow);
                curl_setopt($sites[$i][0], CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($sites[$i][0], CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($sites[$i][0], CURLOPT_AUTOREFERER, true);
                curl_setopt($sites[$i][0], CURLOPT_RETURNTRANSFER, true);
                curl_setopt($sites[$i][0], CURLOPT_CONNECTTIMEOUT,
                    C\PAGE_TIMEOUT);
                curl_setopt($sites[$i][0], CURLOPT_TIMEOUT, C\PAGE_TIMEOUT);
                if (stripos($url,'.onion') !== false && $tor_proxy != "") {
                    curl_setopt($sites[$i][0], CURLOPT_PROXY, $tor_proxy);
                    //CURLPROXY_SOCKS5_HOSTNAME = 7
                    curl_setopt($sites[$i][0], CURLOPT_PROXYTYPE, 7);
                    if ($timer) {
                        crawlLog("Using Tor proxy for $url..");
                    }
                } else if ($proxy_servers != [] && !$is_gopher) {
                    $select_proxy = rand(0, count($proxy_servers) - 1);
                    $proxy_server = $proxy_servers[$select_proxy];
                    $proxy_parts = explode(":", $proxy_server);
                    $proxy_ip = $proxy_parts[0];
                    if (!isset($proxy_parts[2]) ||
                        strtolower($proxy_parts[2]) == 'http') {
                        $proxy_type = CURLPROXY_HTTP;
                    } else if (strtolower($proxy_parts[2]) == 'socks5') {
                        $proxy_type = CURLPROXY_SOCKS5;
                    } else {
                        $proxy_type = $proxy_parts[2];
                    }
                    if (isset($proxy_parts[1])) {
                        $proxy_port = $proxy_parts[1];
                    } else {
                        $proxy_port = "80";
                    }
                    curl_setopt($sites[$i][0], CURLOPT_PROXY,
                        "$proxy_ip:$proxy_port");
                    curl_setopt($sites[$i][0], CURLOPT_PROXYTYPE,
                        $proxy_type);
                    if ($timer) {
                        crawlLog("Selecting proxy $select_proxy for $url");
                    }
                }
                if (!$minimal) {
                    curl_setopt($sites[$i][0], CURLOPT_HEADER, true);
                }
                //make lighttpd happier
                if (!$is_gopher) {
                    curl_setopt($sites[$i][0], CURLOPT_HTTPHEADER,
                        $headers);
                }
                curl_setopt($sites[$i][0], CURLOPT_ENCODING, "");
                   // ^ need to set for sites like att that use gzip
                if ($page_range_request > 0) {
                    curl_setopt($sites[$i][0], CURLOPT_RANGE, "0-".
                        $page_range_request);
                }
                if ($post_data != null) {
                    curl_setopt($sites[$i][0], CURLOPT_POST, true);
                    curl_setopt($sites[$i][0], CURLOPT_POSTFIELDS,
                        $post_data[$i]);
                }
                curl_multi_add_handle($agent_handler, $sites[$i][0]);
            }
        }
        if ($timer) {
            crawlLog("  Init Get Pages ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime(true);
        $start = time();
        //Wait for responses
        $running = null;
        $memory_limit = metricToInt(ini_get("memory_limit")) * 0.7;
        do {
            $mrc = curl_multi_exec($agent_handler, $running);
            /* 0.05 is to prevent this from being too busy a loop sucking
               up CPU cycle. We check every 0.05 if another page is ready of
               not*/
            $ready=curl_multi_select($agent_handler, 0.05);
        } while (memory_get_usage() < $memory_limit &&
            time() - $start < C\PAGE_TIMEOUT &&  $running > 0 );
        if (time() - $start > C\PAGE_TIMEOUT && $timer) {
            crawlLog("  TIMED OUT!!!");
        }
        if ($timer) {
            crawlLog("  Page Request time ".(changeInMicrotime($start_time)));
        }
        $start_time = microtime(true);
        //Process returned pages
        for ($i = 0; $i < $num_sites; $i++) {
            if ($timer) {
                crawlTimeoutLog("fetch_url initial processing of page %s of %s",
                    $i, $num_sites);
            }
            if (!$minimal && isset($ip_holder[$i]) ) {
                rewind($ip_holder[$i]);
                $header = fread($ip_holder[$i], 8192);
                $ip_addresses = self::getCurlIp($header);
                fclose($ip_holder[$i]);
            }
            $is_gopher = false;
            if (isset($sites[$i][0]) && $sites[$i][0]) {
                // Get Data and Message Code
                $content = @curl_multi_getcontent($sites[$i][0]);
                $is_gopher = $sites[$i][CrawlConstants::IS_GOPHER_URL];
                /*
                    If the Transfer-encoding was chunked then the Range header
                    we sent was ignored. So we manually truncate the data
                    here
                 */
                if ($page_range_request > 0) {
                    $content = substr($content, 0, $page_range_request);
                }
                if (isset($content) && !$minimal && !$is_gopher) {
                    $site = self::parseHeaderPage($content, $value);
                    $sites[$i] = array_merge($sites[$i], $site);
                    if (isset($header)) {
                        $header = substr($header, 0,
                            strpos($header, "\x0D\x0A\x0D\x0A") + 4);
                    } else {
                        $header = "";
                    }
                    $sites[$i][CrawlConstants::HEADER] =
                        $header . $sites[$i][CrawlConstants::HEADER];
                    unset($header);
                } else if (isset($content) && !$minimal && $is_gopher) {
                    $sites[$i][CrawlConstants::HEADER] =
                        $header;
                    $sites[$i][$value] = $content;
                    unset($header);
                } else {
                    $sites[$i][$value] = $content;
                }
                if (!$minimal) {
                    $sites[$i][self::SIZE] = @curl_getinfo($sites[$i][0],
                        CURLINFO_SIZE_DOWNLOAD);
                    $sites[$i][self::DNS_TIME] = @curl_getinfo($sites[$i][0],
                        CURLINFO_NAMELOOKUP_TIME);
                    $sites[$i][self::TOTAL_TIME] = @curl_getinfo($sites[$i][0],
                        CURLINFO_TOTAL_TIME);
                    $sites[$i][self::HTTP_CODE] =
                        curl_getinfo($sites[$i][0], CURLINFO_HTTP_CODE);
                    if (!$sites[$i][self::HTTP_CODE] && !$is_gopher) {
                        $sites[$i][self::HTTP_CODE] = curl_error($sites[$i][0]);
                    } else {
                        $sites[$i][self::HTTP_CODE] = 200;
                    }
                    if ($ip_addresses) {
                        $sites[$i][self::IP_ADDRESSES] = $ip_addresses;
                    } else {
                        $sites[$i][self::IP_ADDRESSES] = ["0.0.0.0"];
                    }
                    //Get Time, Mime type and Character encoding
                    $sites[$i][self::TIMESTAMP] = time();
                    if ($is_gopher) {
                        $path = UrlParser::getPath($sites[$i][self::URL]);
                        $filename =
                            UrlParser::getDocumentFilename(
                                $sites[$i][self::URL]);
                        if (isset($path[1])) {
                            $gopher_type = $path[1];
                        } else {
                            $gopher_type = 1;
                        }
                        if ($gopher_type == 1) {
                            $sites[$i][self::TYPE] = "text/gopher";
                        } else if (in_array($gopher_type,
                            [0, 3, 6])) {
                            $sites[$i][self::TYPE] = "text/plain";
                            if ($gopher_type == 6) {
                                $sites[$i][$value] = convert_uudecode(
                                    $content);
                            }
                        } else if ($gopher_type == 'h') {
                            $sites[$i][self::TYPE] = "text/html";
                        } else if ($gopher_type == 'g') {
                            $sites[$i][self::TYPE] = "image/gif";
                        }
                        $path_info = pathinfo($filename);
                        if (!isset($sites[$i][self::TYPE]) &&
                            isset($path_info['extension'])) {
                            $sites[$i][self::TYPE] =
                                UrlParser::guessMimeTypeFromFileName($filename);
                        } else if (!isset($sites[$i][self::TYPE])) {
                            $sites[$i][self::TYPE] = "unknown";
                        }
                    } else {
                        $type_parts =
                            explode(";", curl_getinfo($sites[$i][0],
                                CURLINFO_CONTENT_TYPE));
                        $sites[$i][self::TYPE] =
                            strtolower(trim($type_parts[0]));
                    }
                }
                curl_multi_remove_handle($agent_handler, $sites[$i][0]);
                curl_close($sites[$i][0]);
                if (isset($sites[$i]['ROBOT']) && $sites[$i]['ROBOT']) {
                    if (isset($sites[$i][self::TYPE]) &&
                        $sites[$i][self::TYPE] != "text/plain" &&
                        isset($sites[$i][CrawlConstants::LOCATION]) &&
                        count($site[CrawlConstants::LOCATION]) > 0) {
                        $sites[$i][self::TYPE] = "text/plain";
                        $sites[$i][self::HTTP_CODE] = "200";
                        $tmp = wordwrap($sites[$i][$value], 80);
                        $tmp_parts = explode("\n", $tmp);
                        $tmp = "# Suspect server misconfiguration\n";
                        $tmp .= "# Assume shouldn't crawl this site.\n";
                        $tmp .= "# Pretending got following robots.txt.\n";
                        $tmp .= "User-agent: *\n";
                        $tmp .= "Disallow: /\n";
                        $tmp .= "# Original error code: ".
                            $sites[$i][self::HTTP_CODE]."\n";
                        $tmp .= "# Original content:\n";
                        foreach ($tmp_parts as $part) {
                            $tmp = "#".$part."\n";
                        }
                        $sites[$i][$value] = $tmp;
                        $sites[$i][self::HTTP_CODE] = "200";
                        unset($site[CrawlConstants::LOCATION]);
                    }
                }
            } //end big if
        } //end for
        if ($timer) {
            crawlLog("  Get Page Content time ".
                (changeInMicrotime($start_time)));
        }
        if(microtime(true) - $handler_time > C\PAGE_TIMEOUT) {
            curl_multi_close($agent_handler);
            $agent_handler = null;
        }
        return $sites;
    }
    /**
     * Curl requests are typically done using cache data which is stored
     * after ### at the end of urls if this is possible. To make this
     * work. The http Host: with the url is added a header after the
     * for the curl request. The job of this function is to do this replace
     * @param string $url site to download with ip address at end potentially
     *  afte ###
     * @param bool $minimal don't try to do replacement, but do add an Expect
     *     header
     * @param array $proxy_servers if not empty an array of proxy servers
     *     used to crawl through
     * @return array 3-tuple (orig url, url with replacement, http header array)
     */
    public static function prepareUrlHeaders($url, $minimal = false,
        $proxy_servers = [])
    {
        $url = str_replace("&amp;", "&", $url);
        $is_gopher = false;
        if (substr($url, 0, 6) == "gopher") {
            $is_gopher = true;
        }
        /*Check if an ETag was added by the queue server. If found, create
          If-None_Match header with the ETag and add it to the headers. Remove
          ETag from URL
         */
        $if_none_match = "If-None-Match";
        $etag = null;
        if (C\USE_ETAG_EXPIRES && stristr($url, "ETag:")) {
            $etag_parts = preg_split("/ETag\:/i", $url);
            $etag_data = explode(" ", $etag_parts[1]);
            $etag = $etag_data[1];
            $pos = strrpos($url, "ETag:");
            $url = substr_replace($url, "", $pos, strlen("ETag: ".$etag));
        }

        /* in queue_server we added the ip (if available)
          after the url followed by ###
         */
        $headers = [];
        if (!$minimal) {
            $url_ip_parts = explode("###", $url);
            if ($proxy_servers != [] || (isset($url_ip_parts[0]) &&
                (stripos($url_ip_parts[0],'.onion') !== false)) ) {
                $url_ip_parts = [$url_ip_parts[0]];
                $url = $url_ip_parts[0];
            }
            if (count($url_ip_parts) > 1) {
                $ip_address = ltrim(urldecode(array_pop($url_ip_parts)), "#");
                $len = strlen(inet_pton($ip_address));
                if ($len == 4 || $len == 16) {
                    if ($len == 16) {
                      $ip_address= "[$ip_address]";
                    }
                    if (count($url_ip_parts) > 1) {
                        $url = implode("###", $url_ip_parts);
                    } else {
                        $url = $url_ip_parts[0];
                    }
                    $url_parts = @parse_url($url);
                    if (isset($url_parts['host'])) {
                        $cnt = 1;
                        $url_with_ip_if_possible =
                            str_replace($url_parts['host'], $ip_address ,$url,
                                 $cnt);
                        if ($cnt != 1) {
                            $url_with_ip_if_possible = $url;
                        } else {
                            $headers[] = "Host:".$url_parts['host'];
                        }
                    }
                } else {
                    $url_with_ip_if_possible = $url;
                }
            } else {
                $url_with_ip_if_possible = $url;
            }
        } else {
            $url_with_ip_if_possible = $url;
        }
        $headers[] = 'Expect:';
        if (C\USE_ETAG_EXPIRES && $etag !== null) {
            $etag_header = $if_none_match.": ".$etag;
            $headers[] = $etag_header;
        }
        if ($is_gopher) {
            $headers = "gopher";
        }
        $results = [$url, $url_with_ip_if_possible, $headers];
        return $results;
    }
    /**
     * Computes a hash of a string containing page data for use in
     * deduplication of pages with similar content
     *
     * @param string& $page reference to web page data
     * @return string 8 byte hash to identify page contents
     */
    public static function computePageHash(&$page)
    {
        /* to do dedup we strip script, noscript, and style tags
           as well as their content, then we strip tags, get rid
           of whitespace and hash
         */
        $strip_array =
            ['@<script[^>]*?>.*?</script>@si',
                '@<noscript[^>]*?>.*?</noscript>@si',
                '@<style[^>]*?>.*?</style>@si'];
        $dedup_string = preg_replace(
            $strip_array, '', $page);
        $dedup_string_old = preg_replace(
            '/\W+/', '', $dedup_string);
        $dedup_string = strip_tags($dedup_string_old);
        if ($dedup_string == "") {
            $dedup_string = $dedup_string_old;
        }
        $dedup_string = preg_replace(
            '/\W+/', '', $dedup_string);
        return crawlHash($dedup_string, true);
    }
    /**
     * Splits an http response document into the http headers sent
     * and the web page returned. Parses out useful information from
     * the header and return an array of these two parts and the useful info.
     *
     * @param string& $header_and_page reference to string of downloaded data
     * @param string $value field to store the page portion of page
     * @return array info array consisting of a header, page for an http
     *     response, as well as parsed from the header the server, server
     *     version, operating system, encoding, and date information.
     */
    public static function parseHeaderPage(&$header_and_page,
        $value=CrawlConstants::PAGE)
    {
        $cache_page_validators = [];
        $cache_page_validators['etag'] = -1;
        $cache_page_validators['expires'] = -1;
        $new_offset = 0;
        // header will include all redirect headers
        $site = [];
        $site[CrawlConstants::LOCATION] = [];
        do {
            $continue = false;
            $CRLFCRLF = strpos($header_and_page, "\x0D\x0A\x0D\x0A",
                $new_offset);
            $LFLF = strpos($header_and_page, "\x0A\x0A", $new_offset);
            //either two CRLF (what spec says) or two LF's to be safe
            $old_offset = $new_offset;
            $header_offset = ($CRLFCRLF > 0) ? $CRLFCRLF : $LFLF;
            $header_offset = ($header_offset) ? $header_offset : 0;
            $new_offset = ($CRLFCRLF > 0) ? $header_offset + 4
                : $header_offset + 2;
            $redirect_pos = stripos($header_and_page, 'Location:', $old_offset);
            $redirect_str = "Location:";
            if ($redirect_pos === false) {
                $redirect_pos =
                    stripos($header_and_page, 'Refresh:', $old_offset);
                $redirect_str = "Refresh:";
            }
            if (isset($header_and_page[$redirect_pos - 1]) &&
                ord($header_and_page[$redirect_pos - 1]) > 32) {
                $redirect_pos = $new_offset; //ignore X-XRDS-Location header
            } else if ($redirect_pos !== false && $redirect_pos < $new_offset){
                $redirect_pos += strlen($redirect_str);
                $pre_line = substr($header_and_page, $redirect_pos,
                    strpos($header_and_page, "\n", $redirect_pos) -
                    $redirect_pos);
                $loc = @trim($pre_line);
                if (strlen($loc) > 0) {
                    $site[CrawlConstants::LOCATION][] = @$loc;
                }
                $continue = true;
            }
        } while($continue);
        if ($header_offset > 0) {
            $site[CrawlConstants::HEADER] =
                substr($header_and_page, 0, $header_offset);
            $site[$value] = ltrim(substr($header_and_page, $header_offset));
        } else { //header message no body; maybe 301?
            $site[CrawlConstants::HEADER] = $header_and_page;
            $site[$value] = " ";
        }
        $lines = explode("\n", $site[CrawlConstants::HEADER]);
        $first_line = array_shift($lines);
        $response = preg_split("/(\s+)/", $first_line);
        $site[CrawlConstants::HTTP_CODE] = isset($response[1]) ?
             @trim($response[1]) : 0;
        $site[CrawlConstants::ROBOT_METAS] = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (stristr($line, 'Server:')) {
                $server_parts = preg_split("/Server\:/i", $line);
                $server_name_parts = @explode("/", $server_parts[1]);
                $site[CrawlConstants::SERVER] = @trim($server_name_parts[0]);
                if (isset($server_name_parts[1])) {
                    $version_parts = explode("(", $server_name_parts[1]);
                    $site[CrawlConstants::SERVER_VERSION] =
                        @trim($version_parts[0]);
                    if (isset($version_parts[1])) {
                        $os_parts = explode(")", $version_parts[1]);
                        $site[CrawlConstants::OPERATING_SYSTEM] =
                            @trim($os_parts[0]);
                    }
                }
            }
            if (stristr($line, 'Content-type:')) {
                list(,$mimetype,) = preg_split("/:|;/i", $line);
                $site[CrawlConstants::TYPE] = trim($mimetype);
            }
            if (stristr($line, 'charset=')) {
                $line_parts = preg_split("/charset\=/i", $line);
                $site[CrawlConstants::ENCODING] =
                    strtoupper(@trim($line_parts[1]));
            }
            if (stristr($line, 'Last-Modified:')) {
                $line_parts = preg_split("/Last\-Modified\:/i", $line);
                $site[CrawlConstants::MODIFIED] =
                    strtotime(@trim($line_parts[1]));
            }
            if (stristr($line, 'X-Robots-Tag:')) { // robot directives pdfs etc
                $line_parts = preg_split("/X\-Robots\-Tag\:/i", $line);
                $robot_metas = explode(",", $line_parts[1]);
                foreach ($robot_metas as $robot_meta) {
                    $site[CrawlConstants::ROBOT_METAS][] = strtoupper(
                        trim($robot_meta));
                }
            }
            $canonical_regex = "/Link\:\s*\<\s*(http.*)\s*\>\s*\;\s*".
                "rel\s*\=\s*(\"|')?canonical(\"|')?/";
            // levenshtein gives notices on strings longer than 255
            if (preg_match($canonical_regex, $line, $matches) &&
                isset($site[CrawlConstants::URL]) && strlen($matches[1]) < 252
                && (strlen($site[CrawlConstants::URL]) >= 255 ||
                levenshtein($matches[1], $site[CrawlConstants::URL]) > 3)) {
                // for rel canonical headers
                $site[CrawlConstants::LOCATION][] = $matches[1];
                $site[CrawlConstants::ROBOT_METAS][] = 'NOFOLLOW';
            }
            if (C\USE_ETAG_EXPIRES && stristr($line, 'ETag:')) {
                $line_parts = preg_split("/ETag\:/i", $line);
                if (isset($line_parts[1])) {
                    $etag_data = explode(" ", $line_parts[1]);
                    if (isset($etag_data[1])) {
                        $etag = $etag_data[1];
                        $cache_page_validators['etag'] = $etag;
                    }
                }
            }
            if (C\USE_ETAG_EXPIRES && stristr($line, 'Expires:')) {
                $line_parts = preg_split("/Expires\:/i", $line);
                $all_dates = $line_parts[1];
                $date_parts = explode(",", $all_dates);
                if (count($date_parts) == 2) {
                    $cache_page_validators['expires'] = strtotime(
                        $date_parts[1]);
                } else if (count($date_parts) > 2) {
                    /*Encountered some pages with more than one Expires date
                      :O */
                    $timestamps = [];
                    for ($i = 1;$i < count($date_parts); $i += 2) {
                        $ds = strtotime($date_parts[$i]);
                        $timestamps[] = $ds;
                    }
                    $lowest = min($timestamps);
                    $cache_page_validators['expires'] = $lowest;
                }
            }
            if (C\USE_ETAG_EXPIRES && !($cache_page_validators['etag'] == -1 &&
                $cache_page_validators['expires'] == -1)) {
                $site[CrawlConstants::CACHE_PAGE_VALIDATORS] =
                    $cache_page_validators;
            }
        }
        /*
           If the doc is HTML and it uses a http-equiv to set the encoding
           then we override what the server says (if anything). As we
           are going to convert to UTF-8 we remove the charset info
           from the meta tag so cached pages will display correctly and
           redirects without char encoding won't be given a different hash.
         */
        $encoding_info = guessEncodingHtml($site[$value], true);
        if (is_array($encoding_info)) {
            list($site[CrawlConstants::ENCODING], $start_charset, $len_c) =
            $encoding_info;
            $site[$value] = substr_replace($site[$value], "", $start_charset,
                $len_c);
        } else {
            $site[CrawlConstants::ENCODING] = $encoding_info;
        }

        if (!isset($site[CrawlConstants::SERVER]) ) {
            $site[CrawlConstants::SERVER] = "unknown";
        }
        return $site;
    }
    /**
     * Computes the IP address from http get-responser header
     *
     * @param string $header contains complete transcript of HTTP get/response
     * @return string IPv4 address as a string of dot separated quads.
     */
    public static function getCurlIp($header)
    {
        if (preg_match_all('/Trying\s+(.*)(\.\.\.)/',
            $header, $matches)) {
            $out_addresses = [];
            $addresses = array_unique($matches[1]);
            foreach ($addresses as $address) {
                $num = @inet_pton($address);
                if ($num !== false) {
                    $out_addresses[] = $address;
                }
            }
            if ($out_addresses != []) {
                return $out_addresses;
            }
            return false;
        } else {
            return false;
        }
    }
    /**
     * Make a curl request for the provided url
     *
     * @param string $site url of page to request
     * @param array $post_data  any data to be POST'd to the URL
     * @param bool $check_for_errors whether or not to check the response
     *      for the words, NOTICE, WARNING, FATAL which might indicate an
     *      error on the server
     * @param string $user_password username:password to use for connection if
     *      needed (optional)
     * @return string the contents of what the curl request fetched
     */
    public static function getPage($site, $post_data = null,
        $check_for_errors = false, $user_password = null)
    {
        static $agents = [];
        $not_web_setting = (php_sapi_name() == 'cli');
        $MAX_SIZE = 50;
        $host = @parse_url($site, PHP_URL_HOST);
        if ($host !== false) {
            if (count($agents) > $MAX_SIZE) {
                $agent_host = array_shift($agents);
                if ($agent_host) {
                    curl_close($agent_host);
                }
            }
            if (!isset($agents[$host])) {
                $agents[$host] = curl_init();
            }
        }
        if ($not_web_setting) {
            crawlLog("  Init curl request of a single page");
        }
        curl_setopt($agents[$host], CURLOPT_USERAGENT, C\USER_AGENT);
        curl_setopt($agents[$host], CURLOPT_URL, $site);
        curl_setopt($agents[$host], CURLOPT_AUTOREFERER, true);
        curl_setopt($agents[$host], CURLOPT_FOLLOWLOCATION, true);
        // these next two lines should probably be modified for better security
        curl_setopt($agents[$host], CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($agents[$host], CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($agents[$host], CURLOPT_NOSIGNAL, true);
        curl_setopt($agents[$host], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($agents[$host], CURLOPT_FAILONERROR, true);
        curl_setopt($agents[$host], CURLOPT_TIMEOUT, C\SINGLE_PAGE_TIMEOUT);
        curl_setopt($agents[$host], CURLOPT_CONNECTTIMEOUT, C\PAGE_TIMEOUT);
        //make lighttpd happier
        curl_setopt($agents[$host], CURLOPT_HTTPHEADER, ['Expect:']);
        if ($post_data != null) {
            curl_setopt($agents[$host], CURLOPT_POST, true);
            curl_setopt($agents[$host], CURLOPT_POSTFIELDS, $post_data);
        } else {
            // since we are caching agents, need to do this so doesn't get stuck
            // as post and so query string ignored for get's
            curl_setopt($agents[$host], CURLOPT_HTTPGET, true);
        }
        if($user_password != null) {
            curl_setopt($agents[$host], CURLOPT_FAILONERROR, false);
            curl_setopt($agents[$host], CURLOPT_USERPWD, $user_password);
            curl_setopt($agents[$host], CURLOPT_SSL_VERIFYHOST, 2);
        }
        if ($not_web_setting) {
            crawlLog("  Set curl options for single page request");
        }
        $time = time();
        $response = curl_exec($agents[$host]);
        if (time() - $time > C\PAGE_TIMEOUT && $not_web_setting) {
            crawlLog("  Request took longer than page timeout!!");
            crawlLog("  Either could not reach URL or website took too");
            crawlLog("  long to respond.");
        }
        curl_setopt($agents[$host], CURLOPT_POSTFIELDS, "");
        if ($not_web_setting) {
            crawlLog("  Done curl exec");
        }
        if ($not_web_setting && $check_for_errors) {
            self::checkResponseForErrors($response);
        }
        return $response;
    }
    /**
     * Given the results of a getPage call, check whether or not the response
     * had the words NOTICE, WARNING, FATAL which might indicate an error on
     * the server. If it does, then the $response string is sent to the
     * crawlLog
     *
     * @param string $response getPage response in which to check for errors
     */
    public static function checkResponseForErrors($response)
    {
        if (preg_match("/NOTICE|WARNING|FATAL/i", $response)) {
            crawlLog("There appears to have been an error in the server ".
                "response. Response was:");
            crawlLog(wordwrap($response));
        }
    }
}
