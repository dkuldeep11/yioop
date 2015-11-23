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

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\PageRuleParser;
use seekquarry\yioop\library\Classifiers\Classifier;
use seekquarry\yioop\library\CrawlDaemon;

/**
 * Controller used to handle admin functionalities such as
 * modify login and password, CREATE, UPDATE,DELETE operations
 * for users, roles, locale, and crawls
 *
 * @author Chris Pollett
 */
class AdminController extends Controller implements CrawlConstants
{
    /**
     * Says which activities (roughly methods invoke from the web) this
     * controller will respond to (note: more activities will be loaded from
     * components)
     * @var array
     */
    public $activities = ["crawlStatus", "machineStatus"];
    /**
     * An array of activities which are periodically updated within other
     * activities that they live. For example, within manage crawl,
     * the current crawl status is updated every 20 or so seconds.
     * @var array
     */
    public $status_activities = ["crawlStatus", "machineStatus"];
    /**
     * Associative array of $components activities for this controller
     * Components are collections of activities (a little like traits) which
     * can be reused.
     *
     * @var array
     */
    public static $component_activities = [
        "accountaccess" =>
            ["signin", "manageAccount", "manageUsers", "manageRoles"],
        "crawl" => ["manageCrawls", "manageClassifiers", "pageOptions",
            "resultsEditor", "searchSources"],
        "social" => ["manageGroups", "groupFeeds", "mixCrawls", "wiki"],
        "advertisement" => ["manageCredits", "manageAdvertisements"],
        "system" => ["manageMachines", "manageLocales", "serverSettings",
            "security", "appearance", "configure"]
    ];
    /**
     * This is the main entry point for handling requests to administer the
     * Yioop/SeekQuarry site
     *
     * ProcessRequest determines the type of request (signin , manageAccount,
     * etc) is being made.  It then calls the appropriate method to handle the
     * given activity. Finally, it draws the relevant admin screen
     */
    public function processRequest()
    {
        $data = [];
        if (!C\PROFILE) {
            return $this->configureRequest();
        }
        $view = "signin";
        if (isset($_SESSION['USER_ID'])) {
            $user = $_SESSION['USER_ID'];
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
        }
        $data['SCRIPT'] = "";
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user);
        $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user);
        if ($token_okay || isset($_REQUEST['u'])) {
            if (isset($_SESSION['USER_ID']) && !isset($_REQUEST['u'])) {
                $data = array_merge($data, $this->processSession());
                if (!isset($data['REFRESH'])) {
                    $view = "admin";
                } else {
                    $view = $data['REFRESH'];
                }
            } else if (!isset($_SESSION['REMOTE_ADDR'])
                && !isset($_REQUEST['u'])) {
                $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                    tl('admin_controller_need_cookies')."</h1>');";
                unset($_SESSION['USER_ID']);
            } else if ($this->checkSignin()) {
                if (!isset($_SESSION['AUTH_COUNT']) ||
                    isset($_REQUEST['round_num']) &&
                    $_REQUEST['round_num'] < $_SESSION['AUTH_COUNT']) {
                    $_SESSION['AUTH_COUNT'] = 0;
                }
                if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) {
                    $_SESSION['AUTH_COUNT']++;
                    if ($_SESSION['AUTH_COUNT'] != C\FIAT_SHAMIR_ITERATIONS) {
                        $_SESSION['SALT_VALUE'] = rand(0, 1);
                        $salt_value = $_SESSION['SALT_VALUE'];
                        if ($_SESSION['AUTH_COUNT'] ==
                            C\FIAT_SHAMIR_ITERATIONS - 1) {
                            $salt_value = "done".$salt_value;
                        }
                        e($salt_value);
                        exit();
                    }
                } else {
                    /*
                        if not doing Fiat Shamir pretend have gone through all
                        needed iterations
                     */
                    $_SESSION['AUTH_COUNT'] = C\FIAT_SHAMIR_ITERATIONS;
                }
                $_SESSION['USER_NAME'] = $_REQUEST['u'];
                // successful login.
                if ($_SESSION['AUTH_COUNT'] == C\FIAT_SHAMIR_ITERATIONS) {
                    $_SESSION['AUTH_COUNT'] = 0;
                    $user_id = $this->model("signin")->getUserId(
                        $this->clean($_REQUEST['u'], "string"));
                    $session = $this->model("user")->getUserSession($user_id);
                    if (isset($_SESSION['LAST_ACTIVITY']) &&
                        is_array($_SESSION['LAST_ACTIVITY'])) {
                        $_REQUEST = array_merge($_REQUEST,
                            $_SESSION['LAST_ACTIVITY']);
                    }
                    if (is_array($session)) {
                        $_SESSION = $session;
                    }
                    $allowed_activities =
                        $this->model("user")->getUserActivities($user_id);
                    // now don't want to use remote address anymore
                    if (!$allowed_activities) {
                        unset($_SESSION['USER_ID']);
                        unset($_REQUEST);
                        $_REQUEST['c'] = "admin";
                        return $this->redirectWithMessage(
                            tl('admin_controller_account_not_active'));
                    } else {
                        $_SESSION['USER_ID'] = $user_id;
                        $_REQUEST[C\CSRF_TOKEN] = $this->generateCSRFToken(
                            $_SESSION['USER_ID']);
                        return $this->redirectWithMessage(
                            tl('admin_controller_login_successful'));
                    }
                }
            } else {
                $alt_message = false;
                $_SESSION['AUTH_COUNT'] = 0;
                if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION
                    && !isset($_SESSION['AUTH_FAILED'])) {
                    if (isset($_REQUEST['round_num'])) {
                        $_SESSION['SALT_VALUE'] = 1;
                        $_SESSION['AUTH_FAILED'] = -1;
                        e($_SESSION['AUTH_FAILED']);
                        exit();
                    } else {
                        unset($_SESSION['USER_ID']);
                        unset($_SESSION['AUTH_FAILED']);
                        unset($_REQUEST);
                        $_REQUEST['c'] = "admin";
                        return $this->redirectWithMessage(
                            tl('admin_controller_no_back_button'));
                    }
                }
                if (!$alt_message) {
                    unset($_SESSION['USER_ID']);
                    unset($_SESSION['AUTH_FAILED']);
                    $login_attempted = false;
                    if (isset($_REQUEST['u'])) {
                        $login_attempted = true;
                    }
                    unset($_REQUEST);
                    $_REQUEST['c'] = "admin";
                    if ($login_attempted) {
                        return $this->redirectWithMessage(
                            tl('admin_controller_login_failed'));
                    }
                }
            }
        } else if ($this->checkCSRFToken(C\CSRF_TOKEN, "config")) {
            $data['SCRIPT'] = "doMessage('<h1 class=\"red\" >".
                tl('admin_controller_login_to_config')."</h1>')";
        } else if (isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->status_activities)) {
            e("<p class='red'>".
                tl('admin_controller_status_updates_stopped')."</p>");
            exit();
        }
        if ($token_okay && isset($_SESSION["USER_ID"])) {
            $data["ADMIN"] = true;
        } else {
            $data["ADMIN"] = false;
        }
        if ($view == 'signin') {
            if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) {
                $data['AUTH_ITERATION'] = C\FIAT_SHAMIR_ITERATIONS;
                $data['FIAT_SHAMIR_MODULUS'] = C\FIAT_SHAMIR_MODULUS;
                $_SESSION['SALT_VALUE'] = rand(0, 1);
                $data['INCLUDE_SCRIPTS'] = ["zkp", "big_int", "sha1"];
            } else {
                 unset($_SESSION['SALT_VALUE']);
            }
            $data[C\CSRF_TOKEN] = $this->generateCSRFToken(
                $_SERVER['REMOTE_ADDR']);
            $data['SCRIPT'] .= "var u; if ((u = elt('username')) && u.focus) ".
               "u.focus();";
        }
        $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        if (!isset($data["USERNAME"]) && isset($_SESSION['USER_ID'])) {
            $signin_model = $this->model("signin");
            $data['USERNAME'] = $signin_model->getUserName(
                $_SESSION['USER_ID']);
        }
        $this->initializeAdFields($data, false);
        $this->displayView($view, $data);
    }
    /**
     * If there is no profile/work directory set up then this method
     * get called to by pass any login and go to the configure screen.
     * The configure screen is only displayed if the user is connected
     * from localhost in this case
     */
    public function configureRequest()
    {
        $data = $this->processSession();
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken("config");
        $this->displayView("admin", $data);
    }
    /**
     * Checks whether the user name and password sent presumably by the signin
     * form match a user in the database
     *
     * @return bool whether they do or not
     */
    public function checkSignin()
    {
        if (C\AUTHENTICATION_MODE == C\NORMAL_AUTHENTICATION) {
            $result = false;
            if (isset($_REQUEST['u']) && isset($_REQUEST['p']) ) {
                $result = $this->model("signin")->checkValidSignin(
                    $this->clean($_REQUEST['u'], "string"),
                    $this->clean($_REQUEST['p'], "string") );
            }
        } else {
            if (!isset($_REQUEST['u']) || !isset($_REQUEST['x']) ||
                !isset($_REQUEST['y']) || !isset($_SESSION['SALT_VALUE']) ||
                isset($_SESSION['AUTH_FAILED'])) {
                $result = false;
            } else {
                $result = $this->model("signin")->checkValidSigninForZKP(
                    $this->clean($_REQUEST['u'], "string"),
                    $this->clean($_REQUEST['x'], "string"),
                    $this->clean($_REQUEST['y'], "string"),
                    $_SESSION['SALT_VALUE'], C\FIAT_SHAMIR_MODULUS);
            }
            if (!$result) {
                $_SESSION['AUTH_COUNT'] = 0;
            }
        }
        return $result;
    }
    /**
     * Determines the user's current allowed activities and current activity,
     * then calls the method for the latter.
     *
     * This is called from {@link processRequest()} once a user is logged in.
     *
     * @return array $data the results of doing the activity for display in the
     *     view
     */
    public function processSession()
    {
        $allowed = false;
        if (!C\PROFILE || (C\nsdefined("FIX_NAME_SERVER") &&
            C\FIX_NAME_SERVER)) {
            $activity = "configure";
        } else if (isset($_REQUEST['a']) &&
            in_array($_REQUEST['a'], $this->activities)) {
            $activity = $_REQUEST['a'];
        } else {
            $activity = "manageAccount";
        }
        $activity_model = $this->model("activity");
        if (!C\PROFILE) {
            $allowed_activities = [ [
                "ACTIVITY_NAME" =>
                $activity_model->getActivityNameFromMethodName($activity),
                'METHOD_NAME' => $activity]];
            $allowed = true;
        } else {
            $allowed_activities =
                 $this->model("user")->getUserActivities($_SESSION['USER_ID']);
        }
        if ($allowed_activities == []) {
            $data['INACTIVE'] = true;
            return $data;
        }
        foreach ($allowed_activities as $allowed_activity) {
            if ($activity == $allowed_activity['METHOD_NAME']) {
                 $allowed = true;
            }
            if ($allowed_activity['METHOD_NAME'] == "manageCrawls" &&
                $activity == "crawlStatus") {
                $allowed = true;
            }
            if ($allowed_activity['METHOD_NAME'] == "manageMachines" &&
                $activity == "machineStatus") {
                $allowed = true;
            }
            if ($allowed_activity['METHOD_NAME'] == "groupFeeds" &&
                $activity == "wiki") {
                $allowed = true;
            }
        }
        // business role only allows managing advertisements;
        if(!$allowed && $activity == "manageAccount") {
            $activity = $allowed_activities[0]['METHOD_NAME'];
            $_REQUEST["a"] = $activity;
            $allowed = true;
        }
        //for now we allow anyone to get crawlStatus
        if ($allowed) {
            $data = $this->call($activity);
            $data['ACTIVITY_METHOD'] = $activity; //for settings controller
            if (!is_array($data)) {
                $data = [];
            }
            $data['ACTIVITIES'] = $allowed_activities;
        }
        if (!in_array($activity, $this->status_activities)) {
            $name_activity = $activity;
            if ($activity == "wiki") {
                $name_activity = "groupFeeds";
            }
            $data['CURRENT_ACTIVITY'] =
                $activity_model->getActivityNameFromMethodName($name_activity);
        }
        $data['COMPONENT_ACTIVITIES'] = [];
        $component_translations = [
            "accountaccess" => tl('admin_controller_account_access'),
            "social" => tl('admin_controller_social'),
            "crawl" => tl('admin_controller_crawl_settings'),
            "system" => tl('admin_controller_system_settings'),
            "advertisement" => tl('admin_controller_advertisement')
        ];
        if (isset($data["ACTIVITIES"])) {
            foreach (self::$component_activities as $component => $activities) {
                foreach ($data["ACTIVITIES"] as $activity) {
                    if (in_array($activity['METHOD_NAME'], $activities)) {
                        $data['COMPONENT_ACTIVITIES'][
                            $component_translations[$component]][] =
                            $activity;
                    }
                }
            }
        }
        return $data;
    }
    /**
     * Used to handle crawlStatus REST activities requesting the status of the
     * current web crawl
     *
     * @return array $data contains crawl status of current crawl as well as
     *     info about prior crawls and which crawl is being used for default
     *     search results
     */
    public function crawlStatus()
    {
        $data = [];
        $data['REFRESH'] = "crawlstatus";
        $crawl_model = $this->model("crawl");
        $crawl_time = $crawl_model->getCurrentIndexDatabaseName();
        if (isset($crawl_time) ) {
            $data['CURRENT_INDEX'] = (int)$crawl_time;
        } else {
            $data['CURRENT_INDEX'] = -1;
        }
        $machine_urls = $this->model("machine")->getQueueServerUrls();
        list($stalled, $status, $data['RECENT_CRAWLS']) =
            $crawl_model->combinedCrawlInfo($machine_urls);
        if ($stalled) {
            $crawl_model->sendStopCrawlMessage($machine_urls);
        }
        $data = array_merge($data, $status);
        $data["CRAWL_RUNNING"] = false;
        if (!empty($data['CRAWL_TIME'])) {
            //erase from previous crawl list any active crawl
            $num_crawls = count($data['RECENT_CRAWLS']);
            for ($i = 0; $i < $num_crawls; $i++) {
                if ($data['RECENT_CRAWLS'][$i]['CRAWL_TIME'] ==
                    $data['CRAWL_TIME']) {
                    $data['RECENT_CRAWLS'][$i] = false;
                }
            }
            $data["CRAWL_RUNNING"] = true;
            $data['RECENT_CRAWLS']= array_filter($data['RECENT_CRAWLS']);
        }
        if (isset($data['RECENT_CRAWLS'][0])) {
            L\rorderCallback($data['RECENT_CRAWLS'][0],
                $data['RECENT_CRAWLS'][0], 'CRAWL_TIME');
            usort($data['RECENT_CRAWLS'], C\NS_LIB . "rorderCallback");
        }
        $this->pagingLogic($data, 'RECENT_CRAWLS', 'RECENT_CRAWLS',
            C\DEFAULT_ADMIN_PAGING_NUM);
        return $data;
    }
    /**
     * Gets data from the machine model concerning the on/off states
     * of the machines managed by this Yioop instance and then passes
     * this data the the machinestatus view.
     * @return array $data MACHINES field has information about each
     *     machine managed by this Yioop instance as well the on off
     *     status of its queue_servers and fetchers.
     *     The REFRESH field is used to tell the controller that the
     *     view shouldn't have its own sidemenu.
     */
    public function machineStatus()
    {
        $data = [];
        $data['REFRESH'] = "machinestatus";
        $this->pagingLogic($data, $this->model("machine"), 'MACHINES',
            C\DEFAULT_ADMIN_PAGING_NUM);
        $profile =  $this->model("profile")->getProfile(C\WORK_DIRECTORY);
        $media_mode = isset($profile['MEDIA_MODE']) ?
            $profile['MEDIA_MODE']: "name_server";
        $data['MEDIA_MODE'] = $media_mode;
        if ($data['MEDIA_MODE'] == "name_server" &&
            $data['MACHINES']['NAME_SERVER']["MEDIA_UPDATER_TURNED_ON"] &&
            $data['MACHINES']['NAME_SERVER']["MediaUpdater"] == 0) {
            // try to restart news server if dead
            CrawlDaemon::start("MediaUpdater", 'none', "", -1);
        }
        return $data;
    }
    /**
     * Used to update the yioop installation profile based on $_REQUEST data
     *
     * @param array& $data field data to be sent to the view
     * @param array& $profile used to contain the current and updated profile
     *     field values
     * @param array $check_box_fields fields whose data comes from a html
     *     checkbox
     */
    public function updateProfileFields(&$data, &$profile,
        $check_box_fields = [])
    {
        $script_array = ['SIDE_ADSCRIPT', 'TOP_ADSCRIPT', 'GLOBAL_ADSCRIPT'];
        foreach ($script_array as $value) {
            if (isset($_REQUEST[$value])) {
                $_REQUEST[$value] = str_replace("(","&#40;",$_REQUEST[$value]);
                $_REQUEST[$value] = str_replace(")","&#41;",$_REQUEST[$value]);
            }
        }
        $color_fields = ['BACKGROUND_COLOR', 'FOREGROUND_COLOR',
            'SIDEBAR_COLOR', 'TOPBAR_COLOR'];
        foreach ($this->model("profile")->profile_fields as $field) {
            if (isset($_REQUEST[$field])) {
                if ($field != "ROBOT_DESCRIPTION" &&
                    $field != "MEMCACHE_SERVERS" &&
                    $field != "PROXY_SERVERS") {
                    if (in_array($field, $color_fields)) {
                        $clean_value =
                            $this->clean($_REQUEST[$field], "color");
                    } else {
                        $clean_value =
                            $this->clean($_REQUEST[$field], "string");
                    }
                } else {
                    $clean_value = $_REQUEST[$field];
                }
                if ($field == "NAME_SERVER" &&
                    $clean_value[strlen($clean_value) -1] != "/") {
                    $clean_value .= "/";
                }
                $data[$field] = $clean_value;
                $profile[$field] = $data[$field];
                if ($field == "MEMCACHE_SERVERS" || $field == "PROXY_SERVERS") {
                    $mem_array = preg_split("/(\s)+/", $clean_value);
                    $profile[$field] =
                        $this->convertArrayLines(
                            $mem_array, "|Z|", true);
                }
            }
            if (!isset($data[$field])) {
                if (defined($field) && !in_array($field, $check_box_fields)) {
                    $data[$field] = constant($field);
                } else {
                    $data[$field] = "";
                }
                if (in_array($field, $check_box_fields)) {
                    $profile[$field] = false;
                }
            }
        }
    }
    /**
     * Used to set up view data for table search form (might make use of
     * $_REQUEST if form was submitted, results gotten, and we want to preserve
     * form drop down). Table search forms
     * are used by manageUsers, manageRoles, manageGroups, to do advanced
     * search of the entity they are responsible for.
     *
     * @param array& $data modified to contain the field data needed for
     *     the view to draw the search form
     * @param array $comparison_fields those fields of the entity
     *     in question ( for example, users) which we can search both with
     *     string comparison operators and equality operators
     * @param array $equal_comparison_fields those fields of the entity in
     *     question which can only be search by equality/inequality operators
     * @param string $field_postfix suffix to append onto field names in
     *     case there are multiple forms on the same page
     */
    public function tableSearchRequestHandler(&$data, $comparison_fields = [],
        $equal_comparison_fields = [], $field_postfix = "")
    {
        $data['FORM_TYPE'] = "search";
        $data['COMPARISON_TYPES'] = [
            "=" => tl('admin_controller_equal'),
            "!=" => tl('admin_controller_not_equal'),
            "CONTAINS" => tl('admin_controller_contains'),
            "BEGINS WITH" => tl('admin_controller_begins_with'),
            "ENDS WITH" => tl('admin_controller_ends_with'),
        ];
        $data['EQUAL_COMPARISON_TYPES'] = [
            "=" => tl('admin_controller_equal'),
            "!=" => tl('admin_controller_not_equal'),
        ];
        $data['SORT_TYPES'] = [
            "NONE" => tl('admin_controller_no_sort'),
            "ASC" => tl('admin_controller_sort_ascending'),
            "DESC" => tl('admin_controller_sort_descending'),
        ];
        $paging = "";
        foreach ($comparison_fields as $comparison_start) {
            $comparison = $comparison_start."_comparison";
            $comparison_types = (in_array($comparison_start,
                 $equal_comparison_fields))
                ? 'EQUAL_COMPARISON_TYPES' : 'COMPARISON_TYPES';
            $data[$comparison] = (isset($_REQUEST[$comparison]) &&
                isset($data[$comparison_types][
                $_REQUEST[$comparison]])) ? $_REQUEST[$comparison] :
                "=";
            $paging .= "&amp;$comparison=".
                urlencode($data[$comparison]);
        }
        foreach ($comparison_fields as $sort_start) {
            $sort = $sort_start."_sort";
            $data[$sort] = (isset($_REQUEST[$sort]) &&
                isset($data['SORT_TYPES'][
                $_REQUEST[$sort]])) ?$_REQUEST[$sort] :
                "NONE";
            $paging .= "&amp;$sort=".urlencode($data[$sort]);
        }
        $search_array = [];
        foreach ($comparison_fields as $field) {
            $field_name = $field.$field_postfix;
            $field_comparison = $field."_comparison";
            $field_sort = $field."_sort";
            $data[$field_name] = (isset($_REQUEST[$field_name])) ?
                $this->clean($_REQUEST[$field_name], "string") :
                "";
            if ($field_name=='access' && $data[$field_name] >= 10) {
                $search_array[] = ["status",
                    $data[$field_comparison], $data[$field_name]/10,
                    $data[$field_sort]];
            } else {
                $search_array[] = [$field,
                    $data[$field_comparison], $data[$field_name],
                    $data[$field_sort]];
            }
            $paging .= "&amp;$field_name=".
                urlencode($data[$field_name]);
        }
        $data['PAGING'] = $paging;
        return $search_array;
    }
}
