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

use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test that the UrlParser class. For now, want to see that the
 * method canonicalLink is working correctly and that
 * isPathMemberRegexPaths (used in robot_processor.php) works
 *
 * @author Chris Pollett
 */
class UrlParserTest extends UnitTest
{
    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    public function setUp()
    {
    }
    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    public function tearDown()
    {
    }
    /**
     * Check if can go from a relative link, base link to a complete link
     * in various different ways
     */
    public function canonicalLinkTestCase()
    {
        $test_links = [
             [".", "http://www.example.com/",
                "http://www.example.com/", "root dir0"],
            ["/bob.html", "http://www.example.com/",
                "http://www.example.com/bob.html", "root dir1"],
            ["bob.html", "http://www.example.com/",
                "http://www.example.com/bob.html", "root dir2"],
            ["bob", "http://www.example.com/",
                "http://www.example.com/bob", "root dir3"],
            ["bob", "http://www.example.com",
                "http://www.example.com/bob", "root dir4"],
            ["http://print.bob.com/bob", "http://www.example.com",
                "http://print.bob.com/bob", "root dir5"],
            ["/.", "http://www.example.com/",
                "http://www.example.com/", "root dir6"],
            ["//slashdot.org", "http://www.slashdot.org",
                "http://slashdot.org/", "slashdot dir"],
            ["bob", "http://www.example.com/a",
                "http://www.example.com/a/bob", "sub dir1"],
            ["../bob", "http://www.example.com/a",
                "http://www.example.com/bob", "sub dir2"],
            ["../../bob", "http://www.example.com/a",
                null, "sub dir3"],
            ["./bob", "http://www.example.com/a",
                "http://www.example.com/a/bob", "sub dir4"],
            ["bob.html?a=1", "http://www.example.com/a",
                "http://www.example.com/a/bob.html?a=1", "query 1"],
            ["bob?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/a/bob?a=1&b=2", "query 2"],
            ["/?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/?a=1&b=2", "query 3"],
            ["?a=1&b=2", "http://www.example.com/a",
                "http://www.example.com/a/?a=1&b=2", "query 4"],
            ["b/b.html?a=1&b=2", "http://www.example.com/a/c",
                "http://www.example.com/a/c/b/b.html?a=1&b=2", "query 5"],
            ["b/b.html?a=1&b=2?c=4", "http://www.example.com/a/c",
                "http://www.example.com/a/c/b/b.html?a=1&b=2?c=4", "query 6"],
            ["b#1", "http://www.example.com/",
                "http://www.example.com/b#1", "fragment 1"],
            ["b?a=1#1", "http://www.example.com/",
                "http://www.example.com/b?a=1#1", "fragment 2"],
            ["b?a=1#1#2", "http://www.example.com/",
                "http://www.example.com/b?a=1#1#2", "fragment 3"],
            ["#a", "http://www.example.com/c:d",
                "http://www.example.com/c:d#a", "fragment 4"],
        ];
        foreach ($test_links as $test_link) {
            $result = UrlParser::canonicalLink($test_link[0],
                $test_link[1], false);
            $this->assertEqual($result, $test_link[2], $test_link[3]);
        }
    }
    /**
     * Check is a path matches with a list of paths presumably coming from
     * a robots.txt file
     */
    public function isPathMemberRegexPathsTestCase()
    {
        $path = [];
        $robot_paths = [];
        $results = [];
        $tests = [
            ["/bobby", ["/bob"], true, "Substring Positive"],
            ["/bobby", ["/alice", "/f/g/h/d"], false,
                "Substring Negative 1"],
            ["/bobby/", ["/bobby/bay", "/f/g/h/d", "/yo"], false,
                "Substring Negative 2"],
            ["/bay/bobby/", ["/bobby/", "/f/g/h/d", "/yo"], false,
                "Substring Negative 3 (should match start)"],
            ["http://test.com/bay/bobby/",
                ["/bobby/", "/f/g/h/d", "/yo"], false,
                "Substring Negative 4 (should match start)"],
            ["/a/bbbb/c/", ["/bobby/bay", "/a/*/c/", "/yo"], true,
                "Star Positive 1"],
            ["/a/bbbb/d/", ["/bobby/bay", "/a/*/c/", "/yo"], false,
                "Star Negative 1"],
            ["/test.html?a=b", ["/bobby/bay", "/*?", "/yo"], true,
                "Star Positive 2"],
            ["/test.html", ["/bobby/bay", "/*.html$", "/yo"], true,
                "Dollar Positive 1"],
            ["/test.htmlish", ["/bobby/bay", "/*.html$", "/yo"], false,
                "Dollar Negative 1"],
            ["/test.htmlish", ["/bobby/bay", "*", "/yo"], true,
                "Degenerate 1"],
            ["/test.html", ["/bobby/bay", "/**.html$", "/yo"], true,
                "Degenerate 2"],
            ["/videos/search?q=Angelina+Jolie",
                  ["/videos/search?"], true, "End With Question Regex Case 1"],
        ];
        foreach ($tests as $test) {
            list($path, $robot_paths, $result, $description) = $test;
            $this->assertEqual(UrlParser::isPathMemberRegexPaths($path,
                $robot_paths), $result, $description);
        }
    }
    /**
     * Tests simplifyUrl function used on SERP pages
     */
    public function simplifyUrlTestCase()
    {
        $test_urls = [
            ["http://www.example.com/", 100,
                "www.example.com", "HTTP Domain only"],
            ["https://www.example.com/", 100,
                "www.example.com", "HTTPS Domain only"],
            ["http://www.superreallylongexample.com/", 25,
                "www.superreallylonge...e.com", "Domain truncate"],
            ["http://www.example.com/word1/word2/word3/word4", 25,
                "www.example.com/word...word4", "Path truncate"],
        ];

        foreach ($test_urls as $test_url) {
            $result = UrlParser::simplifyUrl($test_url[0], $test_url[1]);
            $this->assertEqual($result, $test_url[2], $test_url[3]);
        }
    }
    /**
     * urlMemberSiteArray is a function called by both allowedToCrawlSite
     * disallowedToCrawlSite to test if a url belongs to alist of
     * regex's of urls or domain. This test function tests this functionality
     */
    public function urlMemberSiteArrayTestCase()
    {
        $sites = ["http://www.example.com/",
            "http://www.cs.sjsu.edu/faculty/pollett/*/*/",
            "http://www.bing.com/video/search?*&*&",
            "http://*.cool.*/a/*/", "domain:ucla.edu",
            "domain:foodnetwork.com",
            "domain:.ottawa.ca",
            "domain:.ottawa2.ca",
            "http://ottawa2.ca/"];
        $test_urls = [
            ["http://www.cs.sjsu.edu/faculty/pollett/", false,
                "regex url negative 1"],
            ["http://www.bing.com/video/search?", false,"regex url negative 2"],
            ["http://www.cool.edu/a", false, "regex url negative 3"],
            ["http://ucla.edu.com", false, "domain test negative"],
            ["http://www.cs.sjsu.edu/faculty/pollett/a/b/c", true,
                "regex url positive 1"],
            ["http://www.bing.com/video/search?a&b&c", true,
                "regex url positive 2"],
            ["http://www.cool.bob.edu/a/b/c", true, "regex url positive 3"],
            ["http://test.ucla.edu", true, "domain test positive"],
            ["https://test.ucla.edu", true, "domain https test positive"],
            ["gopher://test.ucla.edu", true, "domain gopher stest positive"],
            ["http://www.foodnetworkstore.com/small-appliances/", false,
                "domain test negative"],
            ["http://a.ottawa.ca/", true,
                "domain starting dot test positive 2"],
            ["http://ottawa.ca/", false, "domain starting dot test negative"],
            ["http://a.ottawa2.ca/", true,
                "domain starting dot test positive 2"],
            ["http://ottawa2.ca/", true, "domain starting dot test positive 3"],
        ];
        foreach ($test_urls as $test_url) {
            $result = UrlParser::urlMemberSiteArray($test_url[0], $sites,
                "s");
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
    /**
     * Checks if getScheme is working okay
     */
    public function getSchemeTestCase()
    {
        $test_links = [
            ["http://www.example.com/", "http", "Simple HTTP 1"],
            ["https://www.example.com/", "https", "Simple HTTPS 1"],
            ["gopher://www.example.com/", "gopher", "Simple GOPHER 1"],
            ["./", "http", "Simple HTTP 2"],
        ];
        foreach ($test_links as $test_link) {
            $result = UrlParser::getScheme($test_link[0]);
            $this->assertEqual($result, $test_link[1], $test_link[2]);
        }
    }
}
