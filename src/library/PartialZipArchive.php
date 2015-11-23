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
 * Used to extract files from an initial segment or a fragment of a
 * ZIP Archive.
 *
 * @author Chris Pollett
 */
class PartialZipArchive
{
    /**
     * Stores path/filename -> (compression type, compressed file) associations
     * for all files in the archive that were extractable from the given
     * zip archive fragment
     * @var array
     */
    public $zip_directory = [];
    /**
     * Stores path/filenames that were discovered in the initial segment of
     * this zip archive
     * @var array
     */
    public $zip_file_names = [];
    /** ZIP code to indicate compression type is no compression used*/
    const NO_COMPRESSION = 0;
    /** ZIP code to indicate compression type is deflate*/
    const DEFLATE = 8;
    /** ZIP code to indicate compression type is enhanced deflate (4gb barrier
     *  passable)
     */
    const ENHANCED_DEFLATE = 9;
    /** Byte string to indicate start of a local file header, used to find
     *  locations of all the files stored in ZIP fragment we have
     */
    const LOCAL_FILE_HEADER = "\x50\x4B\x03\x04";
    /**
     * Sets up a PartialZipArchive so that files can be extracted from it.
     * To this it populates the two field variables @see $zip_directory
     * and @see $zip_file_names. Offsets used in the code for extracting
     * various fields out of a zip archive local file header were gotten
     * from https://en.wikipedia.org/wiki/ZIP_%28file_format%29
     * Note the code for the constructor justs splits the whole string into
     * parts on the string @see LOCAL_FILE_HEADER. It doesn't bother to try
     * to use the zip archive's directory (which might not be in the portion
     * of this zip archive given). It is possible for a file contained
     * in archive to actual have within it the string LOCAL_FILE_HEADER, in
     * which case that file would be screwed up by our approach.
     *
     * @param string $zip_string a substring of a zip archive file
     */
    public function __construct($zip_string)
    {
        $sub_files = explode(self::LOCAL_FILE_HEADER, $zip_string);
        foreach ($sub_files as $sub_file) {
            if (!$sub_file) {continue;}
            $len_string = substr($sub_file, 22, 2);
            $file_name_len = (ord($len_string[1]) << 8) + ord($len_string[0]);
            $len_string = substr($sub_file, 24, 2);
            $extra_field_len = (ord($len_string[1]) << 8) + ord($len_string[0]);
            $file_start = 26 + $file_name_len + $extra_field_len;
            $len_string = substr($sub_file, 14, 4);
            $file_size = (((((ord($len_string[3]) << 8) +
                ord($len_string[2])) << 8) + ord($len_string[1])) << 8) +
                ord($len_string[0]);
            $file_string = substr($sub_file, $file_start, $file_size);
            if (strlen($file_string) < $file_size) {continue; }
            $compression = ord($sub_file[4]);
            $file_name = substr($sub_file, 26, $file_name_len);
            if ($file_name && $file_string) {
                $this->zip_directory[$file_name] = [$compression, $file_string];
                $this->zip_file_names[] = $file_name;
            }
        }
    }
    /**
     * Returns the total number of files that were detected in the zip archive
     * fragment.
     *
     * @return int number of files found in archive
     */
    public function numFiles()
    {
        return count($this->zip_file_names);
    }
    /**
     * Returns the file name for the ith file that was extractable from
     * the archive string used in the constructor.
     *
     * @param int $index the number of file want
     * @return string its corresponding file name
     */
    public function getNameIndex($index)
    {
        if (isset($this->zip_file_names[$index])) {
            return $this->zip_file_names[$index];
        }
        return false;
    }
    /**
     * Returns from the PartialZipArchive the uncompressed contents of
     * the provided path/filename if found, and false otherwise.
     *
     * @param string $file_name contains complete path and file_name of afile
     * @return mixed uncompressed file contents if found and extractable,
     *      false otherwise
     */
    public function getFromName($file_name)
    {
        if (!isset($this->zip_directory[$file_name])) {return false; }
        list($compression, $file_string) = $this->zip_directory[$file_name];
        switch ($compression)
        {
            case self::NO_COMPRESSION:
                return $file_string;
            break;
            case self::DEFLATE:
            case self::ENHANCED_DEFLATE:
                return gzinflate($file_string);
            break;
        }
        return false;
    }
}
