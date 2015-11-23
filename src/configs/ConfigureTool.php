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
 * Used to create and manipulate a profile and work directory from the
 * command-line for Yioop.
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\configs;

use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\controllers\AdminController;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/** Loads common utility functions*/
require_once  __DIR__."/../library/Utility.php";
/** Loads common constants for web crawling*/
require_once __DIR__."/../library/LocaleFunctions.php";
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
$locale_tag = L\guessLocale();
$locale = null;
L\setLocaleObject($locale_tag);
/**
 * Provides a command-line interface way to configure a Yioop Instance.
 * Unlike the web interface this interface is English-only.
 */
class ConfigureTool
{
    /**
     * Used to hold an AdminController object used to manipulate the
     * Yioop configuration
     * @var object
     */
    public $admin;
    /**
     * Holds the main menu data for the configuration tool
     * @var array
     */
    public $menu = ["workDirectory" => "Create/Set Work Directory",
        "rootPassword" => "Change root password",
        "defaultLocale"=> "Set Default Locale",
        "debugDisplay"=> "Debug Display Set-up",
        "searchAccess"=> "Search Access Set-up",
        "searchPageElementLinks" => "Search Page Elements and Links",
        "nameServer" => "Name Server Set-up",
        "robotSetUp"=> "Crawl Robot Set-up",
        "quit" => "Exit program"
    ];
    /**
     * To change configuration parameters of Yioop, this program
     * invokes AdminController methods. These methods expect, data
     * passed to them in super globals set up as a result of an HTTP
     * request. This program fakes the settings of these variables.
     * To keep things simple this constructor initializes each of the
     * relevant super globals to be empty arrays.
     */
    function __construct()
    {
        $_REQUEST = [];
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        $_SESSION = [];
        $this->admin = new AdminController();
    }
    /**
     * This is the main loop where options of what the user can configure
     * are presented, a choice is requested, and so on...
     */
    function loop()
    {
        $done = false;
        $activities = array_keys($this->menu);
        $activities[] = "configureMenu";
        $state = "configureMenu";
        while($state != "quit") {
            if (in_array($state, $activities) ) {
                $state = $this->$state();
            }
        }
    }
    /**
     * This is used to draw the main configuration menu and ask for a
     * user selection
     */
    function configureMenu()
    {
        $this->banner();
        $data = $this->callConfigure();
        e("Checking Yioop configuration...".
            "\n===============================\n");
        $check_status = str_replace("<br />", "\n", $data["SYSTEM_CHECK"]);
        e($check_status."\n===============================\n");

        $items = ["workDirectory" => "Create/Set Work Directory",
            "quit" => "Exit program"];
        if ($data["PROFILE"]) {
            $items = $this->menu;
        }
        return $this->drawChooseItems($items, "configureMenu");
    }
    /**
     * Used to create/change the location of this Yioop instances work
     * directory
     */
    function workDirectory()
    {
        $this->banner();
        $data = $this->callConfigure();
        $directory = (isset($data["WORK_DIRECTORY"]) &&
            $data["WORK_DIRECTORY"] != "") ? $data["WORK_DIRECTORY"]
            : "No value set yet.";
        e("CURRENT WORK DIRECTORY: $directory\n\n");
        e("Enter a new value:\n");
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = "";
        }
        $this->prepareGlobals($data);
        $_REQUEST["WORK_DIRECTORY"] = L\readInput();
        $_REQUEST["arg"] = "directory";
        $next_menu = $this->confirmChange("configure", "workDirectory");
        return $next_menu;
    }
    /**
     * Used to change the password of the root account of this Yioop Instance
     */
    function rootPassword()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("Enter old password:");
        $_REQUEST["password"] = L\readPassword();
        e("Enter new password:");
        $_REQUEST["new_password"] = L\readPassword();
        e("Re-Enter new password:");
        $_REQUEST["retype_password"] = L\readPassword();
        $_SESSION['USER_ID'] = ROOT_ID;
        $_REQUEST['arg'] = "updateuser";
        $_REQUEST['edit_pass'] = "true";
        $next_menu = $this->confirmChange("manageAccount", "rootPassword");
        return $next_menu;
    }
    /**
     * Changes the default locale (language) used by Yioop when it cannot
     * determine that information from the users browswer
     */
    function defaultLocale()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("CURRENT LANGUAGE: ".$data["LANGUAGES"][
            $data["DEFAULT_LOCALE"]]."\n\n");
        $_SESSION = [];
        $items = $data["LANGUAGES"];
        $items["configureMenu"] = "Return to Main Menu";

        do {
            $choice = $this->drawChooseItems($items, "defaultLocale");
        } while( $choice == "defaultLocale");

        $this->prepareGlobals($data);
        if ($choice == "configureMenu") {
            $_REQUEST = [];
            $_SERVER = [];
            return "configureMenu";
        }
        $_REQUEST["DEFAULT_LOCALE"] = $choice;
        return "defaultLocale";
    }
    /**
     * Used to configure debugging information for this Yioop instance.
     * i.e., whether PHP notices, warnings, errors, should be displayed,
     * whether query statistics and info should be displayed, and whether
     * unit tests should be viewable from the web
     */
    function debugDisplay()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("CURRENT DEBUG SETTINGS\n======================\n");
        $dlevel = $data["DEBUG_LEVEL"];
        $setting = ($dlevel & ERROR_INFO) ? "On" : "Off";
        e("Error Info: [$setting]\n");
        $setting = ($dlevel & QUERY_INFO) ? "On" : "Off";
        e("Query Info: [$setting]\n");
        $setting = ($dlevel & TEST_INFO) ? "On" : "Off";
        e("Test Info: [$setting]\n");
        $items = ["ERROR_INFO" => "Toggle Error Info",
            "QUERY_INFO" => "Toggle Query Info",
            "TEST_INFO" => "Toggle Test Info",
            "configureMenu" => "Return to Main Menu"];
        do {
            $choice = $this->drawChooseItems($items, "debugDisplay");
        } while( $choice == "debugDisplay");
        $this->prepareGlobals($data);
        if ($choice == "configureMenu") {
            $_REQUEST = [];
            $_SERVER = [];
            return "configureMenu";
        }
        $flag = constant($choice);
        $dlevel = ($dlevel & $flag) ? $dlevel - $flag : $dlevel + $flag;
        if ($dlevel & ERROR_INFO) {$_REQUEST["ERROR_INFO"] = true;}
        if ($dlevel & QUERY_INFO) {$_REQUEST["QUERY_INFO"] = true;}
        if ($dlevel & TEST_INFO) {$_REQUEST["TEST_INFO"] = true;}
        return "debugDisplay";
    }
    /**
     * Configures which methods are allowed by this Yioop instance to access
     * search results, (via the web, via open rss search results, via the
     * API)
     */
    function searchAccess()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("CURRENT SEARCH ACCESS SETTINGS\n==============================\n");
        $settings = ["WEB_ACCESS" => "Web",
            "RSS_ACCESS" => "RSS", "API_ACCESS" => "API"];
        $items = [];
        foreach ($settings as $setting => $setting_string) {
            $toggle = ($data[$setting]) ? "On" : "Off";
            e("$setting_string: [$toggle]\n");
            $items[$setting] = "Toggle $setting_string";
        }
        $items["configureMenu"] = "Return to Main Menu";
        do {
            $choice = $this->drawChooseItems($items, "searchAccess");
        } while( $choice == "searchAccess");
        $this->prepareGlobals($data);
        if ($choice == "configureMenu") {
            $_REQUEST = [];
            $_SERVER = [];
            return "configureMenu";
        }
        $_REQUEST[$choice] = ($data[$choice]) ? false : true;
        return "searchAccess";
    }
    /**
     * Configures which of the various links of the SERPS page such as
     * Cache, etc should be displayed. Also, configures whether the signin
     * links, etc should be displayed.
     */
    function searchPageElementLinks()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("CURRENT SEARCH PAGE ELEMENTS AND LINKS SETTINGS".
            "\n===================================================\n");
        $settings = ["WORD_SUGGEST" => "Word Suggest",
            "SUBSEARCH_LINK"  => "Subsearch Links",
            "SIGNIN_LINK" => "Sign-in Links", "CACHE_LINK" => "Cache Link",
            "SIMILAR_LINK" => "Similar Link", "IN_LINK" => "Inlinks",
            "IP_LINK"=> "IP Links"];
        $items = [];
        foreach ($settings as $setting => $setting_string) {
            $toggle = ($data[$setting]) ? "On" : "Off";
            e("$setting_string: [$toggle]\n");
            $items[$setting] = "Toggle $setting_string";
        }
        $items["configureMenu"] = "Return to Main Menu";
        do {
            $choice = $this->drawChooseItems($items, "searchPageElementLinks");
        } while( $choice == "searchPageElementLinks");
        $this->prepareGlobals($data);
        if ($choice == "configureMenu") {
            $_REQUEST = [];
            $_SERVER = [];
            return "configureMenu";
        }
        $_REQUEST[$choice] = ($data[$choice]) ? false : true;
        return "searchPageElementLinks";
    }
    /**
     * Configures settings relating to the location of the name server and
     * the salt used when communicating with it. Also, configures caching
     * mechanisms the name server should use when returning results.
     */
    function nameServer()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("NAME SERVER SETTINGS\n====================\n");
        e("Server Key: [".$data["AUTH_KEY"]."]\n");
        e("Name Server URL: [".$data["NAME_SERVER"]."]\n");
        $settings = ["USE_FILECACHE" => "Use File Cache",
            "USE_MEMCACHE" => "Use Memcache"];
        $items = ["serverKey" => "Edit Server Key",
            "nameServer" => "Edit Name Server Url"];
        foreach ($settings as $setting => $setting_string) {
            $toggle = ($data[$setting]) ? "On" : "Off";
            e("$setting_string: [$toggle]\n");
            $items[$setting] = "Toggle $setting_string";
        }
        e("\nMemcache Servers:\n=================\n".$data["MEMCACHE_SERVERS"].
            "\n=================\n");
        $items["memcacheServers"] = "Edit Memcache Servers";
        $items["configureMenu"] = "Return to Main Menu";
        do {
            $choice = $this->drawChooseItems($items, "nameServerMenu");
        } while( $choice == "nameServerMenu");
        $this->prepareGlobals($data);
        switch ($choice) {
            case "configureMenu":
                $_REQUEST = [];
                $_SERVER = [];
                return "configureMenu";
                break;
            case "serverKey":
                e("Enter a new server key: ");
                $_REQUEST["AUTH_KEY"] = L\readInput();
                break;
            case "nameServer":
                e("Enter a new name server url: ");
                $_REQUEST["NAME_SERVER"] = L\readInput();
                break;
            case "memcacheServers":
                e("Enter memcache servers, one per line.\n".
                  "Terminate input with a line with only '.' on it:\n");
                $_REQUEST["MEMCACHE_SERVERS"] = L\readMessage();
                break;
            default:
                $_REQUEST[$choice] = ($data[$choice]) ? false : true;
        }
        return "nameServer";
    }
    /**
     * Used to set up the name of this instance of the Yioop robot as well
     * as its description page.
     */
    function robotSetUp()
    {
        $this->banner();
        $data = $this->callConfigure();
        if ($data["PROFILE"] != 1) {
            $_REQUEST["MESSAGE"] = "Work directory needs to be set/created!";
            return "configureMenu";
        }
        e("CRAWL ROBOT SETTINGS\n====================\n");
        e("Crawl Robot Name: [".$data["USER_AGENT_SHORT"]."]\n");
        e("Robot Instance: [".$data["ROBOT_INSTANCE"]."]\n");
        e("\nRobot Description:\n=================\n".
            $data["ROBOT_DESCRIPTION"] . "\n=================\n");
        $items = ["robotName" => "Edit Robot Name",
            "robotInstance" => "Edit Robot Instance",
            "robotDescription" => "Edit Robot Description",
            "configureMenu" => "Return to Main Menu"];
        do {
            $choice = $this->drawChooseItems($items, "robotSetUp");
        } while( $choice == "robotSetUp");
        $this->prepareGlobals($data);
        switch ($choice) {
            case "configureMenu":
                $_REQUEST = [];
                $_SERVER = [];
                return "configureMenu";
                break;
            case "robotName":
                e("Enter a new robot name: ");
                $_REQUEST["USER_AGENT_SHORT"] = L\readInput();
                break;
            case "robotInstance":
                e("Enter a new robot instance value: ");
                $_REQUEST["ROBOT_INSTANCE"] = L\readInput();
                break;
            case "robotDescription":
                e("Enter a description of your web crawler robot.\n".
                  "Terminate input with a line with only '.' on it:\n");
                $_REQUEST["ROBOT_DESCRIPTION"] = L\readMessage();
                break;
        }
        return "robotSetUp";
    }
    /**
     * Used to select to confirm, cancel, or re-enter the last profile
     * change
     *
     * @param string $admin_method to call if confirmed
     * @param string $reenter_method , return value if reenter chosen
     * @return string menu name to do to next
     */
    function confirmChange($admin_method, $reenter_method)
    {
        $component_activities = AdminController::$component_activities;
        $items = ["confirm" => "Confirm Change",
            "reenter" => "Re-enter the information",
            "configureMenu" => "Return to the Configure Menu"];
        $first = true;
        do {
            $choice = $this->drawChooseItems($items, "confirmChange");
        } while( $choice == "confirmChange");
        switch ($choice) {
            case "confirm":
                $component = "system";
                foreach ($component_activities as $available_component =>
                    $activities) {
                    if (in_array($admin_method, $activities)) {
                        $component = $available_component;
                        break;
                    }
                }
                $data = $this->admin->component($component)->$admin_method();
                $_SERVER = [];
                $_SESSION = [];
                $_REQUEST = [];
                $_REQUEST["MESSAGE"] = $data["MESSAGE"];
                $next_menu = "configureMenu";
                break;
            case "reenter":
                $_SERVER = [];
                $_SESSION = [];
                $_REQUEST = [];
                $next_menu = $reenter_method;
                break;
            default:
                $_SERVER = [];
                $_SESSION = [];
                $_REQUEST = [];
                $next_menu = "configureMenu";
        }
        return $next_menu;
    }
    /**
     * Draws a list of options to the screen and gets a choice
     * from this list from the user.
     *
     * @param array $items as associative array (return value => description)
     * @param string $currentView value to return if invalid choice made
     * @return string a choice from the user
     */
    function drawChooseItems($items, $currentView)
    {
        $choice_nums = [];
        $i = 1;
        e("\nAvailable Options:\n==================\n");
        foreach ($items as $name => $description) {
            e("($i) $description\n");
            $choice_nums[$i] = $name;
            $i++;
        }
        if (!empty($_REQUEST["MESSAGE"])) {
            e("\n+++ ".$_REQUEST["MESSAGE"]." +++\n");
            unset($_REQUEST["MESSAGE"]);
        }
        e("\nPlease choose an option:\n");
        $user_data = strtolower(trim(L\readInput()));

        if ($user_data >= 1 && $user_data < $i) {
            $_REQUEST["MESSAGE"] = "";
            return $choice_nums[$user_data];
        } else {
            $_REQUEST["MESSAGE"] = "Invalid choice. Please choose again.";
            return $currentView;
        }
    }
    /**
     * Used to call system components configure method. It detects if
     * a redirect happened by the fact that $data['PROFILE'] is not set.
     * If so it passes along the redirect message and re-calls configure()
     */
    function callConfigure()
    {
        $data = $this->admin->component("system")->configure();
        if(!isset($data["PROFILE"])) {
            $_REQUEST = [];
            $message = (isset($data['MESSAGE'])) ? $data['MESSAGE'] : "";
            $data = $this->admin->component("system")->configure();
            $data['MESSAGE'] = $message;
        }
        return $data;
    }
    /**
     * Prints the banner used by this configuration tool
     */
    function banner()
    {
        e(chr(27) . "[2J" . chr(27) . "[;H");
        e("\n\nYIOOP! CONFIGURATION TOOL\n");
        e("+++++++++++++++++++++++++\n\n");
    }
    /**
     * Sets-up the field values of the super globals used by AdminController
     * when changing a profile or managing passwords. These particular
     * values don't change with respect to what this tool does.
     *
     * @param array $data current profile state
     */
    function prepareGlobals($data)
    {
        $_SESSION = [];
        $_REQUEST = $this->copyProfileFields($data);
        $_REQUEST["arg"] = "profile";
        $_REQUEST['YIOOP_TOKEN'] = "";
        if (!isset($_SERVER['REQUEST_URI'])) {
            if (!empty($data['WEB_URI'])) {
                $_SERVER['REQUEST_URI'] = $data['WEB_URI'];
            } else {
                e("Enter web path for Yioop instance:\n");
                $_SERVER['REQUEST_URI'] = L\readInput();
            }
        }
    }
    /**
     * Used to copy the contents of $data which are profile fields to a
     * new array.
     *
     * @param array $data an array of profile and other fields
     * @return array a new array containing a copy of just the profile fields
     *     from the orginal array
     */
    function copyProfileFields($data)
    {
        $profile = [];
        foreach ($this->admin->model("profile")->profile_fields as $field) {
            if (isset($data[$field])) {
                $profile[$field] = $data[$field];
            }
        }
        return $profile;
    }
}
$configure_tool = new ConfigureTool();
$configure_tool->loop();

