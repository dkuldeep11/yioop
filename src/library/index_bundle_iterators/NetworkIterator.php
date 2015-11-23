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
namespace seekquarry\yioop\library\index_bundle_iterators;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\AnalyticsManager;

/**
 * This iterator is used to handle querying a network of queue_servers
 * with regard to a query
 *
 * @author Chris Pollett
 */
class NetworkIterator extends IndexBundleIterator
{
    /**
     * Part of query without limit and num to be processed by all queue_server
     * machines
     *
     * @var string
     */
    public $base_query;
    /**
     * Current limit number to be added to base query
     *
     * @var string
     */
    public $limit;
    /**
     * An array of servers to ask a query to
     *
     * @var string
     */
    public $queue_servers;
    /**
     * Flags for each server saying if there are more results for that server
     * or not
     *
     * @var array
     */
    public $more_results;
    /**
     * Keeps track of whether the word_iterator list is empty because the
     * word does not appear in the index shard
     * @var int
     */
    public $filter;
    /**
     * used to adaptively change the number of pages requested from each
     * machine based on the number of machines that still have results
     * @var int
     */
    public $next_results_per_block;
    /**
     * Used to keep track of the original desired number of results to be
     * returned in one find docs call versus the number actually retrieved.
     * @var int
     */
    public $hard_query;
    /**
     * the minimum number of pages to group from a block;
     */
    const MIN_FIND_RESULTS_PER_BLOCK = 200;
    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;
    /** Length of a doc key*/
    const KEY_LEN = 8;
    /**
     * Creates a network iterator with the given parameters.
     *
     * @param string $query the query that was supplied by the end user
     *      that we are trying to get search results for
     * @param array $queue_servers urls of yioop instances on which documents
     *      indexes live
     * @param string $timestamp the timestamp of the particular current index
     *      archive bundles that we look in for results
     * @param array& $filter an array of hashes of domains to filter from
     *      results
     * @param string $save_timestamp_name if this timestamp is nonzero, then
     *      when making queries to separate machines the save_timestamp is sent
     *      so the queries on those machine can make savepoints. Note the
     *      format of save_timestamp is timestamp-query_part where query_part
     *      is the number of the item in a query presentation (usually 0).
     * @param bool $limit_news if true the number of media:news items to
     *      allow in search results is limited to WordIterator::LIMIT_NEWS_COUNT
     *
     */
    public function __construct($query, $queue_servers, $timestamp,
        &$filter = null, $save_timestamp_name = "", $limit_news = true)
    {
        $this->results_per_block = ceil(self::MIN_FIND_RESULTS_PER_BLOCK);
        $this->next_results_per_block = $this->results_per_block;
        $this->hard_query = false;
        $this->base_query = "q=".urlencode($query).
            "&f=serial&network=false&raw=1&its=$timestamp&guess=false";
        if (!$limit_news) {
            $this->base_query .= "&s=news";
        }
        if ($save_timestamp_name!="") {
            // used for archive crawls of crawl mixes
            $this->base_query .= "&save_timestamp=$save_timestamp_name";
        }
        $this->queue_servers = $queue_servers;
        $this->limit = 0;
        $count = count($this->queue_servers);
        for ($i = 0; $i < $count; $i++) {
            $this->more_flags[$i] = true;
        }
        if ($filter != null) {
            $this->filter = & $filter;
        } else {
            $this->filter = null;
        }
    }
    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation As this is not easily determined
     * for a network iterator, this method always returns 1.0 for this
     * iterator
     *
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *     relevance of
     * @return float a relevancy score based on BM25F -- always 1.0.
     */
    public function computeRelevance($generation, $posting_offset)
    {
        return 1.0;
    }
    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    public function reset()
     {
        $this->limit = 0;
        $this->next_results_per_block = $this->results_per_block;
        $count = count($this->queue_servers);
        $this->hard_query = false;
        for ($i = 0; $i < $count; $i++) {
            $this->more_flags[$i] = true;
        }
     }
    /**
     * Forwards the iterator one group of docs
     * @param array $gen_doc_offset a generation, doc_offset pair. If set,
     *     the must be of greater than or equal generation, and if equal the
     *     next block must all have $doc_offsets larger than or equal to
     *     this value
     */
    public function advance($gen_doc_offset = null)
     {
        $this->current_block_fresh = false;
        $this->limit += $this->results_per_block;
        $this->results_per_block = $this->next_results_per_block;
     }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator. As this is not easily determined
     * for a network iterator, this method always returns -1 for this
     * iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    public function currentGenDocOffsetWithWord()
    {
        return -1;
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
     public function findDocsWithWord()
     {
        $query = $this->base_query .
            "&num={$this->results_per_block}&limit={$this->limit}";
        $sites = [];
        $lookup = [];
        $i = 0;
        $j = 0;
        foreach ($this->queue_servers as $server) {
            if ($this->more_flags[$i]) {
                $sites[$j][CrawlConstants::URL] = $server ."?". $query.
                    "&machine=$i";
                $lookup[$j] = $i;
                $j++;
            }
            $i++;
        }
        $net_times = AnalyticsManager::get("NET_TIMES");
        $net_times = ($net_times) ? $net_times : 0;
        $download_time = microtime(true);
        $downloads = [];
        if (count($sites) > 0) {
            $downloads = FetchUrl::getPages($sites, false, 0, null, self::URL,
                self::PAGE, true);
        }
        $net_times += L\changeInMicrotime($download_time);
        AnalyticsManager::set("NET_TIMES", $net_times);
        $results = [];
        $count = count($downloads);
        $this->num_docs = 0;
        $in4 = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
        $machine_times = AnalyticsManager::get("MACHINE_TIMES");
        $indent = ($machine_times) ? "<br />$in4" : $in4;
        $machine_times = ($machine_times) ? $machine_times: "";
        $max_machine_times = AnalyticsManager::get("MAX_MACHINE_TIMES");
        $max_machine_times = ($max_machine_times) ? $max_machine_times : 0;
        $max_time = 0;
        $num_with_results = $count;
        for ($j = 0; $j < $count; $j++) {
            $download = $downloads[$j];
            if (isset($download[self::PAGE]) && $download[self::PAGE]) {
                restore_error_handler();
                $pre_result = @unserialize($download[self::PAGE]);
                set_error_handler(C\NS_LIB . "yioop_error_handler");
                if (!isset($pre_result["TOTAL_ROWS"]) ||
                    $pre_result["TOTAL_ROWS"] < $this->results_per_block) {
                    $this->more_flags[$lookup[$j]] = false;
                    $num_with_results--;
                }
                if (isset($pre_result["TOTAL_ROWS"])) {
                    $this->num_docs += $pre_result["TOTAL_ROWS"];
                }
                if (isset($pre_result["PAGES"])) {
                    foreach ($pre_result["PAGES"] as $page_data) {
                        if (isset($page_data[self::KEY])) {
                            $results[$page_data[self::KEY]] =
                                $page_data;
                            $results[$page_data[self::KEY]][self::MACHINE_ID] =
                                $lookup[$j];
                        }
                    }
                }
                $max_time = max($max_time, $pre_result['TOTAL_TIME']);
                $lookup_link = $this->makeLookupLink($sites, $lookup[$j]);
                $machine_times .= $in4 . $lookup_link . " ".
                    number_format($pre_result['ELAPSED_TIME'], 6) . "/" .
                    number_format($pre_result['TOTAL_TIME'], 6) . "<br />";
            }
        }
        $machine_times = substr( $machine_times, 0, -strlen("<br />"));
        if (isset($pre_result["HARD_QUERY"])) {
            $this->hard_query  = $pre_result["HARD_QUERY"];
        }
        if ($num_with_results > 0) {
            $this->next_results_per_block = ceil(
                floatval($count * $this->results_per_block)/
                floatval($num_with_results));
        }
        $max_machine_times += $max_time;
        AnalyticsManager::set("MACHINE_TIMES", $machine_times);
        AnalyticsManager::set("MAX_MACHINE_TIMES", $max_machine_times);
        if ($results == []) {
            $results = -1;
        }
        if ($results != -1) {
            if ($this->filter != null) {
                foreach ($results as $keys => $data) {
                    $host_key =
                        substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
                    if (in_array($host_key, $this->filter) ) {
                        unset($results[$keys]);
                    }
                }
            }
        }
        $this->count_block = count($results);
        $this->pages = $results;
        return $results;
     }
    /**
     * Called to make a link for AnalyticsManager about a network query
     * performed by this iterator.
     *
     * @param array $sites used by this network iterator
     * @param int $index which site in array to make link for
     * @return string html of link
     */
    public function makeLookupLink($sites, $index)
    {
        if (isset($sites[$index][self::URL])) {
            $url = $sites[$index][self::URL];
            $title = $url;
        } else {
            if (!isset($sites[$index])) {
                $sites[$index] = [];
            }
            $tmp = urlencode(print_r($sites[$index],
                true));
            $title = 'URL not set';
            if (trim($tmp) == "") {
                $tmp = 'Site null';
            }
            $url = 'javascript:alert("'.$tmp.'")';
        }
        $link = "<a target='_blank' class='gray-link' href='$url'".
             " title='$title' >ID_$index</a>:";
        return $link;
    }
    /**
     * Gets the summaries associated with the keys provided the keys
     * can be found in the current block of docs returned by this iterator
     * @param array $keys keys to try to find in the current block of returned
     *     results
     * @return array doc summaries that match provided keys
     */
    public function getCurrentDocsForKeys($keys = null)
    {
        if ($this->current_block_fresh == false) {
            $pages = $this->currentDocsWithWord();
            if (!is_array($pages)) {
                return $pages;
            }
        } else {
            $pages = & $this->pages;
        }
        return $pages;
    }
}
 ?>
