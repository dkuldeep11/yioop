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
 * TokenTool is used to create suggest word dictionaries and 'n' word gram
 * filter files for the Yioop! search engine.
 *
 * A description of its usage is given in the $usage global variable
 *
 *
 * @author Ravi Dhillon  ravi.dhillon@yahoo.com, Chris Pollett (modified for n
 *     ngrams)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */

namespace seekquarry\yioop\configs;

use seekquarry\yioop\library\NWordGrams;
use seekquarry\yioop\library\Trie;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
ini_set("memory_limit","1500M");
/** Load in global configuration settings */
require_once 'Config.php';
/**
 * Used to print out a description of how to use TokenTool.php
 * @var string
 */
$usage = <<<EOD
TokenTool.php
==============

Usage
=====
TokenTool is used to create suggest word dictionaries,
segment and 'n' word gram filter files for the Yioop! search engine.
To create either of these items, the user
puts a source file in Yioop's WORK_DIRECTORY/prepare folder. Suggest word
dictionaries are used to supply the content of the dropdown of search terms
that appears as a user is entering a query in Yioop. To make a suggest
dictionary one can use a command like:

php TokenTool.php dictionary filename locale endmarker

Here filename should be in the current folder or PREP_DIR and should consist
of one word per line, locale is the locale this suggest (for example, en-US)
file is being made for and where a file suggest-trie.txt.gz will be written,
and endmarker is the end of word symbol to use in the trie. For example,
$ works pretty well.

TokenTool.php can also be used to make filter files. A filter file is used to
detect when words in a language should be treated as a unit when extracting text
during a crawl and at search time.  For example, Bill Clinton is 2 word gram
which should be treated as unit because it is a particular person. These
filter files can also be used  with a segmenter which
might be used to split Chinese or Japanese text which does not have spaces into
a sequence of Chinese and Japanese words (which may be made out of multiple
characters). For a nonsegmenter filter, TokenTool.php is run from the
command line as:

php TokenTool.php filter wiki_file lang locale n extract_type max_to_extract

where file is a wikipedia xml file or is a bz2  compressed xml file whose urls
or wiki page count dump file (it can also be a folder of these kind of files)
used to determine the n-grams,
lang is an Wikipedia language tag (ignored in segmenter case),
locale is the IANA language tag of the locale to store the results for
(if different from lang, for example, en-US versus en for lang), n is the
number of words in a row to consider , extract_type is where from Wikipedia
source to extract:

0 = title's,
1 = redirect's,
2 = page count dump wikipedia data,
3 = page count dump wiktionary data.

For a segmenter filter, TokenTool.php is run from the
command line as:

php TokenTool.php segment-filter dictionary_file locale

Here dictionary_file should be a text file with one word/line,
locale is the IANA language tag of the locale to store the results for.


Obtaining Data
==============
Many word lists are obtainable on the web for free with Creative Commons
licenses. A good starting point is:
http://en.wiktionary.org/wiki/Wiktionary:Frequency_lists
A little script-fu can generally take such a list and put it into the
format of one word/term per line which is needed by TokenTool.php

For filter file, Raw page count dumps can be found at
http://dumps.wikimedia.org/other/pagecounts-raw/
These probably give the best n-gram or all gram results, usually
in a matter of minutes; nevertheless, this tool does support trying to extract
similar data from Wikipedia dumps. This can take hours.

For Wikipedia dumps, one can go to http://dumps.wikimedia.org/enwiki/
and obtain a dump of the English Wikipedia (similar for other languages).
This page lists all the dumps according to date they were taken. Choose any
suitable date or the latest. A link with a label such as 20120104/, represents
a  dump taken on  01/04/2012.  Click this link to go in turn to a page which has
many links based on type of content you are looking for. For
this tool you are interested in files under

"Recombine all pages, current versions only".

Beneath this we might find a link with a name like:
enwiki-20120104-pages-meta-current.xml.bz2
which is a file that could be processed by this tool.

A Creative Commons licensed file which can be manipulated into a dictionary
file suitable for Chinese segmentation can be found at:
http://www.mdbg.net/chindict/chindict.php?page=cc-cedict

EOD;
$num_args = count($argv);
if ( $num_args < 3 || $num_args > 8) {
    echo $usage;
    exit();
}
switch ($argv[1]) {
    case "dictionary":
        if (!isset($argv[3])) {
            $argv[3] = "en-US";
        }
        if (!isset($argv[4])) {
            $argv[4] = "$";
        }
        makeSuggestTrie($argv[2], $argv[3], $argv[4]);
        break;
    case "filter":
        array_shift($argv);
        array_shift($argv);
        makeNWordGramsFiles($argv);
        break;
    case "segment-filter":
        $file_path = PREP_DIR."/";
        if (!file_exists($file_path.$argv[2])) {
            echo $argv[2]." does not exist in ".$file_path;
            exit();
        }
        NWordGrams::makeSegmentFilterFile($file_path.$argv[2], $argv[3]);
        break;
    default:
        echo $usage;
        exit();
}
if (!PROFILE) {
    echo "Please configure the search engine instance ".
        "by visiting its web interface on localhost.\n";
    exit();
}
/**
 * Makes an n or all word gram Bloom filter based on the supplied arguments
 * Wikipedia files are assumed to have been place in the PREP_DIR before this
 * is run and writes it into the resources folder of the given locale
 *
 * @param array $args command line arguments with first two elements of $argv
 *     removed. For details on which arguments do what see the $usage variable
 */
function makeNWordGramsFiles($args)
{
    if (!isset($args[1])) {
        $args[1] = "en";
        $args[2] = "en-US";
    }
    if (!isset($args[2])) {
        $args[2] = $args[1];
    }
    if (!isset($args[3])) {
        $args[3] = 2; // bigrams
    }
    if (!isset($argv[4])) {
        $args[4] = NWordGrams::PAGE_COUNT_WIKIPEDIA;
    }
    if (!isset($args[5]) && $args[3] == "all" &&
        $args[2] == NWordGrams::PAGE_COUNT_WIKIPEDIA) {
        $args[5] = 400000;
    } else {
        $args[5] = -1;
    }
    $wiki_file_path = PREP_DIR."/";
    if (!file_exists($wiki_file_path.$args[0])) {
        echo $args[0]." does not exist in $wiki_file_path";
        exit();
    }
    /*
     *This call creates a ngrams text file from input xml file and
     *returns the count of ngrams in the text file.
     */
    list($num_ngrams, $max_gram_len) =
        NWordGrams::makeNWordGramsTextFile($args[0], $args[1], $args[2],
        $args[3], $args[4], $args[5]);

    /*
     *This call creates a bloom filter file from n word grams text file based
     *on the language specified.The lang passed as parameter is prefixed
     *to the filter file name. The count of n word grams in text file is passed
     *as a parameter to set the limit of n word grams in the filter file.
     */
    NWordGrams::makeNWordGramsFilterFile($args[2], $args[3], $num_ngrams,
        $max_gram_len);
}

/**
 * Makes a trie that can be used to make word suggestions as someone enters
 * terms into the Yioop! search box. Outputs the result into the file
 * suggest_trie.txt.gz in the supplied locale dir
 *
 * @param string $dict_file where the word list is stored, one word per line
 * @param string $locale which locale to write the suggest file to
 * @param string $end_marker used to indicate end of word in the trie
 */
function makeSuggestTrie($dict_file, $locale, $end_marker)
{
    $locale = str_replace("-", "_", $locale);
    $out_file = LOCALE_DIR."/$locale/resources/suggest_trie.txt.gz";

    // Read and load dictionary and stop word files
    $words = fileWithTrim($dict_file);
    sort($words);
    $trie = new Trie($end_marker);

    /** Ignore the words in the following cases. If the word
     *  - contains punctuation
     *  - is less than 3 characters
     *  - is a stop word
     */
    foreach ($words as $word) {
        if (mb_ereg_match("\p{P}", $word) == 0 && mb_strlen($word) > 2) {
            $trie->add($word);
        }
    }
    $output = [];
    $output["trie_array"] = $trie->trie_array;
    $output["end_marker"] = $trie->end_marker;
    file_put_contents($out_file, gzencode(json_encode($output), 9));
}

/**
 * Reads file into an array or outputs file not found. For each entry in
 * array trims it. Any blank lines are deleted
 *
 * @param $file_name file to read into array
 * @return array of trimmed lines
 */
function fileWithTrim($file_name)
{
    if (!file_exists($file_name)) {
        $file_name = PREP_DIR."/$file_name";
        if (!file_exists($file_name)) {
            echo "$file_name Not Found\n\n";
            return [];
        }
    }
    $file_string = file_get_contents($file_name);
    $pre_lines = mb_split("\n", $file_string);
    $lines = [];
    foreach ($pre_lines as $pre_line) {
        $line = preg_replace( "/(^\s+)|(\s+$)/us", "", $pre_line );
        if ($line != "") {
            array_push($lines, $line);
        }
    }
    return $lines;
}
