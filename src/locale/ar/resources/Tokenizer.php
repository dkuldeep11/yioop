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
namespace seekquarry\yioop\locale\ar\resources;

/**
 * Arabic specific tokenization code. In particular, it has a stemmer,
 * The stemmer is my stab at porting Ljiljana Dolamic (University of Neuchatel,
 * www.unine.ch/info/clef/) C stemming algorithm:
 * http://members.unine.ch/jacques.savoy/clef
 * That algorithm maps all stems to ASCII. Instead, I tried to leave everything
 * using Arabic characters.
 *
 * @author Chris Pollett
 */
class Tokenizer
{
    /**
     * Words we don't want to be stemmed
     * @var array
     */
    public static $no_stem_list = [];
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
        $stop_words = [
            "ا", "أ", "،", "عشر", "عدد", "عدة","عشرة",
            "عدم", "عام", "عاما", "عن", "عند", "عندما",
            "على", "عليه", "عليها", "زيارة", "سنة", "سنوات",
            "تم", "ضد", "بعد", "بعض", "اعادة", "اعلنت",
            "بسبب", "حتى", "اذا", "احد", "اثر", "برس",
            "باسم", "غدا", "شخصا", "صباح", "اطار",
            "اربعة", "اخرى", "بان", "اجل", "غير",
            "بشكل", "حاليا", "بن", "به", "ثم", "اف",
            "ان", "او", "اي", "بها", "صفر", "حيث",
            "اكد", "الا", "اما", "امس", "السابق",
            "التى", "التي", "اكثر", "ايار", "ايضا",
            "ثلاثة", "الذاتي", "الاخيرة", "الثاني",
            "الثانية", "الذى", "الذي", "الان", "امام",
            "ايام", "خلال", "حوالى", "الذين", "الاول",
            "الاولى", "بين", "ذلك", "دون", "حول", "حين",
            "الف", "الى", "انه", "اول", "ضمن", "انها",
            "جميع", "الماضي", "الوقت", "المقبل", "اليوم",
            "ـ", "ف", "و", "و6", "قد", "لا", "ما", "مع",
            "مساء", "هذا", "واحد", "واضاف", "واضافت",
            "فان", "قبل", "قال", "كان", "لدى", "نحو",
            "هذه", "وان", "واكد", "كانت", "واوضح",
            "مايو", "فى", "في", "كل", "لم", "لن", "له",
            "من", "هو", "هي", "قوة", "كما", "لها", "منذ",
            "وقد", "ولا", "نفسه", "لقاء", "مقابل", "هناك",
            "وقال", "وكان", "نهاية", "وقالت", "وكانت",
            "للامم", "فيه", "كلم", "لكن", "وفي", "وقف",
            "ولم", "ومن", "وهو", "وهي", "يوم", "فيها",
            "منها", "مليار", "لوكالة", "يكون", "يمكن",
            "مليون"
        ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/u', '',
            $page);
        return $page;
    }
    /**
     * Computes the stem of an Arabic word
     *
     * @param string $word the string to stem
     * @return string the stem of $word
     */
    public static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }
        $word = mb_strtolower($word);
        $word = self::removeModifiersAndArchaic($word);
        $word = self::removeSuffix($word);
        $word = self::removePrefix($word);
        return $word;
    }
    /**
     * Removes common letter modifiers as well as some archaic characters
     * @param string $word
     * @return string the $word after letter modifiers removed
     */
    private static function removeModifiersAndArchaic($word)
    {
        $modifiers = ["\u0621", "\u0640", "\u064B", "\u064C", "\u064D",
            "\u064E", "\u064F", "\u0650", "\u0651", "\u0652", "\u0653",
            "\u0654", "\u0655", "\u0656", "\u0674", "\u0677", "\u0679"];
        foreach($modifiers as $modifier) {
            $m = json_decode('"'.$modifier.'"');
            $word = preg_replace("/".$m."/u", "", $word);
        }
        return $word;
    }
    /**
     * Removes Arabic suffixes to get root
     *
     * @param string $word word to remove suffixes from
     * @return string the $word after suffix removal
     */
    private static function removeSuffix($word)
    {
        $length = mb_strlen($word);
        if ($length > 5) {
            $last_two = mb_substr($word, -2);
            if(in_array($last_two,["ون", "آه", "أه", "آت", "أت", "آن",
                "أن", "ىة", "ىه", "ىن", "ةئ", "ئن", "ئه"])) {
                $word = mb_substr($word, 0, -2);
                return $word;
            }
        }
        if ($length > 4) {
            $last_one = mb_substr($word, -1);
            if(in_array($last_one, ["ى", "ۃ", "ه", "ئ"])) {
                $word = mb_substr($word, 0, -1);
                return $word;
            }
        }
        return $word;
    }
    /**
     * Removes Arabic prefixes to get root
     * @param string $word word to remove prefixes from
     *
     * @return string the $word after prefix removal
     */
    private static function removePrefix($word)
    {
        $length = mb_strlen($word);
        if ($length > 6) {
            $start_three = mb_substr($word, 0, 3);
            if(in_array($start_three, ["ڡآل", "ۀآل", "بآل", "وآل",
                "ڡأل", "ۀأل", "بأل", "وأل"])) {
                $word = mb_substr($word, 3);
                return $word;
            }
        }
        if ($length > 5) {
            $start_two = mb_substr($word, 0, 2);
            if(in_array($start_two, ["آل", "أل"])) {
                $word = mb_substr($word, 2);
                return $word;
            }
        }
        if ($length > 4) {
            $start_one = mb_substr($word, 0, 1);
            if(in_array($start_one, ["و", "ى", "ٸ"])) {
                $word = mb_substr($word, 1);
                return $word;
            }
        }
        return $word;
    }
}
