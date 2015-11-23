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
 * A subclass of FeatureSelection that implements chi-squared feature
 * selection.
 *
 * This feature selection method scores each feature according to its
 * informativeness, then selects the top N most informative features, where N
 * is a run-time parameter.
 *
 * @author Shawn Tice
 */
class ChiSquaredFeatureSelection extends FeatureSelection
{
    /**
     * The maximum number of features to select, a runtime parameter.
     * @var int
     */
    public $max;
    /**
     * Uses the chi-squared feature selection algorithm to rank features by
     * informativeness, and return a map from old feature indices to new ones.
     *
     * @param object $features full feature set
     * @return array associative array mapping a subset of the original feature
     * indices to new indices
     */
    public function select(Features $features)
    {
        $n = $features->numFeatures();
        $selected = new \SplMinHeap();
        $allowed = isset($this->max) ? min($this->max, $n) : $n;
        $labels = [-1, 1];
        /*
           Start with 1, since 0 is dedicated to the constant intercept term;
           <= $n because n is the last feature.
         */
        for ($j = 1; $j <= $n; $j++) {
            $max_chi2 = 0.0;
            foreach ($labels as $label) {
                /*
                   t = term present
                   l = document has label
                   n = negation
                 */
                $stats = $features->varStats($j, $label);
                list($t_l, $t_nl, $nt_l, $nt_nl) = $stats;
                $num = ($t_l * $nt_nl) - ($t_nl * $nt_l);
                $den = ($t_l + $t_nl) * ($nt_l + $nt_nl);
                $chi2 = $den != 0 ? ($num * $num) / $den : INF;
                if ($chi2 > $max_chi2) {
                    $max_chi2 = $chi2;
                }
            }
            /*
               Keep track of top features in a heap, as we compute
               informativeness.
             */
            if ($allowed > 0) {
                $selected->insert(array($max_chi2, $j));
                $allowed -= 1;
            } else {
                list($other_chi2, $_) = $selected->top();
                if ($max_chi2 > $other_chi2) {
                    $selected->extract();
                    $selected->insert(array($max_chi2, $j));
                }
            }
        }
        return $this->buildMap($selected);
    }
}
