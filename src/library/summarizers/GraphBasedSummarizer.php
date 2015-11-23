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
class GraphBasedSummarizer extends Summarizer
{
    /**
     * Number of distinct terms to use in generating summary
     */
    const MAX_DISTINCT_TERMS = 1000;
    /**
     * whether to output the results to the disk or not
     */
    const OUTPUT_TO_FILE = false;
    /**
     * The full disk location to save the result to
     */
    const OUTPUT_FILE_PATH = "/temp/graph_summarizer_result.txt";
    /**
     * This is a graph based summarizer
     *
     * @param string $doc complete raw page to generate the summary from.
     * @param string $lang language of the page to decide which stop words to
     *     call proper tokenizer.php of the specified language.
     *
     * @return string the summary
     */
    public static function getGraphBasedSummary($doc, $lang)
    {
        $unmodified_doc = strip_tags($doc,
            '<a><h1><h2><h3><h4><h5><h6><b><em><i><u><dl><ol><ul><title>');
        $doc = self::pageProcessing($doc);
        $formatted_doc = self::formatDoc($doc);
        //not filtering non-ascii characters
        $sentences = self::getSentences($doc . " ", true);
        $sentences = self::removeStopWords($sentences, $lang);
        $sentences = self::removePunctuation($sentences);
        $sentences = PhraseParser::stemTermsK($sentences, $lang, true);
        $terms = self::getTerms($sentences, $lang);
        $term_frequencies = self::getTermFrequencies($terms, $sentences,
            $unmodified_doc);
        $term_frequencies_normalized =
            self::normalizeTermFrequencies($term_frequencies);
        $adjacency = self::computeAdjacency($term_frequencies_normalized,
            $sentences, $lang, $unmodified_doc);
        $p = self::getSentenceRanks($adjacency);
        $sentences_with_punctuation = self::getSentences($doc . " ", true);
        $summary = self::getSummary($sentences_with_punctuation, $p);
        return $summary;
    }
    /**
     * Get the summary from the sentences
     * @param array $sentences the sentences in the doc
     * @param array $p the sentence probabilities
     */
    public static function getSummary($sentences, $p)
    {
        $output_file_contents = "";
        $result = "";
        $result_length = 0;
        $n = count($p);
        for($i = 0; $i < $n; $i++ ) {
            $index = self::findLargestIndex($p);
            $p[$index] = -1;
            $sentence = $sentences[$index];
            if ($result_length + strlen($sentence) >
                PageProcessor::$max_description_len) {
                break;
            } else {
                $result_length += strlen($sentence);
                if ($i == 0) {
                    $result = $sentence;
                    if (self::OUTPUT_TO_FILE) {
                        $output_file_contents = $sentence;
                    }
                } else {
                    $result .= " " . $sentence;
                    if (self::OUTPUT_TO_FILE) {
                        $output_file_contents = $output_file_contents .
                            "\r\n" . $sentence;
                    }
                }
            }
        }
        if (self::OUTPUT_TO_FILE) {
            file_put_contents(C\WORK_DIRECTORY . self::OUTPUT_FILE_PATH,
                $output_file_contents);
        }
        return $result;
    }
    /**
     * Find the largest value in the array and return it
     * @param array $v the array to search for the largest value
     * @return double the largest value found in the array
     */
    public static function findLargestIndex($v)
    {
        $result = 0;
        $n = count($v);
        $last_value = -1;
        for ($i = 0; $i < $n; $i++ ) {
            if ($v[$i] > $last_value) {
                $last_value = $v[$i];
                $result = $i;
            }
        }
        return $result;
    }
    /**
     * Compute the sentence ranks using a version of the famous
     * page ranking algorithm developed by the founder of Google.
     * @param array $adjacency the adjacency matrix generated for the
     *      sentences
     * @return array the sentence ranks
     */
    public static function getSentenceRanks($adjacency)
    {
        $n = count($adjacency);
        $old_p = [];
        $p = [];
        for ($i = 0; $i < $n; $i++ ) {
            $p[$i] = 1 / $n;
        }
        for ($i = 0; $i < 10; $i++ ) {
            $p = self::multiplyMatrixVector($adjacency, $p);
        }
        return $p;
    }
    /**
     * Compute the difference of squares
     * @param array $v the  minuend vector
     * @param array $m the subtrahend vector
     * @result double the difference of the squares of vectors
     */
    public static function squareDiff($v, $w)
    {
        $result = 0;
        $n = count($v);
        for ($i = 0; $i < $n; $i++ ) {
            $subtraction = $v[$i] - $w[$i];
            $result += $subtraction * $subtraction;
        }
        return $result;
    }
    /**
     * Perform matrix multiplication on a matrix and a vector
     * @param array $mat the matrix to multiply the probabilities to
     * @param array $vec the probability vector
     * @return array the new vector after it has been multiplied
     */
    public static function multiplyMatrixVector($mat, $vec)
    {
        $result = [];
        $n = count($vec);
        for ($i = 0; $i < $n; $i++ ) {
            $result[$i] = 0;
            for ($j = 0; $j < $n; $j++ ) {
                $result[$i] += $mat[$i][$j] * $vec[$j];
            }
        }
        return $result;
    }
    /**
     * Compute the adjacency matrix based on its distortion measure
     * @param array $term_frequencies_normalized the array of term frequencies
     * @param array $sentences the sentences in the doc
     * @param string $lang locale tag for stemming
     * @param string $doc complete raw page to generate the summary from.
     * @return array the array of sentence adjacency
     */
    public static function computeAdjacency($term_frequencies_normalized,
        $sentences, $lang, $doc)
    {
        $result = [[]];
        $n = count($sentences);
        for ($i = 0; $i < $n; $i++ ) {
            $result[$i][$i] = 0;
            for ($j = $i + 1; $j < $n; $j++ ) {
                $result[$i][$j] = $result[$j][$i] =
                    self::findDistortion($sentences[$i], $sentences[$j],
                    $term_frequencies_normalized, $lang, $doc);
            }
        }
        return $result;
    }
    /**
     * Remove punctuation from an array of sentences
     * @param array $sentences the sentences in the doc
     * @return array the array of sentences with the punctuation removed
     */
     public static function removePunctuation($sentences)
     {
        $n = count($sentences);
        for ($i = 0; $i < $n; $i++ ) {
            $sentences[$i] = trim(preg_replace('/[^a-z0-9]+/iu', ' ',
                $sentences[$i]));
        }
        return $sentences;
     }
    /**
     * Remove the stop words from the array of sentences
     * @param array $sentences the sentences in the doc
     * @param string $lang locale tag for stemming
     * @return array the array of sentences with the stop words removed
     */
    public static function removeStopWords($sentences, $lang)
    {
        $n = count($sentences);
        $stop_obj = PhraseParser::getTokenizer($lang);
        if ($stop_obj && method_exists($stop_obj, "stopwordsRemover")) {
            for ($i = 0; $i < $n; $i++ ) {
                $sentences[$i] = $stop_obj->stopwordsRemover(
                    self::formatDoc($sentences[$i]));
             }
        }
        return $sentences;
    }
    /**
     * Calculate the term frequencies.
     * @param array $terms the list of all terms in the doc
     * @param array $sentences the sentences in the doc
     * @param string $doc complete raw page to generate the summary from.
     * @return array a two dimensional array where the word is the key and
     *      the frequency is the value
     */
    public static function getTermFrequencies($terms, $sentences, $doc)
    {
        $t = count($terms);
        $n = count($sentences);
        $nk = [];
        $nk = array_fill(0, $t, 0);
        $nt = [];
       for($j = 0; $j < $t; $j++) {
            for($i = 0; $i < $n; $i++) {
                $nk[$j] += preg_match_all("/\b" . $terms[$j] . "\b/iu",
                    $sentences[$i], $matches);
            }
        }
        for ($i = 0; $i <  count($nk); $i++ ) {
            $term_frequencies[$terms[$i]] = $nk[$i];
        }
        return $term_frequencies;
    }
    /**
     * Get the terms from an array of sentences
     * @param array $sentences the sentences in the doc
     * @param string $lang locale tag for stemming
     * $return array an array of terms in the array of sentences
     */
    public static function getTerms($sentences, $lang)
    {
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
        return $terms;
    }
    /**
     * Breaks any content into sentences by splitting it on spaces or carriage
     *   returns
     * @param string $content complete page.
     * @param boolean $keep_punctuation whether to keep the punctuation or not.
     * @return array array of sentences from that content.
     */
    public static function getSentences($content, $keep_punctuation)
    {
        if ($keep_punctuation) {
            $sentences =
                preg_split('/(?<!\w\.\w.)(?<![A-Z][a-z]\.)(?<=\.|\?|\!)/u',
                $content, 0, PREG_SPLIT_NO_EMPTY);
            $n = count($sentences);
            for ($i = 0; $i < $n; $i++ ) {
                $sentences[$i] = trim($sentences[$i]);
            }
            return $sentences;
        } else {
            return preg_split(
                '/(\.|\||\!|\?|！|？|。)\s+|(\n|\r)(\n|\r)+|\s{5}/u',
                $content, 0, PREG_SPLIT_NO_EMPTY);
        }
    }
    /**
     * Normalize the term frequencies based on the sum of the squares.
     * @param array $term_frequencies the array with the terms as the key
     *      and its frequency as the value
     * @return array array of term frequencies normalized
     */
    public static function normalizeTermFrequencies($term_frequencies)
    {
        $sum_of_squares = 0;
        $result_sum = 0;
        foreach ($term_frequencies as $k => $v) {
            $sum_of_squares += ($v * $v);
        }
        $square_root = sqrt($sum_of_squares);
        foreach ($term_frequencies as $k => $v) {
            $result[$k] = ($v / $square_root);
        }
        foreach ($result as $k => $v) {
            $result_sum += $v;
        }
        return $result;
    }
    /**
     * Calcluate the distortion measure.
     * 1. Check each word in sentence1 to see if it exists in sentence2.
     * If the word X of sentence1 does not exist in sentence2,
     * square the score of word X and add to the sum
     * and increase the number of not-common words by one.
     * 2. In case the word X is common between sentence1 and
     * sentence2, calculate its frequency in sentence2 and subtract
     * it from the score of word X, then square and add to
     * sum.
     * 3. Then check the sentence2 to find its not-common words
     * with sentence1, in case the word Y is not in sentence1,
     * square the score of word Y and add to sum and increase
     * the number of not-common words by one.
     * 4. At the end, calcualte the distortion between sentence1 and
     * sentence2 by dividing sum by the number of not-common
     * words.
     * @param string $first_sentence the first sentence to compare
     * @param string $second_sentence the second sentence to compare
     * @param string $term_frequencies the term frequency of the sentences
     * @param string $lang locale tag for stemming
     * @param string $doc reference doc sentences come from
     * @return float the distortion distance between the two sentences
     */
    public static function findDistortion($first_sentence, $second_sentence,
        $term_frequencies, $lang, $doc)
    {
        $result = 0;
        $first_sentence_split = preg_split('/ +/u', $first_sentence);
        $second_sentence_split = preg_split('/ +/u', $second_sentence);
        $sum = 0;
        $non_common_words = 0;
        $n = count($first_sentence_split);
        for ($i = 0; $i < $n; $i++ ) {
            $word_to_search_for = trim($first_sentence_split[$i]);
            if ($word_to_search_for != "") {
                preg_match_all("/ " . $word_to_search_for . " /",
                    $second_sentence, $matches);
                if (count($matches[0]) == 0) {
                    $sum += ($term_frequencies[$word_to_search_for] *
                        $term_frequencies[$word_to_search_for]);
                    $non_common_words++;
                } else {
                    $terms = self::getTerms(array($second_sentence), $lang);
                    $temp_term_frequencies = self::getTermFrequencies(
                        $terms, [$second_sentence], $doc);
                    $temp_term_frequencies_normalized =
                        self::normalizeTermFrequencies(
                        $temp_term_frequencies);
                    $new_term_frequency =
                        $term_frequencies[$word_to_search_for] -
                        $temp_term_frequencies_normalized[$word_to_search_for];
                    $sum += ($new_term_frequency * $new_term_frequency);
                }
            }
        }
        $n = count($second_sentence_split);
        for ($i = 0; $i < $n; $i++ ) {
            $word_to_search_for = trim($second_sentence_split[$i]);
            if ($word_to_search_for != "") {
                preg_match_all("/ " . trim($word_to_search_for) . " /",
                    $first_sentence, $matches);
                if (count($matches[0]) == 0) {
                    $sum += ($term_frequencies[$word_to_search_for] *
                        $term_frequencies[$word_to_search_for]);
                    $non_common_words++;
                }
            }
        }
        if ($non_common_words != 0) {
            $result = $sum / $non_common_words;
        }
        return $result;
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
        $substitute = ['/[\n\r\-]+/', '/[^\p{L}\s\.]+/u', '/[\.]+/u'];
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
        $page = preg_replace('/\s{2,}/u', ' ', $page);
        $new_page = preg_replace("/\<br\s*(\/)?\s*\>/u", "\n", $page);
        $changed = false;
        if ($new_page != $page) {
            $changed = true;
            $page = $new_page;
        }
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/u", "\n\n", $page);
        $page = preg_replace("/\<a/u", " <a", $page);
        $page = preg_replace("/\&\#\d{3}(\d?)\;|\&\w+\;/u", " ", $page);
        $page = preg_replace("/\</u", " <", $page);
        $page = strip_tags($page);

        if ($changed) {
            $page = preg_replace("/(\r?\n[\t| ]*){2}/u", "\n", $page);
        }
        $page = preg_replace("/(\r?\n[\t| ]*)/u", "\n", $page);
        $page = preg_replace("/\n\n\n+/u", "\n\n", $page);
        return $page;
    }
}
