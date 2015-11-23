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
 * @author Snigdha Rao Parvatneni
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Library of functions used to fetch Git internal urls
 *
 * @author Chris Pollett
 */
class FetchGitRepositoryUrls implements CrawlConstants
{
    /**
     * A list of meta words that might be extracted from a query
     * @var array
     */
    public static $repository_types = ['git' => 'git', 'svn' => 'svn',
        'cvs' => 'cvs', 'vss' => 'vss', 'mercurial' => 'mercurial',
        'monotone' => 'monotone', 'bazaar' => 'bazaar', 'darcs' => 'darcs',
        'arch' => 'arch'];
    /**
     * An array used to store all the Git internal urls
     * @var array
     */
    public static $all_git_urls;
    /**
     * An indicator to tell no actions to be taken
     */
    const INDICATOR_NONE = 'none';
    /**
     * An indicator to indicate git repository
     */
    const INDICATOR_GIT = 'git';
    /**
     * An indicator to tell more git urls need to be fetched
     */
    const GIT_URL_CONTINUE = '@@@@';
    /**
     * An indicator to tell starting position of Git url to be used
     */
    const GIT_BASE_URL_START = 0;
    /**
     * An indicator to tell ending position of Git url to be used
     */
    const GIT_BASE_URL_END = '###';
    /**
     * A fixed component to be used with Git base url to form Git first url
     */
    const GIT_URL_EXTENSION = 'info/refs?service=git-upload-pack';
    /**
     * A fixed component to be used with Git urls to get next Git urls
     */
    const GIT_URL_OBJECT = 'objects/';
    /**
     * A fixed indicator used to get last letter of git base url
     */
    const GIT_BASE_URL_END_POSITION = -1;
    /**
     * A fixed indicator used to get last letter of git base url
     */
    const GIT_BASE_END_LETTER = 1;
    /**
     * A fixed position used to indicate starting point to fetch next Git url
     * from the master file
     */
    const GIT_NEXT_URL_START = 0;
    /**
     * A fixed position used to indicate ending position to fetch next Git url
     * from the master file
     */
    const GIT_NEXT_URL_END = 40;
    /**
     * A fixed indicator used to make desired Git folder structure from SHA hash
     */
    const GIT_URL_SPLIT = '/';
    /**
     * A fixed indicator used to mark starting position of SHA hash of Git
     * master tree
     */
    const GIT_MASTER_TREE_HASH_START = 16;
    /**
     * A fixed indicator used to mark ending position of SHA hash of Git
     * master tree
     */
    const GIT_MASTER_TREE_HASH_END = 41;
    /**
     * A fixed indicator used to mark starting position of SHA hash used to
     * indicate Git object folder
     */
    const GIT_FOLDER_NAME_START = 0;
    /**
     * A fixed indicator used to mark ending position of SHA hash used to
     * indicate Git object folder
     */
    const GIT_FOLDER_NAME_END = 2;
    /**
     * A fixed indicator used to mark starting position of SHA hash used to
     * indicate Git object file
     */
    const GIT_FILE_NAME_START = 2;
    /**
     * A fixed indicator used to mark ending position of SHA hash used to
     * indicate Git object file
     */
    const GIT_FILE_NAME_END = 38;
    /**
     * A fixed indicator used to indicate Git blob object
     */
     const GIT_BLOB_OBJECT = "blob";
    /**
     * A fixed indicator used to indicate Git tree object
     */
     const GIT_TREE_OBJECT = "tree";
    /**
     * A cURL time out parameter
     */
     const CURL_TIMEOUT = 5;
    /**
     * A cURL transfer parameter
     */
     const CURL_TRANSFER = 1;
    /**
     * Git blob access code starting position
     */
     const BLOB_ACCESS_CODE_START = 0;
    /**
     * Git blob access code ending position
     */
     const BLOB_ACCESS_CODE_END = 6;
    /**
     * Git tree access code starting position
     */
     const TREE_ACCESS_CODE_START = 0;
    /**
     * Git tree access code ending position
     */
     const TREE_ACCESS_CODE_END = 5;
    /**
     * Git SHA hash binary starting position
     */
     const SHA_HASH_BINARY_START = 0;
    /**
     * Git SHA hash binary ending position
     */
     const SHA_HASH_BINARY_END = 20;
    /**
     * A indicator for starting of Git file or folder name
     */
     const GIT_NAME_START = 0;
    /**
     * A indicator to represent next position after the access code in Git
     * blob object
     */
     const GIT_BLOB_NEXT = 7;
    /**
     * A indicator to represent next position after the access code in Git
     * tree object
     */
     const GIT_TREE_NEXT = 6;
    /**
     * A indicator to represent next position after the access code in Git
     * tree object
     */
     const HEX_NULL_CHARACTER = "\x00";
    /**
     * A indicator to represent that a git file is a blob file
     */
    const GIT_BLOB_INDICATOR = '100';
    /**
     * A indicator to represent that a git file is a tree file
     */
    const GIT_TREE_INDICATOR = '400';
    /**
     * Checks repository type based on extension
     *
     * @param string $extension to check
     * @return string $repository_type repository type based on the
     * extension of urls
     */
    public static function checkForRepository($extension)
    {
        if (isset(self::$repository_types[$extension])) {
            $repository_type = self::$repository_types[$extension];
        } else {
            $repository_type = self::INDICATOR_NONE;
        }
        return $repository_type;
    }
    /**
     * Sets up the seed sites with urls from a git repository (updates
     * these sites if have already started downloading from repository)
     *
     * @param string $url_to_check url needs to be processed
     * @param int $counter to keep track of number of urls processed
     * @param array $seeds store sites which are ready to be downloaded
     * @param array $repository_indicator indicates the type of the repository
     * @param array $site_pair contains original Git url crawled
     * @param int $total_git_urls number of urls in repository less those
     *      already processed
     * @param array $all_git_urls current list of urls from git repository
     * @return array $git_internal_urls containing all the internal Git urls
     * fetched from the parent Git url
     */
    public static function setGitRepositoryUrl($url_to_check, $counter, $seeds,
        $repository_indicator, $site_pair, $total_git_urls, $all_git_urls)
    {
        $git_internal_urls = [];
        if (!strpos($url_to_check, self::GIT_URL_CONTINUE)) {
            $git_next_urls = self::fetchGitRepositoryUrl($url_to_check);
            $all_git_urls = $git_next_urls;
            $total_git_urls = count($all_git_urls);
            $count_all_git_urls = $total_git_urls;
            if (intval(C\NUM_MULTI_CURL_PAGES) - $counter < $total_git_urls) {
                $total_git_urls = intval(C\NUM_MULTI_CURL_PAGES) - $counter;
            }
            for ($j = 0; $j < $total_git_urls; $j++) {
                $seeds[$counter][self::URL] = $git_next_urls[$j][2];
                $seeds[$counter][self::WEIGHT] = $site_pair['value'][1];
                $seeds[$counter][self::CRAWL_DELAY] = $site_pair['value'][2];
                $seeds[$counter][self::REPOSITORY_TYPE] = $repository_indicator;
                $seeds[$counter][self::FILE_NAME] = $git_next_urls[$j][0];
                $seeds[$counter][self::SHA_HASH] = $git_next_urls[$j][1];
                $counter++;
                $git_url_index = $j + 1;
                if ($git_url_index >= $count_all_git_urls) {
                    $repository_indicator = self::INDICATOR_NONE;
                } else {
                    $repository_indicator = self::INDICATOR_GIT;
                }
            }
            $counter--;
        } else {
            $position = strpos($url_to_check, self::GIT_URL_CONTINUE);
            $extension_string = substr($url_to_check, $position,
                strlen($url_to_check));
            $extension_count = explode(self::GIT_URL_CONTINUE,
                $extension_string);
            $git_index = intval(array_sum($extension_count));
            $url_to_check = substr($url_to_check, self::GIT_NEXT_URL_START,
                $position);
            $count_all_git_urls = $total_git_urls;
            if (intval(C\NUM_MULTI_CURL_PAGES) - $counter < $total_git_urls -
                $git_index) {
                $total_git_urls = intval(C\NUM_MULTI_CURL_PAGES) - $counter;
            } else {
                $total_git_urls = $total_git_urls - $git_index;
            }
            for ($j = 0; $j < $total_git_urls; $j++) {
                $seeds[$counter][self::URL] = $all_git_urls[$git_index][2];
                $seeds[$counter][self::WEIGHT] = $site_pair['value'][1];
                $seeds[$counter][self::CRAWL_DELAY] = $site_pair['value'][2];
                $seeds[$counter][self::REPOSITORY_TYPE] = $repository_indicator;
                $seeds[$counter][self::FILE_NAME] = $all_git_urls[$git_index]
                    [0];
                $seeds[$counter][self::SHA_HASH] = $all_git_urls[$git_index][1];
                $counter++;
                $git_index++;
                $git_url_index = $j + 1;
                if ($git_index >= $count_all_git_urls) {
                    $repository_indicator = self::INDICATOR_NONE;
                } else {
                    $repository_indicator = self::INDICATOR_GIT;
                }
            }
            $counter--;
        }
        $git_internal_urls['position'] = $counter;
        $git_internal_urls['index'] = $git_url_index;
        $git_internal_urls['seeds'] = $seeds;
        $git_internal_urls['indicator'] = $repository_indicator;
        $git_internal_urls['count'] = $count_all_git_urls;
        $git_internal_urls['all'] = $all_git_urls;
        return $git_internal_urls;
    }
    /**
     * Get the Git internal urls from the parent Git url
     *
     * @param string $url_to_check url needs to be processed
     * @return an array $git_next_urls consists of list of Git
     *      internal urls wich are called during the git clone
     */
    public static function fetchGitRepositoryUrl($url_to_check)
    {
        $compression_indicator = false;
        $position = strpos($url_to_check, self::GIT_BASE_URL_END);
        $git_base_url = substr($url_to_check, self::GIT_BASE_URL_START,
            $position);
        $base_url_last_letter = substr($git_base_url,
            self::GIT_BASE_URL_END_POSITION, self::GIT_BASE_END_LETTER);
        if ($base_url_last_letter != self::GIT_URL_SPLIT) {
            $git_base_url = $git_base_url . self::GIT_URL_SPLIT;
        }
        $git_first_url =  $git_base_url.self::GIT_URL_EXTENSION;
        $git_first_url_content = self::getNextGitUrl($git_first_url,
            $compression_indicator);
        $compression_indicator = true;
        $git_second_url = self::getGitMasterFile($git_first_url_content,
            $git_base_url);
        $git_second_url_content = self::getNextGitUrl($git_second_url,
            $compression_indicator);
        $git_third_url = self::getGitMasterTree($git_second_url_content,
            $git_base_url);
        $git_third_url_content = self::getNextGitUrl($git_third_url,
            $compression_indicator);
        $git_next_urls = self::getObjects($git_third_url_content,
            $git_base_url);
        return $git_next_urls;
    }
    /**
     * Get the Git second url which points to Git master tree structure
     *
     * @param string $git_first_url_content contents of Git first url
     * @param string $git_base_url common portion of Git urls
     * @return string $git_next_url consists of second internal Git url
     */
    public static function getGitMasterFile($git_first_url_content,
        $git_base_url)
    {
        $git_extended_url = substr($git_first_url_content,
            self::GIT_NEXT_URL_START, self::GIT_NEXT_URL_END);
        $first_split_git_extended_url = substr($git_extended_url,
            self::GIT_FOLDER_NAME_START, self::GIT_FOLDER_NAME_END);
        $second_split_git_extended_url = substr($git_extended_url,
            self::GIT_FILE_NAME_START, self::GIT_FILE_NAME_END);
        $git_url_connector = $first_split_git_extended_url .
            self::GIT_URL_SPLIT . $second_split_git_extended_url;
        $git_next_url = $git_base_url . self::GIT_URL_OBJECT .
            $git_url_connector;
        return $git_next_url;
    }
    /**
     * Get the Git third url which contains the information about the
     *    organization of entire git repository
     *
     * @param string $git_second_url_content contents of Git second url
     * @param string $git_base_url common portion of git urls
     * @return string $git_next_url consists of third internal git url
     */
    public static function getGitMasterTree($git_second_url_content,
        $git_base_url)
    {
        $git_master_tree_hash = substr($git_second_url_content,
            self::GIT_MASTER_TREE_HASH_START, self::GIT_MASTER_TREE_HASH_END);
        $git_object_folder_name = substr($git_master_tree_hash,
            self::GIT_FOLDER_NAME_START, self::GIT_FOLDER_NAME_END);
        $git_object_file_name = substr($git_master_tree_hash,
            self::GIT_FILE_NAME_START, self::GIT_FILE_NAME_END);
        $git_object_path = $git_object_folder_name . self::GIT_URL_SPLIT .
            $git_object_file_name;
        $git_next_url = $git_base_url . self::GIT_URL_OBJECT . $git_object_path;
        return $git_next_url;
    }
    /**
     * Get the Git content from url which will be used to get the
     *    next git url
     *
     * @param string $git_url git url to extract contents from it
     * @param string $compression_indicator indicator for compress and
     * uncompress contents
     * @return string $git_object_content consists contents extracted from the
     * url
     */
    public static function getNextGitUrl($git_url, $compression_indicator)
    {
        if (!$compression_indicator) {
            $git_object_compress_content = self::getGitdata($git_url);
            $git_object_content = $git_object_compress_content;
        } else {
            $git_object_compress_content = self::getGitdata($git_url);
            $git_object_uncompress_content = gzuncompress(
                $git_object_compress_content);
            $git_object_content = $git_object_uncompress_content;
        }
        return $git_object_content;
    }
    /**
     * Get the Git blob and tree objects
     *
     * @param string $git_object_content compressed content of git master tree
     *    file
     * @param string $git_base_url common content of git url
     * @return array $blob_url contains information and url for git blob objects
     */
    public static function getObjects($git_object_content, $git_base_url)
    {
        $blob_url = [];
        $temp_git_object_content['content'] = $git_object_content;
        for ($i = 0; $i < strlen($git_object_content); $i++) {
            $blob_position = strpos($temp_git_object_content['content'],
                self::GIT_BLOB_INDICATOR);
            $tree_position = strpos($temp_git_object_content['content'],
                self::GIT_TREE_INDICATOR);
            $git_object_positions = self::checkPosition($blob_position,
                $tree_position, $git_object_content);
            $blob_position = $git_object_positions['blob'];
            $tree_position = $git_object_positions['tree'];
            if ($blob_position < $tree_position) {
                $temp_git_object_content = self::readBlobSha(
                    $temp_git_object_content['content'], $blob_position,
                        strlen($temp_git_object_content['content']),
                            $git_base_url);
            }
            else if ($tree_position < $blob_position) {
                $temp_git_object_content = self::readTreeSha(
                    $temp_git_object_content['content'], $tree_position,
                        strlen($temp_git_object_content['content']),
                            $git_base_url);
            }
            $i = strlen($temp_git_object_content['content']);
            $i = strlen($git_object_content) - $i;
            if ($temp_git_object_content['value']['indicator'] !=
                self::GIT_TREE_OBJECT) {
                $blob_details[0] = $temp_git_object_content['value']['name'];
                $blob_details[1] = $temp_git_object_content['value']['hash'];
                $blob_details[2] = $temp_git_object_content['value']['url'];
                $blob_url[] = $blob_details;
            }
            if ($temp_git_object_content['indicator'] != self::GIT_BLOB_OBJECT){
                for ($k = 0; $k < count($temp_git_object_content['indicator']);
                    $k++) {
                    $blob_details[0] = $temp_git_object_content['indicator'][$k]
                        [0];
                    $blob_details[1] = $temp_git_object_content['indicator'][$k]
                        [1];
                    $blob_details[2] = $temp_git_object_content['indicator'][$k]
                        [2];
                    $blob_url[] = $temp_git_object_content['indicator'][$k];
                }
            }
        }
        return $blob_url;
    }
    /**
     * checks the position of access code for null values
     *
     * @param string $git_blob_position first occuence of git blob access code
     * @param string $git_tree_position first occuence of git tree access code
     * @param string $git_object_content compressed content of git master tree
     * @return array $git_object_positions length of the compressed content
     *    afterthe access code
     */
    public static function checkPosition($git_blob_position, $git_tree_position,
        $git_object_content)
    {
        $git_object_positions = [];
        if (is_bool($git_blob_position) === true) {
            $git_blob_position = strlen($git_object_content);
        }
        if (is_bool($git_tree_position) === true) {
            $git_tree_position = strlen($git_object_content);
        }
        $git_object_positions['blob'] = $git_blob_position;
        $git_object_positions['tree'] = $git_tree_position;
        return $git_object_positions;
    }
    /**
     * Get the details of the blob file i.e blob file name, sha hash and content
     *
     * @param string $git_object_content compressed content of git master tree
     * @param string $blob_position first occuence of git blob access code
     *    in $content
     * @param string $length length of the compressed content of git master tree
     * @param string $git_base_url common portion of git url
     * @return array $git_blob_content contains details of git blob object
     */
    public static function readBlobSha($git_object_content, $blob_position,
        $length, $git_base_url)
    {
        $git_blob_content = [];
        $blob_values = [];
        $temp_git_content = substr($git_object_content, $blob_position,
            $length);
        $access_code = substr($temp_git_content, self::BLOB_ACCESS_CODE_START,
            self::BLOB_ACCESS_CODE_END);
        $blob_values['code'] = $access_code;
        $temp_git_content = substr($temp_git_content, self::GIT_BLOB_NEXT,
            $length);
        $temp_position = strpos($temp_git_content, self::HEX_NULL_CHARACTER);
        $file_name = substr($temp_git_content, self::GIT_NAME_START,
            $temp_position);
        $blob_values['name'] = $file_name;
        $temp_git_content = substr($temp_git_content, $temp_position + 1,
            $length);
        $sha_binary = substr($temp_git_content, self::SHA_HASH_BINARY_START,
            self::SHA_HASH_BINARY_END);
        $sha_hash = bin2hex($sha_binary);
        $blob_values['hash'] = $sha_hash;
        $temp_git_content = substr($temp_git_content, self::SHA_HASH_BINARY_END,
            $length);
        $blob_url = self::urlMaker($sha_hash, $git_base_url);
        $blob_values['url'] = $blob_url;
        $blob_values['indicator'] = self::GIT_BLOB_OBJECT;
        $git_blob_content['value'] = $blob_values;
        $git_blob_content['content'] = $temp_git_content;
        $git_blob_content['indicator'] = self::GIT_BLOB_OBJECT;
        return $git_blob_content;
    }
    /**
     * Get the details of the tree file i.e folder name, sha hash and
     * blob url inside the tree
     *
     * @param string $git_object_content compressed content of git master tree
     * @param string $tree_position first occuence of git tree access code in
     * the $content
     * @param string $length length of the compressed content of git master tree
     * @param string $git_base_url common portion of git url
     * @return array $git_tree_content contains details of git blob object
     */
    public static function readTreeSha($git_object_content, $tree_position,
        $length, $git_base_url)
    {
        $git_tree_content = [];
        $tree_values = [];
        $temp_git_content = substr($git_object_content, $tree_position,
            $length);
        $access_code = substr($temp_git_content, self::TREE_ACCESS_CODE_START,
            self::TREE_ACCESS_CODE_END);
        $tree_values['code'] = $access_code;
        $temp_git_content = substr($temp_git_content, self::GIT_TREE_NEXT,
            $length);
        $temp_position = strpos($temp_git_content, self::HEX_NULL_CHARACTER);
        $folder_name = substr($temp_git_content, self::GIT_NAME_START,
            $temp_position);
        $tree_values['name'] = $folder_name;
        $temp_git_content = substr($temp_git_content, $temp_position + 1,
            $length);
        $sha_binary = substr($temp_git_content, self::SHA_HASH_BINARY_START,
            self::SHA_HASH_BINARY_END);
        $sha_hash = bin2hex($sha_binary);
        $tree_values['hash'] = $sha_hash;
        $tree_values['indicator'] = self::GIT_TREE_OBJECT;
        $temp_git_content = substr($temp_git_content, self::SHA_HASH_BINARY_END,
            $length);
        $blob_url = self::checkNestedStructure($sha_hash, $git_base_url);
        $git_tree_content['value'] = $tree_values;
        $git_tree_content['content'] = $temp_git_content;
        $git_tree_content['indicator'] = $blob_url;
        return $git_tree_content;
    }
    /**
     * Checks the nested structure inside git tree object
     *
     * @param string $sha_hash sha of the git tree object
     * @param string $git_base_url common portion of the parent git url
     * @return string $blob_url contains url of the blob file inside the folder
     */
    public static function checkNestedStructure($sha_hash, $git_base_url)
    {
        $url = self::urlMaker($sha_hash, $git_base_url);
        $git_compressed_content = self::getGitData($url);
        $git_uncompressed_content = gzuncompress($git_compressed_content);
        $blob_url = self::getObjects($git_uncompressed_content, $git_base_url);
        return $blob_url;
    }
    /**
     * Makes the git clone internal url for blob objects
     *
     * @param string $sha_hash of the git blob object
     * @param string $git_base_url common portion of git url
     * @return string $git_object_url contains the complete url of the blob file
     */
    public static function urlMaker($sha_hash, $git_base_url)
    {
        $git_object_folder = substr($sha_hash, self::GIT_FOLDER_NAME_START,
            self::GIT_FOLDER_NAME_END);
        $git_object_file = substr($sha_hash, self::GIT_FILE_NAME_START,
            self::GIT_FILE_NAME_END);
        $git_object_path = $git_object_folder . self::GIT_URL_SPLIT .
            $git_object_file;
        $git_object_url = $git_base_url . self::GIT_URL_OBJECT .
            $git_object_path;
        return $git_object_url;
    }
    /**
     * Makes the cURL call to get the contents
     *
     * @param string $git_url url to dowmload the contents
     * @return string $git_content actual content of the git url
     */
    public static function getGitData($git_url)
    {
        $ch = curl_init();
        $timeout = self::CURL_TIMEOUT;
        curl_setopt($ch, CURLOPT_URL, $git_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, self::CURL_TRANSFER);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $git_content = curl_exec($ch);
        curl_close($ch);
        return $git_content;
    }
}
