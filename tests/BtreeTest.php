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
 * This file contains unit tests of the BTree class used to keep track of
 * etags during a crawl
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
use seekquarry\yioop\models\Model;
use seekquarry\yioop\library\BTree;
use seekquarry\yioop\library\UnitTest;

/**
 * Yioop B-tree Unit Class
 *
 * Used to test insert, lookup, and deletion of key-value pairs on the B-Tree.
 * @author Akshat Kukreti
 */
 class BTreeTest extends UnitTest
{
    /**
     * Number of key value pairs to insert for unit tests
     */
    const NUM_VALS = 25;
    /**
     * Minimum degree of BTree
     */
    const DEGREE = 2;
    /**
     * Test directory to hold btree used for these unit tests
     */
    const TEST_DIR = '/test_files/btree_test';
    /**
     * Minimum degree is set to 2 and the number of key-value pairs is set to 25
     */
    public function setUp()
    {
        $this->test_objects['FILE1'] = new BTree(__DIR__ . self::TEST_DIR, 
            self::DEGREE);
    }
    /**
     * Delete the B-Tree files created during the test
     */
    public function tearDown()
    {
        $model = new Model();
        $model->db->unlinkRecursive(__DIR__ . self::TEST_DIR);
    }
    /**
     * Test to check that an empty B-Tree is not saved to disk.
     * A B-Tree object is created. The test directory is checked for B-Tree
     * files. A value is inserted, the test directory is again checked. The
     * inserted value is removed and the test directory is again checked for
     * B-Tree files.
     */
    public function emptyBTreeNoSaveTestCase()
    {
        $all_files = glob(__DIR__ . self::TEST_DIR.'/*.txt');
        $this->assertEqual(0, count($all_files), 'Empty B-Tree not saved
            saved to disk');
        $this->test_objects['FILE1']->insert(array(1, 1));
        $all_files = glob(__DIR__ . self::TEST_DIR.'/*.txt');
        $this->assertEqual(2, count($all_files), 'Non-empty B-Tree successfully
            saved to disk');
        $this->test_objects['FILE1']->remove(1);
        $all_files = glob(__DIR__ . self::TEST_DIR.'/*.txt');
        $this->assertEqual(0, count($all_files), 'Empty B-Tree not saved
            saved to disk');
    }
    /**
     * Test to check insertion and lookup in a B-Tree
     * Key-value pairs are inserted in the B-Tree and then looked up using keys.
     */
    public function insertLookupTestCase()
    {
        //Insert values
        $key_value_pairs = [];
        for ($i = 1; $i <= self::NUM_VALS; $i++) {
            $value = L\crawlHash(rand(1, 1000), true);
            $key = L\crawlHash($value, true);
            $this->test_objects['FILE1']->insert(array($key, $value));
            $key_value_pairs[] = [$key, $value];
        }

        //Lookup values
        foreach ($key_value_pairs as $key_value_pair) {
            $key = $key_value_pair[0];
            $value = $key_value_pair[1];
            $lookup_entry = $this->test_objects['FILE1']->findValue($key);
            $lookup_value = $lookup_entry[1];
            $this->assertEqual($value, $lookup_value, 'Inserted value is equal
                to lookup value');
        }
    }
    /**
     * Test to check that a key is deleted successfully from a leaf node
     * Key-value pairs are inserted in the B-Tree. key-value pairs present in
     * leaf nodes are then deleted from the B-Tree. The deleted key-value pairs
     * are looked up to check if they were removed successfully.
     */
    public function deleteFromLeafNodeTestCase()
    {
        //Insert values
        for ($i = 1; $i <= self::NUM_VALS; $i++) {
            $this->test_objects['FILE1']->insert(array($i, $i));
        }
        //Keys in leaf nodes
        $leaf_keys = [1, 3, 5, 9, 10];
        foreach ($leaf_keys as $key) {
            $this->test_objects['FILE1']->remove($key);
        }
        //Lookup deleted keys
        foreach ($leaf_keys as $key) {
            $this->assertEqual(null,
                $this->test_objects['FILE1']->findValue($key),
                'Key successfully deleted from leaf node');
        }
    }
    /**
     * Test to check that a key is deleted successfully from an internal node
     * Key-value pairs are first added to the B-Tree. Key-value pairs are then
     * deleted successively from the root node. The deleted key-value pairs are
     * then looked up to check if they were successfully
     */
    public function deleteFromInternalNodeTestCase()
    {
        //Insert values
        for ($i = 1; $i <= self::NUM_VALS; $i++) {
            $value = L\crawlHash(rand(1, 1000), true);
            $key = L\crawlHash($value, true);
            $this->test_objects['FILE1']->insert(array($key, $value));
        }

        $deleted = [];
        for ($i = 1; $i <= self::NUM_VALS - 10; $i++) {
            $internal_key = $this->test_objects['FILE1']->root->keys[0][0];
            $this->test_objects['FILE1']->remove($internal_key);
            $this->assertEqual(null,
                $this->test_objects['FILE1']->findValue($internal_key),
                'Key deleted successfully from internal node');
        }
    }
    /**
     * Function to check that keys are successfully deleted from the B-Tree
     * Random key-value pairs are firs inserted in the B-Tree. From the inserted
     * key-value pairs, key-value pairs are randomly selected and deleted from
     * the B-Tree. The deleted key-value pairs are then looked up using their
     * keys to check if they were successfully deleted.
     */
    public function deleteLookupTestCase()
    {
        //Insert values
        $key_value_pairs = [];
        for ($i = 1; $i <= self::NUM_VALS; $i++) {
            $value = L\crawlHash(rand(1, 1000), true);
            $key = L\crawlHash($value, true);
            $this->test_objects['FILE1']->insert(array($key, $value));
            $key_value_pairs[] = [$key, $key];
        }
        //Delete Values
        $deleted = [];
        for ($i = 1; $i <= self::NUM_VALS; $i++) {
            $index = mt_rand(0, self::NUM_VALS - 1);
            $key = $key_value_pairs[$index][0];
            $this->test_objects['FILE1']->remove($key);
            $deleted[] = $key;
        }
        //Lookup values
        foreach ($deleted as $deleted_key) {
            $this->assertEqual(null,
                $this->test_objects['FILE1']->findValue($deleted_key),
                'Deleted Value not found');
        }
    }
}
