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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\WikiParser;

/**
 * Controller used to handle user group activities outside of
 * the admin panel setting. This either could be because the admin panel
 * is "collapsed" or because the request concerns a wiki page.
 *
 * @author Chris Pollett
 */
class GroupController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    public $activities = ["groupFeeds", "wiki"];
    /**
     * Associative array of $components activities for this controller
     * Components are collections of activities (a little like traits) which
     * can be reused.
     *
     * @var array
     */
    public static $component_activities = ["social" => ["groupFeeds", "wiki"]];
    /**
     * Used to process requests related to user group activities outside of
     * the admin panel setting. This either could be because the admin panel
     * is "collapsed" or because the request concerns a wiki page.
     */
    public function processRequest()
    {
        $data = [];
        if (!C\PROFILE) {
            return $this->configureRequest();
        }
        if (isset($_SESSION['USER_ID'])) {
            $user_id = $_SESSION['USER_ID'];
            $data['ADMIN'] = 1;
        } else {
            $user_id = $_SERVER['REMOTE_ADDR'];
        }
        $signin_model = $this->model("signin");
        $data['USERNAME'] = $signin_model->getUserName($user_id);
        $data['SCRIPT'] = "";
        $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user_id);
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user_id);
        if (!$token_okay) {
            $keep_fields = ["a", "arg", "f", "callback", "group_id",
                "just_group_id", "just_user_id", "just_thread", "limit", "n",
                "num", "page_id", "page_name", 'v'];
            $request = $_REQUEST;
            $_REQUEST = [];
            foreach ($keep_fields as $field) {
                if (isset($request[$field])) {
                    if ($field == "arg" && (!in_array($request[$field],
                        ["read", "pages", "media"]) )){
                        continue;
                    }
                    $_REQUEST[$field] =
                        $this->clean($request[$field], "string");
                }
            }
            $_REQUEST["c"] = "group";
        }
        $data = array_merge($data, $this->processSession());
        if (!isset($data['REFRESH'])) {
            $view = "group";
        } else {
            $view = $data['REFRESH'];
        }
        if ($data['ACTIVITY_METHOD'] == "wiki") {
            if (isset($data["VIEW"]) && !isset($data['REFRESH'])) {
                $view = $data["VIEW"];
            }
        } else if (isset($_REQUEST['f']) &&
            in_array($_REQUEST['f'], ["rss", "json", "serial"])) {
            $this->setupViewFormatOutput($_REQUEST['f'], $view, $data);
        }
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        $this->initializeAdFields($data);
        $this->displayView($view, $data);
    }
    /**
     * Used to perform the actual activity call to be done by the
     * group_controller.
     * processSession is called from @see processRequest, which does some
     * cleaning of fields if the CSRFToken is not valid. It is more likely
     * that that group_controller may be involved in such requests as it can
     * be invoked either when a user is logged in or not and for users with and
     * without accounts. processSession makes sure the $_REQUEST'd activity is
     * valid (or falls back to groupFeeds) then calls it. If someone uses
     * the Settings link to change the language or default number of feed
     * elements to view, this method sets up the $data variable so that
     * the back/cancel button on that page works correctly.
     */
    public function processSession()
    {
        if (isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
        } else {
            $activity = "groupFeeds";
        }
        $data = $this->call($activity);
        $data['ACTIVITY_CONTROLLER'] = "group";
        $data['ACTIVITY_METHOD'] = $activity; //for settings controller
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }
    /**
     * Responsible for setting the view for a feed if something other
     * than HTML (for example, RSS or JSON) is desired. It also
     * sets up any particular $data fields needed for displaying that
     * view correctly.
     *
     * @param string $format can be one of rss, json, or serialize,
     *      if different, default HTML GroupView used.
     * @param string& $view variable used to set the view in calling
     *     method
     * @param array& $data used to send data to the view for drawing
     */
    public function setupViewFormatOutput($format, &$view, &$data)
    {
        $data["QUERY"] = "groups:feed";
        if (isset($data["JUST_GROUP_ID"])) {
            $data["QUERY"] = "groups:just_group_id:".$data["JUST_GROUP_ID"];
        }
        if (isset($data["JUST_USER_ID"])) {
            $data["QUERY"] = "groups:just_user_id:".$data["JUST_USER_ID"];
        }
        if (isset($data["JUST_THREAD"])) {
            $data["QUERY"] = "groups:just_thread:".$data["JUST_THREAD"];
        }
        $data["its"] = 0;
        $num_pages = count($data["PAGES"]);
        $token = empty($data['admin']) ? "" :
            C\CSRF_TOKEN . "=".  $data[C\CSRF_TOKEN];
        for ($i = 0; $i < $num_pages; $i++) {
            $data["PAGES"][$i][self::URL] = htmlentities(B\feedsUrl(
                "thread", $data["PAGES"][$i]['PARENT_ID'],
                !empty($data['admin']), $data['CONTROLLER'])) . $token;
        }
        switch ($format) {
            case "rss":
                $view = "rss";
                break;
            case "json":
                $out_data = [];
                $out_data["language"] = L\getLocaleTag();
                $out_data["link"] =
                    C\NAME_SERVER."?f=$format&amp;q={$data['QUERY']}";
                $out_data["totalResults"] = $data['TOTAL_ROWS'];
                $out_data["startIndex"] = $data['LIMIT'];
                $out_data["itemsPerPage"] = $data['RESULTS_PER_PAGE'];
                foreach ($data['PAGES'] as $page) {
                    $item = [];
                    $item["title"] = $page[self::TITLE];
                    if (!isset($page[self::TYPE]) ||
                    (isset($page[self::TYPE])
                    && $page[self::TYPE] != "link")) {
                        $item["link"] = $page[self::URL];
                    } else {
                        $item["link"] = strip_tags($page[self::TITLE]);
                    }
                    $item["description"] = strip_tags($page[self::DESCRIPTION]);
                    if (isset($page[self::THUMB])
                    && $page[self::THUMB] != 'null') {
                        $item["thumb"] = $page[self::THUMB];
                    }
                    if (isset($page[self::TYPE])) {
                        $item["type"] = $page[self::TYPE];
                    }
                    $out_data['item'][] =$item;
                }
                $out = json_encode($out_data);
                //jsonp format
                if (isset($_REQUEST['callback'])) {
                    $callback = $this->clean($_REQUEST['callback'], "string");
                    $out = "// API callback\n$callback($out);";
                    header("Content-Type: text/javascript; charset=UTF-8");
                } else {
                    header("Content-Type: application/json");
                }
                e($out);
                exit();
            case "serial":
                e(serialize($out_data));
                exit();
        }
    }
}
