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
namespace seekquarry\yioop\locale\fa\resources;

/**
 * Persian specific tokenization code. In particular, it has a stemmer,
 * The stemmer is a modified variant (handling prefixes slightly differently)
 * of my stab at porting Nick Patch's Perl port,
 * https://metacpan.org/pod/Lingua::Stem::UniNE::FA, of the
 * stemming algorithm by Ljiljana Dolamic and Jacques
 * Savoy of the University of Neuchâtel. The Java version of this is at
 * http://members.unine.ch/jacques.savoy/clef/persianStemmerUnicode.txt
 * (beware of Java's handling of Unicode). 
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
            "در", "به", "از", "كه", "مي", "اين", "است", "را", "با", "هاي",
            "براي", "آن", "يك", "شود", "شده","خود", "ها", "كرد", "شد", "اي",
            "تا", "كند", "بر", "بود", "گفت", "نيز", "وي", "هم", "كنند",
            "دارد", "ما", "كرده", "يا", "اما", "بايد", "دو", "اند", "هر",
            "خواهد", "او", "مورد", "آنها", "باشد", "ديگر", "مردم", "نمي",
            "بين", "پيش", "پس", "اگر", "همه", "صورت", "يكي", "هستند",
            "بي", "من", "دهد", "هزار", "نيست", "استفاده", "داد", "داشته",
            "راه", "داشت", "چه", "همچنين", "كردند", "داده", "بوده",
            "دارند", "همين", "ميليون", "سوي", "شوند", "بيشتر", "بسيار",
            "روي", "گرفته", "هايي", "تواند", "اول", "نام", "هيچ", "چند",
            "جديد", "بيش", "شدن", "كردن", "كنيم", "نشان", "حتي", "اينكه",
            "ولی", "توسط", "چنين", "برخي", "نه", "ديروز", "دوم",
            "درباره", "بعد", "مختلف", "گيرد", "شما", "گفته", "آنان",
            "بار", "طور", "گرفت", "دهند", "گذاري", "بسياري", "طي",
            "بودند", "ميليارد", "بدون", "تمام", "كل", "تر",
            "براساس", "شدند", "ترين", "امروز", "باشند", "ندارد",
            "چون", "قابل", "گويد", "ديگري", "همان", "خواهند",
            "قبل", "آمده", "اكنون", "تحت", "طريق", "گيري", "جاي",
            "هنوز", "چرا", "البته", "كنيد", "سازي", "سوم", "كنم",
            "بلكه", "زير", "توانند", "ضمن", "فقط", "بودن", "حق",
            "آيد", "وقتي", "اش", "يابد", "نخستين", "مقابل", "خدمات",
            "امسال", "تاكنون", "مانند", "تازه", "آورد", "فكر",
            "آنچه", "نخست", "نشده", "شايد", "چهار", "جريان",
            "پنج", "ساخته", "زيرا", "نزديك", "برداري", "كسي",
            "ريزي", "رفت", "گردد", "مثل", "آمد", "ام", "بهترين",
            "دانست", "كمتر", "دادن", "تمامي", "جلوگيري",
            "بيشتري", "ايم", "ناشي", "چيزي", "آنكه", "بالا",
            "بنابراين", "ايشان", "بعضي", "دادند", "داشتند",
            "برخوردار", "نخواهد", "هنگام", "نبايد", "غير", "نبود",
            "ديده", "وگو", "داريم", "چگونه", "بندي", "خواست", "فوق", "ده",
            "نوعي", "هستيم", "ديگران", "همچنان", "سراسر", "ندارند",
            "گروهي", "سعي", "روزهاي", "آنجا", "يكديگر", "كردم",
            "بيست", "بروز", "سپس", "رفته", "آورده", "نمايد",
            "باشيم", "گويند", "زياد", "خويش", "همواره", "گذاشته",
            "شش", "نداشته", "شناسي", "خواهيم", "آباد", "داشتن",
            "نظير", "همچون", "باره", "نكرده", "شان", "سابق",
            "هفت", "دانند", "جايي", "بی", "جز", "زیرِ", "رویِ",
            "سریِ", "تویِ", "جلویِ", "پیشِ", "عقبِ", "بالایِ",
            "خارجِ", "وسطِ", "بیرونِ", "سویِ", "کنارِ", "پاعینِ",
            "نزدِ", "نزدیکِ","دنبالِ", "حدودِ", "برابرِ", "طبقِ",
            "مانندِ", "ضدِّ", "هنگامِ", "برایِ", "مثلِ", "بارة",
            "اثرِ", "تولِ", "علّتِ", "سمتِ", "عنوانِ", "قصدِ",
            "روب", "جدا", "کی", "که", "چیست", "هست", "کجا", "کجاست",
            "کَی", "چطور", "کدام", "آیا", "مگر", "چندین",
            "یک", "چیزی", "دیگر", "کسی", "بعری", "هیچ", "چیز",
            "جا", "کس", "هرگز", "یا", "تنها", "بلکه", "خیاه",
            "بله", "بلی", "آره", "آری", "مرسی", "البتّه",
            "لطفاً", "ّه", "انکه",
            "وقتیکه", "همین", "پیش", "مدّتی", "هنگامی", "مان", "تان"
            ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/u', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of a Persian word
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
        $word = self::simplifyPrefix($word);
        $word = self::removeKasra($word);
        $word = self::removeSuffix($word);
        $word = self::removeKasra($word);
        return $word;
    }
    /**
     * Simplifies prefixes beginning with آ to ا
     * @param string $word word to remove mark from
     * @return string result of removal
     */
    private static function simplifyPrefix($word)
    {
        if(mb_strlen($word) < 5) {
            return $word;
        }
        $word = preg_replace('/^ا/u', "آ", $word);
        return $word;
    }
    /**
     * Removes a Kasra diacritic mark if appears
     * at the end of a word.
     * @param string $word word to remove mark from
     * @return string result of removal
     */
    private static function removeKasra($word)
    {
        if(mb_strlen($word) < 5) {
            return $word;
        }
        $kasra = json_decode('"\u0650"');
        $word = preg_replace('/'.$kasra.'$/u', "", $word);
        return $word;
    }
    /**
     * Removes common Persian suffixes
     *
     * @param string $word to remove suffixes from
     * @return string result of suffix removal
     */
    private static function removeSuffix($word)
    {
        $length = mb_strlen($word);
        if ($length > 7) {
            $modified_word = preg_replace("/(?:
                آباد | باره | بندی | بندي | ترین | ترين | ریزی |
                ريزي | سازی | سازي | گیری | گيري | هایی | هايي
                ) $/xu", "", $word);
            if($modified_word != $word) {
                return $modified_word;
            }
        }
        if ($length > 6) {
            $modified_word = preg_replace("/(?:
                    اند | ایم | ايم | شان | های | هاي
                ) $/xu", "", $word);
            if($modified_word != $word) {
                return $modified_word;
            }
        }
        if ($length > 5) {
            $modified_word = preg_replace("/ ان $/xu", "", $word);
            if($modified_word != $word) {
                return self::normalize($word);
            }
            $modified_word = preg_replace("/(?:
                    ات | اش | ام | تر | را | ون | ها | هء | ین | ين
                ) $/xu", "", $word);
            if($modified_word != $word) {
                return $modified_word;
            }
        }
        if ($length > 3) {
            $modified_word = preg_replace("/(?: ت | ش | م | ه | ی | ي ) $/xu",
                "", $word);
            if($modified_word != $word) {
                return $modified_word;
            }
        }
        return $word;
    }
    /**
     * Performs additional end word stripping
     *
     * @param string $word to remove suffixes from
     * @return string result of suffix removal
     */
    private static function normalize($word)
    {
        $length = mb_strlen($word);
        if($length < 4) {
            return $word;
        }
        $modified_word = preg_replace("/(?: ت | ر | ش | گ | م | ى ) $/xu", "",
            $word);
        if($modified_word != $word) {
            $word = $modified_word;
            if(mb_strlen($word) < 4) {
                return $word;
            }
            $word = preg_replace("/(?: ی | ي ) $/xu", "", $word);
        }
        return $word;
    }
}

