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
 * Implements the Naive Bayes text classification algorithm.
 *
 * This class also provides a method to sample a beta vector from a dataset,
 * making it easy to generate several slightly-different classifiers for the
 * same dataset in order to form classifier committees.
 *
 * @author Shawn Tice
 */
class NaiveBayes extends ClassifierAlgorithm
{
    /**
     * Parameter used to weight positive examples.
     * @var float
     */
    public $gamma = 1.0;
    /**
     * Parameter used to weight negative examples.
     * @var float
     */
    public $epsilon = 1.0;
    /**
     * Beta vector of feature weights resulting from the training phase. The
     * dot product of this vector with a feature vector yields the log
     * likelihood that the feature vector describes a document belonging to the
     * trained-for class.
     * @var array
     */
    public $beta;
    /**
     * Computes the beta vector from the given examples and labels. The
     * examples are represented as a sparse matrix where each row is an example
     * and each column a feature, and the labels as an array where each value
     * is either 1 or -1, corresponding to a positive or negative example. Note
     * that the first feature (column 0) corresponds to an intercept term, and
     * is equal to 1 for every example.
     *
     * @param object $X SparseMatrix of training examples
     * @param array $y example labels
     */
    public function train(SparseMatrix $X, $y)
    {
        $n = $X->columns();
        $p = array_fill(0, $n, 0);
        $a = array_fill(0, $n, 0);
        $this->beta = array_fill(0, $n, 0.0);
        $beta =& $this->beta;
        foreach ($X as $i => $row) {
            foreach ($row as $j => $Xij) {
                if ($y[$i] == 1) {
                    $p[$j] += 1;
                } else {
                    $a[$j] += 1;
                }
            }
        }
        $beta[0] = $this->logit($p[0], $a[0]);
        for ($j = 1; $j < $n; $j++) {
            $beta[$j] = $this->logit($p[$j], $a[$j]) - $beta[0];
        }
    }
    /**
     * Constructs beta by sampling from the Gamma distribution for each
     * feature, parameterized by the number of times the feature appears in
     * positive examples, with a scale/rate of 1. This function is used to
     * construct classifier committees.
     *
     * @param object $features Features instance for the training set, used to
     * determine how often a given feature occurs in positive and negative
     * examples
     */
    public function sampleBeta($features)
    {
        $p = [];
        $a = [];
        $n = $features->numFeatures();
        list($p[0], $a[0]) = $features->labelStats();
        for ($j = 1; $j <= $n; $j++) {
            $stats = $features->varStats($j, 1);
            list($t_l, $t_nl, $nt_l, $nt_nl) = $stats;
            $p[$j] = $this->sampleGammaDeviate(1 + $t_l);
            $a[$j] = $this->sampleGammaDeviate(1 + $t_nl);
        }
        $this->beta = [];
        $beta =& $this->beta;
        $beta[0] = $this->logit($p[0], $a[0]);
        for ($j = 1; $j <= $n; $j++) {
            $beta[$j] = $this->logit($p[$j], $a[$j]) - $beta[0];
        }
    }
    /**
     * Returns the pseudo-probability that a new instance is a positive example
     * of the class the beta vector was trained to recognize. It only makes
     * sense to try classification after at least some training
     * has been done on a dataset that includes both positive and negative
     * examples of the target class.
     *
     * @param array $x feature vector represented by an associative array
     * mapping features to their weights
     */
    public function classify($x)
    {
        $beta =& $this->beta;
        $l = 0.0;
        foreach ($x as $j => $xj) {
            /*
               The $x values are in {-1,1} instead of {0,1}, so we just
               manually skip what would be the zero terms.
            */
            if ($xj == 1)
                $l += $beta[$j];
        }
        return 1.0 / (1.0 + exp(-$l));
    }
    /* PRIVATE INTERFACE */
    /**
     * Computes the log odds of a numerator and denominator, corresponding to
     * the number of positive and negative examples exhibiting some feature.
     *
     * @param int $pos count of positive examples exhibiting some feature
     * @param int $neg count of negative examples
     * @return float log odds of seeing the feature in a positive example
     */
    public function logit($pos, $neg)
    {
        $odds = ($pos + $this->gamma) / ($neg + $this->epsilon);
        return log($odds);
    }
    /**
     * Computes a Gamma deviate with beta = 1 and integral, small alpha. With
     * these assumptions, the deviate is just the sum of alpha exponential
     * deviates. Each exponential deviate is just the negative log of a uniform
     * deviate, so the sum of the logs is just the negative log of the products
     * of the uniform deviates.
     *
     * @param int $alpha parameter to Gamma distribution (in practice, a count
     * of occurrences of some feature)
     * @return float a deviate from the Gamma distribution parameterized by
     * $alpha
     */
    public function sampleGammaDeviate($alpha)
    {
        $product = 1.0;
        $randmax = getrandmax();
        for ($i = 0; $i < $alpha; $i++) {
            $product *= rand() / $randmax;
        }
        return -log($product);
    }
}
