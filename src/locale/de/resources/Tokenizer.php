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
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\locale\de\resources;

use seekquarry\yioop\library as L;

/**
 * German specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram
 *
 * This class has a collection of methods for German locale specific
 * tokenization. In particular, it has a stemmer, a stop word remover (for
 * use mainly in word cloud creation). The stemmer is my stab at re-implementing
 * the stemmer algorithm given at
 * http://snowball.tartarus.org/algorithms/german/stemmer.html
 * Here given a word, its stem is that part of the word that
 * is common to all its inflected variants. For example,
 * tall is common to tall, taller, tallest. A stemmer takes
 * a word and tries to produce its stem.
 *
 * @author Chris Pollett
 */
class Tokenizer
{
    /**
     * Words we don't want to be stemmed
     * @var array
     */
    public static $no_stem_list =["titanic"];
    /**
     * German vowels
     * @var string
     */
    private static $vowel = 'aeiouyäöü';
    /**
     * Things that might have an s following them
     * @var string
     */
    private static $s_ending = 'bdfghklmnrt';
    /**
     * Things that might have an st following them
     * @var string
     */
    private static $st_ending = 'bdfghklmnt';
    /**
     * $r1 is the region after the first non-vowel following a vowel, or the end
     * of the word if there is no such non-vowel.
     * @var string
     */
    private static $r1;
    /**
     * Position in $word to stem of $r1
     * @var int
     */
    private static $r1_index;
    /**
     * $r2 is the region after the first non-vowel following a vowel in $r1, or
     * the end of the word if there is no such non-vowel
     * @var string
     */
    private static $r2;
    /**
     * Position in $word to stem of $r2
     * @var int
     */
    private static $r2_index;
    /**
     * Storage used in computing the stem
     * @var string
     */
    private static $buffer;
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
     * Removes the stop words from the page (used for Word Cloud generation)
     *
     * @param string $page the page to remove stop words from.
     * @return string $page with no stop words
     */
    public static function stopwordsRemover($page)
    {
        $stop_words = ['aber', 'alle', 'allem', 'allen', 'aller', 'alles',
            'als', 'as', 'also', 'am', 'an', 'ander', 'andere', 'anderem',
            'anderen', 'anderer', 'anderes', 'anderm', 'andern', 'anderr',
            'anders', 'auch', 'auf', 'aus', 'bei', 'bin', 'bis', 'bist',
            'da', 'damit', 'dann', 'der', 'den', 'des', 'dem', 'die', 'das',
            'daß', 'derselbe', 'derselben', 'denselben', 'desselben',
            'demselben', 'dieselbe', 'dieselben', 'dasselbe', 'dazu', 'dein',
            'deine', 'deinem', 'deinen', 'deiner', 'deines', 'denn', 'derer',
            'dessen', 'dich', 'dir', 'du', 'dies', 'diese', 'diesem', 'diesen',
            'dieser', 'dieses', 'doch', 'dort', 'durch', 'ein', 'eine', 'einem',
            'einen', 'einer', 'eines', 'einig', 'einige', 'einigem', 'einigen',
            'einiger', 'einiges', 'einmal', 'er', 'ihn', 'ihm', 'es', 'etwas',
            'euer', 'eure', 'eurem', 'euren', 'eurer', 'eures', 'für', 'gegen',
            'gewesen', 'hab', 'habe', 'haben', 'hat', 'hatte', 'hatten', 'hier',
            'hin', 'hinter', 'http', 'https', 'ich', 'mich', 'mir', 'ihr',
            'ihre', 'ihrem',
            'ihren', 'ihrer', 'ihres', 'euch', 'im', 'in', 'indem', 'ins',
            'ist', 'jede', 'jedem', 'jeden', 'jeder', 'jedes', 'jene', 'jenem',
            'jenen', 'jener', 'jenes', 'jetzt', 'kann', 'kein', 'keine',
            'keinem', 'keinen', 'keiner', 'keines', 'können', 'könnte',
            'machen', 'man', 'manche', 'manchem', 'manchen', 'mancher',
            'manches', 'mein', 'meine', 'meinem', 'meinen', 'meiner', 'meines',
            'mit', 'muss', 'musste', 'nach', 'nicht', 'nichts', 'noch', 'nun',
            'nur', 'ob', 'oder', 'ohne', 'sehr', 'sein', 'seine', 'seinem',
            'seinen', 'seiner', 'seines', 'selbst', 'sich', 'sie', 'ihnen',
            'sind', 'so', 'solche', 'solchem', 'solchen', 'solcher', 'solches',
            'soll', 'sollte', 'sondern', 'sonst', 'über', 'um', 'und', 'uns',
            'unse', 'unsem', 'unsen', 'unser', 'unses', 'unter', 'viel', 'vom',
            'von', 'vor', 'während', 'war', 'waren', 'warst', 'was', 'weg',
            'weil', 'weiter', 'welche', 'welchem', 'welchen', 'welcher',
            'welches', 'wenn', 'werde', 'werden', 'wie', 'wieder', 'will',
            'wir', 'wird', 'wirst', 'wo', 'wollen',
            'wollte', 'würde', 'würden', 'zu', 'zum',
            'zur', 'zwar', 'zwischen'
            ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/u', '',
            strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of a German word
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }
        self::$buffer = strtolower($word);
        self::prelude();
        self::markRegions();
        self::backwardSuffix();
        self::postlude();
        return self::$buffer;
    }
    /**
     * Upper u and y between vowels so won't be treated as a vowel for the
     * purpose of this algorithm. Maps ß to ss.
     */
    private static function prelude()
    {
        $vowel = static::$vowel;
        $word = self::$buffer;
        //map non-vowel u and y to capitals
        $word = preg_replace("/([$vowel])u([$vowel])/u", '$1U$2',
            $word);
        $word = preg_replace("/([$vowel])y([$vowel])/u", '$1Y$2',
            $word);
        $word = preg_replace("/ß/u", 'ss', $word);
        self::$buffer = $word;
    }
    /**
     * Computes locations of rv - RV is the region after the third letter,
     * otherwise the region after the first vowel
     * not at the beginning of the word, or the end of the word if
     * these positions cannot be found. , r1 is the region after the first
     * non-vowel following a vowel, or the end of the word if there is no such
     * non-vowel and R2 is the region after the first non-vowel following a
     * vowel in R1, or the end of the word if there is no such non-vowel.
     *
     */
    private static function markRegions()
    {
        $word = self::$buffer;
        $vowel = static::$vowel;
        preg_match("/[$vowel][^$vowel]/u", $word, $matches,
            PREG_OFFSET_CAPTURE);
        self::$r1 = "";
        $len = mb_strlen($word);
        self::$r1_index = isset($matches[0][1]) ? $matches[0][1] + 2 : $len;
        if (self::$r1_index != $len) {
            self::$r1 = mb_substr($word, self::$r1_index);
        }
        if (self::$r1_index != $len) {
            preg_match("/[$vowel][^$vowel]/u", self::$r1, $matches,
                PREG_OFFSET_CAPTURE);
            self::$r2_index = isset($matches[0][1]) ? $matches[0][1] + 2 : $len;
            if (self::$r2_index != $len) {
                self::$r2 = mb_substr(self::$r1, self::$r2_index);
                self::$r2_index += self::$r1_index;
            }
        }
        if (self::$r1_index != $len && self::$r1_index < 3) {
            $tmp = mb_substr($word, 0, 2, "UTF-8");
            self::$r1_index = 3;
            if (strlen($tmp) == 3) {
                self::$r1_index = 4;
            }
        }
    }
    /**
     * Used to strip suffixes off word
     */
    private static function backwardSuffix()
    {
        /*
        Step 1:
        Search for the longest among the following suffixes,
        (a) em   ern   er
        (b) e   en   es
        (c) s (preceded by a valid s-ending)
        */
        $word = self::$buffer;
        $a1_index = L\preg_search('/(ern|er|em)$/u', $word);
        $b1_index = L\preg_search('/(en|es|e)$/u', $word);
        $s_ending = self::$s_ending;
        $c1_index = L\preg_search("/([$s_ending]s)$/u", $word);
        if ($c1_index != -1) { $c1_index++; }
        $infty =  strlen($word) + 1;
        $index1 = $infty;
        $option_used1 = '';
        if ($a1_index != -1 && $a1_index < $index1) {
            $option_used1 = 'a';
            $index1 = $a1_index;
        }
        if ($b1_index != -1 && $b1_index < $index1) {
            $option_used1 = 'b';
            $index1 = $b1_index;
        }
        if ($c1_index != -1 && $c1_index < $index1) {
            $option_used1 = 'c';
            $index1 = $c1_index;
        }
        /*
            and delete if in R1. (Of course the letter of the valid s-ending is
            not necessarily in R1.) If an ending of group (b) is deleted, and
            the ending is preceded by niss, delete the final s. (For example,
            äckern -> äck, ackers -> acker, armes -> arm,
            bedürfnissen -> bedürfnis)
        */
        if ($index1 != $infty && self::$r1_index != -1) {
            if ($index1 >= self::$r1_index) {
                $word = substr($word, 0, $index1);
                if ($option_used1 == 'b') {
                    if (L\preg_search('/niss$/u', $word) != -1) {
                        $word = mb_substr($word, 0, mb_strlen($word) - 1);
                    }
                }
            }
        }
        /*
        Step 2:
        Search for the longest among the following suffixes,
        (a) en   er   est
        (b) st (preceded by a valid st-ending, itself preceded by at least 3
        letters)
        */

        $a2_index = L\preg_search('/(en|er|est)$/u', $word);
        $st_ending = self::$st_ending;
        $b2_index = -1;
        $pattern = "/(.{3}[$st_ending]st)$/u";
        if (preg_match($pattern, $word, $matches, PREG_OFFSET_CAPTURE)) {
            $b2_index = $matches[0][1];
        }
        if ($b2_index != -1) {
            $b2_index += strlen($matches[0][0]) - 2;
        }
        $index2 = $infty;
        $option_used2 = '';
        if ($a2_index != -1 && $a2_index < $index2) {
            $option_used2 = 'a';
            $index2 = $a2_index;
        }
        if ($b2_index != -1 && $b2_index < $index2) {
            $option_used2 = 'b';
            $index2 = $b2_index;
        }

        /*
        and delete if in R1.
        (For example, derbsten -> derbst by step 1, and derbst -> derb by
        step 2, since b is a valid st-ending, and is preceded by just 3 letters)
        */
        if ($index2 != $infty && self::$r1_index != -1) {
            if ($index2 >= self::$r1_index) {
                $word = substr($word, 0, $index2);
            }
        }
        /*
        Step 3: d-suffixes (*)
        Search for the longest among the following suffixes, and perform
        the action indicated.
        end   ung
            delete if in R2
            if preceded by ig, delete if in R2 and not preceded by e
        ig   ik   isch
            delete if in R2 and not preceded by e
        lich   heit
            delete if in R2
            if preceded by er or en, delete if in R1
        keit
            delete if in R2
            if preceded by lich or ig, delete if in R2
        */
        $a3_index = L\preg_search('/(end|ung)$/', $word);
        $b3_index = L\preg_search('/[^e](ig|ik|isch)$/', $word);
        $c3_index = L\preg_search('/(lich|heit)$/', $word);
        $d3_index = L\preg_search('/(keit)$/', $word);
        if ($b3_index != -1) {
            $b3_index++;
        }
        $index3 = $infty;
        $option_used3 = '';
        if ($a3_index != -1 && $a3_index < $index3) {
            $option_used3 = 'a';
            $index3 = $a3_index;
        }
        if ($b3_index != -1 && $b3_index < $index3) {
            $option_used3 = 'b';
            $index3 = $b3_index;

        }
        if ($c3_index != -1 && $c3_index < $index3) {
            $option_used3 = 'c';
            $index3 = $c3_index;
        }
        if ($d3_index != -1 && $d3_index < $index3) {
            $option_used3 = 'd';
            $index3 = $d3_index;
        }
        if ($index3 != $infty && self::$r2_index != -1) {
            if ($index3 >= self::$r2_index) {
                $word = substr($word, 0, $index3);
                $option_index = -1;
                $option_subsrt = '';
                if ($option_used3 == 'a') {
                    $option_index = L\preg_search('/[^e](ig)$/u', $word);
                    if ($option_index != -1) {
                        $option_index++;
                        if ($option_index >= self::$r2_index) {
                            $word = substr($word, 0, $option_index);
                        }
                    }
                } else if ($option_used3 == 'c') {
                    $option_index = L\preg_search('/(er|en)$/u', $word);
                    if ($option_index != -1) {
                        if ($option_index >= self::$r1_index) {
                            $word = substr($word, 0, $option_index);
                        }
                    }
                } else if ($option_used3 == 'd') {
                    $option_index = L\preg_search('/(lich|ig)$/u', $word);
                    if ($option_index != -1) {
                        if ($option_index >= self::$r2_index) {
                            $word = substr($word, 0, $option_index);
                        }
                    }
                }
            }
        }
        self::$buffer = $word;
    }
    /**
     * Convert captitalized U and Y back to lower-case get rid of any dots
     * above vowels
     */
    private static function postlude()
    {
        $vowel = static::$vowel;
        $word = self::$buffer;
        $word = preg_replace('/U/u', 'u', $word);
        $word = preg_replace('/Y/u', 'y', $word);
        $word = preg_replace('/ä/u', 'a', $word);
        $word = preg_replace('/ö/u', 'o', $word);
        $word = preg_replace('/ü/u', 'u', $word);
        self::$buffer = $word;
    }
}
