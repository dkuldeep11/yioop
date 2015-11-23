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
 * @author Nakul Natu nakul.natu@gmail.com
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\tests;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\processors\PptxProcessor;
use seekquarry\yioop\library\UnitTest;

/**
 * UnitTest for the PptxProcessor class. It is used to process
 * pptx files which are a zip of an xml-based format
 *
 * @author Nakul Natu
 */
class PptxProcessorTest extends UnitTest implements CrawlConstants
{
    /**
     * Creates a summary of pptx document to check
     */
    public function setUp()
    {
        $processor = new PptxProcessor();
        $url = "";
        $filename = C\PARENT_DIR . "/tests/test_files/test.pptx";
        $page = file_get_contents($filename);
        $summary = [];
        $summary = $processor->process($page, $url);
        $this->test_objects['summary'] = $summary;
    }
    /**
     * Test object is set to null
     */
    public function tearDown()
    {
        $this->test_objects = null;
    }
    /**
     * Checks title of the pptx is correct or not
     */
    public function checkTitleTestCase()
    {
        $objects = $this->test_objects['summary'];
        $title = "Nakul Natu";
        $this->assertEqual($objects[self::TITLE],
            $title, "Correct Title Retrieved");
    }
    /**
     * Checks Language of pptx is correct or not
     */
    public function checkLangTestCase()
    {
        $objects = $this->test_objects['summary'];
        $lang="en-US";
        $this->assertEqual(
            $objects[self::LANG], $lang, "Correct Language Retrieved");
    }
    /**
     * Checks the links are correct or not
     */
    public function checkLinksTestCase()
    {
        $objects = $this->test_objects['summary'];
        $testLinks = [];
        $testLinks[0] = "http://www.google.com/";
        $testLinks[1] = "http://www.facebook.com/";
        $links = [];
        $links = $objects[self::LINKS];
        $i = 0;
        foreach ($links as $link) {
            $this->assertEqual(
                $link, $testLinks[$i], "Correct Link Retrieved");
            $i++;
        }
    }
    /**
     * Checks if description is not null
     */
    public function checkDescriptionTestCase()
    {
        $objects = $this->test_objects['summary'];
        $this->assertTrue(
            isset($objects[self::DESCRIPTION]), "Description is not null");
    }
}
