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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;

/** For wedbencode and Yioop constants*/
require_once __DIR__."/../Utility.php";
/**
 * Used to iterate through the records that result from an SQL query to a
 * database
 *
 * @author Chris Pollett
 * @see WebArchiveBundle
 */
class DatabaseBundleIterator extends ArchiveBundleIterator
{
    /**
     * The path to the directory containing the archive partitions to be
     * iterated over.
     * @var string
     */
    public $iterate_dir;
    /**
     * SQL query whose records we are index
     * @var string
     */
    public $sql;
    /**
     * DB Records are imported as a text string where column_separator
     * is used to delimit the end of a column
     * @var string
     */
    public $column_separator;
    /**
     * For a given DB record each column is converted to a string:
     * name_of_column field_value_separator value_of_column
     * @var string
     */
    public $field_value_separator;
    /**
     * What character encoding is used for the DB records
     * @var string
     */
    public $encoding;

    /**
     * File handle for current arc file
     * @var resource
     */
    public $db;
    /**
     * Current result row of query iterator has processed to
     * @var int
     */
    public $limit;
    /**
     * Creates an database archive iterator with the given parameters. This
     * kind of iterator is used to cycle through the results of a SQL query
     * to a database, so that the results might be indexed by Yioop.
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
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        $ini = L\parse_ini_with_fallback(
            "{$this->iterate_dir}/arc_description.ini");
        $this->dbinfo = ["DBMS" => C\DBMS, "DB_HOST" => C\DB_HOST,
            "DB_NAME" => C\DB_NAME, "DB_USER" => C\DB_USER,
            "DB_PASSWORD" => C\DB_PASSWORD];
        foreach ($this->dbinfo as $key => $value) {
            $ini_key = strtolower($key);
            if (isset($ini[$ini_key])) {
                $this->dbinfo[$key] = $ini[$ini_key];
            }
        }
        $db_class = C\NS_DATASOURCES . ucfirst($this->dbinfo["DBMS"])."Manager";
        $this->db = new $db_class();
        $conn = $this->db->connect($this->dbinfo['DB_HOST'],
            $this->dbinfo['DB_USER'], $this->dbinfo['DB_PASSWORD'],
            $this->dbinfo['DB_NAME']);
        if (isset($ini['sql'])) {
            $this->sql = $ini['sql'];
        } else {
            L\crawlLog(
                "Database Archive Iterator needs a SQL statement to run");
            exit();
        }
        if (isset($ini['field_value_separator'])) {
            $this->field_value_separator = $ini['field_value_separator'];
        } else {
           $this->field_value_separator = "\n----\n";
        }
        if (isset($ini['column_separator'])) {
            $this->column_separator = $ini['column_separator'];
        } else {
           $this->column_separator = "\n====\n";
        }
        if (isset($ini['encoding'])) {
            $this->encoding = $ini['encoding'];
        } else {
            $this->encoding = "UTF-8";
        }

        if (!file_exists($result_dir)) {
            mkdir($result_dir);
        }
        if (file_exists("{$this->result_dir}/iterate_status.txt")) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }
    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume arc files were crawled according to
     *     OPIC and so we use the default doc_depth to estimate page importance
     */
    public function weight(&$site)
    {
        return false;
    }
    /**
     * Resets the iterator to the start of the archive bundle
     */
    public function reset()
    {
        $this->limit = 0;
        $this->end_of_iterator = false;

        if (file_exists("{$this->result_dir}/iterate_status.txt")) {
            unlink("{$this->result_dir}/iterate_status.txt");
        }
    }
    /**
     * Gets the next at most $num many docs from the iterator. It might return
     * less than $num many documents if the partition changes or the end of the
     * bundle is reached.
     *
     * @param int $num number of docs to get
     * @param bool $no_process do not do any processing on page data
     * @return array associative arrays for $num pages
     */
    public function nextPages($num, $no_process = false)
    {
        $pages = [];
        $page_count = 0;
        $db = $this->db;
        $query = "{$this->sql} " . $db->limitOffset($this->limit, $num,
            $this->dbinfo);
        $result = $db->execute($query);
        $i = 0;
        while($row = $db->fetchArray($result)) {
            L\crawlTimeoutLog("..Still getting pages from archive iterator. ".
                "At %s of %s", $i, $num);
            $page = "";
            foreach ($row as $key => $value) {
                $page .= "$key{$this->field_value_separator}".
                    "$value{$this->column_separator}";
            }
            if ($no_process) {
                $pages[] = $page;
            } else {
                $site = [];
                $site[self::HEADER] = "DatabaseBundleIterator extractor";
                $site[self::IP_ADDRESSES] = ["0.0.0.0"];
                $site[self::TIMESTAMP] = date("U", time());
                $site[self::TYPE] = "text/plain";
                $site[self::PAGE] = $page;
                $site[self::HASH] = FetchUrl::computePageHash($page);
                $site[self::URL] = "record:".L\webencode($site[self::HASH]);
                $site[self::HTTP_CODE] = 200;
                $site[self::ENCODING] = $this->encoding;
                $site[self::SERVER] = "unknown";
                $site[self::SERVER_VERSION] = "unknown";
                $site[self::OPERATING_SYSTEM] = "unknown";
                $site[self::WEIGHT] = 1;
                $pages[] = $site;
            }
            $page_count++;
        }
        $this->limit += $page_count;
        if ($page_count < $num) {
            $this->end_of_iterator = true;
        }
        $this->saveCheckpoint();
        return $pages;
    }
    /**
     * Advances the iterator to the $limit page, with as little
     * additional processing as possible
     *
     * @param $limit page to advance to
     */
    public function seekPage($limit)
    {
        $this->reset();
        $this->limit = $limit;
    }
    /**
     * Used to save the result row we are at so that the iterator can start
     * from that row the next time it is invoked.
     * @param array $info any extra info a subclass wants to save
     */
    public function saveCheckPoint($info = [])
    {
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['limit'] = $this->limit;
        file_put_contents("{$this->result_dir}/iterate_status.txt",
            serialize($info));
    }
    /**
     * Restores  the internal state from the file iterate_status.txt in the
     * result dir such that the next call to nextPages will pick up from just
     * after the last checkpoint.
     *
     * @return array the data serialized when saveCheckpoint was called
     */
    public function restoreCheckPoint()
    {
        $info = unserialize(file_get_contents(
            "{$this->result_dir}/iterate_status.txt"));
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->limit = $info['limit'];
        return $info;
    }
}
