<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015 Chris Pollett chris@pollett.org
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

/**
 * Used to iterate over the documents which occur in a set of disjoint iterators
 * all belonging to the same index
 *
 * @author Chris Pollett
 * @see IndexArchiveBundle
 */
class DisjointIterator extends IndexBundleIterator
{
    /**
     * An array of iterators whose intersection we  get documents from
     * @var array
     */
    public $index_bundle_iterators;
    /**
     * Number of elements in $this->index_bundle_iterators
     * @var int
     */
    public $num_iterators;
    /**
     * The number of iterated docs before the restriction test
     * @var int
     */
    public $seen_docs_unfiltered;
    /**
     * Index of the iterator amongst those we are disjoint unioning of
     * least gen_doc_offset
     * @var int
     */
    public $least_offset_index;
    /**
     * Creates an disjoint union iterator with the given parameters.
     *
     * @param object $index_bundle_iterators to use as a source of documents
     *     to iterate over
     */
    public function __construct($index_bundle_iterators)
    {
        $this->index_bundle_iterators = $index_bundle_iterators;
        $this->num_iterators = count($index_bundle_iterators);
        $this->num_docs = 0;
        $this->results_per_block = 1;
        /*
             We take an initial guess of the num_docs we return as the sum
             of the num_docs of the underlying iterators. We are also setting
             up here that we return at most one posting at a time from each
             iterator
        */
        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $this->num_docs += $this->index_bundle_iterators[$i]->num_docs;
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
            $this->seen_docs += $this->index_bundle_iterators[$i]->seen_docs;
            if (isset($this->index_bundle_iterators[$i]->seen_docs_unfiltered)){
                $this->seen_docs_unfiltered +=
                    $this->index_bundle_iterators[$i]->seen_docs_unfiltered;
            } else {
                $this->seen_docs_unfiltered += $this->seen_docs;
            }
        }
        $this->leastGenDocOffsetsAmongstIterators();
    }
    /**
     * Returns the iterators to the first document block that it could iterate
     * over
     */
    public function reset()
    {
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $this->index_bundle_iterators[$i]->setResultsPerBlock(1);
            $this->index_bundle_iterators[$i]->reset();
        }

        $this->seen_docs = 0;
        $this->seen_docs_unfiltered = 0;
        $this->leastGenDocOffsetsAmongstIterators();
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
        if (!$this->current_block_fresh) {
            $docs = $this->currentDocsWithWord();
        }
        $this->index_bundle_iterators[
            $this->least_offset_index]->computeRelevance(
                $generation, $posting_offset);
    }
    /**
     * Hook function used by currentDocsWithWord to return the current block
     * of docs if it is not cached
     *
     * @return mixed doc ids and rank if there are docs left, -1 otherwise
     */
    public function findDocsWithWord()
    {
        $least_offset = $this->leastGenDocOffsetsAmongstIterators();
        if ($least_offset == -1) {
            return -1;
        }
        //next we finish computing BM25F
        $docs = $this->index_bundle_iterators[
            $this->least_offset_index]->currentDocsWithWord();
        $this->count_block = count($docs);
        $this->pages = $docs;
        return $docs;
    }
    /**
     * Gets the doc_offset and generation for the next document that
     * would be return by this iterator
     *
     * @return mixed an array with the desired document offset
     * and generation; -1 on fail
     */
    public function currentGenDocOffsetWithWord() {
        if ($this->num_iterators <= 0) {
            return -1;
        }
        return $this->leastGenDocOffsetsAmongstIterators();
    }
    /**
     * Finds the next generation and doc offset amongst all the iterators
     * that is of least value
     */
    public function leastGenDocOffsetsAmongstIterators()
    {
        $least_gen_offset = -1;
        $this->least_offset_index = 0;
        for ($i = 0; $i < $this->num_iterators; $i++) {
            $cur_gen_doc_offset =
                $this->index_bundle_iterators[
                    $i]->currentGenDocOffsetWithWord();
            if ($least_gen_offset == -1 && is_array($cur_gen_doc_offset)) {
                $least_gen_offset = $cur_gen_doc_offset;
                $this->least_offset_index = $i;
                continue;
            } else if ($cur_gen_doc_offset == -1) {
                continue;
            }
            $gen_doc_cmp = $this->genDocOffsetCmp($cur_gen_doc_offset,
                $least_gen_offset);
            if ($gen_doc_cmp < 0) {
                $least_gen_offset = $cur_gen_doc_offset;
                $this->least_offset_index = $i;
            }
        }
        return $least_gen_offset;
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
        $no_change = true;
        //num_docs can change when advance() called so that's why we recompute
        $total_num_docs = 0;
        if ($gen_doc_offset !== null) {
            for ($i = 0; $i < $this->num_iterators; $i++) {
                $cur_gen_doc_offset = $this->index_bundle_iterators[
                    $i]->currentGenDocOffsetWithWord();
                if ($this->genDocOffsetCmp($cur_gen_doc_offset,
                    $gen_doc_offset) < 0) {
                    if ($no_change) {
                        $this->current_block_fresh = false;
                        $this->seen_docs += 1;
                        $this->seen_docs_unfiltered = 0;
                        $no_change = false;
                    }
                    $this->seen_docs_unfiltered +=
                        $this->index_bundle_iterators[$i]->seen_docs;
                    $total_num_docs +=
                        $this->index_bundle_iterators[$i]->num_docs;
                    $this->index_bundle_iterators[$i]->advance($gen_doc_offset);
                }
            }
        } else {
            if (!$this->current_block_fresh) {
                $this->leastGenDocOffsetsAmongstIterators();
            }
            $this->current_block_fresh = false;
            $this->seen_docs += 1;
            $this->seen_docs_unfiltered = 0;
            $least= $this->least_offset_index;
            if (!isset($this->index_bundle_iterators[$least])) { return; }
            $this->seen_docs_unfiltered +=
                $this->index_bundle_iterators[$least]->seen_docs;
            $total_num_docs += $this->index_bundle_iterators[$least]->num_docs;
            $this->index_bundle_iterators[$least]->advance();
        }
        if ($this->seen_docs_unfiltered > 0) {
            $this->num_docs =
                floor(($this->seen_docs * $total_num_docs) /
                $this->seen_docs_unfiltered);
        }
    }
    /**
     * This method is supposed to set
     * the value of the result_per_block field. This field controls
     * the maximum number of results that can be returned in one go by
     * currentDocsWithWord(). This method cannot be consistently
     * implemented for this iterator and expect it to behave nicely
     * it this iterator is used together with union_iterator or
     * intersect_iterator. So to prevent a user for doing this, calling this
     * method results in a user defined error
     *
     * @param int $num the maximum number of results that can be returned by
     *     a block
     */
     public function setResultsPerBlock($num) {
        if ($num != 1) {
            trigger_error("Cannot set the results per block of
                a phrase iterator", E_USER_ERROR);
        }
     }
}

