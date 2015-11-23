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
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\IndexManager;

/**
 * Used to iterate through the documents associated with a word in
 * an IndexArchiveBundle. It also makes it easy to get the summaries
 * of these documents.
 *
 * A description of how words and the documents containing them are stored
 * is given in the documentation of IndexArchiveBundle.
 *
 * @author Chris Pollett
 * @see IndexArchiveBundle
 */
class WordIterator extends IndexBundleIterator
{
    /**
     * hash of word that the iterator iterates over
     * @var string
     */
    public $word_key;
    /**
     * The timestamp of the index is associated with this iterator
     * @var string
     */
    public $index_name;
    /**
     * The byte mask to apply against the word id
     * @var string
     */
    public $mask;
    /**
     * First shard generation that word info was obtained for
     * @var int
     */
    public $start_generation;
    /**
     * Used to keep track of whether getWordInfo might still get more
     * data on the search terms as advance generations
     * @var bool
     */
    public $no_more_generations;
    /**
     * The next byte offset in the IndexShard
     * @var int
     */
    public $next_offset;
    /**
     * An array of shard generation and posting list offsets, lengths, and
     * numbers of documents
     * @var array
     */
    public $dictionary_info;
    /**
     * File name (including path) of the feed shard for news items
     * @var string
     */
    public $feed_shard_name;
    /**
     * Structure used to hold posting list start and stops for the query
     * in the feed shard
     * @var array
     */
    public $feed_info;
    /**
     * The total number of shards that have data for this word
     * @var int
     */
    public $num_generations;
    /**
     * Index into dictionary_info corresponding to the current shard
     * @var int
     */
    public $generation_pointer;
    /**
     * Numeric number of current shard
     * @var int
     */
    public $current_generation;
    /**
     * The current byte offset in the IndexShard
     * @var int
     */
    public $current_offset;
    /**
     * Starting Offset of word occurence in the IndexShard
     * @var int
     */
    public $start_offset;
    /**
     * Last Offset of word occurence in the IndexShard
     * @var int
     */
    public $last_offset;
    /**
     * Keeps track of whether the word_iterator list is empty because the
     * word does not appear in the index shard
     * @var int
     */
    public $empty;
    /**
     * Keeps track of whether the word_iterator list is empty because the
     * word does not appear in the index shard
     * @var int
     */
    public $filter;
    /**
     * The current value of the doc_offset of current posting if known
     * @var int
     */
    public $current_doc_offset;
    /** Host Key position + 1 (first char says doc, inlink or eternal link)*/
    const HOST_KEY_POS = 17;
    /** Length of a doc key*/
    const KEY_LEN = 8;
    /** If the $limit_news constructor input is true then limit the number
     * of items coming from the feed shard to this count.
     */
    const LIMIT_NEWS_COUNT = 25;
    /**
     * Creates a word iterator with the given parameters.
     *
     * @param string $word_key hash of word or phrase to iterate docs of
     * @param string $index_name time_stamp of the to use
     * @param bool $raw whether the $word_key is our variant of base64 encoded
     * @param array $filter an array of hashes of domains to filter from
     *     results
     * @param int $results_per_block the maximum number of results that can
     *      be returned by a findDocsWithWord call
     * @param bool $limit_news news results appear before all others when
     *      gotten out of this iterator (may be reordered later). This flag
     *      controls whether an upper bound of self::LIMIT_NEWS_COUNT is imposed
     *      on the number of feed results returned
     * @param string $mask byte mask to apply against word id, default is for
     *     exact match
     */
    public function __construct($word_key, $index_name, $raw = false,
        &$filter = null,
        $results_per_block = IndexBundleIterator::RESULTS_PER_BLOCK,
        $limit_news = false,
        $mask = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF")
    {
        if ($raw == false) {
            //get rid of out modified base64 encoding
            $word_key = unbase64Hash($word_key);
        }
        if ($filter != null) {
            $this->filter = & $filter;
        } else {
            $this->filter = null;
        }
        $this->word_key = $word_key;
        $this->index_name =  $index_name;
        $this->mask = $mask;
        list($estimated_total, $this->dictionary_info) =
            IndexManager::getWordInfo($index_name, $word_key, 0,
            $mask, -1, -1, C\NUM_DISTINCT_GENERATIONS, true);
        $this->feed_shard_name = C\WORK_DIRECTORY."/feeds/index";
        if ((!C\nsdefined('NO_FEEDS') || !C\NO_FEEDS)
            && file_exists($this->feed_shard_name)) {
            //NO_FEEDS defined true in statistic_controller.php
            $this->use_feeds = true;
        } else {
            $this->use_feeds = false;
        }
        if ($this->use_feeds) {
            if (!isset($this->dictionary_info[-1])) {
                $this->feed_info = false;
                $this->feed_empty = true;
            } else {
                $this->feed_info = $this->dictionary_info[-1];
                unset($this->dictionary_info[-1]);
                $this->feed_empty = false;
            }
        } else {
            $this->feed_info = false;
            $this->feed_empty = true;
        }
        if (is_array($this->feed_info)) {
            list(,$this->feed_start, $this->feed_end, $this->feed_count,) =
                $this->feed_info;
            $this->feed_info = [$this->feed_start, $this->feed_end,
                $this->feed_count];
        } else {
            $this->feed_start = 0;
            $this->feed_end = 0;
            $this->feed_count = 0;
        }
        if ($this->feed_count > 0) {
            $this->using_feeds = true;
        } else {
            $this->using_feeds = false;
        }
        if ($limit_news && $this->feed_count > self::LIMIT_NEWS_COUNT) {
            $this->feed_count = self::LIMIT_NEWS_COUNT;
            $this->feed_end = $this->feed_start +
                IndexShard::POSTING_LEN * (self::LIMIT_NEWS_COUNT - 1);
        }
        $this->num_docs = $this->feed_count + $estimated_total;
        if ($this->dictionary_info === false) {
            $this->empty = true;
        } else {
            ksort($this->dictionary_info);
            $this->dictionary_info = array_values($this->dictionary_info);
            $this->num_generations = count($this->dictionary_info);
            if ($this->num_generations == 0) {
                $this->empty = true;
            } else {
                $this->empty = false;
            }
        }
        $this->no_more_generations =
            ($this->num_generations < C\NUM_DISTINCT_GENERATIONS);
        $this->current_doc_offset = null;
        $this->results_per_block = $results_per_block;
        $this->current_block_fresh = false;
        $this->start_generation = 0;
        if ($this->dictionary_info !== false || $this->feed_info !== false) {
            $this->reset();
        }
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
        $item = [];
        if ($this->using_feeds && $this->use_feeds) {
            $index = IndexManager::getIndex("feed");
            $num_docs_or_links =
                IndexShard::numDocsOrLinks($this->feed_start,
                $this->feed_end);
            $current = $posting_offset >> 2;
            $posting = $index->getCurrentShard()->getPostingAtOffset(
                $current, $posting_start, $posting_end);
            list( , $item) = $index->getCurrentShard()->makeItem($posting,
                $num_docs_or_links, 1);
            $item[self::RELEVANCE] *= 10;
        } else {
            $index = IndexManager::getIndex($this->index_name);
            $index->setCurrentShard($generation, true);
            $num_docs_or_links =
                IndexShard::numDocsOrLinks($this->start_offset,
                $this->last_offset);
            $current = $posting_offset >> 2;
            $posting = $index->getCurrentShard()->getPostingAtOffset(
                $current, $posting_start, $posting_end);
            list( , $item) = $index->getCurrentShard()->makeItem($posting,
                $num_docs_or_links, 1);
        }
        return $item[self::RELEVANCE];
    }
    /**
     * Resets the iterator to the first document block that it could iterate
     * over

     */
    public function reset()
    {
        if ($this->feed_count > 0) {
            $this->using_feeds = true;
        } else {
            $this->using_feeds = false;
        }
        $no_feeds = $this->feed_empty || !$this->use_feeds;
        if (!$this->empty) {//we shouldn't be called when empty - but to be safe
            if ($this->start_generation > 0) {
                list($estimated_total, $this->dictionary_info) =
                    IndexManager::getWordInfo($this->index_name,
                    $this->word_key, 0, $this->mask, -1, 0,
                    C\NUM_DISTINCT_GENERATIONS, true);
                $this->num_docs = $this->feed_count + $estimated_total;
                ksort($this->dictionary_info);
                $this->dictionary_info = array_values($this->dictionary_info);
                $this->num_generations = count($this->dictionary_info);
                $this->no_more_generations =
                    ($this->num_generations < C\NUM_DISTINCT_GENERATIONS);
            }
            list($this->current_generation, $this->start_offset,
                $this->last_offset, )
                = $this->dictionary_info[0];
        } else {
            $this->start_offset = 0;
            $this->last_offset = -1;
            $this->num_generations = -1;
        }
        if (!$no_feeds) {
            $this->current_offset = $this->feed_start;
            $this->current_generation = -1;
        } else {
            $this->current_offset = $this->start_offset;
        }
        $this->generation_pointer = 0;
        $this->count_block = 0;
        $this->seen_docs = 0;
        $this->current_doc_offset = null;
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    public function findDocsWithWord()
    {
        $no_feeds = $this->feed_empty || !$this->use_feeds;
        $feed_in_use = $this->using_feeds && !$no_feeds;
        if ($this->empty && $no_feeds) {
            return -1;
        }
        if (!$feed_in_use &&(($this->generation_pointer>=$this->num_generations)
            || ($this->generation_pointer == $this->num_generations - 1 &&
            $this->current_offset > $this->last_offset))) {
            return -1;
        }
        $pre_results = [];
        if ($feed_in_use) {
            $this->next_offset = $this->current_offset;
            $feed_shard = IndexManager::getIndex("feed");
            if ($feed_shard) {
                $pre_results = $feed_shard->getPostingsSlice(
                    $this->feed_start,
                    $this->next_offset, $this->feed_end,
                    $this->results_per_block);
                $time = time();
                foreach ($pre_results as $keys => $pre_result) {
                    $pre_results[$keys][self::IS_FEED] = true;
                    $delta = $time - $pre_result[self::SUMMARY_OFFSET];
                    $pre_results[$keys][self::DOC_RANK] = 720000 /
                        max($delta, 1);
                }
            }
        } else if (!$this->empty) {
            $this->next_offset = $this->current_offset;
            $index = IndexManager::getIndex($this->index_name);
            $index->setCurrentShard($this->current_generation, true);
            //the next call also updates next offset
            $shard = $index->getCurrentShard();
            $pre_results = $shard->getPostingsSlice(
                $this->start_offset,
                $this->next_offset, $this->last_offset,
                $this->results_per_block);
        }
        $results = [];
        $doc_key_len = IndexShard::DOC_KEY_LEN;
        $filter = ($this->filter == null) ? [] : $this->filter;
        foreach ($pre_results as $keys => $data) {
            $host_key = substr($keys, self::HOST_KEY_POS, self::KEY_LEN);
            if (in_array($host_key, $filter) ) {
                continue;
            }
            $data[self::KEY] = $keys;
            // inlinks is the domain of the inlink
            $key_parts = str_split($keys, $doc_key_len);
            if (isset($key_parts[2])) {
                list($hash_url, $data[self::HASH], $data[self::INLINKS]) =
                    $key_parts;
            } else {
                continue;
            }
            if (isset($data[self::IS_FEED]) && $data[self::IS_FEED]) {
                $data[self::CRAWL_TIME] = "feed";
            } else {
                $data[self::CRAWL_TIME] = $this->index_name;
            }
            $results[$keys] = $data;
        }
        $this->count_block = count($results);
        if ($this->generation_pointer == $this->num_generations - 1 &&
            $results == []) {
            $results = null;
        }
        $this->pages = $results;
        return $results;
    }
    /**
     * Updates the seen_docs count during an advance() call
     */
    public function advanceSeenDocs()
    {
        if ($this->current_block_fresh != true) {
            if ($this->using_feeds && $this->use_feeds) {
                $num_docs = min($this->results_per_block,
                    IndexShard::numDocsOrLinks($this->next_offset,
                        $this->feed_end));
            } else {
                $num_docs = min($this->results_per_block,
                    IndexShard::numDocsOrLinks($this->next_offset,
                        $this->last_offset));
            }
            $this->next_offset = $this->current_offset;
            $this->next_offset += IndexShard::POSTING_LEN * $num_docs;
            if ($num_docs < 0) {
                return;
            }
        } else {
            $num_docs = $this->count_block;
        }
        $this->current_block_fresh = false;
        $this->seen_docs += $num_docs;
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
        if ($gen_doc_offset != null) { //only advance if $gen_doc_offset bigger
            $cur_gen_doc_offset = $this->currentGenDocOffsetWithWord();
            if ($cur_gen_doc_offset == -1 ||
                $this->genDocOffsetCmp($cur_gen_doc_offset,
                $gen_doc_offset) >= 0) {
                return;
            }
        }
        $this->advanceSeenDocs();
        $this->current_doc_offset = null;
        if ($this->current_offset < $this->next_offset) {
            $this->current_offset = $this->next_offset;
        } else {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        $using_feeds = $this->using_feeds && $this->use_feeds;
        if (($using_feeds &&
            $this->current_offset > $this->feed_end) || (!$using_feeds &&
            $this->current_offset > $this->last_offset)) {
            $this->advanceGeneration();
            $this->next_offset = $this->current_offset;
        }
        if ($gen_doc_offset !== null) {
            if ($this->current_generation < $gen_doc_offset[0]) {
                $this->advanceGeneration($gen_doc_offset[0]);
                $this->next_offset = $this->current_offset;
            }
            $using_feeds = $this->using_feeds && $this->use_feeds;
            if ($using_feeds) {
                $shard = IndexManager::getIndex("feed");
                $last = $this->feed_end;
            } else {
                $index = IndexManager::getIndex($this->index_name);
                $index->setCurrentShard($this->current_generation, true);
                $shard = $index->getCurrentShard();
                $last = $this->last_offset;
            }

            if ($this->current_generation == $gen_doc_offset[0]) {
                $offset_pair =
                    $shard->nextPostingOffsetDocOffset($this->next_offset,
                            $last, $gen_doc_offset[1]);
                if ($offset_pair === false) {
                    $this->advanceGeneration();
                    $this->next_offset = $this->current_offset;
                } else {
                   list($this->current_offset,
                        $this->current_doc_offset) = $offset_pair;
                }
            }
            if ($this->current_generation == -1) {
                $this->seen_docs =
                    ($this->current_offset - $this->feed_start)/
                        IndexShard::POSTING_LEN;
            } else {
                $this->seen_docs = ($using_feeds) ? $this->feed_count : 0;
                $this->seen_docs +=
                    ($this->current_offset - $this->start_offset)/
                        IndexShard::POSTING_LEN;
            }
        }
    }
    /**
     * Switches which index shard is being used to return occurrences of
     * the word to the next shard containing the word
     *
     * @param int $generation generation to advance beyond
     */
    public function advanceGeneration($generation = null)
    {
        if ($this->using_feeds && $this->use_feeds) {
            $this->using_feeds = false;
            $this->generation_pointer = -1;
        }
        if ($generation === null) {
            $generation = $this->current_generation;
        }
        do {
            if ($this->generation_pointer < $this->num_generations) {
                $this->generation_pointer++;
            }
            if ($this->generation_pointer < $this->num_generations) {
                list($this->current_generation, $this->start_offset,
                    $this->last_offset, )
                    = $this->dictionary_info[$this->generation_pointer];
                $this->current_offset = $this->start_offset;
            }
            if (!$this->no_more_generations &&
                $this->current_generation < $generation &&
                $this->generation_pointer >= $this->num_generations) {
                list($estimated_remaining_total, $info) =
                    IndexManager::getWordInfo($this->index_name,
                    $this->word_key, 0,
                    $this->mask, -1, $this->num_generations,
                    C\NUM_DISTINCT_GENERATIONS, true);
                if (count($info) > 0) {
                    $this->num_docs = $this->seen_docs +
                        $estimated_remaining_total;
                    ksort($info);
                    $this->dictionary_info = array_merge($this->dictionary_info,
                        array_values($info));
                    $this->num_generations = count($this->dictionary_info);
                    $this->no_more_generations =
                        count($info) < C\NUM_DISTINCT_GENERATIONS;
                    //will increment back to where were next loop
                    $this->generation_pointer--;
                }
            }

        } while($this->current_generation < $generation &&
            $this->generation_pointer < $this->num_generations);
    }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    public function currentGenDocOffsetWithWord() {
        if ($this->current_doc_offset !== null) {
            return [$this->current_generation, $this->current_doc_offset];
        }
        $feeds = $this->using_feeds && $this->use_feeds && !$this->feed_empty;
        if ( ($feeds && $this->current_offset > $this->feed_end) ||
            (!$feeds && ($this->current_offset > $this->last_offset||
            $this->generation_pointer >= $this->num_generations))) {
            return -1;
        }
        if ($feeds) {
            $index = IndexManager::getIndex("feed");
            $this->current_doc_offset =
                $index->docOffsetFromPostingOffset($this->current_offset);
            return [-1, $this->current_doc_offset];
        }
        $index = IndexManager::getIndex($this->index_name);
        $index->setCurrentShard($this->current_generation, true);
        $this->current_doc_offset = $index->getCurrentShard(
            )->docOffsetFromPostingOffset($this->current_offset);
        return [$this->current_generation, $this->current_doc_offset];
    }
}
