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

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Data structure used to store for entries of the form:
 * word id, index shard generation, posting list offset, and length of
 * posting list. It has entries for all words stored in a given
 * IndexArchiveBundle. There might be multiple entries for a given word_id
 * if it occurs in more than one index shard in the given IndexArchiveBundle.
 *
 * In terms of file structure, a dictionary is stored a folder consisting of
 * 256 subfolders. Each subfolder is used to store the word_ids beginning with
 * a particular character. Within a folder are files of various tier levels
 * representing the data stored. As crawling proceeds words from a shard are
 * added to the dictionary in files of tier level 0 either with suffix A or B.
 * If it is detected that both an A and a B file of a given tier level exist,
 * then the results of these two files are merged to a new file at one tier
 * level up . The old files are then deleted. This process is applied
 * recursively until there is at most an A file on each level.
 *
 * @author Chris Pollett
 */
class IndexDictionary implements CrawlConstants
{
    /**
     * Folder name to use for this IndexDictionary
     * @var string
     */
    public $dir_name;
    /**
     * Array of file handle for files in the dictionary. Members are used
     * to read files to look up words.
     *
     * @var resource
     */
    public $fhs;
    /**
     * Array of file lengths for files in the dictionary. Use so don't try to
     * seek past end of files
     *
     * @var int
     */
    public $file_lens;
    /**
     * An cached array of disk blocks for an index dictionary that has not
     * been completely loaded into memory.
     * @var array
     */
    public $blocks;
    /**
     * The highest tiered index in the IndexDictionary
     * @var int
     */
    public $max_tier;
    /**
     * Tier currently being used to read dictionary data from
     * @var int
     */
    public $read_tier;
    /**
     * Tiers which currently have data for reading
     * @var array
     */
    public $active_tiers;
    /**
     * Length of the doc strings for each of the shards that have been added
     * to the dictionary.
     * @var array
     */
    public $shard_doc_lens;
    /**
     * If not null, then the parent IndexArchiveBundle this dictionary belongs
     * to
     * @var object
     */
    public $parent_archive_bundle;
    /**
     * Represents an empty element in an Auxiliary dictionary entry record
     * 10 bytes long
     */
    const AUX_RECORD_BLANK = "\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF";
    /**
     * Represents the length of an element in a dictionary aux record
     */
    const AUX_RECORD_LEN = 10;
    /**
     * When merging two files on a given dictionary tier. This is the max number
     * of bytes to read in one go. (Must be divisible by WORD_ITEM_LEN)
     */
     const SEGMENT_SIZE = 20000000;
    /**
     * Size in bytes of one block in IndexDictionary
     */
    const DICT_BLOCK_SIZE = 4096;
    /**
     * Disk block size is 1<< this power
     */
    const DICT_BLOCK_POWER = 12;
    /**
     * Size of an item in the prefix index used to look up words.
     * If the sub-dir was 65 (ASCII A), and the second char  was also
     * ASCII 65, then the corresonding prefix record would be the
     * offset to the first word_id beginning with AA, followed by the
     * number of such AA records.
     */
    const PREFIX_ITEM_SIZE = 8;
    /**
     * Number of possible prefix records (number of possible values for
     * second char of a word id)
     */
    const NUM_PREFIX_LETTERS = 256;
    /**
     * One dictionary file represents the words whose ids begin with a
     * fixed char. Amongst these id, the prefix index gives offsets for
     * where id's with a given second char start. The total length of the
     * records needed is PREFIX_ITEM_SIZE * NUM_PREFIX_LETTERS.
     */
    const PREFIX_HEADER_SIZE = 2048;
    /**
     * Makes an index dictionary with the given name
     *
     * @param string $dir_name the directory name to store the index dictionary
     *     in
     * @param object $parent_archive_bundle parent index archive bundle this
     *      dictionary is for
     */
    public function __construct($dir_name, $parent_archive_bundle = null)
    {
        $this->dir_name = $dir_name;
        if (!is_dir($this->dir_name)) {
            mkdir($this->dir_name);
            IndexDictionary::makePrefixLetters($this->dir_name);
            $this->max_tier = 0;
        } else {
            $this->max_tier = unserialize(
                file_get_contents($this->dir_name."/max_tier.txt"));
            $this->read_tier = $this->max_tier;
            $tiers = glob($this->dir_name."/0/*A.dic");
            natsort($tiers);
            $this->active_tiers = [];
            foreach ($tiers as $tier) {
                $path = pathinfo($tier);
                array_unshift($this->active_tiers,
                    substr($path["filename"], 0, -1));
            }
        }
        $this->parent_archive_bundle = $parent_archive_bundle;
    }
    /**
     * Makes dictionary sub-directories for each of the 256 possible first
     * hash characters that crawHash in raw mode code output.
     * @param string $dir_name base directory in which these sub-directories
     *     should be made
     */
    public static function makePrefixLetters($dir_name)
    {
        for ($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            mkdir($dir_name."/$i");
        }
        file_put_contents($dir_name."/max_tier.txt",
            serialize(0));
    }
    /**
     * Adds the words in the provided IndexShard to the dictionary.
     * Merges tiers as needed.
     *
     * @param object $index_shard the shard to add the word to the dictionary
     *     with
     * @param object $callback object with join function to be
     *     called if process is taking too  long
     */
    public function addShardDictionary($index_shard, $callback = null)
    {
        $out_slot = "A";
        if (file_exists($this->dir_name."/0/0A.dic")) {
            $out_slot ="B";
        }
        crawlLog("Adding shard data to index dictionary files...");
        if (!$index_shard->getShardHeader()) {
            crawlLog("  Problem reading shard header");
            return false;
        }
        $base_offset = IndexShard::HEADER_LENGTH + $index_shard->prefixes_len;
        $prefix_string = $index_shard->getShardSubstring(
            IndexShard::HEADER_LENGTH, $index_shard->prefixes_len, false);
        if (!$prefix_string) {
            crawlLog("  Problem reading prefix string");
            return false;
        }
        $next_offset = $base_offset;
        $word_item_len = IndexShard:: WORD_KEY_LEN +
                IndexShard:: WORD_DATA_LEN;
        $num_prefix_letters = self::NUM_PREFIX_LETTERS;
        $prefix_item_size = self::PREFIX_ITEM_SIZE;
        $prefix_header_size = self::PREFIX_HEADER_SIZE;
        for ($i = 0; $i < $num_prefix_letters; $i++) {
            $last_offset = $next_offset;
            // adjust prefix values
            $first_offset_flag = true;
            $last_set = -1;
            for ($j = 0; $j < $num_prefix_letters; $j++) {
                $rec_num = ($i << 8) + $j;
                $prefix_info = $this->extractPrefixRecord($prefix_string,
                        $rec_num);
                if (isset($prefix_info[1])) {
                    list($offset, $count) = $prefix_info;
                    if ($first_offset_flag) {
                        $first_offset = $offset;
                        $first_offset_flag = false;
                    }
                    $offset -= $first_offset;
                    $out = pack("N*", $offset, $count);
                    $last_set = $j;
                    $last_out = $prefix_info;
                    charCopy($out, $prefix_string, $rec_num * $prefix_item_size,
                        $prefix_item_size);
                }
            }
            // write prefixes
            $fh = fopen($this->dir_name."/$i/0".$out_slot.".dic", "wb");
            fwrite($fh, substr($prefix_string, $i * $prefix_header_size,
                $prefix_header_size));
            $j = $num_prefix_letters;
            // write words
            if ($last_set >= 0) {
                list($offset, $count) = $last_out;
                $next_offset = $base_offset + $offset +
                    $count * $word_item_len;
                fwrite($fh, $index_shard->getShardSubstring($last_offset,
                    $next_offset - $last_offset, false));
            }
            fclose($fh);
        }
        unset($prefix_string);
        crawlLog("Incrementally Merging tiers of index dictionary");
        // log merge tiers if needed
        $tier = 0;
        while($out_slot == "B") {
            if ($callback != null) {
                $callback->join();
            }
            $out_slot = "A";
            if (file_exists($this->dir_name."/0/".($tier + 1)."A.dic")) {
                $out_slot ="B";
            }
            crawlLog("..Merging index $tier to ".($tier +1).$out_slot);
            $this->mergeTier($tier, $out_slot);
            $tier++;
            if ($tier > $this->max_tier) {
                $this->max_tier = $tier;
                file_put_contents($this->dir_name."/max_tier.txt",
                    serialize($this->max_tier));
            }
        }
        crawlLog("...Done Incremental Merging of Index Dictionary Tiers");
        return true;
    }
    /**
     * Merges for each first letter subdirectory, the $tier pair of files
     * of dictinary words. The output is stored in $out_slot.
     *
     * @param int $tier tier level to perform the merge of files at
     * @param string $out_slot either "A" or "B", the suffix but not extension
     *      of the file one tier up to create with the merged results.
     */
    public function mergeTier($tier, $out_slot)
    {
        for ($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            crawlTimeoutLog("..processing first index prefix $i of ".
                self::NUM_PREFIX_LETTERS." in $tier.");
            $this-> mergeTierFiles($i, $tier, $out_slot);
        }
    }
    /**
     * For a fixed prefix directory merges the $tier pair of files
     * of dictinary words. The output is stored in $out_slot.
     *
     * @param int $prefix which prefix directory to perform the merge of files
     * @param int $tier tier level to perform the merge of files at
     * @param string $out_slot either "A" or "B", the suffix but not extension
     *      of the file one tier up to create with the merged results.
     */
    public function mergeTierFiles($prefix, $tier, $out_slot)
    {
        $file_a = $this->dir_name."/$prefix/$tier"."A.dic";
        $file_b = $this->dir_name."/$prefix/$tier"."B.dic";
        $size_a = filesize($file_a);
        $size_b = filesize($file_b);
        $prefix_header_size = self::PREFIX_HEADER_SIZE;
        $fh_a = fopen( $file_a, "rb");
        $fh_b = fopen( $file_b, "rb");
        $fh_out = fopen( $this->dir_name."/$prefix/".($tier + 1).
            "$out_slot.dic", "wb+");
        $prefix_bit = ($prefix & 128) ? 0 : 128;
        // Scan past prefix headers
        fread($fh_a, $prefix_header_size);
        fread($fh_b, $prefix_header_size);
        // Set up defaults before reading writing dictionary records
        $word_key_len = IndexShard:: WORD_KEY_LEN;
        $word_item_len = $word_key_len + IndexShard:: WORD_DATA_LEN;
        $blank_prefix_string_out = str_pad("", $prefix_header_size, "\x00");
        fwrite($fh_out, $blank_prefix_string_out);
        $remaining_a = $size_a - $prefix_header_size;
        $remaining_b = $size_b - $prefix_header_size;
        $work_string_a = "";
        $read_size_a = 0;
        $record_a = "";
        $offset_a = 0;
        $a_changed = true;
        $work_string_b = "";
        $read_size_b = 0;
        $record_b = "";
        $offset_b = 0;
        $b_changed = true;
        $out = "";
        $out_len = 0;
        // Default to impossible value for second prefixes
        $second_prefix_a = -1;
        $second_prefix_b = -1;
        $old_second_prefix = -1;
        $second_prefix_info = [];
        $segment_size = self::SEGMENT_SIZE;
        // Merge Dictionary Records
        while($remaining_a > 0 || $remaining_b > 0 ||
            $offset_a < $read_size_a || $offset_b < $read_size_b) {
            crawlTimeoutLog("..merging index tier files for prefix %s tier ".
                "%s.", $prefix, $tier);
            if ($a_changed) {
                if ($offset_a > $read_size_a - $word_item_len) {
                    if ($remaining_a >= $word_item_len) {
                        $read_size_a = min($remaining_a, $segment_size);
                        $work_string_a = fread($fh_a, $read_size_a);
                        $remaining_a -= $read_size_a;
                        $offset_a = 0;
                    } else if ($offset_a < $read_size_a || $remaining_a > 0) {
                        crawlLog(
                            "File A: $file_a too short skipping leftover.. ");
                        $remaining_a = 0;
                        $offset_a = $read_size_a;
                    }
                }
                if ($offset_a < $read_size_a) {
                    $record_a = substr($work_string_a, $offset_a,
                        $word_item_len);
                    $num_aux_records = (ord($record_a[$word_key_len]) << 8) +
                        ord($record_a[$word_key_len + 1]);
                    $len_aux_records = $num_aux_records * $word_item_len;
                    if ($num_aux_records > 0) {
                        if ($offset_a + $len_aux_records < $read_size_a) {
                            $record_a = substr($work_string_a, $offset_a,
                                $word_item_len + $len_aux_records);
                        } else if ($remaining_a > 0) {
                            $aux_read_size = min(max($len_aux_records,
                                $segment_size), $remaining_a);
                            $work_string_a = substr($work_string_a, $offset_a) .
                                fread($fh_a, $aux_read_size);
                            $read_size_a = $read_size_a - $offset_a +
                                $aux_read_size;
                            $remaining_a -= $aux_read_size;
                            $offset_a = 0;
                            $record_a = substr($work_string_a, 0 ,
                                $word_item_len + $len_aux_records);
                        }
                    }
                    $second_prefix_a = ord($record_a[1]);
                }
            }
            if ($b_changed) {
                if ($offset_b > $read_size_b - $word_item_len) {
                    if ($remaining_b >= $word_item_len) {
                        $read_size_b = min($remaining_b, $segment_size);
                        $work_string_b = fread($fh_b, $read_size_b);
                        $remaining_b -= $read_size_b;
                        $offset_b = 0;
                    } else if ($offset_b < $read_size_b || $remaining_b > 0) {
                        crawlLog(
                            "File B: $file_b too short skipping leftover.. ");
                        $remaining_b = 0;
                        $offset_b = $read_size_b;
                    }
                }
                if ($offset_b < $read_size_b) {
                    $record_b = substr($work_string_b, $offset_b,
                        $word_item_len);
                    $num_aux_records = (ord($record_b[$word_key_len]) << 8) +
                        ord($record_b[$word_key_len + 1]);
                    $len_aux_records = $num_aux_records * $word_item_len;
                    if ($num_aux_records > 0) {
                        if ($offset_b + $len_aux_records < $read_size_b) {
                            $record_b = substr($work_string_b, $offset_b,
                                $word_item_len + $len_aux_records);
                        } else if ($remaining_b > 0) {
                            $aux_read_size = min(max($len_aux_records,
                                $segment_size), $remaining_b);
                            $work_string_b = substr($work_string_b, $offset_b) .
                                fread($fh_b, $aux_read_size);
                            $read_size_b = $read_size_b - $offset_b +
                                $aux_read_size;
                            $remaining_b -= $aux_read_size;
                            $offset_b = 0;
                            $record_b = substr($work_string_b, 0,
                                $word_item_len + $len_aux_records);
                        }
                    }
                    $second_prefix_b = ord($record_b[1]);
                }
            }
            if ($offset_a >= $read_size_a && $offset_b >= $read_size_b) {
                $a_changed = true;
                $b_changed = true;
                continue;
            } else if ($offset_b >= $read_size_b) {
                $out .= $record_a;
                $len = strlen($record_a);
                $offset_a += $len;
                $second_prefix = $second_prefix_a;
                $a_changed = true;
                $b_changed = false;
            } else if ($offset_a >= $read_size_a) {
                $out .= $record_b;
                $len = strlen($record_b);
                $offset_b += $len;
                $second_prefix = $second_prefix_b;
                $b_changed = true;
                $a_changed = false;
            } else if (($cmp = strncmp($record_a, $record_b, $word_key_len))
                < 0) {
                $out .= $record_a;
                $len = strlen($record_a);
                $offset_a += $len;
                $second_prefix = $second_prefix_a;
                $a_changed = true;
                $b_changed = false;
            } else if ($cmp > 0) {
                $out .= $record_b;
                $len = strlen($record_b);
                $offset_b += $len;
                $second_prefix = $second_prefix_b;
                $b_changed = true;
                $a_changed = false;
            } else {
                $offset_a += strlen($record_a);
                $offset_b += strlen($record_b);
                $record = $this->combineDictionaryRecord($record_a, $record_b,
                    $prefix_bit);
                $len = strlen($record);
                $out .= $record;
                $second_prefix = $second_prefix_a;
                $a_changed = true;
                $b_changed = true;
            }
            $out_len += $len;
            $add = $len / $word_item_len;
            if ($old_second_prefix === $second_prefix) {
                $second_prefix_info[$old_second_prefix] += $add;
            } else {
                $second_prefix_info[$second_prefix] = $add;
            }
            $old_second_prefix = $second_prefix;
            if ($out_len >=  $segment_size) {
                fwrite($fh_out, $out);
                $out = "";
                $out_len = 0;
            }
        }
        fwrite($fh_out, $out);
        // Write new prefix records
        $offset = 0;
        fseek($fh_out, $offset);
        $blank = IndexShard::BLANK;
        $num_prefix_letters = self::NUM_PREFIX_LETTERS;
        $prefix_string_out = "";
        for ($j = 0; $j < $num_prefix_letters; $j++) {
            crawlTimeoutLog("..processing second index prefix %s of %s.",
                $j, $num_prefix_letters);
            if (isset($second_prefix_info[$j])) {
                $prefix_string_out .=
                    $this->makePrefixRecord($offset, $second_prefix_info[$j]);
                $offset += $second_prefix_info[$j] * $word_item_len;
            } else {
                $prefix_string_out .= $blank;
            }
        }
        fwrite($fh_out, $prefix_string_out);
        fclose($fh_a);
        fclose($fh_b);
        unlink($file_a);
        unlink($file_b);
        fclose($fh_out);
    }
    /**
     * Used to combine the dictionary records for a given word_id between
     * that come from two different tier files
     *
     * @param string $record_a a dictionary record including auxiliary records
     *      from the 'a'th file of the give tier
     * @param string $record_b a dictionary record including auxiliary records
     *      from the 'b'th file of the give tier
     * @param int $prefix_bit either 0 or 32768. The first bit of an auxiliary
     *      record should be ~higher order bit of the given prefix letter
     *      used by the tier file.
     * @return string a single record with merged strings making use of
     *      auxliary records as needed containing
     *      (generation, posting list offset, length) information.
     */
    public function combineDictionaryRecord($record_a, $record_b, $prefix_bit)
    {
        $word_key_len = IndexShard::WORD_KEY_LEN;
        $word_item_len = $word_key_len + IndexShard::WORD_DATA_LEN;
        $aux_record_len = self::AUX_RECORD_LEN;
        $num_aux_records_a = (ord($record_a[$word_key_len]) << 8) +
            ord($record_a[$word_key_len + 1]);
        $num_aux_records = 0;
        if ($num_aux_records_a > 0) {
            $last_aux_record_a = substr($record_a, -$word_item_len);
            $aux_records = $this->decodeAuxRecord($last_aux_record_a);
            $num_aux_records = $num_aux_records_a;
            if (count($aux_records) == 3) {
                $record = $record_a;
                $aux_records = [];
            } else {
                $record = substr($record_a, 0, -$word_item_len);
                $num_aux_records--;
            }
        } else {
            $record = $record_a;
            $aux_records = [];
        }
        $aux_records[] = substr($record_b, $word_item_len - $aux_record_len,
            $aux_record_len);
        if (count($aux_records) == 3) {
            $record .=  chr($prefix_bit + ($num_aux_records >> 8)) .
                chr($num_aux_records & 255). implode("", $aux_records);
            $aux_records = [];
            $num_aux_records++;
        }
        $num_aux_records_b = (ord($record_b[$word_key_len]) << 8) +
            ord($record_b[$word_key_len + 1]);
        for ($i = 0, $rec_offset = $word_item_len; $i < $num_aux_records_b;
            $i++, $rec_offset += $word_item_len) {
            $aux_records += $this->decodeAuxRecord($record_b, $rec_offset);
            if (count($aux_records) >= 3) {
                do {
                    $record .= chr($prefix_bit + ($num_aux_records >> 8)).
                        chr($num_aux_records & 255).
                        implode("", array_slice($aux_records, 0, 3));
                    $aux_records = array_slice($aux_records, 3);
                    $num_aux_records++;
                } while(count($aux_records) >= 3);
            }
        }
        if (count($aux_records) > 0) {
            do {
                $tmp = chr($prefix_bit + ($num_aux_records >> 8)).
                    chr($num_aux_records & 255). implode("",
                    array_slice($aux_records, 0, 3));
                $aux_records = array_slice($aux_records, 3);
                $record .= str_pad($tmp, $word_item_len, "\xFF");
                $num_aux_records++;
            } while(count($aux_records) >= 3);
        }
        $record[$word_key_len] = chr($num_aux_records >> 8);
        $record[$word_key_len + 1] = chr($num_aux_records & 255);
        return $record;
    }
    /**
     * Used to decode an auxiliary dictionary record associated with a
     * given word_id
     *
     * @param string $record_string string in which dictionary records occur
     * @param string $offset a byte offset into $record_string
     * @param string $complete_decode whether to leave the decoded
     *      records as 10 byte strings or to further to decode them as
     *      (generation, posting list offset, length) triples
     * @return array of up to three strings
     */
    public function decodeAuxRecord($record_string, $offset = 0)
    {
        $posting_info = str_split(substr($record_string, $offset + 2, 30), 10);
        if (!isset($posting_info[2]) ){
            crawlLog("Decode Aux Record failed...".
                toHexString($record_string)."  ".$offset);
            crawlLog(print_r($posting_info, true));
            crawlLog(print_r(debug_backtrace(), true));
            exit();
        }
        if ($posting_info[2] == self::AUX_RECORD_BLANK) {
            unset($posting_info[2]);
            if ($posting_info[1] == self::AUX_RECORD_BLANK) {
                unset($posting_info[1]);
            }
        }
        return $posting_info;
    }
    /**
     * Returns the $record_num'th prefix record from $prefix_string
     *
     * @param string $prefix_string string to get record from
     * @param int $record_num which record to extract
     * @return array $offset, $count  array
     */
    public function extractPrefixRecord($prefix_string, $record_num)
    {
        $record = substr($prefix_string, self::PREFIX_ITEM_SIZE * $record_num,
             self::PREFIX_ITEM_SIZE);
        if ($record == IndexShard::BLANK) {
            return false;
        }
        return array_values(unpack("N*", $record));
    }
    /**
     * Makes a prefix record string out of an offset and count (packs and
     * concatenates).
     *
     * @param int $offset byte offset into words for the prefix record
     * @param int $count number of word with that prefix
     * @return string the packed record
     */
    public function makePrefixRecord($offset, $count)
    {
        return pack("N*", $offset, $count);
    }
    /**
     * Merges for each tier and for each first letter subdirectory,
     * the $tier pair of (A and B) files  of dictionary words. If max_tier has
     * not been reached but only one of the two tier files is present then that
     * file is renamed with a name one tier higher. The output in all cases is
     * stored in file ending with A or B one tier up. B is used if an A file is
     * already present.
     * @param object $callback object with join function to be
     *     called if process is taking too long
     * @param int $max_tier the maximum tier to merge to merge till --
     *     if not set then $this->max_tier used. Otherwise, one would
     *     typically set to a value bigger than $this->max_tier
     * @param bool $fast_merge_all if true then merge away B slots but don't
     *     merge everything to a top tier
     */
    public function mergeAllTiers($callback = null, $max_tier = -1,
        $fast_merge_all = false)
    {
        $new_tier = false;
        crawlLog("Starting Full Merge of Index Dictionary Tiers");
        if ($max_tier == -1) {
            $max_tier = $this->max_tier;
        }
        for ($i = 0; $i < self::NUM_PREFIX_LETTERS; $i++) {
            for ($j = 0; $j <= $max_tier; $j++) {
                crawlTimeoutLog("...Processing Index Prefix Number %s Tier %s".
                    " Max Tier %s", $i, $j, $max_tier);
                if ($callback != null) {
                    $callback->join();
                }
                $a_exists = file_exists($this->dir_name."/$i/".$j."A.dic");
                $b_exists = file_exists($this->dir_name."/$i/".$j."B.dic");
                $higher_a = file_exists($this->dir_name."/$i/".($j+1)."A.dic");
                if ($a_exists && $b_exists) {
                    $out_slot = ($higher_a) ? "B" : "A";
                    $this->mergeTierFiles($i, $j, $out_slot);
                    if ($j == $max_tier) {$new_tier = true;}
                } else if ($a_exists && $higher_a && !$fast_merge_all) {
                    rename($this->dir_name."/$i/".$j."A.dic",
                        $this->dir_name."/$i/".($j + 1)."B.dic");
                } else if ($a_exists && $j < $max_tier && !$fast_merge_all) {
                    rename($this->dir_name."/$i/".$j."A.dic",
                        $this->dir_name."/$i/".($j + 1)."A.dic");
                }
            }
        }
        if ($new_tier) {
            $max_tier++;
            file_put_contents($this->dir_name."/max_tier.txt",
                serialize($max_tier));
            $this->max_tier = $max_tier;
        }
        crawlLog("...End Full Merge of Index Dictionary Tiers");
    }
    /**
     * For each index shard generation a word occurred in, return as part of
     * array, an array entry of the form generation, first offset,
     * last offset, and number of documents the
     * word occurred in for this shard. The first offset (similarly, the last
     * offset) is the byte offset into the word_docs string of the first
     * (last) record involving that word.
     *
     * @param string $word_id id of the word or phrase one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or not
     * @param int $shift how many low order bits to drop from $word_id's
     *    when checking for a match
     * @param string $mask bit mask to be applied to bytes after the 8th
     *     byte through 20th byte of word_id. In single word case these
     *     bytes contain safe:, media:, and class: meta word info
     * @param int $threshold if greater than zero how many posting list
     *    results in dictionary info returned before stopping looking for
     *    more matches
     * @param int $start_generation
     * @param int $num_distinct_generations
     * @param bool $with_remaining_total
     * @return mixed an array of entries of the form
     *     generation, first offset, last offset, count
     */
     public function getWordInfo($word_id, $raw = false, $shift = 0, $mask = "",
        $threshold = -1, $start_generation = -1, $num_distinct_generations = -1,
        $with_remaining_total = false)
     {
        $info = [];
        $current_count = 0;
        $found_count = 0;
        $current_max_generation = -2;
        foreach ($this->active_tiers as $tier) {
            $tier_info = $this->getWordInfoTier($word_id, $raw, $tier, $shift,
                $mask, $threshold, $start_generation,
                $num_distinct_generations);
            if (is_array($tier_info) && isset($tier_info[2]) &&
                is_array($tier_info[2])) {
                list($found_count, $max_found_generation,
                    $found_info) = $tier_info;
                if ($with_remaining_total) {
                    $current_max_generation = max($current_max_generation,
                        $max_found_generation);
                    $current_count += $found_count;
                }
                $info = array_merge($info, $found_info);
                if ($found_count > $threshold && $threshold > 0) {
                    //later tiers are always later generations
                    break;
                }
            }
        }
        if ($with_remaining_total &&
            isset($this->parent_archive_bundle->generation_info['ACTIVE'])) {
            $estimated_total_count = $current_count;
            $total_generations =
                $this->parent_archive_bundle->generation_info['ACTIVE'];
            if ($total_generations > 0 && $current_max_generation > 0) {
                $estimated_total_count =
                    ($total_generations - $start_generation) * $current_count
                    / ($current_max_generation - $start_generation);
            }
            return [$estimated_total_count, $info];
        }
        return $info;
     }
    /**
     * This method facilitates query processing of an ongoing crawl.
     * During an ongoing crawl, the dictionary is arranged into tiers
     * as per the logarithmic merge algortihm rather than just one tier
     * as in a crawl that has been stopped.  Word info for more
     * recently crawled pages will tend to be in lower tiers than data
     * that was crawled earlier. getWordInfoTier gets word info data for
     * a specific tier in the index dictionary. Each tier will
     * have word info for a specific, disjoint set of shards, so the format of
     * how to look up posting lists in a shard can be the same
     * regardless of the tier: an array entry is of the form
     * generation, first offset, last offset, and number of documents the
     * word occurred in for this shard.
     *
     * @param string $word_id id of the word one wants to look up
     * @param bool $raw whether the id is our version of base64 encoded or
     * not
     * @param int $tier which tier to get word info from
     * @param int $shift how many low order bits to drop from $word_id's
     *    when checking for a match
     * @param string $mask bit mask to be applied to bytes after the 8th
     *     byte through 20th byte of word_id. In single word case these
     *     bytes contain safe:, media:, and class: meta word info
     * @param int $threshold if greater than zero how many posting list
     *    results in dictionary info returned before stopping looking for
     *    more matches
     * @param int $start_generation
     * @param int $num_generations
     * @return mixed a pair(total_count, max_found_generation,
     *      an array of entries of the form
     *      generation, first offset, last offset, count) or false if
     *      no data
     */
     public function getWordInfoTier($word_id, $raw, $tier, $shift = 0,
        $mask = "", $threshold = -1, $start_generation = -1,
        $num_distinct_generations = -1)
     {
        $num_generations = 0;
        $max_retained_generation = -1;
        $previous_generation = -2;
        $previous_id = -2;
        $remember_generation = -2;
        if (!isset($this->fhs)) {
            $this->fhs = [];
        }
        $this->read_tier = $tier;
        if ($raw == false) {
            //get rid of out modified base64 encoding
            $word_id = unbase64Hash($word_id);
        }
        $word_key_len = strlen($word_id);
        if (strlen($word_id) < 1) {
            return false;
        }
        if ($mask != "") {
            $mask_len = min(11, strlen($mask));
        } else {
            $mask_len = 0;
        }
        $word_item_len = $word_key_len + IndexShard::WORD_DATA_LEN;
        $word_data_len = IndexShard::WORD_DATA_LEN;
        $file_num = ord($word_id[0]);
        /*
            Entries for a particular shard have postings for both
            docs and links. If an entry has more than max_entry_len
            we will assume entry somehow got corrupted and skip that
            generation for that word. Because we are including link have
            set threshold to 5 * number of docs that could be in a shard
         */
        $max_entry_count = 5 * C\NUM_DOCS_PER_GENERATION;
        $total_count = 0;
        $prefix = ord($word_id[1]);
        $prefix_info = $this->getDictSubstring($file_num,
            self::PREFIX_ITEM_SIZE * $prefix, self::PREFIX_ITEM_SIZE);
        if ($prefix_info == IndexShard::BLANK) {
            return false;
        }
        $prefix_tmp = unpack("N*", $prefix_info);
        if (!isset($prefix_tmp[2]) || !$prefix_tmp[2]) {return false; }
        $aux_prefix_bit = (ord($word_id[0]) & 128) ? 0 : 32768;
        list(, $offset, $high) = $prefix_tmp;
        $high--;
        $start = self::PREFIX_HEADER_SIZE  + $offset;
        $low = 0;
        $check_loc = (($low + $high) >> 1);
        $found = false;
        // find a record with word id
        do {
            $old_check_loc = $check_loc;
            $word_string = $this->getDictSubstring($file_num, $start +
                $check_loc * $word_item_len, $word_item_len);
            $num_aux_records = 0;
            if ($word_string == false) {return false;}
            // next 'if' handles finding start of a record with aux records
            $word_prefix = (ord($word_string[0]) << 8) + ord($word_string[1]);
            if ($aux_prefix_bit == ($word_prefix & 32768)) {
                $old_check_loc = $check_loc;
                $check_loc -= ($word_prefix - $aux_prefix_bit + 1);
                $word_string =
                    $this->getDictSubstring($file_num, $start +
                    $check_loc * $word_item_len, $word_item_len);
                if ($check_loc < $low) {
                    $word_prefix =
                        (ord($word_string[0]) << 8) + ord($word_string[1]);
                    // if this is not start of dictionary record fail
                    if ($aux_prefix_bit == ($word_prefix & 32768)) { break; }
                    $num_aux_records = (ord($word_string[$word_key_len]) << 8) +
                        ord($word_string[$word_key_len + 1]);
                    $check_loc += 1 + $num_aux_records;
                    if ($check_loc > $high) { break; }
                    $word_string =
                        $this->getDictSubstring($file_num, $start +
                        $check_loc * $word_item_len, $word_item_len);
                }
            }
            $id = substr($word_string, 0, $word_key_len);
            $cmp = compareWordHashes($word_id, $id, $shift);
            if ($cmp === 0) {
                $found = true;
                break;
            } else if ($cmp < 0) {
                $high = $check_loc;
                $check_loc = (($low + $check_loc) >> 1);
            } else {
                if ($check_loc + 1 == $high) {
                    $check_loc++;
                }
                $low = $check_loc;
                $check_loc = (($high + $check_loc) >> 1);
            }
        } while($old_check_loc != $check_loc);
        if (!$found) {
            return false;
        }
        //now extract the info
        $info = [];
        $id_info = [];
        $num_aux_records = (ord($word_string[$word_key_len]) << 8) +
            ord($word_string[$word_key_len + 1]);
        $word_string = "\x00\x00".substr($word_string, $word_key_len + 2);
        $tmp = IndexShard::getWordInfoFromString($word_string, true);
        $check_and_auxes = 1;
        if ($tmp[3] < $max_entry_count) {
            $previous_generation = $tmp[0];
            $previous_id = $id;
            $remember_generation = $previous_generation;
            if ($start_generation <= $previous_generation) {
                if ($this->checkMaskAndAdd($id, $word_id, $mask, $mask_len,
                    $tmp, $info, $total_count, $previous_generation,
                    $previous_id, $num_generations, $num_distinct_generations,
                    $max_retained_generation, $id_info) && $num_aux_records>0) {
                    $this->addAuxInfoRecords($id ,$file_num, $num_aux_records,
                        $total_count, $threshold, $info, $previous_generation,
                        $num_generations, $start +
                        ($check_loc + 1)* $word_item_len,
                        $num_distinct_generations, $max_retained_generation,
                        $id_info);
                    $check_and_auxes += $num_aux_records;
                    if ($threshold > 0 && $total_count > $threshold) {
                        return $this->formatWordInfo($total_count,
                            $max_retained_generation, $info);
                    }
                }
            }
        }
        //up to first record with word id
        $test_loc = $check_loc - 1;
        $start_loc = $check_loc;
        /*
           break_count is used to allow for some fault tolerance in the case
           single records get corrupted.
         */
        $break_count = 0;
        while ($test_loc >= $low) {
            $word_string = $this->getDictSubstring($file_num, $start +
                $test_loc * $word_item_len, $word_item_len);
            if ($word_string == "" ) { break;}
            $word_prefix = (ord($word_string[0]) << 8) + ord($word_string[1]);
            if ($aux_prefix_bit == ($word_prefix & 32768)) {
                $start_loc = $test_loc;
                $test_loc -= ($word_prefix - $aux_prefix_bit + 1);
                continue;
            }
            $id = substr($word_string, 0, $word_key_len);
            if (compareWordHashes($word_id, $id, $shift) != 0 ) {
                $break_count++;
                if ($break_count > 1) {
                    break;
                }
                $start_loc = $test_loc;
                $test_loc--;
                continue;
            }
            $start_loc = $test_loc;
            $test_loc--;
            $num_aux_records = (ord($word_string[$word_key_len]) << 8) +
                ord($word_string[$word_key_len + 1]);
            $ws = "\x00\x00" . substr($word_string, $word_key_len + 2);
            $tmp = IndexShard::getWordInfoFromString($ws, true);
            /*
               We are doing two checks designed to
               enhance fault tolerance. Both rely on the fact we have
               parsed the word string. The first check is that the number
               of entries for the word id is fewer than what could be stored
               in a shard (sanity check). The second is that the generation
               of one entry for a word id is always different from the next.
               If they are the same it means the crawl was stopped several
               times within one shard and each time merged with the
               dictionary. Only the last such save has useful data.
             */
            $current_generation = $tmp[0];
            if ($tmp[3] < $max_entry_count) {
                if ($previous_generation == $current_generation &&
                    $previous_id == $id) {
                    array_pop($info);
                }
                if ($start_generation <= $current_generation &&
                    ($num_distinct_generations <= 0 ||
                    $num_generations < $num_distinct_generations ||
                    $current_generation <= $max_retained_generation
                    )) {
                    if ($this->checkMaskAndAdd($id, $word_id, $mask, $mask_len,
                        $tmp, $info, $total_count, $previous_generation,
                        $previous_id, $num_generations,
                        $num_distinct_generations, $max_retained_generation,
                        $id_info) && $num_aux_records > 0) {
                        $this->addAuxInfoRecords($id, $file_num,
                            $num_aux_records, $total_count, $threshold, $info,
                            $previous_generation, $num_generations, $start +
                            ($test_loc + 2) * $word_item_len,
                            $num_distinct_generations, $max_retained_generation,
                            $id_info);
                    }
                    if ($threshold > 0 && $total_count > $threshold) {
                        return $this->formatWordInfo($total_count,
                            $max_retained_generation, $info);
                    }
                }
            }
        }
        //until last record with word id
        $test_loc = $check_loc + $check_and_auxes;
        $previous_generation = $remember_generation;
        $break_count = 0;
        while ($test_loc <= $high) {
            $word_string = $this->getDictSubstring($file_num, $start +
                $test_loc * $word_item_len, $word_item_len);
            if ($word_string == "" ) break;
            $id = substr($word_string, 0, $word_key_len);
            if (compareWordHashes($word_id, $id, $shift) != 0 ) {
                $break_count++;
                if ($break_count > 1) {
                    break;
                }
                $test_loc++;
                continue;
            }
            $test_loc++;
            $num_aux_records = (ord($word_string[$word_key_len]) << 8) +
                ord($word_string[$word_key_len + 1]);
            $ws = "\x00\x00" . substr($word_string, $word_key_len + 2);
            $tmp = IndexShard::getWordInfoFromString($ws, true);
            $current_generation = $tmp[0];
            if ($tmp[3] < $max_entry_count &&
                ($previous_generation != $current_generation ||
                $previous_id != $id)) {
                if ($start_generation <= $current_generation &&
                    ($num_distinct_generations <= 0 ||
                    $num_generations < $num_distinct_generations ||
                    $current_generation <= $max_retained_generation
                    )) {
                    if ($this->checkMaskAndAdd($id, $word_id, $mask, $mask_len,
                        $tmp, $info, $total_count, $previous_generation,
                        $previous_id, $num_generations,
                        $num_distinct_generations, $max_retained_generation,
                        $id_info) && $num_aux_records > 0) {
                        $this->addAuxInfoRecords($id, $file_num,
                            $num_aux_records, $total_count, $threshold, $info,
                            $previous_generation, $num_generations, $start +
                            $test_loc * $word_item_len,
                            $num_distinct_generations, $max_retained_generation,
                            $id_info);
                        $test_loc += $num_aux_records;
                    }
                    if ($threshold > 0 && $total_count > $threshold) {
                        return $this->formatWordInfo($total_count,
                            $max_retained_generation, $info);
                    }
                }
            }
        }
        return $this->formatWordInfo($total_count, $max_retained_generation,
            $info);
    }
    /**
     * Adds auxiliary records for a given word id
     *
     * @param string $id
     * @param int $file_num
     * @param int $num_aux_records
     * @param int& $total_count
     * @param int $threshold
     * @param array& $info
     * @param int& $previous_generation
     * @param int& $num_generation
     * @param int $offset
     * @param int $num_distinct_generations
     * @param int& $max_retained_generation
     * @param array& $id_info
     */
    public function addAuxInfoRecords($id, $file_num, $num_aux_records,
        &$total_count, $threshold, &$info, &$previous_generation,
        &$num_generations, $offset, $num_distinct_generations,
        &$max_retained_generation, &$id_info)
    {
        $word_key_len = IndexShard::WORD_KEY_LEN;
        $word_item_len = $word_key_len + IndexShard::WORD_DATA_LEN;
        for ($i = 0; $i < $num_aux_records; $i++) {
            $word_string = $this->getDictSubstring($file_num,
                $offset, $word_item_len);
            $records = $this->decodeAuxRecord($word_string, 0);
            foreach ($records as $record) {
                $record = IndexShard::getWordInfoFromString("\x00\x00".
                    $record, true);
                $record[4] = $id;
                if ($num_distinct_generations > 0) {
                    if (!isset($id_info[$record[0]])) {
                        $id_info[$record[0]] = [];
                        if ($num_generations >= $num_distinct_generations) {
                            if (isset($id_info[$max_retained_generation])) {
                                foreach ($id_info[$max_retained_generation]
                                    as $key){
                                    $total_count -= $info[$key][3];
                                    $info[$key] = false;
                                }
                                unset($id_info[$max_retained_generation]);
                            }
                            $max_retained_generation =
                                max(array_keys($id_info));
                        } else {
                            $num_generations++;
                            if ($record[0] > $max_retained_generation) {
                                $max_retained_generation = $record[0];
                            }
                        }
                    }
                    $id_info[$record[0]][] = count($info);
                    $info[] = $record;
                    $total_count += $record[3];
                    if ($threshold > 0 && $total_count > $threshold) { return; }
                    $previous_generation = $record[0];
                }
            }
            $offset += $word_item_len;
        }
    }
    /**
     * Auxiliary methods that takes the input triple ($total_count,
     * $max_retained_generation, $info) and filters blank entries from 
     * $info and returns the resulting triple
     *
     * @param int& $total_count 
     * @param int $max_retained_generation
     * @param array $info
     * @return array resulting triple
     */
    public function formatWordInfo($total_count, $max_retained_generation,
        $info)
    {
        $info = array_filter($info);
        return [$total_count, $max_retained_generation, $info];
    }
    /**
     * This method is used when computing the array of
     * (generation, posting_list_start, len, exact_word_id) quadruples when
     * looking up a $word_id in an index dictionary.It checks
     * if the $id of a dictionary row matches $word_id up to the $mask info.
     * If so, it adds the word record to the quadruple array $info that has been
     * calculated so far. It also update $total_count, and as well as
     * $previous info for the previous matching record.
     *
     * @param string $id of a row to compare $word_id against
     * @param string $word_id the word id of a term or phrase we are computing
     *     the quadruple array for
     * @param string $mask up to 9 byte wask used to say which materialized
     *     meta words should be checked for when doing a match
     * @param int $mask_len this should be strlen($mask)
     * @param array $record current record from dictionary that we may or may
     *     not add to info
     * @param array& $info quadruple array we are adding to
     * @param int& $total_count count of items in $info
     * @param int& $previous_generation last generation added to $info
     * @param int& $previous_id last exact if added to $info
     * @param int& $num_generations
     * @param int $num_distinct_generations
     * @param int& $max_retained_generation
     * @param array& $id_info
     * @return bool whether the record was added
     */
    public function checkMaskAndAdd($id, $word_id, $mask, $mask_len, $record,
        &$info, &$total_count, &$previous_generation, &$previous_id,
        &$num_generations, $num_distinct_generations,
        &$max_retained_generation, &$id_info)
    {
        $record[4] = $id;
        $add_flag = true;
        if ($mask != "" && strlen($id) > 9 && strlen($word_id) > 9 &&
            substr_compare($id, $word_id, 9, $mask_len) != 0) {
            $k = 0;
            $old_k = 0;
            while(($k = strpos($mask, "\xFF", $old_k)) !== false) {
                $loc = $k + 8;
                if (isset($id[$loc]) && $id[$loc] != $word_id[$loc]) {
                    $add_flag = false;
                    break;
                }
                if ($k == $old_k) {
                    $k++;
                }
                $old_k = $k;
            }
        }
        if ($add_flag) {
            //adding to the end is front is slower than tacking to end
            if ($num_distinct_generations > 0) {
                if (!isset($id_info[$record[0]])) {
                    $id_info[$record[0]] = [];
                    if ($num_generations >= $num_distinct_generations) {
                        if (isset($id_info[$max_retained_generation])) {
                            foreach ($id_info[$max_retained_generation] as
                                $key) {
                                $total_count -= $info[$key][3];
                                $info[$key] = false;
                            }
                            unset($id_info[$max_retained_generation]);
                        }
                        $max_retained_generation = max(array_keys($id_info));
                    } else {
                        $num_generations++;
                        if ($record[0] > $max_retained_generation) {
                            $max_retained_generation = $record[0];
                        }
                    }
                }
                $id_info[$record[0]][] = count($info);
            }
            $info[] = $record;
            $total_count += $record[3];
            $previous_generation = $record[0];
            $previous_id = $id;
        }
        return $add_flag;
    }
    /**
     * Gets from disk $len many bytes beginning at $offset from the
     * $file_num prefix file in the index dictionary
     *
     * @param int $file_num which prefix file to read from (always reads
     *     a file at the max_tier level)
     * @param int $offset byte offset to start reading from
     * @param int $len number of bytes to read
     * @return string data from that location  in the shard
     */
    public function getDictSubstring($file_num, $offset, $len)
    {
        $block_offset =  ($offset >> self::DICT_BLOCK_POWER)
            << self::DICT_BLOCK_POWER;
        $start_loc = $offset - $block_offset;
        //if all in one block do it quickly
        if ($start_loc + $len < self::DICT_BLOCK_SIZE) {
            $data = $this->readBlockDictAtOffset($file_num, $block_offset);
            return substr($data, $start_loc, $len);
        }

        // otherwise, this loop is slower, but handles general case
        $data = $this->readBlockDictAtOffset($file_num, $block_offset);
        if ($data === false) {return "";}
        $substring = substr($data, $start_loc);
        $db_size = self::DICT_BLOCK_SIZE;
        while (strlen($substring) < $len) {
            $data = $this->readBlockDictAtOffset($file_num, $block_offset);
            if ($data === false) {return $substring;}
            $block_offset += $db_size;
            $substring .= $data;
        }
        return substr($substring, 0, $len);
    }
    /**
     * Reads DICT_BLOCK_SIZE bytes from the prefix file $file_num beginning
     * at byte offset $bytes
     *
     * @param int $file_num which dictionary file (given by first letter prefix)
     *     to read from
     * @param int $bytes byte offset to start reading from
     * @return &string data fromIndexShard file
     */
    public function &readBlockDictAtOffset($file_num, $bytes)
    {
        $false = false;
        $tier = $this->read_tier;
        if (isset($this->blocks[$file_num][$tier][$bytes])) {
            return $this->blocks[$file_num][$tier][$bytes];
        }
        if (!isset($this->fhs[$file_num][$tier]) ||
            $this->fhs[$file_num][$tier] === null) {
            $file_name = $this->dir_name."/$file_num/".$tier."A.dic";
            if (!file_exists($file_name)) { return $false; }
            $this->fhs[$file_num][$tier] = fopen($file_name, "rb");
            if ($this->fhs[$file_num][$tier] === false) { return $false; }
            $this->file_lens[$file_num][$tier] = filesize($file_name);
        }
        if ($bytes >= $this->file_lens[$file_num][$tier]) {
            return $false;
        }
        $seek = fseek($this->fhs[$file_num][$tier], $bytes, SEEK_SET);
        if ($seek < 0) {
            return $false;
        }
        $this->blocks[$file_num][$tier][$bytes] = fread(
            $this->fhs[$file_num][$tier], self::DICT_BLOCK_SIZE);
        return $this->blocks[$file_num][$tier][$bytes];
    }
}
