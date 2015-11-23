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
 * @author Vijeth Patil vijeth.patil@gmail.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\PartialZipArchive;

/**
 * Used to create crawl summary information
 * for XML files (those served as application/epub+zip)
 *
 * @author Vijeth Patil
 */
class EpubProcessor extends TextProcessor
{
    /**
     * The constant represents the number of
     * child levels at which the data is present in
     * the content.opf file.
     */
    const MAX_DOM_LEVEL = 15;
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
        self::$indexed_file_types[] = "epub";
        self::$mime_processor["application/epub+zip"] = "EpubProcessor";
    }
    /**
     * The name of the tag element in an xml document
     *
     * @var string name
     */
    public $name;
    /**
     * The attribute of the tag element in an xml document
     *
     * @var string attributes
     */
    public $attributes;
    /**
     * The content of the tag element or attribute, used to extract
     * the fields like title, creator, language of the document
     *
     * @var string content
     */
    public $content;
    /**
     * The child tag element of a tag element.
     *
     * @var string children
     */
    public $children;
    /**
     * Used to extract the title, description and links from
     * a string consisting of ebook publication data.
     *
     * @param string $page epub contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array  a summary of the contents of the page
     *
     */
    public function process($page, $url)
    {
        $summary = null;
        $opf_pattern = "/.opf$/i";
        $html_pattern  = "/.html$/i";
        $xhtml_pattern = "/.xhtml$/i";
        $epub_url[0] = '';
        $epub_language = '';
        $epub_title = '';
        $epub_unique_identifier = '';
        $epub_author = '';
        $epub_publisher = '';
        $epub_date = '';
        $epub_subject = '';
        $desc = '';
        $htmlcontent = '';
        // Open a zip archive
        $zip = new PartialZipArchive($page);
        $num_files = $zip->numFiles();
        for ($i = 0; $i < $num_files; $i++) {
            // get the content file names of .epub document
            $filename[$i] = $zip->getNameIndex($i);
            if (preg_match($opf_pattern, $filename[$i])) {
                // Get the file data from zipped folder
                $opf_data = $zip->getFromName($filename[$i]);
                $opf_summary = $this->xmlToObject($opf_data);
                for ($m = 0; $m <= self::MAX_DOM_LEVEL; $m++) {
                    for ($n = 0; $n <= self::MAX_DOM_LEVEL; $n++) {
                        if (isset($opf_summary->children[$m]->children[$n])){
                            $child = $opf_summary->children[$m]->
                                children[$n];
                            if ( isset($child->name) &&
                                $child->name == "dc:language") {
                                $epub_language =
                                    $opf_summary->children[$m]->
                                        children[$n]->content ;
                            }
                            if ( ($opf_summary->children[$m]->children[$n]->
                                name) == "dc:title") {
                                $epub_title = $opf_summary->children[$m]->
                                    children[$n]->content;
                            }
                            if ( ($opf_summary->children[$m]->children[$n]->
                                name) == "dc:creator") {
                                $epub_author = $opf_summary->children[$m]->
                                    children[$n]->content ;
                            }
                            if ( ($opf_summary->children[$m]->children[$n]->
                                name) == "dc:identifier") {
                                $epub_unique_identifier = $opf_summary->
                                    children[$m]->children[$n]->content ;
                            }
                        }
                    }
                }
            } else if ((preg_match($html_pattern, $filename[$i])) ||
                (preg_match($xhtml_pattern, $filename[$i]))) {
                $html = new HtmlProcessor();
                $html_data = $zip->getFromName($filename[$i]);
                $description[$i] = $html->process($html_data, $url);
                $htmlcontent.= $description[$i]['t'];
            }
        }
        if ($epub_title != '') {
            $desc= " $epub_title .";
        }
        if ($epub_author != '') {
            $desc = $desc." $epub_author ";
        }
        if ($epub_language != '') {
            $desc = $desc." $epub_language ";
        }
        if ($epub_unique_identifier != '') {
            $desc = $desc." URN-".
            $epub_unique_identifier.".";
        }
        if ($epub_publisher != '') {
            $desc = $desc." $epub_publisher ";
        }
        if ($epub_date != '') {
            $desc = $desc." $epub_date ";
        }
        if ($epub_subject != '') {
            $desc = $desc." $epub_subject ";
        }
        $desc= $desc.$htmlcontent;
        //restrict the length of the description to maximum description length
        if (strlen($desc) > self::$max_description_len) {
            $desc = substr($desc, 0, self::$max_description_len);
        }
        $summary[self::TITLE] = $epub_title;
        $summary[self::DESCRIPTION] = $desc;
        $summary[self::LANG] = $epub_language;
        $summary[self::LINKS] = $epub_url;
        $summary[self::PAGE] = $page;
        return $summary;
    }
    /**
     * Used to extract the DOM tree containing the information
     * about the epub file such as title, author, language, unique
     * identifier of the book from a string consisting of ebook publication
     * content OPF file.
     *
     * @param string $xml page contents
     *
     * @return array an information about the contents of the page
     *
     */
    public function xmlToObject($xml)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xml, $tags);
        xml_parser_free($parser);
        $elements = [];  // the currently filling [child] XmlElement array
        $stack = [];
        foreach ($tags as $tag) {
            $index = count($elements);
            if ($tag['type'] == "complete" || $tag['type'] == "open") {
                $elements[$index] = new EpubProcessor;
                $elements[$index]->name = $tag['tag'];
                if (isset($tag['attributes'])) {
                    $elements[$index]->attributes = $tag['attributes'];
                }
                if (isset($tag['value'])) {
                    $elements[$index]->content = $tag['value'];
                }
                if ($tag['type'] == "open") {  // push
                    $elements[$index]->children = [];
                    $stack[count($stack)] = &$elements;
                    $elements = &$elements[$index]->children;
                }
            }
            if ($tag['type'] == "close") {  // pop
                $elements = &$stack[count($stack) - 1];
                unset($stack[count($stack) - 1]);
            }
        }
        return $elements[0];  // the single top-level element
    }
}
