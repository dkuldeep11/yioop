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
 * Main web interface entry point for Yioop!
 * search site. Used to both get and display
 * search results. Also used for inter-machine
 * communication during crawling
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Main entry point to the Yioop web app.
 *
 * Initialization is done in  a function to avoid polluting the global
 * namespace with variables.
 */
function bootstrap()
{
    /**
     * For error function and yioop constants
     */
    require_once __DIR__."/library/Utility.php";
    /**
     * Did we come to this index.php from ../index.php? If so, rewriting
     * must be on
     */
    if(!C\nsdefined("REDIRECTS_ON")) {
        C\nsdefine("REDIRECTS_ON", false);
    }
    /**
     * Check if doing url rewriting, and if so, do initial routing
     */
    configureRewrites();
    if ((C\DEBUG_LEVEL & C\ERROR_INFO) == C\ERROR_INFO) {
        set_error_handler(C\NS_LIB . "yioop_error_handler");
    }
    /**
     * Load global functions related to localization
     */
    require_once __DIR__."/library/LocaleFunctions.php";
    ini_set("memory_limit","500M");
    header("X-FRAME-OPTIONS: DENY"); //prevent click-jacking
    header("X-Content-Type-Options: nosniff"); /*
        Let browsers know that we should be setting the mimetype correctly --
        For none dumb browsers this should help prevent against XSS attacks
        to images containing HTML. Also, might help against PRSSI attacks.
        */
    if (session_status() == PHP_SESSION_NONE) {
        session_name(C\SESSION_NAME);
        session_start();
    }
    /**
     * Load global functions related to checking Yioop! version
     */
    require_once C\BASE_DIR."/library/UpgradeFunctions.php";
    if (!function_exists('mb_internal_encoding')) {
        echo "PHP Zend Multibyte Support must be enabled for Yioop! to run.";
        exit();
    }
    /**
     * Make an initial setting of controllers. This can be overridden in
     * local_config
     */
    $available_controllers = ["admin", "api", "archive",  "cache",
        "classifier", "crawl", "fetch", "group", "jobs", "machine", "resource",
        "search", "settings", "statistics", "static"];
    if(function_exists(C\NS_CONFIGS . "localControllers")) {
        $available_controllers = array_merge($available_controllers,
            C\localControllers());
    }
    if (in_array(C\REGISTRATION_TYPE, ['no_activation', 'email_registration',
        'admin_activation'])) {
        $available_controllers[] = "register";
    }
    if (!C\WEB_ACCESS) {
        $available_controllers = ["admin", "archive", "cache", "crawl","fetch",
            "jobs", "machine"];
    }
    //the request variable c is used to determine the controller
    if (!isset($_REQUEST['c'])) {
        $controller_name = "search";
        if (C\nsdefined('LANDING_PAGE') && C\LANDING_PAGE &&
            !isset($_REQUEST['q'])) {
            $controller_name = "static";
            $_REQUEST['c'] = "static";
            $_REQUEST['p'] = "Main";
        }
    } else {
        $controller_name = $_REQUEST['c'];
    }
    if (!in_array($controller_name, $available_controllers))
    {
        if (C\WEB_ACCESS) {
            $controller_name = "search";
        } else {
            $controller_name = "admin";
        }
    }
    // if no profile exists we force the page to be the configuration page
    if (!C\PROFILE || (C\nsdefined("FIX_NAME_SERVER") && C\FIX_NAME_SERVER)) {
        $controller_name = "admin";
    }
    $locale_tag = L\getLocaleTag();
    if (L\upgradeDatabaseWorkDirectoryCheck()) {
        L\upgradeDatabaseWorkDirectory();
    }
    if (L\upgradeLocalesCheck($locale_tag)) {
        L\upgradeLocales();
    }
    //upgrade manipulations might mess with globale locale, so set it back here
    L\setLocaleObject($locale_tag);
    /**
     * Loads controller responsible for calculating
     * the data needed to render the scene
     *
     */
    $controller_class = C\NS_CONTROLLERS . ucfirst($controller_name) .
        "Controller";
    $controller = new $controller_class();
    $controller->processRequest();
}
/**
 * Used to setup and handles url rewriting for the Yioop Web app
 *
 * Developers can add new routes by creating a Routes class in
 * the app_dir with a static method getRoutes which should return
 * an associating array of incoming_path => handler function
 */
function configureRewrites()
{
    $route_map = [
        'advertise' => 'routeDirect',
        'blog' => 'routeDirect',
        'bot' => 'routeDirect',
        'privacy' => 'routeDirect',
        'terms' => 'routeDirect',
        'admin' => 'routeController',
        'register' => 'routeController',
        'settings' => 'routeController',
        'statistics' => 'routeController',
        's' => "routeSubsearch",
        'more' => 'routeMore',
        'suggest' => 'routeSuggest',
        'group' => 'routeFeeds',
        'thread' => 'routeFeeds',
        'user' => 'routeFeeds',
        'p' => 'routeWiki'
    ];
    if(class_exists(C\NS. "Routes")) {
        $route_map = array_merge($route_map, Routes::getRoutes());
    }
    /**
     * Check for paths of the form index.php/something which yioop doesn't
     * support
     */
    $s_name = $_SERVER['SCRIPT_NAME']."/";
    $path_name = substr($_SERVER["REQUEST_URI"], 0, strlen($s_name));
    if (strcmp($path_name, $s_name) == 0) {
        $_SERVER["PATH_TRANSLATED"] = C\BASE_DIR;
        $scriptinfo = pathinfo($s_name);
        $_SERVER["PATH_INFO"] = ($scriptinfo["dirname"] == "/") ? "" :
            $scriptinfo["dirname"] ;
        require_once(C\BASE_DIR."/error.php");
        if(C\REDIRECTS_ON) {
            return;
        }
        exit();
    }
    if (!isset($_SERVER["PATH_INFO"])) {
        $_SERVER["PATH_INFO"] = ".";
    }
    if(!C\REDIRECTS_ON) {
        return;
    }
    /**
     * Now look for and handle routes
     */
    $index_php = "index.php";
    $script_path = substr($_SERVER['PHP_SELF'], 0, -strlen($index_php));
    if($_SERVER['QUERY_STRING'] == "") {
        $request_script = rtrim(
            substr($_SERVER['REQUEST_URI'], strlen($script_path)), "?");
    } else {
        $request_script = substr($_SERVER['REQUEST_URI'], strlen($script_path),
            -strlen($_SERVER['QUERY_STRING']) -  1);
    }
    $request_script = ($request_script == "") ? $index_php : $request_script;
    if(in_array($request_script, ['', $index_php])) {
        return;
    }
    $request_parts = explode("/", $request_script);
    $handled = false;
    if (isset($route_map[$request_parts[0]])) {
        if(empty($_REQUEST['c']) || $_REQUEST['c'] == $request_parts[0]) {
            $route = C\NS . $route_map[$request_parts[0]];
            $handled = $route($request_parts);
        } else if (!empty($_REQUEST['c'])) {
            $handled = true;
        }
    }
    if (!$handled) {
        $_REQUEST['p'] = "404";
        require_once __DIR__."/error.php";
    }
}
/**
 * Used to route page requests to pages that are fixed Public Group wiki
 * that should always be present. For example, 404 page.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeDirect($route_args)
{
    $_REQUEST['route']['c'] = true;
    require_once __DIR__ . "/". $route_args[0] . ".php";
    return true;
}
/**
 * Given the name of a fixed public group static page creates the url
 * where it can be accessed in this instance of Yioop, making use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $name of static page
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function directUrl($name, $with_delim = false)
{
    if(C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        return C\BASE_URL . $name . $delim;
    } else {
        $delim = ($with_delim) ? "&" : "";
        return C\BASE_URL . "$name.php$delim";
    }
}
/**
 * Used to route page requests for pages corresponding to a group, user,
 * or thread feed. If redirects on then urls ending with /feed_type/id map
 * to a page for the id'th item of that feed_type
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeFeeds($route_args)
{
    $handled = true;
    if (isset($route_args[1]) && $route_args[1] == intval($route_args[1])) {
        $_REQUEST['c'] = "group";
        if (!empty($route_args[2])) {
            $_REQUEST['a'] = 'wiki';
            if ($route_args[2] == 'pages') {
                $_REQUEST['arg'] = 'pages';
                $_REQUEST['route']['arg'] = true;
            } else {
                $_REQUEST['page_name'] = $route_args[2];
                $_REQUEST['route']['page_name'] = true;
            }
        }
        $_REQUEST['a'] = (isset($_REQUEST['a']) &&
            $_REQUEST['a'] == 'wiki') ? $_REQUEST['a'] : "groupFeeds";
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
        $end = ($route_args[0] == 'thread') ? "" : "_id";
        if($_REQUEST['a'] == 'wiki') {
            $_REQUEST['group_id'] = $route_args[1];
            $_REQUEST['route']['group_id'] = true;
        } else {
            $just_id = "just_" . $route_args[0] . $end;
            $_REQUEST[$just_id] = $route_args[1];
            $_REQUEST['route'][$just_id] = true;
        }
    } else if (!isset($route_args[1])) {
        $_REQUEST['c'] = "group";
        $_REQUEST['a'] = (isset($_REQUEST['a']) &&
            $_REQUEST['a'] == 'wiki') ? $_REQUEST['a'] : "groupFeeds";
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the type of feed, the identifier of the feed instance, and which
 * controller is being used creates the url where that feed item can be
 * accessed from the instance of Yioop. It makes use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $type of feed: group, user, thread
 * @param int $id the identifier for that feed.
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @param string $controller which controller is being used to access the
 *      feed: usuall admin or group
 * @return string url for the page in question
 */
function feedsUrl($type, $id, $with_delim = false, $controller = "group")
{
    if(C\REDIRECTS_ON && $controller == 'group') {
        $delim = ($with_delim) ? "?" : "";
        $path = ($type == "") ? "group" : "$type/$id";
        return C\BASE_URL ."$path$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        $begin = (C\REDIRECTS_ON && $controller == "admin") ?
            "admin?" : "?c=$controller&";
        $query = "{$begin}a=groupFeeds";
        $end = ($type == 'thread') ? "" : "_id";
        if ($type != "") {
            if ($begin == "admin?" && $type == "group") {
                $query = "admin/$id";
                $delim = "?";
            } else {
                $query .= "&just_{$type}$end=$id";
            }
        }
        return C\BASE_URL . "$query$delim";
    }
}
/**
 * Used to route requests for the more and tools link on the landing page.
 * If redirects on, then /more routes to this more tools page.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeMore($route_args)
{
    $_REQUEST['c'] = "search";
    $_REQUEST['a'] = "more";
    $_REQUEST['route']['c'] = true;
    $_REQUEST['route']['a'] = true;
    return true;
}
/**
 * Return the url for the more and tools link on the landing page making use of
 * the defined variable REDIRECTS_ON.
 *
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function moreUrl($with_delim = false)
{
    if(C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        return C\BASE_URL ."more$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        return C\BASE_URL . "?a=more$delim";
    }
}
/**
 * Used to route page requests to end-user controllers such as
 * settings, register, admin. urls ending with /controller_name will
 * be routed to that controller.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeController($route_args)
{
    $_REQUEST['c'] = $route_args[0];
    $_REQUEST['route']['c'] = true;
    if (isset($route_args[1]) && intval($route_args[1]) == $route_args[1]) {
        if(isset($_REQUEST['a']) && $_REQUEST['a'] == 'wiki') {
            $_REQUEST['group_id'] = $route_args[1];
        } else if (!empty($route_args[2])) {
            $_REQUEST['a'] = 'wiki';
            $_REQUEST['group_id'] = $route_args[1];
            if ($route_args[2] == 'pages') {
                $_REQUEST['arg'] = 'pages';
                $_REQUEST['route']['arg'] = true;
            } else {
                $_REQUEST['page_name'] = $route_args[2];
                $_REQUEST['route']['page_name'] = true;
            }
            $_REQUEST['route']['page_name'] = true;
            $_REQUEST['route']['a'] = true;
        } else {
            $_REQUEST['a'] = 'groupFeeds';
            $_REQUEST['just_group_id'] = $route_args[1];
        }
        $_REQUEST['route']['group_id'] = true;
    }
    return true;
}
/**
 * Given the name of a controller for which an easy end-user link is useful
 * creates the url where it can be accessed on this instance of Yioop, 
 * making use of the defined variable REDIRECTS_ON. Examples of end-user
 * controllers would be the settings, admin, and register controllers.
 *
 * @param string $name of controller
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function controllerUrl($name, $with_delim = false)
{
    if(C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        $_REQUEST['route']['c'] = true;
        return C\BASE_URL . $name . $delim;
    } else {
        $delim = ($with_delim) ? "&" : "";
        return C\BASE_URL . "?c=$name$delim";
    }
}
/**
 * Used to route page requests for subsearches such as news, video, and images
 * (site owner can define other). Urls of the form /s/subsearch will
 * go the page handling the subsearch.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeSubsearch($route_args)
{
    $handled = true;
    if(isset($route_args[1])) {
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['s'] = true;
        $_REQUEST['c'] = "search";
        $_REQUEST['s'] = $route_args[1];
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the name of a subsearch  creates the url where it can be accessed 
 * on this instance of Yioop, making use of the defined variable REDIRECTS_ON.
 * Examples of subsearches include news, video, and images. A site owner
 * can add to these and delete from these.
 *
 * @param string $name of subsearch
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function subsearchUrl($name, $with_delim = false)
{
    if(C\REDIRECTS_ON) {
        $delim = ($with_delim) ? "?" : "";
        return C\BASE_URL ."s/$name$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        return C\BASE_URL . "?s=$name$delim";
    }
}
/**
 * Used to route requests for the suggest-a-url link on the tools page.
 * If redirects on, then /suugest routes to this suggest-a-url page.
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeSuggest($route_args)
{
    $_REQUEST['c'] = "register";
    $_REQUEST['a'] = "suggestUrl";
    return true;
}
/**
 * Return the url for the suggest-a-url link on the more tools page, making use
 * of the defined variable REDIRECTS_ON.
 *
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @return string url for the page in question
 */
function suggestUrl($with_delim = false)
{
    if(C\REDIRECTS_ON) {
        $_REQUEST['route']['c'] = true;
        $_REQUEST['route']['a'] = true;
        $delim = ($with_delim) ? "?" : "";
        return C\BASE_URL ."suggest$delim";
    } else {
        $delim = ($with_delim) ? "&" : "";
        return C\BASE_URL . "?c=register&a=suggestUrl$delim";
    }
}
/**
 * Used to route page requests for pages corresponding to a wiki page of
 * group. If it is a wiki page for the public group viewed without being
 * logged in, the route might come in as yioop_instance/p/page_name if
 * redirects are on. If it is for a non-public wiki or page accessed with
 * logged in the url will look like either:
 * yioop_instance/group/group_id?a=wiki&page_name=some_name
 * or
 * yioop_instance/admin/group_id?a=wiki&page_name=some_name&csrf_token_string
 *
 * @param array $route_args of url parts (split on slash).
 * @return bool whether was able to compute a route or not
 */
function routeWiki($route_args)
{
    $handled = true;
    if(isset($route_args[1])) {
        if($route_args[1] == 'pages') {
            $_REQUEST['c'] = "group";
            $_REQUEST['a'] = 'wiki';
            $_REQUEST['arg'] = 'pages';
            $_REQUEST['route']['c'] = true;
            $_REQUEST['route']['a'] = true;
            $_REQUEST['route']['arg'] = true;
        } else {
            $_REQUEST['c'] = "static";
            $_REQUEST['p'] = $route_args[1];
            $_REQUEST['route']['c'] = true;
            $_REQUEST['route']['p'] = true;
        }
    } else {
        $handled = false;
    }
    return $handled;
}
/**
 * Given the name of a wiki page, the group it belongs to, and which
 * controller is being used creates the url where that feed item can be
 * accessed from the instance of Yioop. It makes use of the
 * defined variable REDIRECTS_ON.
 *
 * @param string $name of wiki page
 * @param bool $with_delim whether it should be terminated with nothing or
 *      ? or &
 * @param string $controller which controller is being used to access the
 *      feed: usually static (for the public group), admin, or group
 * @param int $id the group the wiki page belongs to
 * @return string url for the page in question
 */
function wikiUrl($name, $with_delim = false, $controller = "static", $id =
    C\PUBLIC_GROUP_ID)
{
    $q = ($with_delim) ? "?" : "";
    $a = ($with_delim) ? "&" : "";
    $is_static = ($controller == "static");
    if (C\REDIRECTS_ON) {
        $q = ($with_delim) ? "?" : "";
        if($is_static) {
            if($name == "") {
                $name = "Main";
            }
            return C\BASE_URL ."p/$name$q";
        } else {
            $page = ($name== "") ? "?a=wiki$a" : "/$name$q";
            return C\BASE_URL .
                $controller . "/$id$page";
        }
    } else {
        $delim = ($with_delim) ? "&" : "";
        if ($name == 'pages') {
            if ($is_static) {
                $controller = $group;
            }
            return  C\BASE_URL .
                "?c=$controller&a=wiki&arg=pages&group_id=$id$a";
        } else {
            if ($is_static) {
                if($name == "") {
                    $name = "main";
                }
                return C\BASE_URL . "?c=static&p=$name$a";
            } else {
                $page = ($name== "") ? "" : "&page_name=$name";
                return C\BASE_URL .
                    "?c=$controller&a=wiki&group_id=$id$page$a";
            }
        }
    }
}
if(!defined('seekquarry\\yioop\\configs\\SKIP_BOOTSTRAP')) {
    bootstrap();
}
