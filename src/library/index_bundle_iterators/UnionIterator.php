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

use seekquarry\yioop\library\BloomFilterFile;

/**
 * Used to iterate over the documents which occur in any of a set of
 * WordIterator results
 *
 * @author Chris Pollett
 * @see IndexArchiveBundle
 */
class UnionIterator extends IndexBundleIterator
{
    /**
     * An array of iterators whose interection we  get documents from
     * @var array
     */
    public $index_bundle_iterators;
    /**
     * Number of elements in $this->index_bundle_iterators
     * @var int
     */
    public $num_iterators;
    /**
     * The number of documents in the current block before filtering
     * by restricted words
     * @var int
     */
    public $count_block_unfiltered;
    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    public $seen_docs_unfiltered;
    /**
     * stores a mapping between seen doc keys and which iterator they came from
     * @var array
     */
    public $key_iterator_table;
    /**
     * Creates a union iterator with the given parameters.
     *
     * @param object $index_bundle_iterators to use as a source of documents
     *     to iterate over
     */
    public function __construct($index_bundle_iterators)
    {
        $this->index_bundle_iterators = $index_bundle_iterators;
        /*
            estimate number of results by sum of all iterator counts,
            then improve estimate as iterate
        */
        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = 0;
        $this->results_per_block = 0;
        $this->key_iterator_table = [];
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $this->num_docs += $this->index_bundle_iterators[$i]->num_docs;
            /*
                result_per_block is at most the sum of
                results_per_block of things we are iterating. Value
                is already init'd in base class.
             */
            $this->results_per_block +=
                $this->index_bundle_iterators[$i]->results_per_block;
            $this->seen_docs += $this->index_bundle_iterators[$i]->seen_docs;
            if (isset($this->index_bundle_iterators[$i]->seen_docs_unfiltered)){
                $this->seen_docs_unfiltered +=
                    $this->index_bundle_iterators[$i]->seen_docs_unfiltered;
            } else {
                $this->seen_docs_unfiltered += $this->seen_docs;
            }
        }
        $doc_block = $this->currentDocsWithWord();
    }
    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    public function reset()
    {
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $this->index_bundle_iterators[$i]->reset();
        }
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        $doc_block = $this->currentDocsWithWord();
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
        $relevance = 0;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $relevance += $this->index_bundle_iterators[$i]->computeRelevance(
                $generation, $posting_offset);
        }
        return $relevance;
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and score if there are docs left, -1 otherwise
     */
    public function findDocsWithWord()
    {
        $pages = [];
        $docs = [];
        $high_score = [];
        $high_score = [];
        $found_docs = false;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $docs =  $this->index_bundle_iterators[$i]->currentDocsWithWord();
            if (is_array($docs)) {
                $doc_keys = array_keys($docs);
                foreach ($doc_keys as $key) {
                    $docs[$key]["ITERATOR"] = $i;
                    $this->key_iterator_table[$key] = $i;
                }
                $pages = array_merge($pages, $docs);
                $found_docs = true;
            }
        }
        if ($found_docs == false) {
            $this->pages = $docs;
            return $docs;
        }
        $this->count_block_unfiltered = count($pages);
        $this->pages = $pages;
        $this->count_block = count($pages);
        return $pages;
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
            $result = $this->currentDocsWithWord();
            if (!is_array($result)) {
                return $result;
            }
        }
        if (!is_array($this->pages)) {
            return $this->pages;
        }
        if ($keys == null) {
            $keys = array_keys($this->pages);
        }
        $out_pages = [];
        foreach ($keys as $doc_key) {
            if (!isset($this->pages[$doc_key]["ITERATOR"])) {
                continue;
            } else {
                $tmp = $this->index_bundle_iterators[
                    $this->pages[
                        $doc_key]["ITERATOR"]]->getCurrentDocsForKeys(
                            [$doc_key]);
                $out_pages[$doc_key] = $tmp[$doc_key];
            }
        }
        return $out_pages;
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
        $total_num_docs = 0;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $total_num_docs += $this->index_bundle_iterators[$i]->num_docs;
            $this->index_bundle_iterators[$i]->advance($gen_doc_offset);
        }
        if ($this->seen_docs_unfiltered > 0) {
            $this->num_docs =
                floor(($this->seen_docs * $total_num_docs) /
                $this->seen_docs_unfiltered);
        } else {
            $this->num_docs = 0;
        }
    }
    /**
     * This method is supposed to set
     * the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord(). This method cannot be consistently
     * implemented for this iterator and expect it to behave nicely
     * it this iterator is used together with intersect_iterator. So
     * to prevent a user for doing this, calling this method results
     * in a user defined error
     *
     * @param int $num the maximum number of results that can be returned by
     *     a block
     */
     public function setResultsPerBlock($num) {
        trigger_error("Cannot set the results per block of
            a union iterator", E_USER_ERROR);
     }
    /**
     * This method is supposed to get the doc_offset and generation
     * for the next document that would be return by
     * this iterator. As the union iterator as written returns a block
     * of size at least the number of iterators in it, and this iterator
     * is intended to be used when results_per_block is 1, we generate
     * a user defined error.
     *
     * @return mixed the desired document offset and generation (actually,
     * triggers error).
     */
    public function currentGenDocOffsetWithWord() {
        trigger_error("Cannot get the doc offset and generation with word of
            a union iterator", E_USER_ERROR);
    }
}
