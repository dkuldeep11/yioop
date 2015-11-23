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
 * @author Niravkumar Patel niravkumar.patel1989@gmail.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\locale\pt\resources;

/**
 * This class has a collection of methods for Portuguese locale specific
 * tokenization. In particular, it has a stemmer implementing the
 * Snowball Stemming algorithm presented in
 * http://snowball.tartarus.org/algorithms/portuguese/stemmer.html
 *
 * @author Niravkumar Patel
 */
class Tokenizer
{
    /**
     * Phrases we would like yioop to rewrite before performing a query
     * @var array
     */
    public static $semantic_rewrites = [];
    /**
     * storage used in computing the stem
     * @var string
     */
    private static $buffer;
    /**
     * Index of the current end of the word at the current state of computing
     * its stem
     * @var int
     */
    private static $k;
    /**
     * R1 is the region in the word after the first non-vowel following
     * a vowel, or is the null region at the end of the word if there
     * is no such non-vowel
     * @var string
     */
    private static $r1 = "";
    /**
     * R2 is the region in the R1 after the first non-vowel following a vowel,
     * or is the null region at the end of the word if there is no non-vowel
     * @var string
     */
    private static $r2 = "";
    /**
     * If the second letter is a consonant, RV is the region after the next
     * following vowel, or if the first two letters are vowels, RV is the
     * region after the next consonant, and otherwise (consonant-vowel case)
     * RV is the region after the third letter.
     * @var string
     */
    private static $rv = "";
    /**
     * Stub function which could be used for a word segmenter.
     * Such a segmenter on input thisisabunchofwords would output
     * this is a bunch of words
     *
     * @param string $pre_segment  before segmentation
     * @return string should return string with words separated by space
     *     in this case does nothing
     */
    public static function segment($pre_segment)
    {
        return $pre_segment;
    }
    /**
     * Computes the stem of an Portuguese word
     * For example, química, químicas, químico, químicos
     * all have químic as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        self::$buffer = $word;
        self::$k = strlen($word) - 1;
        if (self::$k <= 1) { return $word; }
        $word = str_ireplace("ã", "a~", $word);
        $word = str_ireplace("õ", "o~", $word);
        self::$r1 = self::findR1($word);
        self::$r2 = self::findR1(self::$r1);
        self::findRV($word);
        $temp_word = self::step1($word);
        if ($temp_word == $word){
            $temp_word = self::step2($word);
        }
        if ($temp_word != $word){
            self::findRV($temp_word);
            $temp_word = self::step3($temp_word);
        }else{
            $temp_word = self::step4($temp_word);
            self::findRV($temp_word);
        }
        $temp_word = self::step5($temp_word);
        $temp_word = str_ireplace("a~", "ã", $temp_word);
        $temp_word = str_ireplace("o~", "õ", $temp_word);
        self::$rv = "";
        $word = $temp_word;
        return $word;
    }
    /**
     * Standard Suffix Removal Step
     * It search for longest suffix from given set and remove
     * if found
     *
     * @param string $word the string to suffix removal
     * @return processed string
     */
    private static function step1($word)
    {
        $suffix_set = ['eza','ezas','ico','ica','icos','icas','ismo',
            'ismos','ável','ível','ista','istas','oso','osa',
            'osos','osas','amento','amentos','imento','imentos',
            'adora','ador','aça~o','adoras','adores','aço~es',
            'ante','antes','ância'];
        $regex = "/[a-zA-Z~]*(eza|ezas|ico|ica|icos|icas|ismo|ismos|ável|ível
            |ista|istas|oso|osa|osos|osas|amento|amentos|imento
            |imentos|adora|ador|aça~o|adoras|adores|aço~es|ante
            |antes|ância)";
        foreach ($suffix_set as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)) {
                $word = substr($word,0,-strlen($value));
                break;
            }
        }
        $special_suffix = ['logía' , 'logías'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)){
                $word = substr($word,0,-strlen($value))."log";
                break;
            }
        }
        $special_suffix = ['ución','uciones'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)) {
                $word = substr($word,0,-strlen($value))."u";
                break;
            }
        }
        $special_suffix = ['ência','ências'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)){
                $word = substr($word,0,-strlen($value))."ente";
                break;
            }
        }
        $special_suffix = ['ativamente','ivamente','osamente','icamente',
            'adamente','antemente','avelmente','ívelmente'];
        $is_removed = false;
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)) {
                $word = substr($word,0,-strlen($value));
                $is_removed = true;
                break;
            }
        }
        if (!$is_removed) {
            $is_removed = false;
            $value = "amente";
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r1)) {
                $word = substr($word,0,-strlen($value));
                $is_removed = true;
            }
            if (!$is_removed) {
                $value = "mente";
                $regex = "/[a-zA-Z~]*($value)$/";
                if (preg_match($regex, self::$r2)){
                    $word = substr($word,0,-strlen($value));
                    $is_removed = true;
                }
            }
        }

        $special_suffix = ['abilidades','icidades','ividades','abilidade',
            'icidade','ividade','idades','idade'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)) {
                $word = substr($word,0,-strlen($value));
                break;
            }
        }
        $special_suffix = ['ativa','ativo','ativas','ativos','iva','ivo',
            'ivas','ivos'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$r2)){
                $word = substr($word,0,-strlen($value));
                break;
            }
        }
        $special_suffix = ['iras','ira'];
        foreach ($special_suffix as $value) {
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$rv)
                && preg_match("/[a-zA-Z~]*(eiras|eira)$/",$word)) {
                $word = substr($word,0,-strlen($value))."ir";
                break;
            }
        }
        return $word;
    }
    /**
     * Verb Suffix Removal Step
     * If step 1 does not change anything than this function
     * will be called
     *
     * It will also check for longest suffix from the suffix set
     * Remove if found
     *
     * @param string $word the string to suffix removal
     * @return processed string
     */
    private static function step2($word)
    {
        $suffix_set = ["aríamos","eríamos","iríamos","ássemos",
            "êssemos","íssemos","aríeis","eríeis","iríeis","ásseis",
            "ésseis","ísseis","áramos","éramos","íramos","ávamos",
            "aremos","eremos","iremos","ariam","eriam","iriam",
            "assem","essem","issem","ara~o","era~o","ira~o","arias",
            "erias","irias","ardes","erdes","irdes","asses","esses",
            "isses","astes","estes","istes","áreis","areis","éreis",
            "ereis","íreis","ireis","áveis","íamos","armos","ermos","irmos",
            "aria","eria","iria","asse","esse","isse","aste","este","iste",
            "arei","erei","irei","aram","eram","iram","avam","arem","erem",
            "irem","ando","endo","indo","adas","idas","arás","aras","erás",
            "eras","irás","avas","ares","eres","ires","íeis","ados","idos",
            "ámos","amos","emos","imos","iras","ada","ida","ará","ara","erá",
            "era","irá","ava","iam","ado","ido","ias","ais","eis","ira","ia",
            "ei","am","em","ar","er","ir","as","es","is","eu","iu","ou"];
        foreach ($suffix_set as $value){
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, self::$rv)) {
                $word = substr($word,0,-strlen($value));
                break;
            }
        }
        return $word;
    }
    /**
     * Delete suffix i if in RV and preceded by c
     *
     * @param string $word the string to suffix removal
     * @return processed string
     */
    private static function step3($word)
    {
        $regex = "/[a-zA-Z~]*(ci)$/";
        if (preg_match($regex, $word)) {
            $regex = "/(i)[a-zA-Z~]*$/";
            if (preg_match($regex, self::$rv)) {
                $word = substr($word,0,-1);
            }
        }
        return $word;
    }
    /**
     * Residual suffix
     * If the word ends with one of [os a i o á í ó]
     * in RV
     *
     * @param string $word the string to suffix removal
     * @return processed string
     */
    private static function step4($word)
    {
        $regex = "/[a-zA-Z~]*(os)$/";
        if (preg_match($regex, self::$rv)){
            $word = substr($word,0,-2);
        }else{
            $regex = "/[a-zA-Z~]*(a|i|o)$/";
            if (preg_match($regex, self::$rv)){
                $word = substr($word,0,-1);
            }else{
                $regex = "/[a-zA-Z~]*(á|í|ó)$/";
                if (preg_match($regex, self::$rv)){
                    $word = substr($word,0,-2);
                }
            }
        }
        return $word;
    }
    /**
     * Residual suffix
     * If the word ends with one of [e é ê]
     * in RV
     *
     * @param string $word the string to suffix removal
     * @return processed string
     */
    private static function step5($word)
    {
        if (preg_match("/[a-zA-Z~]*(ç)$/", $word)){
            $word = substr($word,0,-2)."c";
            return $word;
        }
        $special_suffix = ["gue","cie"];
        foreach ($special_suffix as $value){
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, $word)) {
                $value = substr($value,1,strlen($value));
                $regex = "/[a-zA-Z~]*($value)[a-zA-Z~]*$/";
                if (preg_match($regex, self::$rv)){
                    $word = substr($word, 0, -2);
                    return $word;
                }
            }
        }
        $special_suffix = ["gué","guê","cié","ciê"];
        foreach ($special_suffix as $value){
            $regex = "/[a-zA-Z~]*($value)$/";
            if (preg_match($regex, $word)) {
                $value = substr($value, 1, strlen($value));
                $regex = "/[a-zA-Z~]*($value)$/";
                if (preg_match($regex, self::$rv)){
                    $word = substr($word, 0, -3);
                    return $word;
                }
            }
        }
        if (preg_match("/[a-zA-Z~]*(e)$/", $word)
            && preg_match("/[a-zA-Z~]*(e)[a-zA-Z~]*$/", self::$rv)){
            $word = substr($word, 0, -1);
            return $word;
        }
        if (preg_match("/[a-zA-Z~]*(é|ê)$/", $word)
            && preg_match("/[a-zA-Z~]*(é|ê)[a-zA-Z~]*$/", self::$rv)){
            $word = substr($word, 0, -2);
            return $word;
        }
        return $word;
    }
    /**
     * This method will find R1 region in the $word
     * R1 is the region after the first non-vowel following a vowel, or is
     * the null region at the end of the word if there is no such non-vowel
     *
     * @param string $word
     * @return string $r1 region
     */
    private static function findR1($word)
    {
        $word_length = mb_strlen($word);
        $vowel = self::mbStringToArray("aeiouáéíóúâêô");
        $first_vowel = false;
        $second_vowel = false;
        $first_consonent = false;
        $second_consonent = false;
        $r1 = "";
        $word_array = self::mbStringToArray($word);
        $i=0;
        for ($i=0; $i<$word_length; $i++) {
            if (in_array($word_array[$i],$vowel)) {
                if ($first_vowel) {
                    if ($first_consonent) {
                        break;
                    }
                }
                $first_vowel = true;

            } else {
                if ($first_vowel) {
                    if ($first_consonent ){
                        break;
                    }
                    $first_consonent = true;
                }
            }
        }
        return mb_substr($word, $i, mb_strlen($word));
    }
    /**
     * This method will find RV region in the $word
     * If the second letter is a consonant,
     * RV is the region after the next following vowel, or
     * if the first two letters are vowels,
     * RV is the region after the next consonant,
     * and otherwise (consonant-vowel case)
     * RV is the region after the third letter.
     *
     * @param string $word
     * @return string $rv region
     */
    private static function findRV($word)
    {
        $vowel = self::mbStringToArray("aeiouáéíóúâêô");
        $word_array = self::mbStringToArray($word);
        if (!isset($word_array[1])  || !in_array($word_array[1], $vowel)){
            $word_length = mb_strlen($word);
            $i = 2;
            for ($i = 2; $i < $word_length; $i++) {
                if (in_array($word_array[$i],$vowel)){
                    $i++;
                    break;
                }
            }
            self::$rv = mb_substr($word, $i, mb_strlen($word));
        } else if (in_array($word_array[0], $vowel)
            && in_array($word_array[1],$vowel)) {
            $word_length = mb_strlen($word);
            for ($i = 2; $i < $word_length; $i++){
                if (!in_array($word_array[$i],$vowel)){
                    self::$rv = mb_substr($word, $i + 1, mb_strlen($word));
                    break;
                }
            }
        } else if (!in_array($word_array[0], $vowel)
            && in_array($word_array[1],$vowel)){
            self::$rv = mb_substr($word, 3, mb_strlen($word));
        }
    }
    /**
     * This method will break-up a multibyte string into its individual
     * characters and generate an array of characters
     *
     * @param string $string of multibyte characters to break-up
     * @return array of multibyte characters
     */
    private static function mbStringToArray($string)
    {
        $len = mb_strlen($string);
        $array = [];
        while($len) {
            $array[] = mb_substr($string,0, 1, "UTF-8");
            $string = mb_substr($string, 1, $len,"UTF-8");
            $len = mb_strlen($string);
        }
        return $array;
    }
}
