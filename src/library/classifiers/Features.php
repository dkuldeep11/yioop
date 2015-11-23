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
 * Manages a dataset's features, providing a standard interface for converting
 * documents to feature vectors, and for accessing feature statistics.
 *
 * Each document in the training set is expected to be fed through an instance
 * of a subclass of this abstract class in order to convert it to a feature
 * vector. Terms are replaced with feature indices (e.g., 'Pythagorean' => 1,
 * 'theorem' => 2, and so on), which are contiguous. The value at a feature
 * index is determined by the subclass; one might weight terms according to how
 * often they occur in the document, while another might use a simple binary
 * representation. The feature index 0 is reserved for an intercept term, which
 * always has a value of one.
 *
 * @author Shawn Tice
 */
abstract class Features
{
    /**
     * Maps terms to their feature indices, which start at 1.
     * @var array
     */
    public $vocab = [];
    /**
     * Maps terms to how often they occur in documents by label.
     * @var array
     */
    public $var_freqs = [];
    /**
     * Maps labels to the number of documents they're assigned to.
     * @var array
     */
    public $label_freqs = [-1 => 0, 1 => 0];
    /**
     * Maps old feature indices to new ones when a feature subset operation has
     * been applied to restrict the number of features.
     * @var array
     */
    public $feature_map;
    /**
     * A list of the top terms according to the last feature subset operation,
     * if any.
     * @var array
     */
    public $top_terms = [];
    /**
     * Maps a new example to a feature vector, adding any new terms to the
     * vocabulary, and updating term and label statistics. The example should
     * be an array of terms and their counts, and the output simply replaces
     * terms with feature indices.
     *
     * @param array $terms array of terms mapped to the number of times they
     *      occur in the example
     * @param int $label label for this example, either -1 or 1
     * @return array input example with terms replaced by feature indices
     */
    public function addExample($terms, $label)
    {
        $this->label_freqs[$label]++;
        $features = [];
        foreach ($terms as $term => $count) {
            if (isset($this->vocab[$term])) {
                $j = $this->vocab[$term];
            } else {
                // Var indices start at 1 to accommodate the intercept at 0.
                $j = count($this->vocab) + 1;
                $this->vocab[$term] = $j;
            }
            $features[$j] = $count;
            // Update term statistics
            if (!isset($this->var_freqs[$j][$label])) {
                $this->var_freqs[$j][$label] = 1;
            } else {
                $this->var_freqs[$j][$label]++;
            }
        }
        // Feature 0 is an intercept term
        $features[0] = 1;
        ksort($features);
        return $features;
    }
    /**
     * Updates the label and term statistics to reflect a label change for an
     * example from the training set. A new label of 0 indicates that the
     * example is being removed entirely. Note that term statistics only count
     * one occurrence of a term per example.
     *
     * @param array $features feature vector from when the example was
     *      originally added
     * @param int $old_label old example label in {-1, 1}
     * @param int $new_label new example label in {-1, 0, 1}, where 0 indicates
     *      that the example should be removed entirely
     */
    public function updateExampleLabel($features, $old_label, $new_label)
    {
        $this->label_freqs[$old_label]--;
        if ($new_label != 0) {
            $this->label_freqs[$new_label]++;
        }
        // Remove the intercept term first.
        unset($features[0]);
        foreach (array_keys($features) as $j) {
            $this->var_freqs[$j][$old_label]--;
            if ($new_label != 0) {
                $this->var_freqs[$j][$new_label]++;
            }
        }
    }
    /**
     * Returns the number of features, not including the intercept term
     * represented by feature zero. For example, if we had features 0..10,
     * this function would return 10.
     *
     * @return int the number of features in the training set
     */
    public function numFeatures()
    {
        return count($this->vocab);
    }
    /**
     * Returns the positive and negative label counts for the training set.
     *
     * @return array positive and negative label counts indexed by label,
     * either 1 or -1
     */
    public function labelStats()
    {
        return [$this->label_freqs[1], $this->label_freqs[-1]];
    }
    /**
     * Returns the statistics for a particular feature and label in the
     * training set. The statistics are counts of how often the term appears or
     * fails to appear in examples with or without the target label. They are
     * returned in a flat array, in the following order:
     *
     *    0 => # examples where feature present, label matches
     *    1 => # examples where feature present, label doesn't match
     *    2 => # examples where feature absent, label matches
     *    3 => # examples where feature absent, label doesn't match
     *
     * @param int $j feature index
     * @param int $label target label
     * @return array feature statistics in 4-element flat array
     */
    public function varStats($j, $label)
    {
        $tl = isset($this->var_freqs[$j][$label]) ?
            $this->var_freqs[$j][$label] : 0;
        $t  = array_sum($this->var_freqs[$j]);
        $l  = $this->label_freqs[$label];
        $N  = array_sum($this->label_freqs);
        return [
            $tl,               //  t and  l
            $t - $tl,          //  t and ~l
            $l - $tl,          // ~t and  l
            $N - $t - $l + $tl // ~t and ~l
        ];
    }
    /**
     * Given a FeatureSelection instance, return a new clone of this Features
     * instance using a restricted feature subset. The new Features instance
     * is augmented with a feature map that it can use to convert feature
     * indices from the larger feature set to indices for the reduced set.
     *
     * @param object $fs FeatureSelection instance to be used to select the
     * most informative terms
     * @return object new Features instance using the restricted feature set
     */
    public function restrict(FeatureSelection $fs)
    {
        $feature_map = $fs->select($this);
        /*
           Collect the top few most-informative features (if any). The features
           are inserted into the feature map by decreasing informativeness, so
           iterating through from the beginning will yield the most informative
           features first, excepting the very first one, which is guaranteed to
           be the intercept term.
         */
        $top_features = [];
        next($feature_map);
        for ($i = 0; $i < 5; $i++) {
            if (!(list($j) = each($feature_map))) {
                break;
            }
            $top_features[$j] = true;
        }
        $classname = get_class($this);
        $new_features = new $classname;
        foreach ($this->vocab as $term => $old_j) {
            if (isset($feature_map[$old_j])) {
                $new_j = $feature_map[$old_j];
                $new_features->vocab[$term] = $new_j;
                $new_features->var_freqs[$new_j] = $this->var_freqs[$old_j];
                // Get the actual term associated with a top feature.
                if (isset($top_features[$old_j])) {
                    $top_features[$old_j] = $term;
                }
            }
        }
        $new_features->label_freqs = $this->label_freqs;
        $new_features->feature_map = $feature_map;
        // Note that this preserves the order of top features.
        $new_features->top_terms = array_values($top_features);
        return $new_features;
    }
    /**
     * Maps the indices of a feature vector to those used by a restricted
     * feature set, dropping and features that aren't in the map. If this
     * Features instance isn't restricted, then the passed-in features are
     * returned unmodified.
     *
     * @param array $features feature vector mapping feature indices to
     * frequencies
     * @return array original feature vector with indices mapped
     * according to the feature_map property, and any features that don't
     * occcur in feature_map dropped
     */
    public function mapToRestrictedFeatures($features)
    {
        if (empty($this->feature_map)) {
            return $features;
        }
        $mapped_features = [];
        foreach ($features as $j => $count) {
            if (isset($this->feature_map[$j])) {
                $mapped_features[$this->feature_map[$j]] = $count;
            }
        }
        return $mapped_features;
    }
    /**
     * Given an array of feature vectors mapping feature indices to counts,
     * returns a sparse matrix representing the dataset transformed according
     * to the specific Features subclass. A Features subclass might use simple
     * binary features, but it might also use some form of TF * IDF, which
     * requires the full dataset in order to assign weights to particular
     * document features; thus the necessity of a map over the entire training
     * set prior to its input to a classification algorithm.
     *
     * @param array $docs array of training examples represented as feature
     *      vectors where the values are per-example counts
     * @return object SparseMatrix instance whose rows are the transformed
     *      feature vectors
     */
    abstract function mapTrainingSet($docs);
    /**
     * Maps a vector of terms mapped to their counts within a single document
     * to a transformed feature vector, exactly like a row in the sparse matrix
     * returned by mapTrainingSet. This method is used to transform a tokenized
     * document prior to classification.
     *
     * @param array $tokens associative array of terms mapped to their
     * within-document counts
     * @return array feature vector corresponding to the tokens, mapped
     * according to the implementation of a particular Features subclass
     */
    abstract function mapDocument($tokens);
}

