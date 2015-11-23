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
 * @author Chris Pollett chris@pollett.org (initial MediaJob class
 *      and subclasses based on work of Pooja Mishra for her master's)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\media_jobs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\Models;

/**
 * A media job to download and index feeds from various search sources (RSS,
 * HTML scraper, etc). Idea is that this job runs once an hour to get the
 * latest news from those sources.
 */
class NewsUpdateJob extends MediaJob
{
    /**
     * how long in seconds before a news item expires
     */
    const ITEM_EXPIRES_TIME = C\ONE_WEEK;
    /** 
     * Mamimum number of feeds to download in one try
     */
    const MAX_FEEDS_ONE_GO = 100;
    /**
     * Time in current epoch when news last updated
     * @var int
     */
    public $update_time;
    /**
     * Datasource object used to run db queries related to news items 
     * (for storing and updating them)
     * @var object
     */
    public $db;
    /**
     * Initializes the last update time to far in the past so, news will get
     * immediately updated. Sets up connect to DB to store news items, and
     * makes it so the same media job runs both on name server and client
     * Media Updaters
     */
    public function init()
    {
        $this->update_time = 0;
        $this->name_server_does_client_tasks = true;
        $this->name_server_does_client_tasks_only = true;
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
    }
    /**
     * Only update if its been more than an hour since the last update
     *
     * @return bool whether its been an hour since the last update
     */
    public function checkPrerequisites()
    {
        $time = time();
        $something_updated = false;
        $delta = $time - $this->update_time;
        if ($delta > C\ONE_HOUR) {
            $this->update_time = $time;
            L\crawlLog("Performing news feeds update");
            return true;
        }
        return false;
    }
    /**
     * Get the media sources from the local database and use those to run the
     * the same task as in the distributed setting
     */
    public function nondistributedTasks()
    {
        $db = $this->db;
        $sql = "SELECT * FROM MEDIA_SOURCE WHERE (TYPE='rss'
             OR TYPE='html' OR TYPE='json')";
        $result = $db->execute($sql);
        $i = 0;
        while ($feeds[$i] = $db->fetchArray($result)) {
            $aux_parts = explode("###",
                    html_entity_decode($feeds[$i]['AUX_INFO'], ENT_QUOTES));
            if (in_array($feeds[$i]['TYPE'], ['html', 'json'])) {
                list($feeds[$i]['CHANNEL_PATH'], $feeds[$i]['ITEM_PATH'],
                $feeds[$i]['TITLE_PATH'], $feeds[$i]['DESCRIPTION_PATH'],
                $feeds[$i]['LINK_PATH']) =
                    $aux_parts;
                $offset = 5;
            } elseif ($feeds[$i]['TYPE'] == 'rss') {
                $offset = 0;
            }
            if (isset($aux_parts[$offset])) {
                $feeds[$i]['IMAGE_XPATH'] = $aux_parts[$offset];
            } else {
                $feeds[$i]['IMAGE_XPATH'] = "";
            }
            if ($feeds[$i]['TYPE'] == 'json') {
                /* this is to avoid name collisions with html tag names when
                   we convert json to html and dom it. The making of tags
                   with the json prefix is done in convertJsonDecodeToTags.
                   Here we are making our xpaths compatible with this
                 */
                foreach (['CHANNEL_PATH', 'ITEM_PATH',
                    'TITLE_PATH', 'DESCRIPTION_PATH', 'LINK_PATH',
                    'IMAGE_XPATH'] as $component) {
                    $xpath = $feeds[$i][$component];
                    $xpath_parts = explode("/", $xpath);
                    $num_parts = count($xpath_parts);
                    for ($j = 0; $j < $num_parts; $j++) {
                        if ($xpath_parts[$j] != "") {
                            $xpath_parts[$j] = "json" . $xpath_parts[$j];
                        }
                    }
                    $feeds[$i][$component] = implode("/", $xpath_parts);
                }
            }
            $i++;
        }
        unset($feeds[$i]); //last one will be null
        $this->tasks = $feeds;
        $this->doTasks($feeds);
    }
    /**
     * For each feed source downloads the feeds, checks which items are
     * not in the database, adds them. Then calls the method to rebuild the
     * inverted index shard for news
     *
     * @param array $tasks array of news feed info (url to download, paths to
     *  extract etc)
     */
    public function doTasks($tasks)
    {
        if (!is_array($tasks)) {
            L\crawlLog(
                "----This media updater is NOT responsible for any feeds!");
            return;
        }
        $feeds = $tasks;
        L\crawlLog("----This media updater is responsible for the feeds:");
        $i = 1;
        foreach ($feeds as $feed) {
            L\crawlLog("----  $i. ".$feed["NAME"]);
            $i++;
        }
        $num_feeds = count($feeds);
        $feeds_one_go = self::MAX_FEEDS_ONE_GO;
        $limit = 0;
        while ($limit < $num_feeds) {
            $feeds_batch = array_slice($feeds, $limit, $feeds_one_go);
            $this->updateFeedItemsOneGo($feeds, self::ITEM_EXPIRES_TIME);
            $limit += $feeds_one_go;
        }
        $this->rebuildFeedShard(self::ITEM_EXPIRES_TIME);
    }
    /**
     * Handles the request to get the  array of news feed sources which hash to
     * a particular value i.e. match with the index of requesting machine's
     * hashed url/name from array of available machines hash
     *
     * @param int $machine_id id of machine making request for news feeds
     * @param array $data not used but inherited from the base MediaJob
     *      class as a parameter (so will alwasys be null in this case)
     * @return array of feed urls and paths to extract from them
     */
    public function getTasks($machine_id, $data = null)
    {
        $parent = $this->controller;
        if (!$parent) {
            return;
        }
        $source_model = $parent->model("source");
        $current_machine = $machine_id;
        $machine_hashes = $source_model->getMachineHashUrls();
        $machine_index_match = array_search($current_machine, $machine_hashes);
        if ($machine_index_match === false) {
            return [];
        }
        $num_machines = count($machine_hashes);
        $pre_feeds = $source_model->getMediaSources("rss");
        $pre_feeds = array_merge($pre_feeds,
            $source_model->getMediaSources("html"));
        $pre_feeds = array_merge($pre_feeds,
            $source_model->getMediaSources("json"));
        if (!$pre_feeds) {
            return false;
        }
        $feeds = [];
        foreach ($pre_feeds as $pre_feed) {
            if (!isset($pre_feed['NAME'])) {
                continue;
            }
            $hash_int = unpack("N", L\crawlHash($pre_feed['NAME']));
            if (!isset($hash_int[1])) {
                continue;
            }
            $hash_index = ($hash_int[1]) % $num_machines;
            if ($machine_index_match != $hash_index) {continue; }
            $aux_parts = explode("###",
                html_entity_decode($pre_feed['AUX_INFO']));
            $offset = 0;
            if (in_array($pre_feed['TYPE'], ['html', 'json'])) {
                list($pre_feed['CHANNEL_PATH'], $pre_feed['ITEM_PATH'],
                    $pre_feed['TITLE_PATH'], $pre_feed['DESCRIPTION_PATH'],
                    $pre_feed['LINK_PATH'], $pre_feed['LINK_PATH']) =
                    $aux_parts;
                $offset = 5;
            }
            if (isset($aux_parts[$offset])) {
                $pre_feed['IMAGE_XPATH'] = $aux_parts[$offset];
            } else {
                $pre_feed['IMAGE_XPATH'] = "";
            }
            if ($pre_feed['TYPE'] == 'json') {
                /* this is to avoid name collisions with html tag names when
                   we convert json to html and dom it. The making of tags
                   with the json prefix is done in convertJsonDecodeToTags.
                   Here we are making our xpaths compatible with this
                 */
                foreach ($pre_feed as $component => $xpath) {
                    $xpath_parts = explode("/", $xpath);
                    $num_parts = count($xpath_parts);
                    for ($i = 0; $i < $num_parts; $i++) {
                        if ($xpath_parts[$i] != "") {
                            $xpath_parts[$i] = "json" . $xpath_parts[$i];
                        }
                    }
                    $pre_feed[$component] = implode("/", $xpath_parts);
                }
            }
            $feeds[] = $pre_feed;
        }
        return $feeds;
    }
    /**
     * Downloads one batch of $feeds_one_go feed items for @see updateFeedItems
     * For each feed source downloads the feeds, checks which items are
     * not in the database, adds them. This method does not update
     * the inverted index shard.
     *
     * @param array $feeds list of feeds to download
     * @param int $age how many seconds old records should be ignored
     */
    public function updateFeedItemsOneGo($feeds, $age = C\ONE_WEEK)
    {
        $feeds = FetchUrl::getPages($feeds, false, 0, null, "SOURCE_URL",
            CrawlConstants::PAGE, true, null, true);
        $sql = "UPDATE MEDIA_SOURCE SET LANGUAGE=? WHERE TIMESTAMP=?";
        $db = $this->db;
        libxml_use_internal_errors(true);
        foreach ($feeds as $feed) {
            if (!isset($feed[CrawlConstants::PAGE]) ||
                !$feed[CrawlConstants::PAGE]) {
                L\crawlLog("...No data in feed skipping.");
                continue;
            }
            L\crawlLog("----Updating {$feed['NAME']}. Making dom ".
                "object from feed.");
            $is_html = ($feed['TYPE'] == 'html') ? true : false;
            $is_json = ($feed['TYPE'] == 'json') ? true : false;
            $dom = new \DOMDocument();
            if ($is_json) {
                $json_decode = json_decode($feed[CrawlConstants::PAGE], true);
                $page = "<html><body>".
                    $this->convertJsonDecodeToTags($json_decode) .
                    "</body></html>";
                $is_html = true;
            } else {
                //strip namespaces
                $page = preg_replace('@<(/?)(\w+\s*)\:@u', '<$1',
                    $feed[CrawlConstants::PAGE]);
            }
            if (isset($feed['IMAGE_XPATH'])) {
                $feed['IMAGE_XPATH'] = preg_replace('@/(\s*\w+\s*)\:@u', '/',
                    $feed['IMAGE_XPATH']);
            }
            if ($is_html) {
                @$dom->loadHTML($page);
            } else {
                /*
                    We parse using loadHTML as less strict. loadHTML
                    auto-closes link tags immediately after open link
                    so to avoid this we replace link with xlink
                 */
                $page = preg_replace("@<link@", "<slink", $page);
                $page = preg_replace("@</link@", "</slink", $page);
                $page = preg_replace("@pubDate@i", "pubdate", $page);
                $page = preg_replace("@&lt;@", "<", $page);
                $page = preg_replace("@&gt;@", ">", $page);
                $page = preg_replace("@<!\[CDATA\[(.+?)\]\]>@", '$1', $page);
                // we also need a hack to make UTF-8 work correctly
                @$dom->loadHTML('<?xml encoding="UTF-8">' . $page);
                foreach ($dom->childNodes as $item)
                if ($item->nodeType == XML_PI_NODE)
                    $dom->removeChild($item);
                $dom->encoding = 'UTF-8';
            }
            L\crawlLog("----...done. Extracting info about whole feed.");
            $lang = "";
            if ($feed['TYPE'] != 'html' &&
                !isset($feed["LANGUAGE"]) || $feed["LANGUAGE"] == "") {
                $languages = $dom->getElementsByTagName('language');
                if ($languages && is_object($languages) &&
                    is_object($languages->item(0))) {
                    $lang = $languages->item(0)->textContent;
                    $db->execute($sql, [$lang, $feed['TIMESTAMP']]);
                }
            } elseif (isset($feed["LANGUAGE"]) && $feed["LANGUAGE"] != "") {
                $lang = $feed["LANGUAGE"];
            } else {
                $lang = C\DEFAULT_LOCALE;
            }
            L\crawlLog("----...Language is $lang. Getting " .
                "channel, finding nodes.");
            if ($is_html) {
                $sub_dom = $this->getTags($dom, $feed['CHANNEL_PATH']);
                if (!$sub_dom) {
                    L\crawlLog("----... Scraper couldn't parse channel".
                        " path so bailing on this feed.");
                    continue;
                } else {
                    L\crawlLog("----...Channel scraped.");
                }
                $nodes = $this->getTags($sub_dom[0], $feed['ITEM_PATH']);
                $rss_elements = ["title" => $feed['TITLE_PATH'],
                    "description" => $feed['DESCRIPTION_PATH'],
                    "link" => $feed['LINK_PATH']];
            } else {
                $nodes = $dom->getElementsByTagName('item');
                // see above comment on why slink rather than link
                $rss_elements = ["title" => "title",
                    "description" 
                    => "description", "link" =>"slink",
                    "guid" => "guid", "pubdate" => "pubdate"];
                if ($nodes->length == 0) {
                    // maybe we're dealing with atom rather than rss
                    $nodes = $dom->getElementsByTagName('entry');
                    $rss_elements = [
                        "title" => "title", "description" => "summary",
                        "link" => "slink", "guid" => "id",
                        "pubdate" => "updated"];
                }
            }
            L\crawlLog("----...done extracting info. Check for new news ".
                "items in {$feed['NAME']}.");
            $num_added = 0;
            $num_seen = 0;
            foreach ($nodes as $node) {
                $item = [];
                foreach ($rss_elements as $db_element => $feed_element) {
                    L\crawlTimeoutLog("----still adding feed items to index.");
                    if ($is_html) {
                        $tag_nodes = $this->getTags($node, $feed_element);
                        if (!isset($tag_nodes[0])) {
                            $tag_node = null;
                        } else {
                            $tag_node = $tag_nodes[0];
                        }
                        $element_text = (is_object($tag_node)) ?
                            $tag_node->textContent: "";
                    } else {
                        $tag_node = $node->getElementsByTagName(
                                $feed_element)->item(0);
                        $element_text = (is_object($tag_node)) ?
                            $tag_node->nodeValue: "";
                    }
                    if ($db_element == "link" && $tag_node &&
                        ($element_text == "" || ($is_html && !$is_json))) {
                        if ($is_html) {
                            $element_text =
                               $tag_node->documentElement->getAttribute("href");
                        } else {
                            $element_text = $tag_node->getAttribute("href");
                        }
                        $element_text = UrlParser::canonicalLink($element_text,
                            $feed["SOURCE_URL"]);
                    }
                    if ($db_element == "link" && $tag_node && $is_json) {
                        $element_text = UrlParser::canonicalLink($element_text,
                            $feed["SOURCE_URL"]);
                    }
                    $item[$db_element] = strip_tags($element_text);
                }
                $item['image_link'] = "";
                if (isset($feed['IMAGE_XPATH']) && $feed['IMAGE_XPATH'] != "") {
                    if ($feed['IMAGE_XPATH'][0]=="^") {
                        $is_channel_image = true;
                        $image_xpath = substr($feed['IMAGE_XPATH'], 1);
                    } else {
                        $is_channel_image = false;
                        $image_xpath = $feed['IMAGE_XPATH'];
                    }
                    if ($is_html) {
                        if ($is_channel_image) {
                            $dom_xpath = new \DOMXPath($sub_dom);
                        } else {
                            $dom_xpath = new \DOMXPath($node);
                        }
                        $image_nodes =
                            $dom_xpath->evaluate($image_xpath);
                        if ($image_nodes && $image_nodes->item(0)) {
                            $item['image_link'] =
                                $image_nodes->item(0)->nodeValue;
                        }
                    } else {
                        $dom_xpath = new \DOMXPath($dom);
                        if ($is_channel_image) {
                            $query = $image_xpath;
                        } else {
                            $query = $node->getNodePath() . $image_xpath;
                        }
                        libxml_clear_errors();
                        $image_nodes = $dom_xpath->evaluate($query);
                        if ($image_nodes && $image_nodes->item(0)) {
                            $item['image_link'] =
                                $image_nodes->item(0)->nodeValue;
                        }
                    }
                }
                $did_add = $this->addFeedItemIfNew($item, $feed['NAME'], $lang,
                    $age);
                if ($did_add) {
                    $num_added++;
                }
                $num_seen++;
            }
            L\crawlLog("----...added $num_added news items of $num_seen ".
                "on rss page.\n Done Processing {$feed['NAME']}.");
        }
    }
    /**
     * Returns an array of DOMDocuments for the nodes that match an xpath
     * query on $dom, a DOMDocument
     *
     * @param DOMDocument $dom document to run xpath query on
     * @param string $query xpath query to run
     * @return array of DOMDocuments one for each node matching the
     *  xpath query in the orginal DOMDocument
     */
    public function getTags($dom, $query)
    {
        $nodes = [];
        $dom_xpath = new \DOMXPath($dom);
        if (!$dom_xpath) {
            return [];
        }
        $tags = $dom_xpath->query($query);
        if (!$tags) {
            return [];
        }
        $i = 0;
        while ($item = $tags->item($i)) {
            $tmp_dom = new \DOMDocument;
            $tmp_dom->formatOutput = true;
            $node = $tmp_dom->importNode($item, true);
            $tmp_dom->appendChild($node);
            $nodes[] = $tmp_dom;
            $i++;
        }
        return $nodes;
    }
    /**
     * Copies all feeds items newer than $age to a new shard, then deletes
     * old index shard and database entries older than $age. Finally sets copied
     * shard to be active. If this method is going to take max_execution_time/2
     * it returns false, so an additional job can be schedules; otherwise
     * it returns true
     *
     * @param int $age how many seconds old records should be deleted
     * @return bool whether job executed to complete
     */
    public function rebuildFeedShard($age)
    {
        $time = time();
        $feed_shard_name = C\WORK_DIRECTORY."/feeds/index";
        $prune_shard_name = C\WORK_DIRECTORY."/feeds/prune_index";
        $prune_shard =  new IndexShard($prune_shard_name);
        $too_old = $time - $age;
        if (!$prune_shard) {
            return false;
        }
        $pre_feeds = $this->tasks;
        if (!$pre_feeds) {
            return false;
        }
        $feeds = [];
        foreach ($pre_feeds as $pre_feed) {
            if (!isset($pre_feed['NAME'])) {
                continue;
            }
            $feeds[$pre_feed['NAME']] = $pre_feed;
        }
        $db = $this->db;
        // we now rebuild the inverted index with the remaining items
        $sql = "SELECT * FROM FEED_ITEM ".
            "WHERE PUBDATE >= ? ".
            "ORDER BY PUBDATE DESC";
        $result = $db->execute($sql, [$too_old]);
        if ($result) {
            $completed = true;
            L\crawlLog("----..still deleting. Making new index" .
                " of non-pruned items.");
            $i = 0;
            while ($item = $db->fetchArray($result)) {
                L\crawlTimeoutLog(
                    "----..have added %s non-pruned items to index.", $i);
                $i++;
                if (!isset($item['SOURCE_NAME'])) {
                    continue;
                }
                $source_name = $item['SOURCE_NAME'];
                if (isset($feeds[$source_name])) {
                    $lang = $feeds[$source_name]['LANGUAGE'];
                } else {
                    $lang = "";
                }
                $phrase_string = $item["TITLE"] . " ". $item["DESCRIPTION"];
                $word_lists = PhraseParser::extractPhrasesInLists(
                    $phrase_string, $lang);
                $raw_guid = L\unbase64Hash($item["GUID"]);
                $doc_keys = L\crawlHash($item["LINK"], true) .
                    $raw_guid."d". substr(L\crawlHash(
                    UrlParser::getHost($item["LINK"])."/", true), 1);
                $meta_ids = $this->calculateMetas($lang, $item['PUBDATE'],
                    $source_name, $item["GUID"]);
                $prune_shard->addDocumentWords($doc_keys, $item['PUBDATE'],
                    $word_lists, $meta_ids, PhraseParser::$materialized_metas,
                    true, false);
            }
        }
        $prune_shard->save();
        @chmod($prune_shard_name, 0777);
        @chmod($feed_shard_name, 0777);
        @rename($prune_shard_name, $feed_shard_name);
        @chmod($feed_shard_name, 0777);
        $sql = "DELETE FROM FEED_ITEM WHERE PUBDATE < ?";
        $db->execute($sql, [$too_old]);
    }
    /**
     * Adds $item to FEED_ITEM table in db if it isn't already there
     *
     * @param array $item data from a single news feed item
     * @param string $source_name string name of the news feed $item was found
     * on
     * @param string $lang locale-tag of the news feed
     * @param int $age how many seconds old records should be ignored
     * @return bool whether an item was added
     */
    public function addFeedItemIfNew($item, $source_name, $lang, $age)
    {
        if (!isset($item["link"]) || !isset($item["title"]) ||
            !isset($item["description"])) {
            return false;
        }
        if (!isset($item["guid"]) || $item["guid"] == "") {
            $item["guid"] = L\crawlHash($item["link"]);
        } else {
            $item["guid"] = L\crawlHash($item["guid"]);
        }
        if (!isset($item["image_link"])) {
            $item["image_link"] = "";
        }
        $raw_guid = L\unbase64Hash($item["guid"]);
        if (!isset($item["pubdate"]) || $item["pubdate"] == "") {
            $item["pubdate"] = time();
        } else {
            $item["pubdate"] = strtotime($item["pubdate"]);
            if ($item["pubdate"] < 0) {
                $item["pubdate"] = time();
            }
        }
        if (time() - $item["pubdate"] > $age) {
            return false;
        }
        $sql = "SELECT COUNT(*) AS NUMBER FROM FEED_ITEM WHERE GUID = ?";
        $db = $this->db;
        $result = $db->execute($sql, [$item["guid"]]);
        if ($result) {
            $row = $db->fetchArray($result);
            if ($row["NUMBER"] > 0) {
                return false;
            }
        } else {
            return true;
        }
        $sql = "INSERT INTO FEED_ITEM (GUID, TITLE, LINK, IMAGE_LINK,".
            "DESCRIPTION, PUBDATE, SOURCE_NAME) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $result = $db->execute($sql, [$item['guid'],
            $item['title'], $item['link'], $item['image_link'],
            $item['description'], $item['pubdate'], $source_name]);
        if (!$result) {
            return false;
        }
        return true;
    }
    /**
     * Used to calculate the meta words for RSS feed items
     *
     * @param string $lang the locale_tag of the feed item
     * @param int $pubdate UNIX timestamp publication date of item
     * @param string $source_name the name of the news feed
     * @param string $guid the guid of the news item
     *
     * @return array $meta_ids meta words found
     */
    public function calculateMetas($lang, $pubdate, $source_name, $guid)
    {
        $meta_ids = ["media:news", "media:news:".urlencode($source_name),
            "guid:".strtolower($guid)];
        $meta_ids[] = 'date:'.date('Y', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H-i', $pubdate);
        $meta_ids[] = 'date:'.date('Y-m-d-H-i-s', $pubdate);
        if ($lang != "") {
            $lang_parts = explode("-", $lang);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if (isset($lang_parts[1])) {
                $meta_ids[] = 'lang:'.$lang;
            }
        }
        return $meta_ids;
    }
    /**
     * Converts the results of an associative array coming from a
     * json_decode'd string to an HTML string where the json field
     * have become tags prefixed with "json". This can then be handled
     * in the rest of the news updater like an HTML feed.
     *
     * @param array $json_decode associative array coming from a
     *  json_decode'd string
     * @return string result of converting array to an html string
     */
    public function convertJsonDecodeToTags($json_decode)
    {
        $out = "";
        if (is_array($json_decode)) {
            foreach ($json_decode as $key => $value) {
                $tag_name = $key;
                $attributes = "";
                if(is_int($tag_name)) {
                    $tag_name = "item";
                    $attributes = "data-number='$key'";
                }
                /* this is to avoid name collisions with html tag names when
                   we convert json to html and dom it
                 */
                $out .= "\n<json$tag_name $attributes>\n";
                $out .= $this->convertJsonDecodeToTags($value);
                $out .= "\n</json$tag_name>\n";
            }
        } else {
            $out = $json_decode;
        }
        return $out;
    }
}
