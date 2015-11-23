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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\WebArchiveBundle;

/** For crawlTimeoutLog */
require_once __DIR__.'/../Utility.php';
/**
 * Class used to model iterating documents indexed in
 * an WebArchiveBundle. This would typically be for the purpose
 * of re-indexing these documents.
 *
 * @author Chris Pollett
 * @see WebArchiveBundle
 */
class WebArchiveBundleIterator extends ArchiveBundleIterator
{
    /**
     * Number of web archive objects in this web archive bundle
     * @var int
     */
    public $num_partitions;
    /**
     * The current web archive in the bundle that is being iterated over
     * @var int
     */
    public $partition;
    /**
     * The item within the current partition to be returned next
     * @var int
     */
    public $partition_index;
    /**
     * Index of web archive in the web archive bundle that the iterator is
     * currently getting results from
     * @var int
     */
    public $current_partition_num;
    /**
     * Index between 0 and $this->count of where the iterator is at
     * @var int
     */
    public $overall_index;
    /**
     * Number of documents in the web archive bundle being iterated over
     * @var int
     */
    public $count;
    /**
     * The web archive bundle being iterated over
     * @var object
     */
    public $archive;
    /**
     * The fetcher prefix associated with this archive.
     * @var string
     */
    public $fetcher_prefix;
    /**
     * Returns the path to an archive given its timestamp.
     *
     * @param string $timestamp the archive timestamp
     * @return string the path to the archive, based off of the fetcher prefix
     *    used when this iterator was constructed
     */
    public function getArchiveName($timestamp)
    {
        return CRAWL_DIR.'/cache/'.$this->fetcher_prefix.
            self::archive_base_name.$timestamp;
    }
    /**
     * Creates a web archive iterator with the given parameters.
     *
     * @param string $prefix fetcher number this bundle is associated with
     * @param string $iterate_timestamp timestamp of the web archive bundle to
     *     iterate over the pages of
     * @param string $result_timestamp timestamp of the web archive bundle
     *     results are being stored in
     */
    public function __construct($prefix, $iterate_timestamp, $result_timestamp)
    {
        $this->fetcher_prefix = $prefix;
        $this->iterate_timestamp = $iterate_timestamp;
        $this->result_timestamp = $result_timestamp;
        $archive_name = $this->getArchiveName($iterate_timestamp);
        $this->archive = new WebArchiveBundle($archive_name);
        $archive_name = $this->getArchiveName($result_timestamp);
        if (file_exists("$archive_name/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }
    /**
     * Saves the current state so that a new instantiation can pick up just
     * after the last batch of pages extracted.
     *
     * @param array $info data needed to restore where we are in the process
     *      of iterating through archive.
     */
    public function saveCheckpoint($info = [])
    {
        $info['overall_index'] = $this->overall_index;
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['partition_index'] = $this->partition_index;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['iterator_pos'] = $this->partition->iterator_pos;
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
        $info = unserialize(file_get_contents(
            "$archive_name/iterate_status.txt"));
        $this->count = $this->archive->count;
        $this->num_partitions = $this->archive->write_partition+1;
        $this->overall_index = $info['overall_index'];
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->partition_index = $info['partition_index'];
        $this->current_partition_num = $info['current_partition_num'];
        $this->partition =  $this->archive->getPartition(
                $this->current_partition_num, false);
        $this->partition->iterator_pos = $info['iterator_pos'];
        return $info;
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
    public function nextPages($num, $no_process= false)
    {
        if ($num + $this->overall_index >= $this->count) {
            $num = max($this->count - $this->overall_index, 0);
        }
        $num_to_get = 1;
        $objects = [];
        for ($i = 0; $i < $num; $i += $num_to_get) {
            L\crawlTimeoutLog("..Still getting pages from archive iterator. ".
                "At %s of %s", $i, $num);
            $num_to_get = min($num, $this->partition->count -
                $this->partition_index);
            $pre_new_objects = $this->partition->nextObjects($num_to_get);
            foreach ($pre_new_objects as $object) {
                $objects[] = $object[1];
            }
            $this->overall_index += $num_to_get;
            $this->partition_index += $num_to_get;
            if ($num_to_get <= 0) {
                $this->current_partition_num++;
                $this->partition = $this->archive->getPartition(
                    $this->current_partition_num, false);
                $this->partition_index = 0;
            }
            if ($this->current_partition_num > $this->num_partitions) break;
        }
        $this->end_of_iterator = ($this->overall_index >= $this->count ) ?
            true : false;
        $this->saveCheckpoint();
        return $objects;
    }
    /**
     * Resets the iterator to the start of the archive bundle
     */
    public function reset()
    {
        $this->count = $this->archive->count;
        $this->num_partitions = $this->archive->write_partition + 1;
        $this->overall_index = 0;
        $this->end_of_iterator = ($this->overall_index >= $this->count) ?
            true : false;
        $this->partition_index = 0;
        $this->current_partition_num = 0;
        $this->partition = $this->archive->getPartition(
            $this->current_partition_num, false);
        $this->partition->reset();
        $archive_name = $this->getArchiveName($this->result_timestamp);
        if (file_exists("$archive_name/iterate_status.txt")) {
            unlink("$archive_name/iterate_status.txt");
        }
    }
}
