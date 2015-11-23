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
use seekquarry\yioop\library\FileCache;
use seekquarry\yioop\library\IndexManager;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\Thesaurus;
use seekquarry\yioop\library\index_bundle_iterators as I;

/**
 * logging is done during crawl not through web,
 * so it will not be used in the phrase model
 */
if (!C\nsdefined("POST_PROCESSING") && !C\nsdefined("LOG_TO_FILES")) {
    C\nsdefine("LOG_TO_FILES", false);
}
/**
 *
 * This is class is used to handle
 * results for a given phrase search
 *
 * @author Chris Pollett
 */
class PhraseModel extends ParallelModel
{
    /** an associative array of additional meta words and
     * the max description length of results if such a meta word is used
     * this array is typically set in index.php
     *
     * @var array
     */
    public $additional_meta_words;
    /**
     * Used to hold query statistics about the current query
     * @var array
     */
    public $query_info;
    /**
     * Used to hold extension of programming language which is used the language
     * @var string
     */
    public $programming_language_map;
    /**
     * A indicator to indicate source code files
     * @var string
     */
    public $program_indicator;
    /**
     * Number of pages to cache in one go in memcache or filecache
     * Size chosen based on 1MB max object size for memcache or filecache
     */
     const NUM_CACHE_PAGES = 10;
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
        $this->programming_language_map = ['java' => 'java', 'py' => 'py'];
        $this->program_indicator = false;
    }
    /**
     * Returns whether there is a index with the provide timestamp
     *
     * @param int $index_time_stamp timestamp of the index to check if in cache
     * @return bool whether it exists or not
     */
    public function indexExists($index_time_stamp)
    {
        return file_exists(C\CRAWL_DIR.'/cache/IndexData'.$index_time_stamp);
    }
    /**
     * Rewrites a mix query so that it maps directly to a query about crawls
     *
     * @param string $query the original before a rewrite
     * @param object $mix a mix object saying how the mix is built out of crawls
     *
     * @return string a rewritten query in terms of crawls
     */
    public function rewriteMixQuery($query, $mix)
    {
        $disjunct_phrases = explode("|", $query);
        $rewrite = "";
        if (isset($mix['FRAGMENTS'])) {
            foreach ($mix['FRAGMENTS'] as $fragment) {
                $pipe = "";
                foreach ($disjunct_phrases as $disjunct) {
                    $rewrite .= $pipe;
                    $pipe = ' | ';
                    $disjunct_string = $disjunct;
                    $base_weight = 1;
                    $pattern = "/(\s)(index:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if (isset($matches[2][0])) {
                        $rewrite .= $disjunct;
                        continue;
                    }
                    $pattern = "/(\s)(i:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if (isset($matches[2][0])) {
                        $rewrite .= $disjunct;
                        continue;
                    }
                    $pattern = "/(\s)(weight:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if (isset($matches[2][0])) {
                        $base_weight = substr($matches[2][0], strlen("weight:"));
                        $disjunct_string =
                            preg_replace($pattern, "", $disjunct_string);
                    }
                    $pattern = "/(\s)(w:(\S)+)/";
                    preg_match_all($pattern, $query, $matches);
                    if (isset($matches[2][0])) {
                        $base_weight = substr($matches[2][0], strlen("w:"));
                        $disjunct_string =
                            preg_replace($pattern, "", $disjunct_string);
                    }
                    $pipe2 = "";
                    if (isset($fragment['COMPONENTS'])) {
                        $start_disjunct_string = $disjunct_string;
                        foreach ($fragment['COMPONENTS'] as $component) {
                            $disjunct_string = $start_disjunct_string;
                            if (isset($component['KEYWORDS'])) {
                                $disjunct_string .= " ".$component['KEYWORDS'];
                            }
                            $stamp = ($component['CRAWL_TIMESTAMP'] <=  1) ?
                                "" : " i:".$component['CRAWL_TIMESTAMP'];
                            $rewrite .= $pipe2.$disjunct_string." w:".
                                ($component['WEIGHT'] * $base_weight).$stamp;
                            $pipe2 = ' | ';
                        }
                    }
                }
                $num_results = (isset($fragment['RESULT_BOUND']) &&
                    $fragment['RESULT_BOUND'] > 1) ?
                    $fragment['RESULT_BOUND'] : 1;
                $rewrite .= " #$num_results# ";
            }
        }
        return $rewrite;
    }
    /**
     * Given a query phrase, returns formatted document summaries of the
     * documents that match the phrase.
     *
     * @param string $input_phrase the phrase to try to match
     * @param int $low  return results beginning with the $low document
     * @param int $results_per_page  how many results to return
     * @param bool $format  whether to highlight in the returned summaries the
     *     matched text
     * @param array $filter an array of hashes of domains to filter from
     *     results
     * @param bool $use_cache_if_allowed if true and USE_CACHE is true then
     *     an attempt will be made to look up the results in either
     *     the file cache or memcache. Otherwise, items will be recomputed
     *     and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *     no grouping done on data also no summaries returned (only lookup
     *     info), $raw > 1 return summaries but no grouping
     * @param array $queue_servers a list of urls of yioop machines which might
     *     be used during lookup
     * @param bool $guess_semantics whether to do query rewriting before lookup
     * @param int $save_timestamp if this timestamp is nonzero, then save
     *     iterate position, so can resume on future queries that make
     *     use of the timestamp
     * @param bool $limit_news if true the number of media:news items to
     *     allow in search results is limited to WordIterator::LIMIT_NEWS_COUNT
     *
     * @return array an array of summary data
     */
    public function getPhrasePageResults(
        $input_phrase, $low = 0, $results_per_page = C\NUM_RESULTS_PER_PAGE,
        $format = true, $filter = null, $use_cache_if_allowed = true,
        $raw = 0, $queue_servers = [], $guess_semantics = true,
        $save_timestamp = 0, $limit_news = true)
    {
        if (C\QUERY_STATISTICS) {
            $indent= "&nbsp;&nbsp;";
            $in2 = $indent . $indent;
            $in3 = $in2 . $indent;
            $prs_cnt = 0;
            $dis_cnt = 0;
            $this->query_info = [];
            $this->query_info['QUERY'] =
                "<b>PHRASE QUERY</b>: ".$input_phrase."<br />";
            $start_time = microtime(true);
        }
        $results = null;
        $word_structs = [];
        /*
            this is a quick and dirty parsing and will usually work,
            exceptions would be # or | in quotes or if someone tried
            to escape |.

            First we split into presentation elements then we split by
            disjuncts
        */
        $presentation_parts = preg_split('/#(\d)+#/',
            $input_phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
        $count = 0;
        $presentation_parts = array_chunk($presentation_parts, 2);
        $num_parts = count($presentation_parts);
        $query_parts = [];
        $last_part = null;
        for ($i = 0;  $i < $num_parts ; $i++) {
            if (isset($presentation_parts[$i][0])  &&
                ($trimmed = trim($presentation_parts[$i][0])) != "") {
                $to_return = (isset($presentation_parts[$i][1])) ?
                    $presentation_parts[$i][1]: 1;
                $query_parts[$trimmed][] =
                    [$count, $to_return];
                $last_part = $trimmed;
                if (isset($presentation_parts[$i][1])) {
                    $count += $presentation_parts[$i][1];
                } else {
                    $count ++;
                }
            }
        }
        $results_high = $low + $results_per_page;
        $num_phrases = count($query_parts);
        if ($num_phrases > 0) {
            $num_last_parts = count($query_parts[$last_part]);
            if ($query_parts[$last_part][$num_last_parts - 1][0] +
                $query_parts[$last_part][$num_last_parts - 1][1] < $low) {
                $query_parts[$last_part][$num_last_parts - 1][1] =
                    $results_high;
            }
        }
        $qpart = 0;
        if (is_string($save_timestamp)) {
            $save_parts = explode("-", $save_timestamp);
            if (isset($save_parts[1])) {
                $qpart = intval($save_parts[1]);
                $save_timestamp = intval($save_parts[0]);
            }
        }
        $orig_stimestamp = $save_timestamp;
        $network = false;
        if ($queue_servers != [] &&
            !$this->isSingleLocalhost($queue_servers)) {
            $network = true;
        }
        foreach ($query_parts as $phrase => $pre_result_bounds) {
            $phrase_high = $pre_result_bounds[0][1];
            $result_bounds = [];
            $start_flag = false;
            $num_bounds = 0;
            foreach ($pre_result_bounds as $bound) {
                if ($bound[0] > $results_high) {
                    break;
                }
                //rest of presentation after what we'll return so break
                $phrase_high =  $bound[0] + $bound[1];
                if ($phrase_high < $low) {
                    continue;
                }
                // this part of presentation is before what we'll return so skip
                $result_bounds[] = $bound;
                $num_bounds++;
            }
            if ($num_bounds == 0) {
                continue;
            }
            $is_last_part = ($phrase == $last_part);
            if ($is_last_part &&
                $result_bounds[$num_bounds - 1][0] +
                $result_bounds[$num_bounds - 1][1] < $results_high) {
                $result_bounds[$num_bounds - 1][1] = $results_high -
                    $result_bounds[$num_bounds - 1][0];
            }
            $phrase_num = max(min($phrase_high, $results_high), $results_high) -
                $low;
            $word_structs = [];
            $format_words = [];
            if (C\QUERY_STATISTICS) {
                $this->query_info['QUERY'] .= $indent .
                    "<b>Presentation $prs_cnt:</b><br />";
                $this->query_info['QUERY'] .= "$in2<i>Low</i>:".
                    $result_bounds[0][0]."<br />";
                $this->query_info['QUERY'] .= $in2 .
                    "<i>High</i>: ".$result_bounds[0][1]."<br />";
                $prs_cnt++;
            }
            $cache_results = false;
            if (C\nsdefined('USE_CACHE') && C\USE_CACHE &&
                $save_timestamp == "" && $use_cache_if_allowed && !$network) {
                $cache_results = self::$cache->get($phrase . $this->index_name);
                if (C\QUERY_STATISTICS) {
                    $this->query_info['QUERY'] .=
                        "$in2<b>Parse done by Cache Lookup</b><br />";
                }
            }
            if ($cache_results) {
                list($word_structs, $format_words) = $cache_results;
            } else {
                if ($guess_semantics) {
                    $disjunct_phrases = explode("|", $phrase);
                    $repeat_check = [];
                    $phrase = "";
                    $delim = " ";
                    foreach ($disjunct_phrases as $disjunct) {
                        $check = trim($disjunct);
                        if (isset($repeat_check[$check])) {
                            continue;
                        }
                        $repeat_check[$check] = true;
                        $phrase .= $delim . $this->guessSemantics(" ".
                            $disjunct);
                        $delim = " | ";
                    }
                    $repeat_check = [];
                }
                if (!$network) {
                    $disjunct_phrases = explode("|", $phrase);
                    foreach ($disjunct_phrases as $disjunct) {
                        if (C\QUERY_STATISTICS) {
                            $this->query_info['QUERY']
                                .="$in2<b>Disjunct $dis_cnt:" . "</b><br />";
                            $dis_cnt++;
                        }
                        list($word_struct, $format_words) =
                            $this->parseWordStructConjunctiveQuery($disjunct);
                        if ($word_struct != null) {
                            $word_structs[] = $word_struct;
                        }
                    }
                    if (C\nsdefined('USE_CACHE') && C\USE_CACHE &&
                        $save_timestamp == "") {
                        self::$cache->set($phrase.$this->index_name,
                            [$word_structs, $format_words]);
                    }
                }
            }
            if (C\QUERY_STATISTICS) {
                $this->query_info['QUERY'] .=
                    "$in2<b>Presentation Parse time</b>: " .
                    L\changeInMicrotime($start_time)."<br />";
            }
            if ($orig_stimestamp > 0) {
                $save_timestamp_name = "$orig_stimestamp-$qpart";
            } else {
                $save_timestamp_name = "";
            }
            $out_results = $this->getSummariesByHash($word_structs,
                $low, $phrase_num, $filter, $use_cache_if_allowed, $raw,
                $queue_servers, $phrase, $save_timestamp_name,
                $limit_news);
            if (isset($out_results['PAGES']) &&
                count($out_results['PAGES']) != 0) {
                $out_count = 0;
                foreach ($result_bounds as $bound) {
                    for ($i = $bound[0];
                        $i < min($bound[0] + $bound[1], $results_high);
                        $i++) {
                        if (isset($out_results['PAGES'][$out_count])) {
                            $results['PAGES'][$i] =
                                $out_results['PAGES'][$out_count];
                            $out_count++;
                        }
                    }
                }
                if ($is_last_part && isset($out_results['TOTAL_ROWS'])) {
                    $total_rows = $out_results['TOTAL_ROWS'];
                }
            }
            $qpart++;
        }
        if (C\QUERY_STATISTICS) {
            $format_time = microtime(true);
        }
        if (isset($out_results['SAVE_POINT'])) {
            /*
              out_result of last used to back-fill earlier ones that are done
              so on crawl mix archive crawls only look at last
             */
            $results['SAVE_POINT'] = $out_results['SAVE_POINT'];
        }
        if (isset($results['PAGES'])) {
            ksort($results['PAGES']);
            $results["PAGES"] = array_values($results["PAGES"]);
        }
        if (isset($out_results['HARD_QUERY'])) {
            $results['HARD_QUERY'] = $out_results['HARD_QUERY'];
        }
        if (count($results) == 0) {
            $results = null;
        }
        if ($results == null) {
            $total_rows = 0;
            $results['TOTAL_ROWS'] = 0;
        }
        if (isset($total_rows)) {
            $results['TOTAL_ROWS'] = $total_rows;
        } elseif (isset($results['PAGES'])) {
            $results['TOTAL_ROWS'] = count($results['PAGES']);
        }

        if ($format) {
            if (count($format_words) == 0) {
                $format_words = null;
            }
        } else {
            $format_words = null;
        }
        $description_length = self::DEFAULT_DESCRIPTION_LENGTH;
        if (isset($this->additional_meta_words) &&
            is_array($this->additional_meta_words)) {
            foreach ($this->additional_meta_words as $meta_word => $length) {
                $pattern = "/$meta_word/";
                if (preg_match($pattern, $input_phrase)) {
                    $description_length = $length;
                    break; // only match the first found
                }
            }
        }
        if ($raw == 0 && isset($results['TOTAL_ROWS']) &&
            $results['TOTAL_ROWS'] > 0) {
            $output = $this->formatPageResults($results, $format_words,
                $description_length);
            if (isset($out_results['THESAURUS_VARIANTS'])) {
                $output['THESAURUS_VARIANTS'] =
                    $out_results['THESAURUS_VARIANTS'];
            }
        } else {
            $output = $results;
        }
        if (C\QUERY_STATISTICS) {
            $this->query_info['QUERY'] .= "<b>Format Time</b>: ".
                L\changeInMicrotime($format_time)."<br />";
            $this->query_info['ELAPSED_TIME'] =
                L\changeInMicrotime($start_time);
            $this->db->total_time += $this->query_info['ELAPSED_TIME'];
            $this->db->query_log[] = $this->query_info;
        }
        return $output;
    }
    /**
     * Parses from a string phrase representing a conjunctive query, a struct
     * consisting of the words keys searched for, the allowed and disallowed
     * phrases, the weight that should be put on these query results, and
     * which archive to use.
     *
     * @param string& $phrase string to extract struct from, if the phrase
     *  semantics is guessed or an if condition is processed the value of
     *  phrase will be altered. (Helps for feeding to network queries)
     * @return array struct representing the conjunctive query
     */
    public function parseWordStructConjunctiveQuery(&$phrase)
    {
        $query = $phrase;
        $indent= "&nbsp;&nbsp;";
        $in2 = $indent . $indent;
        $in3 = $in2 . $indent;
        $in4 = $in2. $in2;
        $phrase = " ".$phrase;
        $phrase = $this->parseIfConditions($phrase);
        $phrase_string = $phrase;
        list($found_metas, $found_materialized_metas, $disallow_phrases,
            $phrase_string, $query_string, $index_name, $weight) =
            $this->extractMetaWordInfo($phrase);
        /*
            we search using the stemmed/char-grammed words, but we format
            snippets in the results by bolding either
         */
        $query_words = explode(" ", $query_string); //not stemmed
        if ($this->program_indicator) {
            $query_string = $query;
            $this->program_indicator = false;
        }
        $locale_tag = L\guessLocaleFromString($query_string);
        $quote_state = false;
        $phrase_parts = explode('"', $phrase_string);
        $base_words = [];
        $num_words = 0;
        $quote_positions = [];
        foreach ($phrase_parts as $phrase_part) {
            if (trim($phrase_part) == "") {
                $quote_state = ($quote_state) ? false : true;
                continue;
            }
            /*still use original phrase string here to handle
               acronyms abbreviations and the like that use periods */
            if ($quote_state) {
                $sub_parts = explode('*', $phrase_part);
                $first_part = true;
                $quote_position = [];
                foreach ($sub_parts as $sub_part) {
                    if (!$first_part) {
                        $quote_position["*$num_words"] = "*";
                    }
                    $new_words = PhraseParser::extractPhrases(
                        $sub_part, $locale_tag, $index_name, true);
                    $base_words = array_merge($base_words, $new_words);
                    foreach ($new_words as $new_word) {
                        $len = substr_count($new_word, " ") + 1;
                        $quote_position[$num_words] = $len;
                        $num_words++;
                    }
                    $first_part = false;
                }
                $quote_positions[] = $quote_position;
            } else {
                $new_words =
                    PhraseParser::extractPhrases($phrase_part, $locale_tag,
                         $index_name);
                if (strpos($new_words[0], " ") > 0 &&
                    $found_materialized_metas == []) {
                    array_pop($new_words);
                }
                $base_words = array_merge($base_words, $new_words);
            }
            $num_words = count($base_words);
            $quote_state = ($quote_state) ? false : true;
        }
        //stemmed, if have stemmer
        $index_version = IndexManager::getVersion($index_name);
        $add_metas = $found_metas;
        $immaterial_metas = array_diff(
            $found_metas, $found_materialized_metas);
        if (count($immaterial_metas) > 0 && $index_version > 0) {
            $add_metas = $immaterial_metas;
        }
        $words = array_merge($base_words, $add_metas);
        if (count($words) == 0 && count($disallow_phrases) > 0) {
            $words[] = "site:any";
        }
        if (C\QUERY_STATISTICS) {
            if (!isset($this->query_info['QUERY'])) {
                $this->query_info['QUERY'] = "";
            }
            $this->query_info['QUERY'] .= "$in3<i>Index</i>: ".
                $index_name."<br />";
            $this->query_info['QUERY'] .= "$in3<i>LocaleTag</i>: ".
                $locale_tag."<br />";
            $this->query_info['QUERY'] .=
                "$in3<i>Stemmed/Char-grammed Words</i>:<br />";
            foreach ($base_words as $word) {
                $this->query_info['QUERY'] .= "$in4$word<br />";
            }
            $this->query_info['QUERY'] .= "$in3<i>Meta Words</i>:<br />";
            foreach ($found_metas as $word) {
                $this->query_info['QUERY'] .= "$in4$word<br />";
            }
            $this->query_info['QUERY'] .= "$in3<i>Quoted Word Locs</i>:<br />";
            foreach ($quote_positions as $quote_position) {
                $this->query_info['QUERY'] .= "$in4(";
                $comma = "";
                foreach ($quote_position as $pos => $len) {
                    $this->query_info['QUERY'] .= "$comma $pos => $len";
                    $comma = ",";
                }
                $this->query_info['QUERY'] .= ")<br />";
            }
        }
        if (isset($words) && count($words) == 1 &&
            count($disallow_phrases) < 1 && !strpos($words[0], " ")) {
            $phrase_string = $words[0];
            if ($index_version == 0) {
                $tmp_hash = L\allCrawlHashPaths($phrase_string);
                $tmp_hash = (is_array($tmp_hash)) ? $tmp_hash : [$tmp_hash];
                $phrase_hash = array_merge([$tmp_hash],
                    [L\crawlHash($phrase_string)]);
            } else {
                if ($found_materialized_metas == []) {
                    $phrase_hash = L\allCrawlHashPaths($phrase_string);
                } else {
                    $phrase_hash = L\allCrawlHashPaths($phrase_string,
                        $found_materialized_metas,
                        PhraseParser::$materialized_metas);
                }
            }
            $word_struct = ["KEYS" => [$phrase_hash],
                "QUOTE_POSITIONS" => null, "DISALLOW_KEYS" => [],
                "WEIGHT" => $weight, "INDEX_NAME" => $index_name,
            ];
        } else {
            //get a raw list of words and their hashes
            $hashes = [];
            $metas_accounted = false;
            $materialized_metas = [];
            $meta_keys = [];
            foreach ($words as $word) {
                if (!$metas_accounted && substr_count($word, " ") == 0
                    && !in_array($word, $found_metas)) {
                    $metas_accounted = true;
                    $materialized_metas = $found_materialized_metas;
                }
                $tmp_hash = L\allCrawlHashPaths($word, $materialized_metas,
                    PhraseParser::$materialized_metas);
                if ($index_version == 0) {
                    $tmp_hash = (is_array($tmp_hash)) ? $tmp_hash : [$tmp_hash];
                    $test =  array_merge($tmp_hash, [L\crawlHash($word)]);
                } else {
                    if(in_array($word, $found_materialized_metas) &&
                        !$metas_accounted) {
                        $meta_keys[] = $tmp_hash;
                    } else {
                        $word_keys[] = $tmp_hash;
                    }
                }
            }
            if(!$metas_accounted) {
                $word_keys = array_merge($word_keys, $meta_keys);
            }
            if (!isset($word_keys) || count($word_keys) == 0) {
                $word_keys = null;
                $word_struct = null;
            }
            $disallow_keys = [];
            $num_disallow_keys = min(C\MAX_QUERY_TERMS,
                count($disallow_phrases));
            if ($num_disallow_keys > 0 && C\QUERY_STATISTICS) {
                $this->query_info['QUERY'] .= "$in3<i>Disallowed Words</i>:".
                    "<br />";
            }
            for ($i = 0; $i < $num_disallow_keys; $i++) {
                // check if disallowed is a meta word and stem or not stem
                if (mb_strstr($disallow_phrases[$i], ':') === false) {
                    $disallow_stem = PhraseParser::extractPhrases(
                        $disallow_phrases[$i], L\getLocaleTag());
                            //stemmed
                } else {
                    $disallow_stem[0] = $disallow_phrases[$i];
                }
                if (C\QUERY_STATISTICS) {
                    $this->query_info['QUERY'] .= "$in4{$disallow_stem[0]}".
                        "<br />";
                }
                $disallow_keys[] = L\crawlHashWord($disallow_stem[0]);
                if ($index_version == 0) {
                    $disallow_keys[] = L\crawlHash($word);
                }
            }
            if ($word_keys !== null) {
                $word_struct = ["KEYS" => $word_keys,
                    "QUOTE_POSITIONS" => $quote_positions,
                    "DISALLOW_KEYS" => $disallow_keys,
                    "WEIGHT" => $weight,
                    "INDEX_NAME" => $index_name
                ];
            }
        }
        $pre_format_words = [];
        foreach ($base_words as $base_word) {
            $pre_format_words = array_merge($pre_format_words,
                explode(" * ", $base_word));
        }
        $pre_format_words = array_values(array_unique(
            array_merge($query_words, $pre_format_words)));
        $format_words = [];
        $count = count($pre_format_words);
        for ($i = 0; $i < $count; $i++) {
            $flag = true;
            if ($pre_format_words[$i] == "") {
                continue;
            }
            for ($j = 0; $j < $count; $j++) {
                if ($j == $i) {
                    continue;
                }
                $hay = mb_strtolower($pre_format_words[$j]);
                $needle = mb_strtolower($pre_format_words[$i]);
                if ($hay == $needle && $j > $i) {
                    continue;
                }
                if (mb_strstr($hay, $needle)) {
                    $flag = false;
                    break;
                }
            }
            if ($flag) {
                $format_words[] = $pre_format_words[$i];
            }
        }
        //clear index manager cache so mask stuff isn't cached *** revisit
        IndexManager::$dictionary = [];
        return [$word_struct, $format_words];
    }
    /**
     * Given a query string, this method extracts meta words, which of these are
     * "materialized" (i.e., should be encoded as part of word ids),
     * disallowed phrases, the query string after meta words removed
     * and ampersand substitution applied, the query string with meta words
     * but apersand substitution applied, the index and the weights found
     * as part of the query string.
     *
     * @param string $phrase the query string
     * @return array containing items listed above in the description of this
     *     method
     */
    public function extractMetaWordInfo($phrase)
    {
        $index_name = $this->index_name;
        $weight = 1;
        $found_metas = [];
        $found_materialized_metas = [];
        $disallow_phrases = [];
        $phrase_string = $phrase;
        $phrase_string = str_replace("&", "&amp;", $phrase_string);
        $meta_words = PhraseParser::$meta_words_list;
        if (isset($this->additional_meta_words)) {
            $meta_words = array_merge($meta_words, array_keys(
                $this->additional_meta_words));
        }
        $materialized_match_conflict = false;
        foreach ($meta_words as $meta_word) {
            $pattern = "/(\s)($meta_word(\S)+)/";
            preg_match_all($pattern, $phrase, $matches);
            if (!in_array($meta_word, ['i:', 'index:', 'w:', 'weight:', '\-'])) {
                $matches = $matches[2];
                $found_metas = array_merge($found_metas, $matches);
                if (in_array($meta_word, PhraseParser::$materialized_metas)) {
                    $seen_matches = [];
                    $seen_match_count = 0;
                    foreach ($matches as $pre_material_match) {
                        $match_kinds = explode(":", $pre_material_match);
                        if (!in_array($match_kinds[1], ["all"]) &&
                            !isset($match_kinds[2])) {
                            $found_materialized_metas[] = $pre_material_match;
                            if ($seen_match_count > 0 &&
                                !isset($seen_matches[$pre_material_match])) {
                                $materialized_match_conflict = true;
                                break 2;
                            }
                            $seen_matches[$pre_material_match] = true;
                            $seen_match_count++;
                        }
                    }
                }
            } elseif ($meta_word == '\-') {
                if (count($matches[0]) > 0) {
                    foreach ($matches[2] as $disallowed) {
                        $disallow_phrases[] = substr($disallowed, 1);
                    }
                }
            } elseif ($meta_word == 'i:' || $meta_word == 'index:') {
                if (isset($matches[2][0])) {
                    $index_name = substr($matches[2][0], strlen($meta_word));
                }
            } elseif ($meta_word == 'w:' || $meta_word == 'weight:') {
                if (isset($matches[2][0])) {
                    $weight = substr($matches[2][0], strlen($meta_word));
                }
            }
            $phrase_string = preg_replace($pattern, "", $phrase_string);
        }
        if ($materialized_match_conflict) {
            $found_metas = [];
            $found_materialized_metas = [];
            $disallow_phrases = [];
            $phrase_string = "";
        }
        $found_metas = array_unique($found_metas);
        $found_materialized_metas = array_unique($found_materialized_metas);
        if(empty(trim($phrase_string)) && count($found_metas) == 2 
            && (in_array("site:doc", $found_metas)
            || in_array("site:any", $found_metas))) {
            /*site:doc and site:any doesn't work with materialized metas by
              themselves */
            array_pop($found_materialized_metas);
        }
        $disallow_phrases = array_unique($disallow_phrases);
        $phrase_string = mb_ereg_replace("&amp;", "_and_", $phrase_string);
        $query_string = mb_ereg_replace(C\PUNCT, " ", $phrase_string);
        $query_string = preg_replace("/(\s)+/", " ", $query_string);
        $query_string = mb_ereg_replace('_and_', '&', $query_string);
        $phrase_string = mb_ereg_replace('_and_', '&', $phrase_string);
        return [$found_metas, $found_materialized_metas,
            $disallow_phrases, $phrase_string, $query_string, $index_name,
            $weight];
    }
    /**
     * Ideally, this function tries to guess from the query what the
     * user is looking for. For now, we are just doing simple things like
     * when a query term is a url and rewriting it to the appropriate meta
     * meta word.
     *
     * @param string $phrase input query to guess semantics of
     * @return string a phrase that more closely matches the intentions of the
     *     query.
     */
    public function guessSemantics($phrase)
    {
        $domain_suffixes = [".com", ".net", ".edu", ".org", ".gov",
            ".mil", ".ca", ".uk", ".fr", ".ly"];
        $len = mb_strlen(trim($phrase));
        if ($len > 4) {
            foreach ($domain_suffixes as $suffix) {
                $phrase = $this->endMatch($phrase, $suffix, "site:", "",
                    [":", "@"]);
            }
            $phrase = $this->beginMatch($phrase, "www.", "site:www.");
            $phrase = $this->beginMatch($phrase, "http:", "site:http:");
            $phrase = $this->beginMatch($phrase, "info:", "info:http://", "/",
                ["/"]);
            $phrase = $this->beginMatch($phrase, "info:", "info:http://", "",
                ["http"]);
        }
        $tag = L\guessLocaleFromString($phrase);
        if (isset($this->programming_language_map[$tag])) {
            $this->program_indicator = true;
        }
        $main_tag = substr($tag, 0, 2);
        if ($len == 1) {
            $letter = "";
            switch ($main_tag) {
                case 'ar':
                    $letter = "سالة";
                    break;
                case 'de':
                    $letter = "Buchstabe";
                    break;
                case 'en':
                    $letter = "letter";
                    break;
                case 'es':
                    $letter = "letra";
                    break;
                case 'fa':
                    $letter = "نامه";
                    break;
                case 'fr':
                    $letter = "lettre";
                    break;
                case 'it':
                    $letter = "lettera";
                    break;
                case 'po':
                    $letter = "literą";
                    break;
                case 'pt':
                    $letter = "letra";
                    break;
                case 'tr':
                    $letter = "harfi";
                    break;
                case 'ru':
                    $letter = "буква";
                    break;
                case 'vi':
                    $letter = "thư";
                    break;
            }
            $phrase = $letter." ".$phrase."|".$phrase;
        }
        $tokenizer_name = C\NS_LOCALE . "$tag\\resources\\" .
            ucfirst($main_tag) . "Tokenizer";
        if (class_exists($tokenizer_name)) {
            $tmp_object = new $tokenizer_name();
            if (isset($tmp_object->semantic_rewrites)) {
                $rewrites = $tmp_object->semantic_rewrites;
                $tmp = trim($phrase);
                if (isset($rewrites[$tmp])) {
                    $phrase = $rewrites[$tmp];
                }
            }
        }
        return $phrase;
    }
    /**
     * Matches terms (non white-char strings) in the language $lang_tag in
     * $phrase that begin with  $start_with and don't contain  $not_contain,
     * replaces $start_with with $new_prefix and adds $suffix to the end
     *
     * @param string $phrase string to look for terms in
     * @param string $start_with what we're looking to see if term begins with
     * @param string $new_prefix what to change $start_with to
     * @param string $suffix what to tack on to the end of the term if there is
     *     a match
     * @param string $not_contains string match is not allowed to contain
     * @param string $lang_tag what language the phrase must be in for the rule
     *     to apply
     *
     * @return string $phrase after modifications have been made
     */
    public function beginMatch($phrase, $start_with, $new_prefix, $suffix = "",
        $not_contains = [], $lang_tag = "en-US")
    {
        $phrase .= " ";
        $quote_start_with = preg_quote($start_with, "/");
        $pattern = "/(\s)($quote_start_with(\S)+)/";
        $start_pos = strlen($start_with);
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, "", $phrase);
        foreach ($matches as $match) {
            $tag = L\guessLocaleFromString($match, $lang_tag, 10);
            $not_check = true;
            foreach ($not_contains as $not_contain) {
                if (strstr($match, $not_contain)) {
                    $not_check = false;
                    break;
                }
            }
            if ($tag == $lang_tag && $not_check) {
                $body = substr($match, $start_pos);
                $result_phrase .= " ".$new_prefix.$body.$suffix;
            } else {
                $result_phrase .= " ".$match;
            }
        }
        return $result_phrase;
    }
    /**
     * Matches terms (non white-char strings) in the language $lang_tag in
     * $phrase that end with $end_with and don't contain  $not_contain,
     * replaces $end_with with $new_suffix (if not empty) and adds $prefix to
     * the beginning
     *
     * @param string $phrase string to look for terms in
     * @param string $end_with what we're looking to see if term ends with
     * @param string $prefix what to tack on to the start if there is
     *     a match
     * @param string $new_suffix what to change $end_with to
     * @param string $not_contains string match is not allowed to contain
     * @param string $lang_tag what language the phrase must be in for the rule
     *     to apply
     *
     * @return string $phrase after modifications have been made
     */
    public function endMatch($phrase, $end_with, $prefix, $new_suffix = "",
        $not_contains = [], $lang_tag = "en-US")
    {
        $phrase .= " ";
        $quote_end_with = preg_quote($end_with, "/");
        $pattern = "/(\s)((\S)+$quote_end_with)(\s)/";
        $end_len = strlen($end_with);
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, " ", $phrase);
        foreach ($matches as $match) {
            $tag = L\guessLocaleFromString($match, $lang_tag, 10);
            $not_check = true;
            foreach ($not_contains as $not_contain) {
                if (strstr($match, $not_contain)) {
                    $not_check = false;
                    break;
                }
            }
            if ($tag == $lang_tag && $not_check) {
                if ($new_suffix == "") {
                    $body = $match;
                } else {
                    $body = substr($match, 0, -$end_len);
                }
                $result_phrase .= " $prefix".$body.$new_suffix;
            } else {
                $result_phrase .= " ".$match;
            }
        }
        return $result_phrase;
    }
    /**
     * Evaluates any if: conditional meta-words in the query string to
     * calculate a new query string.
     *
     * @param string $phrase original query string
     * @return string query string after if: meta words have been evaluated
     */
    public function parseIfConditions($phrase)
    {
        $cond_token = "if:";
        $pattern = "/(\s)($cond_token(\S)+)/";
        preg_match_all($pattern, $phrase, $matches);
        $matches = $matches[2];
        $result_phrase = preg_replace($pattern, "", $phrase);
        foreach ($matches as $match) {
            $match = substr($match, strlen($cond_token));
            $match_parts = explode("!", $match);
            if (count($match_parts) < 2) {
                continue;
            }
            if (stristr($result_phrase, $match_parts[0]) !== false) {
                $result_phrase .= " ".str_replace("+", " ", $match_parts[1]);
            } elseif (isset($match_parts[2])) {
                $result_phrase .= " ".str_replace("+", " ", $match_parts[2]);
            }
        }
        return $result_phrase;
    }
    /**
     * Gets doc summaries of documents containing given words and meeting the
     * additional provided criteria
     * @param array $word_structs an array of word_structs. Here a word_struct
     *     is an associative array with at least the following fields
     *     KEYS -- an array of word keys
     *     QUOTE_POSITIONS -- an array of positions of words that appeared in
     *         quotes (so need to be matched exactly)
     *     DISALLOW_PHRASES -- an array of words the document must not contain
     *     WEIGHT -- a weight to multiple scores returned from this iterator by
     *     INDEX_NAME -- an index timestamp to get results from
     * @param int $limit number of first document in order to return
     * @param int $num number of documents to return summaries of
     * @param array& $filter an array of hashes of domains to filter from
     *     results
     * @param bool $use_cache_if_allowed if true and USE_CACHE is true then
     *     an attempt will be made to look up the results in either
     *     the file cache or memcache. Otherwise, items will be recomputed
     *     and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw > 0)
     *     no grouping done on data. if ($raw == 1) no lookups of summaries
     *     done
     * @param array $queue_servers a list of urls of yioop machines which might
     *     be used during lookup
     * @param string $original_query if set, the original query that corresponds
     *     to $word_structs
     * @param string $save_timestamp_name if this timestamp is not empty, then
     *     save iterate position, so can resume on future queries that make
     *     use of the timestamp. If used then $limit ignored and get next $num
     *     docs after $save_timestamp 's previous iterate position.
     * @param bool $limit_news if true the number of media:news items to
     *     allow in search results is limited to WordIterator::LIMIT_NEWS_COUNT
     *
     * @return array document summaries
     */
    public function getSummariesByHash($word_structs, $limit, $num, &$filter,
        $use_cache_if_allowed = true, $raw = 0, $queue_servers = [],
        $original_query = "", $save_timestamp_name = "",
        $limit_news = true)
    {
        $indent= "&nbsp;&nbsp;";
        $in2 = $indent . $indent;
        $in3 = $in2 . $indent;
        $in4 = $in2. $in2;
        if (C\QUERY_STATISTICS) {
            $lookup_time = microtime(true);
        }
        $use_proximity = false;
        $time = time();
        if (count($word_structs) > 1 || (isset($word_structs[0]["KEYS"])
            && count($word_structs[0]["KEYS"]) > 1) ||
            ($word_structs == [] &&
            substr_count($original_query, " ") > 1)) {
            $use_proximity = true;
        }
        if (!isset($filter['time'])) {
            $filter['time'] = 0;
        }
        $filter_time = $filter['time'];
        unset($filter['time']); //iterators don't expect time field
        $pages = [];
        $generation = 0;
        $to_retrieve = ceil(($limit+$num)/self::NUM_CACHE_PAGES) *
            self::NUM_CACHE_PAGES;
        $start_slice = floor(($limit)/self::NUM_CACHE_PAGES) *
            self::NUM_CACHE_PAGES;
        if ($save_timestamp_name != "") {
            $to_retrieve = $num;
            $limit = 0;
            $start_slice = 0;
        }
        if (C\nsdefined('USE_CACHE') && C\USE_CACHE &&
            $save_timestamp_name == "") {
            $mem_tmp = serialize($raw).serialize($word_structs).
                $original_query . $this->index_name;
            $summary_hash = L\crawlHash($mem_tmp.":".$limit.":".$num);
            if ($use_cache_if_allowed) {
                $cache_success = true;
                $results = self::$cache->get($summary_hash);
                if (!isset($results['TIME']) || $filter_time >
                    $results['TIME']) {
                    //if filter has changed since cached, then invalidate cache
                    $results = false;
                }
                if (isset($results['TIME'])) {
                    $cached_time = $time - $results['TIME'];
                } else {
                    $cached_time = $time;
                }
                if ($cached_time > C\MAX_QUERY_CACHE_TIME) {
                    $results = false;
                }
                if (isset($results['PAGES'])) {
                    $close_prefix = C\WORK_DIRECTORY . "/schedules/".
                        self::index_closed_name;
                    $has_changeable_results = false;
                    $seen_times = [];
                    foreach ($results['PAGES'] as $page) {
                        if (!isset($page[self::CRAWL_TIME]) ||
                            in_array($page[self::CRAWL_TIME], $seen_times)) {
                            continue;
                        }
                        $seen_times[] = $page[self::CRAWL_TIME];
                        $current_closed = $close_prefix .
                            $page[self::CRAWL_TIME].".txt";
                        if (!file_exists($current_closed)) {
                            //either feed result or from active crawl
                            $has_changeable_results = true;
                            break;
                        }
                    }
                    if ($has_changeable_results) {
                        if ($cached_time > C\MIN_QUERY_CACHE_TIME) {
                            $results = false;
                        }
                    }
                }
                if (C\QUERY_STATISTICS) {
                    $this->query_info['QUERY'] .=
                        "$in2<b>Cache Lookup Time</b>: ".
                        L\changeInMicrotime($lookup_time)."<br />";
                }
                if ($results !== false) {
                    return $results;
                }
            }
        }
        $old_to_retrieve = $to_retrieve;
        $get_query_time = microtime(true);
        $query_iterator = $this->getQueryIterator($word_structs, $filter, $raw,
            $to_retrieve, $queue_servers, $original_query,
            $save_timestamp_name, $limit_news);
        $get_query_time = L\changeInMicrotime($get_query_time);
        $num_retrieved = 0;
        $pages = [];
        $retrieve_postings_time = microtime(true);
        if (is_object($query_iterator)) {
            while ($num_retrieved < $to_retrieve &&
                is_array($next_docs =
                    $query_iterator->nextDocsWithWord())) {
                $pages += $next_docs;
                $num_retrieved = count($pages);
            }
        }
        $retrieve_postings_time = L\changeInMicrotime($retrieve_postings_time);
        if ($save_timestamp_name != "" && ($queue_servers == [] ||
            $this->isSingleLocalhost($queue_servers))) {
            // used for archive crawls of crawl mixes
            $save_file = C\CRAWL_DIR.'/schedules/'.self::save_point.
                $save_timestamp_name.".txt";
            $iterators = $query_iterator->save_iterators;
            $cnt_iterators = count($iterators);
            $save_point = [];
            for ($i = 0; $i < $cnt_iterators; $i++) {
                $save_point[$i] =
                    $iterators[$i]->currentGenDocOffsetWithWord();
            }
            $results["SAVE_POINT"] = $save_point;
            file_put_contents($save_file, serialize($save_point));
            $this->db->setWorldPermissionsRecursive($save_file);
        }
        $pages = array_values($pages);
        $result_count = count($pages);
        $sort_time = 0;
        if ($raw == 0) {
            // initialize scores
            $sort_start = microtime(true);
            $max_user_ranks = 0;
            for ($i = 0; $i < $result_count; $i++) {
                $pages[$i]["OUT_SCORE"] = 0;
                if (isset($pages[$i][self::USER_RANKS])) {
                    $j = count($pages[$i][self::USER_RANKS]);
                    if ($max_user_ranks < $j) {
                        $max_user_ranks = $j;
                    }
                }
            }
            if ($max_user_ranks > 0) {
                for ($i = 0; $i < $result_count; $i++) {
                    for ($j = 0; $j < $max_user_ranks; $j++) {
                        if (isset($pages[$i][self::USER_RANKS][$j])) {
                            $pages[$i]["USCORE$j"] =
                                $pages[$i][self::USER_RANKS][$j];
                        } else {
                            $pages[$i]["USCORE$j"] = 0;
                        }
                    }
                }
            }
            $subscore_fields = [self::DOC_RANK, self::RELEVANCE];
            if ($use_proximity) {
                $subscore_fields[] = self::PROXIMITY;
            }
            if ($max_user_ranks > 0) {
                for ($j = 0; $j < $max_user_ranks; $j++) {
                    $subscore_fields[] = "USCORE$j";
                }
            }
            $num_fields = count($subscore_fields);
            // Compute Reciprocal Rank Fusion Score
            $alpha = 600/$num_fields;
            if (isset($pages[0])) {
                foreach ($subscore_fields as $field) {
                    L\orderCallback($pages[0], $pages[0], $field);
                    usort($pages, C\NS_LIB . "orderCallback");
                    $score = 0;
                    for ($i = 0; $i < $result_count; $i++) {
                        if ($i > 0) {
                            if ($pages[$i - 1][$field] != $pages[$i][$field]) {
                                $score++;
                            }
                        }
                        $pages[$i]["OUT_SCORE"] += $alpha/(59 + $score);
                    }
                }
                L\orderCallback($pages[0], $pages[0], "OUT_SCORE");
            }
            usort($pages, C\NS_LIB ."orderCallback");
            if ($use_proximity) {
                for ($i = 0; $i < $result_count; $i++) {
                    $pages[$i][self::SCORE] = $pages[$i]["OUT_SCORE"];
                }
            } else {
                for ($i = 0; $i < $result_count; $i++) {
                    $pages[$i][self::PROXIMITY] = 1;
                    $pages[$i][self::SCORE] = $pages[$i]["OUT_SCORE"];
                }
            }
            $sort_time = L\changeInMicrotime($sort_start);
        }
        if ($num_retrieved < $to_retrieve) {
            $results['TOTAL_ROWS'] = $num_retrieved;
        } else {
            $results['TOTAL_ROWS'] =  $query_iterator->num_docs;
            //this is only an approximation
        }
        if ($raw == 1 && $save_timestamp_name == "") {
            $pages = array_slice($pages, $start_slice);
            $pages = array_slice($pages, $limit - $start_slice, $num);
            $results['PAGES'] = & $pages;
            if ($old_to_retrieve != $to_retrieve) {
                $results['HARD_QUERY'] = $old_to_retrieve;
            }
            return $results;
        }
        if (C\QUERY_STATISTICS) {
            $this->query_info['QUERY'] .= "$in2<b>Lookup Offsets Time</b>: ".
                L\changeInMicrotime($lookup_time)."<br />";
            $machine_times = AnalyticsManager::get("MACHINE_TIMES");
            if ($machine_times) {
                $this->query_info['QUERY'] .=
                "$in3<i>Machine Sub-Times</i> (query/total):<br />".
                    $machine_times."<br />";
            }
            $net_times = AnalyticsManager::get("NET_TIMES");
            $max_machine_times = AnalyticsManager::get("MAX_MACHINE_TIMES");
            if ($net_times && $max_machine_times) {
                $this->query_info['QUERY'] .=
                "$in3<i>Network Overhead Sub-Time</i>: ".
                    ($net_times - $max_machine_times)."<br />";
            }
            if ($sort_time) {
                $this->query_info['QUERY'] .=
                    "$in3<i>Get Iterator Sub-Time</i>: " . $get_query_time .
                    "<br />$in3<i>Retrieve Postings Sub-Time</i>: " .
                    $retrieve_postings_time . "<br />" .
                    "$in3<i>Merge-Rank Sub-Time</i>: " .
                    $sort_time."<br />";
            }
            $this->query_info['PLAN'] = $query_iterator->plan();
            $summaries_time = microtime(true);
        }
        $get_pages = array_slice($pages, $limit, $num);
        $to_get_count = count($get_pages);
        $groups_with_docs = false;
        if (preg_match("/\bsite:doc\b/", $original_query)) {
            $groups_with_docs = true;
        }
        $out_pages = [];
        $cur_limit = $limit;
        while (count($out_pages) < $to_get_count && $get_pages) {
            $out_pages = array_merge($out_pages,
                $this->getSummariesFromOffsets($get_pages, $queue_servers,
                $raw, $groups_with_docs));
            if ($save_timestamp_name != "") {
                break;
            }
            $cur_limit += $num;
            $get_pages = array_slice($pages, $cur_limit, $num);
        }
        $out_pages = array_slice($out_pages, 0, $num);
        if (C\QUERY_STATISTICS) {
            $summary_times_string = AnalyticsManager::get("SUMMARY_TIMES");
            if ($summary_times_string) {
                $round_summary_times = unserialize($summary_times_string);
                $summary_delta_time = L\changeInMicrotime($summaries_time);
                $summary_time_info = "$summary_delta_time<br /> $in4";
                $sum_max_time = 0;
                foreach ($round_summary_times as $summary_times) {
                    $i = 0;
                    $max_time = 0;
                    foreach ($summary_times as $summary_time) {
                        $summary_time_info .= "ID_$i: ".
                            number_format($summary_time, 6)."$indent";
                        $max_time = ($summary_time > $max_time) ?
                            $summary_time : $max_time;
                        $i++;
                    }
                    $summary_time_info .= "<br />\n$in4";
                    $sum_max_time += $max_time;
                }
                $net_overhead =  $summary_delta_time - $sum_max_time;
                $summary_time_info .=
                    "<br />$in3<i>Network Overhead Sub-Time</i>: ".
                    $net_overhead;
            } else {
                $summary_time_info = L\changeInMicrotime($summaries_time);
            }
            $this->query_info['QUERY'] .= "$in2<b>Get Summaries Time</b>: ".
                $summary_time_info."<br />";
        }
        $results['PAGES'] = $out_pages;
        $results['TIME'] = time();
        $lang = L\guessLocaleFromString($original_query);
        $tokenizer = PhraseParser::getTokenizer($lang);
        //only use tokenizer if no meta word or disjuncts in query
        if (!preg_match('/(\||\:)/u', $original_query) &&
            $tokenizer && method_exists($tokenizer, "scoredThesaurusMatches")
            && method_exists($tokenizer, "tagPartsOfSpeechPhrase")
            && isset($tokenizer->use_thesaurus)) {
            $results = $this->sortByThesaurusScore($results, $original_query,
                $lang);
            if (!$out_pages) {
                $results['PAGES'] = $out_pages;
            }
        }
        if (C\nsdefined('USE_CACHE') && C\USE_CACHE &&
            $save_timestamp_name == "") {
            self::$cache->set($summary_hash, $results);
        }
        return $results;
    }
    /**
     * If user selects Wordnet feature in page options then only
     * do WordNet processing. Also user has to specify the WordNet directory
     *
     * @param array $results document summaries
     * @param string $original_query the original query that we are computing
     *      results for
     * @param string $lang locale tag of query
     * @return array results document summaries sorted by wordnet score
     */
    public function sortByThesaurusScore($results, $original_query, $lang)
    {
        $summaries = [];
        $pages = $results['PAGES'];
        foreach ($pages as $page) {
            $summaries[] = $page[self::DESCRIPTION];
        }
        $index_name = $this->index_name;
        $phrases = Thesaurus::getSimilarPhrases($original_query, $index_name,
            $lang);
        $results['THESAURUS_VARIANTS'] = $phrases;
        if (!empty($phrases)) {
            $thesaurus_scores = Thesaurus::scorePhrasesSummaries($phrases,
                $summaries);
            //Store the BM25 score for each page in result array
            $num_scores = count($thesaurus_scores);
            for ($i = 0; $i < $num_scores; $i++) {
                $pages[$i][self::THESAURUS_SCORE] = $thesaurus_scores[$i];
                L\orderCallback($pages[$i], $pages[$i], self::THESAURUS_SCORE);
            }
            if (array_sum($thesaurus_scores) != 0) {
                usort($pages, C\NS_LIB . "orderCallback");
            }
            $results['PAGES'] = $pages;
        }
        return $results;
    }
    /**
     * Used to lookup summary info for the pages provided (using their)
     * self::SUMMARY_OFFSET field. If any of the lookup-ed summaries
     * are location's then looks these up in turn. This method handles robot
     * meta tags which might forbid indexing.
     *
     * @param array& $pages of page data without text summaries
     * @param array& $queue_servers array of queue server to find data on
     * @param int $raw only lookup locations if 0
     * @param bool $groups_with_docs whether to return only groups that
     *     contain at least one doc as opposed to a groups with only links
     * @return array pages with summaries added
     */
    public function getSummariesFromOffsets(&$pages, &$queue_servers, $raw,
        $groups_with_docs)
    {
        $lookups = [];
        $page_indexes = [];
        $index = 0;
        foreach ($pages as $page) {
            $key = $page[self::KEY];
            if (isset($page[self::SUMMARY_OFFSET])) {
                if (is_array($page[self::SUMMARY_OFFSET])) {
                    $lookups[$key] = $page[self::SUMMARY_OFFSET];
                } else {
                    $machine_id = (isset($page[self::MACHINE_ID])) ?
                        $page[self::MACHINE_ID] :$this->current_machine;
                    $lookups[$key][] =
                            [$machine_id, $key,
                                $page[self::CRAWL_TIME],
                                $page[self::GENERATION],
                                $page[self::SUMMARY_OFFSET]];
                }
                $page_indexes[$key] = $index;
            }
            $index++;
        }
        $lookup_queue_servers = $queue_servers;
        if ($queue_servers && !in_array(C\NAME_SERVER, $queue_servers)) {
            $lookup_queue_servers[] = C\NAME_SERVER;
                //name server might still have news
        }
        $summaries = $this->getCrawlItems($lookups, $lookup_queue_servers);
        $lookups = [];
        foreach ($summaries as $hash_url => $summary) {
            $lookup_url = false;
            if (isset($summaries[$hash_url][self::LOCATION]) &&
                $summaries[$hash_url][self::LOCATION] != []) {
                $tmp_url = explode(" => ",
                    $summaries[$hash_url][self::DESCRIPTION]);
                if (isset($tmp_url[1])) {
                    $lookup_url = trim($tmp_url[1]);
                }
            } elseif (isset($summaries[$hash_url][self::HASH])) {
                $hash_parts = explode('|', $summaries[$hash_url][self::HASH]);
                if (isset($hash_parts[3])) {
                    $lookup_url = $hash_parts[1];
                }
            }
            if ($lookup_url) {
                $crawl_time = $pages[$page_indexes[$hash_url]][
                    self::CRAWL_TIME];
                $lookups[$hash_url] = [$lookup_url, $crawl_time];
                unset($summaries[$hash_url]);
            }
        }
        $loc_summaries = $this->getCrawlItems($lookups, $lookup_queue_servers);
        if (is_array($loc_summaries)) {
            $summaries = array_merge($summaries, $loc_summaries);
        }
        $out_pages = [];
        $seen_hashes = [];
        foreach ($pages as $page) {
            $key = $page[self::KEY];
            if (isset($summaries[$key]) &&
                (!isset($summaries[$key][self::HASH]) ||
                !in_array($summaries[$key][self::HASH], $seen_hashes))) {
                $summary = & $summaries[$key];
                if (isset($summaries[$key][self::HASH])) {
                    $seen_hashes[] = $summaries[$key][self::HASH];
                }
                $pre_page = array_merge($page, $summary);
                if (isset($pre_page[self::ROBOT_METAS])) {
                    if (!in_array("NOINDEX", $pre_page[self::ROBOT_METAS])
                         &&
                        !in_array("NONE", $pre_page[self::ROBOT_METAS])) {
                        $out_pages[] = $pre_page;
                    }
                } else {
                    $out_pages[] = $pre_page;
                }
            }
        }
        $cnt = count($out_pages);
        if ($groups_with_docs) {
            for ($i = 0; $i < $cnt; $i++) {
                if (empty($out_pages[$i][self::IS_DOC]) ||
                    !empty($out_pages[$i][self::LOCATION])) {
                    unset($out_pages[$i]);
                }
            }
            $out_pages = array_values($out_pages);
        }
        return $out_pages;
    }
    /**
     * Using the supplied $word_structs, contructs an iterator for getting
     * results to a query
     *
     * @param array $word_structs an array of word_structs. Here a word_struct
     *     is an associative array with at least the following fields
     *     KEYS -- an array of word keys
     *     QUOTE_POSITIONS -- an array of positions of words that appreared in
     *         quotes (so need to be matched exactly)
     *     DISALLOW_PHRASES -- an array of words the document must not contain
     *     WEIGHT -- a weight to multiple scores returned from this iterator by
     *     INDEX_NAME -- an index timestamp to get results from
     * @param array& $filter an array of hashes of domains to filter from
     *     results
     *     and then potentially restored in cache
     * @param int $raw ($raw == 0) normal grouping, ($raw == 1)
     *     no grouping done on data also no summaries returned (only lookup
     *     info), $raw > 1 return summaries but no grouping
     * @param int $to_retrieve number of items to retrieve from location in
     *     in interator
     * @param array $queue_servers a list of urls of yioop machines which might
     *     be used during lookup
     * @param string $original_query if set, the orginal query that corresponds
     *     to $word_structs
     * @param string $save_timestamp_name if this timestamp is non empty, then
     *     when making iterator get sub-iterators to advance to gen doc_offset
     *     stored with respect to save_timestamp if exists.
     * @param bool $limit_news if true the number of media:news items to
     *     allow in search results is limited to WordIterator::LIMIT_NEWS_COUNT
     *
     * @return &object an iterator for iterating through results to the
     * query
     */
    public function getQueryIterator($word_structs, &$filter, $raw,
        &$to_retrieve, $queue_servers = [], $original_query = "",
        $save_timestamp_name="", $limit_news = true)
    {
        $iterators = [];
        $total_iterators = 0;
        $network_flag = false;
        $min_group_flag = false;
        $min_group_override = false;
        if ($queue_servers != [] &&
            !$this->isSingleLocalhost($queue_servers)) {
            $network_flag = true;
            $total_iterators = 1;
            if (!in_array(C\NAME_SERVER, $queue_servers)) {
                $queue_servers[] = C\NAME_SERVER;
                    //name server might still have news
            }
            $num_servers = count($queue_servers);
            if ((!isset($this->index_name) || !$this->index_name) &&
                isset($word_structs[0]["INDEX_NAME"])) {
                $index_name = $word_structs[0]["INDEX_NAME"];
            } else {
                $index_name = $this->index_name;
            }
            $iterators[0] = new I\NetworkIterator($original_query,
                $queue_servers, $index_name, $filter, $save_timestamp_name,
                $limit_news);
        }
        if (!$network_flag) {
            $doc_iterate_hashes = [substr(L\crawlHashWord("site:any"), 0, 9),
                substr(L\crawlHash("site:any"), 0, 9),
                substr(L\crawlHashWord("site:doc"), 0, 9),
                substr(L\crawlHash("site:doc"), 0, 9)];
            if ($save_timestamp_name != "") {
                // used for archive crawls of crawl mixes
                $save_file = C\CRAWL_DIR.'/schedules/'.self::save_point.
                    $save_timestamp_name.".txt";
                if (file_exists($save_file)) {
                    $save_point =
                        unserialize(file_get_contents($save_file));
                }
                $save_count = 0;
            }
            foreach ($word_structs as $word_struct) {
                if (!is_array($word_struct)) {
                    continue;
                }
                $word_keys = $word_struct["KEYS"];
                $distinct_word_keys = [];
                $seen_keys = [];
                foreach ($word_keys as $wkey) {
                    if (is_string($wkey) || is_string($wkey[0])) {
                        $tmp_key = is_string($wkey) ? $wkey : $wkey[0];
                        if (!isset($seen_keys[$tmp_key])) {
                            $seen_keys[$tmp_key] = true;
                            $distinct_word_keys[] = $wkey;
                        }
                    } else {
                        $distinct_word_keys[] = $wkey;
                    }
                }
                $quote_positions = $word_struct["QUOTE_POSITIONS"];
                $disallow_keys = $word_struct["DISALLOW_KEYS"];
                $index_name = $word_struct["INDEX_NAME"];
                $weight = $word_struct["WEIGHT"];
                $num_word_keys = count($word_keys);
                $total_iterators = count($distinct_word_keys);
                $word_iterators = [];
                $word_iterator_map = [];
                if ($num_word_keys < 1) {
                    continue;
                }
                $sum = 0;
                for ($i = 0; $i < $total_iterators; $i++) {
                    $current_key = (is_string($distinct_word_keys[$i])) ?
                        $distinct_word_keys[$i] : (is_string(
                        $distinct_word_keys[$i][0]) ? $distinct_word_keys[$i][0]
                        : $distinct_word_keys[$i][0][0]);
                    if (!is_string($current_key)) {
                        $current_key = $current_key[0];
                    }
                    if (in_array(substr($current_key, 0, 9),
                        $doc_iterate_hashes)) {
                        $word_iterators[$i] = new I\DocIterator(
                            $index_name, $filter, $to_retrieve);
                        $min_group_override = true;
                    } else {
                        //can happen if exact phrase search suffix approach used
                        if (isset($distinct_word_keys[$i][0][0]) &&
                            is_array($distinct_word_keys[$i][0][0])) {
                            $distinct_keys = [
                                $distinct_word_keys[$i][0][1]];
                        } elseif (isset($distinct_word_keys[$i][0]) &&
                            is_array($distinct_word_keys[$i][0])) {
                            $distinct_keys = $distinct_word_keys[$i];
                        } else {
                            $distinct_keys = [$distinct_word_keys[$i]];
                        }
                        $out_keys = [];
                        $old_distinct_key_id = "";
                        foreach ($distinct_keys as $distinct_key) {
                            if (is_array($distinct_key)) {
                                if (!isset($distinct_key[2]) &&
                                    isset($distinct_key[1])) {
                                    $distinct_keys[] = $distinct_key[1];
                                }
                                $shift = (isset($distinct_key[1])) ?
                                    $distinct_key[1] : 0;
                                $mask = (isset($distinct_key[2])) ?
                                    $distinct_key[2] : "\x00\x00\x00\x00\x00" .
                                    "\x00\x00\x00\x00\x00\x00";
                                if (isset($distinct_key[3])) {
                                    $old_distinct_key_id =
                                        L\unbase64Hash($distinct_key[3]);
                                }
                                $distinct_key_id = L\unbase64Hash(
                                    $distinct_key[0]);
                            } else {
                                $shift = 0;
                                $mask = "\x00\x00\x00\x00\x00" .
                                    "\x00\x00\x00\x00\x00\x00";
                                $distinct_key_id =
                                    L\unbase64Hash($distinct_key);
                            }
                            $lookup_cutoff = max(C\MIN_RESULTS_TO_GROUP,
                                $to_retrieve);
                            $info = IndexManager::getWordInfo($index_name,
                                $distinct_key_id, $shift, $mask, -1, -1,
                                C\NUM_DISTINCT_GENERATIONS);
                            if ($old_distinct_key_id != "") {
                                $old_info = IndexManager::getWordInfo(
                                    $index_name, $old_distinct_key_id, $shift,
                                    $mask, -1, -1, C\NUM_DISTINCT_GENERATIONS);
                                if ($info !== false && $old_info !== false) {
                                    $info = array_merge($info, $old_info);
                                } elseif ($old_info !== false) {
                                    $info = $old_info;
                                }
                            }
                            if ($info != []) {
                                $tmp_keys = L\arrayColumnCount($info, 4, 3);
                                $sum += array_sum($tmp_keys);
                                $out_keys = array_merge($out_keys, $tmp_keys);
                            }
                            if ($sum > $lookup_cutoff) {
                                break;
                            }
                        }
                        $out_keys = array_keys(array_slice($out_keys, 0, 50));
                        $tmp_word_iterators =[];
                        $m = 0;
                        foreach ($out_keys as $distinct_key) {
                            $tmp_word_iterators[$m] =
                                new I\WordIterator($distinct_key,
                                $index_name, true, $filter, $to_retrieve,
                                $limit_news);
                            if ($tmp_word_iterators[$m]->dictionary_info !=
                                [] ||
                                $tmp_word_iterators[$m]->feed_count > 0) {
                                $min_group_override = true;
                                $m++;
                            } else {
                                unset($tmp_word_iterators[$m]);
                            }
                        }
                        if ($m == 1) {
                            $word_iterators[$i] = $tmp_word_iterators[0];
                        } else {
                            $word_iterators[$i] = new I\DisjointIterator(
                                $tmp_word_iterators);
                        }
                    }
                    foreach ($word_keys as $index => $key) {
                        if (isset($distinct_word_keys[$i]) &&
                            $key == $distinct_word_keys[$i]) {
                            $word_iterator_map[$index] = $i;
                        }
                    }
                }
                $num_disallow_keys = count($disallow_keys);
                if ($num_disallow_keys > 0) {
                    for ($i = 0; $i < $num_disallow_keys; $i++) {
                        $disallow_iterator =
                            new I\WordIterator($disallow_keys[$i], $index_name,
                                false, $filter);
                        $word_iterators[$num_word_keys + $i] =
                            new I\NegationIterator($disallow_iterator);
                    }
                }
                $num_word_keys += $num_disallow_keys;
                if ($num_word_keys == 1 && $weight == 1) {
                    $base_iterator = $word_iterators[0];
                } else {
                    $base_iterator = new I\IntersectIterator(
                        $word_iterators, $word_iterator_map, $quote_positions,
                        $weight);
                    $min_group_flag = true;
                    if ($save_timestamp_name == "") {
                        $base_iterator->sync_timer_on = true;
                    } else {
                        $base_iterator->sync_timer_on = false;
                    }
                }
                if ($save_timestamp_name != "") {
                    if (isset($save_point[$save_count]) &&
                        $save_point[$save_count] != -1) {
                        $base_iterator->advance($save_point[$save_count]);
                    }
                    $save_count++;
                }
                $iterators[] = $base_iterator;
            }
        }
        $num_iterators = count($iterators); //if network_flag should be 1
        if ($num_iterators < 1) {
            return null;
        } elseif ($num_iterators == 1) {
            $union_iterator = $iterators[0];
        } else {
            $union_iterator = new I\UnionIterator($iterators);
        }
        $raw = intval($raw);
        if ($raw > 0) {
            $group_iterator = $union_iterator;
        } else {
            $group_iterator =
                new I\GroupIterator($union_iterator, $total_iterators,
                    $this->current_machine, $network_flag);
        }
        if ($network_flag) {
            $union_iterator->results_per_block =
                ceil(C\SERVER_ALPHA *
                    $group_iterator->results_per_block/$num_servers);
        } elseif ($save_timestamp_name != "") {
            $group_iterator->save_iterators = $iterators;
        } elseif ($min_group_flag && !$min_group_override) {
            $group_iterator->results_per_block = max(C\MIN_RESULTS_TO_GROUP/20,
                1);
            $to_retrieve = -1;
        }
        return $group_iterator;
    }
}
