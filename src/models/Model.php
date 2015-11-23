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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\UrlParser;

/**  For crawlHash function and Yioop Project constants */
require_once __DIR__."/../library/Utility.php";
/**
 *
 * This is a base class for all models
 * in the SeekQuarry search engine. It provides
 * support functions for formatting search results
 *
 * @author Chris Pollett
 */
class Model implements CrawlConstants
{
    const SCORE_PRECISION = 4;
    const SNIPPET_TITLE_LENGTH = 20;
    const MAX_SNIPPET_TITLE_LENGTH = 20;
    const SNIPPET_LENGTH_LEFT = 20;
    const SNIPPET_LENGTH_RIGHT = 40;
    const MIN_SNIPPET_LENGTH = 100;
    /**
     * Default maximum character length of a search summary
     */
    const DEFAULT_DESCRIPTION_LENGTH = 150;
    /** Reference to a DatasourceManager
     * @var object
     */
    public $db;
    /** Name of the search engine database
     * @var string
     */
    public $db_name;
    /**
     * Associative array of page summaries which might be used to
     * override default page summaries if set.
     * @var array
     */
    public $edited_page_summaries = null;
    /**
     * These fields if present in $search_array (used by @see getRows() ),
     * but with value "-1", will be skipped as part of the where clause
     * but will be used for order by clause
     * @var array
     */
    public $any_fields = [];
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * @var array
     */
    public $search_table_column_map = [];
    /**
     * Cache object to be used if we are doing caching
     * @var object
     */
    public static $cache;
    /**
     * Sets up the database manager that will be used and name of the search
     * engine database
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    public function __construct($db_name = C\DB_NAME, $connect = true)
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        if ($connect) {
            $this->db->connect();
        }
        $this->db_name = $db_name;
    }
    /**
     * Given an array page summaries, for each summary extracts snippets which
     * are related to a set of search words. For each snippet, bold faces the
     * search terms, and then creates a new summary array.
     *
     * @param array $results web pages summaries (these in turn are
     *     arrays!)
     * @param array $words keywords (typically what was searched on)
     * @param int $description_length length of the description
     * @return array summaries which have been snippified and bold faced
     */
    public function formatPageResults($results, $words = null,
        $description_length = self::DEFAULT_DESCRIPTION_LENGTH)
    {
        if (isset($results['PAGES'])) {
            $pages = $results['PAGES'];
            $num_pages = count($pages);
        } else {
            $output['TOTAL_ROWS'] = 0;
            $output['PAGES'] = null;
            return;
        }
        $deleted_a_page = false;
        for ($i = 0; $i < $num_pages; $i++) {
            $page = $pages[$i];
            if (!isset($page[self::URL])) {
                unset($pages[$i]);
                $deleted_a_page = true;
                continue;
            }
            if ($this->edited_page_summaries != null) {
                $url_parts = explode("|", $page[self::URL]);
                if (count($url_parts) > 1) {
                    $url = trim($url_parts[1]);
                } else {
                    $url = $page[self::URL];
                }
                $hash_url = L\crawlHash($url, true);
                if (isset($this->edited_page_summaries[$hash_url])) {
                    $summary = $this->edited_page_summaries[$hash_url];
                    $page[self::URL] = $url;
                    foreach ([self::TITLE, self::DESCRIPTION] as $field) {
                        if (isset($summary[$field])) {
                            $page[$field] = $summary[$field];
                        }
                    }
                }
            }
            if (!isset($page[self::TITLE])) {
                $page[self::TITLE] = "";
            }
            $page[self::TITLE] = strip_tags($page[self::TITLE]);
            if (strlen($page[self::TITLE]) == 0) {
                $offset = min(mb_strlen($page[self::DESCRIPTION]),
                    self::SNIPPET_TITLE_LENGTH);
                $end_title = mb_strpos($page[self::DESCRIPTION], " ", $offset);
                $ellipsis = "";
                if ($end_title > self::SNIPPET_TITLE_LENGTH) {
                    $ellipsis = "...";
                    if ($end_title > self::MAX_SNIPPET_TITLE_LENGTH) {
                        $end_title = self::MAX_SNIPPET_TITLE_LENGTH;
                    }
                }
                $page[self::TITLE] =
                    mb_substr(strip_tags($page[self::DESCRIPTION]), 0,
                        $end_title) . $ellipsis;
                //still no text revert to url
                if (strlen($page[self::TITLE]) == 0 &&
                    isset($page[self::URL])) {
                    $page[self::TITLE] = $page[self::URL];
                }
            }
            // do a little cleaning on text
            if ($words != null) {
                $page[self::TITLE] =
                    $this->boldKeywords($page[self::TITLE], $words);
                if (!isset($page[self::IS_FEED])) {
                    $page[self::DESCRIPTION] =
                        $this->getSnippets(strip_tags($page[self::DESCRIPTION]),
                        $words, $description_length);
                }
                $page[self::DESCRIPTION] =
                    $this->boldKeywords($page[self::DESCRIPTION], $words);
            } else {
                $page[self::DESCRIPTION] = mb_substr(strip_tags(
                    $page[self::DESCRIPTION]), 0, $description_length);
            }
            $page[self::SCORE] = mb_substr($page[self::SCORE], 0,
                self::SCORE_PRECISION);
            $pages[$i] = $page;
        }
        $output['TOTAL_ROWS'] = $results['TOTAL_ROWS'];
        $output['PAGES'] = ($deleted_a_page) ? $pages : array_values($pages);
        return $output;
    }
    /**
     * Given a string, extracts a snippets of text related to a given set of
     * key words. For a given word a snippet is a window of characters to its
     * left and right that is less than a maximum total number of characters.
     * There is also a rule that a snippet should avoid ending in the middle of
     * a word
     *
     * @param string $text haystack to extract snippet from
     * @param array $words keywords used to make look in haystack
     * @param string $description_length length of the description desired
     * @param bool $words_change getSnippets might be called many times on
     *      the same search page with the same $words, if true then the
     *      preprocessing of $words is avoided and cached versions are used
     * @return string a concatenation of the extracted snippets of each word
     */
    public function getSnippets($text, $words, $description_length,
        $words_change = false)
    {
        static $search_words = [];
        static $word_regex = "";
        $start_regex = "/";
        $left = self::SNIPPET_LENGTH_LEFT;
        $right = self::SNIPPET_LENGTH_RIGHT;
        $start_regex2 = "/\b.{0,$left}(?:(?:";
        $end_regex = "/ui";
        $end_regex2 = ").{0,$right}\b)+/ui";
        if (mb_strlen($text) < $description_length) {
            return $text;
        }
        $ellipsis = "";
        if($words_change || empty($search_words)) {
            $search_words = [];
            foreach ($words as $word) {
                $search_words = array_merge($search_words, explode(" ", $word));
            }
            $search_words = array_filter(array_unique($search_words));

            $word_regex = "";
            $delim = "";
            foreach($search_words as $word) {
                $word_regex .= $delim . preg_quote($word);
                $delim = "|";
            }
        }
        $snippet_string = "";
        $snippet_hash = [];
        $text_sources = explode(".. ", $text);
        foreach ($text_sources as $text_source) {
            $len = mb_strlen($text_source);
            $offset = 0;
            if ($len < self::MIN_SNIPPET_LENGTH) {
                if (!isset($snippet_hash[$text_source])) {
                    if (preg_match($start_regex . $word_regex.
                        $end_regex, $text_source)) {
                        $snippet_string .= $ellipsis. $text_source;
                        $ellipsis = " ... ";
                        $snippet_hash[$text_source] = true;
                        if (mb_strlen($snippet_string) >= $description_length) {
                            break;
                        }
                    }
                }
                continue;
            }
            $word_locations = [];
            preg_match_all($start_regex2 .$word_regex. $end_regex2,
                $text_source, $matches);
            if(isset($matches[0])) {
                foreach($matches[0] as $match) {
                    if($match >= $description_length) {
                        $match = mb_substr($match, 0, $description_length);
                        $rpos = strrpos($match, " ");
                        if($rpos) {
                            $match = mb_substr($match, 0, $rpos);
                        }
                    }
                    $snippet_string .= $ellipsis. trim($match, ".");
                    $ellipsis = " ... ";
                    $snippet_hash[$text_source] = true;
                    if (mb_strlen($snippet_string) >= $description_length) {
                        break;
                    }
                }
            }
        }
        return $snippet_string;
    }
    /**
     * Given a string, wraps in bold html tags a set of key words it contains.
     *
     * @param string $text haystack string to look for the key words
     * @param array $words an array of words to bold face
     *
     * @return string  the resulting string after boldfacing has been applied
     */
    public function boldKeywords($text, $words)
    {
        $words = array_unique($words);
        foreach ($words as $word) {
            if ($word != "" && !stristr($word, "/")) {
                $pattern = '/('.preg_quote($word).')/i';
                $new_text = preg_replace($pattern, '<b>$1</b>', $text);
                $text = $new_text;
            }
        }
        return $text;
    }
    /**
     * Gets a list of all DBMS that work with the search engine
     *
     * @return array Names of availabledatasources
     */
    public function getDbmsList()
    {
        $list = [];
        $data_managers = glob(C\BASE_DIR.'/models/datasources/*Manager.php');

        foreach ($data_managers as $data_manager) {
            $dbms =
                substr($data_manager,
                    strlen(C\BASE_DIR.'/models/datasources/'), -
                    strlen("Manager.php"));
            if ($dbms != 'Datasource') {
                $list[] = $dbms;
            }
        }
        return $list;
    }
    /**
     * Returns whether the provided dbms needs a login and password or not
     * (sqlite or sqlite3)
     *
     * @param string $dbms the name of a database management system
     * @return bool true if needs a login and password; false otherwise
     */
    public function loginDbms($dbms)
    {
        return !in_array($dbms, ["Sqlite3"]);
    }
    /**
     * Used to determine if an action involves just one yioop instance on
     * the current local machine or not
     *
     * @param array $machine_urls urls of yioop instances to which the action
     *     applies
     * @param string $index_timestamp if timestamp exists checks if the index
     *     has declared itself to be a no network index.
     * @return bool whether it involves a single local yioop instance (true)
     *     or not (false)
     */
    public function isSingleLocalhost($machine_urls, $index_timestamp = -1)
    {
        if ($index_timestamp >= 0) {
            $index_archive_name= self::index_data_base_name.$index_timestamp;
            if (file_exists(
                C\CRAWL_DIR."/cache/$index_archive_name/no_network.txt")) {
                return true;
            }
        }
        return count($machine_urls) <= 1 &&
                    UrlParser::isLocalhostUrl($machine_urls[0]) &&
                    UrlParser::getPath(C\NAME_SERVER) ==
                    UrlParser::getPath($machine_urls[0]);
    }
    /**
     * Used to get the translation of a string_id stored in the database to
     * the given locale.
     *
     * @param string $string_id id to translate
     * @param string $locale_tag to translate to
     * @return mixed translation if found, $string_id, otherwise
     */
    public function translateDb($string_id, $locale_tag)
    {
        static $lookup = [];
        $db = $this->db;
        if (isset($lookup[$string_id])) {
            return $lookup[$string_id];
        }
        $sql = "
            SELECT TL.TRANSLATION AS TRANSLATION
            FROM TRANSLATION T, LOCALE L, TRANSLATION_LOCALE TL
            WHERE T.IDENTIFIER_STRING = :string_id AND
                L.LOCALE_TAG = :locale_tag AND
                L.LOCALE_ID = TL.LOCALE_ID AND
                T.TRANSLATION_ID = TL.TRANSLATION_ID " . $db->limitOffset(1);
        $result = $db->execute($sql,
            [":string_id" => $string_id, ":locale_tag" => $locale_tag]);
        $row = $db->fetchArray($result);
        if (isset($row['TRANSLATION'])) {
            return $row['TRANSLATION'];
        }
        return $string_id;
    }
    /**
     * Get the user_id associated with a given username
     * (In base class as used as an internal method in both signin and
     *  user models)
     *
     * @param string $username the username to look up
     * @return string the corresponding userid
     */
    public function getUserId($username)
    {
        $db = $this->db;
        $sql = "SELECT USER_ID FROM USERS WHERE
            UPPER(USER_NAME) = UPPER(?) ". $db->limitOffset(1);
        $result = $db->execute($sql, [$username]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        $user_id = $row['USER_ID'];
        return $user_id;
    }
    /**
     * Creates the WHERE and ORDER BY clauses for a query of a Yioop
     * table such as USERS, ROLE, GROUP, which have associated search web
     * forms. Searches are case insensitive
     *
     * @param array $search_array each element of this is a quadruple
     *     name of a field, what comparison to perform, a value to check,
     *     and an order (ascending/descending) to sort by
     * @param array $any_fields these fields if present in search array
     *     but with value "-1" will be skipped as part of the where clause
     *     but will be used for order by clause
     * @return array string for where clause, string for order by clause
     */
    public function searchArrayToWhereOrderClauses($search_array,
        $any_fields = ['status'])
    {
        $db = $this->db;
        $where = "";
        $order_by = "";
        $order_by_comma = "";
        $where_and = "";
        $sort_types = ["ASC", "DESC"];
        foreach ($search_array as $row) {
            if (isset($this->search_table_column_map[$row[0]])) {
                $field_name = $this->search_table_column_map[$row[0]];
            } else {
                $field_name = $row[0];
            }
            $comparison = $row[1];
            $value = $row[2];
            $sort_dir = $row[3];
            if ($value != "" && (!in_array($row[0], $any_fields)
                || $value != "-1")) {
                if ($where == "") {
                    $where = " WHERE ";
                }
                $where .= $where_and;
                switch ($comparison) {
                    case "=":
                         $where .= "$field_name='".
                            $db->escapeString($value)."'";
                        break;
                    case "!=":
                         $where .= "$field_name!='".
                            $db->escapeString($value)."'";
                        break;
                    case "CONTAINS":
                         $where .= "UPPER($field_name) LIKE UPPER('%".
                            $db->escapeString($value)."%')";
                        break;
                    case "BEGINS WITH":
                         $where .= "UPPER($field_name) LIKE UPPER('".
                            $db->escapeString($value)."%')";
                        break;
                    case "ENDS WITH":
                         $where .= "UPPER($field_name) LIKE UPPER('%".
                            $db->escapeString($value)."')";
                        break;
                }
                $where_and = " AND ";
            }
            if (in_array($sort_dir, $sort_types)) {
                if ($order_by == "") {
                    $order_by = " ORDER BY ";
                }
                $order_by .= $order_by_comma.$field_name." ".$sort_dir;
                $order_by_comma = ", ";
            }
        }
        return [$where, $order_by];
    }
    /**
     * Gets a range of rows which match the provided search criteria from
     * $th provided table
     *
     * @param int $limit starting row from the potential results to return
     * @param int $num number of rows after start row to return
     * @param int& $total gets set with the total number of rows that
     *     can be returned by the given database query
     * @param array $search_array each element of this is a
     *     quadruple name of a field, what comparison to perform, a value to
     *     check, and an order (ascending/descending) to sort by
     * @param array $args additional values which may be used to get rows
     *      (what these are will typically depend on the subclass
     *      implementation)
     * @return array
     */
    public function getRows($limit = 0, $num = 100, &$total,
        $search_array = [], $args = null)
    {
        $db = $this->db;
        $tables = $this->fromCallback($args);
        $limit = $db->limitOffset($limit, $num);
        list($where, $order_by) =
            $this->searchArrayToWhereOrderClauses($search_array,
            $this->any_fields);
        $more_conditions = $this->whereCallback($args);
        if ($more_conditions) {
            $add_where = " WHERE ";
            if ($where != "") {
                $add_where = " AND ";
            }
            $where .= $add_where. $more_conditions;
        }
        $count_column = "*";
        if (isset($this->search_table_column_map['key'])) {
            $count_column = "DISTINCT " . $this->search_table_column_map['key'];
        }
        $sql = "SELECT COUNT($count_column) AS NUM FROM $tables $where";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $total = $row['NUM'];
        $select_columns = $this->selectCallback($args);
        $sql = "SELECT $select_columns FROM ".
            "$tables $where $order_by $limit";
        $result = $db->execute($sql);
        $i = 0;
        $rows = [];
        $row_callback = false;
        if ($result) {
            while ($rows[$i] = $db->fetchArray($result)) {
                $rows[$i] = $this->rowCallback($rows[$i], $args);
                $i++;
            }
            unset($rows[$i]); //last one will be null
        }
        $rows = $this->postQueryCallback($rows);
        return $rows;
    }
    /**
     * Controls which columns and the names of those columns from the tables
     * underlying the given model should be return from a getRows call.
     * This defaults to *, but in general will be overriden in subclasses of
     * Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine the columns
     * @return string a comma separated list of columns suitable for a SQL
     *     query
     */
    public function selectCallback($args  = null)
    {
        return "*";
    }
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * This defaults to the single table whose name is whatever is before
     * Model in the name of the model. For example, by default on FooModel
     * this method would return "FOO". If a different behavior, this can be
     * overriden in subclasses of Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args  = null)
    {
        $name = strtoupper(get_class($this));
        $name = substr($name, strlen(C\NS_MODELS), -strlen("Model"));
        return $name;
    }
    /**
     * Controls the WHERE clause of the SQL query that
     * underlies the given model and should be used in a getRows call.
     * This defaults to an empty WHERE clause.
     *
     * @param mixed $args additional arguments that might be used to construct
     *     the WHERE clause.
     * @return string a SQL WHERE clause
     */
    public function whereCallback($args = null)
    {
        return "";
    }
    /**
     * Called after as row is retrieved by getRows from the database to
     * perform some manipulation that would be useful for this model.
     * For example, in CrawlModel, after a row representing a crawl mix
     * has been gotten, this is used to perform an additional query to marshal
     * its components. By default this method just returns this row unchanged.
     *
     * @param array $row row as retrieved from database query
     * @param mixed $args additional arguments that might be used by this
     *     callback
     * @return array $row after callback manipulation
     */
    public function rowCallback($row, $args)
    {
        return $row;
    }
    /**
     * Called after getRows has retrieved all the rows that it would retrieve
     * but before they are returned to give one last place where they could
     * be further manipulated. For example, in MachineModel this callback
     * is used to make parallel network calls to get the status of each machine
     * returned by getRows. The default for this method is to leave the
     * rows that would be returned unchanged
     *
     * @param array $rows that have been calculated so far by getRows
     * @return array $rows after this final manipulation
     *
     */
    public function postQueryCallback($rows)
    {
        return $rows;
    }
}
