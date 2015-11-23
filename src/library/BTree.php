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
namespace seekquarry\yioop\library;

/**
 * This class implements the B-Tree data structure for storing int key based
 * key-value pairs based on the algorithms in Introduction To Algorithms,
 * by T.H. Cormen, C.E. Leiserson, R.L. Rivest, and C. Stein. Second
 * Edition, 2001, The MIT Press
 *
 * @author Akshat Kukreti
 */
class BTree
{
    /**
     * Default value of minimum degree. The minimum degree determines the
     * minimum and maximum number of keys and child nodes, for nodes
     * other than root node
     */
    const MIN_DEGREE = 501;
    /**
     * Minimum degree of the B-Tree. Used in determining the minimum/maximum
     * keys and links a B-Tree node may have.
     * minimum_keys = minimum_degree - 1
     * minimum_links = minimum_keys + 1
     * maximum_keys = 2 * minimum_degree - 1
     * maximum_links = maximum_keys + 1
     * @var int
     */
    public $min_degree;
    /**
     * Storage for root node of the B-Tree
     * @var object
     */
    public $root;
    /**
     * Counter for node Ids
     * @var int
     */
    public $id_count;
    /**
     * Directory for storing the B-Tree files
     * @var string
     */
    public $dir;
    /**
     * Creates/Loads B-Tree having specified directory and minimum_degree. The
     * default minimum_degree is 501.
     * @param string $dir is the directory for storing the B-Tree files
     * @param int $min_degree minimum degree of a B-tree node
     */
    public function __construct($dir, $min_degree = self::MIN_DEGREE)
    {
        $this->dir = $dir;
        $this->min_degree = $min_degree;
        if (!is_dir($this->dir)) {
            mkdir($this->dir);
            @chmod($this->dir, 0777);
        }
        $root_file = $this->dir."/root.txt";
        if (file_exists($root_file)) {
            $this->root = unserialize(file_get_contents($root_file));
            $this->id_count = unserialize(file_get_contents($this->dir.
                "/count.txt"));
        } else {
            $this->root = new BTNode();
            $this->root->id = "root";
            $this->id_count = 1;
        }
    }
    /**
     * Reads node from file saved on disk
     * @param int $id is the Id of the node to be read
     * @return object $node is the node
     */
    public function readNode($id)
    {
        $node_file = $this->dir."/$id.txt";
        if (file_exists($node_file)) {
            $node = unserialize(file_get_contents($node_file));
            return $node;
        } else {
            crawlLog("Btree could not read node $id from disk");
            return false;
        }
    }
    /**
     * Writes node to disk
     * @param object $node is the node to be written to disk
     */
    public function writeNode($node)
    {
        $node_file = $this->dir."/{$node->id}.txt";
        $contents = serialize($node);
        file_put_contents($node_file, $contents);
        @chmod($node_file, 0777);
    }
    /**
     * Writes the root node of this btree to disk
     */
    public function writeRoot()
    {
        $this->writeNode($this->root);
    }
    /**
     * Deletes file associated with given node from disk
     * @param int $id is the id of the node whose file is to be deleted
     */
    public function deleteNodeFile($id)
    {
        $node_file = $this->dir."/$id.txt";
        if (file_exists($node_file)) {
            unlink($node_file);
        } else {
            crawlLog("Could not delete node $id from disk");
        }
    }
    /**
     * Saves value of node id counter
     * @param int $count is the id counter
     */
    public function saveNodeCount()
    {
        $count_file = $this->dir."/count.txt";
        $node_count = serialize($this->id_count);
        file_put_contents($count_file, $node_count);
    }
    /**
     * Deletes the node id count file
     */
    public function deleteCount()
    {
        unlink($this->dir."/count.txt");
    }
    /**
     * Returns key-value pair in the B-Tree based on key
     * @param int $key is the key for whicht the key-value pair is to be
     * found
     * @return array key-value pair associated with $key or null if the
     * key-value pair is not found in the tree.
     */
    public function findValue($key)
    {
        list($node, $flag, $pos) = $this->search($this->root, $key);
        if ($pos !== null) {
            if ($flag == 1) {
                return $node->keys[$pos];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }
    /**
     * Searches for key-value pair for a given key in a node. If key value pair
     * is not found in the node, recursively searches in the root node of the
     * sub-tree till the pair is found. Search stops at leaf nodes.
     * @param object $node is the B-Tree node from where the search starts
     * @param int $key is the key for which the key-value pair is to be
     * searched
     */
    public function search($node, $key)
    {
        $flag = -1;
        if (empty($node->keys)) {
            return [$node, $flag, null];
        } else {
            list($flag, $pos) = $this->binarySearch($node->keys, $key);
            if ($flag == 1) {
                return [$node, $flag, $pos];
            }
            if ($node->is_leaf == true) {
                return [$node, $flag, $pos];
            } else {
                $next_id = $node->links[$pos];
                $next_node = $this->readNode($next_id);
                return $this->search($next_node, $key);
            }
        }
    }
    /**
     * Inserts a key-value pair in the B-Tree
     * @param array $pair is the key-value pair to be inserted
     */
    public function insert($pair)
    {
        $node = $this->root;
        if (empty($node->keys)) {
            $node->keys = [$pair];
            $node->count = count($node->keys);
            $this->writeNode($node);
            $this->saveNodeCount();
        } else if ($node->count == 2 * $this->min_degree - 1) {
            $temp = $this->createEmptyParentNode();
            $this->root = $temp;
            $this->swap($temp->id, $node->id);
            $temp->links[0] = $node->id;
            $this->bTreeSplitChild($temp, 0, $node);
            $this->insertNodeNotFull($temp, $pair);
        } else {
            $this->insertNodeNotFull($node, $pair);
        }
    }
    /**
     * Inserts a key-value pair in a leaf node that is not full. Searches for
     * the appropriate leaf node, splitting full nodes before descending
     * down the tree recursively.
     * @param object $node is the node from where the search for the leaf node
     * begins
     * @param array $pair is the key-value pair
     */
    public function insertNodeNotFull($node, $pair)
    {
        $key = $pair[0];
        $i = $node->count - 1;
        list($flag, $pos) = $this->binarySearch($node->keys, $key);
        if ($node->is_leaf == true) {
            if ($flag == 1) {
                $node->keys[$pos] = $pair;
                $this->writeNode($node);
            } else {
                while($i >= 0 && $node->keys[$i][0] > $key) {
                    $node->keys[$i + 1] = $node->keys[$i];
                    $i -= 1;
                }
                $node->keys[$i + 1] = $pair;
                $node->count = count($node->keys);
                $this->writeNode($node);
            }
        } else {
            if ($flag == 1) {
                $node->keys[$pos] = $pair;
                $this->writeNode($node);
            } else {
                while($i >= 0 && $node->keys[$i][0] > $key) {
                    $i -= 1;
                }
                $i += 1;
                $next_node = $this->readNode($node->links[$i]);
                if ($next_node->count == 2 * $this->min_degree - 1) {
                    $this->bTreeSplitChild($node, $i, $next_node);
                    if ($key > $node->keys[$i][0]) {
                        $i += 1;
                        $next_node = $this->readNode($node->links[$i]);
                    }
                }
                $this->insertNodeNotFull($next_node, $pair);
            }
        }
    }
    /**
     * Splits a full node into two child node. The median key-value pair is
     * added to the parent node of the node being split.
     *
     * @param object $parent is the parent node
     * @param int $i is the link to child node
     * @param object $child is the child node
     */
    public function bTreeSplitChild($parent, $i, $child)
    {
        $this->id_count += 1;
        $temp = new BTNode();
        $temp->id = $this->id_count;
        $this->saveNodeCount();
        $temp->is_leaf = $child->is_leaf;
        $temp->count = $this->min_degree - 1;
        for ($j = 0;$j < $this->min_degree - 1;$j++) {
            $temp->keys[$j] = $child->keys[$this->min_degree + $j];
        }
        if ($child->is_leaf == false) {
            for ($j = 0;$j < $this->min_degree;$j++) {
                $temp->links[$j] = $child->links[$this->min_degree + $j];
            }
        }
        for ($j = $parent->count;$j > $i;$j--) {
            $parent->links[$j + 1] = $parent->links[$j];
        }
        $parent->links[$j + 1] = $temp->id;
        for ($j = $parent->count - 1;$j >= $i;$j--) {
            $parent->keys[$j + 1] = $parent->keys[$j];
        }
        $parent->keys[$j + 1] = $child->keys[$this->min_degree - 1];
        $parent->count = count($parent->keys);
        $child->keys = array_slice($child->keys, 0, $this->min_degree - 1);
        if ($child->is_leaf == false) {
            $child->links = array_slice($child->links, 0, $this->min_degree);
        }
        $child->count = count($child->keys);
        $this->writeNode($child);
        $this->writeNode($temp);
        $this->writeNode($parent);
    }
    /**
     * Swaps value of two variables
     * @param $x is the first variable
     * @param $y is the second variable
     */
    public function swap(&$x, &$y)
    {
        $temp = $x;
        $x = $y;
        $y = $temp;
    }
    /**
     * Creates an empty non-leaf node
     * @return object $node is the non-leaf node
     */
    public function createEmptyParentNode()
    {
        $this->id_count += 1;
        $temp = new BTNode();
        $temp->id = $this->id_count;
        $this->saveNodeCount();
        $temp->is_leaf = false;
        return $temp;
    }
    /**
     * Performs binary search for a integer key on an array of integer key based
     * key-value pairs
     * @param array $keys is an array containing key-value pairs
     * @param int $key is the key
     * @return array containing flag indicating it the value was found or not,
     * and the position equal to, or nearest to the position of the key being
     * searched
     */
    public function binarySearch($keys, $key)
    {
        $low = 0;
        $high = count($keys) - 1;
        $flag = -1;
        while($high >= $low) {
            $middle = (int)floor(($high + $low) / 2);
            if ($key == $keys[$middle][0]) {
                $flag = 1;
                return [$flag, $middle];
            } else if ($key > $keys[$middle][0]) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }
        return [$flag, $low];
    }
    /**
     * Removes a key-value pair from the B-Tree
     * @param int $key associated with the key-value pair to be deleted
     */
    public function remove($key)
    {
        $this->delete($this->root, $key);
    }
    /**
     * Deletes a key-value pair from the B-Tree from a node.
     * Handles deletion from leaf node and internal node. If the key-value pair
     * is not found in an internal node. The recrusion descends to the root
     * of the sub-tree until a leaf node is encoutered that does not have the
     * key-value pair to be deleted.
     * @param object $node is from where the key search starts
     * @param int $key is the key to be deleted
     */
    public function delete($node, $key)
    {
        list($flag, $pos) = $this->binarySearch($node->keys, $key);
        if ($flag == 1 && $node->is_leaf == false) {
            $this->reArrange($node, $pos);
        }
        list($flag, $pos) = $this->binarySearch($node->keys, $key);
        if ($flag == 1 && $node->is_leaf == true) {
                $this->deleteFromLeaf($node, $pos);
        } else if ($flag == 1 && $node->is_leaf == false) {
                $this->deleteFromNonLeaf($node, $pos);
        } else if ($flag !== 1 && $node->is_leaf == false) {
            $sub_tree_root = $this->getDescendant($node, $pos);
            $this->delete($sub_tree_root, $key);
        }
    }
    /**
     * Shifts a key from a non-leaf root to it's child node using nodes
     * preceding and next to the key-value pair to be deleted. If the
     * preceding child node has atleast minimum MIN_DEGREE keys, a the last
     * key-value pair from the preceding node is moved to the position of the
     * key-value pair that is to be deleted. Otherwise the same process is done
     * using the first key-value pair of the child node next to the key-value
     * pair to be deleted.
     * @param object $node is the internal node containing the key-value pair to
     * be deleted
     * @param int $pos is the position of the key-value pair within $pos.
     */
    public function reArrange(&$node, $pos)
    {
        $pred_id = $node->links[$pos];
        $pred = $this->readNode($pred_id);
        $next_id = $node->links[$pos + 1];
        $next = $this->readNode($next_id);
        if ($pred->count >= $this->min_degree) {
            $this->adjustChildUsingLeftSiblingAndParent($node, $next, $pred,
                $pos + 1);
        } else if ($next->count >= $this->min_degree) {
            $this->adjustChildUsingRightSiblingAndParent($node, $pred,
                $next, $pos);
        }
    }
    /**
     * Deletes key-value pair from a leaf node in a B-Tree
     * @param object& $node is the leaf node containing the key-value pair
     * @param int $pos in node to delete
     */
    public function deleteFromLeaf(&$node, $pos)
    {
        if ($pos == $node->count - 1) {
            array_pop($node->keys);
            $node->count -= 1;
            $this->writeNode($node);
        } else {
            for ($i = $pos + 1; $i < $node->count; $i++) {
                $node->keys[$i - 1] = $node->keys[$i];
            }
            $node->keys = array_slice($node->keys, 0, $node->count - 1);
            $node->count -= 1;
            $this->writeNode($node);
        }
        if ($node == $this->root && $node->count == 0) {
            $this->deleteNodeFile("root");
            $this->deleteCount();
        }
    }
    /**
     * Deletes key-value pair from a non-leaf node in a B-Tree
     * @param object& $node is the non-leaf node containing the key-value pair
     * @param int $pos link position in node to delete
     */
    public function deleteFromNonLeaf(&$node, $pos)
    {
        $pred_id = $node->links[$pos];
        $pred = $this->readNode($pred_id);
        if ($pred->count >= $this->min_degree) {
            $pred_pair = $pred->keys[$pred->count - 1];
            $pred_key = $pred_pair[0];
            $this->delete($pred, $pred_key);
            $node->keys[$pos] = $pred_pair;
            $this->writeNode($node);
        } else {
            $next_id = $node->links[$pos + 1];
            $next = $this->readNode($next_id);
            if ($next->count >= $this->min_degree) {
                $next_pair = $next->keys[0];
                $next_key = $next_pair[0];
                $this->delete($next, $next_key);
                $node->keys[$pos] = $next_pair;
                $this->writeNode($node);
            } else {
                $node_pair = $node->keys[$pos];
                $node_key = $node_pair[0];
                $pred->keys[$pred->count] = $node_pair;
                $pred->count += 1;
                if ($pos == $node->count - 1) {
                    array_pop($node->keys);
                    array_pop($node->links);
                    $node->count -= 1;
                } else {
                    for ($i = $pos + 1;$i < $node->count;$i++) {
                        $node->keys[$i - 1] = $node->keys[$i];
                    }
                    $node->keys = array_slice($node->keys, 0, $node->count - 1);
                    for ($i = $pos + 2;$i <= $node->count;$i++) {
                        $node->links[$i - 1] = $node->links[$i];
                    }
                    $node->links = array_slice($node->links, 0, $node->count);
                    $node->count -= 1;
                }
                for ($i = 0;$i < $next->count;$i++) {
                    $pred->keys[$pred->count + $i] = $next->keys[$i];
                }
                if ($next->is_leaf == false) {
                    for ($i = 0;$i <= $next->count;$i++) {
                        $pred->links[$pred->count + $i] = $next->links[$i];
                    }
                }
                $pred->count += $next->count;
                $this->writeNode($pred);
                $this->deleteNodeFile($next->id);
                if ($node == $this->root && $node->count == 0) {
                    $old_id = $pred->id;
                    $pred->id = "root";
                    $this->root = $pred;
                    $this->deleteNodeFile($old_id);
                    $this->writeNode($this->root);
                } else {
                    $this->writeNode($node);
                }
                $this->delete($pred, $node_key);
            }
        }
    }
    /**
     * If the key to be deleted is not found in an internal node, finds the root
     * of the sub-tree that might contain the key to be deleted. If the node
     * contains atleast $min_degree number of keys, the node is returned.
     * Otherwise, the node is adjusted using one of its sibling nodes and the
     * parent node so that the resultant node has $min_degree keys.
     * @param object $parent is the parent node
     * @param int $pos is the link to the root of the sub-tree
     * @return object $child is the child node to which the recursion will
     * descend
     */
    public function getDescendant($parent, $pos)
    {
        $child_id = $parent->links[$pos];
        $child = $this->readNode($child_id);
        if ($child->count == $this->min_degree - 1) {
            $siblings = $this->getSiblings($parent, $pos);
            if ($siblings[0] !== -1 && $siblings[1] !== -1) {
                $pred_id = $siblings[0];
                $pred = $this->readNode($pred_id);
                if ($pred->count >= $this->min_degree) {
                    $this->adjustChildUsingLeftSiblingAndParent($parent, $child,
                        $pred, $pos);
                    return $child;
                } else {
                    $next_id = $siblings[1];
                    $next = $this->readNode($next_id);
                    if ($next->count >= $this->min_degree) {
                        $this->adjustChildUsingRightSiblingAndParent($parent,
                            $child, $next, $pos);
                        return $child;
                    } else {
                        if ($pred->count <= $next->count) {
                            $this->mergeChildWithParentKeyAndRightSibling(
                                $parent, $pred, $child, $pos - 1);
                            return $pred;
                        } else {
                            $this->mergeChildWithParentKeyAndRightSibling(
                                $parent, $child, $next, $pos);
                            return $child;
                        }
                    }
                }
            } else if ($siblings[0] !== -1) {
                $pred_id = $siblings[0];
                $pred = $this->readNode($pred_id);
                if ($pred->count >= $this->min_degree) {
                    $this->adjustChildUsingLeftSiblingAndParent($parent, $child,
                        $pred, $pos);
                    return $child;
                } else {
                    $this->mergeChildWithParentKeyAndRightSibling($parent,
                        $pred, $child, $pos - 1);
                    return $pred;
                }
            } else {
                $next_id = $siblings[1];
                $next = $this->readNode($next_id);
                if ($next->count >= $this->min_degree) {
                    $this->adjustChildUsingRightSiblingAndParent($parent,
                        $child, $next, $pos);
                    return $child;
                } else {
                    $this->mergeChildWithParentKeyAndRightSibling($parent,
                        $child, $next, $pos);
                    return $child;
                }
            }
        } else return $child;
    }
    /**
     * Gives a child node an extra key by moving a key from the parent to the
     * child node, and by moving a key from the child's left sibling to the
     * parent node
     * @param object $parent is the parent node
     * @param object $child is the child node
     * @param object $pred is the $child's left sibling node
     * @param $pos is the link from $parent to $child
     */
    public function adjustChildUsingLeftSiblingAndParent(&$parent, &$child,
        &$pred, $pos)
    {
        $pred_pair = array_pop($pred->keys);
        $pred_link = -1;
        if ($pred->is_leaf == false) {
            $pred_link = array_pop($pred->links);
        }
        $pred->count -= 1;
        $this->writeNode($pred);
        $parent_pair = $parent->keys[$pos - 1];
        for ($i = $child->count - 1;$i >= 0;$i--) {
            $child->keys[$i + 1] = $child->keys[$i];
        }
        $child->keys[0] = $parent_pair;
        if ($child->is_leaf == false) {
            for ($i = $child->count;$i >= 0;$i--) {
                $child->links[$i + 1] = $child->links[$i];
            }
            $child->links[0] = $pred_link;
        }
        $child->count += 1;
        $this->writeNode($child);
        $parent->keys[$pos - 1] = $pred_pair;
        $this->writeNode($parent);
    }
    /**
     * Gives a child node an extra key by moving a key from the parent to the
     * child node, and by moving a key from the child's right sibling to the
     * parent node
     * @param object& $parent is the parent node
     * @param object& $child is the child node
     * @param object& $next is the $child's right sibling node
     * @param int $pos is the link from $parent to $child
     */
    public function adjustChildUsingRightSiblingAndParent(&$parent, &$child,
        &$next, $pos)
    {
        $next_pair = $next->keys[0];
        $next_link = -1;
        for ($i = 1;$i < $next->count;$i++) {
            $next->keys[$i - 1] = $next->keys[$i];
        }
        $next->keys = array_slice($next->keys, 0, $next->count - 1);
        if ($next->is_leaf == false) {
            $next_link = $next->links[0];
            for ($i = 1;$i <= $next->count;$i++) {
                $next->links[$i - 1] = $next->links[$i];
            }
            $next->links = array_slice($next->links, 0, $next->count);
        }
        $next->count -= 1;
        $this->writeNode($next);
        $parent_pair = $parent->keys[$pos];
        $child->keys[$child->count] = $parent_pair;
        $child->count += 1;
        if ($child->is_leaf == false) {
            $child->links[$child->count] = $next_link;
        }
        $this->writeNode($child);
        $parent->keys[$pos] = $next_pair;
        $this->writeNode($parent);
    }
    /**
     * Merges the child node with it's right sibling. The separating key in the
     * parent node is added as the median key to the newly formed node
     * @param object $parent is the parent node
     * @param object $child is the child node
     * @param object $next is the $child's right sibling node
     * @param $pos is the link from $parent to $child
     */
    public function mergeChildWithParentKeyAndRightSibling(&$parent, &$child,
        &$next, $pos)
    {
        $parent_pair = $parent->keys[$pos];
        $child->keys[$child->count] = $parent_pair;
        $child->count += 1;
        for ($i = 0;$i < $next->count;$i++) {
            $child->keys[$child->count + $i] = $next->keys[$i];
        }
        if ($next->is_leaf == false) {
            for ($i = 0;$i <= $next->count;$i++) {
                $child->links[$child->count + $i] = $next->links[$i];
            }
        }
        $child->count = count($child->keys);
        $this->writeNode($child);
        $this->deleteNodeFile($next->id);
        if ($pos == $parent->count - 1) {
            array_pop($parent->keys);
            array_pop($parent->links);
            $parent->count -= 1;
        } else {
            for ($i = $pos + 1;$i < $parent->count;$i++) {
                $parent->keys[$i - 1] = $parent->keys[$i];
            }
            $parent->keys = array_slice($parent->keys, 0, $parent->count - 1);
            for ($i = $pos + 2;$i <= $parent->count;$i++) {
                $parent->links[$i - 1] = $parent->links[$i];
            }
            $parent->links = array_slice($parent->links, 0, $parent->count);
            $parent->count -= 1;
        }
        if ($parent == $this->root && $parent->count == 0) {
            $old_id = $child->id;
            $child->id = "root";
            $this->root = $child;
            $this->deleteNodeFile($old_id);
            $this->writeNode($this->root);
        } else {
            $this->writeNode($parent);
        }
    }
    /**
     * Gets the siblings ids based on link in parent node
     * @param object $parent is the parent node
     * @param int $pos is the link for which the siblings are to be found
     */
    public function getSiblings($parent, $pos)
    {
        $siblings = [];
        if ($pos > 0 && $pos < $parent->count) {
            $siblings[] = $parent->links[$pos - 1];
            $siblings[] = $parent->links[$pos + 1];
        } else if ($pos == 0) {
            $siblings[] = -1;
            $siblings[] = $parent->links[$pos + 1];
        } else {
            $siblings[] = $parent->links[$pos - 1];
            $siblings[] = -1;
        }
        return $siblings;
    }
}
/**
 * Class for B-Tree nodes
 */
class BTNode
{
    /**
     * Storage for id of a B-Tree node
     * @var int
     */
    public $id;
    /**
     * Flag for checking if node is a leaf node or internal node
     * @var boolean
     */
    public $is_leaf;
    /**
     * Storage for keeping track of node ids
     * @var int
     */
    public $count;
    /**
     * Storage for key-value pairs in a B-Tree node
     * @var array
     */
    public $keys;
    /**
     * Storage for links to child nodes in a B-Tree node
     * @var array
     */
    public $links;
    /**
     * Creates and initializes an empty leaf node with id -1
     * @var int
     */
    public function __construct()
    {
        $this->id = -1;
        $this->is_leaf = true;
        $this->count = 0;
        $this->keys = null;
        $this->links = null;
    }
}
