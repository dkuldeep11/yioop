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
 * @author Shawn Tice, (docs added by Chris Pollett chris@pollett.org)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library;

/**
 * This class is used to allow one to iterate through a Bzip2 file.
 * The main advantage of using this class over the built-in bzip is that
 * it can "remember" where it left off between serializations. So can
 * continue where left off between web invocations. This is used in
 * doing archive crawls of wiki dumps to allow the name server picks up where
 * it left off.
 *
 * @author Shawn Tice, (some docs added by Chris Pollett chris@pollett.org)
 */
class BZip2BlockIterator
{
    /**
     * File handle for bz2 file
     * @var resource
     */
    public $fd = null;
    /**
     * Byte offset into bz2 file
     * @var int
     */
    public $file_offset = 0;
    /**
     * Since block sizes are not constant used to store sufficiently many
     * bytes so can properly extract next blocks
     * @var string
     */
    public $buffer = '';
    /**
     * Used to build and store a bz2 block from the file stream
     * @var string
     */
    public $block = '';
    /**
     * Stores the left over bits of a bz2 block
     * @var int
     */
    public $bits = 0;
    /**
     * Store how many left-over bits there are
     * @var int
     */
    public $num_extra_bits = 0;
    /**
     * Lookup table fpr the number of bits by which the magic
     * number for the next block has been shifted right. Second
     * components of sub-arrays say whether block header or endmark
     * @var array
     */
    public static $header_info = [
        "\x41" => [0,  true], "\xa0" => [1,  true],
        "\x50" => [2,  true], "\x28" => [3,  true],
        "\x14" => [4,  true], "\x8a" => [5,  true],
        "\xc5" => [6,  true], "\x62" => [7,  true],

        "\x72" => [0, false], "\xb9" => [1, false],
        "\xdc" => [2, false], "\xee" => [3, false],
        "\x77" => [4, false], "\xbb" => [5, false],
        "\x5d" => [6, false], "\x2e" => [7, false]
    ];
    /** String to tell if file is a bz2 file*/
    const MAGIC = 'BZh';
    /** String at the start of each bz2 block */
    const BLOCK_HEADER = "\x31\x41\x59\x26\x53\x59";
    /** String at the end of each bz2 block*/
    const BLOCK_ENDMARK = "\x17\x72\x45\x38\x50\x90";
    /**
     * Blocks are NOT byte-aligned, so the block header (and endmark) may show
     * up shifted right by 0-8 bits in various places throughout the file. This
     * regular expression matches any of the possible shifts for both the block
     * header and the block endmark.
     */
    const BLOCK_LEADER_RE = '
        /
         \x41\x59\x26\x53\x59 | \xa0\xac\x93\x29\xac | \x50\x56\x49\x94\xd6
        |\x28\x2b\x24\xca\x6b | \x14\x15\x92\x65\x35 | \x8a\x0a\xc9\x32\x9a
        |\xc5\x05\x64\x99\x4d | \x62\x82\xb2\x4c\xa6

        |\x72\x45\x38\x50\x90 | \xb9\x22\x9c\x28\x48 | \xdc\x91\x4e\x14\x24
        |\xee\x48\xa7\x0a\x12 | \x77\x24\x53\x85\x09 | \xbb\x92\x29\xc2\x84
        |\x5d\xc9\x14\xe1\x42 | \x2e\xe4\x8a\x70\xa1
        /x';
    /**
     * How many bytes to read into buffer from bz2 stream in one go
     */
    const BLOCK_SIZE = 8192;
    /**
     * Creates a new iterator of a bz2 file by opening the file, doing a
     * sanity check and then setting up the initial file_offset to
     * where the data starts
     * @param string $path file path of bz2 file
     */
    public function __construct($path)
    {
        $this->path = $path;
        $this->fd = fopen($this->path, 'rb');
        $this->header = fread($this->fd, 4);
        if (substr($this->header, 0, 3) != self::MAGIC) {
            throw new \Exception('Bad bz2 magic number. Not a bz2 file?');
        }
        $this->block = fread($this->fd, 6);
        if ($this->block != self::BLOCK_HEADER) {
            throw new \Exception('Bad bz2 block header');
        }
        $this->file_offset = 10;
    }
    /**
     * Called by unserialize prior to execution
     */
    public function __wakeup()
    {
        $this->fd = fopen($this->path, 'rb');
        fseek($this->fd, $this->file_offset);
    }
    /**
     * Checks whether the current Bzip2 file has reached an end of file
     * @return bool eof or not
     */
    public function eof()
    {
        return feof($this->fd);
    }
    /**
     * Used to close the file associated with this iterator
     * @return bool whether the file close was successful
     */
    public function close()
    {
        return fclose($this->fd);
    }
    /**
     * Extracts the next bz2 block from the bzip2 file this iterator works
     * on
     * @param bool $raw if false then decompress the recovered block
     */
    public function nextBlock($raw = false)
    {
        $recovered_block = null;
        while(!feof($this->fd)) {
            $next_chunk = fread($this->fd, self::BLOCK_SIZE);
            $this->file_offset += strlen($next_chunk);
            $this->buffer .= $next_chunk;
            $match = preg_match( self::BLOCK_LEADER_RE, $this->buffer,
                $matches, PREG_OFFSET_CAPTURE);
            if ($match) {
                /*
                    $pos is the position of the SECOND byte of the magic number
                    (plus some part of the first byte for a non-zero new_shift).
                 */
                $pos = $matches[0][1];
                /*
                     The new_shift is the number of bits by which the magic
                      number for the next block has been shifted right.
                 */
                list($new_shift, $is_start) =
                    self::$header_info[$this->buffer[$pos]];
                /*
                    The new number of extra bits is what's left in a byte after
                    the new shift. For example, if we have 10|001011 as the byte
                    that begins the next block's header, where the vertical bar
                    represents the beginning of the header bits, the new shift
                    is 2, and after we byte-align the new header to the left
                    there will always be 6 extra bits waiting for two bits to
                    form a byte to be added to the next block.
                */
                $new_num_extra_bits = $new_shift == 0 ? 0 : 8 - $new_shift;
                if ($new_shift == 0) {
                    $tail_bits = $new_bits = 0;
                    $header_end = 5;
                    $new_header = substr($this->buffer, $pos - 1, 6);
                    $new_block = $new_header;
                } else {
                    $byte = ord($this->buffer[$pos-1]);
                    $tail_bits = $byte & (((0x1 << $new_shift) - 1) <<
                        (8 - $new_shift));
                    $new_bits = ($byte << $new_shift) & 0xff;
                    $header_end = 6;
                    $new_block = '';
                    $new_header = substr($this->buffer, $pos, 6);
                    self::packLeft($new_block, $new_bits, $new_header,
                        $new_num_extra_bits);
                }
                // Make sure all six header bytes match.
                if ($is_start && $new_block != self::BLOCK_HEADER ||
                        !$is_start && $new_block != self::BLOCK_ENDMARK) {
                    $unmatched = substr($this->buffer, 0, $pos + 6);
                    $keep = substr($this->buffer, $pos + 6);
                    self::packLeft($this->block, $this->bits, $unmatched,
                        $this->num_extra_bits);
                    continue;
                }
                /*
                    Copy and shift the last chunk of bytes from the previous
                    block before adding the block trailer.
                */
                $block_tail = substr($this->buffer, 0, $pos - 1);
                $this->packLeft($this->block, $this->bits, $block_tail,
                    $this->num_extra_bits);
                /*
                    We need to combine the non-header tail bits from the most
                    significant end of the last byte before the next block's
                    header with whatever extra bits are left over from shifting
                    the body of the previous block.
                */
                $bits_left = 8 - $this->num_extra_bits;
                if ($new_shift >= $bits_left) {
                    $this->bits |= ($tail_bits >> $this->num_extra_bits);
                    $this->block .= chr($this->bits);
                    $this->bits = ($tail_bits << $bits_left) & 0xff;
                    $this->num_extra_bits = $new_shift - $bits_left;
                } else {
                    $this->bits |= ($tail_bits >> $this->num_extra_bits);
                    $this->num_extra_bits = $this->num_extra_bits +
                        $new_shift;
                }
                /*
                    The last block is marked by a different header (sqrt(pi)),
                    and a CRC for the entire "file", which is just the CRC for
                    the first block, since there's only one block.
                */
                $trailer = "\x17\x72\x45\x38\x50\x90".
                    substr($this->block, 6, 4);
                $this->packLeft($this->block, $this->bits, $trailer,
                    $this->num_extra_bits);
                if ($this->num_extra_bits != 0) {
                    $this->block .= chr($this->bits);
                }
                $recovered_block = $this->header.$this->block;
                $this->block = $new_block;
                /*
                    Keep everything after the end of the header for the next
                    block in the buffer.
                */
                $this->buffer = substr($this->buffer, $pos + $header_end);
                $this->bits = $new_bits;
                $this->num_extra_bits = $new_num_extra_bits;
                break;
            } else {
                /*
                    No match, but we may have just missed a header by a byte, so
                    we need to keep the last six bytes in the buffer so that we
                    have a chance to get the full header on the next round.
                */
                $unmatched = substr($this->buffer, 0, -6);
                $this->packLeft($this->block, $this->bits, $unmatched,
                    $this->num_extra_bits);
                $this->buffer = substr($this->buffer, -6);
            }
        }
        if (!$raw) {
            return bzdecompress($recovered_block);
        } else {
            return $recovered_block;
        }
    }
    /**
     * Computes a new bzip2 block portions and bits left over after adding
     * $bytes to the passed $block.
     *
     * @param string& $block the block to add to
     * @param int& $bits used to hold bits left over
     * @param string $bytes what to add to the bzip block
     * @param int $num_extra_bits how many extra bits there are
     */
    public function packLeft(&$block, &$bits, $bytes, $num_extra_bits)
    {
        if ($num_extra_bits == 0) {
            $block .= $bytes;
            return;
        }
        $num_bytes = strlen($bytes);
        for ($i = 0; $i < $num_bytes; $i++) {
            $byte = ord($bytes[$i]);
            $bits |= ($byte >> $num_extra_bits);
            $block .= chr($bits);
            $bits = ($byte << (8 - $num_extra_bits)) & 0xff;
        }
    }
}
if (!function_exists("main") && php_sapi_name() == 'cli') {
    /**
     * Command-line shell for testing the class
     */
    function main()
    {
        global $argv;
        $path = $argv[1];
        $prefix = isset($argv[2]) ? $argv[2] : 'rec';
        $itr = new BZip2BlockIterator($path);
        $i = 1;
        while(($block = $itr->next_block(true)) !== null) {
            $rec_name = sprintf("%s%05d.bz2", $prefix, $i);
            file_put_contents($rec_name, $block);
            echo "Recovered block {$i}\n";
            $i++;
        }
    }
    // Only run main if this script is called directly from the command line.
    if (isset($argv[0]) && realpath($argv[0]) == __FILE__) {
        main();
    }
}
