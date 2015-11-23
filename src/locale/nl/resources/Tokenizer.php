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
namespace seekquarry\yioop\locale\nl\resources;

/**
 * This class has a collection of methods for Dutch locale specific
 * tokenization. In particular, it has a stemmer, .
 *
 * @author Charles Bocage
 */
class Tokenizer
{
    /**
     * Words we don't want to be stemmed
     * @var array
     */
    public static $no_stem_list = [
        "abs", "ahs", "aken", "àlle", "als", "are",
        "allèen", "ate", "aten", "azen", "bse", "cfce", "curaçao",
        "dègelijk", "dme", "ede", "eden", "eds", "ehs", "ems", "ene", "epe",
        "eps", "ers", "eten", "ets", "even", "fme", "gedaçht", "ghe", "gve",
        "hdpe", "hôte", "hpe", "hse", "ibs", "ics", "ile", "ims", "jònge",
        "kwe", "ldpe", "lldpe", "lme", "lze", "maitres", "mwe", "nme", "ode",
        "ogen", "oke", "ole", "ons", "ònze", "open", "ops", "oren", "ors",
        "oss", "oven", "ows", "pre", "pve", "rhône", "ròme", "rwe", "ske",
        "sme", "spe", "ste", "the", "tje", "uce", "uden", "uien", "uren",
        "use", "uwe", "vse", "ype"
    ];
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
     * boolean that tells the code if the e suffix was removed in step2 or not
     * @var array
     */
    static $removed_e_suffix = false;
    /**
     * Computes the stem of a Dutch word
     *
     * For example, lichamelijk, lichamelijke, lichamelijkheden and lichamen,
     * all have licham as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        self::$removed_e_suffix = false;
        $result = $word;
        if (isset($word) && !empty($word)) {
            $result = self::removeAllUmlautAndAcuteAccents($word);
            $result = trim(mb_strtolower($result));
            $result = self::substituteIAndY($result);
            $R1 = self::getRIndex($result, 1);
            $R2 = self::getRIndex($result, $R1);
            $result = self::step1($result, $R1);
            $result = self::step2($result);
            $result = self::step3a($result, $R2);
            $result = self::step3b($result, $R2);
            $result = self::step4($result);
            $result = mb_strtolower($result);
        }
        return $result;
    }
    /**
     * Remove all umlaut and acute accents that need to be removed.
     *
     * @param string $word the string to remove the umlauts and accents from
     * @return string the string with the umlauts and accents removed
     */
    private static function removeAllUmlautAndAcuteAccents($word)
    {
        $result = preg_replace("/\é|\ë/", "e", $word);
        $result = preg_replace("/\á|ä/", "a", $result);
        $result = preg_replace("/\ó|ö/", "o", $result);
        $result = preg_replace("/\ï/", "i", $result);
        $result = preg_replace("/\ü|\ú/", "u", $result);
        return $result;
    }
    /**
     * Put initial y, y after a vowel, and i between vowels into upper case.
     *
     * @param string $word the string to put initial y, y after a vowel, and
     * i between vowels into upper case.
     * @return string the string with an initial y, y after a vowel, and i
     * between vowels into upper case.
     */
    private static function substituteIAndY($word)
    {
        $word_split = preg_split('/(?!^)(?=.)/u', $word);
        for ($i = 0; $i < count($word_split); $i++) {
            if ($i == 0) {
                if ($word_split[$i] == 'y') {
                    $word_split[$i] = 'Y';
                }
            } else {
                if ($word_split[$i] == 'i') {
                    if (count($word_split) > ($i + 1)) {
                        if (self::isVowel($word_split[$i - 1]) &&
                            self::isVowel($word_split[$i + 1])) {
                            $word_split[$i] = 'I';
                        }
                    }
                } elseif ($word_split[$i] == 'y') {
                    if (self::isVowel($word_split[$i - 1])) {
                        $word_split[$i] = 'Y';
                    }
                }
            }
        }
        return implode('', $word_split);
    }
    /**
     * Check that the letter is a vowel
     *
     * @param string $letter the character to check
     * @return boolean true if it is a vowel, otherwise false
     */
    private static function isVowel($letter)
    {
        $result = false;
        switch ($letter) {
            case 'e':
                // no break
            case 'a':
            case 'o':
            case 'i':
            case 'u':
            case 'y':
            case 'è':
                $result = true;
        }
        return $result;
    }
    /**
     * Get the R index.  The R index is the first consonent that follows a
     * vowel after the $start index
     *
     * @param string $word the string to search for the R index
     * @param int $start the index to start searching for the R index in the
     * string
     * @return int the R index if found, otherwise -1
     */
    private static function getRIndex($word, $start)
    {
        $result = -1;
        $word_split = preg_split('/(?!^)(?=.)/u', $word);
        for ($i = $start <= 0 ? 1 : $start; $i < count($word_split); $i++) {
            if (!self::isVowel($word_split[$i]) &&
                self::isVowel($word_split[$i - 1])) {
                $result = ($i + 1);
                break;
            }
        }
        return $result;
    }
    /**
     * Define a valid en-ending as a non-vowel, and not gem and remove it
     *
     * @param string $word the string to stem
     * @param int $R1 the int that represents the R index
     * @return string the string with the valid en-ending as a non-vowel, and
     * not gem ending removed
     */
    private static function step1($word, $R1)
    {
        $result = $word;
        if ($R1 > -1) {
            $wordLength = strlen($word);
            if ($wordLength > 2 && $R1 < $wordLength) {
                if (self::endsWith($word, "heden")) {
                    $result = self::replace($word, '/heden$/', 'heid', $R1);
                } else {
                    if (preg_match("/(?<![aeioèuy]|gem)(ene?)$/", $word,
                        $matches, 0, $R1) && $word != "eten" &&
                        $word != "even" && $word != "opene") {
                        $result = self::undouble(self::replace($word,
                            '/ene$/', '', $R1));
                        $result = self::undouble(self::replace($result,
                            '/en$/', '', $R1));
                    } else {
                        if (preg_match("/(?<![aeiouyèj])se$/", $word,
                            $matches, 0, $R1) && $word != "osse") {
                            $result = self::replace($word,
                                '/(?<![aeiouyèj])se$/', '', $R1);
                        } elseif (preg_match("/(?<![aeiouyèj])s$/", $word,
                            $matches, 0, $R1)) {
                            $result = self::replace($word,
                                '/(?<![aeiouyèj])s$/', '', $R1);
                        }
                    }
                }
            }
        }
        return $result;
    }
    /**
     * Delete the suffix e if in R1 and preceded by a non-vowel, and then
     * undouble the ending
     *
     * @param string $word the string to delete the suffix e if in R1 and
     * preceded by a non-vowel, and then undouble the ending
     * @return string the string with the suffix e if in R1 and preceded by
     * a non-vowel deleted, and then undouble the ending
     */
    private static function step2($word)
    {
        $result = $word;
        if (self::endsWith($word, "e")
            && $word != "ene" && $word != "oce" && $word != "ohe" &&
                $word != "pâte") {
            $word_split = preg_split('/(?!^)(?=.)/u', $word);
            $word_split_length = count($word_split);
            if ($word_split_length > 2 &&
                !self::isVowel($word_split[$word_split_length - 2])) {
                $word_split = array_slice($word_split, 0,
                    $word_split_length - 1);
                $result = implode('', $word_split);
                $result = self::undouble($result);
                self::$removed_e_suffix = true;
            }
        }
        return $result;
    }
    /**
     * Delete the letters heid if in R2 and not preceded by a c, and treat an a
     * preceding en like in step 1
     *
     * @param string $word the string to delete the letters heid if in R2 and
     * not preceded by a c, and treat an a preceding en like in step 1
     * @param int $R2 the R index
     * @return string the string with the letters heid if in R2 and not
     * preceded by a c deleted, and treated an a preceding en like in step 1
     */
    private static function step3a($word, $R2)
    {
        $result = $word;
        if ($R2 > -1) {
            if (preg_match("/(?<![c])(heid)$/", $word, $matches, 0, $R2)) {
                $result = self::replace($word, '/(?<![c])(heid)$/', '', $R2);
                if (preg_match("/(?<![aeiouyè]|gem)(ene?)$/", $result,
                    $matches, 0, $R2)) {
                    $result = self::undouble(self::replace($result,
                        '/(?<![aeiouyè]|gem)(ene?)$/', '', $R2));
                }
            }
        }
        return $result;
    }
    /**
     * Search for the longest among the following suffixes, and perform the
     * action indicated.
     * If in R2 and ends with eigend, eigingm igend or iging remove it
     * If in R2 and ends with ig preceded by an e remove it
     * If in R2 and ends with lijk, baar or bar then remove it
     *
     * @param string $word the string to stem
     * @param int $R2 the R index
     * @return string the string with the various endings removed if they exist
     */
    public static function step3b($word, $R2)
    {
        $result = $word;
        $word_split = preg_split('/(?!^)(?=.)/u', $word);
        if (count($word_split) > 2) {
            if ($R2 > -1) {
                if (preg_match('/(end|ing)$/', $word, $matches, 0, $R2)) {
                    if (preg_match('/eig(end|ing)$/', $word, $matches, 0,
                        $R2)) {
                        $result = self::replace($word, '/(end|ing)$/', '',
                            $R2);
                    }
                    elseif (preg_match('/ig(end|ing)$/', $word, $matches, 0,
                        $R2)) {
                        $result = self::replace($word, '/(igend|iging)$/', '',
                            $R2);
                        $result = self::undouble($result);
                    }
                    else {
                        $result = self::replace($word, '/(end|ing)$/', '',
                            $R2);
                        $result = self::undouble($result);
                    }
                }
                elseif (preg_match("/(?<![e])ig$/", $word, $matches, 0, $R2)) {
                    $result = self::replace($word, '/(?<![e])ig$/', '', $R2);
                }
                elseif (preg_match("/lijk$/", $word, $matches, 0, $R2)) {
                    $result = self::replace($word, '/lijk$/', '', $R2);
                    $result = self::step2($result);
                }
                elseif (preg_match("/baar$/", $word, $matches, 0, $R2)) {
                    $result = self::replace($word, '/baar$/', '', $R2);
                }
                elseif (preg_match("/bar$/", $word, $matches, 0, $R2) &&
                    self::$removed_e_suffix) {
                    $result = self::replace($word, '/bar$/', '', $R2);
                }
            }
        }
        return $result;
    }
    /**
     * If the words ends CVD, where C is a non-vowel, D is a non-vowel
     * other than I, and V is double a, e, o or u, remove one of the
     * vowels from V (for example, moom -> mon, weed -> wed).
     *
     * @param string $word the string to check for the CVD combination
     * @return string the string with the CVD combination removed otherwise
     * the original string
     */
    private static function step4($word)
    {
        $result = $word;
        $word_split = preg_split('/(?!^)(?=.)/u', $word);
        $numberOfLetters = count($word_split);
        if ($numberOfLetters > 3) {
            $c = $word_split[$numberOfLetters - 4];
            $v1 = $word_split[$numberOfLetters - 3];
            $v2 = $word_split[$numberOfLetters - 2];
            $d = $word_split[$numberOfLetters - 1];
            if (!self::isVowel($c) &&
                self::isVowel($v1) &&
                self::isVowel($v2) &&
                !self::isVowel($d) &&
                $v1 == $v2 &&
                $d != 'I' &&
                $v1 != 'i') {
                unset($word_split[$numberOfLetters - 2]);
                $result = implode('', $word_split);
            }
        }
        return $result;
    }
    /**
     * Replace a string based on a regex expression
     *
     * @param string $word the string to search for regex replacement
     * @param string $reges the regex to use to find and replacement
     * @param string $replace the string to replace if the pattern is matched
     * @param int $offset the int to start to look for the regex replacement
     * @return string the string with the characters replaced if the regex
     * matches, otherwise the original string
     */
    private static function replace($word, $regex, $replace, $offset)
    {
        $result = "";
        if ($offset > 0) {
            $part1 = substr($word, 0, $offset);
            $part2 = substr($word, $offset, strlen($word));
            $part2 = preg_replace($regex, $replace, $part2);
            $result = $part1 . "" . $part2;
        } else {
            $result = preg_replace($regex, $replace, $word);
        }
        return $result;
    }
    /**
     * Checks to see if a string ends with a certain string
     *
     * @param string $haystack the string to check
     * @param string $needle the string to match at the end
     * @param bool $case whether the check should be case insensitive or not
     * @return boolean true if it ends with $needle, otherwise false
     */
    private static function endsWith($haystack, $needle, $case = true)
    {
        if ($case) {
            return (strcmp(substr($haystack,
                strlen($haystack) - strlen($needle)), $needle) === 0);
        }
        return (strcasecmp(substr($haystack,
            strlen($haystack) - strlen($needle)), $needle) === 0);
    }
    /**
     * undoubles the end of a string.  If the string ends in kk, tt, dd remove
     * one of the characters
     *
     * @param string $word the string to undouble
     * @return string the undoubled string, otherwise the original string
     */
    private static function undouble($word)
    {
        $result = $word;
        if (self::endsWith($word, "kk") ||
            self::endsWith($word, "tt") ||
            self::endsWith($word, "dd")) {
            $result = substr($word, 0, strlen($word) - 1);
        }
        return $result;
    }
}
