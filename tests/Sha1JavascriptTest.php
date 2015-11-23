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

use seekquarry\yioop\library\JavascriptUnitTest;

/**
 * Used to test the Javascript implementation of the sha1 function.
 * @author Akash Patel
 */
class Sha1JavascriptTest extends JavascriptUnitTest
{
    /**
     * Number of test cases
     * @var int
     */
    const NUM_TEST_CASES = 5;
    /**
     * This test case generates random strings and computes their sha1 hash
     * in PHP-land. It then sends the strings and their hashes to Javascript
     * land to test if the Javascript implementation of Sha1 gets the same
     * answer.
     */
    public function sha1TestCase()
    {
        $time = time();
        $input_value = [];
        $k = 0;
        for ($i=0; $i < self::NUM_TEST_CASES; $i++) {
            $random_string = md5($time.rand(1, 1000));
            $sha1 = sha1($random_string);
            $input_value[$k++] = $sha1;
            $input_value[$k++] = $random_string;
        }
        $js_array = json_encode($input_value);
        ?>
        <div id="sha1Test">
        </div>
        <script type="text/javascript" src="../scripts/sha1.js" ></script>
        <script type="text/javascript"
            src="../scripts/hash_captcha.js" ></script>
        <script type="text/javascript">
        var input_array = <?= $js_array ?>;
        var total_test_cases = <?= self::NUM_TEST_CASES ?>;
        var cell;
        var row;
        var table;
        var result;
        var color;
        var i = 0;
        var counter = 0;
        while(i < input_array.length) {
            if (input_array[i++] == generateSha1(input_array[i++])) {
                counter++;
            }
        }
        result = counter + "/" + total_test_cases+" Test Passed";
        if (total_test_cases == counter){
            color='lightgreen';
        } else {
            color='red';
        }
        table = document.createElement('table');
        row = table.insertRow(0);
        cell = row.insertCell(0);
        cell.style.fontWeight = 'bold';
        cell.innerHTML = "generateSha1TestCase";
        cell = row.insertCell(1);
        cell.setAttribute("style","background-color: "+color+";");
        cell.innerHTML = result;
        document.getElementById("sha1Test").appendChild(table);
        </script>
        <?php

    }
}
