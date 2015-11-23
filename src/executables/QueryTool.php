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
namespace seekquarry\yioop\executables;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\FileCache;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\controllers\SearchController;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/** so can output plans */
define("seekquarry\\yioop\\configs\\QUERY_STATISTICS", true);
/** Loads common constants for web crawling*/
require_once __DIR__."/../library/LocaleFunctions.php";
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/**
 * Tool to provide a command line query interface to indexes stored in
 * Yioop! database. Running with no arguments gives a help message for
 * this tool.
 *
 * @author Chris Pollett
 */
class QueryTool implements CrawlConstants
{
    /**
     * Initializes the QueryTool, for now does nothing
     */
    public function __construct()
    {
    }
    /**
     * Runs the QueryTool on the supplied command line arguments
     */
    public function start()
    {
        global $argv;
        if (!isset($argv[1])) {
            $this->usageMessageAndExit();
        }
        $query = $argv[1];
        $results_per_page = (isset($argv[2])) ? $argv[2] : 10;
        $limit = (isset($argv[3])) ? $argv[3] : 0;
        L\setLocaleObject((isset($argv[4])) ? $argv[4] : C\DEFAULT_LOCALE);
        $start_time = microtime(true);
        $controller = new SearchController();
        $data = $controller->queryRequest($query, $results_per_page, $limit);
        if (isset($argv[2]) && ($argv[2] == "plan" || $argv[2] == "explain")) {
            echo "\n" . $controller->model("phrase")->db->query_log[0]["PLAN"]
                ."\n";
            exit();
        }
        if (!isset($data['PAGES'])) {
            $data['PAGES'] = [];
        }
        foreach ($data['PAGES'] as $page) {
            echo "============\n";
            echo "TITLE: ". trim($page[self::TITLE]). "\n";
            echo "URL: ". trim($page[self::URL]). "\n";
            echo "IPs: ";
            if (isset($page[self::IP_ADDRESSES])) {
                foreach ($page[self::IP_ADDRESSES] as $address) {
                    echo $address." ";
                }
            }
            echo "\n";
            echo "DESCRIPTION: ".wordwrap(trim($page[self::DESCRIPTION]))."\n";
            echo "Rank: ".$page[self::DOC_RANK]."\n";
            echo "Relevance: ".$page[self::RELEVANCE]."\n";
            echo "Proximity: ".$page[self::PROXIMITY]."\n";
            echo "Score: ".$page[self::SCORE]."\n";
            echo "============\n\n";
        }
        $data['ELAPSED_TIME'] = L\changeInMicrotime($start_time);
        echo "QUERY STATISTICS\n";

        echo "============\n";
        echo "ELAPSED TIME: ".$data['ELAPSED_TIME']."\n";
        if (isset($data['LIMIT'])) {
            echo "LOW: ".$data['LIMIT']."\n";
        }
        if (isset($data['HIGH'])) {
            echo "HIGH: ".min($data['TOTAL_ROWS'],
                $data['LIMIT'] + $data['RESULTS_PER_PAGE'])."\n";
        }
        if (isset($data['TOTAL_ROWS'])) {
            echo "TOTAL ROWS: ".$data['TOTAL_ROWS']."\n";
        }
        if (isset($data['ERROR'])) {
            echo $data['ERROR']."\n";
        }
    }
    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    public function usageMessageAndExit()
    {
        echo "\nQueryTool.php is used to run a Yioop";
        echo " query from the command line.\n For example,\n";
        echo "  php QueryTool.php 'chris pollett' \n returns results ".
            "from the default index of a search on 'chris pollett'.\n";
        echo "The general command format is:\n";
        echo "  php QueryTool.php query num_results start_num lang_tag\n\n";
        echo "QueryTool.php can also be used to explain the plan by which\n";
        echo "Yioop will compute query results. For this usage one types:\n";
        echo "  php QueryTool.php query plan\n";
        echo "or\n";
        echo "  php QueryTool.php query explain\n";
        echo "For example,";
        echo "  php QueryTool.php 'chris pollett' explain\n";
        exit();
    }
}
$query_tool =  new QueryTool();
$query_tool->start();
