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
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\AnalyticsManager;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexManager;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\index_bundle_iterators\WordIterator;
use seekquarry\yioop\library\UrlParser;

/** For getLocaleTag */
require_once __DIR__.'/../library/LocaleFunctions.php';
/**
 * Base class of models that need access to data from multiple queue servers
 * Subclasses include @see CrawlModel and @see PhraseModel.
 *
 * @author Chris Pollett
 */
class ParallelModel extends Model
{
    /**
     * Stores the name of the current index archive to use to get search
     * results from
     * @var string
     */
    public $index_name;
    /**
     * If known the id of the queue_server this belongs to
     * @var int
     */
    public $current_machine;
    /**
     * the minimum length of a description before we stop appending
     * additional link doc summaries
     */
    const MIN_DESCRIPTION_LENGTH = 100;
    /**
     * {@inheritDoc}
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    public function __construct($db_name = C\DB_NAME, $connect = true)
    {
        parent::__construct($db_name, $connect);
        $this->current_machine = 0;//if known, controller will set later
    }
    /**
     * Get a summary of a document by the generation it is in
     * and its offset into the corresponding WebArchive.
     *
     * @param string $url of summary we are trying to look-up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param string $index_name timestamp of the index to do the lookup in
     * @return array summary data of the matching document
     */
    public function getCrawlItem($url, $machine_urls = null, $index_name = "")
    {
        $hash_url = L\crawlHash($url, true);
        if ($index_name == "") {
            $index_name = $this->index_name;
        }
        $results = $this->getCrawlItems(
            [$hash_url => [$url, $index_name]], $machine_urls);
        if (isset($results[$hash_url])) {
            return $results[$hash_url];
        }
        return $results;
    }
    /**
     * Gets summaries for a set of document by their url, or by group of
     * 5-tuples of the form (machine, key, index, generation, offset).
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    public function getCrawlItems($lookups, $machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $summaries = $this->networkGetCrawlItems($lookups, $machine_urls);
        } else {
            $summaries = $this->nonNetworkGetCrawlItems($lookups);
        }
        return $summaries;
    }
    /**
     * In a multiple queue server setting, gets summaries for a set of document
     * by their url, or by group of 5-tuples of the form
     * (machine, key, index, generation, offset). This makes an execMachines
     * call to make a network request to the CrawlController's on each machine
     * which in turn calls getCrawlItems (and thence nonNetworkGetCrawlItems)
     * on each machine. The results are then sent back to networkGetCrawlItems
     * and aggregated.
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array of summary data for the matching documents
     */
    public function networkGetCrawlItems($lookups, $machine_urls)
    {
        //Set-up network request
        $machines = [];
        $indexes = [];
        $num_machines = count($machine_urls);
        foreach ($lookups as $lookup => $lookup_info) {
            if (count($lookup_info) == 2 && ($lookup_info[0][0] === 'h'
                || $lookup_info[0][0] === 'r'
                || $lookup_info[0][0] === 'g')) {
                $machines = $machine_urls;
                break;
            } else {
                foreach ($lookup_info as $lookup_item) {
                    $out_lookup_info = [];
                    if (count($lookup_item) == 5) {
                        list($index, , , , ) = $lookup_item;
                        $machines[$index] = $machine_urls[$index];
                    } else {
                        $machines = $machine_urls;
                        break;
                    }
                }
            }
        }
        //Make request
        $page_set = $this->execMachines("getCrawlItems",
            $machines, serialize($lookups), $num_machines);
        //Aggregate results
        $summaries = [];
        $elapsed_times = [];
        if (is_array($page_set)) {
            foreach ($page_set as $elt) {
                $description_hash = [];
                restore_error_handler();
                $result = @unserialize(L\webdecode($elt[self::PAGE]));
                set_error_handler(C\NS_LIB . "yioop_error_handler");
                if (!is_array($result)) {
                    $elapsed_times[] = 0;
                    continue;
                }
                $elapsed_times[] = $result["ELAPSED_TIME"];
                unset($result["ELAPSED_TIME"]);
                $ellipsis = "";
                foreach ($result as $lookup => $summary) {
                    if (isset($summaries[$lookup])) {
                        if (isset($summary[self::DESCRIPTION])) {
                            $description = trim($summary[self::DESCRIPTION]);
                            if (!isset($summaries[$lookup][self::DESCRIPTION])) {
                                $summaries[$lookup][self::DESCRIPTION] = "";
                            }
                            if (!isset($description_hash[$description])) {
                                $summaries[$lookup][self::DESCRIPTION] =
                                    $ellipsis . $description;
                                $ellipsis = " .. ";
                                $description_hash[$description] = true;
                            }
                        }
                        foreach ($summary as $attr => $value) {
                            if ($attr !=self::DESCRIPTION &&
                                !isset($summaries[$lookup][$attr])) {
                                $summaries[$lookup][$attr] = $value;
                            }
                        }
                    } else {
                        $summaries[$lookup] =  $summary;
                    }
                }
            }
            $summary_times_string = AnalyticsManager::get("SUMMARY_TIMES");
            if ($summary_times_string) {
                $all_elapsed_times = unserialize($summary_times_string);
            } else {
                $all_elapsed_times = [];
            }
            $all_elapsed_times[] = $elapsed_times;
            AnalyticsManager::set("SUMMARY_TIMES", serialize(
                $all_elapsed_times));
        }
        return $summaries;
    }
    /**
     * Gets summaries on a particular machine for a set of document by
     * their url, or by group of 5-tuples of the form
     * (machine, key, index, generation, offset)
     * This may be used in either the single queue_server setting or
     * it may be called indirectly by a particular machine's
     * CrawlController as part of fufilling a network-based getCrawlItems
     * request. $lookups contains items which are to be grouped (as came
     * from same url or site with the same cache). So this function aggregates
     * their descriptions.
     *
     * @param string $lookups things whose summaries we are trying to look up
     * @return array of summary data for the matching documents
     */
    public function nonNetworkGetCrawlItems($lookups)
    {
        $summary_offset = null;
        $generation = null;
        $summaries = [];
        $db = $this->db;
        foreach ($lookups as $lookup => $lookup_info) {
            $scheme = (isset($lookup_info[0]) && is_string($lookup_info[0])) ?
                substr($lookup_info[0], 0, 3) : "";
            if (count($lookup_info) == 2 && ($scheme == 'htt' ||$scheme == 'gop'
                || $scheme == 'rec')) {
                list($url, $index_name) = $lookup_info;
                $index_archive = IndexManager::getIndex($index_name);
                $offset_gen_arr =
                    $this->lookupSummaryOffsetGeneration($url, $index_name);
                if ($offset_gen_arr !== false) {
                    list($summary_offset, $generation) = $offset_gen_arr;
                } else {
                    return false;
                }
                $summary =
                    $index_archive->getPage($summary_offset, $generation);
            } else {
                $summary = [];
                $ellipsis = "";
                $description_hash = [];
                $sql = "SELECT * FROM FEED_ITEM WHERE GUID=?";
                foreach ($lookup_info as $lookup_item) {
                    if (count($lookup_item) == 2) {
                        list($word_key, $index_name) = $lookup_item;
                        $offset_info =
                            $this->lookupSummaryOffsetGeneration(
                                $word_key, $index_name, true);
                        if (is_array($offset_info)) {
                            list($summary_offset, $generation) = $offset_info;
                        } else {
                            continue;
                        }
                    } else {
                        list($machine, $key, $index_name, $generation,
                            $summary_offset) = $lookup_item;
                    }
                    if (strcmp($index_name, "feed") != 0) {
                        $index = IndexManager::getIndex($index_name);
                        if (is_integer($summary_offset) &&
                            is_integer($generation)) {
                            $page = $index->getPage($summary_offset,
                                $generation);
                        } else {
                            $page = null;
                        }
                    } else {
                        $guid = L\base64Hash(substr($key,
                            IndexShard::DOC_KEY_LEN,
                            IndexShard::DOC_KEY_LEN));
                        $result = $db->execute($sql, [$guid]);
                        $page = false;
                        if ($result) {
                            $row = $db->fetchArray($result);
                            if ($row) {
                                $page[self::TITLE] = $row["TITLE"];
                                $page[self::DESCRIPTION] = $row["DESCRIPTION"];
                                $page[self::URL] = $row["LINK"];
                                $page[self::SOURCE_NAME] = $row["SOURCE_NAME"];
                                $page[self::IMAGE_LINK] = $row["IMAGE_LINK"];
                            }
                        }
                    }
                    if (!$page || $page == []) {
                        continue;
                    }
                    $copy = false;
                    if ($summary == []) {
                        if (isset($page[self::DESCRIPTION])) {
                            $description = trim($page[self::DESCRIPTION]);
                            $page[self::DESCRIPTION] = $description;
                            $description_hash[$description] = true;
                        }
                        $ellipsis = " .. ";
                        $summary = $page;
                    } elseif (isset($page[self::DESCRIPTION])) {
                        $description = trim($page[self::DESCRIPTION]);
                        if (!isset($summary[self::DESCRIPTION])) {
                            $summary[
                                self::DESCRIPTION] = "";
                        }
                        if (!isset($description_hash[$description])) {
                            $summary[self::DESCRIPTION] .=
                                $ellipsis . $description;
                            $ellipsis = " .. ";
                            $description_hash[$description] = true;
                        }
                        $copy = true;
                    } else {
                        $copy = true;
                    }
                    if (strlen($summary[self::DESCRIPTION]) >
                        self::MIN_DESCRIPTION_LENGTH) {
                        break;
                    }
                    if ($copy) {
                        foreach ($page as $attr => $value) {
                            if ($attr !=self::DESCRIPTION &&
                                !isset($summary[$attr])) {
                                $summary[$attr] = $value;
                            }
                        }
                    }
                }
            }
            if ($summary != []) {
                $summaries[$lookup] = $summary;
            }
        }
        return $summaries;
    }
    /**
     * Determines the offset into the summaries WebArchiveBundle and generation
     * of the provided url (or hash_url) so that the info:url
     * (info:base64_hash_url) summary can be retrieved. This assumes of course
     * that the info:url  meta word has been stored.
     *
     * @param string $url_or_key either info:base64_hash_url or just a url to
     *     lookup
     * @param string $index_name index into which to do the lookup
     * @param bool $is_key whether the string is info:base64_hash_url or just a
     *     url
     * @return array (offset, generation) into the web archive bundle
     */
    public function lookupSummaryOffsetGeneration($url_or_key, $index_name = "",
        $is_key = false)
    {
        if ($index_name == "") {
            $index_name = $this->index_name;
        }
        $index_archive = IndexManager::getIndex($index_name);
        if (!$index_archive) {
            return false;
        }
        $num_retrieved = 0;
        $summary_offset = null;
        if (!isset($index_archive->generation_info['ACTIVE'])) {
            return false;
        }
        $mask = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $num_generations = $index_archive->generation_info['ACTIVE'];
        $hash_key = ($is_key) ? L\crawlHashWord($url_or_key, true, $mask) :
            L\crawlHashWord("info:$url_or_key", true, $mask);
        $info = IndexManager::getWordInfo($index_name, $hash_key, 0, $mask, 1);
        if (!isset($info[0][4])) {
            return false;
        }
        $word_iterator = new WordIterator($info[0][4], $index_name, true);
        if (is_array($next_docs = $word_iterator->nextDocsWithWord())) {
            $doc_info = current($next_docs);
            if (!$doc_info) {
                return false;
            }
            $summary_offset =
                $doc_info[CrawlConstants::SUMMARY_OFFSET];
            $generation = $doc_info[CrawlConstants::GENERATION];
        } else {
            return false;
        }
        return [$summary_offset, $generation];
    }
    /**
     * A save point is used to store to disk a sequence generation-doc-offset
     * pairs of a particular mix query when doing an archive crawl of a crawl
     * mix. This is used so that the mix can remember where it was the next
     * time it is invoked by the web app on the machine in question.
     * This function deletes such a save point associated with a timestamp
     *
     * @param int $save_timestamp timestamp of save point to delete
     * @param array $machine_urls  machines on which to try to delete savepoint
     */
    public function clearQuerySavePoint($save_timestamp, $machine_urls = null)
    {
        /*
           It's important to quit early in the case that the timestamp is
           empty, as this could result in deleting all SavePoint* files below.
        */
        if (!$save_timestamp) {
            return;
        }

        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $this->execMachines("clearQuerySavePoint", $machine_urls,
                $save_timestamp);
            return;
        }

        /*
           SavePoint files have a $qpart tagged on to the timestamp to
           distinguish between parts of a query, so we want to delete anything
           that starts with the appropriate timestamp.
        */
        $save_stub = C\CRAWL_DIR.'/schedules/'.self::save_point.$save_timestamp;
        foreach (glob($save_stub.'*.txt') as $save_file) {
            @unlink($save_file);
        }
    }
    /**
     * This method is invoked by other ParallelModel (@see CrawlModel
     * for examples) methods when they want to have their method performed
     * on an array of other  Yioop instances. The results returned can then
     * be aggregated.  The invocation sequence is
     * crawlModelMethodA invokes execMachine with a list of
     * urls of other Yioop instances. execMachine makes REST requests of
     * those instances of the given command and optional arguments
     * This request would be handled by a CrawlController which in turn
     * calls crawlModelMethodA on the given Yioop instance, serializes the
     * result and gives it back to execMachine and then back to the originally
     * calling function.
     *
     * @param string $command the ParallelModel method to invoke on the remote
     *     Yioop instances
     * @param array $machine_urls machines to invoke this command on
     * @param string $arg additional arguments to be passed to the remote
     *      machine
     * @param int $num_machines the integer to be used in calculating partition
     * @return array a list of outputs from each machine that was called.
     */
    public function execMachines($command, $machine_urls, $arg = null,
        $num_machines = 0)
    {
        if ($num_machines == 0) {
            $num_machines = count($machine_urls);
        }
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $query = "c=crawl&a=$command&time=$time&session=$session" .
            "&num=$num_machines";
        if ($arg != null) {
            $arg = L\webencode($arg);
            $query .= "&arg=$arg";
        }
        $sites = [];
        $post_data = [];
        $i = 0;
        foreach ($machine_urls as $index => $machine_url) {
            $sites[$i][CrawlConstants::URL] = $machine_url;
            $post_data[$i] = $query."&i=$index";
            $i++;
        }
        $outputs = [];
        if (count($sites) > 0) {
            $outputs = FetchUrl::getPages($sites, false, 0, null, self::URL,
                self::PAGE, true, $post_data);
        }
        return $outputs;
    }
}
