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
namespace seekquarry\yioop\locale\en_US\resources;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\PhraseParser;

/* If you would like to use wordnet for thesaurus reordering of query results
   define the following variable in your configs/local_config.php file with
   the path to the WordNet executable.
 */
if (!C\nsdefined("WORDNET_EXEC")) {
    C\nsdefine("WORDNET_EXEC", "");
}
/**
 * This class has a collection of methods for English locale specific
 * tokenization. In particular, it has a stemmer, a stop word remover (for
 * use mainly in word cloud creation), and a part of speech tagger (if
 * thesaurus reordering used). The stemmer is my stab at implementing the
 * Porter Stemmer algorithm
 * presented http://tartarus.org/~martin/PorterStemmer/def.txt
 * The code is based on the non-thread safe C version given by Martin Porter.
 * Since PHP is single-threaded this should be okay.
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
    public static $no_stem_list = ["titanic", "programming", "fishing", 'ins',
        "blues", "factorial", "pbs"];
    /**
     * Phrases we would like yioop to rewrite before performing a query
     * @var array
     */
    public static $semantic_rewrites = [
        "ins" => 'uscis',
        "mimetype" => 'mime',
        "military" => 'armed forces',
        'full metal alchemist' => 'fullmetal alchemist',
        'bruce schnier' => 'bruce schneier',
    ];
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
     * Index to start of the suffix of the word being considered for
     * manipulation
     * @var int
     */
    private static $j;
    /**
     * The constructor for a tokenizer can be used to say that a thesaurus
     * for final query reordering is present. For english we do this if
     * the WORDNET_EXEC variable is set. In which case we use WordNet for
     * our reordering
     */
    public function __construct()
    {
        if (C\WORDNET_EXEC != "") {
            $this->use_thesaurus = true;
        }
    }
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
     * Computes similar words and scores from WordNet output based on word type.
     *
     * @param string $term term to find related thesaurus terms
     * @param string $word_type is the type of word such as "NN" (noun),
     *     "VB" (verb), "AJ" (adjective), or "AV" (adverb)
     *     (all other types will be ignored)
     * @param string $whole_query the original query $term came from
     * @return array a sequence of
     *     (score => array of thesaurus terms) associations. The score
     *     representing one word sense of term
     */
    public static function scoredThesaurusMatches($term, $word_type,
        $whole_query)
    {
        $word_map = ["VB" => "verb", "NN" => "noun", "AJ" => "adj",
            "AV" => "adv"];
        //Gets overview of senses of term[$i] into data
        exec(C\WORDNET_EXEC . " $term -over", $data);
        if (!$data || ! isset($word_map[$word_type])) { return null; }
        $full_name = $word_map[$word_type];
        $lexicon_output = implode("\n", $data);
        $sense_parts = preg_split("/\bThe\s$full_name".'[^\n]*\n\n/',
            $lexicon_output);
        if (!isset($sense_parts[1])) {return null; }
        list($sense, ) = preg_split("/\bOverview\sof\s/", $sense_parts[1]);
        $definitions_for_sense = preg_split("/\d+\.\s/", $sense, -1,
            PREG_SPLIT_NO_EMPTY);
        $num_definitions = count($definitions_for_sense);
        $sentence = [];
        $similar_phrases = [];
        $avg_scores = [];
        for ($i = 0; $i < $num_definitions; $i++) {
            //get sentence fragments examples of using that definition
            preg_match_all('/\"(.*?)\"/', $definitions_for_sense[$i], $matches);
            // to separate out the words
            preg_match('/[\w+\s\,\.\']+\s\-+/', $definitions_for_sense[$i],
                $match_word);
            $thesaurus_phrases = preg_split("/\s*\,\s*/",
                strtolower(rtrim(trim($match_word[0]), "-")));
            //remove ori ginal term from thesaurus phrases if present
            $m = 0;
            foreach ($thesaurus_phrases as $thesaurus_phrase) {
                $tphrase = trim($thesaurus_phrase);
                if ($tphrase == trim($term)) {
                    unset($thesaurus_phrases[$m]);
                }
                $m++;
            }
            $thesaurus_phrases = array_filter($thesaurus_phrases);
            if ($thesaurus_phrases == []) {continue;}
            $num_example_sentences = count($matches[1]);
            $score = [];
            for ($j = 0; $j < $num_example_sentences; $j++) {
                $query_parts = explode(' ', strtolower($whole_query));
                $example_sentence_parts = explode(' ',
                    strtolower($matches[1][$j]));
                $score[$j] = PhraseParser::getCosineRank($query_parts,
                    $example_sentence_parts);
                /*  If Cosine similarity is zero then go for
                 * intersection similarity ranking
                 */
                if ($score[$j] == 0) {
                    $score[$j] = PhraseParser::getIntersection($query_parts,
                        $example_sentence_parts);
                }
            }
            /*  We use the rounded average of the above times 100 as a score
                score for a definition. To avoid ties we store in the low
                order digits 99 - the definition it was
             */
            if ($num_example_sentences > 0) {
                $definition_score = 100 * round(
                    100 * (array_sum($score) / $num_example_sentences))
                    + (99 - $i);
            } else {
                $definition_score = 99 - $i;
            }
            $similar_phrases[$definition_score] = $thesaurus_phrases;
        }
        krsort($similar_phrases);
        return $similar_phrases;
    }
    /**
     * Removes the stop words from the page (used for Word Cloud generation)
     *
     * @param string $page the page to remove stop words from.
     * @return string $page with no stop words
     */
    public static function stopwordsRemover($page)
    {
        $stop_words = ['a','able','about','above','abst',
        'accordance','according','based','accordingly','across','act',
        'actually','added','adj','affected','affecting','affects','after',
        'afterwards','again','against','ah','all','almost','alone','along',
        'already','also','although','always','am','among','amongst','an','and',
        'announce','another','any','anybody','anyhow','anymore','anyone',
        'anything','anyway','anyways','anywhere','apparently','approximately',
        'are','aren','arent','arise','around','as','aside','ask','asking','at',
        'auth','available','away','awfully','b','back','be','became','because',
        'become','becomes','becoming','been','before','beforehand','begin',
        'beginning','beginnings','begins','behind','being','believe','below',
        'beside','besides','between','beyond','biol','both','brief','briefly',
        'but','by','c','ca','came','can','cannot','cant','cause','causes',
        'certain','certainly','co','com','come','comes','contain','containing',
        'contains','could','couldnt','d','date','did','didnt',
        'different','do','does','doesnt','doing',
        'done','dont','down','downwards',
        'due','during','e','each','ed','edu','effect','eg','eight','eighty',
        'either','else','elsewhere','end',
        'ending','enough','especially','et',
        'et-al','etc','even','ever','every',
        'everybody','everyone','everything'
        ,'everywhere','ex','except','f','far','few','ff','fifth','first',
        'five','fix','followed','following','follows','for','former',
        'formerly','forth','found','four','from','further','furthermore',
        'g','gave','get','gets','getting','give','given','gives','giving','go',
        'goes','gone','got','gotten','h','had','happens','hardly','has','hasnt',
        'have','havent','having','he','hed','hence','her','here','hereafter',
        'hereby','herein','heres','hereupon','hers','herself','hes','hi','hid',
        'him','himself','his','hither','home','how','howbeit',
        'however', 'http', 'https', 'hundred','i','id','ie','if','ill',
        'im','immediate','immediately',
        'importance','important','in','inc','indeed','index','information',
        'instead','into','invention','inward','is','isnt','it','itd','itll',
        'its','itself','ive','j','just','k','keep','keeps',
        'kept','kg','km','know',
        'known','knows','l','largely','last','lately',
        'later','latter','latterly',
        'least','less','lest','let','lets','like','liked','likely','line',
        'little','ll','look','looking','looks','ltd','m','made','mainly','make',
        'makes','many','may','maybe','me','mean','means','meantime','meanwhile',
        'merely','mg','might','million','miss','ml','more','moreover','most',
        'mostly','mr','mrs','much','mug','must','my','myself','n','na','name',
        'namely','nay','nd','near','nearly','necessarily','necessary','need',
        'needs','neither','never','nevertheless','new','next',
        'nine','ninety','no',
        'nobody','non','none','nonetheless','noone',
        'nor','normally','nos','not',
        'noted','nothing','now','nowhere','o','obtain',
        'obtained','obviously','of',
        'off','often','oh','ok','okay','old','omitted','on','once','one','ones',
        'only','onto','or','ord','other','others',
        'otherwise','ought','our','ours',
        'ourselves','out','outside','over','overall','owing','own','p','page',
        'pages','part','particular','particularly',
        'past','per','perhaps','placed',
        'please','plus','poorly','possible','possibly','potentially','pp',
        'predominantly','present','previously',
        'primarily','probably','promptly',
        'proud','provides','put','q','que','quickly','quite','qv','r','ran',
        'rather','rd','re','readily','really','recent','recently','ref','refs',
        'regarding','regardless','regards','related','relatively','research',
        'respectively','resulted','resulting',
        'results','right','run','s','said',
        'same','saw','say','saying','says','sec',
        'section','see','seeing','seem',
        'seemed','seeming','seems',
        'seen','self','selves','sent','seven','several',
        'shall','she','shed','shell',
        'shes','should','shouldnt','show','showed','shown','showns','shows',
        'significant','significantly','similar','similarly','since',
        'six','slightly',
        'so','some','somebody','somehow','someone','somethan',
        'something','sometime',
        'sometimes','somewhat','somewhere','soon',
        'sorry','specifically','specified',
        'specify','specifying','still','stop','strongly','sub','substantially',
        'successfully','such','sufficiently','suggest','sup','sure','t','take',
        'taken','taking','tell','tends','th','than',
        'thank','thanks','thanx','that',
        'thatll','thats','thatve','the','their',
        'theirs','them','themselves','then',
        'thence','there','thereafter','thereby','thered','therefore','therein',
        'therell','thereof','therere','theres','thereto','thereupon','thereve',
        'these','they','theyd','theyll','theyre',
        'theyve','think','this','those',
        'thou','though','thoughh','thousand','throug',
        'through','throughout','thru',
        'thus','til','tip','to','together','too',
        'took','toward','towards','tried',
        'tries','truly','try','trying','ts','twice','two','u','un','under',
        'unfortunately','unless','unlike','unlikely','until','unto','up','upon',
        'ups','us','use','used','useful','usefully','usefulness','uses','using',
        'usually','v','value','various','ve','very',
        'via','viz','vol','vols','vs',
        'w','want','wants','was','wasnt','way','we',
        'wed','welcome','well','went',
        'were','werent','weve','what','whatever',
        'whatll','whats','when','whence',
        'whenever','where','whereafter','whereas','whereby','wherein','wheres',
        'whereupon','wherever','whether','which','while','whim','whither','who',
        'whod','whoever','whole','wholl','whom','whomever','whos','whose','why',
        'widely','willing','wish','with','within',
        'without','wont','words','world',
        'would','wouldnt','www','x','y','yes','yet','you','youd','youll','your',
        'youre','yours','yourself','yourselves','youve','z','zero'];
        $page = preg_replace('/\b('.implode('|',$stop_words).')\b/', '',
            mb_strtolower($page));
        return $page;
    }
    /**
     * Takes a phrase and tags each term in it with its part of speech.
     * So each term in the original phrase gets mapped to term~part_of_speech
     * This tagger is based on a Brill tagger. It makes uses a lexicon
     * consisting of words from the Brown corpus together with a list of
     * part of speech tags that that word had in the Brown Corpus. These are
     * used to get an initial part of speech (in word was not present than
     * we assume it is a noun). From this a fixed set of rules is used to modify
     * the initial tag if necessary.
     *
     * @param string $phrase text to add parts speech tags to
     * @return string $tagged_phrase phrase where each term has ~part_of_speech
     *     appended
     */
    public static function tagPartsOfSpeechPhrase($phrase)
    {
        preg_match_all("/[\w\d]+/", $phrase, $matches);
        $tokens = $matches[0];
        $tagged_tokens = self::tagTokenizePartOfSpeech($phrase);
        $tagged_phrase  = self::taggedPartOfSpeechTokensToString(
            $tagged_tokens);
        return $tagged_phrase;
    }
    /**
     * Computes the stem of an English word
     *
     * For example, jumps, jumping, jumpy, all have jump as a stem
     *
     * @param string $word the string to stem
     * @return string the stem of $words
     */
    public static function stem($word)
    {
        if (in_array($word, self::$no_stem_list)) {
            return $word;
        }

        self::$buffer = $word;
        self::$k = strlen($word) - 1;
        self::$j = self::$k;
        if (self::$k <= 1) { return $word; }
        self::step1ab();
        self::step1c();
        self::step2();
        self::step3();
        self::step4();
        self::step5();
        return substr(self::$buffer, 0, self::$k + 1);
    }
    /**
     * Checks to see if the ith character in the buffer is a consonant
     *
     * @param int $i the character to check
     * @return if the ith character is a constant
     */
    private static function cons($i)
    {
        switch (self::$buffer[$i]) {
            case 'a':
                // no break
            case 'e':
            case 'i':
            case 'o':
            case 'u':
                return false;
            case 'y':
                return ($i== 0 ) ? true : !self::cons($i - 1);
            default:
                return true;
        }
    }
    //private methods for stemming
    /**
     * m() measures the number of consonant sequences between 0 and j. if c is
     * a consonant sequence and v a vowel sequence, and [.] indicates arbitrary
     * presence,
     * <pre>
     *   [c][v]       gives 0
     *   [c]vc[v]     gives 1
     *   [c]vcvc[v]   gives 2
     *   [c]vcvcvc[v] gives 3
     *   ....
     * </pre>
     */
    private static function m()
    {
        $n = 0;
        $i = 0;
        while(true) {
            if ($i > self::$j) return $n;
            if (!self::cons($i)) break;
            $i++;
        }
        $i++;
        while(true) {
            while(true) {
                if ($i > self::$j) return $n;
                if (self::cons($i)) break;
                $i++;
            }
            $i++;
            $n++;

            while(true)
            {
                if ($i > self::$j) return $n;
                if (!self::cons($i)) break;
                $i++;
            }
            $i++;
        }
    }
    /**
     * Checks if 0,...$j contains a vowel
     *
     * @return bool whether it does not
     */
    private static function vowelinstem()
    {
        for ($i = 0; $i <= self::$j; $i++) {
            if (!self::cons($i)) return true;
        }
        return false;
    }
    /**
     * Checks if $j,($j-1) contain a double consonant.
     *
     * @param int $j position to check in buffer for double consonant
     * @return bool if it does or not
     */
    private static function doublec($j)
    {
        if ($j < 1) { return false; }
        if (self::$buffer[$j] != self::$buffer[$j - 1]) { return false; }
        return self::cons($j);
    }
    /**
     * Checks whether the letters at the indices $i-2, $i-1, $i in the buffer
     * have the form consonant - vowel - consonant and also if the second c is
     * not w,x or y. this is used when trying to restore an e at the end of a
     * short word. e.g.
     *<pre>
     *   cav(e), lov(e), hop(e), crim(e), but
     *   snow, box, tray.
     *</pre>
     * @param int $i position to check in buffer for consonant-vowel-consonant
     * @return bool whether the letters at indices have the given form
     */
    private static function cvc($i)
    {
        if ($i < 2 || !self::cons($i) || self::cons($i - 1) ||
            !self::cons($i - 2)) return false;
        $ch = self::$buffer[$i];
        if ($ch == 'w' || $ch == 'x' || $ch == 'y') return false;
        return true;
    }
    /**
     * Checks if the buffer currently ends with the string $s
     *
     * @param string $s string to use for check
     * @return bool whether buffer currently ends with $s
     */
    private static function ends($s)
    {
        $len = strlen($s);
        $loc = self::$k - $len + 1;
        if ($loc < 0 ||
            substr_compare(self::$buffer, $s, $loc, $len) != 0) {
            return false;
        }
        self::$j = self::$k - $len;
        return true;
    }
    /**
     * setto($s) sets (j+1),...k to the characters in the string $s, readjusting
     * k.
     *
     * @param string $s string to modify the end of buffer with
     */
    private static function setto($s)
    {
        $len = strlen($s);
        $loc = self::$j + 1;
        self::$buffer = substr_replace(self::$buffer, $s, $loc, $len);
        self::$k = self::$j + $len;
    }
    /**
     * Sets the ending in the buffer to $s if the number of consonant sequences
     * between $k and $j is positive.
     *
     * @param string $s what to change the suffix to
     */
    private static function r($s)
    {
        if (self::m() > 0) self::setto($s);
    }

    /** step1ab() gets rid of plurals and -ed or -ing. e.g.
     * <pre>
     *    caresses  ->  caress
     *    ponies    ->  poni
     *    ties      ->  ti
     *    caress    ->  caress
     *    cats      ->  cat
     *
     *    feed      ->  feed
     *    agreed    ->  agree
     *    disabled  ->  disable
     *
     *    matting   ->  mat
     *    mating    ->  mate
     *    meeting   ->  meet
     *    milling   ->  mill
     *    messing   ->  mess
     *
     *    meetings  ->  meet
     * </pre>
     */
    private static function step1ab()
    {
        if (self::$buffer[self::$k] == 's') {
            if (self::ends("sses")) {
                self::$k -= 2;
            } else if (self::ends("ies")) {
                self::setto("i");
            } else if (self::$buffer[self::$k - 1] != 's') {
                self::$k--;
            }
        }
        if (self::ends("eed")) {
            if (self::m() > 0) self::$k--;
        } else if ((self::ends("ed") || self::ends("ing")) &&
            self::vowelinstem()) {
            self::$k = self::$j;
            if (self::ends("at")) {
                self::setto("ate");
            } else if (self::ends("bl")) {
                self::setto("ble");
            } else if (self::ends("iz")) {
                self::setto("ize");
            } else if (self::doublec(self::$k)) {
                self::$k--;
                $ch = self::$buffer[self::$k];
                if ($ch == 'l' || $ch == 's' || $ch == 'z') self::$k++;
            } else if (self::m() == 1 && self::cvc(self::$k)) {
                self::setto("e");
            }
       }
    }
    /**
     * step1c() turns terminal y to i when there is another vowel in the stem.
     */
    private static function step1c()
    {
        if (self::ends("y") && self::vowelinstem()) {
            self::$buffer[self::$k] = 'i';
        }
    }
    /**
     * step2() maps double suffices to single ones. so -ization ( = -ize plus
     * -ation) maps to -ize etc.Note that the string before the suffix must give
     * m() > 0.
     */
    private static function step2()
    {
        if (self::$k < 1) return;
        switch (self::$buffer[self::$k - 1]) {
            case 'a':
                if (self::ends("ational")) { self::r("ate"); break; }
                if (self::ends("tional")) { self::r("tion"); break; }
                break;
            case 'c':
                if (self::ends("enci")) { self::r("ence"); break; }
                if (self::ends("anci")) { self::r("ance"); break; }
                break;
            case 'e':
                if (self::ends("izer")) { self::r("ize"); break; }
                break;
            case 'l':
                if (self::ends("abli")) { self::r("able"); break; }
                if (self::ends("alli")) { self::r("al"); break; }
                if (self::ends("entli")) { self::r("ent"); break; }
                if (self::ends("eli")) { self::r("e"); break; }
                if (self::ends("ousli")) { self::r("ous"); break; }
                break;
            case 'o':
                if (self::ends("ization")) { self::r("ize"); break; }
                if (self::ends("ation")) { self::r("ate"); break; }
                if (self::ends("ator")) { self::r("ate"); break; }
                break;
            case 's':
                if (self::ends("alism")) { self::r("al"); break; }
                if (self::ends("iveness")) { self::r("ive"); break; }
                if (self::ends("fulness")) { self::r("ful"); break; }
                if (self::ends("ousness")) { self::r("ous"); break; }
                break;
            case 't':
                if (self::ends("aliti")) { self::r("al"); break; }
                if (self::ends("iviti")) { self::r("ive"); break; }
                if (self::ends("biliti")) { self::r("ble"); break; }
                break;
        }
    }
    /**
     * step3() deals with -ic-, -full, -ness etc. similar strategy to step2.
     */
    private static function step3()
    {
        switch (self::$buffer[self::$k]) {
            case 'e':
                if (self::ends("icate")) { self::r("ic"); break; }
                if (self::ends("ative")) { self::r(""); break; }
                if (self::ends("alize")) { self::r("al"); break; }
                break;
            case 'i':
                if (self::ends("iciti")) { self::r("ic"); break; }
                break;
            case 'l':
                if (self::ends("ical")) { self::r("ic"); break; }
                if (self::ends("ful")) { self::r(""); break; }
                break;
            case 's':
                if (self::ends("ness")) { self::r(""); break; }
                break;
        }
    }
    /**
     * step4() takes off -ant, -ence etc., in context <c>vcvc<v>.
     */
    private static function step4()
    {
        if (self::$k < 1) { return; }
        switch (self::$buffer[self::$k - 1]) {
            case 'a':
                if (self::ends("al")) { break; }
                return;
            case 'c':
                if (self::ends("ance")) { break; }
                if (self::ends("ence")) { break; }
                return;
            case 'e':
                if (self::ends("er")) break;
                return;
            case 'i':
                if (self::ends("ic")) break;
                return;
            case 'l':
                if (self::ends("able")) break;
                if (self::ends("ible")) break;
                return;
            case 'n':
                if (self::ends("ant")) break;
                if (self::ends("ement")) break;
                if (self::ends("ment")) break;
                if (self::ends("ent")) break;
                return;
            case 'o':
                if (self::ends("ion") && self::$j >= 0 &&
                    (self::$buffer[self::$j] == 's' ||
                    self::$buffer[self::$j] == 't')) break;
                if (self::ends("ou")) break;
                return;
            /* takes care of -ous */
            case 's':
                if (self::ends("ism")) break;
                return;
            case 't':
                if (self::ends("ate")) break;
                if (self::ends("iti")) break;
                    return;
            case 'u':
                if (self::ends("ous")) break;
                return;
            case 'v':
                if (self::ends("ive")) break;
                return;
            case 'z':
                if (self::ends("ize")) break;
                return;
            default:
                return;
        }
        if (self::m() > 1) self::$k = self::$j;
    }
    /** step5() removes a final -e if m() > 1, and changes -ll to -l if
     * m() > 1.
     */
    private static function step5()
    {
        self::$j = self::$k;

        if (self::$buffer[self::$k] == 'e') {
            $a = self::m();
            if ($a > 1 || $a == 1 && !self::cvc(self::$k - 1)) self::$k--;
        }
        if (self::$buffer[self::$k] == 'l' &&
            self::doublec(self::$k) && self::m() > 1) self::$k--;
    }
    //private methods for part of speech tagging
    /**
     * Split input text into terms and output an array with one element
     * per term, that element consisting of array with the term token
     * and the part of speech tag.
     *
     * @param string $text string to tag and tokenize
     * @return array of pairs of the form( "token" => token_for_term,
     *     "tag"=> part_of_speech_tag_for_term) for one each token in $text
     */
    private static function tagTokenizePartOfSpeech($text)
    {
        static $lex_string = null;
        if (!$lex_string) {
            $lex_string = gzdecode(file_get_contents(
                C\LOCALE_DIR . "/en_US/resources/lexicon.txt.gz"));
        }
        preg_match_all("/[\w\d]+/", $text, $matches);
        $tokens = $matches[0];
        $nouns = ['NN', 'NNS', 'NNP'];
        $verbs = ['VBD', 'VBP', 'VB'];
        $result = [];
        $previous = ['token' => -1, 'tag' => -1];
        $previous_token = -1;
        sort($tokens);
        $dictionary = [];
        /*
            Notice we sorted the tokens, and notice how we use $cur_pos
            so only advance forward through $lex_string. So the
            run time of this is bound by at most one scan of $lex_string
         */
        $cur_pos = 0;
        foreach ($tokens as $token) {
            $token = strtolower(rtrim($token, "."));
            $token_pos = stripos($lex_string, "\n".$token." ", $cur_pos);
            if ($token_pos !== false) {
                $token_pos++;
                $cur_pos = stripos($lex_string, "\n", $token_pos);
                $line = trim(substr($lex_string, $token_pos,
                    $cur_pos - $token_pos));
                $tag_list = explode(' ', $line);
                $dictionary[strtolower(rtrim($token, "."))] =
                    array_slice($tag_list, 1);
                $cur_pos++;
            }
        }
        // now using our dictionary we tag
        $i = 0;
        $tag_list = [];
        foreach ($matches[0] as $token) {
            $prev_tag_list = $tag_list;
            $tag_list = [];
            // default to a common noun
            $current = ['token' => $token, 'tag' => 'NN'];
            // remove trailing full stops
            $token = strtolower(rtrim($token, "."));
            if (isset($dictionary[$token])) {
                $tag_list = $dictionary[$token];
                $current['tag'] = $tag_list[0];
            }
            // Converts verbs after 'the' to nouns
            if ($previous['tag'] == 'DT' && in_array($current['tag'], $verbs)) {
                $current['tag'] = 'NN';
            }
            // Convert noun to number if . appears
            if ($current['tag'][0] == 'N' && strpos($token, '.') !== false) {
                $current['tag'] = 'CD';
            }
            $ends_with = substr($token, -2);
            switch ($ends_with) {
                case 'ed':
                    // Convert noun to past particle if ends with 'ed'
                    if ($current['tag'][0] == 'N') { $current['tag'] = 'VBN'; }
                break;
                case 'ly':
                    // Anything that ends 'ly' is an adverb
                    $current['tag'] = 'RB';
                break;
                case 'al':
                    // Common noun to adjective if it ends with al
                    if (in_array($current['tag'], $nouns)) {
                        $current['tag'] = 'JJ';
                    }
                break;
            }
            // Noun to verb if the word before is 'would'
            if ($current['tag'] == 'NN' && $previous_token == 'would') {
                $current['tag'] = 'VB';
            }
            // Convert common noun to gerund
            if (in_array($current['tag'], $nouns) &&
                substr($token, -3) == 'ing') { $current['tag'] = 'VBG'; }
            //nouns followed by adjectives
            if (in_array($previous['tag'], $nouns) &&
                $current['tag'] == 'JJ' && in_array('JJ', $prev_tag_list)) {
                $result[$i - 1]['tag'] = 'JJ';
                $current['tag'] = 'NN';
            }
            /* If we get noun noun, and the second can be a verb,
             * convert to verb; if noun noun and previous could be an
             * adjective convert to adjective
             */
            if (in_array($previous['tag'], $nouns) &&
                in_array($current['tag'], $nouns) ) {
                if (in_array('VBN', $tag_list)) {
                    $current['tag'] = 'VBN';
                } else if (in_array('VBZ', $tag_list)) {
                    $current['tag'] = 'VBZ';
                } else if (in_array('JJ', $prev_tag_list)) {
                    $result[$i - 1]['tag'] = 'JJ';
                }
            }
            $result[$i] = $current;
            $i++;
            $previous = $current;
            $previous_token = $token;
        }
        return $result;
    }
    /**
     * Takes an array of pairs (token, tag) that came from phrase
     * and builds a new phrase where terms look like token~tag.
     *
     * @param array $tagged_tokens array of pairs as might come from tagTokenize
     * @return $tagged_phrase a phrase with terms in the format token~tag
     */
    private static function taggedPartOfSpeechTokensToString($tagged_tokens)
    {
        $tagged_phrase = "";
        $simplified_parts_of_speech = [
            "NN" => "NN", "NNS" => "NN", "NNP" => "NN", "NNPS" => "NN",
            "PRP" => "NN", 'PRP$' => "NN", "WP" => "NN",
            "VB" => "VB", "VBD" => "VB", "VBN" => "VB", "VBP" => "VB",
            "VBZ" => "VB",
            "JJ" => "AJ", "JJR" => "AJ", "JJS" => "AJ",
            "RB" => "AV", "RBR" => "AV", "RBS" => "AV", "WRB" => "AV"
        ];
        foreach ($tagged_tokens as $t) {
            $tag = trim($t['tag']);
            $tag = (isset($simplified_parts_of_speech[$tag])) ?
                $simplified_parts_of_speech[$tag] : $tag;
            $tagged_phrase .= $t['token'] . "~" . $tag .  " ";
        }
        return $tagged_phrase;
    }
}
