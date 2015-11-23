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

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\PartialZipArchive;

/**
 * Used to create crawl summary information
 * for DOCX files
 *
 * @author Chris Pollett
 */
class DocxProcessor extends TextProcessor
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
        self::$indexed_file_types[] = "docx";
        self::$mime_processor["application/vnd.openxmlformats-".
            "officedocument.wordprocessingml.document"] = "DocxProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a docx file consisting of xml data.
     *
     * @param string $page docx(zip) contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $summary = null;
        $sites = [];
        $zip = new PartialZipArchive($page);
        $buf = $zip->getFromName("docProps/core.xml");
        if ($buf) {
            $dom = self::dom($buf);
            if ($dom !== false) {
                // Try to get the title from the document meta data
                $summary[self::TITLE] = self::title($dom);
            }
        }
        $buf = $zip->getFromName("word/document.xml");
        if ($buf) {
            $dom = self::dom($buf);
            $summary[self::DESCRIPTION] = self::docText($dom);
            $summary[self::LANG] = L\guessLocaleFromString(
                $summary[self::DESCRIPTION], 'en-US');
        } else {
            $summary[self::DESCRIPTION] = "Did not download ".
                "word/document.xml portion of docx file";
            $summary[self::LANG] = 'en-US';
        }
        $buf = $zip->getFromName("word/_rels/document.xml.rels");
        if ($buf) {
            $dom = self::dom($buf);
            $summary[self::LINKS] = self::links($dom, $url);
        } else {
            $summary[self::LINKS] = [];
        }
        return $summary;
    }
    /**
     * Returns up to MAX_LINK_PER_PAGE many links from the supplied
     * dom object where links have been canonicalized according to
     * the supplied $site information.
     *
     * @param object $dom a document object with links on it
     * @param string $site  a string containing a url
     * @return array links from the $dom object
     */
    public static function links($dom, $site)
    {
        $sites = [];
        $hyperlink = "http://schemas.openxmlformats.org/officeDocument/2006/".
            "relationships/hyperlink";
        $i = 0;
        $relationships = $dom->getElementsByTagName("Relationships");
        foreach ($relationships as $relationship) {
            $relations = $relationship->getElementsByTagName("Relationship");
            foreach ($relations as $relation) {
                if ( strcmp( $relation->getAttribute('Type'),
                    $hyperlink) == 0 ) {
                    if ($i < C\MAX_LINKS_TO_EXTRACT) {
                        $link = $relation->getAttribute('Target');
                        $url = UrlParser::canonicalLink(
                            $link, $site);
                        if (!UrlParser::checkRecursiveUrl($url)  &&
                            strlen($url) < C\MAX_URL_LEN) {
                            if (isset($sites[$url])) {
                                $sites[$url] .=" ".$link;
                            } else {
                                $sites[$url] = $link;
                            }
                            $i++;
                        }
                    }
                }
            }
        }
        return $sites;
    }
    /**
     * Return a document object based on a string containing the contents of
     * a web page
     *
     * @param string $page   xml document
     *
     * @return object  document object
     */
    public static function dom($page)
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($page);
        return $dom;
    }
    /**
     * Returns powerpoint head title of a pptx based on its document object
     *
     * @param object $dom   a document object to extract a title from.
     * @return string  a title of the page
     *
     */
    public static function title($dom)
    {
        $coreProperties = $dom->getElementsByTagName("coreProperties");
        $property = $coreProperties->item(0);
        $title = "";
        if ($property) {
            $titles = $property->getElementsByTagName("title");
            if ($titles->item(0)) {
                $title = $titles->item(0)->nodeValue;
            }
        }
        return $title;
    }
    /**
     * Returns descriptive text concerning a pptx slide based on its document
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @return string a description of the slide
     */
    public static function docText($dom)
    {
        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->evaluate("//w:p");
        $description = "";
        $len = 0;
        foreach ($paragraphs as $paragraph) {
            $text = $paragraph->nodeValue."\n\n";
            $text_len = strlen($text);
            $len += $text_len;
            if ($len > self::$max_description_len) {break; }
            $description .= $text;
        }
        return $description;
    }
}
