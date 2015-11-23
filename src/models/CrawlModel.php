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

use seekquarry\yioop\controllers\SearchController;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\IndexArchiveBundle;
use seekquarry\yioop\library\IndexManager;
use seekquarry\yioop\library\UrlParser;

/** used to prevent cache page requests from being logged*/
if (!C\nsdefined("POST_PROCESSING") && !C\nsdefined("NO_LOGGING")) {
    C\nsdefine("NO_LOGGING", true);
}
/**
 * This is class is used to handle getting/setting crawl parameters, CRUD
 * operations on current crawls, start, stop, status of crawls,
 * getting cache files out of crawls, determining
 * what is the default index to be used, marshalling/unmarshalling crawl mixes,
 * and handling data from suggest-a-url forms
 *
 * @author Chris Pollett
 */
class CrawlModel extends ParallelModel
{
    /**
     * Used to map between search crawl mix form variables and database columns
     * @var array
     */
    public $search_table_column_map = ["name"=>"NAME", "owner_id"=>"OWNER_ID"];
    /**
     * File to be used to store suggest-a-url form data
     * @var string
     */
    public $suggest_url_file;
    /**
     * {@inheritDoc}
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    public function __construct($db_name = C\DB_NAME, $connect = true)
    {
        $this->suggest_url_file = C\WORK_DIRECTORY."/data/suggest_url.txt";
        parent::__construct($db_name, $connect);
    }
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args = null)
    {
        return "CRAWL_MIXES";
    }
    /**
     * {@inheritDoc}
     *
     * @param array $row row as retrieved from database query
     * @param mixed $args additional arguments that might be used by this
     *      callback. In this case, should be a boolean flag that says whether
     *      or not to add information about the components of the crawl mix
     * @return array $row after callback manipulation
     */
    public function rowCallback($row, $args)
    {
        if ($args) {
            $mix = $this->getCrawlMix($row['TIMESTAMP'], true);
            $row['FRAGMENTS'] = $mix['FRAGMENTS'];
        }
        return $row;
    }

    /**
     * Gets the cached version of a web page from the machine on which it was
     * fetched.
     *
     * Complete cached versions of web pages typically only live on a fetcher
     * machine. The queue server machine typically only maintains summaries.
     * This method makes a REST request of a fetcher machine for a cached page
     * and get the results back.
     *
     * @param string $machine the ip address of domain name of the machine the
     *     cached page lives on
     * @param string $machine_uri the path from document root on $machine where
     *     the yioop scripts live
     * @param int $partition the partition in the WebArchiveBundle the page is
     *      in
     * @param int $offset the offset in bytes into the WebArchive partition in
     *     the WebArchiveBundle at which the cached page lives.
     * @param string $crawl_time the timestamp of the crawl the cache page is
     *     from
     * @param int $instance_num which fetcher instance for the particular
     *     fetcher crawled the page (if more than one), false otherwise
     * @return array page data of the cached page
     */
    public function getCacheFile($machine, $machine_uri, $partition,
        $offset, $crawl_time, $instance_num = false)
    {
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        if ($machine == '::1') { //IPv6 :(
            $machine = "[::1]";
            //used if the fetching and queue serving were on the same machine
        }
        // we assume all machines use the same scheme & port of the name server
        $port = UrlParser::getPort(C\NAME_SERVER);
        $scheme = UrlParser::getScheme(C\NAME_SERVER);
        $request = "$scheme://$machine:$port$machine_uri?c=archive&a=cache&".
            "time=$time&session=$session&partition=$partition&offset=$offset".
            "&crawl_time=$crawl_time";
        if ($instance_num !== false) {
            $request .= "&instance_num=$instance_num";
        }
        $tmp = FetchUrl::getPage($request);
        $page = @unserialize(base64_decode($tmp));
        $page['REQUEST'] = $request;

        return $page;
    }
    /**
     * Gets the name (aka timestamp) of the current index archive to be used to
     * handle search queries
     *
     * @return string the timestamp of the archive
     */
    public function getCurrentIndexDatabaseName()
    {
        $db = $this->db;
        $sql = "SELECT CRAWL_TIME FROM CURRENT_WEB_INDEX";
        $result = $db->execute($sql);

        $row =  $db->fetchArray($result);

        return $row['CRAWL_TIME'];
    }
    /**
     * Sets the IndexArchive that will be used for search results
     *
     * @param $timestamp  the timestamp of the index archive. The timestamp is
     *      when the crawl was started. Currently, the timestamp appears as
     *      substring of the index archives directory name
     */
    public function setCurrentIndexDatabaseName($timestamp)
    {
        $db = $this->db;
        $db->execute("DELETE FROM CURRENT_WEB_INDEX");
        $sql = "INSERT INTO CURRENT_WEB_INDEX VALUES ( ? )";
        $db->execute($sql, [$timestamp]);
    }
    /**
     * Returns all the files in $dir or its subdirectories with modfied times
     * more recent than timestamp. The file which have
     * in their path or name a string in the $excludes array will be exclude
     *
     * @param string $dir a directory to traverse
     * @param int $timestamp used to check modified times against
     * @param array $excludes an array of path substrings tot exclude
     * @return array of file structs consisting of name, modified time and
     *     size.
     */
    public function getDeltaFileInfo($dir, $timestamp, $excludes)
    {
        $dir_path_len = strlen($dir) + 1;
        $files = $this->db->fileInfoRecursive($dir, true);
        $names = [];
        $results = [];
        foreach ($files as $file) {
            $file["name"] = substr($file["name"], $dir_path_len);
            if ($file["modified"] > $timestamp && $file["name"] !="") {
                $flag = true;
                foreach ($excludes as $exclude) {
                    if (stristr($file["name"], $exclude)) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag) {
                    $results[$file["name"]] = $file;
                }
            }
        }
        $results = array_values($results);
        return $results;
    }
    /**
     * Gets a list of all mixes of available crawls
     *
     * @param int $user_id user that we are getting a list of mixes for
     * @param bool $with_components if false then don't load the factors
     *     that make up the crawl mix, just load the name of the mixes
     *     and their timestamps; otherwise, if true loads everything
     * @return array list of available crawls
     */
    public function getMixList($user_id, $with_components = false)
    {
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE OWNER_ID=?";
        $result = $this->db->execute($sql, [$user_id]);
        $rows = [];
        while ($row = $this->db->fetchArray($result)) {
            if ($with_components) {
                $mix = $this->getCrawlMix($row['TIMESTAMP'], true);
                $row['FRAGMENTS'] = $mix['FRAGMENTS'];
            }
            $rows[] = $row;
        }
        return $rows;
    }
    /**
     * Retrieves the weighting component of the requested crawl mix
     *
     * @param string $timestamp of the requested crawl mix
     * @param bool $just_components says whether to find the mix name or
     *     just the components array.
     * @return array the crawls and their weights that make up the
     *     requested crawl mix.
     */
    public function getCrawlMix($timestamp, $just_components = false)
    {
        $db = $this->db;
        if (!$just_components) {
            $sql = "SELECT TIMESTAMP, NAME, OWNER_ID, PARENT FROM CRAWL_MIXES ".
                "WHERE TIMESTAMP = ?";
            $result = $db->execute($sql, [$timestamp]);
            $mix =  $db->fetchArray($result);
        } else {
            $mix = [];
        }
        $sql = "SELECT FRAGMENT_ID, RESULT_BOUND".
            " FROM MIX_FRAGMENTS WHERE ".
            " TIMESTAMP = ?";
        $result = $db->execute($sql, [$timestamp]);
        $mix['FRAGMENTS'] = [];
        while ($row = $db->fetchArray($result)) {
            $mix['FRAGMENTS'][$row['FRAGMENT_ID']]['RESULT_BOUND'] =
                $row['RESULT_BOUND'];
        }
        $sql = "SELECT CRAWL_TIMESTAMP, WEIGHT, KEYWORDS ".
            " FROM MIX_COMPONENTS WHERE ".
            " TIMESTAMP=:timestamp AND FRAGMENT_ID=:fragment_id";
        $params = [":timestamp" => $timestamp];
        foreach ($mix['FRAGMENTS'] as $fragment_id => $data) {
            $params[":fragment_id"] = $fragment_id;
            $result = $db->execute($sql, $params);
            $mix['COMPONENTS'] = [];
            $count = 0;
            if($result) {
                while ($row =  $db->fetchArray($result)) {
                    $mix['FRAGMENTS'][$fragment_id]['COMPONENTS'][$count] =$row;
                    $count++;
                }
            } else {
                break;
            }
        }
        return $mix;
    }
    /**
     * Returns the timestamp associated with a mix name;
     *
     * @param string $mix_name name to lookup
     * @return mixed timestamp associated with name if exists false otherwise
     */
    public function getCrawlMixTimestamp($mix_name)
    {
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " NAME= ?";
        $result = $db->execute($sql, [$mix_name]);
        $mix =  $db->fetchArray($result);
        if (isset($mix["TIMESTAMP"])) {
            return $mix["TIMESTAMP"];
        }
        return false;
    }
    /**
     * Returns whether the supplied timestamp corresponds to a crawl mix
     *
     * @param string $timestamp of the requested crawl mix
     *
     * @return bool true if it does; false otherwise
     */
    public function isCrawlMix($timestamp)
    {
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " TIMESTAMP = ?";
        $result = $db->execute($sql, [$timestamp]);
        if ($result) {
            if ($mix = $db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }
    /**
     * Returns whether there is a mix with the given $timestamp that $user_id
     * owns
     *
     * @param string $timestamp to see if exists
     * @param string $user_id id of would be owner
     *
     * @return bool true if owner; false otherwise
     */
    public function isMixOwner($timestamp, $user_id)
    {
        $db = $this->db;
        $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
            " TIMESTAMP = ? and OWNER_ID = ?";
        $result = $db->execute($sql, [$timestamp, $user_id]);
        if ($result) {
            if ($mix = $db->fetchArray($result)) {
                return true;
            } else {
                return false;
            }
        }
    }
    /**
     * Stores in DB the supplied crawl mix object
     *
     * @param array $mix an associative array representing the crawl mix object
     */
    public function setCrawlMix($mix)
    {
        $db = $this->db;
        //although maybe slower, we first get rid of any old data
        $timestamp = $mix['TIMESTAMP'];
        $this->deleteCrawlMix($timestamp);
        //next we store the new data
        $sql = "INSERT INTO CRAWL_MIXES VALUES (?, ?, ?, ?)";
        $db->execute($sql, [$timestamp, $mix['NAME'], $mix['OWNER_ID'],
            $mix['PARENT']]);
        $fid = 0;
        foreach ($mix['FRAGMENTS'] as $fragment_id => $fragment_data) {
            $sql = "INSERT INTO MIX_FRAGMENTS VALUES (?, ?, ?)";
            $db->execute($sql, [$timestamp, $fid,
                $fragment_data['RESULT_BOUND']]);
            foreach ($fragment_data['COMPONENTS'] as $component) {
                $sql = "INSERT INTO MIX_COMPONENTS VALUES (?, ?, ?, ?, ?)";
                $db->execute($sql, [$timestamp, $fid,
                    $component['CRAWL_TIMESTAMP'], $component['WEIGHT'],
                    $component['KEYWORDS']]);
            }
            $fid++;
        }
    }
    /**
     * Deletes from the DB the crawl mix ans its associated components and
     * fragments
     *
     * @param int $timestamp of the mix to delete
     */
    public function deleteCrawlMix($timestamp)
    {
        $sql = "DELETE FROM CRAWL_MIXES WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
        $sql = "DELETE FROM MIX_FRAGMENTS WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
        $sql = "DELETE FROM MIX_COMPONENTS WHERE TIMESTAMP=?";
        $this->db->execute($sql, [$timestamp]);
    }
    /**
     * Deletes the archive iterator and savepoint files created during the
     * process of iterating through a crawl mix.
     *
     * @param int $timestamp The timestamp of the crawl mix
     */
    public function deleteCrawlMixIteratorState($timestamp)
    {
        L\setLocaleObject(L\getLocaleTag());
        $search_controller = new SearchController();
        $search_controller->clearQuerySavepoint($timestamp);

        $archive_dir = C\WORK_DIRECTORY."/schedules/".
            self::name_archive_iterator.$timestamp;
        if (file_exists($archive_dir)) {
            $this->db->unlinkRecursive($archive_dir);
        }
    }
    /**
     * Returns the initial sites that a new crawl will start with along with
     * crawl parameters such as crawl order, allowed and disallowed crawl sites
     * @param bool $use_default whether or not to use the Yioop! default
     *     crawl.ini file rather than the one created by the user.
     * @return array  the first sites to crawl during the next crawl
     *     restrict_by_url, allowed, disallowed_sites
     */
    public function getSeedInfo($use_default = false)
    {
        if (file_exists(C\WORK_DIRECTORY."/crawl.ini") && !$use_default) {
            $info = L\parse_ini_with_fallback(C\WORK_DIRECTORY."/crawl.ini");
        } else {
            $info = L\parse_ini_with_fallback(
                C\BASE_DIR."/configs/default_crawl.ini");
        }
        return $info;
    }
    /**
     * Writes a crawl.ini file with the provided data to the user's
     * WORK_DIRECTORY
     *
     * @param array $info an array containing information about the crawl
     */
    public function setSeedInfo($info)
    {
        if (!isset($info['general']['crawl_index'])) {
            $info['general']['crawl_index']='12345678';
        }
        if (!isset($info["general"]["arc_dir"])) {
            $info["general"]["arc_dir"] = "";
        }
        if (!isset($info["general"]["arc_type"])) {
            $info["general"]["arc_type"] = "";
        }
        if (!isset($info["general"]["cache_pages"])) {
            $info["general"]["cache_pages"] = true;
        }
        if (!isset($info["general"]["summarizer_option"])) {
            $info["general"]["summarizer_option"] = "";
        }
        $n = [];
        $n[] = <<<EOT
; ***** BEGIN LICENSE BLOCK *****
;  SeekQuarry/Yioop Open Source Pure PHP Search Engine, Crawler, and Indexer
;  Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
;
;  This program is free software: you can redistribute it and/or modify
;  it under the terms of the GNU General Public License as published by
;  the Free Software Foundation, either version 3 of the License, or
;  (at your option) any later version.
;
;  This program is distributed in the hope that it will be useful,
;  but WITHOUT ANY WARRANTY; without even the implied warranty of
;  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
;  GNU General Public License for more details.
;
;  You should have received a copy of the GNU General Public License
;  along with this program.  If not, see <http://www.gnu.org/licenses/>.
;  ***** END LICENSE BLOCK *****
;
; crawl.ini
;
; Crawl configuration file
;
EOT;
        if (!isset($info['general']['page_range_request'])) {
            $info['general']['page_range_request'] = C\PAGE_RANGE_REQUEST;
        }
        if (!isset($info['general']['page_recrawl_frequency'])) {
            $info['general']['page_recrawl_frequency'] =
                C\PAGE_RECRAWL_FREQUENCY;
        }
        if (!isset($info['general']['max_description_len'])) {
            $info['general']['max_description_len'] = C\MAX_DESCRIPTION_LEN;
        }
        $n[] = '[general]';
        $n[] = "crawl_order = '".$info['general']['crawl_order']."';";
        $n[] = "summarizer_option = '".
            $info['general']['summarizer_option']."';";
        $n[] = "crawl_type = '".$info['general']['crawl_type']."';";
        $n[] = "crawl_index = '".$info['general']['crawl_index']."';";
        $n[] = 'arc_dir = "'.$info["general"]["arc_dir"].'";';
        $n[] = 'arc_type = "'.$info["general"]["arc_type"].'";';
        $n[] = "page_recrawl_frequency = '".
            $info['general']['page_recrawl_frequency']."';";
        $n[] = "page_range_request = '".
            $info['general']['page_range_request']."';";
        $n[] = "max_description_len = '".
            $info['general']['max_description_len']."';";
        $bool_string =
            ($info['general']['cache_pages']) ? "true" : "false";
        $n[] = "cache_pages = $bool_string;";
        $bool_string =
            ($info['general']['restrict_sites_by_url']) ? "true" : "false";
        $n[] = "restrict_sites_by_url = $bool_string;";
        $n[] = "";

        $n[] = "[indexed_file_types]";
        if (isset($info["indexed_file_types"]['extensions'])) {
            foreach ($info["indexed_file_types"]['extensions'] as $extension) {
                $n[] = "extensions[] = '$extension';";
            }
        }
        $n[] = "";

        $n[] = "[active_classifiers]";
        if (isset($info['active_classifiers']['label'])) {
            foreach ($info['active_classifiers']['label'] as $label) {
                $n[] = "label[] = '$label';";
            }
        }
        $n[] = "";

        $n[] = "[active_rankers]";
        if (isset($info['active_rankers']['label'])) {
            foreach ($info['active_rankers']['label'] as $label) {
                $n[] = "label[] = '$label';";
            }
        }
        $n[] = "";

        $site_types =
            ['allowed_sites' => 'url', 'disallowed_sites' => 'url',
                'seed_sites' => 'url', 'page_rules'=>'rule'];
        foreach ($site_types as $type => $field) {
            $n[] = "[$type]";
            if (isset($info[$type][$field])) {
                foreach ($info[$type][$field] as $field_value) {
                    $n[] = $field . "[] = '$field_value';";
                }
            }
            $n[]="";
        }
        $n[] = "[indexing_plugins]";
        if (isset($info["indexing_plugins"]['plugins'])) {
            foreach ($info["indexing_plugins"]['plugins'] as $plugin) {
                if ($plugin == "") {
                    continue;
                }
                $n[] = "plugins[] = '$plugin';";
            }
        }
        $out = implode("\n", $n);
        $out .= "\n";
        file_put_contents(C\WORK_DIRECTORY."/crawl.ini", $out);
    }
    /**
     * Returns the crawl parameters that were used during a given crawl
     *
     * @param string $timestamp timestamp of the crawl to load the crawl
     *     parameters of
     * @return array  the first sites to crawl during the next crawl
     *     restrict_by_url, allowed, disallowed_sites
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     */
    public function getCrawlSeedInfo($timestamp,  $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            /* seed info should be same amongst all queue servers that have it--
               only start schedule differs -- however, not all queue servers
               necessarily have the same crawls. Thus, we still query all
               machines in case only one has it.
             */
            $a_list = $this->execMachines("getCrawlSeedInfo",
                $machine_urls, serialize($timestamp));
            if (is_array($a_list)) {
                foreach ($a_list as $elt) {
                    $seed_info = unserialize(L\webdecode(
                        $elt[self::PAGE]));
                    if (isset($seed_info['general'])) {
                        break;
                    }
                }
            }
            return $seed_info;
        }
        $dir = C\CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
        $seed_info = null;
        if (file_exists($dir)) {
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            if (!isset($info['DESCRIPTION']) ||
                $info['DESCRIPTION'] == null ||
                strstr($info['DESCRIPTION'], "Archive created")) {
                return $seed_info;
            }
            $index_info = unserialize($info['DESCRIPTION']);
            $general_params = ["restrict_sites_by_url" =>
                [self::RESTRICT_SITES_BY_URL, false],
                "crawl_type" => [self::CRAWL_TYPE, self::WEB_CRAWL],
                "crawl_index" => [self::CRAWL_INDEX, ''],
                "crawl_order" => [self::CRAWL_ORDER, self::PAGE_IMPORTANCE],
                "summarizer_option" => [self::SUMMARIZER_OPTION,
                    self::BASIC_SUMMARIZER],
                "arc_dir" => [self::ARC_DIR, ''],
                "arc_type" => [self::ARC_TYPE, ''],
                "cache_pages" => [self::CACHE_PAGES, true],
                "page_recrawl_frequency" => [self::PAGE_RECRAWL_FREQUENCY, -1],
                "page_range_request" => [self::PAGE_RANGE_REQUEST,
                    C\PAGE_RANGE_REQUEST],
                "max_description_len" => [self::MAX_DESCRIPTION_LEN,
                    C\MAX_DESCRIPTION_LEN],
            ];
            foreach ($general_params as $param => $info) {
                $seed_info['general'][$param] = (isset($index_info[$info[0]])) ?
                    $index_info[$info[0]] : $info[1];
            }

            $site_types = [
                "allowed_sites" => [self::ALLOWED_SITES, "url"],
                "disallowed_sites" => [self::DISALLOWED_SITES, "url"],
                "seed_sites" => [self::TO_CRAWL, "url"],
                "page_rules" => [self::PAGE_RULES, "rule"],
                "indexed_file_types" => [self::INDEXED_FILE_TYPES,
                    "extensions"],
            ];
            foreach ($site_types as $type => $info) {
                if (isset($index_info[$info[0]])) {
                    $tmp = & $index_info[$info[0]];
                } else {
                    $tmp = [];
                }
                $seed_info[$type][$info[1]] =  $tmp;
            }
            if (isset($index_info[self::INDEXING_PLUGINS])) {
                $seed_info['indexing_plugins']['plugins'] =
                    $index_info[self::INDEXING_PLUGINS];
            }
            if (isset($index_info[self::INDEXING_PLUGINS_DATA])) {
                $seed_info['indexing_plugins']['plugins_data'] =
                    $index_info[self::INDEXING_PLUGINS_DATA];
            }
        }
        return $seed_info;
    }
    /**
     * Changes the crawl parameters of an existing crawl (can be while crawling)
     * Not all fields are allowed to be updated
     *
     * @param string $timestamp timestamp of the crawl to change
     * @param array $new_info the new parameters
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function setCrawlSeedInfo($timestamp, $new_info,
        $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $params = [$timestamp, $new_info];
            $this->execMachines("setCrawlSeedInfo",
                $machine_urls, serialize($params));
        }
        $dir = C\CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
        if (file_exists($dir)) {
            $info = IndexArchiveBundle::getArchiveInfo($dir);
            $index_info = unserialize($info['DESCRIPTION']);
            if (isset($new_info['general']["restrict_sites_by_url"])) {
                $index_info[self::RESTRICT_SITES_BY_URL] =
                    $new_info['general']["restrict_sites_by_url"];
            }
            $updatable_site_info = [
                "allowed_sites" => [self::ALLOWED_SITES,'url'],
                "disallowed_sites" => [self::DISALLOWED_SITES, 'url'],
                "seed_sites" => [self::TO_CRAWL, "url"],
                "page_rules" => [self::PAGE_RULES, 'rule'],
                "indexed_file_types" => [self::INDEXED_FILE_TYPES,
                    "extensions"],
                "active_classifiers" => [self::ACTIVE_CLASSIFIERS, 'label'],
                "active_rankers" => [self::ACTIVE_RANKERS, 'label'],
            ];
            foreach ($updatable_site_info as $type => $type_info) {
                if (isset($new_info[$type][$type_info[1]])) {
                    $index_info[$type_info[0]] =
                        $new_info[$type][$type_info[1]];
                }
            }
            if (isset($new_info['indexing_plugins']['plugins'])) {
                $index_info[self::INDEXING_PLUGINS] =
                    $new_info['indexing_plugins']['plugins'];
            }
            $info['DESCRIPTION'] = serialize($index_info);
            IndexArchiveBundle::setArchiveInfo($dir, $info);
        }
    }
    /**
     * Returns an array of urls which were stored via the suggest-a-url
     * form in suggest_view.php
     *
     * @return array urls that have been suggested
     */
    public function getSuggestSites()
    {
        $suggest_file = $this->suggest_url_file;
        if (file_exists($suggest_file)) {
            $urls = file($suggest_file);
        } else {
            $urls = [];
        }
        return $urls;
    }
    /**
     * Add new distinct urls to those already saved in the suggest_url_file
     * If the supplied url is not new or the file size
     * exceeds MAX_SUGGEST_URL_FILE_SIZE then it is not added.
     *
     * @param string $url to add
     * @return string true if the url was added or already existed
     *     in the file; false otherwise
     */
    public function appendSuggestSites($url)
    {
        $suggest_file = $this->suggest_url_file;
        $suggest_size = strlen($url);
        if (file_exists($suggest_file)) {
            $suggest_size += filesize($suggest_file);
        } else {
            $this->clearSuggestSites();
        }
        if ($suggest_size < C\MAX_SUGGEST_URL_FILE_SIZE) {
            $urls = file($suggest_file);
            $urls[] = $url;
            $urls = array_unique($urls);
            $out_string = "";
            $delim = "";
            foreach ($urls as $url) {
                $trim_url = trim($url);
                if (strlen($trim_url) > 0) {
                    $out_string .= $delim . $trim_url;
                    $delim = "\n";
                }
            }
            file_put_contents($suggest_file, $out_string, LOCK_EX);
            return true;
        }
        return false;
    }
    /**
     * Resets the suggest_url_file to be the empty file
     */
    public function clearSuggestSites()
    {
        file_put_contents($this->suggest_url_file, "", LOCK_EX);
    }
    /**
     * Get a description associated with a Web Crawl or Crawl Mix
     *
     * @param int $timestamp of crawl or mix in question
     * @param array $machine_urls an array of urls of yioop queue servers
     *
     * @return array associative array containing item DESCRIPTION
     */
    public function getInfoTimestamp($timestamp, $machine_urls = null)
    {
        $is_mix = $this->isCrawlMix($timestamp);
        $info = [];
        if ($is_mix) {
            $sql = "SELECT TIMESTAMP, NAME FROM CRAWL_MIXES WHERE ".
                " TIMESTAMP=?";
            $result = $this->db->execute($sql, [$timestamp]);
            $mix =  $this->db->fetchArray($result);
            $info['TIMESTAMP'] = $timestamp;
            $info['DESCRIPTION'] = $mix['NAME'];
            $info['IS_MIX'] = true;
        } else {
            if ($machine_urls != null &&
                !$this->isSingleLocalhost($machine_urls, $timestamp)) {
                $cache_file = C\CRAWL_DIR."/cache/".self::network_base_name.
                    $timestamp.".txt";
                if (file_exists($cache_file)) {
                    $old_info = unserialize(file_get_contents($cache_file));
                }
                if (isset($old_info) && filemtime($cache_file)
                    + 300 > time()) {
                    return $old_info;
                }
                $info = [];
                if (isset($old_info["MACHINE_URLS"])) {
                    $info["MACHINE_URLS"] = $old_info["MACHINE_URLS"];
                } else {
                    $info["MACHINE_URLS"] = $machine_urls;
                }
                $info_lists = $this->execMachines("getInfoTimestamp",
                    $info["MACHINE_URLS"], serialize($timestamp));
                $info['DESCRIPTION'] = "";
                $info["COUNT"] = 0;
                $info['VISITED_URLS_COUNT'] = 0;
                restore_error_handler();
                foreach ($info_lists as $info_list) {
                    $a_info = @unserialize(L\webdecode(
                        $info_list[self::PAGE]));
                    if (isset($a_info['DESCRIPTION'])) {
                        $info['DESCRIPTION'] = $a_info['DESCRIPTION'];
                    }
                    if (isset($a_info['VISITED_URLS_COUNT'])) {
                        $info['VISITED_URLS_COUNT'] +=
                            $a_info['VISITED_URLS_COUNT'];
                    }
                    if (isset($a_info['COUNT'])) {
                        $info['COUNT'] +=
                            $a_info['COUNT'];
                    }
                }
                set_error_handler(C\NS_LIB . "yioop_error_handler");
                file_put_contents($cache_file, serialize($info));
                return $info;
            }
            $dir = C\CRAWL_DIR.'/cache/'.self::index_data_base_name.$timestamp;
            if (file_exists($dir)) {
                $info = IndexArchiveBundle::getArchiveInfo($dir);
                if (isset($info['DESCRIPTION'])) {
                    $tmp = unserialize($info['DESCRIPTION']);
                    $info['DESCRIPTION'] = isset($tmp['DESCRIPTION']) ?
                        $tmp['DESCRIPTION'] : "";
                }
            }
        }
        return $info;
    }
    /**
     * Deletes the crawl with the supplied timestamp if it exists. Also
     * deletes any crawl mixes making use of this crawl
     *
     * @param string $timestamp a Unix timestamp
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function deleteCrawl($timestamp, $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            //get rid of cache info on Name machine
            $mask = C\CRAWL_DIR."/cache/".self::network_crawllist_base_name.
                "*.txt";
            array_map("unlink", glob($mask));
            $delete_files = [
                C\CRAWL_DIR."/cache/".self::network_base_name.
                    "$timestamp.txt",
                C\CRAWL_DIR."/cache/".self::statistics_base_name.
                    "$timestamp.txt"
            ];
            foreach ($delete_files as $delete_file) {
                if (file_exists($delete_file)) {
                    unlink($delete_file);
                }
            }
            if (!in_array(C\NAME_SERVER, $machine_urls)) {
                array_unshift($machine_urls, C\NAME_SERVER);
            }
            //now get rid of files on queue servers
            $this->execMachines("deleteCrawl",
                $machine_urls, serialize($timestamp));
            return;
        }
        $delete_dirs = [
            C\CRAWL_DIR.'/cache/'.self::index_data_base_name . $timestamp,
            C\CRAWL_DIR.'/schedules/'.self::index_data_base_name . $timestamp,
            C\CRAWL_DIR.'/schedules/' . self::schedule_data_base_name .
                $timestamp,
            C\CRAWL_DIR.'/schedules/'.self::robot_data_base_name . $timestamp,
            C\CRAWL_DIR.'/schedules/'.self::name_archive_iterator . $timestamp,
        ];
        foreach ($delete_dirs as $delete_dir) {
            if (file_exists($delete_dir)) {
                $this->db->unlinkRecursive($delete_dir, true);
            }
        }
        $save_point_files = glob(C\CRAWL_DIR.'/schedules/'.self::save_point.
            $timestamp."*.txt");
        foreach ($save_point_files as $save_point_file) {
            @unlink($save_point_file);
        }

        $sql = "SELECT DISTINCT TIMESTAMP FROM MIX_COMPONENTS WHERE ".
            " CRAWL_TIMESTAMP='$timestamp'";
        $result = $this->db->execute($sql);
        $rows = [];
        while ($rows[] =  $this->db->fetchArray($result)) ;

        foreach ($rows as $row) {
            $this->deleteCrawlMix($row['TIMESTAMP']);
        }
        $current_timestamp = $this->getCurrentIndexDatabaseName();
        if ($current_timestamp == $timestamp) {
            $this->db->execute("DELETE FROM CURRENT_WEB_INDEX");
        }
    }
    /**
     * Used to send a message to the queue servers to start a crawl
     *
     * @param array $crawl_params has info like the time of the crawl,
     *      whether starting a new crawl or resuming an old one, etc.
     * @param array $seed_info what urls to crawl, etc as from the crawl.ini
     *      file
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function sendStartCrawlMessage($crawl_params, $seed_info = null,
        $machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $params = [$crawl_params, $seed_info];
            $crawl_time = $crawl_params[self::CRAWL_TIME];
            file_put_contents(C\CRAWL_DIR."/schedules/network_status.txt",
                serialize($crawl_time));
            $this->execMachines("sendStartCrawlMessage",
                $machine_urls, serialize($params));
            return;
        }
        $info_string = serialize($crawl_params);
        file_put_contents(
            C\CRAWL_DIR."/schedules/QueueServerMessages.txt",
            $info_string);
        chmod(C\CRAWL_DIR."/schedules/QueueServerMessages.txt",
            0777);
        if ($seed_info != null) {
            $scheduler_info[self::HASH_SEEN_URLS] = [];
            foreach ($seed_info['seed_sites']['url'] as $site) {
                if ($site[0] == "#") {
                    continue;
                } //ignore comments in file
                $site_parts = preg_split("/\s+/", $site);
                if (strlen($site_parts[0]) > 0) {
                    $scheduler_info[self::TO_CRAWL][] = [$site_parts[0], 1.0];
                }
            }
            $scheduler_string = "\n".L\webencode(
                gzcompress(serialize($scheduler_info)));
            file_put_contents(
                C\CRAWL_DIR."/schedules/".self::schedule_start_name,
                $scheduler_string);
        }
    }
    /**
     * Used to send a message to the queue servers to stop a crawl
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function sendStopCrawlMessage($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            @unlink(C\CRAWL_DIR."/schedules/network_status.txt");
            $this->execMachines("sendStopCrawlMessage", $machine_urls);
            return;
        }

        $info = [];
        $info[self::STATUS] = "STOP_CRAWL";
        $info_string = serialize($info);
        file_put_contents(
            C\CRAWL_DIR."/schedules/QueueServerMessages.txt",
            $info_string);
    }
    /**
     * Gets a list of all index archives of crawls that have been conducted
     *
     * @param bool $return_arc_bundles whether index bundles used for indexing
     *     arc or other archive bundles should be included in the lsit
     * @param bool $return_recrawls whether index archive bundles generated as
     *     a result of recrawling should be included in the result
     * @param array $machine_urls an array of urls of yioop queue servers
     * @param bool $cache whether to try to get/set the data to a cache file
     *
     * @return array available IndexArchiveBundle directories and
     *     their meta information this meta information includes the time of
     *     the crawl, its description, the number of pages downloaded, and the
     *     number of partitions used in storing the inverted index
     */
    public function getCrawlList($return_arc_bundles = false,
        $return_recrawls = false, $machine_urls = null, $cache = false)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $arg = ($return_arc_bundles && $return_recrawls) ? 3 :
                (($return_recrawls) ? 2 : (($return_arc_bundles) ? 1 : 0));
            $cache_file = C\CRAWL_DIR."/cache/" .
                self::network_crawllist_base_name . "$arg.txt";
            if ($cache && file_exists($cache_file) && filemtime($cache_file)
                + 300 > time()) {
                return unserialize(file_get_contents($cache_file));
            }
            $list_strings = $this->execMachines("getCrawlList",
                $machine_urls, $arg);
            $list = $this->aggregateCrawlList($list_strings);
            if ($cache) {
                file_put_contents($cache_file, serialize($list));
            }
            return $list;
        }
        $list = [];
        $dirs = glob(C\CRAWL_DIR.'/cache/'.self::index_data_base_name.
            '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $crawl = [];
            $pre_timestamp = strstr($dir, self::index_data_base_name);
            $crawl['CRAWL_TIME'] =
                substr($pre_timestamp, strlen(self::index_data_base_name));
            $info = L\IndexArchiveBundle::getArchiveInfo($dir);
            if (isset($info['DESCRIPTION'])) {
                restore_error_handler();
                $index_info = @unserialize($info['DESCRIPTION']);
                set_error_handler(C\NS_LIB . "yioop_error_handler");
            } else {
                $index_info = [];
                $index_info['DESCRIPTION'] = "ERROR!! $dir<br />" .
                    print_r($info, true);
            }
            $crawl['DESCRIPTION'] = "";
            if (!$return_recrawls &&
                isset($index_info[self::CRAWL_TYPE]) &&
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                continue;
            } elseif ($return_recrawls  &&
                isset($index_info[self::CRAWL_TYPE]) &&
                $index_info[self::CRAWL_TYPE] == self::ARCHIVE_CRAWL) {
                $crawl['DESCRIPTION'] = "RECRAWL::";
            }
            $sched_path = C\CRAWL_DIR.'/schedules/'.
                self::schedule_data_base_name.$crawl['CRAWL_TIME'];
            $crawl['RESUMABLE'] = false;
            if (is_dir($sched_path)) {
                $sched_dir = opendir($sched_path);
                while (($name = readdir($sched_dir)) !==  false) {
                    $sub_path = "$sched_path/$name";
                    if (!is_dir($sub_path) || $name == '.' ||
                        $name == '..') {
                        continue;
                    }
                    $sub_dir = opendir($sub_path);
                    $i = 0;
                    while (($sub_name = readdir($sub_dir)) !==  false && $i < 5) {
                        if ($sub_name[0] == 'A' && $sub_name[1] == 't') {
                            $crawl['RESUMABLE'] = true;
                            break 2;
                        }
                    }
                    closedir($sub_dir);
                }
                closedir($sched_dir);
            }
            if (isset($index_info['DESCRIPTION'])) {
                $crawl['DESCRIPTION'] .= $index_info['DESCRIPTION'];
            }
            $crawl['VISITED_URLS_COUNT'] =
                isset($info['VISITED_URLS_COUNT']) ?
                $info['VISITED_URLS_COUNT'] : 0;
            $crawl['COUNT'] = (isset($info['COUNT'])) ? $info['COUNT'] :0;
            $crawl['NUM_DOCS_PER_PARTITION'] =
                (isset($info['NUM_DOCS_PER_PARTITION'])) ?
                $info['NUM_DOCS_PER_PARTITION'] : 0;
            $crawl['WRITE_PARTITION'] =
                (isset($info['WRITE_PARTITION'])) ?
                $info['WRITE_PARTITION'] : 0;
            $list[] = $crawl;
        }
        if ($return_arc_bundles) {
            $dirs = glob(C\CRAWL_DIR.'/archives/*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $crawl = [];
                $crawl['CRAWL_TIME'] = crc32($dir);
                $crawl['DESCRIPTION'] = "ARCFILE::";
                $crawl['ARC_DIR'] = $dir;
                $ini_file = "$dir/arc_description.ini";
                if (!file_exists($ini_file)) {
                    continue;
                } else {
                    $ini = L\parse_ini_with_fallback($ini_file);
                    $crawl['ARC_TYPE'] = $ini['arc_type'];
                    $crawl['DESCRIPTION'] .= $ini['description'];
                }
                $crawl['VISITED_URLS_COUNT'] = 0;
                $crawl['COUNT'] = 0;
                $crawl['NUM_DOCS_PER_PARTITION'] = 0;
                $crawl['WRITE_PARTITION'] = 0;
                $list[] = $crawl;
            }
        }
        return $list;
    }
    /**
     * When @see getCrawlList() is used in a multi-queue server this method
     * used to integrate the crawl lists received by the different machines
     *
     * @param array $list_strings serialized crawl list data from different
     * queue servers
     * @param string $data_field field of $list_strings to use for data
     * @return array list of crawls and their meta data
     */
    public function aggregateCrawlList($list_strings, $data_field = null)
    {
        restore_error_handler();
        $pre_list = [];
        foreach ($list_strings as $list_string) {
            $a_list = @unserialize(L\webdecode(
                $list_string[self::PAGE]));
            if ($data_field != null) {
                $a_list = $a_list[$data_field];
            }
            if (is_array($a_list)) {
                foreach ($a_list as $elt) {
                    $timestamp = $elt['CRAWL_TIME'];
                    if (!isset($pre_list[$timestamp])) {
                        $pre_list[$timestamp] = $elt;
                    } else {
                        if (isset($elt["DESCRIPTION"]) &&
                            $elt["DESCRIPTION"] != "") {
                            $pre_list[$timestamp]["DESCRIPTION"] =
                                $elt["DESCRIPTION"];
                        }
                        $pre_list[$timestamp]["VISITED_URLS_COUNT"] +=
                            $elt["VISITED_URLS_COUNT"];
                        $pre_list[$timestamp]["COUNT"] +=
                            $elt["COUNT"];
                        $pre_list[$timestamp]['RESUMABLE'] |= $elt['RESUMABLE'];
                    }
                }
            }
        }
        $list = array_values($pre_list);
        set_error_handler(C\NS_LIB . "yioop_error_handler");
        return $list;
    }
    /**
     * Determines if the length of time since any of the fetchers has spoken
     * with any of the queue servers has exceeded CRAWL_TIME_OUT. If so,
     * typically the caller of this method would do something such as officially
     * stop the crawl.
     *
     * @param array $list_strings serialized crawl list data from different
     *  queue servers
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return bool whether the current crawl is stalled or not
     */
    public function crawlStalled($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $outputs = $this->execMachines("crawlStalled", $machine_urls);
            return $this->aggregateStalled($outputs);
        }

        if (file_exists(C\CRAWL_DIR."/schedules/crawl_status.txt")) {
            /* assume if status not updated for CRAWL_TIME_OUT
               crawl not active (do check for both scheduler and indexer) */
            if (filemtime(
                C\CRAWL_DIR."/schedules/crawl_status.txt") +
                    C\CRAWL_TIME_OUT < time()) {
                return true;
            }
            $schedule_status_exists =
                file_exists(C\CRAWL_DIR."/schedules/schedule_status.txt");
            if ($schedule_status_exists &&
                filemtime(C\CRAWL_DIR."/schedules/schedule_status.txt") +
                    C\CRAWL_TIME_OUT < time()) {
                return true;
            }
        }
        return false;
    }
    /**
     * When @see crawlStalled() is used in a multi-queue server this method
     * used to integrate the stalled information received by the different
     * machines
     *
     * @param array $stall_statuses contains web encoded serialized data one
     * one field of which has the boolean data concerning stalled statis
     *
     * @param string $data_field field of $stall_statuses to use for data
     *     if null then each element of $stall_statuses is a wen encoded
     *     serialized boolean
     * @return bool true if no queue server has heard from one
     *     fetcher within the time out period
     */
    public function aggregateStalled($stall_statuses, $data_field = null)
    {
        restore_error_handler();
        $result = true;
        foreach ($stall_statuses as $status) {
            $stall_status = @unserialize(L\webdecode($status[self::PAGE]));
            if ($data_field != null) {
                $stall_status = $stall_status[$data_field];
            } else {
                /* this case would mean some kind of error occurred, but
                   don't stop crawl for it */
                $result = false;
                break;
            }
            if ($stall_status === false) {
                $result = false;
                break;
            }
        }
        set_error_handler(C\NS_LIB . "yioop_error_handler");
        return $result;
    }
    /**
     * Returns data about current crawl such as DESCRIPTION, TIMESTAMP,
     * peak memory of various processes, most recent fetcher, most recent
     * urls, urls seen, urls visited, etc.
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     *     on which the crawl is being conducted
     * @return array associative array of the said data
     */
    public function crawlStatus($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $status_strings = $this->execMachines("crawlStatus", $machine_urls);
            return $this->aggregateStatuses($status_strings);
        }

        $data = [];
        $crawl_status_exists =
            file_exists(C\CRAWL_DIR."/schedules/crawl_status.txt");
        if ($crawl_status_exists) {
            $crawl_status =
                @unserialize(file_get_contents(
                    C\CRAWL_DIR."/schedules/crawl_status.txt"));
        }
        $schedule_status_exists =
            file_exists(C\CRAWL_DIR."/schedules/schedule_status.txt");
        if ($schedule_status_exists) {
            $schedule_status =
                @unserialize(file_get_contents(
                    C\CRAWL_DIR."/schedules/schedule_status.txt"));
            if (isset($schedule_status[self::TYPE]) &&
                $schedule_status[self::TYPE] == self::SCHEDULER) {
                $data['SCHEDULER_PEAK_MEMORY'] =
                    isset($schedule_status[self::MEMORY_USAGE]) ?
                    $schedule_status[self::MEMORY_USAGE] : 0;
            }
        }

        $data = (isset($crawl_status) && is_array($crawl_status)) ?
            array_merge($data, $crawl_status) : $data;

        if (isset($data['VISITED_COUNT_HISTORY']) &&
            count($data['VISITED_COUNT_HISTORY']) > 1) {
            $recent = array_shift($data['VISITED_COUNT_HISTORY']);
            $data["MOST_RECENT_TIMESTAMP"] = $recent[0];
            $oldest = array_pop($data['VISITED_COUNT_HISTORY']);
            unset($data['VISITED_COUNT_HISTORY']);
            $change_in_time_hours = floatval(time() - $oldest[0]) /
                floatval(C\ONE_HOUR);
            $change_in_urls = $recent[1] - $oldest[1];
            $data['VISITED_URLS_COUNT_PER_HOUR'] = ($change_in_time_hours > 0) ?
                $change_in_urls/$change_in_time_hours : 0;
        } else {
            $data['VISITED_URLS_COUNT_PER_HOUR'] = 0;
        }

        return $data;
    }
    /**
     * When @see crawlStatus() is used in a multi-queue server this method
     * used to integrate the status information received by the different
     * machines
     *
     * @param array $status_strings
     * @param string $data_field field of $status_strings to use for data
     * @return array associative array of DESCRIPTION, TIMESTAMP,
     * peak memory of various processes, most recent fetcher, most recent
     * urls, urls seen, urls visited, etc.
     */
    public function aggregateStatuses($status_strings, $data_field = null)
    {
        $status['WEBAPP_PEAK_MEMORY'] = 0;
        $status['FETCHER_PEAK_MEMORY'] = 0;
        $status['QUEUE_PEAK_MEMORY'] = 0;
        $status["SCHEDULER_PEAK_MEMORY"] = 0;
        $status["COUNT"] = 0;
        $status["VISITED_URLS_COUNT"] = 0;
        $status["VISITED_URLS_COUNT_PER_HOUR"] = 0;
        $status["MOST_RECENT_TIMESTAMP"] = 0;
        $status["DESCRIPTION"] = "";
        $status['MOST_RECENT_FETCHER'] = "";
        $status['MOST_RECENT_URLS_SEEN'] = [];
        $status['CRAWL_TIME'] = 0;
        restore_error_handler();
        foreach ($status_strings as $status_string) {
            $a_status = @unserialize(L\webdecode(
                    $status_string[self::PAGE]));
            if ($data_field != null) {
                $a_status = $a_status[$data_field];
            }
            $count_fields = ["COUNT", "VISITED_URLS_COUNT_PER_HOUR",
                "VISITED_URLS_COUNT"];
            foreach ($count_fields as $field) {
                if (isset($a_status[$field])) {
                    $status[$field] += $a_status[$field];
                }
            }
            if (isset($a_status["CRAWL_TIME"]) && $a_status["CRAWL_TIME"] >=
                $status['CRAWL_TIME']) {
                $status['CRAWL_TIME'] = $a_status["CRAWL_TIME"];
                $text_fields = ["DESCRIPTION", "MOST_RECENT_FETCHER"];
                foreach ($text_fields as $field) {
                    if (isset($a_status[$field])) {
                        if ($status[$field] == "" ||
                            in_array($status[$field], ["BEGIN_CRAWL",
                                "RESUME_CRAWL"])) {
                            $status[$field] = $a_status[$field];
                        }
                    }
                }
            }
            if (isset($a_status["MOST_RECENT_TIMESTAMP"]) &&
                $status["MOST_RECENT_TIMESTAMP"] <=
                    $a_status["MOST_RECENT_TIMESTAMP"]) {
                $status["MOST_RECENT_TIMESTAMP"] =
                    $a_status["MOST_RECENT_TIMESTAMP"];
                if (isset($a_status['MOST_RECENT_URLS_SEEN'])) {
                    $status['MOST_RECENT_URLS_SEEN'] =
                        $a_status['MOST_RECENT_URLS_SEEN'];
                }
            }
            $memory_fields = ["WEBAPP_PEAK_MEMORY", "FETCHER_PEAK_MEMORY",
                "QUEUE_PEAK_MEMORY", "SCHEDULER_PEAK_MEMORY"];
            foreach ($memory_fields as $field) {
                $status[$field] = (!isset($a_status[$field])) ? 0 :
                        max($status[$field], $a_status[$field]);
            }
        }
        set_error_handler(C\NS_LIB . "yioop_error_handler");
        return $status;
    }
    /**
     * This method is used to reduce the number of network requests
     * needed by the crawlStatus method of admin_controller. It returns
     * an array containing the results of the @see crawlStalled
     * @see crawlStatus and @see getCrawlList methods
     *
     * @param array $machine_urls an array of urls of yioop queue servers
     * @return array containing three components one for each of the three
     *     kinds of results listed above
     */
    public function combinedCrawlInfo($machine_urls = null)
    {
        if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
            $combined_strings =
                $this->execMachines("combinedCrawlInfo", $machine_urls);
            $combined = [];
            $combined[] = $this->aggregateStalled($combined_strings,
                0);
            $combined[] = $this->aggregateStatuses($combined_strings,
                1);
            $combined[] = $this->aggregateCrawlList($combined_strings,
                2);
            return $combined;
        }
        $combined = [];
        $combined[] = $this->crawlStalled();
        $combined[] = $this->crawlStatus();
        $combined[] = $this->getCrawlList(false, true);
        return $combined;
    }
    /**
     * Add the provided urls to the schedule directory of URLs that will
     * be crawled
     *
     * @param string $timestamp Unix timestamp of crawl to add to schedule of
     * @param array $inject_urls urls to be added to the schedule of
     *     the active crawl
     * @param array $machine_urls an array of urls of yioop queue servers
     */
    public function injectUrlsCurrentCrawl($timestamp, $inject_urls,
        $machine_urls = null)
    {
        if ($machine_urls != null &&
            !$this->isSingleLocalhost($machine_urls, $timestamp)) {
            $this->execMachines("injectUrlsCurrentCrawl", $machine_urls,
                serialize(array($timestamp, $inject_urls)));
            return;
        }

        $dir = C\CRAWL_DIR."/schedules/".
            self::schedule_data_base_name. $timestamp;
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $day = floor($timestamp/C\ONE_DAY) - 1;
            /* want before all other schedules,
               execute next */
        $dir .= "/$day";
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $count = count($inject_urls);
        if ($count > 0) {
            $now = time();
            $schedule_data = [];
            $schedule_data[self::SCHEDULE_TIME] =
                $timestamp;
            $schedule_data[self::TO_CRAWL] = [];
            for ($i = 0; $i < $count; $i++) {
                $url = $inject_urls[$i];
                $hash = L\crawlHash($now.$url);
                $schedule_data[self::TO_CRAWL][] =
                    [$url, 1, $hash];
            }
            $data_string = L\webencode(
                gzcompress(serialize($schedule_data)));
            $data_hash = L\crawlHash($data_string);
            file_put_contents($dir."/At1From127-0-0-1".
                "WithHash$data_hash.txt", $data_string);
            return true;
        }
        return false;
    }
    /**
     * Computes for each word in an array of words a count of the total number
     * of times it occurs in this crawl model's default index.
     *
     * @param array $words words to find the counts for
     * @param array $machine_urls machines to invoke this command on
     * @return array associative array of word => counts
     */
     public function countWords($words, $machine_urls = null)
     {
         if ($machine_urls != null && !$this->isSingleLocalhost($machine_urls)) {
             $count_strings = $this->execMachines("countWords", $machine_urls,
                serialize(array($words, $this->index_name)));
             $word_counts = [];
             foreach ($count_strings as $count_string) {
                 $a_word_counts = unserialize(L\webdecode(
                        $count_string[self::PAGE]));
                 if (is_array($a_word_counts)) {
                     foreach ($a_word_counts as $word => $count) {
                         $word_counts[$word] = (isset($word_counts[$word])) ?
                            $word_counts[$word] + $count : $count;
                     }
                 }
             }
             return $word_counts;
         }
         $index_archive = IndexManager::getIndex($this->index_name);
         $hashes = [];
         $lookup = [];
         foreach ($words as $word) {
             $tmp = L\crawlHash($word);
             $hashes[] = $tmp;
             $lookup[$tmp] = $word;
         }
         $word_key_counts =
            $index_archive->countWordKeys($hashes);
         $phrases = [];
         $word_counts = [];
         if (is_array($word_key_counts) && count($word_key_counts) > 0) {
             foreach ($word_key_counts as $word_key =>$count) {
                 $word_counts[$lookup[$word_key]] = $count;
             }
         }
         return $word_counts;
     }
}
