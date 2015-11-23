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
 * Implements the logistic regression text classification algorithm using lasso
 * regression and a cyclic coordinate descent optimization step.
 *
 * This algorithm is rather slow to converge for large datasets or a large
 * number of features, but it does provide regularization in order to combat
 * over-fitting, and out-performs Naive-Bayes in tests on the same data set.
 * The algorithm augments a standard cyclic coordinate descent approach by
 * ``sleeping'' features that don't significantly change during a single step.
 * Each time an optimization step for a feature doesn't change the feature
 * weight beyond some threshold, that feature is forced to sit out the next
 * optimization round. The threshold increases over successive rounds,
 * effectively placing an upper limit on the number of iterations over all
 * features, while simultaneously limiting the number of features updated on
 * each round. This optimization speeds up convergence, but at the cost of some
 * accuracy.
 *
 * @author Shawn Tice
 */
class LassoRegression extends ClassifierAlgorithm
{
    /**
     * Level of detail to be used for logging. Higher values mean more detail.
     * @var int
     */
    public $debug = 0;
    /**
     * Threshold used to determine convergence.
     * @var float
     */
    public $epsilon = 0.001;
    /**
     * Lambda parameter to CLG algorithm.
     * @var float
     */
    public $lambda = 1.0;
    /**
     * Beta vector of feature weights resulting from the training phase. The
     * dot product of this vector with a feature vector yields the log
     * likelihood that the feature vector describes a document belonging to the
     * trained-for class.
     * @var array
     */
    public $beta;
    /**
     * An adaptation of the Zhang-Oles 2001 CLG algorithm by Genkin et al. to
     * use the Laplace prior for parameter regularization. On completion,
     * optimizes the beta vector to maximize the likelihood of the data set.
     *
     * @param object $X SparseMatrix representing the training dataset
     * @param array $y array of known labels corresponding to the rows of $X
     */
    public function train($X, $y)
    {
        $invX = new InvertedData($X);
        $this->lambda = $this->estimateLambdaNorm($invX);
        $m = $invX->rows();
        $n = $invX->columns();
        $this->beta = array_fill(0, $n, 0.0);
        $beta =& $this->beta;
        $lambda = $this->lambda;
        $d = array_fill(0, $n, 1.0);
        $r = array_fill(0, $m, 0.0);
        $converged = false;
        $drSum = 0.0;
        $rSum = 0.0;
        $change = 0.0;
        $score = 0.0;
        $minDrj = $this->epsilon;
        $prevDrj = $this->epsilon;
        $schedule = new \SplMaxHeap();
        $nextSchedule = new \SplMaxHeap();
        for ($j = 0; $j < $n; $j++)
            $schedule->insert(array($this->epsilon, $j));
        for ($k = 0; !$converged; $k++) {
            $prevR = $r;
            $var = 1;
            while (!$schedule->isEmpty()) {
                list($drj, $j) = $schedule->top();
                if ($drj < $minDrj) {
                    break;
                } else {
                    $schedule->extract();
                    $prevDrj = $drj;
                }
                $Xj = $invX->iterateColumn($j);
                list($numer, $denom) = $this->computeApproxLikelihood(
                    $Xj, $y, $r, $d[$j]);
                // Compute tentative step $dvj
                if ($beta[$j] == 0) {
                    $dvj = ($numer - $lambda) / $denom;
                    if ($dvj <= 0) {
                        $dvj = ($numer + $lambda) / $denom;
                        if ($dvj >= 0)
                            $dvj = 0;
                    }
                } else {
                    $s = $beta[$j] > 0 ? 1 : -1;
                    $dvj = ($numer - ($s * $lambda)) / $denom;
                    if ($s * ($beta[$j] + $dvj) < 0)
                        $dvj = -$beta[$j];
                }
                if ($dvj == 0) {
                    $d[$j] /= 2;
                    $nextSchedule->insert(array($this->epsilon, $j, $k));
                } else {
                    // Compute delta for beta[j], constrained to trust region.
                    $dbetaj = min(max($dvj, -$d[$j]), $d[$j]);
                    // Update our cached dot product by the delta.
                    $drj = 0.0;
                    foreach ($Xj as $cell) {
                        list($_, $i, $Xij) = $cell;
                        $dr = $dbetaj * $Xij;
                        $drj += $dr;
                        $r[$i] += $dr;
                    }
                    $drj = abs($drj);
                    $nextSchedule->insert(array($drj, $j, $k));
                    $beta[$j] += $dbetaj;
                    // Update the trust region.
                    $d[$j] = max(2 * abs($dbetaj), $d[$j] / 2);
                }
                if ($this->debug > 1) {
                    $score = $this->score($r, $y, $beta);
                }
                $this->log(sprintf(
                    "itr = %3d, j = %4d (#%d), score = %6.2f, change = %6.4f",
                    $k + 1, $j, $var, $score, $change));
                $var++;
            }
            // Update $converged
            $drSum = 0.0;
            $rSum = 0.0;
            for ($i = 0; $i < $m; $i++) {
                $drSum += abs($r[$i] - $prevR[$i]);
                $rSum += abs($r[$i]);
            }
            $change = $drSum / (1 + $rSum);
            $converged = $change <= $this->epsilon;
            while (!$schedule->isEmpty()) {
                list($drj, $j) = $schedule->extract();
                $nextSchedule->insert(array($drj * 4, $j));
            }
            $tmp = $schedule;
            $schedule = $nextSchedule;
            $nextSchedule = $tmp;
            $minDrj *= 2;
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
        $l = 0.0;
        foreach ($x as $j => $xj) {
            $l += $xj * $this->beta[$j];
        }
        return 1.0 / (1.0 + exp(-$l));
    }
    /* PRIVATE INTERFACE */
    /**
     * Computes the approximate likelihood of y given a single feature, and
     * returns it as a pair <numerator, denominator>.
     *
     * @param object $Xj iterator over the non-zero entries in column j of the
     * data
     * @param array $y labels corresponding to entries in $Xj; each label is 1
     * if example i has the target label, and -1 otherwise
     * @param array $r cached dot products of the beta vector and feature
     * weights for each example i
     * @param float $d trust region for feature j
     * @return array two-element array containing the numerator and denominator
     * of the likelihood
     */
    public function computeApproxLikelihood($Xj, $y, $r, $d)
    {
        $numer = 0.0;
        $denom = 0.0;
        foreach ($Xj as $cell) {
            list($j, $i, $Xij) = $cell;
            $yi = $y[$i];
            $ri = $yi * $r[$i];
            $a = abs($ri);
            $b = abs($d * $Xij);
            if ($a <= $b) {
                $F = 0.25;
            } else {
                $e = exp($a - $b);
                $F = 1.0 / (2.0 + $e + (1.0/$e));
            }
            $numer += $Xij * $yi / (1 + exp($ri));
            $denom += $Xij * $Xij * $F;
        }
        return [$numer, $denom];
    }
    /**
     * Computes an approximate score that can be used to get an idea of how
     * much a given optimization step improved the likelihood of the data set.
     *
     * @param array $r cached dot products of the beta vector and feature
     * weights for each example i
     * @param array $y labels for each example
     * @param array $beta beta vector of feature weights (used to
     * penalize large weights)
     * @return float value proportional to the likelihood of the data,
     * penalized by the magnitude of the beta vector
     */
    public function score($r, $y, $beta)
    {
        $score = 0;
        foreach ($r as $i => $ri)
            $score += -log(1 + exp(-$ri * $y[$i]));
        return $score - array_sum($beta);
    }
    /**
     * Estimates the lambda parameter from the dataset.
     *
     * @param object $invX inverted X matrix for dataset (essentially a posting
     * list of features in X)
     * @return float lambda estimate
     */
    public function estimateLambdaNorm($invX)
    {
        $sqNorm = 0;
        foreach ($invX->iterateData() as $entry) {
            $Xij = $entry[2];
            $sqNorm += $Xij * $Xij;
        }
        $m = $invX->rows();
        $n = $invX->columns();
        $sigmaSq = $n * $m / $sqNorm;
        return sqrt(2) / sqrt($sigmaSq);
    }
}
/**
 * Stores a data matrix in an inverted index on columns with non-zero entries.
 *
 * The index is just an array of entries <j, i, X[i][j]> sorted first by j and
 * then by i, where all X[i][j] > 0. Provides a method to iterate over all rows
 * which have a non-zero entry for a particular column (feature) j. There is
 * no efficient way to iterate over rows in order.
 *
 * @author Shawn Tice
 */
class InvertedData
{
    /**
     * Number of rows in the matrix.
     * @var int
     */
    public $rows;
    /**
     * Number of columns in the matrix.
     * @var int
     */
    public $columns;
    /**
     * Array of non-zero matrix entries.
     * @var array
     */
    public $data;
    /**
     * Array of offsets into the $data array, where each offset gives the start
     * of the entries for a particular feature.
     * @var array
     */
    public $index;
    /**
     * Converts a SparseMatrix into an InvertedData instance. The data is
     * duplicated.
     *
     * @param object $X SparseMatrix instance to convert
     */
    public function __construct(SparseMatrix $X)
    {
        $this->rows = $X->rows();
        $this->columns = $X->columns();
        $this->data = [];
        $this->index = [];

        foreach ($X as $i => $row) {
            foreach ($row as $j => $Xij) {
                $this->data[] = [$j, $i, $Xij];
            }
        }
        sort($this->data);
        $lastVar = -1;
        foreach ($this->data as $dataOffset => $x) {
            $currVar = $x[0];
            if ($currVar != $lastVar) {
                for ($var = $lastVar + 1; $var <= $currVar; $var++)
                    $this->index[$var] = $dataOffset;
                $lastVar = $currVar;
            }
        }
    }
    /**
     * Accessor method which the number of rows in the matrix
     * @return number of rows
     */
    public function rows()
    {
        return $this->rows;
    }
    /**
     * Accessor method which the number of columns in the matrix
     * @return number of columns
     */
    public function columns()
    {
        return $this->columns;
    }
    /**
     * Returns an iterator over the values for a particular column of the
     * matrix. If no matrix entry in the column is non-zero then an empty
     * iterator is returned.
     *
     * @param into $j feature index (column) to iterate over
     * @return object iterator over values in the column
     */
    public function iterateColumn($j)
    {
        $start = $this->index[$j];
        if ($j < count($this->index) - 1)
            $count = $this->index[$j + 1] - $start;
        else
            $count = -1;
        if ($count != 0) {
            $arr_itr = new \ArrayIterator($this->data);
            return new \LimitIterator($arr_itr, $start, $count);
        }
        return new \EmptyIterator();
    }
    /**
     * Returns an iterator over the entire matrix. Note that this iterator is
     * not in row order, but effectively in column order.
     *
     * @return object iterator over every non-zero entry in the matrix
     */
    public function iterateData()
    {
        return new \ArrayIterator($this->data);
    }
}
