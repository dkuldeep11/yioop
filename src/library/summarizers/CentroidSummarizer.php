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
 * @author Mangesh Dahale mangeshadahale@gmail.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\summarizers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * Class which may be used by TextProcessors to get a summary for a text
 * document that may later be used for indexing. It does this by doing
 * centroid-based clustering. It also generates a word cloud for a document
 * @author Mangesh Dahale mangeshadahale@gmail.com
 */
class CentroidSummarizer extends Summarizer
{
    /**
     * Number of bytes in a sentence before it is considered long
     * We use strlen rather than mbstrlen. This might actually be
     * a better metric of the potential of a sentence to have info.
     */
    const LONG_SENTENCE_LEN = 50;
    /**
     * Number of sentences in a document before only consider longer
     * sentences in centroid
     */
    const LONG_SENTENCE_THRESHOLD = 100;
    /**
     * Number of distinct terms to use in generating summary
     */
    const MAX_DISTINCT_TERMS = 1000;
    /**
     * Number of words in word cloud
     */
    const WORD_CLOUD_LEN = 5;
    /**
     * Number of nonzero centroid components
     */
    const CENTROID_COMPONENTS = 50;
    /**
     * whether to output the results to the disk or not
     */
    const OUTPUT_TO_FILE = false;
    /**
     * The full disk location to save the result to
     */
    const OUTPUT_FILE_PATH = "/temp/centroid_summarizer_result.txt";
    /**
     * Generates a centroid with which every sentence is ranked with cosine
     * ranking method and also generates a word cloud.
     * @param string $doc complete raw page to generate the summary from.
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     *
     * @return array array of summary and word cloud
     */
    public static function getCentroidSummary($doc, $lang)
    {
        $doc = self::pageProcessing($doc);
        /* Format the document to remove characters other than periods and
           alphanumerics.
        */
        $formatted_doc = self::formatDoc($doc);
        $stop_obj = PhraseParser::getTokenizer($lang);
        /* Splitting into sentences */
        $out_sentences = self::getSentences($doc);
        $n = count($out_sentences);
        $sentences = [];
        if ($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
            for ($i = 0; $i < $n; $i++ ) {
                $sentences[$i] = $stop_obj->stopwordsRemover(
                    self::formatDoc($out_sentences[$i]));
             }
        } else {
            $sentences = $out_sentences;
        }
        /*  Splitting into terms */
        $terms = [];
        foreach ($sentences as $sentence) {
            $terms = array_merge($terms,
                PhraseParser::segmentSegment($sentence, $lang));
        }
        $terms = array_filter($terms);
        $terms_counts = array_count_values($terms);
        arsort($terms_counts);
        $terms_counts = array_slice($terms_counts, 0,
            self::MAX_DISTINCT_TERMS);
        $terms = array_unique(array_keys($terms_counts));
        $t = count($terms);
        if ($t == 0) {
            return ["", ""];
        }
        /* Initialize Nk [Number of sentences the term occurs] */
        $nk = [];
        $nk = array_fill(0, $t, 0);
        $nt = [];
        /* Count TF for each word */
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $t; $j++) {
                if (strpos($sentences[$i], $terms[$j]) !== false) {
                    $nk[$j]++;
                }
            }
        }
        /* Calculate weights of each term for every sentence */
        $w = [];
        $idf = [];
        $idf_temp = 0;
        for ($k = 0; $k < $t; $k++) {
            if ($nk[$k] == 0) {
                $idf_temp = 0;
                $tmp = 0;
            } else {
                $idf_temp = $n / $nk[$k];
                $tmp = log($idf_temp);
            }
            $idf[$k] = $tmp;
        }
        /* Count TF for finding centroid */
        $wc = [];
        $max_nt = -1;
        $b = "\b";
        if (in_array($lang, ["zh-CN", "ja", "ko"])) {
            $b = "";
        }
        for ($j = 0; $j < $t; $j++) {
            $nt = @preg_match_all("/$b{$terms[$j]}$b/", $formatted_doc,
                $matches); //$matches included for backwards compatibility
            $wc[$j] = $nt * $idf[$j];
            if (is_nan($wc[$j]) || is_infinite($wc[$j])) {
                $wc[$j] = 0;
            }
        }
        /* Calculate centroid */
        arsort($wc);
        $centroid = array_slice($wc, 0, self::CENTROID_COMPONENTS, true);
        /* Initializing centroid weight array by 0 */
        $wc = array_fill(0, $t, 0);
        /* Word cloud */
        $i = 0;
        $word_cloud = [];
        foreach ($centroid as $key => $value) {
            $wc[$key] = $value;
            if ($i < self::WORD_CLOUD_LEN) {
                $word_cloud[$i] = $terms[$key];
            }
            $i++;
        }
        if (strlen($formatted_doc) < PageProcessor::$max_description_len
            || $n == 1) {
            //if input short only use above to get a word cloud
            $formatted_doc = substr($formatted_doc, 0,
                PageProcessor::$max_description_len);
            return [$formatted_doc, $word_cloud];
        }
        ksort($wc);
        /* Calculate similarity measure between centroid and each sentence */
        $sim = [];
        for ($i=0; $i < $n; $i++) {
            $a = $b1 = $b2 = $c1 = $c2 = $d = 0;
            for ($k = 0; $k < $t; $k++) {
                    $wck = $wc[$k];
                    $idfk = $idf[$k];
                    $tmp = substr_count($sentences[$i], $terms[$k]);
                    $wik = ($tmp > 0) ? $idfk * (1 + log($tmp)) : 0;
                    $a += ($wik * $wck * $idfk);
                    $b1 += ($wik * $wik);
                    $c1 += ($wck * $wck);
            }
            $b2 = sqrt($b1);
            $c2 = sqrt($c1);
            $d = $b2 * $c2;
            if ($d == 0) {
                $sim[$i] = 0;
            } else {
                $sim[$i] = $a / $d;
            }
        }
        arsort($sim);
        /* Getting how many sentences should be there in summary */
        $top = self::summarySentenceCount($out_sentences, $sim);
        $sum_array = [];
        $sum_array = array_keys(array_slice($sim, 0, $top - 1, true));
        sort($sum_array);
        $summary = '';
        foreach($sum_array as $key) {
            $summary .= $out_sentences[$key] . ". ";
        }
        if (self::OUTPUT_TO_FILE) {
            $output_file_contents = "";
            foreach($sum_array as $key) {
                $output_file_contents .= $out_sentences[$key] . ".\n";
            }
            file_put_contents(C\WORK_DIRECTORY . self::OUTPUT_FILE_PATH,
                $output_file_contents);
        }
        /* Summary of text summarization */
        return [$summary, $word_cloud];
    }
    /**
     * Calculates how many sentences to put in the summary to match the
     * MAX_DESCRIPTION_LEN.
     *
     * @param array $sentences sentences in doc in their original order
     * @param array $sim associative array of sentence-number-in-doc =>
     *      similarity score to centroid (sorted from highest to lowest score).
     * @return int number of sentences
     */
    public static function summarySentenceCount($sentences, $sim)
    {
        $top = null;
        $count = 0;
        foreach ($sim as $key => $value)
        {
            if ($count < PageProcessor::$max_description_len) {
                $count += strlen($sentences[$key]);
                $top++;
            }
        }
        return $top;
    }
    /**
     * Breaks any content into sentences by splitting it on spaces or carriage
     *   returns
     * @param string $content complete page.
     * @return array array of sentences from that content.
     */
    public static function getSentences($content)
    {
        $lines = preg_split(
            '/(\.|\||\!|\?|！|？|。)\s+|(\n|\r)(\n|\r)+|\s{5}/',
            $content, 0, PREG_SPLIT_NO_EMPTY);
        $out = [];
        $sentence = "";
        $count = 0;
        $theshold_factor = 1;
        foreach ($lines as $line) {
            $sentence .= " " . $line;
            if (strlen($line) < 2) {
                continue;
            }
            if ($count < self::LONG_SENTENCE_THRESHOLD ||
                strlen($sentence) > $theshold_factor *
                    self::LONG_SENTENCE_LEN){
                $sentence = preg_replace("/\s+/ui", " ", $sentence);
                $out[] = trim($sentence);
                $count++;
                $theshold_factor =
                    pow(1.5, floor($count/self::LONG_SENTENCE_THRESHOLD));
            }
            $sentence = "";
        }
        if (trim($sentence) != "") {
            $sentence = preg_replace("/\s+/ui", " ", $sentence);
            $out[] = trim($sentence);
        }
        return $out;
    }
    /**
     * Formats the sentences to remove all characters except words,
     *   digits and spaces
     * @param string $sent complete page.
     * @return string formatted sentences.
     */
    public static function formatSentence($sent)
    {
        $sent = trim(preg_replace('/[^\p{L}\p{N}\s]+/u',
            ' ', mb_strtolower($sent)));
        return $sent;
    }
    /**
     * Formats the document to remove carriage returns, hyphens and digits
     * as we will not be using digits in word cloud.
     * The formatted document generated by this function is only used to
     * compute centroid.
     * @param string $content formatted page.
     * @return string formatted document.
     */
    public static function formatDoc($content)
    {
        $substitute = ['/[\n\r\-]+/', '/[^\p{L}\s\.]+/u', '/[\.]+/'];
        $content = preg_replace($substitute, ' ', mb_strtolower($content));
        return $content;
    }
    /**
     * This function does an additional processing on the page
     * such as removing all the tags from the page
     * @param string $page complete page.
     * @return string processed page.
     */
    public static function pageProcessing($page)
    {
        $substitutions = ['@<script[^>]*?>.*?</script>@si',
            '/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
            '@<style[^>]*?>.*?</style>@si', '/[\^\(\)]/',
            '/\[(.*?)\]/', '/\t\n/'
        ];
        $page = preg_replace($substitutions, ' ', $page);
        $page = preg_replace('/\s{2,}/', ' ', $page);
        $new_page = preg_replace("/\<br\s*(\/)?\s*\>/", "\n", $page);
        $changed = false;
        if ($new_page != $page) {
            $changed = true;
            $page = $new_page;
        }
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/", "\n\n", $page);
        $page = preg_replace("/\<a/", " <a", $page);
        $page = preg_replace("/\&\#\d{3}(\d?)\;|\&\w+\;/", " ", $page);
        $page = preg_replace("/\</", " <", $page);
        $page = strip_tags($page);

        if ($changed) {
            $page = preg_replace("/(\r?\n[\t| ]*){2}/", "\n", $page);
        }
        $page = preg_replace("/(\r?\n[\t| ]*)/", "\n", $page);
        $page = preg_replace("/\n\n\n+/", "\n\n", $page);
        return $page;
    }
}

