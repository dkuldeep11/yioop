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
 * @author Charles Bocage charles.bocage@sjsu.edu
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\summarizers;

/** For Yioop global defines used by subclasses*/
require_once __DIR__."/../../configs/Config.php";
/**
 * Base class for all summarizers that will hold common methods and
 * base functionality.
 *
 * @author Charles Bocage charles.bocage@sjsu.edu
 */
class Summarizer
{
    /**
     * The value to represent the weight for class one tags.
     */
    public static $CLASS_ONE_WEIGHT = 0;
    /**
     * The value to represent the weight for class two tags.
     */
    public static $CLASS_TWO_WEIGHT = 0;
    /**
     * The value to represent the weight for class three tags.
     */
    public static $CLASS_THREE_WEIGHT = 0;
    /**
     * The value to represent the weight for class four tags.
     */
    public static $CLASS_FOUR_WEIGHT = 0;
    /**
     * The value to represent the weight for class five tags.
     */
    public static $CLASS_FIVE_WEIGHT = 0;
    /**
     * This function will return additional weights if the term is in
     * certain tags of the document.  Each tag is grouped into 6 categories:
     * A
     * H1, H2
     * H3, H4, H5, H6
     * STRONG, B, EM, I, U, DL, OL, UL
     * Title
     * Plain Text = None of the above
     *
     * @param string $term the term to search for.
     * @param string $doc complete raw page to generate the summary from.
     *
     * @return int the additional weight of the term
     */
    public static function getAdditionalWeight($term, $doc)
    {
        $result = 0;
        $class_one_regex = "/(?s)<a.*?>(.*?" . $term . ".*?)<\/a>/iu";
        $class_two_regex = "/(?s)<h1.*?>(.*?" . $term . ".*?)<\/h1>|" .
            "<h2.*?>(.*?" . $term . ".*?)<\/h2>/iu";
        $class_three_regex = "/(?s)<h3.*?>(.*?" . $term . ".*?)<\/h3>|" .
            "<h4.*?>(.*?" . $term . ".*?)<\/h4>|" .
            "<h5.*?>(.*?" . $term . ".*?)<\/h5>|" .
            "<h6.*?>(.*?" . $term . ".*?)<\/h6>/iu";
        $class_four_regex = "/(?s)<strong.*?>(.*?" . $term . ".*?)<\/strong>|" .
            "<b.*?>(.*?" . $term . ".*?)<\/b>|" .
            "<em.*?>(.*?" . $term . ".*?)<\/em>|" .
            "<i.*?>(.*?" . $term . ".*?)<\/i>|" .
            "<u.*?>(.*?" . $term . ".*?)<\/u>|" .
            "<dl.*?>(.*?" . $term . ".*?)<\/dl>|" .
            "<ol.*?>(.*?" . $term . ".*?)<\/ol>|" .
            "<ul.*?>(.*?" . $term . ".*?)<\/ul>/iu";
        $class_five_regex = "/(?s)<title.*?>(.*?" . $term . ".*?)<\/title>/iu";
        $class_one_weight = self::$CLASS_ONE_WEIGHT;
        $class_two_weight = self::$CLASS_TWO_WEIGHT;
        $class_three_weight = self::$CLASS_THREE_WEIGHT;
        $class_four_weight = self::$CLASS_FOUR_WEIGHT;
        $class_five_weight = self::$CLASS_FIVE_WEIGHT;
        $class_one_matches = 0;
        $class_two_matches = 0;
        $class_three_matches = 0;
        $class_four_matches = 0;
        $class_five_matches = 0;
        $class_one_matches_count = 0;
        $class_two_matches_count = 0;
        $class_three_matches_count = 0;
        $class_four_matches_count = 0;
        $class_five_matches_count = 0;
        if (strpos(mb_strtolower($doc),
            mb_strtolower($term), 0) !== false)
        {
            preg_match_all($class_one_regex, $doc, $class_one_matches);
            preg_match_all($class_two_regex, $doc, $class_two_matches);
            preg_match_all($class_three_regex, $doc, $class_three_matches);
            preg_match_all($class_four_regex, $doc, $class_four_matches);
            preg_match_all($class_five_regex, $doc, $class_five_matches);

            if (count($class_one_matches[0]) > 0) {
                for($i = 1; $i < count($class_one_matches); $i++) {
                    $class_one_matches_count = $class_one_matches_count +
                        substr_count($class_one_matches[$i][0], $term);
                }
            }
            if (count($class_two_matches[0]) > 0) {
                for($i = 1; $i < count($class_two_matches); $i++) {
                    $class_two_matches_count = $class_two_matches_count +
                        substr_count($class_two_matches[$i][0], $term);
                }
            }
            if (count($class_three_matches[0]) > 0) {
                for($i = 1; $i < count($class_three_matches); $i++) {
                    $class_three_matches_count = $class_three_matches_count +
                        substr_count($class_three_matches[$i][0], $term);
                }
            }
            if (count($class_four_matches[0]) > 0) {
                for($i = 1; $i < count($class_four_matches); $i++) {
                    $class_four_matches_count = $class_four_matches_count +
                        substr_count($class_four_matches[$i][0], $term);
                }
            }
            if (count($class_five_matches[0]) > 0) {
                for($i = 1; $i < count($class_five_matches); $i++) {
                    $class_five_matches_count = $class_five_matches_count +
                        substr_count($class_five_matches[$i][0], $term);
                }
            }
            $result =
                ($class_one_weight * $class_one_matches_count) +
                ($class_two_weight * $class_two_matches_count) +
                ($class_three_weight * $class_three_matches_count) +
                ($class_four_weight * $class_four_matches_count) +
                ($class_five_weight * $class_five_matches_count);
        }
        return $result;
    }
}
