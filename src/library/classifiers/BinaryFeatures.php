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
 * A concrete Features subclass that represents a document as a binary
 * vector where a one indicates that a feature is present in the document, and
 * a zero indicates that it is not. The absent features are ignored, so the
 * binary vector is actually sparse, containing only those feature indices
 * where the value is one.
 *
 * @author Shawn Tice
 */
class BinaryFeatures extends Features
{
    /**
     * Replaces term counts with 1, indicating only that a feature occurs in a
     * document.  When a Features instance is a subset of a larger instance, it
     * will have a feature_map member that maps feature indices from the larger
     * feature set to the smaller one. The indices must be mapped in this way
     * so that the training set can retain complete information, only throwing
     * away features just before training. See the abstract parent class for a
     * more thorough introduction to the interface.
     *
     * @param array $docs array of training examples represented as feature
     * vectors where the values are per-example counts
     * @return object SparseMatrix instance whose rows are the transformed
     * feature vectors
     */
    public function mapTrainingSet($docs)
    {
        $m = count($docs);
        $n = count($this->vocab) + 1;
        $X = new SparseMatrix($m, $n);

        $i = 0;
        foreach ($docs as $features) {
            /*
               If this is a restricted feature set, map from the expanded
               feature set first, potentially dropping features.
             */
            $features = $this->mapToRestrictedFeatures($features);
            $new_features = array_combine(
                array_keys($features),
                array_fill(0, count($features), 1));
            $X->setRow($i++, $new_features);
        }
        return $X;
    }
    /**
     * Converts a map from terms to  within-document term counts with the
     * corresponding sparse binary feature vector used for classification.
     *
     * @param array $tokens associative array of terms mapped to their
     *      within-document counts
     * @return array feature vector corresponding to the tokens, mapped
     *      according to the implementation of a particular Features subclass
     */
    public function mapDocument($tokens)
    {
        $x = [];
        foreach ($tokens as $token => $count) {
            if (isset($this->vocab[$token])) {
                $x[$this->vocab[$token]] = 1;
            }
        }
        $x[0] = 1;
        ksort($x);
        return $x;
    }
}
