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
namespace seekquarry\yioop\library\compressors;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Implementation of a trivial Compressor.
 *
 * NonCompressor's compress and uncompress filter return the string unchanged
 *
 * @author Chris Pollett
 */
class NonCompressor implements Compressor
{
    /** Constructor does nothing
     */
    public function __construct() {}
    /**
     * Applies the Compressor compress filter to a string before it is inserted
     * into a WebArchive. In this case, the filter does nothing.
     *
     * @param string $str  string to apply filter to
     * @return string  the result of applying the filter
     */
    public function compress($str)
    {
        return $str;
    }
    /**
     * Used to unapply the compress filter as when data is read out of a
     * WebArchive. In this case, the unapplying filter does nothing.
     *
     * @param string $str  data read from a string archive
     * @return string result of uncompressing
     */
    public function uncompress($str)
    {
        return $str;
    }
    /**
     * Used to compress an int as a fixed length string in the format of
     * the compression algorithm underlying the compressor. Since this
     * compressor doesn't compress we just use pack
     *
     * @param int $my_int the integer to compress as a fixed length string
     * @return string the fixed length string containing the packed int
     */
    public function compressInt($my_int) {
        return L\packInt($my_int);
    }
    /**
     * Used to uncompress an int from a fixed length string in the format of
     * the compression algorithm underlying the compressor. Since this
     * compressor doesn't compress we just use unpack
     *
     * @param string $my_compressed_int the fixed length string containing
     *     the packed int to extract
     * @return int the integer contained in that string
     */
    public function uncompressInt($my_compressed_int) {
        return L\unpackInt($my_compressed_int);
    }
    /**
     * Computes the length of an int when packed using the underlying
     * compression algorithm as a fixed length string. The pack function
     * stores ints as 4 byte strings
     *
     * @return int length of int as a fixed length compressed string
     */
    public function compressedIntLen() {
        return 4;
    }
    /**
     * File extension that should be associated with this compressor
     * @return string name of dos file extension
     */
    public static function fileExtension()
    {
        return ".txt";
    }
}
