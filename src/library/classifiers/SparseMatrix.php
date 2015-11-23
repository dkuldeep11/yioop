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
 * A sparse matrix implementation based on an associative array of associative
 * arrays.
 *
 * A SparseMatrix is mostly a wrapper around an array of arrays, but it keeps
 * track of some extra information such as the true matrix dimensions, and the
 * number of non-zero entries. It also provides a convenience method for
 * partitioning the matrix rows into two new sparse matrices.
 *
 * @author Shawn Tice
 */
class SparseMatrix implements \Iterator //Iterator is built-in to PHP
{
    /**
     * The number of rows, regardless of whether or not some are empty.
     * @var int
     */
    public $m;
    /**
     * The number of columns, regardless of whether or not some are empty.
     * @var int
     */
    public $n;
    /**
     * The number of non-zero entries.
     * @var int
     */
    public $nonzero = 0;
    /**
     * The actual matrix data, an associative array mapping row indices to
     * associative arrays mapping column indices to their values.
     * @var array
     */
    public $data;
    /**
     * Initializes a new sparse matrix with specific dimensions.
     *
     * @param int $m number of rows
     * @param int $n number of columns
     */
    public function __construct($m, $n)
    {
        $this->m = $m;
        $this->n = $n;
        $this->data = [];
    }
    /**
     * Accessor method which the number of rows in the matrix
     * @return number of rows
     */
    public function rows()
    {
        return $this->m;
    }
    /**
     * Accessor method which the number of columns in the matrix
     * @return number of columns
     */
    public function columns()
    {
        return $this->n;
    }
    /**
     * Accessor method which the number of nonzero entries in the matrix
     * @return number of nonzero entries
     */
    public function nonzero()
    {
        return $this->nonzero;
    }
    /**
     * Sets a particular row of data, keeping track of any new non-zero
     * entries.
     *
     * @param int $i row index
     * @param array $row associative array mapping column indices to values
     */
    public function setRow($i, $row)
    {
        $this->data[$i] = $row;
        $this->nonzero += count($row);
    }
    /**
     * Given two sets of row indices, returns two new sparse matrices
     * consisting of the corresponding rows.
     *
     * @param array $a_indices row indices for first new sparse matrix
     * @param array $b_indices row indices for second new sparse matrix
     * @return array array with two entries corresponding to the first and
     * second new matrices
     */
    public function partition($a_indices, $b_indices)
    {
        $a = new SparseMatrix(count($a_indices), $this->n);
        $b = new SparseMatrix(count($b_indices), $this->n);
        $new_i = 0;
        foreach ($a_indices as $i) {
            $a->setRow($new_i++, $this->data[$i]);
        }
        $new_i = 0;
        foreach ($b_indices as $i) {
            $b->setRow($new_i++, $this->data[$i]);
        }
        return [$a, $b];
    }
    /* Iterator Interface */
    /**
     *  Resets the iterator
     */
    public function rewind() { reset($this->data); }
    /**
     * Returns the current iterated over row
     * @return array current row
     */
    public function current() { return current($this->data); }
    /**
     * Returns the index of the current row
     * @return int index of row
     */
    public function key() { return key($this->data); }
    /**
     * Returns the next row to be iterated over
     * @return array next row
     */
    public function next() { return next($this->data); }
    /**
     * Whether the current key position is not null
     * @return bool whether it is null or not
     */
    public function valid() { return !is_null(key($this->data)); }
}

