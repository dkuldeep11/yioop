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
namespace seekquarry\yioop\locale\es\resources;

use seekquarry\yioop\library as L;

/**
 * Spanish specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram
 *
 * This class has a collection of methods for Spanish locale specific
 * tokenization. In particular, it has a stemmer, a stop word remover (for
 * use mainly in word cloud creation). The stemmer is my stab at re-implementing
 * the stemmer algorithm given at http://snowball.tartarus.org
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
     * Spanish vowels
     * @var string
     */
    private static $vowel = 'aeiouáéíóúü';
    /**
     * Storage used in computing the stem
     * @var string
     */
    private static $buffer;
    /**
     * $rv is approximately the string after the first vowel in the $word we
     * want to stem
     * @var string
     */
    private static $rv;
    /**
     * Position in $word to stem of $rv
     * @var int
     */
    private static $rv_index;
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
        $stop_words = ["de", "la", "que", "el","en", "y", "a", "los",
            "del", "se", "las", "por", "un", "para", "con", "no", "una",
            "su", "al", "lo", "como", "más", "pero", "sus", "le", "ya", "o",
            "este", "sí", "porque", "esta", "entre", "cuando", "muy", "sin",
            "sobre", "también", "me", "hasta",
            "hay", "donde", "quien", "desde",
            "todo", "nos", "durante", "todos", "uno", "les", "ni", "contra",
            "otros", "ese", "eso", "ante", "ellos", "e", "esto", "mí", "antes",
            "algunos", "qué", "unos", "yo", "otro", "otras", "otra", "él",
            "tanto", "esa", "estos", "mucho", "quienes", "nada", "muchos",
            "cual", "poco", "ella", "estar", "estas", "algunas", "algo",
            "nosotros", "mi", "mis", "tú", "te", "ti", "tu", "tus", "ellas",
            "nosotras", "vosotros", "vosotras", "os", "mío", "mía", "míos",
            "mías", "tuyo", "tuya", "tuyos", "tuyas", "suyo", "suya", "suyos",
            "suyas", "nuestro", "nuestra", "nuestros", "nuestras", "vuestro",
            "vuestra", "vuestros", "vuestras", "esos", "esas", "estoy",
            "estás", "está", "estamos", "estáis",
            "están", "esté", "estés",
            "estemos", "estéis", "estén", "estaré", "estarás", "estará",
            "estaremos", "estaréis", "estarán", "estaría", "estarías",
            "estaríamos", "estaríais", "estarían", "estaba", "estabas",
            "estábamos", "estabais", "estaban", "estuve", "estuviste",
            "estuvo", "estuvimos", "estuvisteis", "estuvieron", "estuviera",
            "estuvieras", "estuviéramos", "estuvierais", "estuvieran",
            "estuviese", "estuvieses", "estuviésemos", "estuvieseis",
            "estuviesen", "estando", "estado", "estada", "estados", "estadas",
            "estad", "he", "has", "ha", "hemos", "habéis", "han", "haya",
            "hayas", "hayamos", "hayáis", "hayan",
            "habré", "habrás", "habrá",
            "habremos", "habréis", "habrán",
            "habría", "habrías", "habríamos",
            "habríais", "habrían", "había",
            "habías", "habíamos", "habíais", 'http', 'https',
            "habían", "hube", "hubiste", "hubo", "hubimos", "hubisteis",
            "hubieron", "hubiera", "hubieras", "hubiéramos", "hubierais",
            "hubieran", "hubiese", "hubieses", "hubiésemos", "hubieseis",
            "hubiesen", "habiendo", "habido", "habida", "habidos", "habidas",
            "soy", "eres", "es", "somos", "sois", "son", "sea", "seas",
            "seamos", "seáis", "sean", "seré", "serás", "será", "seremos",
            "seréis", "serán", "sería", "serías", "seríamos", "seríais",
            "serían", "era", "eras", "éramos",
            "erais", "eran", "fui", "fuiste",
            "fue", "fuimos", "fuisteis", "fueron", "fuera", "fueras",
            "fuéramos", "fuerais", "fueran", "fuese", "fueses", "fuésemos",
            "fueseis", "fuesen", "siendo", "sido", "sed", "tengo", "tienes",
            "tiene", "tenemos", "tenéis", "tienen", "tenga", "tengas",
            "tengamos", "tengáis", "tengan", "tendré", "tendrás", "tendrá",
            "tendremos", "tendréis", "tendrán", "tendría", "tendrías",
            "tendríamos", "tendríais", "tendrían", "tenía", "tenías",
            "teníamos", "teníais", "tenían", "tuve", "tuviste", "tuvo",
            "tuvimos", "tuvisteis", "tuvieron", "tuviera", "tuvieras",
            "tuviéramos", "tuvierais", "tuvieran", "tuviese", "tuvieses",
            "tuviésemos", "tuvieseis", "tuviesen", "teniendo", "tenido",
            "tenida", "tenidos", "tenidas", "tened"];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of a French word
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }
        self::$buffer = mb_strtolower($word, "UTF-8");
        self::computeRegions();
        self::step0(); // attached pronoun
        $before_step1 = self::$buffer;
        self::step1(); //suffix removal
        if ($before_step1 == self::$buffer) {
            self::step2a(); //verb suffixes beginning with y
            if ($before_step1 == self::$buffer) {
                self::step2b(); //other verb suffixes
            }
        }
        self::step3();
        self::removeAccents();
        return self::$buffer;
    }
    /**
     * This computes the three regions of the word rv, r1, and r2 used in the
     * rest of the stemmer
     * $rv is defined as follows: If the second letter is a consonant,
     *   $rv is the region after the next following vowel, or if the first two
     *   letters are vowels, RV is the region after the next consonant,
     *   and otherwise (consonant-vowel case) RV is the region after the third
     *   letter. But RV is the end of the word if these positions cannot be
     *   found.
     * $r1 is the region after the first non-vowel following a vowel, or the end
     * of the word if there is no such non-vowel.
     * $r2 is the region after the first non-vowel following a vowel in $r1, or
     * the end of the word if there is no such non-vowel
     */
    private static function computeRegions()
    {
        $word = self::$buffer;
        $vowel = static::$vowel;
        self::$rv_index = -1;
        $start_letters = mb_substr($word, 0, 2, 'UTF-8');
        $second_letter = mb_substr($word, 1, 1, 'UTF-8');
        $len_start = strlen($start_letters);
        if (($loc = L\preg_search("/[^$vowel]/u", $second_letter)) != -1) {
            self::$rv_index = L\preg_search("/[$vowel]/", $word, $len_start);
        } else if (($loc = L\preg_search("/^[$vowel]{2}/u", $word)) != -1) {
            $tmp = strlen(mb_substr($word, 0, 2));
            $loc += $tmp;
            self::$rv_index = max(L\preg_search("/[^$vowel]/u", $word, $loc),
                $tmp);
        } else {
            if (strlen($word) >= 3) {
                self::$rv_index = strlen(mb_substr($word, 0, 2, "UTF-8"));
            }
        }
        preg_match("/[$vowel][^$vowel]/u", $word, $matches,
            PREG_OFFSET_CAPTURE);
        self::$r1 = "";
        $len = strlen($word);
        self::$r1_index = isset($matches[0][1]) ? $matches[0][1] +
            strlen(mb_substr($word,$matches[0][1], 2, 'UTF-8')) : $len;
        if (self::$r1_index != $len) {
            self::$r1 = substr($word, self::$r1_index);
        }
        if (self::$r1_index != $len) {
            preg_match("/[$vowel][^$vowel]/u", self::$r1, $matches,
                PREG_OFFSET_CAPTURE);
            self::$r2_index = isset($matches[0][1]) ? $matches[0][1] +
                strlen(mb_substr(self::$r1, $matches[0][1], 2, 'UTF-8')) : $len;
            if (self::$r2_index != $len) {
                self::$r2 = substr(self::$r1, self::$r2_index);
                self::$r2_index += self::$r1_index;
            }
        }
        if (self::$r1_index != $len && self::$r1_index < 3) {
            self::$r1_index = 3;
            self::$r1 = substr($word, 3);
        }
    }
    /**
     * Remove attached pronouns
     */
    private static function step0()
    {
        $word = self::$buffer;
        $rv_index = self::$rv_index;
        $first_char_len = max(strlen(mb_substr(substr($word, $rv_index), 0, 1,
            "UTF-8")), 1);
        $end_pattern =
            '(me|se|sela|selo|selas|selos|la|le|lo|las|les|los|nos)$/u';
        $start = "/(iéndo|ándo|ár|ér|ír)";
        $new_word = L\preg_offset_replace($start . $end_pattern, '$1', $word,
            $rv_index + $first_char_len);
        if ($new_word != $word) {
            $word = preg_replace(array('/iéndo$/u', '/ándo$/u', '/ár$/u',
                '/ér$/u', '/ír$/u'), ['iendo', 'ando', 'ar', 'er', 'ir'],
                $new_word);
        } else {
            $start = "/(iendo|ando|ar|er|ir)";
            $word = L\preg_offset_replace($start . $end_pattern, '$1',
                $word, $rv_index + $first_char_len);
            $start = "/uyendo";
            $word = L\preg_offset_replace($start . $end_pattern, '$1',
                $word, $rv_index + $first_char_len);
        }
        self::$buffer = $word;
    }
    /**
     * Standard suffix removal
     */
    private static function step1()
    {
        $word = self::$buffer;
        $rv_index = self::$rv_index;
        $r1_index = self::$r1_index;
        $r2_index = self::$r2_index;
        $r2_char_len = strlen(mb_substr($word, $r2_index, 1, "UTF-8"));
        $r1_char_len = strlen(mb_substr($word, $r1_index, 1, "UTF-8"));
        if (L\preg_search('/amente$/u', $word, $r2_index + $r2_char_len) != -1){
            $word = L\preg_offset_replace('/((((at)?iv)?)|'.
                '(oc|ic|ad)?)amente$/u', '', $word, $r1_index + $r1_char_len);
            if ($word == self::$buffer) {
                $word = preg_replace('/amente$/u', '', $word);
            }
        } else if (L\preg_search('/amente$/u', $word, $r1_index) != -1) {
            $word = preg_replace('/amente$/u', '', $word);
        } else {
            $word = L\preg_offset_replace('/logía(s)?$/u', 'log', $word,
                $r2_index);
            $word = L\preg_offset_replace('/(ución|uciones)$/u', 'u', $word,
                $r2_index);
            $word = L\preg_offset_replace('/(encia|encias)$/u', 'ente', $word,
                $r2_index);
            if ($word == self::$buffer) {
                $patterns = [
                    '/(anza|anzas|ico|ica|icos|icas|ismo|ismos|able|'.
                    'ables|ible|ibles|ista|istas|oso|osa|osos|osas|amiento|'.
                    'amientos|imiento|imientos)$/u',
                    '/(ic)?(adora|ador|ación|adoras|'.
                    'adores|aciones|ante|antes|'.
                    'ancia|ancias)$/u',
                    '/(ante|able|ible)?mente$/u',
                    '/(abil|ic|iv)?(idad|idades)$/u',
                    '/(at)?(iva|ivo|ivas|ivos)$/u'
                ];
                $original = $word;
                foreach ($patterns as $pattern) {
                    $word = L\preg_offset_replace($pattern, '', $word, $r2_index);
                    if ($word != $original) {break; }
                }
            }
        }
        self::$buffer = $word;
    }
    /**
     * Stem verb suffixes beginning y
     */
    private static function step2a()
    {
        $word = self::$buffer;
        $rv_index = self::$rv_index;
        if (L\preg_search(
            '/u(ya|ye|yan|yen|yeron|yendo|yo|yó|yas|yes|yais|yamos)$/u', $word,
            $rv_index) != -1) {
            self::$buffer = preg_replace(
                '/(ya|ye|yan|yen|yeron|yendo|yo|yó|yas|yes|yais|yamos)$/u', '',
                $word);
        }
    }
    /**
     * Stem other verb suffixes
     */
    private static function step2b()
    {
        $word = self::$buffer;
        $rv_index = self::$rv_index;
        $first_char_len = max(strlen(mb_substr(substr($word, $rv_index), 0, 1,
            "UTF-8")), 1);
        $pattern = '/(aríamos|eríamos|iríamos|'.
        'iéramos|iésemos|aremos|áramos|' .
            'ábamos|ásemos|eremos|iremos|aríais|' .
            'asteis|eríais|arían|arías|' .
            'erían|erías|ierais|ieseis|isteis|' .
            'iríais|irían|irías|aseis|aréis|'.
            'abais|arais|eréis|íamos|iendo|ieran|' .
            'ieras|ieses|iréis|ieron|iesen' .
            '|aban|abas|adas|ados|amos|ando|aran|' .
            'arán|aras|arás|aron|asen|ases' .
            '|erán|irán|erás|irás|iese|' .
            '(er|ar|ir)?ía|aste|íais|idas|idos|imos'.
            '|iste|iera|áis|ará|aré|erá|eré|ías|' .
            'irá|iré|aba|ada|ado|ara|ase'.
            '|(í)?an|ida|ido|ad|ed|id|ió|ar|er|ir|as|ís)$/u';
        if (L\preg_search($pattern, $word, $rv_index + $first_char_len) != -1 ){
            $word = L\preg_offset_replace($pattern, '', $word, $rv_index +
                $first_char_len);
        } else if (L\preg_search('/gu(en|es|éis|emos)$/u', $word, $rv_index -
            $first_char_len)
            != -1) {
            $word = preg_replace('/u(en|es|éis|emos)$/u', '', $word);
        } else if (L\preg_search('/(en|es|éis|emos)$/u', $word, $rv_index +
            $first_char_len)  != -1){
            $word = preg_replace('/(en|es|éis|emos)$/u', '', $word);
        }
        self::$buffer = $word;
    }
    /**
     * Delete residual suffixes
     */
    private static function step3()
    {
        $word = self::$buffer;
        $rv_index = self::$rv_index;
        $first_char_len = max(strlen(mb_substr(substr($word, $rv_index), 0, 1,
            "UTF-8")), 1);
        if (L\preg_search('/(os|a|o|á|í|ó)$/u', $word, $rv_index
            + $first_char_len) != -1) {
            $word = L\preg_offset_replace('/(os|a|o|á|í|ó)$/u', '', $word,
                $rv_index + $first_char_len);
        } else if (($loc = L\preg_search('/gu(e|é)$/u', $word)) != -1 &&
            $loc >= $rv_index - 1) {
            $word = preg_replace('/u(e|é)$/u', '', $word);
        } else if (($loc = L\preg_search('/(e|é)$/u', $word, $rv_index +
            $first_char_len)) !=-1){
            $word = preg_replace('/(e|é)$/u', '', $word);
        }
        self::$buffer = $word;
    }
    /**
     * Un-accent end
     */
    private static function removeAccents()
    {
        $vowel = static::$vowel;
        self::$buffer = preg_replace(array('/á/u', '/é/u',
            '/í/u', '/ó/u', '/ú/u'), ['a','e','i', 'o','u'],
            self::$buffer);
    }
}
