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
namespace seekquarry\yioop\library\archive_bundle_iterators;

use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\Bzip2BlockIterator;

/** For webencode */
require_once __DIR__.'/../Utility.php';
/**
 * Used to iterate through the records of a collection of text or compressed
 * text-oriented records
 *
 * @author Chris Pollett
 * @see WebArchiveBundle
 */
class TextArchiveBundleIterator extends ArchiveBundleIterator
{
    /**
     * The path to the directory containing the archive partitions to be
     * iterated over.
     * @var string
     */
    public $iterate_dir;
    /**
     * The number of arc files in this arc archive bundle
     * @var int
     */
    public $num_partitions;
    /**
     * Counting in glob order for this arc archive bundle directory, the
     * current active file number of the arc file being process.
     *
     * @var int
     */
    public $current_partition_num;
    /**
     * current number of pages into the current arc file
     * @var int
     */
    public $current_page_num;
    /**
     * current byte offset into the current arc file
     * @var int
     */
    public $current_offset;
    /**
     * Array of filenames of arc files in this directory (glob order)
     * @var array
     */
    public $partitions;
    /**
     * File handle for current archive file
     * @var resource
     */
    public $fh;
    /**
     * Used to buffer data from the currently opened file
     * @var string
     */
    public $buffer;

    /**
     * Starting delimiters for records
     * @var string
     */
    public $start_delimiter;

    /**
     * Ending delimiters for records
     * @var string
     */
    public $end_delimiter;

    /**
     * File name to write this archive iterator status messages to
     * @var string
     */
    public $status_filename;

    /**
     * If gzip is being used a buffer file is also employed to
     * try to reduce the number of calls to gzseek. $buffer_fh is a
     * filehandle for the buffer file
     *
     * @var resource
     */
    public $buffer_fh;
    /**
     * Which block of self::BUFFER_SIZE from the current archive
     * file is stored in the file $this->buffer_filename
     *
     * @var int
     */
    public $buffer_block_num;
    /**
     * Name of a buffer file to be used to reduce gzseek calls in the
     * case where gzip compression is being used
     *
     * @var string
     */
    public $buffer_filename;
    /**
     * Name of function to be call whenever the partition is changed
     * that the iterator is reading. The point of the callback is to
     * read meta information at the start of the new partition
     *
     * @var string
     */
    public $switch_partition_callback_name = null;
    /**
     * Contains basic parameters of how this iterate works: compression,
     * start and stop delimiter. Typically, this data is read from the
     * arc_description.ini file
     *
     * @var array
     */
    public $ini;
    /**
     * How many bytes at a time should be read from the current archive
     * file into the buffer file. 8192 = BZip2BlockIteraror::BlOCK_SIZE
     */
    const BUFFER_SIZE = 16384000;
    /**
     * Estimate of the maximum size of a record stored in a text archive
     * Data in archives is split into chunk of buffer size plus two record
     * sizes. This is used to provide a two record overlap between successive
     * chunks. This si further used to ensure that records that go over
     * the basic chunk boundary of BUFFER_SIZE will be processed.
     */
    const MAX_RECORD_SIZE = 49152;

    /**
     * Creates an text archive iterator with the given parameters.
     *
     * @param string $iterate_timestamp timestamp of the arc archive bundle to
     *     iterate  over the pages of
     * @param string $iterate_dir folder of files to iterate over. If this
     *     iterator is used in a fetcher and the data is on a name server
     *     set this to false
     * @param string $result_timestamp timestamp of the arc archive bundle
     *     results are being stored in
     * @param string $result_dir where to write last position checkpoints to
     * @param array $ini describes start_ and end_delimiter, file_extension,
     *     encoding, and compression method used for pages in this archive
     */
    public function __construct($iterate_timestamp, $iterate_dir,
        $result_timestamp, $result_dir, $ini = [])
    {
        $this->iterate_timestamp = $iterate_timestamp;
        $this->iterate_dir = $iterate_dir;
        $this->result_timestamp = $result_timestamp;
        $this->result_dir = $result_dir;
        if (!file_exists($result_dir)) {
            mkdir($result_dir);
        }
        $this->partitions = [];
        if ($this->iterate_dir != false) { // false = network/fetcher iterator
            if ($ini == []) {
                $ini = L\parse_ini_with_fallback(
                    "{$this->iterate_dir}/arc_description.ini");
            }
            $extension = $ini['file_extension'];
        }
        $this->setIniInfo($ini);
        if ($this->start_delimiter == "" && $this->end_delimiter == "" &&
            $this->iterate_dir != false) {
            L\crawlLog("At least one of start or end delimiter must be set!!");
            exit();
        }
        if ($this->iterate_dir != false) {
            foreach (glob("{$this->iterate_dir}/*.$extension", GLOB_BRACE)
                as $filename) {
                $this->partitions[] = $filename;
            }
        }
        $this->num_partitions = count($this->partitions);
        $this->status_filename = "{$this->result_dir}/iterate_status.txt";
        $this->buffer_filename = $this->result_dir."/buffer.txt";

        if (file_exists($this->status_filename)) {
            $this->restoreCheckpoint();
        } else {
            $this->reset();
        }
    }
    /**
     * Mutator Method for controller how this text archive iterator behaves
     * Normally, data, on compression, start, stop delimiter read from an ini
     * file. This reads it from the supplied array.
     *
     * @param array $ini configuration settings for this archive iterator
     */
    public function setIniInfo($ini)
    {
        $this->ini = $ini;
        if (isset($ini['compression'])) {
            $this->compression = $ini['compression'];
        } else {
            $this->compression = "plain";
        }
        if (isset($ini['start_delimiter'])) {
            $this->start_delimiter = L\addRegexDelimiters(
                $ini['start_delimiter']);
        } else {
            $this->start_delimiter = "";
        }
        if (isset($ini['end_delimiter'])) {
            $this->end_delimiter = L\addRegexDelimiters($ini['end_delimiter']);
        } else {
            $this->end_delimiter = "";
        }
        if ($this->end_delimiter == "") {
            $this->delimiter = $this->start_delimiter;
        } else {
            $this->delimiter = $this->end_delimiter;
        }
        if (isset($ini['encoding'])) {
            $this->encoding = $ini['encoding'];
        } else {
            $this->encoding = "UTF-8";
        }
    }
    /**
     * Estimates the important of the site according to the weighting of
     * the particular archive iterator
     * @param $site an associative array containing info about a web page
     * @return bool false we assume arc files were crawled according to
     *     OPIC and so we use the default doc_depth to estimate page importance
     */
    public function weight(&$site)
    {
        return false;
    }
    /**
     * Resets the iterator to the start of the archive bundle
     */
    public function reset()
    {
        $this->current_partition_num = -1;
        $this->end_of_iterator = false;
        $this->current_offset = 0;
        $this->fh = null;
        $this->buffer_fh = null;
        $this->buffer_block_num = 0;
        $this->bz2_iterator = null;
        $this->buffer = "";
        $this->header = [];
        $this->remainder = "";
        if (file_exists($this->status_filename)) {
            unlink($this->status_filename);
        }
        if (file_exists($this->buffer_filename)) {
            unlink($this->buffer_filename);
        }
    }
    /**
     * Called to get the next chunk of BUFFER_SIZE + 2 MAX_RECORD_SIZE bytes
     * of data from the text archive. This data is returned unprocessed in
     * self::ARC_DATA together with ini and header information about the
     * archive. This method is typically called in the name server setting
     * from FetchController.
     *
     * @return array with contents as described above
     */
    public function nextChunk()
    {
        $info = [];
        $info[self::START_PARTITION] = false;
        if (!$this->checkFileHandle() || $this->checkEof()) {
            $this->updatePartition($info);
        }
        $info[self::INI] = $this->ini;
        $info[self::HEADER] = $this->header;
        $info[self::ARC_DATA] = $this->updateBuffer("", true);
        if (!$info[self::ARC_DATA]) {
            $this->updatePartition($info);
            $info[self::ARC_DATA] = $this->updateBuffer("", true);
        }
        $info[self::NUM_PARTITIONS] = $this->num_partitions;
        $info[self::PARTITION_NUM] = $this->current_partition_num;
        $this->saveCheckpoint();
        return $info;
    }
    /**
     * Helper function for nextChunk to advance the parition if we are
     * at the end of the current archive file
     *
     * @param array& $info a struct with data about current chunk. will up start
     *     partition flag
     */
    public function updatePartition(&$info)
    {
        $this->current_partition_num++;
        if ($this->current_partition_num >= $this->num_partitions) {
            $this->end_of_iterator = true;
            return false;
        }
        $this->fileOpen(
            $this->partitions[$this->current_partition_num]);
        if ($this->switch_partition_callback_name != null) {
            $callback_name = $this->switch_partition_callback_name;
            $result = $this->$callback_name();
        }
        $info[self::START_PARTITION] = true;
    }
    /**
     * Gets the next at most $num many docs from the iterator. It might return
     * less than $num many documents if the partition changes or the end of the
     * bundle is reached.
     *
     * @param int $num number of docs to get
     * @param bool $no_process if true then just an array of page strings found
     *     not any additional meta data.
     * @return array associative arrays for $num pages
     */
    public function nextPages($num, $no_process = false)
    {
        $pages = [];
        $page_count = 0;
        for ($i = 0; $i < $num; $i++) {
            L\crawlTimeoutLog("..Still getting pages from archive iterator. ".
                "At %s of %s", $i, $num);
            $page = $this->nextPage($no_process);
            if (!$page) {
                if ($this->checkFileHandle()) {
                    $this->fileClose();
                }
                if (!$this->iterate_dir) { //fetcher local case
                    $this->current_offset = self::BUFFER_SIZE +
                        self::MAX_RECORD_SIZE;
                    break;
                }
                $this->current_partition_num++;
                if ($this->current_partition_num >= $this->num_partitions) {
                    $this->end_of_iterator = true;
                    break;
                }
                $this->fileOpen(
                    $this->partitions[$this->current_partition_num]);
                if ($this->switch_partition_callback_name != null) {
                    $callback_name = $this->switch_partition_callback_name;
                    $result = $this->$callback_name();
                    if (!$result) { break; }
                }
                $page = $this->nextPage($no_process);
                if (!$page) {continue; }
            }
            $pages[] = $page;
            $this->current_offset = $this->fileTell();
            $this->current_page_num++;
        }
        $this->saveCheckpoint();
        return $pages;
    }
    /**
     * Gets the next doc from the iterator
     * @param bool $no_process if true then just return page string found
     *     not any additional meta data.
     * @return mixed associative array for doc or just string of doc
     */
    public function nextPage($no_process = false)
    {
        if (!$this->checkFileHandle()) { return null; }
        $matches = [];
        while((preg_match($this->delimiter, $this->buffer, $matches,
            PREG_OFFSET_CAPTURE)) != 1) {
            L\crawlTimeoutLog("..still looking for a page in local buffer");
            $block = $this->getFileBlock();
            if (!$block ||
                !$this->checkFileHandle() || $this->checkEof()) {
                return null;
            }
            $this->buffer .= $block;
        }
        $delim_len = strlen($matches[0][0]);
        $pos = $matches[0][1] + $delim_len;
        $page_pos = ($this->start_delimiter == "") ? $pos : $pos - $delim_len;
        $page = substr($this->buffer, 0, $page_pos);
        if ($this->end_delimiter == "") {
            $page = $this->remainder . $page;
            $this->remainder = $matches[0][0];
        }
        $this->buffer = substr($this->buffer, $pos + $delim_len);
        if ($this->start_delimiter != "") {
            $matches = [];
            if ((preg_match($this->start_delimiter, $this->buffer, $matches,
                PREG_OFFSET_CAPTURE)) != 1) {
                if (isset($matches[0][1])) {
                    $page = substr($page, $matches[0][1]);
                }
            }
        }
        if ($no_process == true) {return $page; }
        $site = [];
        $site[self::HEADER] = "TextArchiveBundleIterator extractor";
        $site[self::IP_ADDRESSES] = ["0.0.0.0"];
        $site[self::TIMESTAMP] = date("U", time());
        $site[self::TYPE] = "text/plain";
        $site[self::PAGE] = $page;
        $site[self::HASH] = FetchUrl::computePageHash($page);
        $site[self::URL] = "record:".L\webencode($site[self::HASH]);
        $site[self::HTTP_CODE] = 200;
        $site[self::ENCODING] = $this->encoding;
        $site[self::SERVER] = "unknown";
        $site[self::SERVER_VERSION] = "unknown";
        $site[self::OPERATING_SYSTEM] = "unknown";
        $site[self::WEIGHT] = 1;
        return $site;
    }
    /**
     * Reads and return the block of data from the current partition
     * @return mixed a uncompressed string from the current partitin
     *     or null if iterator not set up, or false if EOF reached.
     */
    public function getFileBlock()
    {
        $block = null;
        return $this->fileGets();
    }
    /**
     * Acts as gzread($num_bytes, $archive_file), hiding the fact that
     * buffering of the archive_file is being done to a buffer file
     *
     * @param int $num_bytes to read from archive file
     * @return string of length up to $num_bytes (less if eof occurs)
     */
    public function fileRead($num_bytes)
    {
        $len = 0;
        $read_string = "";
        do {
            $read_string .= fread($this->buffer_fh, $num_bytes - $len);
            $len += strlen($read_string);
        } while($len < $num_bytes && $this->updateBuffer());
        return $read_string;
    }
    /**
     * Acts as gzgets(), hiding the fact that
     * buffering of the archive_file is being done to a buffer file
     *
     * @return string from archive file up to next line ending or eof
     */
    public function fileGets()
    {
        $len = 0;
        $read_string = "";
        do {
            $read_string .= fgets($this->buffer_fh);
        } while(feof($this->buffer_fh) && $this->updateBuffer());
        return $read_string;
    }
    /**
     * If reading from a gzbuffer file goes off the end of the current
     * buffer, reads in the next block from archive file.
     * @param string $buffer
     * @param bool $return_string
     * @return bool whether successfully read in next block or not
     */
    public function updateBuffer($buffer= "", $return_string = false)
    {
        if (!$this->iterate_dir) { //network case
            return false;
        }
        $this->buffer_block_num++;
        return $this->makeBuffer($buffer, $return_string);
    }
    /**
     * Reads in block $this->buffer_block_num of size self::BUFFER_SIZE from
     * the archive file
     *
     * @param string $buffer
     * @param bool $return_string
     * @return mixed whether successfully read in block or not
     */
    public function makeBuffer($buffer= "", $return_string = false)
    {
        if ($buffer == "") {
            if (!$this->checkFileHandle()) { return false; }
            $success = 1;
            $seek_pos = $this->buffer_block_num * self::BUFFER_SIZE;
            if ($this->compression == "gzip") {
                $success = gzseek($this->fh, $seek_pos);
            }
            if ($this->compression == "plain") {
                $success = fseek($this->fh, $seek_pos);
            }
            if ($success == -1 || !$this->checkFileHandle()
                || $this->checkEof()) { return false; }
            if (is_resource($this->buffer_fh)) {
                fclose($this->buffer_fh);
            }
            $padded_buffer_size = self::BUFFER_SIZE + 2*self::MAX_RECORD_SIZE;
            switch ($this->compression) {
                case 'bzip2':
                    $buffer = "";
                    while(strlen($buffer) < $padded_buffer_size) {
                        while(!is_string($block =
                            $this->bz2_iterator->nextBlock())) {
                            if ($this->bz2_iterator->eof()) {
                                break;
                            }
                        }
                        $buffer .= $block;
                        if ($this->bz2_iterator->eof()) {
                            break;
                        }
                    }
                    if ($buffer == "") {
                        return false;
                    }
                    break;
                case 'gzip':
                    $buffer = gzread($this->fh, $padded_buffer_size);
                    break;
                case 'plain':
                    $buffer = fread($this->fh, $padded_buffer_size);
                    break;
            }
        }
        if ($return_string) {
            return $buffer;
        }
        file_put_contents($this->buffer_filename, $buffer);
        $this->buffer_fh = fopen($this->buffer_filename, "rb");
        return true;
    }

    /**
     * Checks if have a valid handle to object's archive's current partition
     *
     * @return bool whether it has or not (true -it has)
     */
    public function checkFileHandle()
    {
        if (!$this->iterate_dir) { //network mode
            return is_resource($this->buffer_fh);
        } else if ($this->compression != "bzip2") {
            return is_resource($this->fh);
        } else {
            return !is_null($this->bz2_iterator);
        }
    }
    /**
     * Checks if this object's archive's current partition is at an end of file
     *
     * @return bool whether end of file has been reached (true -it has)
     */
    public function checkEof()
    {
        if (!$this->iterate_dir) {
            return feof($this->buffer_fh);
        }
        switch ($this->compression) {
            case 'bzip2':
                $eof = $this->bz2_iterator->eof();
                break;
            case 'gzip':
                $eof = gzeof($this->fh);
                break;
            case 'plain':
                $eof = feof($this->fh);
                break;
        }
        return $eof;
    }
    /**
     * Wrapper around particular compression scheme fopen function
     *
     * @param string $filename name of file to open
     * @param bool $make_buffer_if_needed
     */
    public function fileOpen($filename, $make_buffer_if_needed = true)
    {
        if ($this->iterate_dir) {
            switch ($this->compression) {
                case 'bzip2':
                    $this->bz2_iterator = new BZip2BlockIterator($filename);
                    break;
                case 'gzip':
                    $this->fh = gzopen($filename, "rb");
                    break;
                case 'plain':
                    $this->fh = fopen($filename, "rb");
                    break;
            }
        }
        if ($make_buffer_if_needed) {
            if (!file_exists($this->buffer_filename)) {
                $this->makeBuffer();
            } else {
                $this->buffer_fh = fopen($this->buffer_filename, "rb");
            }
        }
        $this->buffer_block_num = -1;
        $this->current_offset = 0;
    }
    /**
     * Wrapper around particular compression scheme fclose function
     */
    public function fileClose()
    {
        if ($this->iterate_dir) {
            switch ($this->compression) {
                case 'bzip2':
                    $this->bz2_iterator->close();
                    break;
                case 'gzip':
                    gzclose($this->fh);
                    break;
                case 'plain':
                    fclose($this->fh);
                    break;
            }
        }
        fclose($this->buffer_fh);
    }
    /**
     * Returns the current position in the current iterator partition file
     * for the given compression scheme.
     * @return int a position into the currently being processed file of the
     *     iterator
     */
    public function fileTell()
    {
        return ftell($this->buffer_fh);
    }
    /**
     * Stores the current progress to the file iterate_status.txt in the result
     * dir such that a new instance of the iterator could be constructed and
     * return the next set of pages without having to process all of the pages
     * that came before. Each iterator should make a call to saveCheckpoint
     * after extracting a batch of pages.
     * @param array $info any extra info a subclass wants to save
     */
    public function saveCheckPoint($info = [])
    {
        $info['end_of_iterator'] = $this->end_of_iterator;
        $info['buffer_block_num'] = $this->buffer_block_num;
        $info['current_partition_num'] = $this->current_partition_num;
        $info['current_page_num'] = $this->current_page_num;
        $info['buffer'] = $this->buffer;
        $info['remainder'] = $this->remainder;
        $info['header'] = $this->header;
        $info['bz2_iterator'] = $this->bz2_iterator;
        $info['current_offset'] = $this->current_offset;
        file_put_contents($this->status_filename,
            serialize($info));
    }
    /**
     * Restores  the internal state from the file iterate_status.txt in the
     * result dir such that the next call to nextPages will pick up from just
     * after the last checkpoint. Text archive bundle iterator takes
     * the unserialized data from the last check point and calls the
     * compression specific restore checkpoint to further set up the iterator
     * according to the given compression scheme.
     *
     * @return array the data serialized when saveCheckpoint was called
     */
    public function restoreCheckPoint()
    {
        $info = unserialize(file_get_contents($this->status_filename));
        $this->end_of_iterator = $info['end_of_iterator'];
        $this->current_partition_num = $info['current_partition_num'];
        $this->current_page_num = (isset($info['current_page_num'])) ?
            $info['current_page_num'] : 0;
        $this->buffer = (isset($info['buffer'])) ? $info['buffer'] : "";
        $this->remainder = (isset($info['remainder'])) ? $info['remainder']:"";
        $this->header = (isset($info['header'])) ? $info['header']: [];
        $this->bz2_iterator = $info['bz2_iterator'];
        if (!$this->end_of_iterator && !$this->bz2_iterator) {
            if (isset($this->partitions[$this->current_partition_num])) {
                $filename = $this->partitions[$this->current_partition_num];
            } else if (!$this->iterate_dir) { // fetcher case
                $filename = ""; // will only use buffer so don't need
            }
            $this->fileOpen($filename);
            $success = fseek($this->buffer_fh, $info['current_offset']);
            if ($success == -1) { $this->buffer_fh = null; }
        }
        $this->buffer_block_num = $info['buffer_block_num'];
        $this->current_offset = $info['current_offset'];
        return $info;
    }
    /**
     * Used to extract data between two tags. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param string $tag tag name to look for
     *
     * @return string data start tag contents close tag of name $tag
     */
    public function getNextTagData($tag)
    {
        $info = $this->getNextTagsData(array($tag));
        if (!isset($info[1])) {return $info; }
        return $info[0];
    }
    /**
     * Used to extract data between two tags for the first tag found
     * amongst the array of tags $tags. After operation $this->buffer has
     * contents after the close tag.
     *
     * @param array $tags array of tagnames to look for
     *
     * @return array of two elements: the first element is a string consisting
     *     of start tag contents close tag of first tag found, the second
     *     has the name of the tag amongst $tags found
     */
    public function getNextTagsData($tags)
    {
        $close_regex = '@</('.implode('|', $tags).')[^>]*?>@';

        $offset = 0;
        while(!preg_match($close_regex, $this->buffer, $matches,
                    PREG_OFFSET_CAPTURE, $offset)) {
            if (!$this->checkFileHandle() || $this->checkEof()) {
                return false;
            }
            /*
               Get the next block; the block iterator can very occasionally
               return a bad block if a block header pattern happens to show up
               in compressed data, in which case decompression will fail. We
               want to skip over these false blocks and get back to real
               blocks.
            */
            while(!is_string($block = $this->getFileBlock())) {
                L\crawlTimeoutLog("..still getting next tags data..");
                if ($this->checkEof())
                    return false;
            }
            $this->buffer .= $block;
        }
        $tag = $matches[1][0];
        $start_info = strpos($this->buffer, "<$tag");
        $this->remainder = substr($this->buffer, 0, $start_info);
        $pre_end_info = strpos($this->buffer, "</$tag", $start_info);
        $end_info = strpos($this->buffer, ">", $pre_end_info) + 1;
        $tag_info = substr($this->buffer, $start_info,
            $end_info - $start_info);
        $this->buffer = substr($this->buffer, $end_info);
        return [$tag_info, $tag];
    }
}
