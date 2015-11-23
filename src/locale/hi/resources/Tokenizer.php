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
namespace seekquarry\yioop\locale\hi\resources;

/**
 * Hindi specific tokenization code. In particular, it has a stemmer,
 * The stemmer is my stab at porting Ljiljana Dolamic (University of Neuchatel,
 * www.unine.ch/info/clef/) Java stemming algorithm:
 * http://members.unine.ch/jacques.savoy/clef/HindiStemmerLight.java.txt
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
            "पर ", "इन ", "वह ", "यिह ", "वुह ", "जिन्हें", "जिन्हों",
            "तिन्हें", "तिन्हों", "किन्हों", "किन्हें", "इत्यादि", "द्वारा",
            "इन्हें", "इन्हों", "उन्हों", "बिलकुल", "निहायत", "ऱ्वासा",
            "इन्हीं", "उन्हीं", "उन्हें", "इसमें", "जितना", "दुसरा",
            "कितना", "दबारा", "साबुत", "वग़ैरह", "दूसरे", "कौनसा", "लेकिन",
            "होता", "करने", "किया", "लिये", "अपने", "नहीं", "दिया", "इसका",
            "करना", "वाले", "सकते", "इसके", "सबसे", "होने", "करते", "बहुत",
            "वर्ग", "करें", "होती", "अपनी", "उनके", "कहते", "होते", "करता",
            "उनकी", "इसकी", "सकता", "रखें", "अपना", "उसके", "जिसे",
            "तिसे", "किसे", "किसी", "काफ़ी", "पहले", "नीचे", "बाला", "यहाँ",
            "जैसा", "जैसे", "मानो", "अंदर", "भीतर", "पूरा", "सारा", "होना",
            "उनको", "वहाँ", "वहीं", "जहाँ", "जीधर","उनका", "इनका", "﻿के",
            "हैं", "गया", "बनी", "एवं", "हुआ", "साथ", "बाद", "लिए", "कुछ",
            "कहा", "यदि", "हुई", "इसे", "हुए", "अभी", "सभी", "कुल", "रहा",
            "रहे", "इसी", "उसे", "जिस", "जिन", "तिस", "तिन", "कौन", "किस",
            "कोई", "ऐसे", "तरह", "किर", "साभ", "संग", "यही", "बही", "उसी",
            "फिर", "मगर", "का", "एक", "यह", "से", "को", "इस", "कि", "जो",
            "कर", "मे", "ने", "तो", "ही", "या", "हो", "था", "तक", "आप", "ये",
            "थे", "दो", "वे", "थी", "जा", "ना", "उस", "एस", "पे", "उन", "सो",
            "भी", "और", "घर", "तब", "जब", "अत", "व", "न"
        ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/u', '',
            $page);
        return $page;
    }
    /**
     * Computes the stem of an Hindi word
     *
     * @param string $word the string to stem
     * @return string the stem of $word
     */
    public static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }
        $word = self::removeSuffix($word);
        return $word;
    }
    /**
     * Removes common Hindi suffixes
     *
     * @param string $word to remove suffixes from
     * @return string result of suffix removal
     */
    private static function removeSuffix($word)
    {
        $length = mb_strlen($word);
        if ($length > 5) {
            $last_three = mb_substr($word, -3);
            if (in_array($last_three, ["िया", "ियो"])) {
                $word = mb_substr($word, 0, -3);
                return $word;
            }
        }
        if ($length > 4) {
            $last_two = mb_substr($word, -2);
            if (in_array($last_two, ["ाए", " ाओ", " ुआ", " ुओ",
                "ये", " ेन", " ेण", " ीय", "टी", "ार", "ाई"])) {
                $word = mb_substr($word, 0, -2);
                return $word;
            }
        }
        if ($length > 3) {
            $last_one = mb_substr($word, -1);
             if (in_array($last_one, [" ा", " े", " ी", " ो", "ि ",
                "अ"])) {
                $word = mb_substr($word, 0, -1);
                return $word;
            }
        }
        return $word;
    }

}
