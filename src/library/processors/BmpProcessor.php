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
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\library\UrlParser;

/**
 * Used to create crawl summary information
 * for BMP and ICO files
 *
 * @author Chris Pollett
 */
class BmpProcessor extends ImageProcessor
{
    /**
     * Size in bytes of one block to read in of BMP
     */
    const BLOCK_SIZE = 4096;
    /**
     * Size in bytes of BMP identifier and size info
     */
    const BMP_ID = 10;
    /**
     * Size in bytes of BMP header
     */
    const BMP_HEADER_LEN = 108;
    /**
     * Maximum pixel width or height
     */
    const MAX_DIM = 1000;
    /**
     * Set-ups the any indexing plugins associated with this page
     * processor
     *
     * @param array $plugins an array of indexing plugins which might
     *     do further processing on the data handles by this page
     *     processor
     * @param int $max_description_len maximal length of a page summary
     * @param int $summarizer_option CRAWL_CONSTANT specifying what kind
     *      of summarizer to use self::BASIC_SUMMARIZER,
     *      self::GRAPH_BASED_SUMMARIZER and self::CENTROID_SUMMARIZER
     *      self::CENTROID_SUMMARIZER
     */
    public function __construct($plugins = [], $max_description_len = null,
        $summarizer_option = self::BASIC_SUMMARIZER)
    {
        parent::__construct($plugins, $max_description_len, $summarizer_option);
        /** Register File Types We Handle*/
        self::$indexed_file_types[] = "bmp";
        self::$image_types[] = "bmp";
        self::$mime_processor["image/bmp"] = "BmpProcessor";
    }
    /**
     * {@inheritDoc}
     *
     * @param string $page  the image represented as a character string
     * @param string $url  the url where the image was downloaded from
     * @return array summary information including a thumbnail and a
     *     description (where the description is just the url)
     */
    public function process($page, $url)
    {
        if (is_string($page)) {
            $image = $this->imagecreatefrombmp($page);
            $thumb_string = self::createThumb($image);
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = "Image of ".
                UrlParser::getDocumentFilename($url);
            $summary[self::LINKS] = [];
            $summary[self::PAGE] =
                "<html><body><div><img src='data:image/bmp;base64," .
                base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                "' /></div></body></html>";
            $summary[self::THUMB] = 'data:image/jpeg;base64,'.
                base64_encode($thumb_string);
        }
        return $summary;
    }
    /**
     * Reads in a 32 / 24bit non-palette bmp files from provided filename
     * and returns a php  image object corresponding to it. This is a crude
     * variation of code from imagecreatewbmp function documentation at php.net
     *
     * @param string $bmp_string string with the contents of a bmp file
     */
    public function imagecreatefrombmp($bmp_string)
    {
        $temp = unpack("H*", $bmp_string);
        $hex = $temp[1];
        $header = substr($hex, 0, self::BMP_HEADER_LEN);
        $can_understand_flag = substr($header, 0, 4) == "424d";
        // get parameters of image from header bytes
        if ($can_understand_flag) {
            $header_parts = str_split($header, 2);
            $width  = hexdec($header_parts[19] . $header_parts[18]);
            $height = hexdec($header_parts[23] . $header_parts[22]);
            $bits_per_pixel = hexdec($header_parts[29] . $header_parts[28]);
            $can_understand_flag = (($bits_per_pixel == 24) ||
                ($bits_per_pixel == 32)) && ($width <
                self::MAX_DIM && $height < self::MAX_DIM );
            unset($header_parts);
        }
        $x = 0;
        $y = 1;
       /* We're going to manually write pixel info into the following
            image object
        */
        $image  = imagecreatetruecolor($width, $height);
        if (!$can_understand_flag) {
            return $image;
        }
        //    Grab the body from the image
        $body = substr($hex, self::BMP_HEADER_LEN);
        /*
            Calculate any end-of-line padding needed
        */
        $body_size = strlen($body)/2;
        $header_size = ($width * $height);
        // Set-up padding flag
        $padding_flag = ($body_size > ($header_size * 3) + 4);
        $pixel_step = ceil($bits_per_pixel >> 3);
        // Write pixels
        for ($i = 0; $i < $body_size; $i += $pixel_step)
        {
            //    Calculate line-ending and padding
            if ($x >= $width)
            {
                if ($padding_flag) {
                    $i += $width % 4;
                }
                $x = 0;
                $y++;
                if ($y > $height) break;
            }
            $i_pos  = $i << 1;
            if(!isset($body[$i_pos + 5])) { break; }
            $r =hexdec($body[$i_pos + 4] . $body[$i_pos + 5]);
            $g = hexdec($body[$i_pos + 2] . $body[$i_pos + 3]);
            $b  = hexdec($body[$i_pos].$body[$i_pos + 1]);
            $color = imagecolorallocate($image, $r, $g, $b);
            imagesetpixel($image, $x, $height - $y, $color);
            $x++;
        }
        unset($body);
        return $image;
    }
}
