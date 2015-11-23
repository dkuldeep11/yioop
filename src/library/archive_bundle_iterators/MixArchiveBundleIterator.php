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

use seekquarry\yioop\configs as C;
use seekquarry\yioop\controllers\SearchController;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FileCache;

/** For getLocaleTag and Yioop constants*/
require_once __DIR__."/../LocaleFunctions.php";
/**
 * Used to do an archive crawl based on the results of a crawl mix.
 * the query terms for this crawl mix will have site:any raw 1 appended to them
 *
 * @author Chris Pollett
 */
class MixArchiveBundleIterator extends ArchiveBundleIterator
{
    /**
     * Used to hold timestamp of the crawl mix being used to iterate over
     *
     * @var int
     */
    public $mix_timestamp;
    /**
     * Used to hold timestamp of the index archive bundle of output results
     *
     * @var int
     */
    public $result_timestamp;
    /**
     * count of how far out into the crawl mix we've gone.
     *
     * @var int
     */
    public $limit;
    /**
     * Creates a web archive iterator with the given parameters.
     *
     * @param string $mix_timestamp timestamp of the crawl mix to
     *     iterate over the pages of
     * @param string $result_timestamp timestamp of the web archive bundle
     *     results are being stored in
     */
    public function __construct($mix_timestamp, $result_timestamp)
    {
        L\setLocaleObject(L\getLocaleTag());
        $this->mix_timestamp = $mix_timestamp;
        $this->result_timestamp = $result_timestamp;
        $this->query = "site:any m:".$mix_timestamp;
        $this->searchController = new SearchController();
        $archive_name = $this->getArchiveName($result_timestamp);
        if (!file_exists($archive_name)) {
            mkdir($archive_name);
        }
        if (file_exists("$archive_name/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }
    /**
     * Get the filename of the file that says information about the
     * current archive iterator (such as whether the end of the iterator
     * has been reached)
     *
     * @param int $timestamp of current archive crawl
     */
    public function getArchiveName($timestamp)
    {
        return C\CRAWL_DIR."/schedules/".
            self::name_archive_iterator.$timestamp;
    }
    /**
     * Saves the current state so that a new instantiation can pick up just
     * after the last batch of pages extracted.
     *
     * @param array $info data needed to restore where we are in the process
     *      of iterating through archive. By default save fields LIMIT and
     *      END_OF_ITERATOR
     */
    public function saveCheckpoint($info = [])
    {
        if ($info == []) {
            $info["LIMIT"] = $this->limit;
            $info["END_OF_ITERATOR"] = $this->end_of_iterator;
        }
        $archive_name = $this->getArchiveName($this->result_timestamp);
        file_put_contents("$archive_name/iterate_status.txt",
            serialize($info));
    }
    /**
     * Restores state from a previous instantiation, after the last batch of
     * pages extracted.
     */
    public function restoreCheckpoint()
    {
        $archive_name = $this->getArchiveName($this->result_timestamp);
        $info = unserialize(
            file_get_contents("$archive_name/iterate_status.txt"));
        if (isset($info["LIMIT"])) {
            $this->limit = $info["LIMIT"];
        }
        if (isset($info["END_OF_ITERATOR"])) {
            $this->end_of_iterator = $info["END_OF_ITERATOR"];
        } else {
            $this->end_of_iterator = false;
        }
    }
    /**
     * Estimates the importance of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume files were crawled roughly according to
     *     page importance so we use default estimate of doc rank
     */
    public function weight(&$site)
    {
        return false;
    }
    /**
     * Gets the next $num many docs from the iterator
     *
     * @param int $num number of docs to get
     * @param bool $no_process this flag is inherited from base class but
     *     does not do anything in this case
     * @return array associative arrays for $num pages
     */
    public function nextPages($num, $no_process = false)
    {
        $objects = ["NO_PROCESS" => false];
        if ($this->end_of_iterator) {
            return $objects;
        }
        $results = $this->searchController->queryRequest($this->query,
            $num, $this->limit, 1, $this->result_timestamp);
        $num_results = count($results["PAGES"]);
        if (isset($results["PAGES"]) && $num_results > 0 ) {
            $objects = $results["PAGES"];
            $this->limit += $num_results;
            $objects["NO_PROCESS"] = true;
        } else if ($num_results == 0) {
            $this->end_of_iterator = true;
        } else {
            $objects['NO_PROCESS'] = $results;
        }
        if (isset($results["SAVE_POINT"]) ){
            $end = true;
            foreach ($results["SAVE_POINT"] as $save_point)  {
                if ($save_point != -1) {
                    $end = false;
                }
            }
            $this->save_points = $results["SAVE_POINT"];
            if ($end) {
                $this->end_of_iterator = true;
            }
        }
        $this->saveCheckpoint();
        return $objects;
    }
    /**
     * Resets the iterator to the start of the archive bundle
     */
    public function reset()
    {
        $this->limit = 0;
        $this->end_of_iterator = false;
        $this->searchController->clearQuerySavepoint($this->result_timestamp);
        $this->saveCheckpoint();
    }
}
