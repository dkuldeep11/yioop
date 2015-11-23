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
namespace seekquarry\yioop\library;

/**
 * For packInt/unpackInt
 */
require_once __DIR__."/Utility.php";

/**
 * Code used to manage a bloom filter in-memory and in file.
 * A Bloom filter is used to store a set of objects.
 * It can support inserts into the set and it can also be
 * used to check membership in the set.
 *
 * @author Chris Pollett
 */
class BloomFilterFile extends PersistentStructure
{
    /**
     * Number of bit positions in the Bloom filter used to say an item is
     * in the filter
     * @var int
     */
    public $num_keys;
    /**
     * Size in bits of the packed string array used to store the filter's
     * contents
     * @var int
     */
    public $filter_size;
    /**
     * Packed string used to store the Bloom filters
     * @var string
     */
    public $filter;
    /**
     * Initializes the fields of the BloomFilter and its base
     * PersistentStructure.
     *
     * @param string $fname name of the file to store the BloomFilter data in
     * @param int $num_values the maximum number of values that will be stored
     *     in the BloomFilter. Filter will be sized so the odds of a false
     *     positive are roughly one over this value
     * @param int $save_frequency how often to store the BloomFilter to disk
     */
    public function __construct($fname, $num_values,
        $save_frequency = self::DEFAULT_SAVE_FREQUENCY)
    {
        $log2 = log(2);
        $this->num_keys = ceil(log($num_values)/$log2);
        $this->filter_size = ceil( ($this->num_keys) * $num_values/$log2 );
        $mem_before =  memory_get_usage(true);
        $this->filter = pack("x". ceil(0.125 * $this->filter_size));
            // 1/8 =.125 = num bits/bytes, want to make things floats
        $mem = memory_get_usage(true) - $mem_before;
        parent::__construct($fname, $save_frequency);
    }
    /**
     * Inserts the provided item into the Bloomfilter
     *
     * @param string $value item to add to filter
     */
    public function add($value)
    {
        $num_keys = $this->num_keys;
        $pos_array = $this->getHashBitPositionArray($value, $num_keys);
        for ($i = 0;  $i < $num_keys; $i++) {
            $this->setBit($pos_array[$i]);
        }
        $this->checkSave();
    }
    /**
     * Checks if the BloomFilter contains the provided $value
     *
     * @param string $value item to check if is in the BloomFilter
     * @return bool whether $value was in the filter or not
     */
    public function contains($value)
    {
        $num_keys = $this->num_keys;
        $pos_array = $this->getHashBitPositionArray($value, $num_keys);
        for ($i = 0;  $i < $num_keys; $i++) {
            if (!$this->getBit($pos_array[$i])) {
                return false;
            }
        }
        return true;
    }
    /**
     * Hashes $value to a bit position in the BloomFilter
     *
     * @param string $value value to map to a bit position in the filter
     * @param int $num_keys number of bit positions in the Bloom filter
     *      used to say an item isin the filter
     * @return int the bit position mapped to
     */
    public function getHashBitPositionArray($value, $num_keys)
    {
        $offset = ($num_keys >> 2) + 1;
        $rand_string = "";
        for ($i = 0 ; $i < $offset; $i++) {
            $value = md5($value, true);
            $rand_string .= $value;
        }
        $seed = array_values(unpack("N*", $rand_string));
        $pos_array = [];
        $size = $this->filter_size >> 1;
        $less_one = $size - 1;
        for ($i = 0; $i < $num_keys; $i++) {
            $pos_array[$i] = ($seed[$i] % $size) + $less_one;
        }
        return $pos_array;
    }
    /**
     * Sets to true the ith bit position in the filter.
     *
     * @param int $i the position to set to true
     */
    public function setBit($i)
    {
        $byte = ($i >> 3);
        $bit_in_byte = $i - ($byte << 3);
        $tmp = $this->filter[$byte];
        $this->filter[$byte] = $tmp | chr(1 << $bit_in_byte);
    }
    /**
     * Looks up the value of the ith bit position in the filter
     *
     * @param int $i the position to look up
     * @return bool the value of the looked up position
     */
    public function getBit($i)
    {
        $byte = $i >> 3;
        $bit_in_byte = $i - ($byte << 3);
        return ($this->filter[$byte] & chr(1 << $bit_in_byte)) != chr(0);
    }
}
