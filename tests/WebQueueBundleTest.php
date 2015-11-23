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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\WebQueueBundle;
use seekquarry\yioop\library\UnitTest;

/**
 * UnitTest for the WebQueueBundle class.
 *
 * @author Chris Pollett
 */
class WebQueueBundleTest extends UnitTest
{
    /** our dbms manager handle so we can call unlinkRecursive
     * @var object
     */
    public $db;
    /**
     * Sets up a miminal DBMS manager class so that we will be able to use
     * unlinkRecursive to tear down own WebQueueBundle
     */
    public function __construct()
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
        $this->db = new $db_class();
    }
    /**
     * Set up a web queue bundle that can store 1000 urls in ram, has bloom
     * filter space for 1000 urls and which uses a maximum value returning
     * priority queue.
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] =
            new WebQueueBundle(C\WORK_DIRECTORY."/QueueTest",
                1000, 1000, CrawlConstants::MAX);
    }
    /**
     * Delete the directory and files associated with the WebQueueBundle
     */
    public function tearDown()
    {
        $this->db->unlinkRecursive(C\WORK_DIRECTORY."/QueueTest");
    }
    /**
     * Does two adds to the WebQueueBundle of urls and weight. Then checks the
     * contents of the queue to see if as expected. Then does a rebuild on the
     * hash table of the queue and checks that the contents have not changed.
     */
    public function addQueueTestCase()
    {
        $urls1 = [ ["http://www.pollett.com/", 10],
            ["http://www.ucanbuyart.com/", 15]];
        $this->test_objects['FILE1']->addUrlsQueue($urls1);
        $urls2 = [ ["http://www.yahoo.com/", 2],
            ["http://www.google.com/", 20],
            ["http://www.slashdot.org/", 3]];
        $this->test_objects['FILE1']->addUrlsQueue($urls2);

        $expected_array = [ ['http://www.google.com/', 20, 0, 7694],
            ['http://www.ucanbuyart.com/', 15, 0, 6507],
            ['http://www.yahoo.com/', 2, 0, 5222],
            ['http://www.pollett.com/', 10, 0, 6364],
            ['http://www.slashdot.org/', 3, 0, 1653]
        ];
        $this->assertEqual(
            $this->test_objects['FILE1']->getContents(), $expected_array,
            "Insert Queue matches predicted");

        $this->test_objects['FILE1']->rebuildUrlTable();
        $this->assertEqual(
            $this->test_objects['FILE1']->getContents(), $expected_array,
            "Rebuild table should not affect contents");
    }
    /**
     * Checks the two methods addGotRobotTxtFilter($host) and
     * containsGotRobotTxt($host) properly insert do containment for the
     * robots.txt Bloom filter
     */
    public function addContainsRobotTxtFilterTestCase()
    {
        $web_queue = $this->test_objects['FILE1'];
        $host = "http://www.host.com/";
        $web_queue->addGotRobotTxtFilter($host);
        $this->assertTrue(
            $web_queue->containsGotRobotTxt($host),
            "Contains added robots.txt host");
        $this->assertFalse(
            $web_queue->containsGotRobotTxt("http://www.bob.com/"),
            "Contains added robots.txt host");
    }
    /**
     * Tests the methods addRobotPaths and checkRobotOkay
     */
    public function addRobotPathsCheckRobotOkayTestCase()
    {
        $web_queue = $this->test_objects['FILE1'];
        $host = "http://www.test.com/";
        $paths = [
            CrawlConstants::ALLOWED_SITES => ["/trapdoor"],
            CrawlConstants::DISALLOWED_SITES => ["/trap","/*?",'/cgi-bin'],
        ];
        $web_queue->addRobotPaths($host, $paths);
        $test_urls = [
            ["http://www.cs.sjsu.edu/", true,
                "url with no stored rules"],
            ["http://www.test.com/trapdoor", true,
                "allowed url"],
            ["http://www.test.com/trapdoor?b", true,
                "allowed overrides all disallows"],
            ["http://www.test.com/trap", false,
                "forbidden url 1"],
            ["http://www.test.com/abc?", false,
                "forbidden url 2"],
            ["http://www.test.com/a?b", false,
                "forbidden url 3"],
            ["http://www.test.com/cgi-bin/psearch?-list", false,
                "forbidden url 4"],
        ];
        foreach ($test_urls as $test_url) {
            $result = $web_queue->checkRobotOkay($test_url[0]);
            $this->assertEqual($result, $test_url[1], $test_url[2]);
        }
    }
}
