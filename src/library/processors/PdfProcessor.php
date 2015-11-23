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

/**
 * Used to create crawl summary information
 * for PDF files
 *
 * @author Chris Pollett
 */
class PdfProcessor extends TextProcessor
{
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
        self::$indexed_file_types[] = "pdf";
        self::$mime_processor["application/pdf"] = "PdfProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a string consisting of PDF data.
     *
     * @param $page  a string consisting of web-page contents
     * @param $url  the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $text = "";
        if (is_string($page)) {
            $text =  self::getText($page);
        }
        if ($text == "") {
            $text = $url;
        }
        $summary = parent::process($text, $url);
        return $summary;
    }
    /**
     * Gets the text out of a PDF document
     *
     * @param string $pdf_string a string representing the PDF document
     * @return string text extracted from the document
     */
    public static function getText($pdf_string)
    {
        $len = strlen($pdf_string);
        $cur_pos = 0;
        $out = "";

        $i = 0;
        while($cur_pos < $len) {
            list($cur_pos, $object_string) =
                self::getNextObject($pdf_string, $cur_pos);
            $object_dictionary = self::getObjectDictionary($object_string);
            if (!self::objectDictionaryHas(
                $object_dictionary, ["Image", "Catalog"])) {
                $stream_data =
                    rtrim(ltrim(self::getObjectStream($object_string)));
                if (self::objectDictionaryHas(
                    $object_dictionary, ["FlateDecode"])) {
                    $stream_data = @gzuncompress($stream_data);
                    if (strpos($stream_data, "PS-AdobeFont")){
                        $out .= $stream_data;
                        break;
                    }
                    $text = self::parseText($stream_data);
                    $out .= $text;

                } else {
                    $text = self::parseText($stream_data);
                    if (strpos($stream_data, "PS-AdobeFont")){
                        $out .= $stream_data;
                        break;
                    }
                    $out .= $text;
                }
            }
        }
        $font_pos = strpos($out, "PS-AdobeFont");
        if (!$font_pos) {
            $font_pos = strlen($out);
        }
        $out = substr($out, 0, $font_pos);
        return $out;
    }
    /**
     * Gets between an obj and endobj tag at the current position in a PDF
     * document
     *
     * @param string $pdf_string astring of a PDF document
     * @param int $cur_pos a integer postion in that string
     * @return string the contents of the PDF object located at $cur_pos
     */
    public static function getNextObject($pdf_string, $cur_pos)
    {
        return self::getBetweenTags($pdf_string, $cur_pos, "obj", "endobj");
    }
    /**
     * Checks if the PDF object's object dictionary is in a list of types
     *
     * @param string $object_dictionary the object dictionary to check
     * @param array $type_array the list of types to check against
     * @return whether it is in or not
     */
    public static function objectDictionaryHas($object_dictionary, $type_array)
    {
        foreach ($type_array as $type) {
            if (strstr($object_dictionary, $type)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Gets the object dictionary portion of the current PDF object
     * @param string $object_string represents the contents of a PDF object
     * @return string the object dictionary for the object
     */
    public static function getObjectDictionary($object_string)
    {
        list( , $object_dictionary) =
            self::getBetweenTags($object_string, 0, '<<', '>>');
        return $object_dictionary;
    }
    /**
     * Gets the object stream portion of the current PDF object
     *
     * @param string $object_string represents the contents of a PDF object
     * @return string the object stream for the object
     */
    public static function getObjectStream($object_string)
    {
        list( , $stream_data) =
            self::getBetweenTags($object_string, 0, 'stream', 'endstream');
        return $stream_data;
    }
    /**
     * Extracts ASCII text from PDF data, getting rid of non printable data,
     * square brackets and parenthesis and converting char codes to their
     * values.
     *
     * @param string $data source to extract character data from
     * @return string extracted text
     */
    public static function parseText($data)
    {
        $cur_pos = 0;
        //replace ASCII codes in decimal with their value
        $data = preg_replace_callback('/\\\(\d{3})/',
            function($matches) {
                return chr(intval($matches[1]));
            },
            $data);
        //replace ASCII codes in hex with their value
        $data = preg_replace_callback('/\<([0-9A-F]{2})\>/',
            function($matches) {
                return chr(hexdec($matches[1]));
            },
            $data);
        $len = strlen($data);
        $out = "";
        $escape_flag =false;
        while($cur_pos < $len) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '[' && !$escape_flag) {
                list($cur_pos, $text) = self::parseBrackets($data, $cur_pos);
                $cur_pos--;
                $out .= " ".$text;
            }
            if ($cur_char == '\\') {
                $escape_flag = true;
            } else {
                $escape_flag = false;
            }
            $cur_pos++;
        }
        return $out;
    }
    /**
     * Extracts ASCII text till the next close brackets
     *
     * @param string $data source to extract character data from
     * @param int $cur_pos position to start in $data
     * @return array pair consisting of the final position in $data as well
     *     as extracted text
     */
    public static function parseBrackets($data, $cur_pos)
    {
        $cur_pos++;
        $len = strlen($data);
        $out = "";
        $escape_flag =false;
        $cur_char="";
        while($cur_pos < $len && ($cur_char != "]")) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '(') {
                list($cur_pos, $text) = self::parseParentheses($data, $cur_pos);
                $cur_pos --;
                $out .= $text;
            }
            $cur_pos++;
        }
        if (isset($data[$cur_pos]) && isset($data[$cur_pos + 1]) &&
            ord($data[$cur_pos]) == ord('T') &&
                ord($data[$cur_pos + 1]) == ord('J') ) {
            if (isset($data[$cur_pos + 3]) &&
                ord($data[$cur_pos + 3]) != ord('F')) {
                $out .= " ";
            } else {
                $out .= "\n";
            }
        }
        return [$cur_pos, $out];
    }
    /**
     * Extracts ASCII text till the next close parenthesis
     *
     * @param string $data source to extract character data from
     * @param int $cur_pos position to start in $data
     * @return array pair consisting of the final position in $data as well
     *     as extracted text
     */
    public static function parseParentheses($data, $cur_pos)
    {
        $cur_pos++;
        $len = strlen($data);
        $out = "";
        $escape_flag =false;
        $cur_char = "";
        while($cur_pos < $len && ($cur_char != ")" || $escape_flag)) {
            $cur_char = $data[$cur_pos];
            if ($cur_char == '\\' && !$escape_flag) {
                $escape_flag = true;
            } else {
                if ($escape_flag || $cur_char !=")"){
                    $ascii = ord($cur_char);
                    if ((9 <= $ascii && $ascii <= 13) ||
                        (32 <= $ascii && $ascii <= 126)) {
                        $out .= $cur_char;
                    }
                }
                $escape_flag = false;
            }
            $cur_pos++;
        }
        $check_positioning = substr($data, $cur_pos, 4);
        if (preg_match("/\-\d{3}/", $check_positioning) > 0 ) {
            $out .= " ";
        }
        return [$cur_pos, $out];
    }
}
