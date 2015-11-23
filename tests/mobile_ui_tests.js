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
 * @author Eswara Rajesh Pinapala epinapala@live.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
/**
 * Set a mobile user agent for PhantomJS, so that all the pages that phantomJs
 * visits will be treated as mobile web pages.
 */
page.settings.userAgent = 'Mozilla/5.0 (Linux; U; Android 2.2.1; en-ca;'
    + ' LG-P505R Build/FRG83) AppleWebKit/533.1 (KHTML, like Gecko)'
    + ' Version/4.0 Mobile Safari/533.1';
/**
 * Below is a list of step functions executed withe a delay of 2s by
 * the interval function in "phantomjs_runner.js". Each function returns a
 * result object, which contains the test name, test status(PASS/FAIL) &
 * test ack(true/false). Note that result also contains the element of the page
 * that is being asserted for existence, however this is stripped off later
 * by "interval". All the cumulative steps are failed if any one test fails.
 */
var steps = [
    /**
     * Test if Yioop Home page is accessible.
     */
        function testHomePage()
    {
        page.open(page_url, function()
        {
            return true;
        });
    },
    /**
     * Test is SignIn link appears on the top of the page.
     * If exists, Click on the sign in link. Page.evaluate does this action
     * once the link existence is confirmed.
     */
    function testSignInLink()
    {
        var result = page.assertExists('body > div > div.landing-top-bar'
            + ' > div.user-nav > ul > li:nth-child(2) > a',
            "Signin link exists", page);
        if (result.ack) {
            page.evaluate(function()
            {
                var ev = document.createEvent("MouseEvents");
                ev.initEvent("click", true, true);
                document.querySelector("a[href='./?c=admin']")
                    .dispatchEvent(ev);
            });
        } else {
            l("Failed Test");
        }
        return result;
    },
    /**
     * Now that the click was expected in the last step function, now we assume
     * the user lands on the login form, So we assert the same, If the user
     * does land on login form, enter username/password and submit the form.
     * Username and password can be changed in "phantomjs_runner.js".
     */
    function testLoginFormExists()
    {
        var result = page.assertExists('body > div > div.landing.non-search'
        + ' > form', "Login Form exists", page);
        if (result.ack) {
            var creds = {};
            creds.username = yioop_username;
            creds.password = yioop_password;
            //Enter Credentials
            page.evaluate(function(creds)
            {
                document.getElementById("username").value = creds.username;
                document.getElementById("password").value = creds.password;
                document.querySelector('body > div > div.landing.non-search'
                +
                ' > form').submit();
                return;
            }, creds);
        } else {
            l("Failed Test");
        }
        return result;
    },
    /**
     * Now assuming that the login form is submitted successfully, we check for
     * the ManageGroups dropdown option on the mobile web page, test is it
     * exists.
     */
    function testDropDownExists()
    {
        var result = page.assertExists('#activity',
            "Menu Dropdown exists", page);
        if (result.ack) {
        } else {
            l("Failed Test");
        }
        return result;
    },
    /**
     * If manageGroups exists, select it.
     */
    function testManageGroups()
    {
        var selector = '#activity';
        var result = {};
        result.ack = false;
        result.status = "FAIL";
        result.msg = "Manage Groups link option exists in Dropdown.";
        return page.evaluate(function(selector, result, page_url)
        {
            var elm = document.querySelector(selector);
            if (elm !== null) {
                var num_options = elm.options.length;
                for (i = 0; i < num_options; i++) {
                    var option_text = elm.options[i].text;
                    if (option_text === "Manage Groups") {
                        result.ack = true;
                        result.status = "PASS";
                        document.location.href = elm.options[i].value;
                    }
                }
            }
            return result;
        }, selector, result, page_url);
    },
    /**
     * On manage Groups page help button is expected, click on it if
     * it exists.
     */
    function testHelpButtonExists()
    {
        var result = page.assertExists('button[data-pagename="Browse Groups"]',
            "Help Button exists On Manage groups Page", page);
        if (result.ack) {
            //click on help
            page.click('button[data-pagename="Browse Groups"]');
        } else {
            l("Failed Test");
        }
        return result;
    },
    /**
     * If the help button exists and if it is functioning properly, clicking on
     * the help button will pull out the help article with an Edit hyper link.
     * check if Edit link exists, If it does- click on it.
     */
    function testEditLinkForHelpArticlesExists()
    {
        var result = page.assertExists('#page_name > a',
            "Edit Link for Help article exists", page);
        if (result.ack) {
            page.click('#page_name > a');
        } else {
            l("Failed Test");
        }
        return result;
    }
];
