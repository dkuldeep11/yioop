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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UrlParser;

/**
 * This component is used to handle activities related to the configuration
 * of a Yioop installation, translations of text appearing in the installation,
 * as well as control of specifying what machines make up the installation
 * and which processes they run.
 *
 * @author Chris Pollett
 */
class SystemComponent extends Component
{
    /**
     * Handles admin request related to the managing the machines which perform
     * crawls
     *
     * With this activity an admin can add/delete machines to manage. For each
     * managed machine, the admin can stop and start fetchers/queue_servers
     * as well as look at their log files
     *
     * @return array $data MACHINES, their MACHINE_NAMES, data for
     *     FETCHER_NUMBERS drop-down
     */
    public function manageMachines()
    {
        $parent = $this->parent;
        $machine_model = $parent->model("machine");
        $profile_model = $parent->model("profile");
        $data = [];
        $data["ELEMENT"] = "managemachines";
        $possible_arguments = ["addmachine", "deletemachine", "log", "update",
                "updatemode"];
        $data['SCRIPT'] = "doUpdate();";
        $data["leftorright"]=(L\getLocaleDirection() == 'ltr') ? "right":
            "left";
        $data['MACHINE_NAMES'] = [];
        $data['FETCHER_NUMBERS'] = [
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            16 => 16
        ];
        $tmp = tl('system_component_select_machine');
        if (!isset($_REQUEST["has_queue_server"]) ||
            isset($_REQUEST['is_replica'])) {
            $_REQUEST["has_queue_server"] = false;
        }
        if (isset($_REQUEST['is_replica'])) {
            $_REQUEST['num_fetchers'] = 0;
        } else {
            $_REQUEST['parent'] = "";
        }
        $request_fields = [
            "name" => "string",
            "url" => "web-url",
            "has_queue_server" => "bool",
            "num_fetchers" => array_keys($data['FETCHER_NUMBERS']),
            "parent" => "string"
        ];
        $r = [];
        $allset = true;
        foreach ($request_fields as $field => $type) {
            if (isset($_REQUEST[$field])) {
                $r[$field] = $parent->clean($_REQUEST[$field], $type);
                if ($type == "string") {
                    $r[$field] = trim($r[$field]);
                    if ($r[$field] == "" && $field != "parent") {
                        $allset = false;
                    }
                }
                if ($field == "url") {
                    if (isset($r[$field][strlen($r[$field])-1]) &&
                        $r[$field][strlen($r[$field])-1] != "/") {
                        $r[$field] .= "/";
                    }
                    $r[$field] = UrlParser::canonicalLink($r[$field],
                        C\NAME_SERVER);
                    if (!$r[$field]) {
                        $allset = false;
                    }
                }
            } else {
                $allset = false;
            }
        }
        if (isset($r["num_fetchers"])) {
            $data['FETCHER_NUMBER'] = $r["num_fetchers"];
        } else {
            $data['FETCHER_NUMBER'] = 0;
        }
        $machine_exists = (isset($r["name"]) &&
            $machine_model->checkMachineExists("NAME", $r["name"]) ) ||
            (isset($r["url"]) && $machine_model->checkMachineExists("URL",
            $r["url"]) );
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "addmachine":
                    if ($allset == true && !$machine_exists) {
                        $machine_model->addMachine(
                            $r["name"], $r["url"], $r["has_queue_server"],
                            $r["num_fetchers"], $r["parent"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_added'),
                            ["start_row", "end_row", "num_show"]);
                    } else if ($allset && $machine_exists ) {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_exists'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_incomplete'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
                case "deletemachine":
                    if (!$machine_exists) {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_doesnt_exists'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        $machines = $machine_model->getRows(0, 1,
                            $total_rows, [
                                ["name", "=", $r["name"], ""]]);
                        $service_in_use = false;
                        foreach ($machines as $machine) {
                            if ($machine['NAME'] == $r["name"]) {
                                if (isset($machine['STATUSES']) &&
                                    is_array($machine['STATUSES']) &&
                                    $machine['STATUSES'] != []) {
                                    $service_in_use = true;
                                    break;
                                } else {
                                    break;
                                }
                            }
                        }
                        if ($service_in_use) {
                            return $parent->redirectWithMessage(
                                tl('system_component_stop_service_first'),
                                ["start_row", "end_row", "num_show"]);
                            break;
                        }
                        $machine_model->deleteMachine($r["name"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_deleted'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
                case "updatemode":
                    $profile =  $profile_model->getProfile(C\WORK_DIRECTORY);
                    if(isset($profile['MEDIA_MODE']) &&
                        $profile['MEDIA_MODE'] == "name_server") {
                        $profile['MEDIA_MODE'] = "distributed";
                    } else {
                        $profile['MEDIA_MODE'] = "name_server";
                    }
                    $profile_model->updateProfile(C\WORK_DIRECTORY, [],
                        $profile);
                    return $parent->redirectWithMessage(
                        tl('system_component_updatemode_toggled'),
                        ["start_row", "end_row", "num_show"]);
                    break;
                case "log":
                    $log_fields = ["id" => "int", "name"=>"string",
                        "type"=>"string", "f" => "string"];
                    foreach ($log_fields as $field => $type) {
                        if (isset($_REQUEST[$field])) {
                            $r[$field] =
                                $parent->clean($_REQUEST[$field], $type);
                        }
                    }
                    $filter = (isset($r['f'])) ? $r['f'] : "";
                    if (isset($_REQUEST["time"])) {
                        $data["time"] =
                            $parent->clean($_REQUEST["time"], "int") + 30;
                    } else {
                        $data["time"] = 30;
                    }
                    if (isset($_REQUEST["NO_REFRESH"])) {
                        $data["NO_REFRESH"] = $parent->clean(
                            $_REQUEST["NO_REFRESH"], "bool");
                    } else {
                        $data["NO_REFRESH"] = false;
                    }
                    $data["ELEMENT"] = "machinelog";
                    $data['filter'] = $filter;
                    $data["REFRESH_LOG"] = "&time=". $data["time"];
                    $data["LOG_TYPE"] = "";
                    if (isset($r['id']) && isset($r['name']) &&
                        isset($r['type'])) {
                        $data["LOG_FILE_DATA"] = $machine_model->getLog(
                            $r["name"], $r["id"], $r["type"], $filter);
                        $data["LOG_TYPE"] = $r['name'] . " " . $r["type"];
                        if($r["type"] == "fetcher") {
                            $data["LOG_TYPE"] .= " ".$r['id'];
                        }
                        $data["REFRESH_LOG"] .= "&arg=log&name=".$r['name'].
                            "&id=".$r['id']."&type=".$r["type"];
                    }
                    if ($data["time"] >= C\ONE_HOUR/3) {
                        $data["REFRESH_LOG"] = "";
                    }
                    if (!isset($data["LOG_FILE_DATA"])
                        || $data["LOG_FILE_DATA"] == ""){
                        $data["LOG_FILE_DATA"] =
                            tl('system_component_no_machine_log');
                    }
                    $lines = array_reverse(
                        explode("\n",$data["LOG_FILE_DATA"]));
                    $data["LOG_FILE_DATA"] = implode("\n", $lines);
                    break;
                case "update":
                    if (isset($_REQUEST["id"])) {
                        $r["id"] =
                            $parent->clean($_REQUEST["id"], "int");
                    } else {
                        $r["id"] = 0;
                    }
                    $available_actions = ["start", "stop"];
                    $available_types = ["QueueServer", "MediaUpdater",
                        "Mirror", "Fetcher"];
                    if (isset($r["name"]) && isset($_REQUEST["action"]) &&
                        in_array($_REQUEST["action"], $available_actions)
                        && isset($_REQUEST["type"]) && in_array(
                        $_REQUEST["type"], $available_types)) {
                        $action = $_REQUEST["action"];
                        $machine_model->update($r["name"],
                            $_REQUEST["action"], $r["id"], $_REQUEST["type"]);
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_servers_updated'),
                            ["start_row", "end_row", "num_show"]);
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_machine_no_action'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    break;
            }
        }
        $parent->pagingLogic($data, $machine_model, "MACHINE",
            C\DEFAULT_ADMIN_PAGING_NUM);
        if (!isset($_REQUEST['arg']) || $_REQUEST['arg'] != 'log') {
            $data['SCRIPT'] .= "toggleReplica(false);";
        }
        return $data;
    }
    /**
     * Handles admin request related to the manage locale activity
     *
     * The manage locale activity allows a user to add/delete locales, view
     * statistics about a locale as well as edit the string for that locale
     *
     * @return array $data info about current locales, statistics for each
     *     locale as well as potentially the currently set string of a
     *     locale and any messages about the success or failure of a
     *     sub activity.
     */
    public function manageLocales()
    {
        $parent = $this->parent;
        $locale_model = $parent->model("locale");
        $possible_arguments = ["addlocale", "deletelocale", "editlocale",
            "editstrings", "search"];
        $search_array = [["tag", "", "", "ASC"]];
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "managelocales";
        $data['CURRENT_LOCALE'] = ["localename" => "",
            'localetag' => "", 'writingmode' => '-1', 'active' => 1];
        $data['WRITING_MODES'] = [
            -1 => tl('system_component_select_mode'),
            "lr-tb" => "lr-tb",
            "rl-tb" => "rl-tb",
            "tb-rl" => "tb-rl",
            "tb-lr" => "tb-lr"
        ];
        $data['FORM_TYPE'] = "addlocale";
        $paging = true;
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $clean_fields = ['localename', 'localetag', 'writingmode',
                'selectlocale', 'active'];
            $edit_preserve_fields = ["selectlocale", "arg",
                "start_row", "end_row", "num_show", "previous_activity",
                "filter", "show"];
            $preserve_fields = ["start_row", "end_row", "num_show"];
            $incomplete = false;
            $required = ['localename', 'localetag'];
            foreach ($clean_fields as $field) {
                $$field = "";
                if ($field == 'active') {
                    $active = 0;
                }
                if (isset($_REQUEST[$field])) {
                    $tmp = trim($parent->clean($_REQUEST[$field], "string"));
                    if ($field == "writingmode" && ($tmp == -1 ||
                        !isset($data['WRITING_MODES'][$tmp]))) {
                        $tmp = "lr-tb";
                    }
                    if ($tmp == "" && in_array($field, $required)) {
                        $incomplete = true;
                    }
                    $$field = $tmp;
                } else if (in_array($field, $required)) {
                    $incomplete = true;
                }
            }
            switch ($_REQUEST['arg']) {
                case "addlocale":
                    if ($incomplete && isset($_REQUEST['update'])) {
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_missing_info'),
                            $preserve_fields);
                    } else if (isset($_REQUEST['update'])) {
                        $locale_model->addLocale(
                            $localename, $localetag, $writingmode, $active);
                        $locale_model->extractMergeLocales();
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_added'),
                            $preserve_fields);
                    }
                    break;
                case "deletelocale":
                    if (!$locale_model->checkLocaleExists($selectlocale)) {
                        return $parent->redirectWithMessage(
                            tl('system_component_localename_doesnt_exists'),
                            $preserve_fields);
                    }
                    $locale_model->deleteLocale($selectlocale);
                    return $parent->redirectWithMessage(
                        tl('system_component_localename_deleted'),
                        $preserve_fields);
                    break;
                case "editlocale":
                    if (!$locale_model->checkLocaleExists($selectlocale)) {
                        return $parent->redirectWithMessage(
                            tl('system_component_localename_doesnt_exists'),
                            $preserve_fields);
                    }
                    $data['FORM_TYPE'] = "editlocale";
                    $info = $locale_model->getLocaleInfo($selectlocale);
                    $change = false;
                    if (isset($localetag) && $localetag != "") {
                        $info["LOCALE_TAG"] = $localetag;
                        $change = true;
                    }
                    if (isset($writingmode) && $writingmode != "") {
                        $info["WRITING_MODE"] = $writingmode;
                        $change = true;
                    }
                    if (isset($_REQUEST['update']) &&
                        $active != $info['ACTIVE']) {
                        $info['ACTIVE'] =  $active;
                        $change=true;
                    }
                    $data['CURRENT_LOCALE']['active'] = $info['ACTIVE'];
                    $data['CURRENT_LOCALE']['localename'] =
                        $info["LOCALE_NAME"];
                    $data['CURRENT_LOCALE']['localetag'] =
                        $selectlocale;
                    $data['CURRENT_LOCALE']['writingmode'] =
                        $info["WRITING_MODE"];
                    if ($change) {
                        $locale_model->updateLocaleInfo($info);
                        return $parent->redirectWithMessage(
                            tl('system_component_locale_updated'),
                            $edit_preserve_fields);
                    }
                    break;
                case "editstrings":
                    if (!isset($selectlocale)) { break; }
                    $paging = false;
                    $data["leftorright"] =
                        (L\getLocaleDirection() == 'ltr') ? "right": "left";
                    $data['PREVIOUS_ACTIVITY'] = "manageLocales";
                    if (isset($_REQUEST['previous_activity']) &&
                        in_array($_REQUEST['previous_activity'], [
                        "security", "searchSources"])) {
                            $data['PREVIOUS_ACTIVITY'] =
                                $_REQUEST['previous_activity'];
                    }
                    $data["ELEMENT"] = "editlocales";
                    $data['CURRENT_LOCALE_NAME'] =
                        $locale_model->getLocaleName($selectlocale);
                    $data['CURRENT_LOCALE_TAG'] = $selectlocale;
                    if (isset($_REQUEST['STRINGS'])) {
                        $safe_strings = [];
                        foreach ($_REQUEST['STRINGS'] as $key => $value) {
                            $clean_key = $parent->clean($key, "string" );
                            $clean_value = $parent->clean($value, "string");
                            $safe_strings[$clean_key] = $clean_value;
                        }
                        $locale_model->updateStringData(
                            $selectlocale, $safe_strings);
                        return $parent->redirectWithMessage(
                            tl('system_component_localestrings_updated'),
                            $edit_preserve_fields);
                    } else {
                        $locale_model->extractMergeLocales();
                    }
                    $data['STRINGS'] =
                        $locale_model->getStringData($selectlocale);
                    $data['DEFAULT_STRINGS'] =
                        $locale_model->getStringData(C\DEFAULT_LOCALE);
                    $data['show'] = "all";
                    $data["show_strings"] =
                        [   "all" => tl('system_component_all_strings'),
                            "missing" => tl('system_component_missing_strings')
                        ];
                    if (isset($_REQUEST['show']) &&
                        $_REQUEST['show'] == "missing") {
                        $data["show"]= "missing";
                        foreach ($data['STRINGS'] as
                            $string_id => $translation) {
                            if ($translation != "") {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                    $data["filter"] = "";
                    if (isset($_REQUEST['filter']) && $_REQUEST['filter']) {
                        $filter = $parent->clean($_REQUEST['filter'], "string");
                        $data["filter"] = $filter;
                        foreach ($data['STRINGS'] as
                            $string_id => $translation) {
                            if (mb_stripos($string_id, $filter) === false &&
                                mb_stripos($translation, $filter) === false) {
                                unset($data['STRINGS'][$string_id]);
                                unset($data['DEFAULT_STRINGS'][$string_id]);
                            }
                        }
                    }
                    $data['NUM_STRINGS_SHOW'] = 100;
                    $data['TOTAL_STRINGS'] = count($data['STRINGS']);
                    $data['LIMIT'] = (isset($_REQUEST['limit'])) ?
                        min($parent->clean($_REQUEST['limit'], 'int'),
                        $data['TOTAL_STRINGS']) : 0;
                    $data['STRINGS'] = array_slice($data['STRINGS'],
                        $data['LIMIT'], $data['NUM_STRINGS_SHOW']);
                    break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        ['name', 'tag', 'mode', 'active'],
                        ['active']);
                    break;
            }
        }
        if ($paging) {
            $parent->pagingLogic($data, $locale_model,
                "LOCALES", C\DEFAULT_ADMIN_PAGING_NUM, $search_array);
        }
        return $data;
    }
    /**
     * Handles admin panel requests for mail, database, tor, proxy server
     * settings
     *
     * @return array $data data for the view concerning the current settings
     *     so they can be displayed
     */
    public function serverSettings()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $role_model = $parent->model("role");
        $activity_model = $parent->model("activity");
        $user_id = $_SESSION['USER_ID'];
        $data = [];
        $profile = [];
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "serversettings";
        switch ($arg) {
            case "update":
                $parent->updateProfileFields($data, $profile,
                    ['AD_LOCATION', 'SEND_MAIL_MEDIA_UPDATER',
                    'USE_FILECACHE', 'USE_MAIL_PHP', 'USE_MEMCACHE',
                    'USE_PROXY']);
                $old_profile =
                    $profile_model->getProfile(C\WORK_DIRECTORY);
                if (strcmp($old_profile["ADVERTISEMENT_TYPE"],
                        $data["ADVERTISEMENT_TYPE"]) !== 0) {
                    $business_id = $role_model->getRoleId('Business User');
                    $ad_id = $activity_model->getActivityIdFromMethodName(
                        'manageAdvertisements');
                    if (isset($data['ADVERTISEMENT_TYPE']) &&
                        in_array($data['ADVERTISEMENT_TYPE'],
                        ['no_advertisements', 'external_advertisements'])) {
                        if($business_id) {
                            $role_model->deleteActivityRole($business_id,
                                $ad_id);
                        }
                        $role_model->deleteActivityRole(C\ADMIN_ROLE, $ad_id);
                    } else {
                        if($business_id) {
                            $role_model->addActivityRole($business_id, $ad_id);
                        }
                        $role_model->addActivityRole(C\ADMIN_ROLE, $ad_id);
                    }
                }
                $db_problem = false;
                if ((isset($profile['DBMS']) &&
                    $profile['DBMS'] != $old_profile['DBMS']) ||
                    (isset($profile['DB_NAME']) &&
                    $profile['DB_NAME'] != $old_profile['DB_NAME']) ||
                    (isset($profile['DB_HOST']) &&
                    $profile['DB_HOST'] != $old_profile['DB_HOST'])) {
                    if (!$profile_model->migrateDatabaseIfNecessary(
                        $profile)) {
                        $db_problem = true;
                    }
                } else if ((isset($profile['DB_USER']) &&
                    $profile['DB_USER'] != $old_profile['DB_USER']) ||
                    (isset($profile['DB_PASSWORD']) &&
                    $profile['DB_PASSWORD'] != $old_profile['DB_PASSWORD'])) {
                    if ($profile_model->testDatabaseManager(
                        $profile) !== true) {
                        $db_problem = true;
                    }
                }
                if ($db_problem) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_db'));
                }
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile)) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'));
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'));
                }
                break;
        }
        $data = array_merge($data,
            $profile_model->getProfile(C\WORK_DIRECTORY));
        if ($data['DBMS'] == lcfirst($data['DBMS'])) {
            //using old name, migrate to new
            $_REQUEST['DBMS'] = ucfirst($data['DBMS']);
            $parent->updateProfileFields($data, $profile,
                ['AD_LOCATION', 'SEND_MAIL_MEDIA_UPDATER',
                'USE_FILECACHE', 'USE_MAIL_PHP', 'USE_MEMCACHE',
                'USE_PROXY']);
            $old_profile = $profile_model->getProfile(C\WORK_DIRECTORY);
            $profile_model->updateProfile(C\WORK_DIRECTORY, $profile,
                $old_profile);
        }
        $data['MEMCACHE_SERVERS'] = str_replace(
            "|Z|","\n", $data['MEMCACHE_SERVERS']);
        $data['PROXY_SERVERS'] = str_replace(
            "|Z|","\n", $data['PROXY_SERVERS']);
        $data['DBMSS'] = [];
        $data['SCRIPT'] .= "logindbms = [];\n";
        foreach ($profile_model->getDbmsList() as $dbms) {
            $data['DBMSS'][$dbms] = $dbms;
            if ($profile_model->loginDbms($dbms)) {
                $data['SCRIPT'] .= "logindbms['$dbms'] = true;\n";
            } else {
                $data['SCRIPT'] .= "logindbms['$dbms'] = false;\n";
            }
        }
        $data['REGISTRATION_TYPES'] = array (
                'disable_registration' =>
                    tl('system_component_configure_disable_registration'),
                'no_activation' =>
                    tl('system_component_configure_no_activation'),
                'email_registration' =>
                    tl('system_component_configure_email_activation'),
                'admin_activation' =>
                    tl('system_component_configure_admin_activation'),
            );
        $data['ADVERTISEMENT_TYPES'] = array (
            'no_advertisements' =>
                tl('system_component_configure_no_advertisements'),
            'keyword_advertisements' =>
                tl('system_component_configure_keyword_advertisements'),
            'external_advertisements' =>
                tl('system_component_configure_external_advertisements'),
             );
        $data['show_mail_info'] = "false";
        if (isset($data['REGISTRATION_TYPE']) &&
            in_array($data['REGISTRATION_TYPE'],
            ['email_registration', 'admin_activation'])) {
            $data['show_mail_info'] = "true";
        }
        $data['show_ad_info'] = "false";
        if (isset($data['ADVERTISEMENT_TYPE']) &&
            in_array($data['ADVERTISEMENT_TYPE'],
                ['no_advertisements', 'keyword_advertisements'])) {
            $data['show_ad_info'] = "true";
        }
        $data['no_mail_php'] =  ($data["USE_MAIL_PHP"]) ? "false" :"true";
        $data['SCRIPT'] .= <<< EOD
    elt('account-registration').onchange = function () {
        var show_mail_info = false;
        no_mail_registration = ['disable_registration', 'no_activation'];
        if (no_mail_registration.indexOf(elt('account-registration').value)
            < 0) {
            show_mail_info = true;
        }
        setDisplay('registration-info', show_mail_info);
    };
    setDisplay('registration-info', {$data['show_mail_info']});
    elt('use-php-mail').onchange = function () {
        setDisplay('smtp-info', (elt('use-php-mail').checked == false));
    };
    setDisplay('smtp-info', {$data['no_mail_php']});

    elt('database-system').onchange = function () {
        setDisplay('login-dbms', self.logindbms[elt('database-system').value]);
    };
    setDisplay('login-dbms', logindbms[elt('database-system').value]);
    elt('use-proxy').onchange = function () {
        setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
    };
    setDisplay('proxy', (elt('use-proxy').checked) ? true : false);
    elt('ad-registration').onchange = function () {
        var show_ad_info = false;
        var ad_type = elt('ad-registration').value;
        no_external_ad = ['no_advertisements', 'keyword_advertisements'];
        if (no_external_ad.indexOf(ad_type)
            < 0) {
            show_ad_info = true;
        }
        setDisplay('ad-location-info', show_ad_info);
        setDisplay('ad-payment-processing',
            (ad_type == 'keyword_advertisements'));
    };
EOD;
        if (class_exists("\Memcache")) {
            $data['SCRIPT'] .= <<< EOD
    elt('use-memcache').onchange = function () {
        setDisplay('filecache', (elt('use-memcache').checked) ? false: true);
        setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
    };
    setDisplay('filecache', (elt('use-memcache').checked) ? false : true);
    setDisplay('memcache', (elt('use-memcache').checked) ? true : false);
EOD;
        }
        return $data;
    }
    /**
     * Responsible for the Captcha Settings and managing Captcha/Recovery
     * questions.
     */
    public function security()
    {
        $parent = $this->parent;
        $captcha_model = $parent->model("captcha");
        $possible_arguments = ["updatequestions", "updatetypes"];
        $data = [];
        $data['CAN_LOCALIZE'] = $parent->model("user")->isAllowedUserActivity(
            $_SESSION['USER_ID'], "manageLocales");
        $profile_model = $parent->model("profile");
        $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
        $data['SCRIPT'] = "";
        $data["ELEMENT"] = "security";
        $data["CURRENT_LOCALE"] = L\getLocaleTag();
        $data['CAPTCHA_MODES'] = array (
           C\TEXT_CAPTCHA =>
               tl('captchasettings_element_text_captcha'),
           C\HASH_CAPTCHA =>
               tl('captchasettings_element_hash_captcha'),
           C\IMAGE_CAPTCHA =>
               tl('captchasettings_element_image_captcha'),
            );
        if (C\FIAT_SHAMIR_MODULUS != "") {
            $data['AUTHENTICATION_MODES'] = array (
                    C\NORMAL_AUTHENTICATION =>
                       tl('serversettings_element_normal_authentication'),
                    C\ZKP_AUTHENTICATION =>
                       tl('serversettings_element_zkp_authentication'),
                );
        } else {
            $data['AUTHENTICATION_MODES'] = array (
                    C\NORMAL_AUTHENTICATION =>
                       tl('serversettings_element_normal_authentication'),
                );
            $data['ZKP_UNAVAILABLE'] = true;
        }
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            switch ($_REQUEST['arg']) {
                case "updatetypes":
                    $change = false;
                    if (in_array($_REQUEST['CAPTCHA_MODE'],
                        array_keys($data['CAPTCHA_MODES']))) {
                        $profile["CAPTCHA_MODE"] = $_REQUEST['CAPTCHA_MODE'];
                        $change = true;
                    }
                    if (in_array($_REQUEST['AUTHENTICATION_MODE'],
                        array_keys($data['AUTHENTICATION_MODES']))) {
                        $profile["AUTHENTICATION_MODE"] =
                            $_REQUEST['AUTHENTICATION_MODE'];
                        $change = true;
                    }
                    if ($change) {
                        $profile_model->updateProfile(C\WORK_DIRECTORY,
                            [], $profile);
                        return $parent->redirectWithMessage(
                            tl('system_component_settings_updated'));
                    } else {
                        return $parent->redirectWithMessage(
                            tl('system_component_no_update_settings'));
                    }
                    break;
            }
        }
        $data = array_merge($data,
            $profile_model->getProfile(C\WORK_DIRECTORY));
        $data["CAPTCHA_MODE"] = $profile["CAPTCHA_MODE"];
        return $data;
    }
    /**
     * Responsible for handling admin request related to the appearance activity
     *
     * The activity is used to control the look and feel of the Yioop instance
     * such as foreground, background color, icons, etc.
     *
     * @return array $data fields for current appearance settings
     */
    public function appearance()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $group_model = $parent->model("group");
        $data = [];
        $profile = [];
        $data["ELEMENT"] = "appearance";
        $data['SCRIPT'] = "";
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch ($arg) {
            case "profile":
                $parent->updateProfileFields($data, $profile,
                    ['LANDING_PAGE']);
                $old_profile =
                    $profile_model->getProfile(C\WORK_DIRECTORY);
                $folder = C\APP_DIR . "/resources";
                if ((!file_exists(C\APP_DIR) && !mkdir(C\APP_DIR)) ||
                    (!file_exists($folder) && !mkdir($folder))) {
                    return $parent->redirectWithMessage(
                        tl('system_component_no_resource_folder'),
                        ['advanced', 'lang']);
                }
                foreach (array('BACKGROUND_IMAGE', 'LOGO','M_LOGO', 'FAVICON',
                    'SEARCHBAR_PATH') as $field) {
                    if (isset($_FILES[$field]['name']) &&
                        $_FILES[$field]['name'] !="") {
                        if ((!in_array($_FILES[$field]['type'],
                            ['image/png', 'image/gif', 'image/jpeg',
                                'image/x-icon']) &&
                            $field != 'SEARCHBAR_PATH') || (
                            $_FILES[$field]['type'] != 'text/xml' &&
                            $field == 'SEARCHBAR_PATH')) {
                            return $parent->redirectWithMessage(
                                tl('system_component_invalid_filetype'),
                                ['advanced', 'lang']);
                        }
                        if ($_FILES[$field]['size'] > C\THUMB_SIZE) {
                            return $parent->redirectWithMessage(
                                tl('system_component_file_too_big'),
                                ['advanced', 'lang']);
                        }
                        $profile[$field] = [];
                        $profile[$field]['name'] = $_FILES[$field]['name'];
                        $profile[$field]['tmp_name'] =
                            $_FILES[$field]['tmp_name'];
                        $data[$field] = C\BASE_URL .
                            "/?c=resource&amp;a=get&amp;" .
                            "f=resources&amp;n=" . $profile[$field]['name'];
                    }
                }
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile)) {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'),
                        ['advanced', 'lang']);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'),
                        ['advanced', 'lang']);
                }
                break;
            case "reset":
                $base_url = C\NAME_SERVER;
                if (defined("BASE_URL")) {
                    $base_url = C\BASE_URL;
                }
                $profile = [
                    'LANDING_PAGE' => false,
                    'BACKGROUND_COLOR' => "#FFFFFF",
                    'BACKGROUND_IMAGE' => "",
                    'FOREGROUND_COLOR' => "#FFFFFF",
                    'SIDEBAR_COLOR' => "#88AA44",
                    'TOPBAR_COLOR' => "#EEEEFF",
                    'LOGO' => "resources/yioop.png",
                    'M_LOGO' => "resources/m-yioop.png",
                    'FAVICON' => $base_url . "favicon.ico",
                    'TIMEZONE' => 'America/Los_Angeles',
                    'SESSION_NAME' => "yioopbiscuit",
                    'CSRF_TOKEN' => "YIOOP_TOKEN",
                    'AUXILIARY_CSS' => "",
                    'SEARCHBAR_PATH' => C\NAME_SERVER."yioopbar.xml"
                ];
                $old_profile = $profile_model->getProfile(C\WORK_DIRECTORY);
                foreach ($old_profile as $key => $value) {
                    $data[$key] =$value;
                }
                $tmp_image = $old_profile['BACKGROUND_IMAGE'];
                $old_profile['BACKGROUND_IMAGE'] = "";
                if ($profile_model->updateProfile(
                    C\WORK_DIRECTORY, $profile, $old_profile,
                    true)) {
                    $old_profile['BACKGROUND_IMAGE'] = $tmp_image;
                    foreach ($profile as $key => $value) {
                        $data[$key] = $value;
                        if (in_array($key, ['BACKGROUND_IMAGE',
                            'LOGO', 'M_LOGO', 'FAVICON', 'SEARCHBAR_PATH'] )
                            && $old_profile[$key] != "") {
                            $resource_name = C\APP_DIR ."/resources/".
                                $old_profile[$key];
                            if (file_exists($resource_name)) {
                                unlink($resource_name);
                            }
                        }
                    }
                    $_REQUEST['advanced'] = "true";
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_reset_completed'));
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'));
                }
                break;
            default:
                $data = array_merge($data,
                    $profile_model->getProfile(C\WORK_DIRECTORY));
        }
        $locale_tag = L\getLocaleTag();
        $not_null_fields = [
            'LOGO' => "resources/yioop.png",
            'M_LOGO' => "resources/m-yioop.png",
            'FAVICON' => C\BASE_URL."favicon.ico",
            'TIMEZONE' => 'America/Los_Angeles',
            'SESSION_NAME' => "yioopbiscuit",
            'CSRF_TOKEN' => "YIOOP_TOKEN"
        ];
        foreach ($not_null_fields as $field => $default) {
            if (!$data[$field]) {
                $data[$field] = $default;
            }
        }
        return $data;
    }
    /**
     * Responsible for handling admin request related to the configure activity
     *
     * The configure activity allows a user to set the work directory for
     * storing data local to this SeekQuarry/Yioop instance. It also allows one
     * to set the default language of the installation, debug info, robot info,
     * test info, etc.
     *
     * @return array $data fields for available language, debug level,
     *      etc as well as results of processing sub activity if any
     */
    public function configure()
    {
        $parent = $this->parent;
        $profile_model = $parent->model("profile");
        $group_model = $parent->model("group");
        $data = [];
        $profile = [];

        $data['SYSTEM_CHECK'] = $this->systemCheck();
        $languages = $parent->model("locale")->getLocaleList();
        foreach ($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if (isset($_REQUEST['lang']) && $_REQUEST['lang']) {
            $data['lang'] = $parent->clean($_REQUEST['lang'], "string");
            $profile['DEFAULT_LOCALE'] = $data['lang'];
            L\setLocaleObject($data['lang']);
        }
        $data["ELEMENT"] = "configure";
        $data['SCRIPT'] = "";
        $data['PROFILE'] = false;
        if (isset($_REQUEST['WORK_DIRECTORY']) ||
            (C\nsdefined('WORK_DIRECTORY') &&
            C\nsdefined('FIX_NAME_SERVER') && C\FIX_NAME_SERVER) ) {
            if (C\nsdefined('WORK_DIRECTORY') && C\nsdefined('FIX_NAME_SERVER')
                && C\FIX_NAME_SERVER && !isset($_REQUEST['WORK_DIRECTORY'])) {
                $_REQUEST['WORK_DIRECTORY'] = C\WORK_DIRECTORY;
                $_REQUEST['arg'] = "directory";
                @unlink($_REQUEST['WORK_DIRECTORY']."/".C\PROFILE_FILE_NAME);
            }
            $dir =
                $parent->clean($_REQUEST['WORK_DIRECTORY'], "string");
            $data['PROFILE'] = true;
            if (strstr(PHP_OS, "WIN")) {
                //convert to forward slashes so consistent with rest of code
                $dir = str_replace("\\", "/", $dir);
                if ($dir[0] != "/" && $dir[1] != ":") {
                    $data['PROFILE'] = false;
                }
            } else if ($dir[0] != "/") {
                    $data['PROFILE'] = false;
            }
            if ($data['PROFILE'] == false) {
                return $parent->redirectWithMessage(
                    tl('system_component_configure_use_absolute_path'),
                    ['lang']);
            }
            if (strstr($dir."/", C\BASE_DIR)) {
                return $parent->redirectWithMessage(
                    tl('system_component_configure_configure_diff_base_dir'),
                    ['lang']);
            }
            $data['WORK_DIRECTORY'] = $dir;
        } else if (C\nsdefined("WORK_DIRECTORY") &&
            strlen(C\WORK_DIRECTORY) > 0 &&
            strcmp(realpath(C\WORK_DIRECTORY), realpath(C\BASE_DIR)) != 0 &&
            (is_dir(C\WORK_DIRECTORY) || is_dir(C\WORK_DIRECTORY."../"))) {
            $data['WORK_DIRECTORY'] = C\WORK_DIRECTORY;
            $data['PROFILE'] = true;
            if(C\WORK_DIRECTORY == C\DEFAULT_WORK_DIRECTORY &&
                is_writable(C\WORK_DIRECTORY) &&
                !file_exists(C\WORK_DIRECTORY. C\PROFILE_FILE_NAME) ) {
                $_REQUEST['arg'] = 'directory';
            }
        }
        $arg = "";
        if (isset($_REQUEST['arg'])) {
            $arg = $_REQUEST['arg'];
        }
        switch ($arg) {
            case "directory":
                if (!isset($data['WORK_DIRECTORY'])) {break;}
                if ($data['PROFILE'] &&
                    file_exists($data['WORK_DIRECTORY']."/".
                        C\PROFILE_FILE_NAME)) {
                    $data = array_merge($data, $profile_model->getProfile(
                            $data['WORK_DIRECTORY']));
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_work_dir_set'),
                        ['lang']);
                } else if ($data['PROFILE'] &&
                    strlen($data['WORK_DIRECTORY']) > 0) {
                    if ($profile_model->makeWorkDirectory(
                        $data['WORK_DIRECTORY'])) {
                        $profile['DBMS'] = 'sqlite3';
                        $data['DBMS'] = 'sqlite3';
                        $profile['DB_NAME'] = 'default';
                        $data['DB_NAME'] = 'default';
                        $profile['USER_AGENT_SHORT'] =
                            tl('system_component_name_your_bot');
                        $data['USER_AGENT_SHORT'] =
                            $profile['USER_AGENT_SHORT'];
                        $uri = UrlParser::getPath($_SERVER['REQUEST_URI']);
                        $http = (isset($_SERVER['HTTPS'])) ? "https://" :
                            "http://";
                        $profile['NAME_SERVER'] =
                            $http . $_SERVER['SERVER_NAME'] . $uri;
                        $data['NAME_SERVER'] = $profile['NAME_SERVER'];
                        $profile['AUTH_KEY'] = L\crawlHash(
                            $data['WORK_DIRECTORY'].time());
                        $data['AUTH_KEY'] = $profile['AUTH_KEY'];
                        $profile['FIAT_SHAMIR_MODULUS'] =
                            L\generateFiatShamirModulus();
                        $robot_instance = str_replace(".", "_",
                            $_SERVER['SERVER_NAME'])."-".time();
                        $profile['ROBOT_INSTANCE'] = $robot_instance;
                        $data['ROBOT_INSTANCE'] = $profile['ROBOT_INSTANCE'];
                        if ($profile_model->updateProfile(
                            $data['WORK_DIRECTORY'], [], $profile)) {
                            if ((defined('WORK_DIRECTORY') &&
                                $data['WORK_DIRECTORY'] == C\WORK_DIRECTORY) ||
                                $profile_model->setWorkDirectoryConfigFile(
                                    $data['WORK_DIRECTORY'])) {
                                return $parent->redirectWithMessage(
                            tl('system_component_configure_work_profile_made'),
                                    ['lang']);
                            } else {
                                return $parent->redirectWithMessage(
                                tl('system_component_configure_no_set_config'),
                                    ['lang']);
                            }
                        } else {
                            $profile_model->setWorkDirectoryConfigFile(
                                $data['WORK_DIRECTORY']);
                            return $parent->redirectWithMessage(
                            tl('system_component_configure_no_create_profile'),
                                ['lang']);
                        }
                    } else {
                        $profile_model->setWorkDirectoryConfigFile(
                            $data['WORK_DIRECTORY']);
                        return $parent->redirectWithMessage(
                            tl('system_component_configure_work_dir_invalid'),
                            ['lang']);
                    }
                } else {
                    $profile_model->setWorkDirectoryConfigFile(
                        $data['WORK_DIRECTORY']);
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_work_dir_invalid'),
                        ['lang']);
                }
                break;
            case "profile":
                $parent->updateProfileFields($data, $profile,
                    ['WEB_ACCESS', 'RSS_ACCESS', 'API_ACCESS']);
                $data['DEBUG_LEVEL'] = 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["ERROR_INFO"])) ? C\ERROR_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["QUERY_INFO"])) ? C\QUERY_INFO : 0;
                $data['DEBUG_LEVEL'] |=
                    (isset($_REQUEST["TEST_INFO"])) ? C\TEST_INFO : 0;
                $profile['DEBUG_LEVEL'] = $data['DEBUG_LEVEL'];
                $old_profile =
                    $profile_model->getProfile($data['WORK_DIRECTORY']);
                if ($profile_model->updateProfile(
                    $data['WORK_DIRECTORY'], $profile, $old_profile)) {
                    if (isset($_REQUEST['ROBOT_DESCRIPTION'])) {
                        $locale_tag = L\getLocaleTag();
                        $robot_description = substr(
                            $parent->clean($_REQUEST['ROBOT_DESCRIPTION'],
                            "string"), 0, C\MAX_GROUP_PAGE_LEN);
                        $group_model->setPageName(C\ROOT_ID, C\PUBLIC_GROUP_ID,
                            "bot", $robot_description, $locale_tag,
                            "", "", "", "");
                    }
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_profile_change'),
                        ['lang']);
                } else {
                    return $parent->redirectWithMessage(
                        tl('system_component_configure_no_change_profile'),
                        ['lang']);
                }
                break;
            default:
                if (isset($data['WORK_DIRECTORY']) &&
                    file_exists($data['WORK_DIRECTORY'] ."/" .
                    C\PROFILE_FILE_NAME)){
                    $data = array_merge($data,
                        $profile_model->getProfile($data['WORK_DIRECTORY']));
                } else {
                    $data['WORK_DIRECTORY'] = "";
                    $data['PROFILE'] = false;
                }
        }
        if ($data['PROFILE']) {
            $locale_tag = L\getLocaleTag();
            $robot_info = $group_model->getPageInfoByName(
                 C\PUBLIC_GROUP_ID, "bot", $locale_tag, "edit");
            $data['ROBOT_DESCRIPTION'] = isset($robot_info["PAGE"]) ?
                $robot_info["PAGE"] : tl('system_component_describe_robot');
        }
        $data['SCRIPT'] .=
            "\nelt('locale').onchange = ".
            "function () { elt('configureProfileForm').submit();};\n";

        return $data;
    }
    /**
     * Checks to see if the current machine has php configured in a way
     * Yioop! can run.
     *
     * @return string a message indicatign which required and optional
     *     components are missing; or "Passed" if nothing missing.
     */
     public function systemCheck()
     {
        $parent = $this->parent;
        $required_items = [
            [   "name" => "Multi-Curl",
                "check"=>"curl_multi_init", "type"=>"function"],
            [   "name" => "GD Graphics Library",
                "check"=>"imagecreate", "type"=>"function"],
            [   "name" => "Multibyte Character Library",
                "check"=>"mb_internal_encoding", "type"=>"function"],
            [   "name" => "PDO SQLite3 Library",
                "check"=>"\PDO", "type"=>"class"],
            [   "name" =>
                    "Process Creation Functions (popen, pclose, and exec".
                    " needed for crawling)",
                "check"=>"popen", "type"=>"function"],
        ];
        $optional_items = [
         /* as an example of what this array could contain...
            ["name" => "Memcache", "check" => "\Memcache","type"=> "class"], */
        ];

        $missing_required = "";
        $comma = "";
        foreach ($required_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach ($check_parts as $check) {
                if ($check_function($check)) {
                    $check_flag = false;
                }
            }
            if ($check_flag) {
                $missing_required .= $comma.$item["name"];
                $comma = ",<br />";
            }
        }
        if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
            $missing_required .= $comma.tl("system_component_php_version");
            $comma = ", ";
        }

        $out = "";
        $br = "";

        if (!is_writable(C\BASE_DIR."/configs/Config.php")) {
            $out .= tl('system_component_no_write_config_php');
            $br = "<br />";
        }

        if (defined(C\WORK_DIRECTORY) && !is_writable(C\WORK_DIRECTORY)) {
            $out .= $br. tl('system_component_no_write_work_dir');
            $br = "<br />";
        }

        if (intval(ini_get("post_max_size")) < 2) {
            $out .= $br. tl('system_component_post_size_small');
            $br = "<br />";
        }

        if ($missing_required != "") {
            $out .= $br.
                tl('system_component_missing_required'). "<br />". 
                $missing_required;
            $br = "<br />";
        }

        $missing_optional = "";
        $comma = "";
        foreach ($optional_items as $item) {
            $check_function = $item["type"]."_exists";
            $check_parts = explode("|", $item["check"]);
            $check_flag = true;
            foreach ($check_parts as $check) {
                if ($check_function($check)) {
                    $check_flag = false;
                }
            }
            if ($check_flag) {
                $missing_optional .= $comma.$item["name"];
                $comma = ", ";
            }
        }

        if ($missing_optional != "") {
            $out .= $br.
                tl('system_component_missing_optional') . "<br />".
                $missing_optional;
            $br = "<br />";
        }

        if ($out == "") {
            $out = tl('system_component_check_passed');
        } else {
            $out = "<span class='red'>$out</span>";
        }
        if (file_exists(C\BASE_DIR."/configs/LocalConfig.php")) {
            $out .= "<br />".tl('system_component_using_local_config');
        }
        return $out;
     }
}
