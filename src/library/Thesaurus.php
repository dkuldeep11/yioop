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
 * @author Shailesh Padave shaileshpadave49@gmail.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Class used to reorder the last 10 links computed by PhraseModel based on
 * thesaurus semantic information. For English, thesaurus semantic information
 * can be provided by WordNet, a lexical English database
 * available at http://wordnet.princeton.edu/
 * To enable, you this have to define WORDNET_EXEC in your local_config file.
 * The idea behind thresaurus reordering is that given a query, it
 * is tagged for parts of speech. Each term is then looked up in thesaurus for
 * those parts of speech. Representative phrases for those term senses are
 * extracted from the ranked thesaurus output and a set of rewrites of the
 * original query are created. By looking up the number
 * of times these rewrites occur in the searched index the top two phrases
 * that represent the original query are computed.The BM25 similarity of these
 * phrases is then scored against each of the 10 output summaries of
 * PhraseModel and used to reorder the results.
 * To add thesaurus reordering for a different locale, two methods need to be
 * written in that locale tokenizer.php file
 * tagPartsOfSpeechPhrase($phrase) which on an input phrase return a string
 *     where each term_i in the phrase has been replace with term_i~pos
 *     where pos is a two character part of speech NN, VB, AJ, AV, or NA (if
 *     none of the previous apply)
 * scoredThesaurusMatches($term, $word_type, $whole_query) which takes
 *     a term from an original whole_query which has been tagged to be
 *     one of the types VB (for verb), NN (for noun), AJ (for adjective),
 *     AV (for adverb), or NA (for anything else), it outputs
 *     a sequence of  (score => array of thesaurus terms) associations.
 *     The score representing one word sense of term
 * Given that these methods have been implemented if the use_thesaurus field
 * of that language tokenizer is set to true, the thesaurus will be used.
 */
class Thesaurus
{
    /**
     * Extracts similar phrases to the input query using thesaurus results.
     * Part of speech tagging is processed on input and the output is
     * looked up in the thesaurus. USing this a ranked list of alternate
     * query phrases is created.
     * For those phrases, counts in the Yioop index are calculated
     * and the top two phrases are selected.
     * @param string $orig_query input query from user
     * @param string $index_name selected index for search engine
     * @param string $lang locale tag for the query
     * @param integer $threshold once count in posting list for any word
     *     reaches to threshold then return the number
     * @return array of top two words
     */
    public static function getSimilarPhrases($orig_query, $index_name,
        $lang, $threshold = 10)
    {
        $num_docs = [];
        $scores = [];

        $suggested_queries =
            self::getInitialSuggestions($orig_query, $lang);
        foreach ($suggested_queries as $suggestion) {
            $num_docs[$suggestion] =
                self::numDocsIndex($suggestion, $threshold, $index_name, $lang);
        }
        arsort($num_docs);
        $result = [];
        $i = 0;
        foreach ($num_docs as $k => $v) {
            $result[$i] = $k;
            $i++;
            if ($i >= 2) { break; }
        }
        return $result;
    }
    /**
     * Gets array of BM25 scores for given input array of summaries
     * and thesaurus generated queries
     * @param array $similar_phrases an array of thesaurus generated queries
     * @param array $summaries an array of summaries which is generated
     *     during crawl time.
     * @return array of BM25 score for each document based on the thesaurus
     * simimar phrases
     */
    public static function scorePhrasesSummaries($similar_phrases, $summaries)
    {
        $score = [];
        //if there are no similar words then
        if (empty($similar_phrases)) {
            return [];
        } else {
            $num_phrases = count($similar_phrases);
            for ($i = 0; $i < $num_phrases; $i++) {
                $phrase = $similar_phrases[$i];
                $terms = explode(' ', $phrase);
                $summaries = self::changeCaseOfStringArray($summaries);
                $idf = self::calculateIDF($summaries, $terms);
                $tf = self::calculateTFBM25($summaries, $terms);
                $num_summaries = count($summaries);
                $num_terms = count($terms);
                $bm25_result[$i] =
                    self::calculateBM25($idf, $tf, $num_terms, $num_summaries);
            }
            if (count($bm25_result) == 1) {
                for ($i = 0; $i < $num_summaries; $i++) {
                    $temp = 0;
                    $temp = $bm25_result[0][$i];
                    $score[$i] = $temp;
                }
            } else {
                for ($i = 0; $i < $num_summaries; $i++) {
                    $temp = 0;
                    $temp = $bm25_result[0][$i] * (2/3) +
                        $bm25_result[1][$i] * (1/3);
                    $score[$i] = $temp;
                }
            }
            return $score;
        }
    }
    /**
     * Computes suggested related phrases from thesaurus based on part of
     * speech  done on each query term.
     *
     * @param string $query query entered by user
     * @param string $lang locale tag for the query
     * @return string array $suggestion consisting of phrases suggested to
     *     be similar in meaning to some sens of the query
     */
    public static function getInitialSuggestions($query, $lang)
    {
        $tokenizer = PhraseParser::getTokenizer($lang);
        $pos_query = $tokenizer->tagPartsOfSpeechPhrase($query);
        $max_len = 25;
        $replacement_phrases = [];
        $suggestions = [];
        $terms = preg_split("/\s+|\-/", trim($query));
        $pos_terms = preg_split("/\s+/",
            trim($pos_query), -1, PREG_SPLIT_NO_EMPTY);
        $num_pos_terms = count($pos_terms);
        $word_type = null;
        $similar_words = [];
        $known_word_types = ["NN", "VB", "AJ", "AV"];
        for ($i = 0; $i < $num_pos_terms; $i++) {
            $pos = strpos($pos_terms[$i], '~');
            $word_type = trim(substr($pos_terms[$i], $pos + 1));
            if (!in_array($word_type, $known_word_types)) {
                $word_type = "NA";
            }
            $current_word = substr($pos_terms[$i], 0, $pos);
            if ($word_type != "NA") {
                $similar_phrases = $tokenizer->scoredThesaurusMatches(
                    $current_word, $word_type, $query);
                $highest_scoring_sense_phrases = ($similar_phrases) ?
                    array_shift($similar_phrases): false;
                if ($highest_scoring_sense_phrases) {
                    $replacement_phrases[$current_word] =
                        $highest_scoring_sense_phrases;
                }
            }
        }
        $i = 0;
        foreach ($replacement_phrases as $words => $similar_phrases) {
            foreach ($similar_phrases as $phrase) {
                if (mb_strpos(trim($phrase), ' ') !== false) {
                    $phrase = preg_replace('/~[\w]+/', '', $phrase);
                }
                $modified_query = preg_replace(
                    '/' . $words . '/', trim($phrase), $query);
                if (mb_strlen($modified_query) < $max_len &&
                    mb_strpos($modified_query, $query) === false) {
                    $suggestions[$i] = $modified_query;
                    $i++;
                }
            }
        }
        return $suggestions;
    }
    /**
     * Returns the number of documents in an index that a phrase occurs in.
     * If it occurs in more than threshold documents then cut off search.
     *
     * @param string $phrase to look up in index
     * @param int $threshold once count in posting list for any word
     *     reaches to threshold then return the number
     * @param string $index_name selected index for search engine
     * @param string $lang locale tag for the query
     * @return int number of documents phrase occurs in
     */
    public static function numDocsIndex($phrase, $threshold, $index_name, $lang)
    {
        PhraseParser::canonicalizePunctuatedTerms($phrase, $lang);
        $terms = PhraseParser::stemCharGramSegment($phrase, $lang);
        $num  = count($terms);
        if ($index_name == null) {
            return 0;
        }
        if (count($terms) > C\MAX_QUERY_TERMS) {
            $terms  = array_slice($terms, 0, C\MAX_QUERY_TERMS);
        }
        $whole_phrase = implode(" ", $terms);
        return IndexManager::numDocsTerm($whole_phrase, $index_name,
            $threshold);
    }
    /**
     * Lower cases an array of strings
     *
     * @param array $summaries strings to put into lower case
     * @return array with strings converted to lower case
     */
    public static function changeCaseOfStringArray($summaries)
    {
        return explode("-!-", mb_strtolower(implode("-!-", $summaries)));
    }
    /**
     * Computes the BM25 of an array of documents given that the idf and
     * tf scores for these documents have already been computed
     *
     * @param array $idf inverse doc frequency for given query array
     * @param array $tf term frequency for given query array
     * @param $num_terms number of terms that make up input query
     * @param $num_summaries count for input summaries
     * @returns array consisting of BM25 scores for each document
     */
    public static function calculateBM25($idf, $tf, $num_terms, $num_summaries)
    {
        $scores = [];
        for ($i = 0; $i < $num_terms; $i++) {
            for ($j = 0; $j < $num_summaries; $j++) {
                $bm25_score[$i][$j] = $idf[$i] * $tf[$i][$j];
            }
        }
        for ($i = 0; $i < $num_summaries; $i++) {
            $val = 0;
            for ($j = 0; $j < $num_terms; $j++) {
                $val += $bm25_score[$j][$i];
            }
            $scores[$i] = $val;
        }
        return $scores;
    }
    /**
     * Calculates the BM25 normalized term frequency of a set of terms in
     * a collection of text summaries
     *
     * @param array $summaries list of summary strings to compute BM25TF w.r.t
     * @param array $terms we want the term frequency computation for
     * @return array $tfbm25 a 2d array with rows being indexed by terms and
     *     columns indexed by summaries and the values of an entry being
     *     the tfbm25 score for that term in that document
     */
    public static function calculateTFBM25($summaries, $terms)
    {
        $k1 = 1.5;
        $b = 0.75;
        $tf_values = [];
        $tfbm25 = [];
        $doc_length = strlen(implode("", $summaries));
        $num_summaries = count($summaries);
        if ($num_summaries!= 0) {
            $avg_length = $doc_length / $num_summaries;
        } else {
            $avg_length = 0;
        }
        $avg_length = max($avg_length, 1);
        $tf_values = self::calculateTermFreq($summaries, $terms);
        $num_terms =count($terms);
        for ($i = 0; $i < $num_terms; $i++) {
            for ($j = 0; $j < $num_summaries; $j++) {
                $frequency = $tf_values[$i][$j];
                $tfbm25[$i][$j] =
                    ($frequency * ($k1 + 1))/($frequency + $k1 *
                    ((1 - $b) + $b * ($doc_length/$avg_length)));
            }
        }
        return $tfbm25;
    }
    /**
     * Computes a 2D array of the number of occurences of term i in document j
     *
     * @param array $summaries documents to compute frequencies in
     * @param array $terms terms to compute frequencies for
     * @return array 2D array as described above
     */
    public static function calculateTermFreq($summaries, $terms)
    {
        $tf_values = [];
        $num_terms = count($terms);
        $num_summaries = count($summaries);
        for ($i = 0; $i < $num_terms; $i++) {
            for ($j = 0; $j < $num_summaries; $j++) {
                if ($terms[$i] != "") {
                    $frequency = substr_count($summaries[$j], $terms[$i]);
                    $tf_values[$i][$j] = $frequency;
                } else {
                    $tf_values[$i][$j] = 0;
                }
            }
        }
        return $tf_values;
    }
    /**
     * To get the inverse document frequencies for a collection of terms in
     * a set of documents.
     * IDF(term_i) = log_10(# of document / # docs term i in)
     *
     * @param array $summaries documents to use in calculating IDF score
     * @param array $terms terms to compute IDF score for
     * @return array $idf 1D-array saying the inverse document frequency for
     * each term
     */
    public static function calculateIDF($summaries, $terms)
    {
        $N = count($summaries);
        $Nt = [];
        $term_count = 0;
        $num_terms = count($terms);
        for ($i = 0; $i < $num_terms; $i++) {
            $cnt_Nt = 0;
            $term_count++;
            foreach ($summaries as $summary)
            {
                if (stripos($summary, $terms[$i]) !== false) {
                    $cnt_Nt++;
                }
            }
            $Nt[$i] = $cnt_Nt;
            $idf[$i] = ($Nt[$i] != 0) ? log10($N / $Nt[$i]) : 0;
        }
        return $idf;
    }
}
