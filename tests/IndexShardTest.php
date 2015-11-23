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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\IndexShard;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test that the IndexShard class can properly add new documents
 * and retrieve those documents by word. Checks that doc offsets can be
 * updated, shards can be saved and reloaded
 *
 * @author Chris Pollett
 */
class IndexShardTest extends UnitTest
{
    /**
     * Construct some index shard we can add documents to
     */
    public function setUp()
    {
        $this->test_objects['shard'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard.txt", 0);
        $this->test_objects['shard2'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard2.txt", 0);
        $this->test_objects['shard3'] = new IndexShard(C\WORK_DIRECTORY.
            "/shard3.txt", 0);
    }
    /**
     * Deletes any index shard files we may have created
     */
    public function tearDown()
    {
        @unlink(C\WORK_DIRECTORY."/shard.txt");
        @unlink(C\WORK_DIRECTORY."/shard2.txt");
        @unlink(C\WORK_DIRECTORY."/shard3.txt");
    }
    /**
     * Check if can store documents into an index shard and retrieve them
     */
    public function addDocumentsGetPostingsSliceByIdTestCase()
    {
        $docid = "AAAAAAAA";
        $doc_hash = "BBBBBBBB";
        $doc_hosts_url = "CCCCCCCC";
        $docid .= $doc_hash . $doc_hosts_url;
        $offset = 5;
        $word_counts = [
            'BBBBBBBB' => [1, 3],
            'CCCCCCCC' => [4, 9, 16],
            'DDDDDDDD' => [5, 25, 125],
        ];
        $meta_ids = ["EEEEEEEE", "FFFFFFFF"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids, ["EEEEEEEE"], true);
        $this->assertEqual($this->test_objects['shard']->len_all_docs, 8,
            "Len All Docs Correctly Counts Length of First Doc");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('CCCCCCCC', true), 5);
        $this->assertTrue(isset($c_data[$docid]),
            "Doc lookup by word works");
        // add a second document and check
        $docid = "HHHHHHHH";
        $doc_hash = "IIIIIIII";
        $doc_hosts_url = "JJJJJJJJ";
        $docid .= $doc_hash. $doc_hosts_url;
        $offset = 7;
        $word_counts = [
            'CCCCCCCC' => [1, 4, 9],
            'GGGGGGGG' => [6],
        ];
        $meta_ids = ["YYYYYYYY"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids, [], true);
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('CCCCCCCC', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Work lookup first item of two works");
        $this->assertTrue(isset($c_data["HHHHHHHHIIIIIIIIJJJJJJJJ"]),
            "Work lookup second item of two works");
        $this->assertEqual(count($c_data), 2,
            "Exactly two items were found in two item case");
        //add a meta word lookup
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('EEEEEEEE', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup by meta word works");
        $this->assertEqual(count($c_data), 1,
            "Doc lookup by meta word works has correct count");
    }
    /**
     * Check if can store link documents into an index shard and retrieve them
     */
    public function addLinkGetPostingsSliceByIdTestCase()
    {
        $docid = "AAAAAAAABBBBBBBBCCCCCCCC"; //set up link doc
        $offset = 5;
        $word_counts = [
            'MMMMMMMM' => [1, 3, 5],
            'NNNNNNNN' => [2, 4, 6],
            'OOOOOOOO' => [7, 8, 9],
        ];
        $meta_ids = ["PPPPPPPP", "QQQQQQQQ"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids);
        $this->assertEqual($this->test_objects['shard']->len_all_link_docs, 9,
            "Len All Docs Correctly Counts Length of First Doc");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('MMMMMMMM', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Link Doc lookup by word works");
        $docid = "AAAAAAAA";
        $doc_hash = "BBBBBBBB";
        $docid .= $doc_hash."EEEEEEEE";
        $offset = 10;
        $word_counts = [
            'BBBBBBBB' => [1],
            'CCCCCCCC' => [2],
            'MMMMMMMM' => [6],
        ];
        $meta_ids = ["EEEEEEEE", "FFFFFFFF"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids, true);
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('MMMMMMMM', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Link Doc lookup by word works 1st of two");
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBEEEEEEEE"]),
            "Link Doc lookup by word works 2nd doc");
        $this->assertEqual(count($c_data), 2,
            "Link Doc lookup by word works has correct count");
    }
    /**
     * Check that appending two index shards works correctly
     */
    public function appendIndexShardTestCase()
    {
        $docid = "AAAAAAAA"; //it actually shouldn't matter if have one or more
            // 8 byte doc_keys, both should be treated as documents
        $offset = 5;
        $word_lists = [
            'BBBBBBBB' => [1],
            'CCCCCCCC' => [2],
            'DDDDDDDD' => [6],
        ];
        $meta_ids = ["EEEEEEEE", "FFFFFFFF"];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids, true);
        $docid = "KKKKKKKKGGGGGGGGHHHHHHHH";
        $offset = 20;
        $word_lists = [
            'ZZZZZZZZ' => [9],
            'DDDDDDDD' => [4],
        ];
        $meta_ids = [];
        $this->test_objects['shard2']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $docid = "GGGGGGGG";
        $offset = 6;
        $word_lists = [
            'DDDDDDDD' => [3],
            'IIIIIIII' => [4],
            'JJJJJJJJ' => [5],
        ];
        $meta_ids = ["KKKKKKKK"];
        $this->test_objects['shard2']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $this->test_objects['shard']->appendIndexShard(
            $this->test_objects['shard2']);
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('BBBBBBBB', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAA"]),
            "Data from first shard present 1");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('CCCCCCCC', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAA"]),
            "Data from first shard present 2");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('DDDDDDDD', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAA"]),
            "Data from first shard present 3");
        $this->assertTrue(isset($c_data["KKKKKKKKGGGGGGGGHHHHHHHH"]),
            "Data from second shard present 1");
        $this->assertTrue(isset($c_data["GGGGGGGG"]),
            "Data from third shard present 1");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('EEEEEEEE', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAA"]),
            "Data from first shard present 4");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('FFFFFFFF', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAA"]),
            "Data from first shard present 5");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('ZZZZZZZZ', true), 5);
        $this->assertTrue(isset($c_data["KKKKKKKKGGGGGGGGHHHHHHHH"]),
            "Data from second shard present 2");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('IIIIIIII', true), 5);
        $this->assertTrue(isset($c_data["GGGGGGGG"]),
            "Data from third shard present 2");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('JJJJJJJJ', true), 5);
        $this->assertTrue(isset($c_data["GGGGGGGG"]),
            "Data from third shard present 3");
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('KKKKKKKK', true), 5);
        $this->assertTrue(isset($c_data["GGGGGGGG"]),
            "Data from third shard present 4");
    }
    /**
     * Check that changing document offsets works
     */
    public function changeDocumentOffsetTestCase()
    {
        $docid = "AAAAAAAASSSSSSSS";
        $offset = 0;
        $word_lists = [
            'BBBBBBBB' => [1]
        ];
        $meta_ids = [];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $docid = "AAAAAAAAEEEEEEEEFFFFFFFF";
        $offset = 0;
        $word_lists = [
            'BBBBBBBB' => [1]
        ];
        $meta_ids = [];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $docid = "CCCCCCCCFFFFFFFF";
        $offset = 0;
        $word_lists = [
            'BBBBBBBB' => [1],
            'ZZZZZZZZ' => [1]
        ];
        $meta_ids = [];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $docid = "QQQQQQQQEEEEEEEEFFFFFFFF";
        $offset = 0;
        $word_lists = [
            'BBBBBBBB' => [1]
        ];
        $meta_ids = [];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $docid = "DDDDDDDD";
        $offset = 0;
        $word_lists = [
            'BBBBBBBB' => [1]
        ];
        $meta_ids = [];
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_lists, $meta_ids);
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('BBBBBBBB', true), 5);
        $new_doc_offsets = [
            "AAAAAAAASSSSSSSS" => 5,
            "AAAAAAAAEEEEEEEEFFFFFFFF" => 10,
            "QQQQQQQQEEEEEEEEFFFFFFFF" => 9,
            "DDDDDDDD" => 7,
        ];
        $this->test_objects['shard']->changeDocumentOffsets($new_doc_offsets);
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('BBBBBBBB', true), 5);
        $predicted_offsets = [
            "AAAAAAAASSSSSSSS" => 5,
            "CCCCCCCCFFFFFFFF" => 0,
            "AAAAAAAAEEEEEEEEFFFFFFFF" => 10,
            "QQQQQQQQEEEEEEEEFFFFFFFF" => 9,
            "DDDDDDDD" => 7,
        ];
        $i = 0;
        foreach ($predicted_offsets as $key =>$offset) {
            $this->assertTrue(isset($c_data[$key]),
                "Summary key matches predicted $i");
            $this->assertEqual($c_data[$key][CrawlConstants::SUMMARY_OFFSET],
                $offset,  "Summary offset matches predicted $i");
            $i++;
        }
        $c_data = $this->test_objects['shard']->getPostingsSliceById(
            L\crawlHashWord('ZZZZZZZZ', true), 5);
        $this->assertEqual($c_data['CCCCCCCCFFFFFFFF']
                [CrawlConstants::SUMMARY_OFFSET],
                0,  "Summary offset matches predicted second word");
    }
    /**
     * Check that save and load work
     */
    public function saveLoadTestCase()
    {
        $docid = "AAAAAAAABBBBBBBBCCCCCCCC";
        $offset = 5;
        $word_counts = [
            'BBBBBBBB' => [1],
            'CCCCCCCC' => [2],
            'DDDDDDDD' => [6],
        ];
        $meta_ids = ["EEEEEEEE", "FFFFFFFF"];
        //test saving and loading to a file
        $this->test_objects['shard']->addDocumentWords($docid,
            $offset, $word_counts, $meta_ids, [], true);
        $this->test_objects['shard']->save();
        $this->test_objects['shard2'] = IndexShard::load(C\WORK_DIRECTORY.
            "/shard.txt");
        $this->assertEqual($this->test_objects['shard2']->len_all_docs, 3,
            "Len All Docs Correctly Counts Length of First Doc");

        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('BBBBBBBB', true), 5);

        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('CCCCCCCC', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('DDDDDDDD', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('EEEEEEEE', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('FFFFFFFF', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "Doc lookup 2 by word works");
        // test saving and loading from a string
        $out_string = $this->test_objects['shard']->save(true);
        $this->test_objects['shard2'] = IndexShard::load("shard.txt",
            $out_string);
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('BBBBBBBB', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "String Load Doc lookup by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('CCCCCCCC', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "String Load Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('DDDDDDDD', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "String Load Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('EEEEEEEE', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "String Load Doc lookup 2 by word works");
        $c_data = $this->test_objects['shard2']->getPostingsSliceById(
            L\crawlHashWord('FFFFFFFF', true), 5);
        $this->assertTrue(isset($c_data["AAAAAAAABBBBBBBBCCCCCCCC"]),
            "String Load Doc lookup 2 by word works");
    }
}
