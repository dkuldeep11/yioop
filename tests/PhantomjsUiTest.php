<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 * Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 * LICENSE:
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * END LICENSE
 *
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\library\JavascriptUnitTest;

/**
 * Used to test the UI using PhantomJs.
 *
 * @author Eswara Rajesh Pinapala
 */
class PhantomjsUiTest extends JavascriptUnitTest
{
    /**
     * This test case runs the UI test cases in JS using PhantomJS. It then
     * sends the result JSON to Javascript land to render test results.
     */
    public function UITestCase()
    {
        ?>
        <style>
            .login {
                width :500px;
            }
            .login ul {
                padding: 0;
                margin: 0;
            }
            .login li {
                display: inline;
            }
            label {
                display: block;
                color: #999;
            }
        </style>
        <fieldset class="login cf">
            <span>
                <h4>Please enter username & password of any user with
                    "Admin" role in Yioop.</h4>
            </span>
            <form>
                <ul>
                    <li>
                        <label for="u">Username</label>
                        <input type="username" id="u" name="u"
                               placeholder="Admin username" required="">
                    </li>
                    <br /><br />
                    <li>
                        <label for="p">Password</label>
                        <input type="password" id="p" name="p"
                               placeholder="Admin password" required="">
                    </li>
                    <br /><br />
                    <li>
                        <button type="button"
                                onclick="triggerTests(); return false;">
                            Run Tests
                        </button>
                    </li>
                </ul>
            </form>
        </fieldset>
        <br /><br />
        <div id="web-ui-test">
        </div>
        <div id="mobile-ui-test">
        </div>
        <script type="text/javascript" src="../scripts/basic.js"></script>
        <script type="text/javascript" src="../scripts/help.js"></script>
        <script type="text/javascript">
            var u, p;
            /**
             * This function is the main function that initiates the tests.
             * Picks up the user name and password the user entered, and
             * calls the web service to run tests.
             *
             */
            function triggerTests()
            {
                u = document.getElementById("u").value;
                p = document.getElementById("p").value;
                runTests("web");
                runTests("mobile");
            }
            /**
             * This function makes a XHR POST request with callback functions.
             * @param url String Url to do POST request on.
             * @param postData String post params data.
             * @param response_type String response type expected.
             * @param success_callback Function callback function to call on
             * success.
             * @param error_handler Function callback function to call on
             * failure.
             *
             */
            function postToPageWithCallback(url, post_data, response_type,
                success_call_back,
                error_handler)
            {
                var request = makeRequest();
                request.open('POST', url, true);
                request.setRequestHeader("Content-Type",
                    "application/x-www-form-urlencoded");
                request.onload = function()
                {
                    var status = request.status;
                    if (status == 200) {
                        success_call_back && success_call_back(
                            JSON.parse(request.responseText));
                    } else {
                        error_handler && error_handler(status,
                            JSON.parse(request.responseText));
                    }
                };
                request.send(post_data);
            }
            /**
             * This function runs the tests for the mode requested.
             * @param String mode Tests mode Web/Mobile.
             */
            function runTests(mode)
            {
                elt(mode + "-ui-test").innerHTML =
                    '<div style="width:200px;">' + 'Loading ' +
                    mode + '-UI ' + 'test Results<marquee ' +
                    'behavior="alternate">.............' +
                    '</marquee></div>';
                postToPageWithCallback("?activity=runBrowserTests&mode=" +
                    mode, "&u=" + u + "&p=" + p, "json",
                    function(data)
                    {
                        renderResults(data.results, mode)
                    },
                    function(status, response)
                    {
                        elt(mode + "-ui-test").innerHTML =
                            "Unable to run " +
                            mode + " UI tests. \n Error: " + response.error;
                        updateResultsSummary(0,
                            Object.keys(data.results).length,
                            'red', mode);
                    }
                );
            }
            /**
             *  This function takes in the results object, parses it to render
             *  the results ui.
             *  @param results Obj results object.
             *  @param mode String tests mode Web/Mobile.
             */
            function renderResults(results, mode)
            {
                elt(mode + "-ui-test").innerHTML = "";
                var h2 = document.createElement("h2");
                h2.innerHTML = "UI test results - " + mode;
                elt(mode + "-ui-test").appendChild(h2);
                var success_count = 0;
                var results_size = 0;
                for (var key in results) {
                    var test_result = results[key];
                    var cell;
                    var row;
                    var table;
                    var color;
                    table = document.createElement('table');
                    if (test_result.ack) {
                        color = 'lightgreen';
                        success_count++;
                    } else {
                        color = 'red';
                        row = table.insertRow(0);
                        cell = row.insertCell(0);
                        cell.style.fontWeight = 'bold';
                        cell.innerHTML = key;
                        cell = row.insertCell(1);
                        cell.setAttribute("style", "background-color: " +
                        color + ";");
                        cell.innerHTML = test_result.status;
                        elt(mode + "-ui-test").appendChild(table);
                    }
                    results_size++;
                }
                updateResultsSummary(success_count, results_size,
                    'lightgreen',
                    mode);
            }
            /**
             * This test updates the results summary UI.
             * @param pass String passed tests count
             * @param total String total tests count
             * @param color String color to display
             * @param mode String mode of tests, Web/Mobile.
             */
            function updateResultsSummary(pass, total, color, mode)
            {
                var cell;
                var row;
                var table;
                table = document.createElement('table');
                row = table.insertRow(0);
                cell = row.insertCell(0);
                cell.style.fontWeight = 'bold';
                cell.innerHTML = "PhantomJS " + mode + " UI Tests";
                cell = row.insertCell(1);
                cell.setAttribute("style", "background-color: " +
                color + ";");
                cell.innerHTML = pass + "/" + total;
                elt(mode + "-ui-test").appendChild(table);
            }
        </script>
    <?php

    }
}
