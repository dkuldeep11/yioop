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
 * Test to see for big strings which how long various string concatenation
 * operations take.
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/**
 * This script is used to randomly sample from input_vocabulary.txt and
 * stemmed_result.txt text files available for a given locale at
 * http://snowball.tartarus.org/algorithms/ and output only a 1000 words and
 * stems so that unit test for stems can be of a manageable size
 */
$num_samples = 1000;
$locale = 'hindi_stemmer';
$words = file("$locale/input_vocabulary.txt");
$stems = file("$locale/stemmed_result.txt");
$num_words = count($words);
$sample_words = [];
$sample_stems = [];
$indices = [];
for ($i = 0; $i < $num_samples; $i++) {
    do {
        $rand = rand(0, $num_words - 1);
    } while (isset($indices[$rand]));
    $indices[$rand] = true;
}
$indices = array_keys($indices);
sort($indices);
for ($i = 0; $i < $num_samples; $i++) {
    $index = $indices[$i];
    $sample_words[] = trim($words[$index]);
    $sample_stems[] = trim($stems[$index]);
}
file_put_contents("input_vocabulary.txt", implode("\n", $sample_words));
file_put_contents("stemmed_result.txt", implode("\n", $sample_stems));
