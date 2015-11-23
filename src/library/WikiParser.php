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
use seekquarry\yioop\library\processors\TextProcessor;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Class with methods to parse mediawiki documents, both within Yioop, and
 * when Yioop indexes mediawiki dumps as from Wikipedia.
 *
 * @author Chris Pollett
 */
class WikiParser implements CrawlConstants
{
    /**
     * Escape string to try to prevent incorrect nesting of div for some of the
     * substitutions;
     * @var string
     */
    public $esc=",[}";
    /**
     * Whether the parser should be configured only to do minimal substituitions
     * or all available (minimal might be used for posts in discussion groups)
     * @var bool
     */
    public $minimal;
    /**
     * Used to initialize the arrays of match/replacements used to format
     * wikimedia syntax into HTML (not perfectly since we are only doing
     * regexes)
     *
     * @param string $base_address base url for link substitutions
     * @param array $add_substitutions additional wiki rule subsitutions in
     *      addition to the default ones that should be used by this wiki parser
     * @param bool $minimal substitution list is shorter - suitable for
     *      posting to discussion
     */
    public function __construct($base_address = "", $add_substitutions = [],
        $minimal = false)
    {
        $esc = $this->esc;
        $not_braces = '(?:[^\}]|\}[^\}])*';
        $not_paragraph = '(?:\A|[^\n]|[^\n]\n)';
        $class_or_id = '0-9a-zA-Z\_\-\s';
        $this->minimal = $minimal;
        //assume substitutions are applied after htmlentities called on string
        $substitutions = [
            ['/(\A|\n)=\s*([^=]+)\s*=/', "\n<h1 id='$2'>$2</h1>"],
            ['/(\A|\n)==\s*([^=]+)\s*==/', "\n<h2 id='$2'>$2</h2>"],
            ['/(\A|\n)===\s*([^=]+)\s*===/', "\n<h3 id='$2'>$2</h3>"],
            ['/(\A|\n)====\s*([^=]+)\s*====/', "\n<h4 id='$2'>$2</h4>"],
            ['/(\A|\n)=====\s*([^=]+)\s*=====/', "\n<h5 id='$2'>$2</h5>"],
            ['/(\A|\n)======\s*([^=]+)\s*======/', "\n<h6 id='$2'>$2</h6>"],
            ["/\n*?{{Main\s*\|(.+?)(\|.+)?}}/i",
                "$esc<div class='indent'>\n\n(<a href=\"".
                $base_address."$1\">$1</a>)\n\n$esc</div>"],
            ["/\n*?{{See also\s*\|(.+?)(\|.+)?}}/i",
                "$esc<div class='indent'>\n\n(<a href=\"".
                $base_address."$1\">$1</a>)\n\n$esc</div>"],
            ["/\n*?{{See\s*\|(.+?)(\|.+)?}}/i",
                "$esc<div class='indent'>\n\n(<a href=\"".
                $base_address."$1\">$1</a>)\n\n$esc</div>"],
            ["/\n*?{{\s*Related articles\s*\|(.+?)\|(.+?)}}/si",
                "$esc<div class='indent'>\n\n(<a href=\"".
                $base_address . "$1\">$1?</a>)\n\n$esc</div>"],
            ['/{{Hatnote\|('.$not_braces.')}}/', "($1)"],
            ["/{{lang[\||\-](.+?)\|(.+?)}}/si", "$1 &rarr; $2"],
            ["/{{convert\|(.+?)\|(.+?)\|(.+?)}}/si", "$1$2"],
            ["/{{IPA-(.+?)\|(.+?)}}/si", "(IPA $2)"],
            ["/{{dablink\|('.$not_braces.')}}/", "($1)"],
        ];
        $minimal_substitutions = [
            ['/\[\[(http[^\s\|\]]+)\|([^\[\]]+?)\]\]/s',
                "<a href=\"$1\">$2</a>"],
            ['/\[\[([^\[\]]+?)\|([^\[\]]+?)\]\]/s',
                "<a href=\"{$base_address}$1\">$2</a>\t"],
            ['/\[\[((\#?)[^\[\]]+?)\]\]/s',
                "<a href=\"{$base_address}$1\">$1</a>\t"],
            ["/\[(http[^\s\]]+)\s+(.+?)\]/s", "[<a href=\"$1\">$2</a>]"],
            ["/\[(http[^\]\s]+)\s*\]/","(<a href=\"$1\">&rarr;</a>)"],
            ["/&#039;&#039;&#039;&#039;&#039;(.+?)".
                "&#039;&#039;&#039;&#039;&#039;/s", "<b><i>$1</i></b>\t"],
            ["/&#039;&#039;&#039;(.+?)&#039;&#039;&#039;/s","<b>$1</b>\t"],
            ["/&#039;&#039;(.+?)&#039;&#039;/s", "<i>$1</i>\t"],
            ["/{{(".$not_braces."{{(".$not_braces."({{".$not_braces.
                "({{".$not_braces."}})+".$not_braces."}})+".
                $not_braces.")+}}".$not_braces.")}}/", "@@~3$1~3@@"],
            ["/{{(".$not_braces."{{(".$not_braces."({{".$not_braces."}})+".
                $not_braces.")+}}".$not_braces.")}}/", "@@~2$1~2@@"],
            ["/{{(".$not_braces."({{".$not_braces."}})+".
                $not_braces.")}}/", "@@~1$1~1@@"],
            ["/&lt;blockquote&gt;(.+?)&lt;\/blockquote&gt;/s",
                "$esc<blockquote>\n\n$1\n\n$esc</blockquote>"],
            ["/&lt;pre&gt;(.+?)&lt;\/pre&gt;/s",
                "$esc<pre>$1$esc</pre>"],
            ["/(\A|\n)(( |\t).*(\n|\Z))+/", "$esc<pre>$0$esc</pre>\n"],
            ["/&lt;tt&gt;(.+?)&lt;\/tt&gt;/s", "<tt>$1</tt>"],
            ["/&lt;u&gt;(.+?)&lt;\/u&gt;/s", "<u>$1</u>"],
            ["/&lt;strike&gt;(.+?)&lt;\/strike&gt;/s", "<strike>$1</strike>"],
            ["/&lt;s&gt;(.+?)&lt;\/s&gt;/s", "<s>$1</s>\t"],
            ["/&lt;ins&gt;(.+?)&lt;\/ins&gt;/s", "<ins>$1</ins>\t"],
            ["/&lt;del&gt;(.+?)&lt;\/del&gt;/s", "<del>$1</del>\t"],
            ["/&lt;sub&gt;(.+?)&lt;\/sub&gt;/s", "<sub>$1</sub>\t"],
            ["/&lt;sup&gt;(.+?)&lt;\/sup&gt;/s", "<sup>$1</sup>\t"],
            ["/&lt;math&gt;(.+?)&lt;\/math&gt;/s", "`$1`"],
            ["/&lt;br(\s*\/)?&gt;/", "<br />"],
            ["/&amp;(\#?[a-zA-Z0-9_]+);/", '&$1;'],
            ['/(\A|\n)\*(.+)(\n(\*|\n\s*|\Z|<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$2</li>\n$4"],
            ['/(\A|\n)\*(.+)(\n(\*|\n\s*|\Z|<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$2</li>\n$4"],
            ['/(\A|[^>])\n<li>/', "$1\n<ul>\n<li>"],
            ['@</li>\n(\Z|([^<]|<[^l\/]))@', "</li>\n</ul>\n$1"],
            ['@</li>\n<li>\*@', "\n**"],
            ['/\n\*\*(.+)(\n(\*|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/\n\*\*(.+)(\n(\*|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/([^>])\n<li>/', "$1\n<ul>\n<li>"],
            ['@</li></li>@', "</li>\n</ul>\n</li>"],
            ['/(\A|\n)\#(.+)(\n(\#|\n\s*|\Z|<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$2</li>\n$4"],
            ['/(\A|\n)\#(.+)(\n(\#|\n\s*|\Z|<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$2</li>\n$4"],
            ['/(\A|[^>])\n<li>/', "$1\n<ol>\n<li>"],
            ['@</li>\n(\Z|([^<]|<[^l\/]))@', "</li>\n</ol>\n$1"],
            ['@</li>\n<li>\#@', "\n##"],
            ['/\n\#\#(.+)(\n(\#|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/\n\#\#(.+)(\n(\#|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/([^>])\n<li>/', "$1\n<ol>\n<li>"],
            ['@</li></li>@', "</li>\n</ol>\n</li>"],
            ['@</li>\n<li>\*@', "\n**"],
            ['/\n\*\*(.+)(\n(\*|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/\n\*\*(.+)(\n(\*|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/([^>])\n<li>/', "$1\n<ul>\n<li>"],
            ['@</li></li>@', "</li>\n</ul>\n</li>"],
            ['@</li>\n<li>\#@', "\n##"],
            ['/\n\#\#(.+)(\n(\#|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/\n\#\#(.+)(\n(\#|\n\s*|\Z|\<|[\t\n\r]+\n)|\Z)/sU',
                "\n<li>$1</li>\n$3"],
            ['/([^>])\n<li>/', "$1\n<ol>\n<li>"],
            ['@</li></li>@', "</li>\n</ol>\n</li>"],
            ['/(\A|\n);([^:]+):(.+)(\n(;|[\t\n\r]+\n|<|\n\s*)|\Z)/sU',
                "\n<dl><dt>$2</dt><dd>$3</dd></dl>$4"],
            ['/(\A|\n);([^:]+):(.+)(\n(;|[\t\n\r]+\n|<|\n\s*)|\Z)/sU',
                "\n<dl><dt>$2</dt><dd>$3</dd></dl>$4"],
            ['/\<\/dl\>\n*\<dl\>/', "\n"],
            ['/(\A|\n):\s/', "\n<span class='indent1'>&nbsp;</span>\t"],
            ['/(\A|\n)::\s/', "\n<span class='indent2'>&nbsp;</span>\t"],
            ['/(\A|\n):::\s/', "\n<span class='indent3'>&nbsp;</span>\t"],
            ['/(\A|\n)::::\s/', "\n<span class='indent4'>&nbsp;</span>\t"],
            ['/(\A|\n)(:)+::::\s/',
                "\n<span class='indent5'>&nbsp;</span>\t"],
            ['/{{smallcaps\|(.+?)}}/s', "<small>$1</small>\t"],
            ["/{{fraction\|(.+?)\|(.+?)}}/si", "<small>$1/$2</small>\t"],
            ['/(\A|\n)----/', "$1<hr />"],
            ['/\r/', ""],
        ];
        $braces_substitutions = [
            ['/'.$not_paragraph.'{{\s*class\s*\=\s*'.
                "&quot;([$class_or_id]+)&quot;\s+(".$not_braces .")}}/",
                "$esc<span class=\"$1\" >\t\n$2$esc</span>\t"],
            ['/'.$not_paragraph.'{{\s*class\s*\=\s*'.
                "&#039;([$class_or_id]+)&#039;\s+(".$not_braces .")}}/",
                "$esc<span class=\"$1\" >\t\n$2$esc</span>\t"],
            ['/\n*?{{\s*class\s*\=\s*'.
                "&quot;([$class_or_id]+)&quot;\s+(".$not_braces .")}}/",
                "\n\n$esc<div class=\"$1\" >\n\n$2\n\n$esc</div>"],
            ['/\n*?{{\s*class\s*\=\s*'.
                "&#039;([$class_or_id]+)&#039;\s+(".$not_braces .")}}/",
                "\n\n$esc<div class='$1' >\n\n$2\n\n$esc</div>"],
            ["/\n*?{{\s*id\s*\=\s*&quot;([$class_or_id]+)&quot;\s+(".
                $not_braces .')}}/',
                "$esc<span id=\"$1\">$2$esc</span>"],
            ["/\n*?{{\s*id\s*\=\s*&quot;([$class_or_id]+)&quot;\s+(".
                $not_braces .')}}/',
                "\n\n$esc<div id=\"$1\">\n\n$2\n\n$esc</div>"],
            ["/\n*?{{\s*id\s*\=\s*&#039;([$class_or_id]+)&#039;\s+("
                .$not_braces .')}}/',
                "\n\n$esc<div id='$1'>\n\n$2\n\n$esc</div>"],
            ['/'.$not_paragraph.'{{\s*style\s*\=\s*'.
                '&quot;([0-9a-zA-Z\/\#\_\-\.\;\:\s\n\%]+)&quot;\s+('.
                 $not_braces .')}}/',
                "$esc<span style=\"$1\">$2$esc</span>"],
            ['/\n*?{{\s*style\s*\=\s*'.
                '&quot;([0-9a-zA-Z\/\#\_\-\.\;\:\s\n\%]+)&quot;\s+('.
                $not_braces .')}}/',
                "\n\n$esc<div style=\"$1\">\n\n$2\n\n$esc</div>"],
            ['/'.$not_paragraph.'{{\s*style\s*\=\s*'.
                '&#039;([0-9a-zA-Z\_\-\.\;\:\s\n\%]+)&#039;'.
                '\s+('.$not_braces .')}}/',
                "$esc<span style='$1'>\t$2$esc</span>\t"],
            ['/\n*?{{\s*style\s*\=\s*'.
                '&#039;([0-9a-zA-Z\_\-\.\;\:\s\n\%]+)&#039;\s+('.
                $not_braces .')}}/',
                "\n\n$esc<div style='$1'>\n\n$2\n\n$esc</div>"],
            ['/\n*{{center\s*\|\s*('.$not_braces .')}}/',
                "\n\n$esc<div class='center'>\n\n$1\n\n$esc</div>"],
            ['/\n*?{{left\s*\|\s*('.$not_braces .')}}/',
                "\n\n$esc<div class='align-left'>\n\n$1\n\n$esc</div>"],
            ['/\n*?{{right\s*\|\s*('.$not_braces .')}}/',
                "\n\n$esc<div class='align-right'>\n\n$1\n\n$esc</div>"],
        ];
        if ($minimal) {
            $substitutions = [
                ['/(\A|\n)=\s*([^=]+)\s*=/',
                    "\n<h1 id='$2'>$2</h1>"],
            ];
        }
        $substitutions = array_merge($substitutions,
            $minimal_substitutions);
        $this->matches = [];
        $this->replaces = [];
        $this->braces_matches = [];
        $this->braces_replaces = [];
        $this->base_address = $base_address;
        if ($add_substitutions != []) {
            $substitutions = array_merge($substitutions,
                $braces_substitutions, $add_substitutions);
        }
        foreach ($substitutions as $substitution) {
            list($this->matches[], $this->replaces[]) = $substitution;
        }
        foreach ($braces_substitutions as $substitution) {
            list($this->braces_matches[], $this->braces_replaces[]) =
                $substitution;
        }
    }
    /**
     * Parses a mediawiki document to produce an HTML equivalent
     *
     * @param string $document a document which might have mediawiki markup
     * @param bool $parse_head_vars header variables are an extension of
     *     mediawiki syntax used to add meta variable and titles to
     *     the head tag of an html document. This flag controls whether to
     *     supprot this extension or not
     * @param bool $handle_big_files for indexing purposes Yioop by default
     *     truncates long documents before indexing them. If true, this
     *     method does not do this default truncation. The true value
     *     is more useful when using Yioop's built-in wiki.
     * @return string HTML document obtained by parsing mediawiki
     *     markup in $document
     */
    public function parse($document, $parse_head_vars = true,
        $handle_big_files = false)
    {
        $head = "";
        $page_type = "standard";
        $head_vars = [];
        $draw_toc = true;
        if ($parse_head_vars && !$this->minimal) {
            $document_parts = explode("END_HEAD_VARS", $document);
            if (count($document_parts) > 1) {
                $head = $document_parts[0];
                $document = $document_parts[1];
                $head_lines = preg_split("/\n\s*\n/", $head);
                foreach ($head_lines as $line) {
                    $semi_pos =  (strpos($line, ";")) ? strpos($line, ";"):
                        strlen($line);
                    $line = substr($line, 0, $semi_pos);
                    $line_parts = explode("=",$line);
                    if (count($line_parts) == 2) {
                        $head_vars[trim(addslashes($line_parts[0]))] =
                            addslashes(trim($line_parts[1]));
                    }
                }
                if (isset($head_vars['page_type'])) {
                    $page_type = $head_vars['page_type'];
                }
                if (isset($head_vars['toc'])) {
                    $draw_toc = $head_vars['toc'];
                }
            }
        }
        $document = preg_replace_callback(
            "/&lt;nowiki&gt;(.+?)&lt;\/nowiki&gt;/s",
            C\NS_LIB . "base64EncodeCallback", $document);
        $document = preg_replace_callback(
            "/&lt;pre&gt;(.+?)&lt;\/pre&gt;/s",
            C\NS_LIB . "spaceEncodeCallback", $document);
        if (!$this->minimal) {
            if ($draw_toc && $page_type != "presentation") {
                $toc = $this->makeTableOfContents($document);
            }
            list($document, $references) = $this->makeReferences($document);
        }
        $document = preg_replace_callback('/(\A|\n){\|(.*?)\n\|}/s',
            C\NS_LIB . "makeTableCallback", $document);
        if ($page_type == "presentation") {
            $lines = explode("\n", $document);
            $out_document = "";
            $slide = "";
            $div = "<div class='slide'>";
            foreach ($lines as $line) {
                if (trim($line) == "....") {
                    $slide = $this->processRegexes($slide);
                    $out_document .= $div .$this->cleanLinksAndParagraphs(
                        $slide) ."</div>";
                    $slide = "";
                } else {
                    $slide .= $line."\n";
                }
            }
            $document = $out_document. $div . $this->processRegexes($slide) .
                "</div>";
        } else if ($handle_big_files) {
            $document= $this->processRegexes($document);
            $document = $this->cleanLinksAndParagraphs($document);
        } else {
            if (strlen($document) > 0.9 * C\MAX_GROUP_PAGE_LEN) {
                $document = substr($document, 0, 0.9 * C\MAX_GROUP_PAGE_LEN);
            }
            $document = $this->processRegexes($document);
            $document = $this->cleanLinksAndParagraphs($document);
        }
        if (!$this->minimal) {
            $document = $this->insertReferences($document, $references);
            if ($draw_toc && $page_type != "presentation") {
                $document = $this->insertTableOfContents($document, $toc);
            }
        }
        $document = preg_replace_callback(
            "/&lt;nowiki&gt;(.+?)&lt;\/nowiki&gt;/s",
            C\NS_LIB . "base64DecodeCallback", $document);
        if ($head != "" && $parse_head_vars) {
            $document = $head . "END_HEAD_VARS" . $document;
        }
        if (!$handle_big_files &&
            strlen($document) > 0.9 * C\MAX_GROUP_PAGE_LEN) {
            $document = substr($document, 0, 0.9 * C\MAX_GROUP_PAGE_LEN);
            TextProcessor::closeDanglingTags($document);
            $document .="...";
        }
        return $document;
    }
    /**
     * Applies all the wiki subsitutions of this WikiParser to the document
     * to create an html document
     * makes use of @see processProvidedRegexes
     * @param string $document a document with wiki syntax
     * @return string result of subistutions to make html
     */
    public function processRegexes($document)
    {
        $document = $this->processProvidedRegexes($this->matches,
            $this->replaces, $document);
        $i = 1;
        while(strpos($document, "@@~$i") !== false) {
            $document = str_replace("@@~$i", "{{", $document);
            $document = str_replace("~$i@@", "}}", $document);
            $document = $this->processProvidedRegexes($this->braces_matches,
                $this->braces_replaces, $document);
            $i++;
        }
        return $document;
    }
    /**
     * Applies a set of transformations from wiki syntax to html to a document
     * @param array $matches an array of things to match for
     * @param array $replaces what to replace matches with
     * @param string $document wiki document to fix
     * @return string document after substitutions
     */
    public function processProvidedRegexes($matches, $replaces, $document)
    {
        if (strlen($document) < C\MAX_GROUP_PAGE_LEN) {
            $document = preg_replace($matches, $replaces, $document);
        } else {
            $num_matches = count($matches);
            for ($i = 0; $i < $num_matches; $i++) {
                crawlTimeoutLog("..Doing wiki substitutions..");
                $document = preg_replace($matches[$i],
                    $replaces[$i], $document);
            }
        }
        return $document;
    }
    /**
     * Replaces with underscores links with spaces, fixes newline issues
     * withing span tags
     * @param string $document wiki document to fix
     * @return string document after substitutions
     */
    public function cleanLinksAndParagraphs($document)
    {
        $esc = $this->esc;
        $document = preg_replace_callback("/((href=)\"([^\"]+)\")/",
            C\NS_LIB . "fixLinksCallback", $document);
        $document = preg_replace_callback(
            "/<span(.+?)<\/span\s*>/s",
            C\NS_LIB . "spanEncodeCallback", $document);
        $doc_parts = preg_split("/\n\n+/", $document);
        $document = "";
        foreach ($doc_parts as $part) {
            if (trim($part) == "") {
                continue;
            }
            $start = substr($part, 0, 3);
            if ($start != $esc) {
                $document .= "\n<div>\n".$part. "\n</div>\n";
            } else {
                $document .= $part;
            }
        }

        $document = str_replace($esc, "", $document);
        return $document;
    }
    /**
     * Used to make a table of contents for a wiki page based on the
     * level two headings on that page.
     *
     * @param string $page a wiki document
     * @return string HTML table of contents to be inserted after wiki
     *     page processed
     */
    public function makeTableOfContents($page)
    {
        $toc= "";
        $matches = [];
        $min_sections_for_toc = 4;
        preg_match_all("/(\A|\n)==(\s*([^=]+)\s*|".
            "|=\s*([^=]+)\s*=)==/", $page, $matches);
        if (isset($matches[3]) && count(array_filter($matches[3])) >=
            $min_sections_for_toc) {
            $headers = [];
            $section = "page_level";
            $headers[$section] = [];
            $i = 0;
            foreach ($matches[2] as $header) {
                if (isset($matches[3][$i]) && $matches[3][$i]) {
                    $section = $header;
                    $headers[$header] = [];
                } else if (isset($matches[4][$i]) && $matches[4][$i]) {
                    $headers[$section][] = $matches[4][$i];
                }
                $i++;
            }
            $toc .= "<div class='top-color' style='border: 1px ".
                "ridge #000; width:70%;padding: 3px; margin:6px;'><ol>\n";
            foreach ($headers as $section => $subsections) {
                if ($section == 'page_level' && $subsections == []) {
                    continue;
                }
                $toc .= "<li><a href='#".addslashes($section)."'>".
                    $section."</a>";
                if ($subsections != []) {
                    $toc .= "<ol style='list-style-type: lower-alpha;'>\n";
                    foreach ($subsections as $subsection) {
                        $toc .= "<li><a href='#".addslashes($subsection)."'>".
                            $subsection."</a></li>";
                    }
                    $toc .= "</ol>";
                }
                $toc .= "</li>\n";
            }
            $toc .= "</ol></div>\n";
        }
        return $toc;
    }
    /**
     * Used to make a reference list for a wiki page based on the
     * cite tags on that page.
     *
     * @param string $page a wiki document
     * @return string HTML reference list to be inserted after wiki
     *     page processed
     */
    public function makeReferences($page)
    {
        $base_address = $this->base_address;
        $references= "\n";
        $matches = [];
        preg_match_all('/{{v?cite(.+?)}}/si', $page, $matches);
        citeCallback(null, 1);
        $page = preg_replace_callback('/{{v?cite?a?t?i?o?n?(.+?)}}/si',
            C\NS_LIB. "citeCallback", $page);
        if (isset($matches[1])) {
            $i = 1;
            $wiki_fields = ["title", "publisher", "author", "journal",
                "book", "quote"];
            foreach ($matches[1] as $reference) {
                $ref_parts = explode("|", $reference);
                $references .= "<div id=\"ref_$i\">$i.".
                    "<a href=\"#cite_$i\">^</a>.";
                crawlTimeoutLog("..Making wiki references outer..");
                if (count($ref_parts) > 0) {
                    $ref_data = [];
                    $type = trim(strtolower($ref_parts[0]));
                    array_shift($ref_parts);
                    foreach ($ref_parts as $part) {
                        crawlTimeoutLog("..Making wiki references inner..");
                        $part_parts = explode("=", $part);
                        if (isset($part_parts[1])){
                            $field = strtolower(trim($part_parts[0]));
                            $value = trim($part_parts[1]);
                            if (in_array($field, $wiki_fields)) {
                                $value = preg_replace($this->matches,
                                    $this->replaces, $value);
                                $value = strip_tags($value,
                                    '<a><b><i><span><img>');
                            }
                            $ref_data[$field] = $value;
                        }
                    }
                    if (!isset($ref_data['author']) && isset($ref_data['last'])
                        && isset($ref_data['first'])) {
                        $ref_data['author'] = $ref_data['last'].", ".
                            $ref_data['first'];
                    }
                    if (isset($ref_data['authorlink']) ) {
                        if (!isset($ref_data['author'])) {
                            $ref_data['author'] = $ref_data['authorlink'];
                        }
                        $ref_data['author'] = "<a href=\"$base_address".
                            $ref_data['author']."\">{$ref_data['author']}</a>";
                    }
                    for ($i = 2; $i < 6; $i++) {
                        if (!isset($ref_data["author$i"]) &&
                            isset($ref_data["last$i"])
                            && isset($ref_data['first'])) {
                            $ref_data["author$i"] = $ref_data["last$i"].", ".
                                $ref_data["first$i"];
                        }
                        if (!isset($ref_data["author$i"])) {break; }
                        if (isset($ref_data["authorlink$i"]) ) {
                            if (!isset($ref_data["author$i"])) {
                                $ref_data["author$i"] =
                                    $ref_data["authorlink$i"];
                            }
                            $ref_data["author$i"] = "<a href=\"$base_address".
                                $ref_data["author$i"].
                                "\">".$ref_data["author$i"]."</a>";
                        }
                        if(isset($ref_data["author"])) {
                            $ref_data["author"] .=
                                " and " . $ref_data["author$i"];
                        }
                    }
                    if (!isset($ref_data['title']) && isset($ref_data['url'])) {
                        $ref_data['title'] = $ref_data['url'];
                    }
                    if (isset($ref_data['title']) && isset($ref_data['url'])) {
                        $ref_data['title'] = "<a href=\"{$ref_data['url']}\">".
                            "{$ref_data['title']}</a>";
                    }
                    if (isset($ref_data['quote'])) {
                        $references .= '"'.$ref_data['quote'].'". ';
                    }
                    if (isset($ref_data['author'])) {
                        $references .= $ref_data['author'].". ";
                    }
                    if (isset($ref_data['title'])) {
                        $references .= '"'.$ref_data['title'].'". ';
                    }

                    if (isset($ref_data['accessdate']) &&
                        !isset($ref_data['archivedate'])) {
                        $references .= '('.$ref_data['accessdate'].') ';
                    }
                    if (isset($ref_data['archivedate'])) {
                        if (isset($ref_data['archiveurl'])) {
                            $ref_data['archivedate'] = "<a href=\"".
                                $ref_data['archiveurl']."\">".
                                $ref_data['archivedate']."</a>";
                        }
                        $references .= '('.$ref_data['archivedate'].') ';
                    }
                    if (isset($ref_data['journal'])) {
                         $references .= "<i>{$ref_data['journal']}</i> ";
                    }
                    if (isset($ref_data['location'])) {
                         $references .= $ref_data['location'].". ";
                    }
                    if (isset($ref_data['publisher'])) {
                         $references .= $ref_data['publisher'].". ";
                    }
                    if (isset($ref_data['doi'])) {
                         $references .= "doi:".$ref_data['doi'].". ";
                    }
                    if (isset($ref_data['isbn'])) {
                         $references .= "ISBN:".$ref_data['isbn'].". ";
                    }
                    if (isset($ref_data['jstor'])) {
                         $references .= "JSTOR:".$ref_data['jstor'].". ";
                    }
                    if (isset($ref_data['oclc'])) {
                         $references .= "OCLC:".$ref_data['oclc'].". ";
                    }
                    if (isset($ref_data['volume'])) {
                         $references .= "<b>".$ref_data['volume'].
                            "</b> ";
                    }
                    if (isset($ref_data['issue'])) {
                         $references .= "#".$ref_data['issue'].". ";
                    }
                    if (isset($ref_data['date'])) {
                         $references .= $ref_data['date'].". ";
                    }
                    if (isset($ref_data['year'])) {
                         $references .= $ref_data['year'].". ";
                    }
                    if (isset($ref_data['page'])) {
                         $references .= "p.".$ref_data['page'].". ";
                    }
                    if (isset($ref_data['pages'])) {
                         $references .= "pp.".$ref_data['pages'].". ";
                    }
                }
                $references .="</div>\n";
                $i++;
            }
        }
        return [$page, $references];
    }
    /**
     * After regex processing has been done on a wiki page this function
     * inserts into the resulting page a table of contents just before
     * the first h2 tag, then returns the result page
     *
     * @param string $page page in which to insert table of contents
     * @param string $toc HTML table of contents
     * @return string resulting page after insert
     */
    public function insertTableOfContents($page, $toc)
    {
        $pos = strpos($page, "<h2");
        if ($pos !== false) {
            $start = substr($page, 0, $pos);
            $end = substr($page, $pos);
            $page = $start . $toc. $end;
        }
        return $page;
    }
    /**
     * After regex processing has been done on a wiki page this function
     * inserts into the resulting page a reference at
     * {{reflist locations, then returns the result page
     *
     * @param string $page page in which to insert the reference lists
     * @param string $references HTML table of contents
     * @return string resulting page after insert
     */
    public function insertReferences($page, $references)
    {
        $page = preg_replace('/{{reflist(.+?)}}/si', $references, $page);
        return $page;
    }
}
/**
 * Callback used by a preg_replace_callback in nextPage to make a table
 * @param array $matches of table cells
 */
function makeTableCallback($matches)
{
    $table = str_replace("\n!","\n|#",$matches[2]);
    $table = str_replace("!!","||#",$table);
    $table = str_replace("||","\n|",$table);
    $row_data = explode("|", $table);
    $first = true;
    $out = $matches[1];
    $state = "";
    $skip = false;
    $type = "td";
    $old_type = "td";
    $table_cell_attributes = ["align", "colspan", "style", "scope",
        "rowspace", "valign"];
    foreach ($row_data as $item) {
        crawlTimeoutLog("..Making Wiki Tables..");
        if ($first) {
            $item = trim(str_replace("\n", " ", $item));
            $item = str_replace("&quot;", "\"", $item);
            $item = stripAttributes($item, ['id', 'class', 'style']);
            $out .= "<table $item >\n<tr>";
            $first = false;
            $old_line = true;
            continue;
        }
        $end = substr($out, -4);
        if ($item == "" || $item[0] == "-") {
            if ($end != "<tr>") {
                $out .= "</$old_type>";
            }
            $out .= "</tr>\n<tr>";
            continue;
        }

        if ($item[0] == "+") {
            $type= "caption";
            $item = substr($item, 1);
            if ($end == "<tr>") {
                $out = substr($out, 0, -4);
            }
        } else if ($item[0] == "#") {
            $type= "th";
            $item = substr($item, 1);
        } else {
            $type = "td";
        }
        $trim_item = trim($item);
        $attribute_trim = str_replace("\n", " ", $trim_item);
        $attribute_trim = str_replace("&quot;", "\"", $attribute_trim);
        if (!$skip && $state = trim(
            stripAttributes($attribute_trim, $table_cell_attributes))) {
            $old_type = $type;
            $skip = true;
            continue;
        }
        $skip = false;
        if ($end != "<tr>") {
            $out .= "</$old_type>";
            if ($old_type == "caption") {
                $out .= "<tr>";
            }
        }
        $out .= "<$type $state>\n$trim_item";
        $state = "";
        $old_type = $type;
    }
    $out .= "</$old_type></tr></table>";
    return $out;
}
/**
 * Used to convert {{cite }} to a numbered link to a citation
 *
 * @param array $matches from regular expression to check for {{cite }}
 * @param int $init used to initialize counter for citations
 * @return string a HTML link to citation in current document
 */
function citeCallback($matches, $init = -1)
{
    static $ref_count = 1;
    if ($init != -1) {
        $ref_count = $init;
        return "";
    }
    $out = "<sup>(<a id=\"cite_$ref_count\" ".
        "href=\"#ref_$ref_count\">$ref_count</a>)</sup>";
    $ref_count++;
    return $out;
}
/**
 * Used to changes spaces to underscores in links generated from our earlier
 * matching rules
 *
 * @param array $matches from regular expression to check for links
 * @return string result of correcting link
 */
function fixLinksCallback($matches)
{
    $out = $matches[2].'"'.str_replace(" ", "_", $matches[3]).'"';
    return $out;
}
/**
 * Callback used to base64 encode the contents of nowiki tags so they
 * won't be manipulated by wiki replacements.
 *
 * @param array $matches $matches[1] should contain the contents of a nowiki tag
 * @return string base 64 encoded contents surrounded by an escaped nowiki tag.
 */
function base64EncodeCallback($matches)
{
    return "&lt;nowiki&gt;".base64_encode($matches[1])."&lt;/nowiki&gt;";
}
/**
 * Callback used to base64 encode the contents of pre tags so they
 * won't accidently get sub-pre tags because a bunch of leading lines have
 * spaces
 *
 * @param array $matches $matches[1] should contain the contents of a pre tag
 * @return string encoded contents surrounded by an escaped pre tag.
 */
function spaceEncodeCallback($matches)
{
    $out = str_replace(" ", "&nbsp;", $matches[1]);
    $out = str_replace("\n\n", "&nbsp;\n&nbsp;\n", $out);
    return "&lt;pre&gt;".$out."&lt;/pre&gt;";
}
/**
 * Callback used to base64 encode the contents of pre tags so they
 * won't accidently get sub-pre tags because a bunch of leading lines have
 * spaces
 *
 * @param array $matches $matches[1] should contain the contents of a pre tag
 * @return string encoded contents surrounded by an escaped pre tag.
 */
function spanEncodeCallback($matches)
{
    $out = str_replace("\n\n", "&nbsp;\n&nbsp;\n", $matches[1]);
    return "<span".$out."</span>";
}
/**
 * Callback used to base64 decode the contents of previously base64 encoded
 * (@see base64EncodeCallback) nowiki tags after all mediawiki substitutions
 * have been done
 *
 * @param array $matches $matches[1] should contain the contents of a nowiki tag
 * @return string base 64 decoded contents surrounded by a pre-formatted tag.
 */
function base64DecodeCallback($matches)
{
    return "<pre>".base64_decode($matches[1])."</pre>";
}
