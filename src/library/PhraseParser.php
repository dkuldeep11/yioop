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
use seekquarry\yioop\models\LocaleModel;
use seekquarry\yioop\library\processors\PageProcessor;

/**
 * For crawlHash
 */
require_once __DIR__."/Utility.php";
/**
 * So know which part of speech tagger to use
 */
require_once __DIR__."/LocaleFunctions.php";
/**
 * Library of functions used to manipulate words and phrases
 *
 * @author Chris Pollett
 */
class PhraseParser
{
    /**
     * A list of meta words that might be extracted from a query
     * @var array
     */
    public static $meta_words_list = ['\-', 'class:', 'class-score:', 'code:',
        'date:', 'dns:', 'elink:', 'filetype:', 'guid:', 'host:', 'i:',
        'info:', 'index:', 'ip:', 'link:', 'modified:',
        'lang:', 'media:', 'location:', 'numlinks:', 'os:',
        'path:', 'robot:', 'safe:', 'server:', 'site:', 'size:',
        'time:', 'u:', 'version:','weight:', 'w:'
        ];
    /**
     * Those meta words whose values will be encoded as part of word_ids
     * @var array
     */
    public static $materialized_metas = ["class:", "media:", "safe:"];
    /**
     * A list of meta words that might be extracted from a query
     * @var array
     */
    public static $programming_language_map = ['java' => 'java',
            'py' => 'python'];
    /**
     *  Tokenizer objects that have been loaded so far
     *  @var array
     */
    public static $tokenizers = [];
    /**
     * Constant storing the string
     */
    const TOKENIZER = 'Tokenizer';
    /**
     * Indicates the control word for programming languages
     */
    const CONTROL_WORD_INDICATOR = ':';
    /**
     * Indicates the control word for programming languages
     */
    const REGEX_INITIAL_POSITION = 1;
    /**
     * Converts a summary of a web page into a string of space separated words
     *
     * @param array $page associative array of page summary data. Contains
     *     title, description, and links fields
     * @return string the concatenated words extracted from the page summary
     */
    public static function extractWordStringPageSummary($page)
    {
        if (isset($page[CrawlConstants::TITLE])) {
            $title_phrase_string = mb_ereg_replace(C\PUNCT, " ",
                $page[CrawlConstants::TITLE]);
        } else {
            $title_phrase_string = "";
        }
        if (isset($page[CrawlConstants::DESCRIPTION])) {
            $description_phrase_string = mb_ereg_replace(C\PUNCT, " ",
                $page[CrawlConstants::DESCRIPTION]);
        } else {
            $description_phrase_string = "";
        }
        $page_string = $title_phrase_string . " " . $description_phrase_string;
        $page_string = preg_replace("/(\s)+/", " ", $page_string);

        return $page_string;
    }
    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Array key indicates position
     * of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @param string $index_name name of index to be used as a reference
     *     when extracting phrases
     * @param bool $exact_match whether the match has to be exact or not
     * @param int $threshold roughly causes a stop to extracting more phrases
     *  if exceed $threshold (still might get more than $threshold back, only
     *  when detect have more stop)
     * @return array of phrases
     */
    public static function extractPhrases($string, $lang = null,
        $index_name = null, $exact_match = false, $threshold = 10)
    {
        $char_class = C\NS_LOCALE . $lang . "\\resources\\Tokenizer";
        if (isset(self::$programming_language_map[$lang])) {
            $control_word = self::$programming_language_map[$lang] .
                self::CONTROL_WORD_INDICATOR;
            $string = trim(substr($string, strlen($control_word) + 1));
        } else {
            self::canonicalizePunctuatedTerms($string, $lang);
        }
        $terms = self::stemCharGramSegment($string, $lang);
        $num = count($terms);
        if ($index_name == null || $num <= 1 || (class_exists($char_class) &&
            isset($char_class::$char_gram_len) )) {
            return $terms;
        }
        if (count($terms) > C\MAX_QUERY_TERMS) {
            $first_terms = array_slice($terms, 0, C\MAX_QUERY_TERMS);
            $whole_phrase = implode(" ", $first_terms);
        } else {
            $whole_phrase = implode(" ", $terms);
            $first_terms = $terms;
        }
        if ($exact_match) {
            return $terms; /* for exact phrase search do not use suffix tree
                              stuff for now
                            */
        }
        $count_whole_phrase = IndexManager::numDocsTerm($whole_phrase,
            $index_name, $threshold);
        if ($count_whole_phrase >= $threshold
            || $num > C\SUFFIX_TREE_THRESHOLD) {
            $terms = [$whole_phrase, $terms[0]];
            return $terms;
        } else if ($count_whole_phrase > 0) {
            foreach ($terms as $term) {
                $count_term = IndexManager::numDocsTerm($term,
                    $index_name, 5 * $threshold);
                if ($count_term > 50 * $count_whole_phrase) {
                    $terms = [$whole_phrase, $terms[0]];
                    return $terms;
                }
            }
        } else if ($num > 2) {
            $start_terms = $first_terms;
            $last_term = array_pop($start_terms);
            $start_phrase = implode(" ", $start_terms);
            $count_start = IndexManager::numDocsTerm($start_phrase,
                $index_name, $threshold);
            if ($count_start >= $threshold) {
                $terms = [$start_phrase, $last_term, $terms[0]];
                return $terms;
            }
            $end_terms = $first_terms;
            $first_term = array_shift($end_terms);
            $end_phrase = implode(" ", $end_terms);
            $count_end = IndexManager::numDocsTerm($end_phrase,
                $index_name, $threshold);
            if ($count_end >= $threshold) {
                $terms = [$first_term, $end_phrase];
                return $terms;
            }
        }
        if ($index_name != 'feed' &&
            IndexManager::getVersion($index_name) == 0) {
            return $terms; //old style index before max phrase extraction
        }
        return $terms;
    }
    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * not extract terms within those phrase. Returns an associative array
     * of phrase => number of occurrences of phrase
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @return array pairs of the form (phrase, number of occurrences)
     */
    public static function extractPhrasesAndCount($string, $lang = null)
    {
        $phrases = self::extractPhrasesInLists($string, $lang);
        $phrase_counts = [];
        foreach ($phrases as $term => $positions) {
            $phrase_counts[$term] = count($positions);
        }

        return $phrase_counts;
    }
    /**
     * Extracts all phrases (sequences of adjacent words) from $string. Does
     * extract terms within those phrase.
     *
     * @param string $string subject to extract phrases from
     * @param string $lang locale tag for stemming
     * @return array word => list of positions at which the word occurred in
     *     the document
     */
    public static function extractPhrasesInLists($string, $lang = null)
    {
        if (!isset(self::$programming_language_map[$lang])) {
            self::canonicalizePunctuatedTerms($string, $lang);
        }
        return self::extractMaximalTermsAndFilterPhrases($string, $lang);
    }
    /**
     * This functions tries to convert acronyms, e-mail, urls, etc into
     * a format that does not involved punctuation that will be stripped
     * as we extract phrases.
     *
     * @param string& $string a string of words, etc which might involve such
     *      terms
     * @param $lang a language tag to use as part of the canonicalization
     *     process not used right now
     */
    public static function canonicalizePunctuatedTerms(&$string, $lang = null)
    {
        $acronym_pattern = "/\b[A-Za-z](\.\s*[A-Za-z])+(\.|\b)/";
        $string = preg_replace_callback($acronym_pattern,
            function($matches) {
                $result = "_".mb_strtolower(
                    mb_ereg_replace("\.\s*", "", $matches[0]));
                return $result;
            }, $string);
        $ampersand_pattern = "/[A-Za-z]+(\s*(\s(\'n|\'N)\s|\&)\s*[A-Za-z])+/";
        $string = preg_replace_callback($ampersand_pattern,
            function($matches) {
                $result = mb_strtolower(
                    mb_ereg_replace("\s*(\'n|\'N|\&)\s*", "_and_",$matches[0]));
                return $result;
            },
            $string);
        $url_or_email_pattern =
            '@((gopher|http|https)://([^ \t\r\n\v\f\'\"\;\,<>])*)|'.
            '([A-Z0-9._%-]+\@[A-Z0-9.-]+\.[A-Z]{2,4})@i';
        $string = preg_replace_callback($url_or_email_pattern,
            function($matches) {
                $result =  mb_ereg_replace("\.", "_d_",$matches[0]);
                $result =  mb_ereg_replace("\:", "_c_",$result);
                $result =  mb_ereg_replace("\/", "_s_",$result);
                $result =  mb_ereg_replace("\@", "_a_",$result);
                $result =  mb_ereg_replace("\[", "_bo_",$result);
                $result =  mb_ereg_replace("\]", "_bc_",$result);
                $result =  mb_ereg_replace("\(", "_po_",$result);
                $result =  mb_ereg_replace("\)", "_pc_",$result);
                $result =  mb_ereg_replace("\?", "_q_",$result);
                $result =  mb_ereg_replace("\=", "_e_",$result);
                $result =  mb_ereg_replace("\&", "_a_",$result);
                $result = mb_strtolower($result);
                return $result;
            },
            $string);
    }
    /**
     * Splits string according to punctuation and white space then
     * extracts (stems/char grams) of terms and n word grams from the string
     * Uses a notiona of maximal n word gram to dot eh extraction
     *
     * @param string $string to extract terms from
     * @param string $lang IANA tag to look up stemmer under
     * @return array of terms and n word grams in the order they appeared in
     *     string
     */
    public static function extractMaximalTermsAndFilterPhrases($string,
        $lang = null)
    {
        $pos_lists = [];
        $maximal_phrases = [];
        $terms = self::stemCharGramSegment($string, $lang);
        if ($terms == []) { 
            return [];
        }
        $suffix_tree = new SuffixTree($terms);
        $suffix_tree->outputMaximal(1, "", 0, $maximal_phrases);
        $t = 0;
        $seen = [];
        // add all single terms
        foreach ($terms as $term) {
            if (!isset($seen[$term])) {
                $seen[$term] = [];
                $maximal_phrases[$term] = [];
            }
            $maximal_phrases[$term][] = $t;
            $t++;
        }
        return $maximal_phrases;
    }
    /**
     * Given a string splits it into terms by running any applicable
     * segmenters, chargrammers, or stemmers of the given locale
     *
     * @param string $string what to extract terms from
     * @param string $lang locale tag to determine which stemmers, chargramming
     *     and segmentation needs to be done.
     *
     * @return array the terms computed from the string
     */
    public static function stemCharGramSegment($string, $lang)
    {
        if (isset(self::$programming_language_map[$lang])) {
            mb_internal_encoding("UTF-8");
            $tokenizer_name = self::$programming_language_map[$lang] .
                self::TOKENIZER;
            $terms = self::$tokenizer_name($string, $lang);
        } else {
            mb_internal_encoding("UTF-8");
            $string = mb_strtolower($string);
            $string = mb_ereg_replace("\s+|".C\PUNCT, " ", $string);
            $terms = self::segmentSegment($string, $lang);
            $terms = self::charGramTerms($terms, $lang);
            $terms = self::stemTerms($terms, $lang);
        }
        return $terms;
    }
    /**
     * Given a string tokenizes into Java tokens
     *
     * @param string $string what to extract terms from
     * @param string $lang indicates programming language
     *
     * @return array the terms computed from the string
     */
    public static function javaTokenizer($string, $lang)
    {
        //Comments
        $single_line_comments = "(\/\/).*?(\n)";
        $multiline_comments = "\\/\\*[^(\\/\\*)]*?\\*\\/";
        $javadoc_comments = "\/\*([^*]|[\r\n]|(\*+([^*\/]|[\r\n])))*\*+\/";
        $multiple_line_comments = "$javadoc_comments|$multiline_comments";
        $comments = "($multiple_line_comments|$single_line_comments)";
        //Identifiers
        $alphabetic = "[A-Za-z]";
        $id_start = "($alphabetic)|\\".'$'."|\_";
        $numeric = "[0-9]";
        $repeat = "$id_start|$numeric";
        $identifiers = "($id_start)($repeat)*";
        //Keywords
        $keywords_part1 = "abstract|assert|boolean|break|byte|case|catch|char";
        $keywords_part2 = "class|const|continue|default|do|double|else|extends";
        $keywords_part3 = "final|finally|float|for|goto|if|implements|import";
        $keywords_part4 = "instanceof|int|interface|long|native|new|package";
        $keywords_part5 = "private|protected|public|return|short|static";
        $keywords_part6 = "strictfp|super|synchronized|switch|this|throw";
        $keywords_part7 = "throws|transient|try|void|volatile|while";
        $keywords_string1 = "$keywords_part1|$keywords_part2|$keywords_part3";
        $keywords_string2 = "$keywords_part4|$keywords_part5|$keywords_part6";
        $keywords_string3 = "$keywords_part7";
        $keywords = "($keywords_string1|$keywords_string2|$keywords_string3)";
        //Seperators
        $seperators = "(;|,|\.|\(|\)|\{|\}|\[|\])";
        //Operators
        $operators_part1 = "\+|\-|\*|\/|&|\||\^|%|<<|>>|=|>|<|!|~|\?|:";
        $operators_part2 = "\-\-|\+\+|>>>|==|<=|>=|!=|&&|\|\|";
        $operators_part3 = "\+=|\-=|\*=|\/=|&=|\|=|\^=|%=|<<=|>>=|>>>=";
        $operators = "($operators_part3|$operators_part2|$operators_part1)";
        //Null Literal
        $null_literal = "null";
        //Boolean Literal
        $boolean_literal = "true|false";
        //Floating point Literal
        $non_zero_digit = "1|2|3|4|5|6|7|8|9";
        $digit = "0|$non_zero_digit";
        $digits = "($digit)($digit)*";
        $exponent_part = "(e|E)([\+|\-])($digits)";
        $float_part1 = "($digits)($exponent_part)";
        $float_part2 = "($digits)(\.)($digits)?($exponent_part)?";
        $float_part3 = "(\.$digits)($exponent_part)?";
        $floating_point_numeral = "$float_part1|$float_part2|$float_part3";
        //Integer Literal
        $decimal_numeral = "0|($non_zero_digit)($digits){0,1}";
        $hex_numeral = "0[x|X][0-9A-Fa-f]+";
        $octal_numeral = "0[0-7]+";
        $integer_numeral = "($hex_numeral|$octal_numeral|$decimal_numeral)";
        //Character Literal
        $special_part1 = "\!|%|\^|&|\*|\(|\)|\-|\+|\=|\{|\}|\||~|\[|\]|\\|;";
        $special_part2 = "'|\:|\<|\>|\?|,|\.|\/|#|@|`|_";
        $special = "$special_part1|$special_part2";
        $alphanumeric = "[A-Za-z0-9]";
        $graphic = "$alphanumeric|$special";
        $escape = "\\n|\\t|\\v|\\a|\\b|\\r|\\f|\\\\|\\'|\\\"";
        $char_literal = "(\'($graphic)\'|\'\s\'|\'($escape)\')";
        //String Literal
        $string_literal = "(\"($graphic|\s|$escape)*?[^\\\]\")";
        //Literals
        $literals_part1 = "$string_literal|$floating_point_numeral";
        $literals_part2 = "$integer_numeral|$char_literal|$boolean_literal";
        $literals_part3 = "$null_literal";
        $literals = "($literals_part1|$literals_part2|$literals_part3)";
        //Java Tokens
        $tokens_part1 = "$comments|$literals|$operators";
        $tokens_part2 = "$seperators|$keywords|$identifiers";
        $tokens = "($tokens_part1|$tokens_part2)";
        $length = strlen($string);
        $current_length = $length;
        $position = self::REGEX_INITIAL_POSITION;
        $results = [];
        while($position == 1 && $current_length > 0) {
            $temp_results = [];
            $position = preg_match("/$tokens/", $string, $matches,
                PREG_OFFSET_CAPTURE);
            if (isset($matches[0][0])) {
                $text = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n",
                    $matches[0][0]);
                $lines = explode("\n", trim($text));
                $line = implode(' ', $lines);
                $data = preg_replace("/[\t\s]+/", ' ', trim($line));
                $temp_results = explode(" ", trim($data));
                foreach ($temp_results as $result) {
                    if (!empty($result)) {
                        $results[] = self::$programming_language_map[$lang] .
                            self::CONTROL_WORD_INDICATOR . trim($result);
                    }
                }
                $current_length = (strlen($matches[0][0]));
                $string = trim(substr($string, $current_length, $length));
            }
        }
        return $results;
    }
    /**
     * Given a string tokenizes into Python tokens
     *
     * @param string $string what to extract terms from
     * @param string $lang indicates programming language
     *
     * @return array the terms computed from the string
     */
    public static function pythonTokenizer($string, $lang)
    {
        //Comments
        $ordinary_part1 = "_|\(|\)|\[|\]|\{|\}|\+|\-|\*|\/|%";
        $ordinary_part2 = "\!|&|\||\^|~|\<|\=|\>|,|\.|\:|;|$|\?|#|\@";
        $ordinary = "$ordinary_part1|$ordinary_part2";
        $lower = "a|b|c|d|e|f|g|h|i|j|k|l|m|n|o|p|q|r|s|t|u|v|w|x|y|z";
        $upper = "A|B|C|D|E|F|G|H|I|J|K|L|M|N|O|P|Q|R|S|T|U|V|W|X|Y|Z";
        $digit = "0|1|2|3|4|5|6|7|8|9";
        $graphic = "$lower|$upper|$digit|$ordinary";
        $text_chars = "$graphic|\s|\"|\'";
        $comments = "#($text_chars|\\\)*?(\n)";
        //Identifiers
        $id_start = "$lower|$upper|\_";
        $repeat = "$id_start|$digit";
        $identifiers = "($id_start)($repeat)*";
        //Keywords
        $keywords_part1 = "False|None|True|and|as|assert|break|class|continue";
        $keywords_part2 = "finally|for|from|global|elif|import|in|def|del|if";
        $keywords_part3 = "is|lambda|nonlocal|not|or|pass|raise|else|except";
        $keywords_part4 = "return|try|while|with|yield";
        $keywords_string1 = "$keywords_part1|$keywords_part2";
        $keywords_string2 = "$keywords_part3|$keywords_part4";
        $keywords = "($keywords_string1|$keywords_string2)";
        //Operators
        $operators_part1 = "is|in|or|not|and|\+|\-|\*|\/|%|<|>|&";
        $operators_part2 = "\*\*|==|!=|<=|>=|\/\/";
        $operators_part3 = "<<|>>|\^|~|\|";
        $operators = "($operators_part3|$operators_part2|$operators_part1)";
        //Delimiters
        $delimiters_part1 = "\.|,|:|;|@|=|\(|\)|\{|\}|\[|\]";
        $delimiters_part2 = "\+=|\-=|\*=|\/=|\/\/=|%=|\*\*=";
        $delimiters_part3 = "&=|\|=|\^=|<<=|>>=";
        $delimiters = "$delimiters_part3|$delimiters_part2|$delimiters_part1";
        //Floating point Literal
        $digits = "($digit)($digit)*";
        $mantissa = "($digits)\.($digit)*|\.($digits)";
        $exponent = "e[\+|\-]$digits|E[\+|\-]$digits";
        $float_literal = "($mantissa)($exponent)*|($digits)($exponent)";
        //Integer Literal
        $non_zero_digit = "1|2|3|4|5|6|7|8|9";
        $binary_digit = "0|1";
        $octal_digit = "0|1|2|3|4|5|6|7";
        $hex_digit = "$digit|a|b|c|d|e|f|A|B|C|D|E|F";
        $decimal_literal = "0+|($non_zero_digit)($digit)*";
        $binary_literal_part1 = "0b($binary_digit)($binary_digit)*";
        $binary_literal_part2 = "0B($binary_digit)($binary_digit)*";
        $binary_literal = "$binary_literal_part1|$binary_literal_part2";
        $octal_literal_part1 = "0O($octal_digit)($octal_digit)*";
        $octal_literal_part2 = "0o($octal_digit)($octal_digit)*";
        $octal_literal = "$octal_literal_part1|$octal_literal_part2";
        $hex_literal_part1 ="0X($hex_digit)($hex_digit)*";
        $hex_literal_part2 ="0x($hex_digit)($hex_digit)*";
        $hex_literal = "$hex_literal_part1|$hex_literal_part2";
        $integer_literal_part1 = "($binary_literal)|($octal_literal)";
        $integer_literal_part2 = "($hex_literal)|($decimal_literal)";
        $integer_literal = "$integer_literal_part1|$integer_literal_part2";
        //Boolean Literal
        $boolean_literal = "True|False";
        //None Type Literal
        $none_literal = "None";
        //String Literal
        $esc_a = "\\\o[$octal_digit]{3}|\\\h[$hex_digit]{2}|\\\[$text_chars]";
        $unicode = "[^\\x00-\\x80]+";
        $esc_u = "$esc_a|\\\n$unicode|\\\u[$hex_digit]{4}|\\\U[$hex_digit]{8}";
        $raw_opt = "r|R";
        $bytes_opt = "b|B";
        $single_quoted_element1 = "($graphic|$esc_u|\\s|\\t|\')*";
        $single_quoted_element2 = "($graphic|$esc_u|\\s|\\t|\")*";
        $single_quoted_string1 = "(\"$single_quoted_element1\")";
        $single_quoted_string2 = "(\'$single_quoted_element2\')";
        $single_quoted_string = "$single_quoted_string1|$single_quoted_string2";
        $triple_quoted_element = "$text_chars|$esc_u";
        $triple_quoted_string1 = "(\"\"\"($triple_quoted_element)*?\"\"\")";
        $triple_quoted_string2 = "(\'\'\'($triple_quoted_element)*?\'\'\')";
        $triple_quoted_string = "$triple_quoted_string1|$triple_quoted_string2";
        $string_literal_part1 = "($raw_opt)?($triple_quoted_string)";
        $string_literal_part2 = "($raw_opt)?($single_quoted_string)";
        $string_literal = "$string_literal_part1|$string_literal_part2";
        //Byte Literal
        $single_quoted_element3 = "($graphic|$esc_a|\\s|\\t|\')*";
        $single_quoted_element4 = "($graphic|$esc_a|\\s|\\t|\")*";
        $single_quoted_byte1 = "(\"$single_quoted_element3\")";
        $single_quoted_byte2 = "(\'$single_quoted_element4\')";
        $single_quoted_byte = "$single_quoted_byte1|$single_quoted_byte2";
        $triple_quoted_byte1 = "(\"\"\"($triple_quoted_element)*?\"\"\")";
        $triple_quoted_byte2 = "(\'\'\'($triple_quoted_element)*?\'\'\')";
        $triple_quoted_byte = "$triple_quoted_byte1|$triple_quoted_byte2";
        $bytes_literal_part1 = "($bytes_opt)($raw_opt)?($triple_quoted_byte)";
        $bytes_literal_part2 = "($bytes_opt)($raw_opt)?($single_quoted_byte)";
        $bytes_literal = "$bytes_literal_part1|$bytes_literal_part2";
        //Literals
        $literals_part1 = "$string_literal|$bytes_literal|$float_literal";
        $literals_part2 = "$integer_literal|$boolean_literal|$none_literal";
        $literals = "($literals_part1|$literals_part2)";
        //Python Tokens
        $tokens_part1 = "$comments|$literals|$delimiters";
        $tokens_part2 = "$operators|$keywords|$identifiers";
        $tokens = "($tokens_part1|$tokens_part2)";
        $length = strlen($string);
        $current_length = $length;
        $position = self::REGEX_INITIAL_POSITION;
        $results = [];
        while($position == 1 && $current_length > 0) {
            $temp_results = [];
            $position = preg_match("/$tokens/", $string, $matches,
                PREG_OFFSET_CAPTURE);
            if (isset($matches[0][0])) {
                $text = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n",
                    $matches[0][0]);
                $lines = explode("\n", trim($text));
                $line = implode(' ', $lines);
                $data = preg_replace("/[\t\s]+/", ' ', trim($line));
                $temp_results = explode(" ", trim($data));
                foreach ($temp_results as $result) {
                    if (!empty($result)) {
                        $results[] = self::$programming_language_map[$lang] .
                            self::CONTROL_WORD_INDICATOR . trim($result);
                    }
                }
                $current_length = (strlen($matches[0][0]));
                $string = trim(substr($string, $current_length, $length));
            }
        }
        return $results;
    }
    /**
     * Given an array of pre_terms returns the characters n-grams for the
     * given terms where n is the length Yioop uses for the language in
     * question. If a stemmer is used for language then n-gramming is not
     * done and this just returns an empty array this method differs from
     * getCharGramsTerm in that it may do checking of certain words and
     * not char gram them. For example, it won't char gram urls.
     *
     * @param array $pre_terms the terms to make n-grams for
     * @param string $lang locale tag to determine n to be used for n-gramming
     *
     * @return array the n-grams for the terms in question
     */
    public static function charGramTerms($pre_terms, $lang)
    {
        $char_class = C\NS_LOCALE . $lang . "\\resources\\Tokenizer";
        mb_internal_encoding("UTF-8");
        if ($pre_terms == []) { return [];}
        $terms = [];
        $tokenizer = PhraseParser::getTokenizer($lang);
        if (class_exists($char_class) && isset($char_class::$char_gram_len)) {
            foreach ($pre_terms as $pre_term) {
                if ($pre_term == "") { continue; }
                if (substr($pre_term, 0, 4) == 'http') {
                    $terms[]  = $pre_term; // don't chargram urls
                    continue;
                }
                $ngrams = self::getCharGramsTerm(array($pre_term), $lang);
                if (count($ngrams) > 0) {
                    $terms = array_merge($terms, $ngrams);
                }
            }
        } else {
            $terms = & $pre_terms;
        }
        return $terms;
    }
    /**
     * Returns the characters n-grams for the given terms where n is the length
     * Yioop uses for the language in question. If a stemmer is used for
     * language then n-gramming is not done and this just returns an empty array
     *
     * @param array $terms the terms to make n-grams for
     * @param string $lang locale tag to determine n to be used for n-gramming
     *
     * @return array the n-grams for the terms in question
     */
    public static function getCharGramsTerm($terms, $lang)
    {
        $char_class = C\NS_LOCALE . $lang . "\\resources\\Tokenizer";
        mb_internal_encoding("UTF-8");
        if (class_exists($char_class) && isset($char_class::$char_gram_len)) {
            $n = $char_class::$char_gram_len;
        } else {
            return [];
        }
        $ngrams = [];
        foreach ($terms as $term) {
            $pre_gram = $term;
            $last_pos = mb_strlen($pre_gram) - $n;
            if ($last_pos < 0) {
                $ngrams[] = $pre_gram;
            } else {
                for ($i = 0; $i <= $last_pos; $i++) {
                    $tmp = mb_substr($pre_gram, $i, $n);
                    if ($tmp != "") {
                        $ngrams[] = $tmp;
                    }
                }
            }
        }
        return $ngrams;
    }
    /**
     * Given a string to segment into words (where strings might
     * not contain spaces), this function segments them according to the given
     * locales segmenter
     *
     * @param string $segment string to split into terms
     * @param string $lang IANA tag to look up segmenter under
     *     from some other language
     * @param array of terms found in the segments
     */
    public static function segmentSegment($segment, $lang)
    {
        if ($segment == "") { return [];}
        $term_string = "";
        if ($lang != null) {
            $segment_obj = self::getTokenizer($lang);
        } else {
            $segment_obj = null;
        }
        if ($segment_obj != null && method_exists($segment_obj, "segment")) {
            $term_string .= $segment_obj->segment($segment);
        } else {
            $term_string = $segment;
        }
        $terms = mb_split("\s+", trim($term_string));
        return $terms;
    }
    /**
     * Splits supplied string based on white space, then stems each
     * terms according to the stemmer for $lang if exists
     *
     * @param mixed $string_or_array to extract stemmed terms from
     * @param string $lang IANA tag to look up stemmer under
     * @return array stemmed terms if stemmer; terms otherwise
     */
    public static function stemTerms($string_or_array, $lang)
    {
        return self::stemTermsK($string_or_array, $lang, false);
    }
    /**
    * Splits supplied string based on white space, then stems each
     * terms according to the stemmer for $lang if exists
     *
     * @param mixed $string_or_array to extract stemmed terms from
     * @param string $lang IANA tag to look up stemmer under
     * @param string $keep_empties whether to keep empty sentences or not
     * @return array stemmed terms if stemmer; terms otherwise
     */
    public static function stemTermsK($string_or_array, $lang, $keep_empties)
    {
        if ($string_or_array == [] ||
            $string_or_array == "") { return [];}
        if (is_array($string_or_array)) {
            $terms = & $string_or_array;
        } else {
            $terms = mb_split("[[:space:]]", $string_or_array);
        }
        $stem_obj = self::getTokenizer($lang);
        $stems = [];
        if ($stem_obj != null && method_exists($stem_obj, "stem")) {
            foreach ($terms as $term) {
                if (trim($term) == "") {
                    if (!$keep_empties) {
                        continue;
                    }
                }
                $stems[] = $stem_obj->stem($term);
            }
        } else {
            return $terms;
        }
        return $stems;
    }
    /**
     * Loads and instantiates a tokenizer object for a language if exists
     *
     * @param string $lang IANA tag to look up stemmer under
     * @return object tokenizer object
     */
    public static function getTokenizer($lang)
    {
        if (isset(self::$tokenizers[$lang])) {
            return self::$tokenizers[$lang];
        }
        mb_regex_encoding('UTF-8');
        mb_internal_encoding("UTF-8");
        $lower_lang = strtolower($lang); //try to avoid case sensitivity issues
        $lang_parts = explode("-", $lang);
        if(!isset($lang_parts[1])) {
            $tokenizer_list = glob(C\LOCALE_DIR .
                "/$lang*/resources/Tokenizer.php");
            if(isset($tokenizer_list[0])) {
                $tag = substr($tokenizer_list[0], strlen(C\LOCALE_DIR) + 1,
                    - strlen("/resources/Tokenizer.php"));
            } else {
                $tag = "";
            }
        } else {
            $tag = str_replace("-", "_", $lang);
            if (!file_exists(C\LOCALE_DIR . "/$tag/resources/Tokenizer.php")) {
                $tokenizer_list = glob(C\LOCALE_DIR .
                    "/{$lang_parts[0]}*/resources/Tokenizer.php");
                if(isset($tokenizer_list[0])) {
                    $tag = substr($tokenizer_list[0], strlen(C\LOCALE_DIR) + 1,
                        - strlen("/resources/Tokenizer.php"));
                } else {
                    $tag = "";
                }
            }
        }
        $tokenizer_class_name = C\NS_LOCALE . "$tag\\resources\\Tokenizer";
        if (class_exists($tokenizer_class_name)) {
            $tokenizer_obj = new $tokenizer_class_name();
        } else {
            $tokenizer_obj = 0;
        }
        self::$tokenizers[$lang] = $tokenizer_obj;
        return $tokenizer_obj;
    }
    /**
     * Calculates the meta words to be associated with a given downloaded
     * document. These words will be associated with the document in the
     * index for (server:apache) even if the document itself did not contain
     * them.
     *
     * @param array& $site associated array containing info about a downloaded
     *     (or read from archive) document.
     * @param array $video_sources used to check if a page should be marked as
     *      having meta media:video
     * @return array of meta words to be associate with this document
     */
    public static function calculateMetas(&$site, $video_sources = [])
    {
        $meta_ids = [];
        // handles user added meta words
        if (isset($site[CrawlConstants::META_WORDS])) {
            $meta_ids = $site[CrawlConstants::META_WORDS];
        }
        /*
            Handle the built-in meta words. For example
            store the sites the doc_key belongs to,
            so you can search by site
        */
        $url_sites = UrlParser::getHostPaths($site[CrawlConstants::URL]);
        $url_sites = array_merge($url_sites,
            UrlParser::getHostSubdomains($site[CrawlConstants::URL]));
        $meta_ids[] = 'site:all';
        foreach ($url_sites as $url_site) {
            if (strlen($url_site) > 0) {
                $meta_ids[] = 'site:'.$url_site;
            }
        }
        $path =  UrlParser::getPath($site[CrawlConstants::URL]);
        if (strlen($path) > 0 ) {
            $path_parts = explode("/", $path);
            $pre_path = "";
            $meta_ids[] = 'path:all';
            $meta_ids[] = 'path:/';
            foreach ($path_parts as $part) {
                if (strlen($part) > 0 ) {
                    $pre_path .= "/$part";
                    $meta_ids[] = 'path:'.$pre_path;
                }
            }
        }
        $meta_ids[] = 'info:'.$site[CrawlConstants::URL];
        $meta_ids[] = 'info:'.crawlHash($site[CrawlConstants::URL]);
        $meta_ids[] = 'code:all';
        $meta_ids[] = 'code:'.$site[CrawlConstants::HTTP_CODE];
        if (UrlParser::getHost($site[CrawlConstants::URL])."/" ==
            $site[CrawlConstants::URL]) {
            $meta_ids[] = 'host:all'; //used to count number of distinct hosts
        }
        if (isset($site[CrawlConstants::SIZE])) {
            $meta_ids[] = "size:all";
            $interval = C\DOWNLOAD_SIZE_INTERVAL;
            $size = floor($site[CrawlConstants::SIZE]/$interval) * $interval;
            $meta_ids[] = "size:$size";
        }
        if (isset($site[CrawlConstants::TOTAL_TIME])) {
            $meta_ids[] = "time:all";
            $interval = C\DOWNLOAD_TIME_INTERVAL;
            $time = floor(
                $site[CrawlConstants::TOTAL_TIME]/$interval) * $interval;
            $meta_ids[] = "time:$time";
        }
        if (isset($site[CrawlConstants::DNS_TIME])) {
            $meta_ids[] = "dns:all";
            $interval = C\DOWNLOAD_TIME_INTERVAL;
            $time = floor(
                $site[CrawlConstants::DNS_TIME]/$interval) * $interval;
            $meta_ids[] = "dns:$time";
        }
        if (isset($site[CrawlConstants::LINKS])) {
            $num_links = count($site[CrawlConstants::LINKS]);
            $meta_ids[] = "numlinks:all";
            $meta_ids[] = "numlinks:$num_links";
            $link_urls = array_keys($site[CrawlConstants::LINKS]);
            $meta_ids[] = "link:all";
            foreach ($link_urls as $url) {
                    $meta_ids[] = 'link:'.$url;
                    $meta_ids[] = 'link:'.crawlHash($url);
            }
        }
        if (isset($site[CrawlConstants::LOCATION]) &&
            is_array($site[CrawlConstants::LOCATION])){
            foreach ($site[CrawlConstants::LOCATION] as $location) {
                $meta_ids[] = 'info:'.$location;
                $meta_ids[] = 'info:'.crawlHash($location);
                $meta_ids[] = 'location:all';
                $meta_ids[] = 'location:'.$location;
            }
        }
        if (isset($site[CrawlConstants::IP_ADDRESSES]) ){
            $meta_ids[] = 'ip:all';
            foreach ($site[CrawlConstants::IP_ADDRESSES] as $address) {
                $meta_ids[] = 'ip:'.$address;
            }
        }
        $meta_ids[] = 'media:all';
        if ($video_sources != []) {
            if (UrlParser::isVideoUrl($site[CrawlConstants::URL],
                $video_sources)) {
                $meta_ids[] = "media:video";
            } else {
                $meta_ids[] = (stripos($site[CrawlConstants::TYPE],
                    "image") !== false) ? 'media:image' : 'media:text';
            }
        }
        // store the filetype info
        $url_type = UrlParser::getDocumentType($site[CrawlConstants::URL]);
        if (strlen($url_type) > 0) {
            $meta_ids[] = 'filetype:all';
            $meta_ids[] = 'filetype:'.$url_type;
        }
        if (isset($site[CrawlConstants::SERVER])) {
            $meta_ids[] = 'server:all';
            $meta_ids[] = 'server:'.strtolower($site[CrawlConstants::SERVER]);
        }
        if (isset($site[CrawlConstants::SERVER_VERSION])) {
            $meta_ids[] = 'version:all';
            $meta_ids[] = 'version:'.
                $site[CrawlConstants::SERVER_VERSION];
        }
        if (isset($site[CrawlConstants::OPERATING_SYSTEM])) {
            $meta_ids[] = 'os:all';
            $meta_ids[] = 'os:'.strtolower(
                $site[CrawlConstants::OPERATING_SYSTEM]);
        }
        if (isset($site[CrawlConstants::MODIFIED])) {
            $modified = $site[CrawlConstants::MODIFIED];
            $meta_ids[] = 'modified:all';
            $meta_ids[] = 'modified:'.date('Y', $modified);
            $meta_ids[] = 'modified:'.date('Y-m', $modified);
            $meta_ids[] = 'modified:'.date('Y-m-d', $modified);
        }
        if (isset($site[CrawlConstants::TIMESTAMP])) {
            $date = $site[CrawlConstants::TIMESTAMP];
            $meta_ids[] = 'date:all';
            $meta_ids[] = 'date:'.date('Y', $date);
            $meta_ids[] = 'date:'.date('Y-m', $date);
            $meta_ids[] = 'date:'.date('Y-m-d', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H-i', $date);
            $meta_ids[] = 'date:'.date('Y-m-d-H-i-s', $date);
        }
        if (isset($site[CrawlConstants::LANG])) {
            $meta_ids[] = 'lang:all';
            $lang_parts = explode("-", $site[CrawlConstants::LANG]);
            $meta_ids[] = 'lang:'.$lang_parts[0];
            if (isset($lang_parts[1])){
                $meta_ids[] = 'lang:'.$site[CrawlConstants::LANG];
            }
        }
        if (isset($site[CrawlConstants::AGENT_LIST])) {
            foreach ($site[CrawlConstants::AGENT_LIST] as $agent) {
                $meta_ids[] = 'robot:'.strtolower($agent);
            }
        }
        //Add all meta word for subdoctype
        if (isset($site[CrawlConstants::SUBDOCTYPE])){
            $meta_ids[] = $site[CrawlConstants::SUBDOCTYPE].':all';
        }
        return $meta_ids;
    }
    /**
     * Used to compute all the meta ids for a given link with $url
     * and $link_text that was on a site with $site_url.
     *
     * @param string $url url of the link
     * @param string $link_host url of the host name of the link
     * @param string $link_text text of the anchor tag link came from
     * @param string $site_url url of the page link was on
     */
    public static function calculateLinkMetas($url, $link_host, $link_text,
        $site_url)
    {
        $link_meta_ids = [];
        if (strlen($link_host) == 0) {
            return $link_meta_ids;
        }
        if (substr($link_text, 0, 9) == "location:") {
            $location_link = true;
            $link_meta_ids[] = $link_text;
            $link_meta_ids[] = "location:all";
            $link_meta_ids[] = "location:" . crawlHash($site_url);
        }
        $link_type = UrlParser::getDocumentType($url);
        $link_meta_ids[] = "media:all";
        $link_meta_ids[] = "safe:all";
        /* Assumes PageProcessor::$image_types populated. True if called
           from Fetcher or CrawlComponent
         */
        if (in_array($link_type, PageProcessor::$image_types)) {
            $link_meta_ids[] = "media:image";
            if (isset($safe) && !$safe) {
                $link_meta_ids[] = "safe:false";
            }
        } else {
            $link_meta_ids[] = "media:text";
        }
        $link_meta_ids[] = "link:all";
        return $link_meta_ids;
    }
    /**
     * Given the word key of a term (a hash of the term string which may
     * have materialized meta information such as media type encoded in it),
     * compute the media type.
     * @param string $word_key hash of term with encoded metas
     * @return string what media type it is such as Text, Image, News, Video
     *      if it can be determined and unknown otherwise.
     */
    public static function getMediaType($word_key)
    {
        if (strlen($word_key) < 10) {
            return "unknown";
        }
        $media_char = $word_key[9];
        $media_types = ["media:text" => "Text", "media:image" => "Image",
            "media:video" => "Video", "media:news" => "News"];
        foreach ($media_types as $type => $common_name) {
            $material_meta_string = encodeMaterialMetas(array($type),
                PhraseParser::$materialized_metas);
            if ($material_meta_string[0] == $media_char) {
                return $common_name;
            }
        }
        return "Unknown";
    }
    /**
     * Given the word key of a term (a hash of the term string which may
     * have materialized meta information such as safe (not X-rated) search
     * info encoded in it), compute the safe value.
     * @param string $word_key hash of term with encoded metas
     * @return string whether the term is associated with a "safe" page
     *      in which case the string "True" is returned; an "unsafe" page
     *      in which case the string "False" is returned; or "Undefined"
     *      if it cannot be determined from the word key
     */
    public static function getSafety($word_key)
    {
        if (strlen($word_key) < 11) {
            return "unknown";
        }
        $safety_char = $word_key[10];
        $safety_types = ["safe:true" => "True", "safe:false" => "False"];
        foreach ($safety_types as $type => $common_name) {
            $material_meta_string = encodeMaterialMetas(array($type),
                PhraseParser::$materialized_metas);
            if ($material_meta_string[1] == $safety_char) {
                return $common_name;
            }
        }
        return "Undefined";
    }
    /**
     * Computes the Cosine-similarity of two phrases
     *
     * @param array $phrase1 first input phrase as array of terms
     * @param array $phrase2 second input phrase as array of terms
     * @return float Cosine-similarity of the phrases
     */
    public static function getCosineRank($phrase1, $phrase2)
    {
        $result = 0;
        $term_vector1 = [];
        $term_vector2 = [];
        $unique_terms = array_unique(array_merge($phrase1, $phrase2));
        foreach ($phrase1 as $term) {
            if (!isset($term_vector1[$term])) {
                $term_vector1[$term] = 0;
            }
            $term_vector1[$term]++;
        }
        $norm1 = 0;
        foreach ($phrase1 as $term) {
            $norm1 += $term_vector1[$term]*$term_vector1[$term];
        }
        $norm1 = sqrt($norm1);
        foreach ($phrase2 as $term) {
            if (!isset($term_vector2[$term])) {
                $term_vector2[$term] = 0;
            }
            $term_vector2[$term]++;
        }
        $norm2 = 0;
        foreach ($phrase2 as $term) {
            $norm2 += $term_vector2[$term]*$term_vector2[$term];
        }
        $norm2 = sqrt($norm2);
        foreach ($unique_terms as $term) {
            $inner_prod_term =
                (isset($term_vector1[$term]) && isset($term_vector2[$term])) ?
                $term_vector1[$term] * $term_vector2[$term]: 0;
            $result += $inner_prod_term;
        }
        $score = ($norm1 * $norm2 > 0) ?
            $result / sqrt($norm1 * $norm2) : 0;
        return $score;
    }
    /**
     * Computes the ratio of the number of terms shared by two phrases
     * divided by the average number of terms in a pair of phrases.
     *
     * @param array $terms1 first input phrase as array of terms
     * @param array $terms2 second input phrase as array of terms
     * @return float the above described ratio
     */
    public static function getIntersection($terms1, $terms2)
    {
        $total_terms = count($terms1) + count($terms2);
        if ($total_terms == 0) {
            return 0;
        }
        $num_intersect = count(array_intersect($terms1, $terms2));
        $avg_num_terms = $total_terms / 2;
        return $num_intersect / $avg_num_terms;
    }
    /**
     * Used to split a string of text in the language given by $locale into
     * space separated words. Ex: "acontinuousstringofwords" becomes
     * "a continuous string of words". It operates by scanning from the end of
     * the string to the front and splitting on the longest segment that is a
     * word.
     *
     * @param string $segment string to make into a string of space separated
     *     words
     * @param string $locale IANA tag used to look up dictionary filter to
     *     use to do this segmenting
     * @param array $additional_regexes which should be treated as a suffix
     * @return string space separated words
     */
    public static function reverseMaximalMatch($segment, $locale,
        $additional_regexes =[])
    {
        $segment = " " . $segment;
        $len = mb_strlen($segment);
        $cur_pos = $len;
        if ($cur_pos < 1) {
            return $segment;
        }
        $out_segment = "";
        $char_added = "";
        $word_guess = "";
        $was_space = true;
        while($cur_pos >= 0) {
            $cur_pos--;
            $char_added =  mb_substr($segment, $cur_pos, 1);
            $is_space = trim($char_added) == "";
            if ($is_space && $was_space) { 
                continue;
            } else if ($is_space) {
                $was_space = true;
                $one_word = self::oneWord($word_guess, $locale,
                    $additional_regexes);
                if ($one_word) {
                    $out_segment .= " ".strrev($word_guess);
                    $word_guess = "";
                } else {
                    $out_segment .= " ". strrev(mb_substr($word_guess, 1));
                    $out_segment .= " ". $char_added;
                    $word_guess = "";
                }
                continue;
            } else {
                $word_guess = $char_added . $word_guess;
                $was_space = false;
            }
            $is_suffix = NWordGrams::ngramsContains("*".$word_guess,
                $locale, "segment");
            if (!$is_suffix) {
                foreach ($additional_regexes as $regex) {
                    if (preg_match($regex, $word_guess)) {
                        $is_suffix = true;
                        break;
                    }
                }
            }
            if (!$is_suffix) {
                if(mb_strlen($word_guess) > 1 &&
                    !self::oneWord($word_guess, $locale, $additional_regexes)) {
                    $out_segment .= " ".strrev(mb_substr($word_guess, 1));
                    $word_guess = $char_added;
                } else {
                    $out_segment .= " ".strrev($word_guess);
                    $word_guess = "";
                }
                $was_space = false;
            }
        }
        $out_segment = strrev($out_segment);
        return $out_segment;
    }
    /**
     * Checks if a given word guess is a single word with respect to
     * a word detection bloom filter and regexes
     *
     * @param string $word_guess word guess to be checked if a single word
     * @param string $locale language to check if is word for
     * @param array $additional_regexes used in checking for this locale if
     *  something should be considered a word
     * @return bool true if a single word false otherwise
     */
    public static function oneWord($word_guess, $locale, $additional_regexes)
    {
        $one_word = false;
        if (NWordGrams::ngramsContains($word_guess, $locale,
            "segment")) {
            $one_word = true;
        } else {
            foreach ($additional_regexes as $regex) {
                if (preg_match($regex, $word_guess)) {
                    $one_word = true;
                    break;
                }
            }
        }
        return $one_word;
    }
    /**
     * Scores documents according to the lack or nonlack of sexually explicit
     * terms. Tries to work for several languages. Very crude classifier.
     *
     * @param array $word_lists word => pos_list tuples
     * @param int $len length of text being examined in characters
     * @return int $score of how explicit document is
     */
    public static function computeSafeSearchScore(&$word_lists, $len)
    {
        static $unsafe_phrase = "
XXX sex slut nymphomaniac MILF lolita lesbian sadomasochism
bondage fisting erotic vagina Tribadism penis facial hermaphrodite
transsexual tranny bestiality snuff boob fondle tit
blowjob lap cock dick hardcore pr0n fuck pussy penetration ass
cunt bisexual prostitution screw ass masturbation clitoris clit suck whore bitch
bellaco cachar chingar shimar chinquechar chichar clavar coger culear hundir
joder mámalo singar cojon carajo caray bicho concha chucha chocha
chuchamadre coño panocha almeja culo fundillo fundío puta puto teta
connorito cul pute putain sexe pénis vulve foutre baiser sein nicher nichons
puta sapatão foder ferro punheta vadia buceta bucetinha bunda caralho
mentula cunnus verpa sōpiō pipinna
cōleī cunnilingus futuō copulate cēveō crīsō
scortor meretrīx futatrix minchia coglione cornuto culo inocchio frocio puttana
vaffanculo fok hoer kut lul やりまん 打っ掛け
 二形 ふたなりゴックン ゴックン
ショタコン 全裸 受け 裏本 пизда́ хуй еба́ть
блядь елда́ гондо́н хер манда́ му́ди мудя
пидора́с залу́па жо́па за́дница буфер
雞巴 鷄巴 雞雞 鷄鷄 阴茎 陰莖 胯下物
屌 吊 小鳥 龟头 龜頭 屄 鸡白 雞白 傻屄 老二
那话儿 那話兒 屄 鸡白 雞白 阴道 陰道
阴户 陰戶 大姨妈 淫蟲 老嫖 妓女 臭婊子 卖豆腐
賣豆腐 咪咪 大豆腐 爆乳 肏操
炒饭 炒飯 cặc lồn kaltak orospu siktir sıçmak amcık";
        static $unsafe_terms = [];

        if (count($word_lists) == 0) {
            return 0;
        }
        if ($unsafe_terms == []) {
            $unsafe_lists = PhraseParser::extractPhrasesInLists($unsafe_phrase,
                "en-US");
            $unsafe_terms = array_keys($unsafe_lists);
        }
        $num_unsafe_terms = 0;
        $unsafe_count = 0;
        $words = array_keys($word_lists);

        $unsafe_found = array_intersect($words, $unsafe_terms);
        foreach ($unsafe_found as $term) {
            $count = count($word_lists[$term]);
            if ($count > 0 ) {
                $unsafe_count += $count;
                $num_unsafe_terms++;
            }
        }
        $score = $num_unsafe_terms * $unsafe_count/($len + 1);
        return $score;
    }
}
