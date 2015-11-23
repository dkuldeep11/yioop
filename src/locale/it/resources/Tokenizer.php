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
namespace seekquarry\yioop\locale\it\resources;

/**
 * Italian specific tokenization code. Typically, tokenizer.php
 * either contains a stemmer for the language in question or
 * it specifies how many characters in a char gram
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
     * Storage used in computing the stem
     * @var string
     */
    private static $buffer;
    /**
     * Storage used in computing the starting index of region R1
     * @var int
     */
    private static $r1_start;
    /**
     * Storage used in computing the starting index of region R2
     * @var int
     */
    private static $r2_start;
    /**
     * Storage used in computing the starting index of region RV
     * @var int
     */
    private static $rv_start;
    /**
     * Storage used in computing region R1
     * @var string
     */
    private static $r1_string;
    /**
     * Storage used in computing region R2
     * @var string
     */
    private static $r2_string;
    /**
     * Storage used in computing Region RV
     * @var string
     */
    private static $rv_string;
    /**
     * Storage for computing the starting position for the longest suffix
     * @var int
     */
    private static $max_suffix_pos;
    /**
     * Storage used in determinig if step1 removed any endings from the word
     * @var bool
     */
    private static $step1_changes;
    /**
     * This method currently does nothing. For some locales it could
     * used to split strings of the form "thisisastring" into a string
     * with the words seperated: "this is a string"
     *
     * @param string $pre_segment string to be segmented
     * @return string after segmentation done (same string in this case)
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
            'http', 'https',
            "ad", "al", "allo", "ai", "agli", "all", "agl", "alla",
            "alle", "con", "col", "coi", "da", "dal", "dallo", "dai",
            "dagli", "dall", "dagl", "dalla", "dalle", "di", "del",
            "dello", "dei", "degli", "dell", "degl", "della", "delle",
            "in", "nel", "nello", "nei", "negli", "nell", "negl", "nella",
            "nelle", "su", "sul", "sullo", "sui", "sugli", "sull", "sugl",
            "sulla", "sulle", "per", "tra", "contro", "io", "tu", "lui",
            "lei", "noi", "voi", "loro", "mio", "mia", "miei", "mie", "tuo",
            "tua", "tuoi", "tue", "suo", "sua", "suoi", "sue", "nostro",
            "nostra", "nostri", "nostre", "vostro", "vostra", "vostri",
            "vostre", "mi", "ti", "ci", "vi", "lo", "la", "li", "le", "gli",
            "ne", "il", "un", "uno", "una", "ma", "ed", "se", "perché",
            "anche", "come", "dov", "dove", "che", "chi", "cui", "non", "più",
            "quale", "quanto", "quanti", "quanta", "quante", "quello", "quelli",
            "quella", "quelle", "questo", "questi", "questa", "queste", "si",
            "tutto", "tutti", "a", "c", "e", "i", "l", "o", "ho", "hai", "ha",
            "abbiamo", "avete", "hanno", "abbia", "abbiate", "abbiano", "avrò",
            "avrai", "avrà", "avremo", "avrete", "avranno", "avrei", "avresti",
            "avrebbe", "avremmo", "avreste", "avrebbero", "avevo", "avevi",
            "aveva", "avevamo", "avevate", "avevano", "ebbi", "avesti", "ebbe",
            "avemmo", "aveste", "ebbero", "avessi", "avesse", "avessimo",
            "avessero", "avendo", "avuto", "avuta", "avuti", "avute", "sono",
            "sei", "è", "siamo", "siete", "sia", "siate", "siano", "sarò",
            "sarai", "sarà", "saremo", "sarete", "saranno", "sarei", "saresti",
            "sarebbe", "saremmo", "sareste", "sarebbero", "ero", "eri", "era",
            "eravamo", "eravate", "erano", "fui", "fosti", "fu", "fummo",
            "foste", "furono", "fossi", "fosse", "fossimo", "fossero",
            "essendo", "faccio", "fai", "facciamo", "fanno", "faccia",
            "facciate", "facciano", "farò", "farai", "farà", "faremo",
            "farete", "faranno", "farei", "faresti", "farebbe", "faremmo",
            "fareste", "farebbero", "facevo", "facevi", "faceva", "facevamo",
            "facevate", "facevano", "feci", "facesti", "fece", "facemmo",
            "faceste", "fecero", "facessi", "facesse", "facessimo", "facessero",
            "facendo", "sto", "stai", "sta", "stiamo", "stanno", "stia",
            "stiate", "stiano", "starò", "starai", "starà", "staremo",
            "starete", "staranno", "starei", "staresti", "starebbe", "staremmo",
            "stareste", "starebbero", "stavo", "stavi", "stava", "stavamo",
            "stavate", "stavano", "stetti", "stesti", "stette", "stemmo",
            "steste", "stettero", "stessi", "stesse", "stessimo", "stessero",
            "stando"
        ];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Computes the stem of an Italian word
     * Example guardando,guardandogli,guardandola,guardano all stem to guard
     *
     * @param string $word is the word to be stemmed
     * @return string stem of $word
     */
    public static function stem($word)
    {
        self::$buffer = $word;
        self::$step1_changes = false;
        self::$max_suffix_pos = -1;
        self::prelude();
        self::step0();
        self::step1();
        self::step2();
        self::step3a();
        self::step3b();
        self::postlude();
        return self::$buffer;
    }
    /**
     * Checks if a string is a suffix for another string
     *
     * @param $parent_string is the string in which we wish to find the suffix
     * @param $substring is the suffix we wish to check
     * @return $pos as the starting position of the suffix $substring in
     * $parent_string if it exists, else false
     */
    private static function checkForSuffix($parent_string,$substring)
    {
        $pos = strrpos($parent_string,$substring);
        if ($pos !== false &&
           (strlen($parent_string) - $pos == strlen($substring)))
            return $pos;
        else
            return false;
    }
    /**
     * Checks if a string occurs in another string
     *
     * @param $string is the parent string
     * @param $substring is the string checked to be a sub-string of $string
     * @return bool if $substring is a substring of $string
     */
    private static function in($string, $substring)
    {
        $pos = strpos($string, $substring);
        if ($pos !== false) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * Computes the starting index for region R1
     *
     * @param $string is the string for which we wish to find the index
     * @return $r1_start as the starting index for R1 for $string
     */
    private static function r1($string)
    {
       $r1_start = -1;
       for ($i = 0; $i < strlen($string); $i++){
           if ($i >= 1){
               if (self::isVowel($string[$i - 1]) &&
                  !self::isVowel($string[$i])){
                   $r1_start = $i + 1;
                    break;
                }
            }
        }
        if ($r1_start != -1){
            if ($r1_start > strlen($string) - 1)
                $r1_start = -1;
            else{
                if ($string[$r1_start] == "`")
                    $r1_start += 1;
            }
        }
        return $r1_start;
    }
    /**
     * Computes the starting index for region R2
     *
     * @param $string is the string for which we wish to find the index
     * @return $r2_start as the starting index for R1 for $string
     */
    private static function r2($string)
    {
        $r2_start = -1;
        $r1_start = self::r1($string);
        if ($r1_start !== -1) {
            $len = strlen($string);
            for ($i = $r1_start; $i < $len; $i++) {
                if ($i >= $r1_start + 1){
                    if (self::isVowel($string[$i - 1]) &&
                       !self::isVowel($string[$i])) {
                        $r2_start = $i + 1;
                        break;
                    }
                }
            }
        }
        if ($r2_start != -1){
            if ($r2_start > strlen($string) - 1) {
                $r2_start = -1;
            } else {
                if ($string[$r2_start] == "`") {
                    $r2_start += 1;
                }
            }
        }

        return $r2_start;
    }
    /**
     * Computes the starting index for region RV
     *
     * @param $string is the string for which we wish to find the index
     * @return $rv_start as the starting index for RV for $string
     */
    private static function rv($string)
    {
        $i = 0;
        $j = 1;
        $rv_start = -1;
        $length = strlen($string);
        if ($length <= 2)
            $rv_start = -1;
        else{
            if (self::isVowel($string[$j])){
                if (self::isVowel($string[$i])){
                    for ($k = $j + 1; $k < $length; $k++){
                        if (!self::isVowel($string[$k])){
                            $rv_start = $k + 1;
                            break;
                        }
                    }
                } else {
                    $rv_start = 3;
                }
            } else {
                for ($k = $j + 1; $k < $length; $k++){
                    if (self::isVowel($string[$k])){
                        $rv_start = $k + 1;
                        break;
                    }
                }
            }
        }
        if ($rv_start != -1){
            if ($rv_start >= $length)
                $rv_start = -1;
            else{
                if ($string[$rv_start] == "`")
                    $rv_start += 1;
            }
        }
        return $rv_start;
    }
    /**
     * Computes regions R1, R2 and RV in the form
     * strings. $r1_string, $r2_string, $r3_string for R1,R2 and R3
     * repectively
     */
    private static function getRegions()
    {
        if ((self::$r1_start = self::r1(self::$buffer)) != -1) {
            self::$r1_string = substr(self::$buffer,self::$r1_start);
        } else {
            self::$r1_string = null;
        }
        if ((self::$r2_start = self::r2(self::$buffer)) != -1) {
            self::$r2_string = substr(self::$buffer,self::$r2_start);
        } else {
            self::$r2_string = null;
        }
        if ((self::$rv_start = self::rv(self::$buffer)) != -1) {
            self::$rv_string = substr(self::$buffer,self::$rv_start);
        } else {
            self::$rv_string = null;
        }
    }
    /**
     * Checks if a character is a vowel or not
     *
     * @param $char is the character to be checked
     * @return bool if $char is a vowel
     */
    private static function isVowel($char)
    {
        switch ($char)
        {
            case "U":
                // no break
            case "I":
            case "`":
                return false;
            case "a":
                // no break
            case "e":
            case "i":
            case "o":
            case "u":
                return true;
        }
    }
    /**
     * Computes the longest suffix for a given string from a given set of
     * suffixes
     *
     * @param $string is the for which the maximum suffix is to be found
     * @param $suffixes is an array of suffixes
     * @return $max_suffix is the longest suffix for $string
     */
    private static function maxSuffix($string, $suffixes)
    {
        $max_length = 0;
        $max_suffix = null;
        foreach ($suffixes as $suffix){
            $pos = strrpos($string,$suffix);
            if ($pos !== false){
                $string_tail = substr($string,$pos);
                $suffix_length = strlen($suffix);
                if (!strcmp($string_tail, $suffix)){
                    if ($suffix_length > $max_length){
                        $max_suffix = $suffix;
                        $max_length = $suffix_length;
                        self::$max_suffix_pos = $pos;
                    }
                }
            }
        }
        return $max_suffix;
    }
    /**
     * Replaces all acute accents in a string by grave accents and also handles
     * accented characters
     *
     * @param $string is the string from in which the acute accents are to be
     * replaced
     * @return $string with changes
     */
    private static function acuteByGrave($string)
    {
        $pattern2 = ["/á/","/é/","/ó/","/ú/","/è/",
            "/ì/","/ò/","/ù/", "/à/","/í/"];
        $replacement = ["a`","e`","o`","u`","e`",
            "i`","o`","u`","a`","i`"];
        $string = preg_replace($pattern2,$replacement,$string);
        return($string);
    }
    /**
     * Performs the following functions:
     * Replaces acute accents with grave accents
     * Marks u after q and u,i preceded and followed by a vowel as a non vowel
     * by converting to upper case
     */
    private static function prelude()
    {
        $pattern_array = ["/Qu/","/qu/"];
        $replacement_array = ["QU","qU"];
        //Replace acute accents by grave accents
        self::$buffer = self::acuteByGrave(self::$buffer);
        /**
         * Convert u preceded by q and u,i preceded and followed by vowels
         * to upper case to mark them as non vowels
         */
        self::$buffer = preg_replace($pattern_array,$replacement_array,
                       self::$buffer);
        for ($i = 0; $i < strlen(self::$buffer) - 1; $i++){
            if ($i >= 1 && (self::$buffer[$i] == "i" ||
                           self::$buffer[$i] == "u")){
                if (self::isVowel(self::$buffer[$i-1]) &&
                   self::isVowel(self::$buffer[$i+1]) &&
                   self::$buffer[$i+1] !== '`')
                    self::$buffer[$i] = strtoupper(self::$buffer[$i]);
            }
        }
    }
    /**
     * Handles attached pronoun
     */
    private static function step0()
    {
        $max = 0;
        $suffixes = ["ci","gli","la","le","li","lo",
            "mi","ne","si","ti","vi","sene",
            "gliela","gliele","glieli","glielo",
            "gliene","mela","mele","meli","melo",
            "mene","tela","tele","teli","telo",
            "tene","cela","cele","celi","celo",
            "cene","vela","vele","veli","velo","vene"];
        $phrases = ["ando","endo","ar","er","ir"];
        //Get R1, R2, RV
        self::getRegions();
        //Find the maximum length suffix in the string
        $max_suffix = self::maxSuffix(self::$rv_string,$suffixes);
        if ($max_suffix != null){
            $sub_string = substr(self::$rv_string,0,-strlen($max_suffix));
            foreach ($phrases as $phrase){
                if (self::checkForSuffix($sub_string,$phrase) !== false){
                    switch ($phrase) {
                        case "ando": case "endo":
                            self::$buffer = substr_replace(self::$buffer,"",
                                self::$rv_start + self::$max_suffix_pos,
                                strlen($max_suffix));
                            break;
                        case "ar": case "er": case "ir":
                            self::$buffer = substr_replace(self::$buffer,"e",
                                self::$rv_start + self::$max_suffix_pos,
                                strlen($max_suffix));
                            break;
                    }
                }
            }
        }
    }
    /**
     * Handles standard suffixes
     */
    private static function step1()
    {
        $suffixes = ["anza","anze","ico","ici","ica",
            "ice","iche","ichi","ismo","ismi",
            "abile","abili","ibile","ibili","ista",
            "iste","isti","ista`","iste`","isti`",
            "oso","osi","osa","ose","mente","atrice",
            "atrici","ante","anti","azione","azioni",
            "atore","atori","logia","logie","uzione",
            "uzioni","usione","usioni","enza","enze",
            "amento","amenti","imento","imenti","amente",
            "ita`","ivo","ivi","iva","ive"];
        //Get R1,R2 and RV
        self::getRegions();
        //Find the longest suffix
        $max_suffix = self::maxSuffix(self::$buffer,$suffixes);
        //Handle suffix according
        switch ($max_suffix) {
            case "anza":
                // no break
            case "anze": case "ico": case "ici": case "ica":
            case "ice": case "iche": case "ichi": case "ismo": case "ismi":
            case "abile": case "abili": case "ibile": case "ibili":
            case "ista": case "iste": case "isti": case "ista`": case "iste`":
            case "isti`": case "oso": case"osi": case "osa": case "ose":
            case "mente": case "atrice": case "atrici": case "ante":
            case "anti":
                //Delete if in R2
                if (self::in(self::$r2_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                break;
            case "azione":
                // no break
            case "azioni": case "atore": case "atori":
                //Delete if in R2
                if (self::in(self::$r2_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                self::getRegions();
                //If preceded by ic, delete if in R2
                if (self::checkForSuffix(self::$buffer,"ic")) {
                    if (self::in(self::$r2_string,"ic")){
                        self::$buffer = str_replace("ic","",self::$buffer);
                        self::$step1_changes = true;
                    }
                }
                break;
            case "logia":
                // no break
            case "logie":
                //Replace with log if in R2
                if (self::in(self::$r2_string,$max_suffix)) {
                    self::$buffer = substr_replace(self::$buffer,"log",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                break;
            case "uzione":
                // no break
            case "uzioni": case "usione": case "usioni":
                //Replace with u if in R2
                if (self::in(self::$r2_string,$max_suffix)) {
                    self::$buffer = substr_replace(self::$buffer,"u",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                break;
            case "enza":
                // no break;
            case "enze":
                //Replace with ente if in R2
                if (self::in(self::$r2_string,$max_suffix)) {
                    self::$buffer = substr_replace(self::$buffer,"ente",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                break;
            case "amento":
                // no break
            case "amenti": case "imento": case "imenti":
                //Delete if in RV
                if (self::in(self::$rv_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                break;
            case "amente":
                //Delete if in R1
                if (self::in(self::$r1_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                                    self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                self::getRegions();
                //Check if preceded by iv, if yes, delete if in R2
                if (self::checkForSuffix(self::$buffer,"iv")){
                    if (self::in(self::$r2_string,"iv")){
                        self::$buffer = str_replace("iv","",self::$buffer);
                        self::$step1_changes = true;
                        if (self::checkForSuffix(self::$buffer,"at")){
                            if (self::in(self::$r2_string,"at")){
                                self::$buffer = str_replace("at","",
                                    self::$buffer);
                                self::$step1_changes = true;
                            }
                        }
                    }
                } else {
                    /**
                     * Otherwise check if preceded by os,ic or abil, if yes,
                     * delete if in r2
                     */
                    self::getRegions();
                    $further = ["os","ic","abil"];
                    foreach ($further as $suffix){
                        $pos = self::checkForSuffix(self::$buffer,$suffix);
                        if ($pos !== false){
                            if (self::in(self::$r2_string,$suffix)){
                                self::$buffer = substr_replace(self::$buffer,
                                    "",$pos);
                                self::$step1_changes = true;
                            }
                        }
                    }
                }
                break;
            case "ita`":
                //Delete if in R2
                if (self::in(self::$r2_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                //If further preceded by abil,ic or iv, delete if in R2
                self::getRegions();
                $further = ["abil","ic","iv"];
                foreach ($further as $suffix){
                    if (self::checkForSuffix(self::$buffer,$suffix)){
                        if (self::in(self::$r2_string,$suffix)){
                            self::$buffer = str_replace($suffix,"",
                                self::$buffer);
                            self::$step1_changes = true;
                        }
                    }
                }
                // no break
            case "ivo":
                // no break
            case "ivi": case "iva": case "ive":
                //Delete if in R2
                if (self::in(self::$r2_string,$max_suffix)){
                    self::$buffer = substr_replace(self::$buffer,"",
                        self::$max_suffix_pos,strlen($max_suffix));
                    self::$step1_changes = true;
                }
                //If preceded by at, delete if in R2
                self::getRegions();
                $pos = self::checkForSuffix(self::$buffer,"at");
                if ($pos !== false){
                    if (self::in(self::$r2_string,"at")){
                        self::$buffer = substr_replace(self::$buffer,"",$pos,2);
                        self::$step1_changes = true;
                        //If further preceded by ic, delete if in R2
                        self::getRegions();
                        $pos = self::checkForSuffix(self::$buffer,"ic");
                        if ($pos !== false){
                            if (self::in(self::$r2_string,"ic")){
                                self::$buffer = substr_replace(self::$buffer,"",
                                    $pos,2);
                                self::$step1_changes = true;
                            }
                        }
                    }
                }
        }
    }
    /**
     * Handles verb suffixes
     */
    private static function step2()
    {
        $verb_suffixes = ["ammo","ando","ano","are","arono","asse",
            "assero","assi","assimo","ata","ate","ati",
            "ato","ava","avamo","avano","avate","avi","avo",
            "emmo","enda","ende","endi","endo","era`",
            "erai","eranno","ere","erebbe","erebbero",
            "erei","eremmo","eremo","ereste","eresti",
            "erete","ero`","erono","essero","ete","eva",
            "evamo","evano","evate","evi","evo","Yamo",
            "iamo","immo","ira`","irai","iranno","ire",
            "irebbe","irebbero","irei","iremmo","iremo",
            "ireste","iresti","irete","iro`","irono","isca",
            "iscano","isce","isci","isco","iscono","issero",
            "ita","ite","iti","ito","iva","ivamo","ivano",
            "ivate","ivi","ivo","ono","uta","ute","uti",
            "uto","ar","ir"];
        /**
         * If no ending was removed in step1, find the longest suffix from the
         * above suffixes and delete if in RV
         */
        if (!self::$step1_changes){
            //Get R1,R2 and RV
            self::getRegions();

            $max_suffix = self::maxSuffix(self::$rv_string,$verb_suffixes);
            if (self::in(self::$rv_string,$max_suffix))
                self::$buffer = substr_replace(self::$buffer,"",
                    self::$rv_start + self::$max_suffix_pos,
                    strlen($max_suffix));
        }
    }
    /**
     * Deletes a final a,e,i,o,a`,e`,i`,o` and a preceding i if in RV
     */
    private static function step3a()
    {
        $vowels = array ("a","e","i","o","a`","e`","i`","o`");

        //Get R1,R2 and RV
        self::getRegions();

        //If a character from the above is found in RV, delete it
        foreach ($vowels as $character) {
            $pos = self::checkForSuffix(self::$buffer,$character);
            if ($pos !== false){
                if (self::in(self::$rv_string,$character)){
                    self::$buffer = substr_replace(self::$buffer, "", $pos,
                        strlen($character));
                    break;
                }
            }
        }
        //If preceded by i, delete if in RV
        self::getRegions();
        $pos = self::checkForSuffix(self::$buffer,"i");
        if ($pos !== false){
            if (self::in(self::$rv_string,"i"))
                self::$buffer = substr_replace(self::$buffer,"",$pos,1);
        }
    }
    /**
     * Replaces a final ch/gh by c/g if in RV
     */
    private static function step3b()
    {
        //Get R1,R2 and RV
        self::getRegions();
        //Replace final ch/gh with c/g if in RV
        $patterns = ["ch","gh"];
        foreach ($patterns as $pattern) {
            switch ($pattern) {
                case "ch":
                    $pos = self::checkForSuffix(self::$buffer,$pattern);
                    if ($pos !== false){
                        if (self::in(self::$rv_string,$pattern))
                            self::$buffer = substr_replace(self::$buffer,
                                "c",$pos);
                    }
                    break;
                case "gh":
                    $pos = self::checkForSuffix(self::$buffer,$pattern);
                    if ($pos !== false){
                        if (self::in(self::$rv_string,$pattern))
                            self::$buffer = substr_replace(self::$buffer,
                                "g",$pos);
                    }
                    break;
            }
        }
    }
    /**
     * Converts U and/or I back to lowercase
     */
    private static function postlude()
    {
        $pattern_array_1 = ["/U/","/I/"];
        $replacement_array_1 = ["u","i"];
        $pattern_array_2 = ["/a`/","/e`/","/i`/","/o`/","/u`/"];
        $replacement_array_2 = ["à","è","ì","ò","ù"];
        self::$buffer = preg_replace($pattern_array_1, $replacement_array_1,
            self::$buffer);
        self::$buffer = preg_replace($pattern_array_2, $replacement_array_2,
            self::$buffer);
    }
}
