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
namespace seekquarry\yioop\locale\ru\resources;

/**
 * This class has a collection of methods for Russian locale specific
 * tokenization. In particular, it has a stemmer, a stop word remover (for
 * use mainly in word cloud creation). The stemmer is a modification
 * (with bug fixes ) of  Dennis Kreminsky's stemmer from:
 * http://snowball.tartarus.org/otherlangs/russian_php5.txt
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
     * Num bytes of Russian unicode char.
     */
    const CHAR_LENGTH = 2;
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
        $stop_words = ["й", "ч", "чп", "ое", "юфп",
            "по", "об", "с", "у", "уп", "лбл", "б", "фп",
            "чуе", "поб", "фбл", "езп", "оп", "дб", "фщ",
            "л", "х", "це", "чщ", "ъб", "вщ", "рп",
            "фпмшлп", "ее", "ное", "вщмп", "чпф", "пф",
            "неос", "еэе", "оеф ", "п", "йъ", "енх",
            "феретш", "лпздб", "дбце", "ох ", "чдтхз",
            "мй", "еумй", "хце", "ймй", "ой", "вщфш",
            "вщм", "оезп", "дп", "чбу", "ойвхдш",
            "прсфш", "хц", "чбн", "улбъбм", "чедш",
            "фбн", "рпфпн", "уевс", "ойюезп", "ек",
            "нпцеф", "пой", "фхф", "зде", "еуфш",
            "обдп", "оек", "дмс", "нщ", "февс",
            "йи", "юен", "вщмб", "убн", "юфпв", "веъ ",
            "вхдфп", "юемпчел", "юезп", "тбъ", "фпце",
            "уеве", "рпд", "цйъош", "вхдеф", "ц",
            "фпздб", "лфп", "ьфпф", "зпчптйм", "фпзп",
            "рпфпнх", "ьфпзп", "лблпк", "упчуен",
            "ойн", "ъдеуш", "ьфпн", "пдйо",
            "рпюфй", "нпк", "фен", "юфпвщ", "оее",
            "лбцефус", "уекюбу", "вщмй", "лхдб",
            "ъбюен", "улбъбфш", "чуеи", "ойлпздб",
            "уезпдос", "нпцоп", "ртй", "облпоег",
            "дчб", "пв", "дтхзпк", "ипфш", "рпуме",
            "обд", "впмшые", "фпф", "юетеъ", "ьфй",
            "обу", "ртп", "чуезп",  "ойи", "лблбс",
            "нопзп", "тбъче", "улбъбмб", "фтй",
            "ьфх", "нпс", "чртпюен", "иптпып",
            "учпа", "ьфпк", "ретед",  "йопздб",
            "мхюые", "юхфш", "фпн", "оемшъс",
            "фблпк", "йн", "впмее", "чуездб",
            "лпоеюоп", "чуа", "нецдх", 'http', 'https'
        ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/u', '',
            strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of a Russian word
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        $a = self::rv($word);
        $start = $a[0];
        $rv = $a[1];
        $rv = self::step1($rv);
        $rv = self::step2($rv);
        $rv = self::step3($rv);
        $rv = self::step4($rv);
        return $start . $rv;
    }
    /**
     * Compute the RV region of a word. RV is the region after the first vowel,
     * or the end of the word if it contains no vowel.
     *
     * @param string $word word to compute rv regions for
     * @return array pair string before rv, string after rv
     */
    private static function rv($word)
    {

        $vowel = 'аеиоуыэюя';
        $start = $word;
        $rv = '';
        preg_match("/[$vowel]/u", $word , $matches,
            PREG_OFFSET_CAPTURE);
        $len = strlen($word);
        $rv_index = isset($matches[0][1]) ? $matches[0][1] +
            self::CHAR_LENGTH : $len;
        if ($rv_index != $len) {
            $start = substr($word, 0, $rv_index);
            $rv = substr($word, $rv_index);
        }
        return [$start, $rv];
    }
    /**
     * Search for a PERFECTIVE GERUND ending. If one is found remove it,
     * and that is then the end of step 1. Otherwise try and remove a REFLEXIVE
     * ending, and then search in turn for (1) an ADJECTIVAL, (2) a VERB or
     * (3) a NOUN ending.
     * As soon as one of the endings (1) to (3) is found remove it, and
     * terminate step 1.
     * @param string $word word to stem
     * @return string $word after step
     */
    private static function step1($word)
    {
        $perfective1 = ['в', 'вши', 'вшись'];
        foreach ($perfective1 as $suffix) {
            $len = strlen($suffix);
            if (substr($word, -$len) == $suffix &&
                (substr($word, -$len - self::CHAR_LENGTH,
                    self::CHAR_LENGTH) == 'а' ||
                substr($word, -$len - self::CHAR_LENGTH,
                    self::CHAR_LENGTH) == 'я')) {
                return substr($word, 0, -$len);
            }
        }
        $perfective2 = ['ив', 'ивши', 'ившись', 'ыв',
            'ывши', 'ывшись'];
        foreach ($perfective2 as $suffix) {
            $len = strlen($suffix);
            if (substr($word, -$len) == $suffix) {
                return substr($word, 0, -$len);
            }
        }
        $reflexive = ['ся', 'сь'];
        foreach ($reflexive as $suffix) {
            $len = strlen($suffix);
            if (substr($word, -$len) == $suffix) {
                $word = substr($word, 0, -$len);
            }
        }
        $adjective = ['ее', 'ие', 'ые', 'ое', 'ими',
            'ыми', 'ей', 'ий', 'ый', 'ой', 'ем', 'им', 'ым',
            'ом', 'его', 'ого', 'ему', 'ому', 'их', 'ых',
            'ую', 'юю', 'ая', 'яя', 'ою', 'ею'];
        $participle1 = ['ем', 'нн', 'вш',
            'ющ', 'щ'];
        $participle2 = ['ивш', 'ывш',
            'ующ'];
        foreach ($adjective as $adj_suffix) {
            $len = strlen($adj_suffix);
            if (substr($word, -$len) != $adj_suffix) {
                continue;
            }
            $word = substr($word, 0, -$len);
            foreach ($participle1 as $suffix) {
                $len = strlen($suffix);
                if (substr($word, -$len) == $suffix &&
                    (substr($word, -$len - self::CHAR_LENGTH,
                        self::CHAR_LENGTH) =='а' ||
                    substr($word, -$len - self::CHAR_LENGTH,
                        self::CHAR_LENGTH) =='я')) {
                    $word = substr($word, 0, -$len);
                }
            }
            foreach ($participle2 as $suffix) {
                $len = strlen($suffix);
                if (substr($word, -$len) == $suffix) {
                    $word = substr($word, 0, -$len);
                }
            }
            return $word;
        }
        $verb1 = ['ла','на','ете','йте','ли','й',
            'л','ем','н','ло','но', 'ет','ют','ны',
            'ть','ешь','нно'];
        foreach ($verb1 as $suffix) {
            $len = strlen($suffix);
            if (substr($word,-$len) == $suffix &&
                (substr($word,-$len - self::CHAR_LENGTH,
                    self::CHAR_LENGTH) == 'а' ||
                substr($word, -$len - self::CHAR_LENGTH,
                    self::CHAR_LENGTH) == 'я')) {
                return substr($word, 0, -$len);
            }
        }
        $verb2 = ['ила', 'ыла', 'ена', 'ейте',
            'уйте', 'ите', 'или', 'ыли', 'ей', 'уй',
            'ил', 'ыл', 'им', 'ым', 'ен', 'ило', 'ыло',
            'ено', 'ят', 'ует', 'уют', 'ит', 'ыт',
            'ены', 'ить', 'ыть', 'ишь', 'ую', 'ю'];
        foreach ($verb2 as $suffix) {
            $len = strlen($suffix);
            if (substr($word, -$len) == $suffix) {
                return substr($word, 0, -$len);
            }
        }
        $noun = ['а', 'ев', 'ов', 'ие', 'ье', 'е',
            'иями', 'ями', 'ами', 'еи', 'ии', 'и',
            'ией', 'ей', 'ой', 'ий', 'й', 'иям', 'ям',
            'ием', 'ем', 'ам', 'ом', 'о', 'у','ах', 'иях',
            'ях', 'ы', 'ь', 'ию', 'ью', 'ю', 'ия', 'ья', 'я'];
        foreach ($noun as $suffix) {
            $len = strlen($suffix);
            if (substr($word, -$len) == $suffix) {
                return substr($word, 0, -$len);
            }
        }
        return $word;
    }
    /**
     * If the word ends with и (i), remove it.
     * @param string $word word to stem
     * @return string $word after step
     */
    private static function step2($word)
    {
        if (substr($word, -self::CHAR_LENGTH, self::CHAR_LENGTH) == 'и') {
            $word = substr($word, 0, -self::CHAR_LENGTH);
        }
        return $word;
    }
    /**
     * Search for a DERIVATIONAL ending in R2 (i.e. the entire ending must
     * lie in R2), and if one is found, remove it.
     * @param string $word word to stem
     * @return string $word after step
     */
    private static function step3($word)
    {
        $vowel = 'аеиоуыэюя';
        $r1 = '';
        $r2 = '';
        preg_match("/[^$vowel]/u", $word , $matches,
            PREG_OFFSET_CAPTURE);
        $len = strlen($word);
        $r1_index = isset($matches[0][1]) ? $matches[0][1] +
            self::CHAR_LENGTH : $len;
        if ($r1_index != $len) {
            $r1 = substr($word , $r1_index);
        }
        if ($r1_index != $len) {
            preg_match("/[$vowel][^$vowel]/u", $r1, $matches,
                PREG_OFFSET_CAPTURE);
            $r2_index = isset($matches[0][1]) ? $matches[0][1] +
                2 * self::CHAR_LENGTH : $len;
            if ($r2_index != $len) {
                $r2 = substr($r1, $r2_index);
            }
        }
        $derivational = ['ост', 'ость'];
        foreach ($derivational as $suffix) {
            $len = strlen($suffix);
            if (substr($r2, -$len) == $suffix)
                $word  = substr($word, 0, -$len);
        }
        return $word;
    }
    /**
     * 1) Undouble н (n), or, (2) if the word ends with a SUPERLATIVE ending,
     * remove it and undouble н (n), or (3) if the word ends ь (') (soft sign)
     * remove it.
     * @param string $word word to stem
     * @return string $word after step
     */
    private static function step4($word)
    {
        if (substr($word, -self::CHAR_LENGTH * 2) == 'нн') {
            $word = substr($word, 0,  -self::CHAR_LENGTH);
        } else {
            $superlative=array('ейш', 'ейше');
            foreach ($superlative as $suffix) {
                $len = strlen($suffix);
                if (substr($word, -$len) == $suffix) {
                    $word = substr($word, 0, - $len);
                }
            }
            if (substr($word, -self::CHAR_LENGTH * 2) == 'нн') {
                $word = substr($word, 0, -self::CHAR_LENGTH);
            }
        }
        /* should there be a guard flag? can't think of a russian word
         that ends with ейшь or ннь anyways, though the algorithm states
         this is an "otherwise" case
        */
        if (substr($word, -self::CHAR_LENGTH, self::CHAR_LENGTH) == 'ь') {
            $word = substr($word, 0, strlen($word) - self::CHAR_LENGTH);
        }
        return $word;
    }
}
