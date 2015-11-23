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
 * This is an abstract class that specifies an interface for selecting top
 * features from a dataset.
 *
 * Each FeatureSelection class implements a select method that takes a Features
 * instance and returns a mapping from a subset of the old feature indices to
 * new ones.
 *
 * @author Shawn Tice
 */
abstract class FeatureSelection
{
    /**
     * Sets any passed runtime parameters.
     *
     * @param array $parameters optional associative array of parameters to
     * replace the default ones with
     */
    public function __construct($parameters = [])
    {
        foreach ($parameters as $parameter => $value) {
            $this->$parameter = $value;
        }
    }
    /**
     * Constructs a map from old feature indices to new ones according to a
     * max-heap of the most informative features. Always keep feature index 0,
     * which is used as an intercept term.
     *
     * @param object $selected max heap containing entries ordered by
     * informativeness and feature index.
     * @return array associative array mapping a subset of the original feature
     * indices to the new indices
     */
    public function buildMap($selected)
    {
        $keep_features = [0 => 0];
        $i = 1;
        while (!$selected->isEmpty()) {
            list($chi2, $j) = $selected->extract();
            $keep_features[$j] = $i++;
        }
        return $keep_features;
    }
    /**
     * Computes the top features of a Features instance, and returns a mapping
     * from a subset of those features to new contiguous indices. The mapping
     * allows documents that have already been mapped into the larger feature
     * space to be converted to the smaller feature space, while keeping the
     * feature indices contiguous (e.g., 1, 2, 3, 4, ... instead of 22, 35, 75,
     * ...).
     *
     * @param object $features Features instance
     * @return array associative array mapping a subset of the original feature
     * indices to new indices
     */
    abstract function select(Features $features);
}

