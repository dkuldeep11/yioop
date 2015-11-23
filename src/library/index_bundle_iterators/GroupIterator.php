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
use seekquarry\yioop\library\IndexShard;

/**
 * This iterator is used to group together documents or document parts
 * which share the same url. For instance, a link document item and
 * the document that it links to will both be stored in the IndexArchiveBundle
 * by the QueueServer. This iterator would combine both these items into
 * a single document result with a sum of their score, and a summary, if
 * returned, containing text from both sources. The iterator's purpose is
 * vaguely analagous to a SQL GROUP BY clause
 *
 * @author Chris Pollett
 * @see IndexArchiveBundle
 */
class GroupIterator extends IndexBundleIterator
{
    /**
     * The iterator we are using to get documents from
     * @var string
     */
    public $index_bundle_iterator;
    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    public $count_block_unfiltered;
    /**
     * The number of documents in the current block after filtering
     * by restricted words
     * @var int
     */
    public $count_block;
    /**
     * hashes of document web pages seen in results returned from the
     * most recent call to findDocsWithWord
     * @var array
     */
    public $current_block_hashes;
    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    public $seen_docs_unfiltered;
    /**
     * hashed url keys used to keep track of track of groups seen so far
     * @var array
     */
    public $grouped_keys;
    /**
     * hashed of document web pages used to keep track of track of
     * groups seen so far
     * @var array
     */
    public $grouped_hashes;
    /**
     * Used to keep track and to weight pages based on the number of other
     * pages from the same domain
     * @var array
     */
    public $domain_factors;
    /**
     * Whether the iterator is being used for a network query
     * @var bool
     */
    public $network_flag;
    /**
     * Id of queue_server this group_iterator lives on
     * @var int
     */
    public $current_machine;
    /**
     * the minimum number of pages to group from a block;
     * this trumps $this->index_bundle_iterator->results_per_block
     */
    const MIN_FIND_RESULTS_PER_BLOCK = C\MIN_RESULTS_TO_GROUP;
    /**
     * the minimum length of a description before we stop appending
     * additional link doc summaries
     */
    const MIN_DESCRIPTION_LENGTH = 10;
    /**
     * Creates a group iterator with the given parameters.
     *
     * @param object $index_bundle_iterator to use as a source of documents
     *     to iterate over
     * @param int $num_iterators number of word iterators appearing in
     *     in sub-iterators -- if larger than reduce the default grouping
     *     number
     * @param int $current_machine if this iterator is being used in a multi-
     *     queue_server setting, then this is the id of the current
     *     queue_server
     * @param bool $network_flag the iterator is being used for a network query
     */
    public function __construct($index_bundle_iterator, $num_iterators = 1,
        $current_machine = 0, $network_flag = false)
    {
        $this->index_bundle_iterator = $index_bundle_iterator;
        $this->num_docs = $this->index_bundle_iterator->num_docs;
        $this->results_per_block = max(
            $this->index_bundle_iterator->results_per_block,
            self::MIN_FIND_RESULTS_PER_BLOCK);
        $this->results_per_block /=  ceil($num_iterators/2);
        $this->network_flag = $network_flag;
        $this->current_machine = $current_machine;
        $this->is_feed = false;
        $this->reset();
    }
    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    public function reset()
    {
        $this->index_bundle_iterator->reset();
        $this->grouped_keys = [];
         $this->grouped_hashes = [];
            // -1 == never save, so file name not used using time to be safer
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
    }
    /**
     * Computes a relevancy score for a posting offset with respect to this
     * iterator and generation
     * @param int $generation the generation the posting offset is for
     * @param int $posting_offset an offset into word_docs to compute the
     *     relevance of
     * @return float a relevancy score based on BM25F.
     */
    public function computeRelevance($generation, $posting_offset)
    {
        return $this->index_bundle_iterator->computeRelevance($generation,
            $posting_offset);
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    public function findDocsWithWord()
    {
        // first get a block of documents on which grouping can be done
        $pages =  $this->getPagesToGroup();
        $this->count_block_unfiltered = count($pages);
        if (!is_array($pages)) {
            return $pages;
        }
        $this->current_block_hashes = [];
        $this->current_seen_hashes = [];
        if ($this->count_block_unfiltered > 0 ) {
            /* next we group like documents by url and remember
               which urls we've seen this block
            */
            $pre_out_pages = $this->groupByHashUrl($pages);
           /*get doc page for groups of link data if exists and don't have
             also aggregate by hash
           */
           $this->groupByHashAndAggregate($pre_out_pages);
           $this->count_block = count($pre_out_pages);
            /*
                Calculate aggregate values for each field of the groups we
                found
             */
            $pages = $this->computeOutPages($pre_out_pages);
        }
        $this->pages = $pages;
        return $pages;
    }
    /**
     * Gets a sample of a few hundred pages on which to do grouping by URL
     *
     * @return array of pages of document key --> meta data arrays
     */
    public function getPagesToGroup()
    {
        $pages = [];
        $count = 0;
        $done = false;
        do {
            $new_pages = $this->index_bundle_iterator->currentDocsWithWord();
            if (!is_array($new_pages)) {
                $done = true;
                if (count($pages) == 0) {
                    $pages = -1;
                }
            } else {
                $pages += $new_pages;
                $count = count($pages);
            }
            if (isset($this->index_bundle_iterator->hard_query)) {
                $this->results_per_block =
                    $this->index_bundle_iterator->hard_query;
            }
            if ($count < $this->results_per_block && !$done) {
                $this->index_bundle_iterator->advance();
            } else {
                $done = true;
            }
        } while($done != true);

        return $pages;
    }
    /**
     * Groups documents as well as mini-pages based on links to documents by
     * url to produce an array of arrays of documents with same url. Since
     * this is called in an iterator, documents which were already returned by
     * a previous call to currentDocsWithWord() followed by an advance() will
     * have been remembered in grouped_keys and will be ignored in the return
     * result of this function.
     *
     * @param array& $pages pages to group
     * @return array $pre_out_pages pages after grouping
     */
    public function groupByHashUrl(&$pages)
    {
        $pre_out_pages = [];
        foreach ($pages as $doc_key => $doc_info) {
            if (!is_array($doc_info) || $doc_info[self::SUMMARY_OFFSET] ==
                self::NEEDS_OFFSET_FLAG) { continue;}
            $hash_url = substr($doc_key, 0, IndexShard::DOC_KEY_LEN);
            if (isset($doc_info[self::IS_FEED])) {
                $this->is_feed = true;
            } else {
                $this->is_feed = false;
            }
            // initial aggregate domain score vector for given domain
            if ($doc_info[self::IS_DOC]) {
                if (!isset($pre_out_pages[$hash_url])) {
                    $pre_out_pages[$hash_url] = [];
                }
                array_unshift($pre_out_pages[$hash_url], $doc_info);
            } else {
                $pre_out_pages[$hash_url][] = $doc_info;
            }
            if (!isset($this->grouped_keys[$hash_url])) {
               /*
                    new urls found in this block
                */
                $this->current_block_hashes[] = $hash_url;
            } else {
                unset($pre_out_pages[$hash_url]);
            }
        }
        return $pre_out_pages;
    }
    /**
     * For documents which had been previously grouped by the hash of their
     * url, groups these groups further by the hash of their pages contents.
     * For each group of groups with the same hash summary, this function
     * then selects the subgroup of with the highest aggregate score for
     * that group as its representative. The function then modifies the
     * supplied argument array to make it an array of group representatives.
     *
     * @param array& $pre_out_pages documents previously grouped by hash of url
     */
    public function groupByHashAndAggregate(&$pre_out_pages)
    {
        foreach ($pre_out_pages as $hash_url => $data) {
            $hash = $pre_out_pages[$hash_url][0][self::HASH];
            $this->aggregateScores($hash_url, $pre_out_pages[$hash_url]);
            if (isset($pre_out_pages[$hash_url][0][self::HASH])) {
                $hash = $pre_out_pages[$hash_url][0][self::HASH];
                if (isset($this->grouped_hashes[$hash])) {
                    unset($pre_out_pages[$hash_url]);
                } else {
                    if (!isset($this->current_seen_hashes[$hash])) {
                        $this->current_seen_hashes[$hash] = [];
                    }
                    if (!isset($this->current_seen_hashes[$hash][$hash_url])) {
                        $this->current_seen_hashes[$hash][$hash_url] = 0;
                    }
                    $this->current_seen_hashes[$hash][$hash_url] +=
                        $pre_out_pages[$hash_url][0][self::HASH_SUM_SCORE];
                }
            }
        }
        // delete all except highest scoring group with given hash
        foreach ($this->current_seen_hashes as $hash => $url_data) {
            if (count($url_data) == 1) continue;
            arsort($url_data);
            $first_time = true;
            foreach ($url_data as $hash_url => $value) {
                if ($first_time) {
                    $first_hash_url = $hash_url;
                } else {
                    $pre_out_pages[$first_hash_url][0][self::DOC_RANK] +=
                        $pre_out_pages[$hash_url][0][self::DOC_RANK];
                    $pre_out_pages[$first_hash_url][0][self::RELEVANCE] +=
                        $pre_out_pages[$hash_url][0][self::RELEVANCE];
                    $pre_out_pages[$first_hash_url][0][self::PROXIMITY] = max(
                        $pre_out_pages[$first_hash_url][0][self::PROXIMITY],
                        $pre_out_pages[$hash_url][0][self::PROXIMITY]);
                    unset($pre_out_pages[$hash_url]);
                }
            }
        }
    }
    /**
     * For a collection of grouped pages generates a grouped summary for each
     * group and returns an array of out pages consisting
     * of single summarized documents for each group. These single summarized
     * documents have aggregated scores.
     *
     * @param array& $pre_out_pages array of groups of pages for which out pages
     *     are to be generated.
     * @return array $out_pages array of single summarized documents
     */
    public function computeOutPages(&$pre_out_pages)
    {
        $out_pages = [];
        foreach ($pre_out_pages as $hash_url => $group_infos) {
            $out_pages[$hash_url] = $pre_out_pages[$hash_url][0];
            $add_lookup = false;
            if ($this->network_flag) {
                $hash = $out_pages[$hash_url][self::HASH];
                $is_location = 
                    (L\crawlHash($hash_url."LOCATION", true) == $hash);
                if (!$out_pages[$hash_url][self::IS_DOC] || $is_location) {
                    $add_lookup = true;
                }
            }
            $out_pages[$hash_url][self::SUMMARY_OFFSET] = [];
            unset($out_pages[$hash_url][self::GENERATION]);
            $hash_count = $out_pages[$hash_url][self::HASH_URL_COUNT];
            for ($i = 0; $i < $hash_count; $i++) {
                $doc_info = $group_infos[$i];
                if (isset($doc_info[self::GENERATION])) {
                    if (is_int($doc_info[self::SUMMARY_OFFSET])) {
                        $machine_id = (isset($doc_info[self::MACHINE_ID])) ?
                            $doc_info[self::MACHINE_ID] :$this->current_machine;
                        $out_pages[$hash_url][self::SUMMARY_OFFSET][] =
                            [$machine_id, $doc_info[self::KEY],
                                $doc_info[self::CRAWL_TIME],
                                $doc_info[self::GENERATION],
                                $doc_info[self::SUMMARY_OFFSET]];
                    } else if (is_array($doc_info[self::SUMMARY_OFFSET])) {
                        $out_pages[$hash_url][self::SUMMARY_OFFSET] =
                            array_merge(
                                $out_pages[$hash_url][self::SUMMARY_OFFSET],
                                $doc_info[self::SUMMARY_OFFSET]);
                    }
                }
            }
            $out_pages[$hash_url][self::SCORE] =
                $out_pages[$hash_url][self::HASH_SUM_SCORE];
            if ($add_lookup) {
                $prefix = ($is_location) ? "location:" : "info:";
                $word_key = $prefix.L\base64Hash($hash_url);
                array_unshift($out_pages[$hash_url][self::SUMMARY_OFFSET],
                    [$word_key, $group_infos[0][self::CRAWL_TIME]]);
            }
        }
        return $out_pages;
    }
    /**
     * For a collection of pages each with the same url, computes the page
     * with the min score, max score, as well as the sum of the score,
     * aggregate of the ranks, proximity, and relevance scores, and a count.
     * Stores this information in the first element of the array of pages.
     * This process is described in detail at:
     * http://www.seekquarry.com/?c=main&p=ranking#search
     *
     * @param string $hash_url the crawlHash of the url of the page we are
     *      scoring which will be compared with that of the host to see if
     *      the current page has the url of a hostname.
     * @param array& $pre_hash_page pages to compute scores for
     */
    public function aggregateScores($hash_url, &$pre_hash_page)
    {
        $sum_score = 0;
        $sum_rank = 0;
        $sum_relevance = 0;
        $max_proximity = 0;
        $domain_weights = [];
        foreach ($pre_hash_page as $hash_page) {
            if (isset($hash_page[self::SCORE])) {
                $current_rank = $hash_page[self::DOC_RANK];
                $hash_host = $hash_page[self::INLINKS];
                if (!isset($domain_weights[$hash_host])) {
                    $domain_weights[$hash_host] = 1;
                }
                $relevance_boost = 1;
                if (substr($hash_url, 1) == substr($hash_host, 1)) {
                    $relevance_boost = 2;
                }
                $alpha = $relevance_boost * $domain_weights[$hash_host];
                $sum_score += $alpha * $hash_page[self::DOC_RANK];

                $sum_rank += $alpha * $hash_page[self::DOC_RANK];
                $sum_relevance += $alpha * $hash_page[self::RELEVANCE];
                $max_proximity = max($max_proximity,
                    $hash_page[self::PROXIMITY]);
                $domain_weights[$hash_host] *=  0.5;
            }
        }
        /* if two pages have the same hash HASH_SUM_SCORE used to determine
           which url is assumed to be the correct one (the other will be
           deleted). It doesn't show up as part of the final scores one
           sees on SERP pages.
         */
        $pre_hash_page[0][self::HASH_SUM_SCORE] = $sum_score;
        $pre_hash_page[0][self::DOC_RANK] = $sum_rank;
        $pre_hash_page[0][self::HASH_URL_COUNT] = count($pre_hash_page);
        $pre_hash_page[0][self::RELEVANCE] = $sum_relevance;
        $pre_hash_page[0][self::PROXIMITY] = $max_proximity;
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
        $this->advanceSeenDocs();
        $this->seen_docs_unfiltered += $this->count_block_unfiltered;
        if ($this->seen_docs_unfiltered > 0) {
            if ( $this->count_block_unfiltered < $this->results_per_block) {
                $this->num_docs = $this->seen_docs;
            } else {
                $this->num_docs =
                    floor(
                    ($this->seen_docs*$this->index_bundle_iterator->num_docs)/
                    $this->seen_docs_unfiltered);
            }
        } else {
            $this->num_docs = 0;
        }
        foreach ($this->current_block_hashes as $hash_url) {
            $this->grouped_keys[$hash_url] = true;
        }
        foreach ($this->current_seen_hashes as $hash => $url_data) {
            $this->grouped_hashes[$hash] = true;
        }
        $this->index_bundle_iterator->advance($gen_doc_offset);
    }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    public function currentGenDocOffsetWithWord() {
        $this->index_bundle_iterator->currentGenDocOffsetWithWord();
    }
}
