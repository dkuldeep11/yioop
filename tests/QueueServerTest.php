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
use seekquarry\yioop\executables\QueueServer;
use seekquarry\yioop\library\UnitTest;

C\nsdefine("UNIT_TEST_MODE", true);
/**
 * Used to test functions related to scheduling websites to crawl for
 * a web crawl (the responsibility of a QueueServer)
 *
 * @author Chris Pollett
 */
class QueueServerTest extends UnitTest
{
    /**
     * Creates a QueueServer object with an initial set of indexed file types
     */
    public function setUp()
    {
        $this->test_objects['Q_SERVER'] =  new QueueServer();
        $this->test_objects['Q_SERVER']->indexed_file_types = ["html", "txt"];
    }
    /**
     * Used to get rid of any object/files we created during a test case.
     * that need to be disposed of.
     */
    public function tearDown()
    {
        // get rid of the queue_server from previous test case
        $this->test_objects['Q_SERVER'] = null;
    }
    /**
     * allowedToCrawlSite check if a url is  matches a list of url
     * and domains stored in a QueueServer's allowed_sites and that it
     * is of an allowed to crawl file type. This function tests these
     * properties
     */
    public function allowedToCrawlSiteTestCase()
    {
        $q_server = $this->test_objects['Q_SERVER'];
        $q_server->allowed_sites = ["http://www.example.com/",
            "domain:ca", "domain:somewhere.tv"];
        $test_urls = [
            ["http://www.yoyo.com/", true,
                "not restrict by url case", ["restrict_sites_by_url" => false]],
            ["http://www.yoyo.com/", false,
                "simple not allowed case", ["restrict_sites_by_url" => true]],
            ["http://www.example.com/", true,
                "simple allowed site", [ "restrict_sites_by_url" => true]],
            ["http://www.example.com/a.bad", false, "not allowed filetype"],
            ["http://www.example.com/a.txt", true, "allowed filetype"],
            ["http://www.uchicago.com/", false,
                "domain disallowed", ["restrict_sites_by_url" => true]],
            ["http://www.findcan.ca/", true,
                "domain disallowed", ["restrict_sites_by_url" => true]],
            ["http://somewhere.tv.com/", false,
                "domain disallowed", ["restrict_sites_by_url" => true]],
            ["http://woohoo.somewhere.tv/", true,
                "domain disallowed", ["restrict_sites_by_url" => true]],
        ];
        foreach ($test_urls as $test_url) {
            if (isset($test_url[3])) {
                foreach ($test_url[3] as $field => $value) {
                    $q_server->$field = $value;
                }
            }
            $result = $q_server->allowedToCrawlSite($test_url[0]);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
    /**
     * disallowedToCrawlSite check if a url is  matches a list of url
     * and domains stored in a QueueServer's disallowed_sites. This function
     * tests this properties (The test cases are similar to those of
     * urlMemberSiteArrayTestCase, but are using the disallowed_sites array)
     */
    public function disallowedToCrawlSiteTestCase()
    {
        $q_server = $this->test_objects['Q_SERVER'];
        $q_server->disallowed_sites = ["http://www.example.com/",
            "http://www.cs.sjsu.edu/faculty/pollett/*/*/",
            "http://www.bing.com/video/search?*&*&",
            "http://*.cool.*/a/*/"];
        $test_urls = [
            ["http://www.cs.sjsu.edu/faculty/pollett/", false,
                "regex url negative 1"],
            ["http://www.bing.com/video/search?", false,"regex url negative 2"],
            ["http://www.cool.edu/a", false, "regex url negative 3"],
            ["http://www.cs.sjsu.edu/faculty/pollett/a/b/c", true,
                "regex url positive 1"],
            ["http://www.bing.com/video/search?a&b&c", true,
                "regex url positive 2"],
            ["http://www.cool.bob.edu/a/b/c", true, "regex url positive 3"],
        ];
        foreach ($test_urls as $test_url) {
            $result = $q_server->disallowedToCrawlSite($test_url[0]);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
}
