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
use seekquarry\yioop\library\summarizers\Summarizer;
use seekquarry\yioop\library\UnitTest;

/**
 * Code used to test the summarizer algorithms.
 *
 * @author Charles Bocage
 */
class SummarizerTest extends UnitTest
{
    /**
     * Each test we set up a new Summarizer object
     */
    public function setUp()
    {
        Summarizer::$CLASS_ONE_WEIGHT = 1;
        Summarizer::$CLASS_TWO_WEIGHT = 1;
        Summarizer::$CLASS_THREE_WEIGHT = 1;
        Summarizer::$CLASS_FOUR_WEIGHT = 1;
        Summarizer::$CLASS_FIVE_WEIGHT = 1;
    }
    /**
     * Nothing done for unit test tear done
     */
    public function tearDown()
    {
    }
    /**
     * This function reads each line from the $terms_and_html_tags file. The
     * $terms_and_html_tags file contains a list of a term to search for and a
     * sample html tag separated by a pipe.  It parses each line from the
     * $terms_and_html_tags file to and sends the arguments to the
     * additionalWeights method.  Then it compares the value returned to its
     * corresponding value in the $return_values_to_compare file to make sure
     * they match.
     *
     * $return_values_to_compare is a list of corresponding values used for
     * comparing results
     * $terms_and_html_tags is a list of terms and the sample html tags to
     * search matches
     */
    public function getAdditionalWeightTestCase()
    {
        $summarizer_dir = C\PARENT_DIR.'/tests/test_files/summarizer';
        $terms_and_html_tags = file("$summarizer_dir/summarizer_input.txt");
        $return_values_to_compare =
            file("$summarizer_dir/summarizer_result.txt");
        for ($i = 0; $i < count($terms_and_html_tags); $i++) {
            $term_and_html_tag = preg_split('/\|+/u',
                trim($terms_and_html_tags[$i]));
            $return_value_to_compare = trim($return_values_to_compare[$i]);
            $additional_weight_return_value =
                Summarizer::getAdditionalWeight($term_and_html_tag[0],
                $term_and_html_tag[1]);
            if ($return_value_to_compare != $additional_weight_return_value) {
                $clean_word = htmlentities($term_and_html_tag[1], ENT_QUOTES,
                    "UTF-8");
                echo "Calculating the weight of \"$term_and_html_tag[0]\" in ".
                    "$clean_word to $additional_weight_return_value should be".
                    " $return_value_to_compare\n";
                exit();
            }
            $this->assertEqual($additional_weight_return_value,
                $return_value_to_compare, "function getAdditionalWeight ".
                "correctly weights\"$term_and_html_tag[0]\" in ".
                "$term_and_html_tag[1] to $return_value_to_compare");
        }
    }
}
