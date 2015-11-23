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
 * @author Charles Bocage charles.bocage@sjsu.edu
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\summarizers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing.
 *
 * @author Charles Bocage charles.bocage@sjsu.edu
 */
class ScrapeSummarizer extends Summarizer
{
    /**
     * This is a basic summarizer
     *
     * @param object $dom   a document object to extract a description from.
     * @param string $page original page string to extract description from
     *
     * @return string the summary
     */
    public static function getBasicSummary($dom, $dom_page)
    {
        return self::description($dom, $dom_page);
    }
    /**
     * Returns descriptive text concerning a webpage based on its document
     * object
     *
     * @param object $dom   a document object to extract a description from.
     * @param string $page original page string to extract description from
     * @return string a description of the page
     */
    public static function description($dom, $page)
    {
        $xpath = new \DOMXPath($dom);
        $metas = $xpath->evaluate("/html//meta");
        $description = "";
        //look for a meta tag with a description
        foreach($metas as $meta) {
            if(stristr($meta->getAttribute('name'), "description")) {
                $description .= " .. ".$meta->getAttribute('content');
            }
        }
        if(PageProcessor::$max_description_len > 2 * C\MAX_DESCRIPTION_LEN) {
            /* if don't need to summarize much, take meta description
               from above code, then concatenate body of doc
               after stripping tags, return result
             */
            $description .= "\n".self::crudeDescription($page);
            return $description;
        }
        /*
          concatenate the contents of then additional dom elements up to
          the limit of description length. Choose tags in order of likely
          importance to this doc
        */
        $page_parts = ["/html//p[1]",
            "/html//div[1]", "/html//p[2]", "/html//div[2]", "/html//p[3]",
            "/html//div[3]", "/html//p[4]", "/html//div[4]",
            "/html//td", "/html//li", "/html//dt", "/html//dd",
            "/html//pre", "/html//a", "/html//article",
            "/html//section", "/html//cite"];
        $para_data = [];
        $len = 0;
        foreach($page_parts as $part) {
            $doc_nodes = $xpath->evaluate($part);
            foreach($doc_nodes as $node) {
                if($part == "/html//a") {
                    $content = $node->getAttribute('href')." = ";
                    $add_len  = min(PageProcessor::$max_description_len / 2,
                        mb_strlen($content));
                    $para_data[$add_len][] = mb_substr($content, 0, $add_len);
                }
                $node_text = self::domNodeToString($node);
                $add_len  = min(PageProcessor::$max_description_len / 2,
                    mb_strlen($node_text));
                $para_data[$add_len][] = mb_substr($node_text, 0, $add_len);
                $len += $add_len;

                if($len > PageProcessor::$max_description_len) { break 2;}
                if(in_array($part, ["/html//p[1]", "/html//div[1]",
                    "/html//div[2]", "/html//p[2]", "/html//p[3]",
                    "/html//div[3]", "/html//div[4]", "/html//p[4]"])){ break;}
            }
        }
        krsort($para_data);
        foreach($para_data as $add_len => $data) {
            if(!isset($first_len)) {
                $first_len = $add_len;
            }
            foreach($data as $datum) {
                $description .= " .. ". $datum;
            }
            if($first_len > 3 * $add_len) break;
        }
        $description = preg_replace("/(\s)+/u", " ",  $description);

        return $description;
    }
    /**
     * Returns summary of body of a web page based on crude regex matching
     *     used as a fall back if dom parsing did not work.
     *
     * @param string $page to extract description from
     * @return string  a title of the page
     */
    public static function crudeDescription($page)
    {
        $body = parent::getBetweenTags($page, 0, "<body", "</body");
        $body = preg_replace("/\</", " <", $body);
        $body = strip_tags("<body".$body[1]."</body>");
        if($body == "") { return $body; }
        $body= preg_replace("/\s+/", " ", $body);
        return mb_substr($body, 0, self::$max_description_len);
    }
    /**
     * This returns the text content of a node but with spaces
     * where tags were (unlike just using textContent)
     *
     * @param object $node a DOMNode
     * @return string its text content with spaces
     */
    public static function domNodeToString($node)
    {
        $text = $node->ownerDocument->saveHTML($node);
        $text = html_entity_decode($text);
        $text = preg_replace('/\</', ' <', $text);
        return strip_tags($text);
    }
}
?>
