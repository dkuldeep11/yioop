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
 * @author Snigdha Rao Parvatneni
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\library\UrlParser;

/**
 * Parent class common to all processors used to create crawl summary
 * information  that involves basically text data
 *
 * @author Chris Pollett
 */
class PythonProcessor extends TextProcessor
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
        self::$indexed_file_types[] = "py";
        self::$mime_processor["text/py"] = "PythonProcessor";
    }
    /**
     * Computes a summary based on a text string of a document
     *
     * @param string $page text string of a document
     * @param string $url location the document came from, not used by
     *     PythonProcessor at this point. Some of its subclasses override
     *     this method and use url to produce complete links for
     *     relative links within a document
     *
     * @return array a summary of (title, description,links, and content) of
     *     the information in $page
     */
    public function process($page, $url)
    {
        $summary = null;

        if (is_string($page)) {
            $summary[self::TITLE] = "";
            $summary[self::DESCRIPTION] = $page;
            $summary[self::LANG] = self::calculateLang(
                $summary[self::DESCRIPTION], $url);
            $summary[self::LINKS] = self::extractHttpHttpsUrls($page);
            $summary[self::PAGE] = "<html><body><div><pre>".
                strip_tags($page)."</pre></div></body></html>";
        }
        return $summary;
    }
    /**
     * Tries to determine the language of the document by looking at the
     * $sample_text and $url provided
     * the language
     * @param string $sample_text sample text to try guess the language from
     * @param string $url url of web-page as a fallback look at the country
     *     to figure out language
     *
     * @return string language tag for guessed language
     */
    public static function calculateLang($sample_text = null, $url = null)
    {
        if ($url != null) {
            $lang = UrlParser::getDocumentType($url);
        }
        return $lang;
    }

}

