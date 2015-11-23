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
 * @author Nakul Natu nakul.natu@gmail.com
 * @license http://www.gnu.org/licenses/ GPLv3
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
 * for PPTX files
 *
 * @author Nakul Natu
 */
class PptxProcessor extends TextProcessor
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
        self::$indexed_file_types[] = "pptx";
        self::$mime_processor["application/vnd.openxmlformats-officedocument.".
            "presentationml.presentation"] = "PptxProcessor";
    }
    /**
     * Used to extract the title, description and links from
     * a pptx file consisting of xml data.
     *
     * @param string $page pptx(zip) contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $summary = null;
        // Open zip archive
        $zip = new PartialZipArchive($page);
        $buf= $zip->getFromName("docProps/core.xml");
        if ($buf) {
            $dom = self::dom($buf);
            if ($dom !== false) {
            // Get the title
                $summary[self::TITLE] = self::title($dom);
            }
        }
        $buf = $zip->getFromName("docProps/app.xml");
        if ($buf) {
        // Get number of slides present
            $dom = self::dom($buf);
            $num_slides = self::numSlides($dom);
        } else {
            /*  go for an upper bound on number of slides (might happen on
                partial download of pptx file)
             */
            $num_slides = $zip->numFiles();
        }
        $summary[self::DESCRIPTION] = "";
        $summary[self::LINKS] = [];
        $lang = null;
        for ($i = 1; $i <= $num_slides; $i++) {
            $buf = $zip->getFromName("ppt/slides/slide" . $i . ".xml");
            if ($buf) {
            /* Get description , language and url links asociated
               with each slide*/
                $dom = self::dom($buf);
                $description = self::slideText($dom);
                if (strlen($summary[self::DESCRIPTION]) + strlen($description)
                    < self::$max_description_len) {
                        $summary[self::DESCRIPTION] .= $description;
                }
                if (!$lang) {
                    $lang = self::lang($dom);
                    if ($lang) {
                        $summary[self::LANG] = $lang;
                    }
                }
                $summary[self::LINKS] = array_merge($summary[self::LINKS],
                    self::links($dom, $url));
            }
        }
        return $summary;
    }
    /**
     * Returns up to MAX_LINK_PER_PAGE many links from the supplied
     * dom object where links have been canonicalized according to
     * the supplied $site information.
     *
     * @param object $dom a document object with links on it
     * @param string $site a string containing a url
     *
     * @return array links from the $dom object
     */
    public static function links($dom, $site)
    {
        $sites = [];
        $xpath = new \DOMXPath($dom);
        $paras = $xpath->evaluate("/p:sld//p:cSld//p:spTree//p:sp//
            p:txBody//a:p//a:r//a:rPr//a:hlinkClick");
        $i=0;
        foreach ($paras as $para) {
            if ($i < C\MAX_LINKS_TO_EXTRACT) {
                $hlink = $para->parentNode->parentNode->
                    getElementsByTagName("t")->item(0)->nodeValue;
                $url = UrlParser::canonicalLink(
                    $hlink, $site);
                $len = strlen($url);
                if (!UrlParser::checkRecursiveUrl($url)  &&
                    strlen($url) < C\MAX_URL_LEN && $len > 0) {
                    if (isset($sites[$url])) {
                        $sites[$url] .= " ".$hlink;
                    } else {
                        $sites[$url] = $hlink;
                    }
                }
            }
            $i++;
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
        $titles = $property->getElementsByTagName("title");
        $title = $titles->item(0)->nodeValue;
        return $title;
    }
    /**
     * Returns number of slides of  pptx based on its document object
     *
     * @param object $dom   a document object to extract a title from.
     * @return number  number of slides
     *
     */
    public static function numSlides($dom)
    {
        $properties = $dom->getElementsByTagName("Properties");
        $property = $properties->item(0);
        $slides = $property->getElementsByTagName("Slides");
        $number = $slides->item(0)->nodeValue;
        return $number;
    }
    /**
     * Determines the language of the xml document by looking at the
     * language attribute of a tag.
     *
     * @param object $dom  a document object to check the language of
     *
     * @return string language tag for guessed language
     */
    public static function lang($dom)
    {
        $xpath = new \DOMXPath($dom);
        $languages = $xpath->evaluate("/p:sld//p:cSld//p:spTree//
            p:sp//p:txBody//a:p//a:r//a:rPr");
        if (!$languages) {
            return false;
        }
        return $languages->item(0)->getAttribute("lang");
    }
    /**
     * Returns descriptive text concerning a pptx slide based on its document
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @return string a description of the slide
     */
    public static function slideText($dom)
    {
        $xpath = new \DOMXPath($dom);
        $paragraphs = $xpath->evaluate("//a:p");
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
