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

ini_set("gd.jpeg_ignore_warning", 1);
/**
 * Used to create crawl summary information
 * for JPEG files
 *
 * @author Chris Pollett
 */
class JpgProcessor extends ImageProcessor
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
        self::$indexed_file_types[] = "jpg";
        self::$indexed_file_types[] = "jpeg";
        self::$image_types[] = "jpg";
        self::$image_types[] = "jpeg";
        self::$mime_processor["image/jpeg"] = "JpgProcessor";
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
            $image = @imagecreatefromstring($page);
            $thumb_string = self::createThumb($image);
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = "Image of ".
                UrlParser::getDocumentFilename($url);
            $summary[self::LINKS] = [];
            $summary[self::PAGE] =
                "<html><body><div><img src='data:image/jpeg;base64," .
                base64_encode($page)."' alt='".$summary[self::DESCRIPTION].
                "' /></div></body></html>";
            $summary[self::THUMB] = 'data:image/jpeg;base64,'.
                base64_encode($thumb_string);
        }
        return $summary;
    }
}
