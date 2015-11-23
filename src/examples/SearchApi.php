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
namespace seekquarry\yioop\examples;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\controllers\SearchController;
/**
 * This file contains an example script to show the different
 * methods of the Yioop! search api
 */
// this example should be only run from the command-line
if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/** Calculate base directory of script @ignore
 * If you have Yioop! in a separate folder from your web-site
 * You should change BASE_DIR to the location of the Yioop! directory
 */
define("seekquarry\\yioop\\configs\\PARENT_DIR",
    substr(dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/src/examples")));
define("seekquarry\\yioop\\configs\\BASE_DIR", C\PARENT_DIR . "/src");
/** Load in global configuration settings; you need this*/
require_once C\BASE_DIR.'/configs/Config.php';
/** Load class autoloader*/
require_once C\PARENT_DIR.'/vendor/autoload.php';
if (!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/*
 * We now move the search API test index over to the WORK_DIRECTORY
 * if it isn't already there. In a real-world set-up a user would have
 * put a crawl into the WORK_DIRECTORY and that would be used to make the
 * query.
 */
$archive_timestamp = "1421025145";
$archive = C\BASE_DIR."/examples/0-Archive$archive_timestamp.zip";
$index_archive = C\BASE_DIR."/examples/IndexData$archive_timestamp.zip";
$extract_folder = C\CRAWL_DIR."/cache";
if (!file_exists($archive) ||
   !file_exists($index_archive)) {
   echo "\nSearch API test index doesn't exist, so can't run demo\n\n";
   exit();
}
if (class_exists("\ZipArchive")) {
    $zip = new \ZipArchive();
    $zip_h = $zip->open($archive);
    $zip->extractTo($extract_folder);
    $zip->close();
    $zip_h = $zip->open($index_archive);
    $zip->extractTo($extract_folder);
    $zip->close();
} else {
    exec("unzip $archive -d $extract_folder");
    exec("unzip $index_archive -d $extract_folder");
}
// Create a SearchController to do queries with
$controller = new SearchController();
/*
  Now we can do queries! First do a simple search on art and print the results
 */
echo "\n\n\nAn example of a query request with the search API:\n";
echo "Total rows numbers are high because by default grouping is done.\n";

$query = "art i:$archive_timestamp";
    /* i:1317414322 is the timestamp of the index to use.
       API requires that a default index be set even though the query might
       specify to use a different one. The query string we pass to the
       API can be anything you can type into Yioop! search box.
     */
$num_results = 10; // how many results to get back
$first_result_to_return = 0;
    // what ranked results show be the first to be returned (0 = highest ranked)
$data = $controller->queryRequest($query, $num_results,
    $first_result_to_return);
outputQueryData($data);

/*
   next we do a related search (as our index only has one page in it)
   the only related page is the page itself
 */
echo "\n\n\nAn example of making a related query request with the search API\n";
$url = "http://www.ucanbuyart.com/";
$num_results = 10; // how many results to get back
$first_result_to_return = 0;
$index_timestamp = $archive_timestamp;
$data = $controller->relatedRequest($url, $num_results,
    $first_result_to_return, $index_timestamp);
outputQueryData($data);
/*
   Finally, we give an example of requesting the cached version of
   a downloaded page...
 */
echo "\n\n\nAn example of making a cached of page request".
    " with the search API:\n";
$url = "http://www.ucanbuyart.com/";
$ui_flags = [];
$search_terms = "art classifieds"; // these words will be highlighted
$index_timestamp = $archive_timestamp;
$data = $controller->cacheRequest($url, $ui_flags,
    $search_terms, $index_timestamp);
echo $data;
/*
  We now delete the example index to clean-up our test. In real-life
  you wouldn't want to delete your query index after making one query
*/
unlinkRecursive(C\CRAWL_DIR."/cache/0-Archive$archive_timestamp");
unlinkRecursive(C\CRAWL_DIR."/cache/IndexData$archive_timestamp");
// demo over, bye-bye for now!
exit();
/**
 * Short function to pretty-print the data gotten back from a Yioop! query
 * @param array $data  what we got back from doing a query
 */
function outputQueryData($data)
{
    // Now to print out info in the result
    foreach ($data['PAGES'] as $page) {
        echo "============\n";
        echo "TITLE: ". trim($page[CrawlConstants::TITLE]). "\n";
        echo "URL: ". trim($page[CrawlConstants::URL]). "\n";
        echo "DESCRIPTION:".
            wordwrap(trim($page[CrawlConstants::DESCRIPTION]))."\n";
        echo "Rank: ".$page[CrawlConstants::DOC_RANK]."\n";
        echo "Relevance: ".$page[CrawlConstants::RELEVANCE]."\n";
        echo "Proximity: ".$page[CrawlConstants::PROXIMITY]."\n";
        echo "Score: ".$page[CrawlConstants::SCORE]."\n";
        echo "============\n\n";
    }

    echo "QUERY STATISTICS\n";
    echo "============\n";
    echo "LOW: ".$data['LIMIT']."\n";
    echo "HIGH: ".min($data['TOTAL_ROWS'],
        $data['LIMIT'] + $data['RESULTS_PER_PAGE'])."\n";
    echo "TOTAL ROWS: ".$data['TOTAL_ROWS']."\n";
}
/**
 * Recursively delete a directory
 *
 * @param string $dir Directory name
 * @param boolean $deleteRootToo Delete specified top directory as well
 */
function unlinkRecursive($dir, $deleteRootToo = true)
{
    traverseDirectory($dir, C\NS_LIB . "deleteFileOrDir", $deleteRootToo);
}
/**
 * Recursively traverse a directory structure and call a callback function
 *
 * @param string $dir Directory name
 * @param function $callback Function to call as traverse structure
 * @param boolean $rootToo do op on top-level directory as well
 */
function traverseDirectory($dir, $callback, $rootToo = true)
{
    if (!$dh = @opendir($dir)) {
        return;
    }
    while (false !== ($obj = readdir($dh))) {
        if ($obj == '.' || $obj == '..') {
            continue;
        }
        if (is_dir($dir . '/' . $obj)) {
            traverseDirectory($dir.'/'.$obj, $callback, true);
        }
        @$callback($dir . '/' . $obj);
    }
    closedir($dh);
    if ($rootToo) {
        @$callback($dir);
    }
}
