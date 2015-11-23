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
namespace seekquarry\yioop\library\classifiers;

/**
 * A concrete Features subclass that represents a document as a
 * vector of feature weights, where weights are computed using a modified form
 * of TF * IDF. This feature mapping is experimental, and may not work
 * correctly.
 *
 * @author Shawn Tice
 */
class WeightedFeatures extends Features
{
    /**
     * Number of trainin examples
     * @var int
     */
    public $D = 0;
    /**
     * Number of elements in Vocabulary
     * @var int
     */
    public $n = [];
    /**
     * {@inheritDocs}
     *
     * @param array $docs array of training examples represented as feature
     *      vectors where the values are per-example counts
     * @return object SparseMatrix instance whose rows are the transformed
     *      feature vectors
     */
    public function mapTrainingSet($docs)
    {
        $m = count($this->examples);
        $n = count($this->vocab);
        $this->D = $m;
        $this->n = [];
        // Fill in $n, the count of documents that contain each term
        foreach ($this->examples as $features) {
            foreach (array_keys($features) as $j) {
                if (!isset($this->n[$j]))
                    $this->n[$j] = 1;
                else
                    $this->n[$j] += 1;
            }
        }
        $X = new SparseMatrix($m, $n);
        $y = $this->exampleLabels;
        foreach ($this->examples as $i => $features) {
            $u = [];
            $sum = 0;
            // First compute the unnormalized TF * IDF term weights and keep
            // track of the sum of all weights in the document.
            foreach ($features as $j => $count) {
                $tf = 1 + log($count);
                $idf = log(($this->D + 1) / ($this->n[$j] + 1));
                $weight = $tf * $idf;
                $u[$j] = $weight;
                $sum += $weight * $weight;
            }
            // Now normalize each of the term weights.
            $norm = sqrt($sum);
            foreach (array_keys($features) as $j) {
                $features[$j] = $u[$j] / $norm;
            }
            $X->setRow($i, $features);
        }
        return [$X, $y];
    }
    /**
     *  {@inheritDocs}
     *
     * @param array $tokens associative array of terms mapped to their
     *      within-document counts
     * @return array feature vector corresponding to the tokens, mapped
     *      according to the implementation of a particular Features subclass
     */
    public function mapDocument($tokens)
    {
        $u = [];
        $sum = 0;
        ksort($this->current);
        foreach ($this->current as $j => $count) {
            $tf = 1 + log($count);
            $idf = log(($this->D + 1) / ($this->n[$j] + 1));
            $weight = $tf * $idf;
            $u[$j] = $weight;
            $sum += $weight * $weight;
        }
        $norm = sqrt($sum);
        $x = [];
        foreach (array_keys($this->current) as $j) {
            $x[$j] = $u[$j] / $norm;
        }
        $this->current = [];
        return $x;
    }
}
