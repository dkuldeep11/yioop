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
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\PhraseParser;
use seekquarry\yioop\library\UnitTest;

/**
 * Code used to test the English stemming algorithm. The inputs for the
 * algorithm are words in
 * http://snowball.tartarus.org/algorithms/porter/voc.txt and the resulting
 * stems are compared with the stem words in
 * http://snowball.tartarus.org/algorithms/porter/output.txt
 * Code uses orginal Porter stemmer, not Porter 2
 *
 * @author Chris Pollett
 */
class EnTokenizerTest extends UnitTest
{
    /**
     * Each test we set up a new English Tokenizer object
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] = PhraseParser::getTokenizer("en-US");
    }
    /**
     * Nothing done for unit test tear done
     */
    public function tearDown()
    {
    }
    /**
     * Tests whether the stem funtion for the English stemming algorithm
     * stems words according to the rules of stemming. The function tests stem
     * by calling stem with the words in $test_words and compares the results
     * with the stem words in $stem_words
     *
     * $test_words is an array containing a set of words in English provided in
     * the snowball web page
     * $stem_words is an array containing the stems for words in $test_words
     */
    public function stemmerTestCase()
    {
        $stem_dir = C\PARENT_DIR.'/tests/test_files/english_stemmer';
        //Test word set from snowball
        $test_words = file("$stem_dir/input_vocabulary.txt");
        //Stem word set from snowball for comparing results
        $stem_words = file("$stem_dir/stemmed_result.txt");
        /**
         * check if function stem correctly stems the words in $test_words by
         * comparing results with stem words in $stem_words
         */
        $tokenizer = $this->test_objects['FILE1'];
        $no_stem_list = isset($tokenizer::$no_stem_list) ?
            $tokenizer::$no_stem_list : [];
        for ($i = 0; $i < count($test_words); $i++) {
            $word = trim($test_words[$i]);
            if (in_array($word, $no_stem_list) ||
                strlen($word) < 3) {
                continue;
            }
            $stem = trim($stem_words[$i]);
            $word_stem = $tokenizer->stem($word);
            if ($stem != $word_stem) {
                echo "Stemming $word to $word_stem should be $stem\n";
                exit();
            }
            $this->assertEqual($word_stem,
                    $stem, "function stem correctly stems
                    $word to $stem");
        }
    }
}
