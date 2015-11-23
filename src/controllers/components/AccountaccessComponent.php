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
namespace seekquarry\yioop\controllers\components;

use seekquarry\yioop\configs as C;

/**
 * Component of the Yioop control panel used to handle activitys for
 * managing accounts, users, roles, and groups. i.e., Settings of users
 * and groups, what roles and groups a user has, what roles and users
 * a group has, and what activities make up a role. It is used by
 * AdminController
 *
 * @author Chris Pollett
 */
class AccountaccessComponent extends Component
{

    /**
     * This method is data to signin a user and initialize the data to be
     * display in a view
     *
     * @return array empty array of data to show so far in view
     */
    public function signin()
    {
        $parent = $this->parent;
        $data = [];
        $_SESSION['USER_ID'] =
            $parent->model("signin")->getUserId($_REQUEST['username']);
        return $data;
    }

    /**
     * Used to handle the change current user password admin activity
     *
     * @return array $data SCRIPT field contains success or failure message
     */
    public function manageAccount()
    {
        $parent = $this->parent;
        $signin_model = $parent->model("signin");
        $group_model = $parent->model("group");
        $user_model = $parent->model("user");
        $crawl_model = $parent->model("crawl");
        $role_model = $parent->model("role");
        $profile_model = $parent->model("profile");
        $profile = $profile_model->getProfile(C\WORK_DIRECTORY);
        $data["ELEMENT"] = "manageaccount";
        $data['SCRIPT'] = "";
        $data['MESSAGE'] = "";
        $data['yioop_advertisement'] = $profile['ADVERTISEMENT_TYPE'];
        if (!in_array($profile['ADVERTISEMENT_TYPE'],
            ['no_advertisements','external_advertisements'])) {
            $data['yioop_advertisement'] = true;
        } else {
            $data['yioop_advertisement'] = false;
        }
        $user_id = $_SESSION['USER_ID'];
        $username = $signin_model->getUserName($user_id);
        $data["USER"] = $user_model->getUser($username);
        $data["CRAWL_MANAGER"] = false;
        if ($user_model->isAllowedUserActivity($user_id, "manageCrawls")) {
            $data["CRAWL_MANAGER"] = true;
            $machine_urls = $parent->model("machine")->getQueueServerUrls();
            list($stalled, $status, $recent_crawls) =
                $crawl_model->combinedCrawlInfo($machine_urls);
            $data = array_merge($data, $status);
            $data["CRAWLS_RUNNING"] = 0;
            $data["NUM_CLOSED_CRAWLS"] = count($recent_crawls);
            if (!empty($data['CRAWL_TIME'])) {
                $data["CRAWLS_RUNNING"] = 1;
                $data["NUM_CLOSED_CRAWLS"]--;
            }
        }
        if (isset($_REQUEST['edit']) && $_REQUEST['edit'] == "true") {
            $data['EDIT_USER'] = true;
        }
        if (isset($_REQUEST['edit_pass'])) {
            if ($_REQUEST['edit_pass'] == "true") {
                $data['EDIT_USER'] = true;
                $data['EDIT_PASSWORD'] = true;
                $data['AUTHENTICATION_MODE'] = C\AUTHENTICATION_MODE;
                $data['FIAT_SHAMIR_MODULUS'] = C\FIAT_SHAMIR_MODULUS;
                $data['INCLUDE_SCRIPTS'] = ["sha1", "zkp", "big_int"];
            } else {
                $data['EDIT_USER'] = true;
            }
        }
        if(isset($data['EDIT_USER']) && $data['EDIT_USER']) {
            $business_id = $role_model->getRoleId('Business User');
            if(!$business_id || $business_id < 0) {
                $data['yioop_advertisement'] = false;
            } else {
                $role_ids = $user_model->isAllowedUserActivity($user_id,
                    "manageAdvertisements", true);
                if(is_array($role_ids) && (count($role_ids) > 1 ||
                    $role_ids[0] != $business_id) ) {
                    $data['yioop_advertisement'] = false;
                }
            }
        }
        $data['USERNAME'] = $username;
        $data['NUM_SHOWN'] = 5;
        $data['GROUPS'] = $group_model->getRows(0, $data['NUM_SHOWN'],
            $data['NUM_GROUPS'], [["name", "", "", "ASC"]], [$user_id, false]);
        $num_shown = count($data['GROUPS']);
        for ($i = 0; $i < $num_shown; $i++) {
            $search_array = [
                ["group_id", "=", $data['GROUPS'][$i]['GROUP_ID'], ""],
                ["pub_date", "", "", "DESC"]
                ];
            $item = $group_model->getGroupItems(0, 1,
                $search_array, $user_id);
            $data['GROUPS'][$i]['NUM_POSTS'] =
                $group_model->getGroupItemCount($search_array, $user_id);
            $data['GROUPS'][$i]['NUM_THREADS'] =
                $group_model->getGroupItemCount($search_array, $user_id,
                $data['GROUPS'][$i]['GROUP_ID']);
            $data['GROUPS'][$i]['NUM_PAGES'] =
                $group_model->getGroupPageCount(
                $data['GROUPS'][$i]['GROUP_ID']);
            if (isset($item[0]['TITLE'])) {
                $data['GROUPS'][$i]["ITEM_TITLE"] = $item[0]['TITLE'];
                $data['GROUPS'][$i]["THREAD_ID"] = $item[0]['PARENT_ID'];
            } else {
                $data['GROUPS'][$i]["ITEM_TITLE"] =
                    tl('accountaccess_component_no_posts_yet');
                $data['GROUPS'][$i]["THREAD_ID"] = -1;
            }
        }
        $data['NUM_SHOWN'] = $num_shown;
        $data['NUM_MIXES'] = count($crawl_model->getMixList($user_id));
        $arg = (isset($_REQUEST['arg'])) ? $_REQUEST['arg'] : "";
        switch ($arg) {
            case "updateuser":
                if (isset($_REQUEST['new_password']) ) {
                    $pass_len = strlen($_REQUEST['new_password']);
                    if ($pass_len > C\ZKP_PASSWORD_LEN ||
                        (C\AUTHENTICATION_MODE != C\ZKP_AUTHENTICATION &&
                            $pass_len > C\LONG_NAME_LEN
                        )) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_too_long'),
                            ["edit", "edit_pass"]);
                    }
                }
                if (isset($data['EDIT_PASSWORD']) &&
                    (!isset($_REQUEST['retype_password']) ||
                    !isset($_REQUEST['new_password']) ||
                    $_REQUEST['retype_password'] !=
                        $_REQUEST['new_password'])){
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_passwords_dont_match'),
                        ["edit", "edit_pass"]);
                }
                $result = $signin_model->checkValidSignin($username,
                    $parent->clean($_REQUEST['password'], "string") );
                if (!$result) {
                    return $parent->redirectWithMessage(
                        tl('accountaccess_component_invalid_password'),
                        ["edit", "edit_pass"]);
                }
                if (isset($data['EDIT_PASSWORD'])) {
                    if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) {
                        $signin_model->changePasswordZKP($username,
                            $parent->clean($_REQUEST['new_password'],
                            "string"));
                    } else {
                        $signin_model->changePassword($username,
                            $parent->clean($_REQUEST['new_password'],
                            "string"));
                    }
                }
                $user = [];
                $user['USER_ID'] = $user_id;
                $fields = ["EMAIL" => C\LONG_NAME_LEN,
                    "FIRST_NAME" => C\NAME_LEN, "LAST_NAME" => C\NAME_LEN];
                foreach ($fields as $field => $len) {
                    if (isset($_REQUEST[$field])) {
                        $user[$field] = substr($parent->clean(
                            $_REQUEST[$field], "string"), 0, $len);
                        $data['USER'][$field] =  $user[$field];
                    }
                }
                $is_advertiser = false;
                $business_id = $role_model->getRoleId('Business User');
                if (isset($_REQUEST['IS_ADVERTISER']) && $business_id ) {
                    $user['IS_ADVERTISER'] = 1;
                    $data['USER']['IS_ADVERTISER'] = $user['IS_ADVERTISER'];
                    $is_advertiser = true;
                    $role_model->addUserRole($user_id, $business_id);
                } else if ($business_id) {
                    $user['IS_ADVERTISER'] = 0;
                    $data['USER']['IS_ADVERTISER'] = $user['IS_ADVERTISER'];
                    $role_model->deleteUserRole($user_id, $business_id);
                }
                if(isset($_FILES['user_icon']['name']) &&
                    $_FILES['user_icon']['name'] !="") {
                    if (!in_array($_FILES['user_icon']['type'],
                        ['image/png', 'image/gif', 'image/jpeg'])) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_unknown_imagetype'),
                            ["edit", "edit_pass"]);
                    }
                    if ($_FILES['user_icon']['size'] > C\THUMB_SIZE) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_icon_too_big'),
                             ["edit", "edit_pass"]);
                    }
                    $user['IMAGE_STRING'] = file_get_contents(
                        $_FILES['user_icon']['tmp_name']);
                    $folder = $user_model->getUserIconFolder(
                        $user['USER_ID']);
                    if (!$folder) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_no_user_folder'),
                            ["edit", "edit_pass"]);
                    }
                }
                $user_model->updateUser($user);
                $data['USER']['USER_ICON'] = $user_model->getUserIconUrl(
                    $user['USER_ID']);
                unset($user['IMAGE_STRING']);
                return $parent->redirectWithMessage(
                    tl('accountaccess_component_user_updated'),
                    ["edit", "edit_pass"]);
          }
        return $data;
    }
    /**
     * Used to handle the manage user activity.
     *
     * This activity allows new users to be added, old users to be
     * deleted and allows roles to be added to/deleted from a user
     *
     * @return array $data infomation about users of the system, roles, etc.
     *     as well as status messages on performing a given sub activity
     */
    public function manageUsers()
    {
        $parent = $this->parent;
        if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) {
            $_SESSION['SALT_VALUE'] = rand(0, 1);
            $_SESSION['AUTH_COUNT'] = 1;
            $data['INCLUDE_SCRIPTS'] = ["sha1", "zkp", "big_int"];
            $data['AUTHENTICATION_MODE'] = C\ZKP_AUTHENTICATION;
            $data['FIAT_SHAMIR_MODULUS'] = C\FIAT_SHAMIR_MODULUS;
        } else {
            $data['AUTHENTICATION_MODE'] = C\NORMAL_AUTHENTICATION;
            unset($_SESSION['SALT_VALUE']);
        }
        $request_fields = ['start_row', 'num_show', 'end_row',
            'visible_roles', 'visible_groups', 'role_filter',
            'group_filter', 'role_limit', 'group_limit'];
        $signin_model = $parent->model("signin");
        $user_model = $parent->model("user");
        $group_model = $parent->model("group");
        $role_model = $parent->model("role");
        $possible_arguments = ["adduser", 'edituser', 'search',
            "deleteuser", "adduserrole", "deleteuserrole",
            "addusergroup", "deleteusergroup", "updatestatus"];

        $data["ELEMENT"] = "manageusers";
        $data['SCRIPT'] = "";
        $data['STATUS_CODES'] = [
            C\ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            C\INACTIVE_STATUS => tl('accountaccess_component_inactive_status'),
            C\SUSPENDED_STATUS =>
                tl('accountaccess_component_suspended_status'),
        ];
        $data['MEMBERSHIP_CODES'] = [
            C\INACTIVE_STATUS => tl('accountaccess_component_request_join'),
            C\INVITED_STATUS => tl('accountaccess_component_invited'),
            C\ACTIVE_STATUS => tl('accountaccess_component_active_status'),
            C\SUSPENDED_STATUS =>
                tl('accountaccess_component_suspended_status')
        ];
        $data['FORM_TYPE'] = "adduser";
        $search_array = [];
        $username = "";
        if (isset($_REQUEST['user_name'])) {
            $username = substr($parent->clean($_REQUEST['user_name'], "string"),
                0, C\NAME_LEN);
        }
        if ($username == "" && isset($_REQUEST['arg']) && $_REQUEST['arg']
            != "search") {
            unset($_REQUEST['arg']);
        }
        $select_group = isset($_REQUEST['selectgroup']) ?
            $parent->clean($_REQUEST['selectgroup'],"string") : "";
        $select_role = isset($_REQUEST['selectrole']) ?
            $parent->clean($_REQUEST['selectrole'],"string") : "";
        if (isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'edituser') {
            if ($select_role != "") {
                $_REQUEST['arg'] = "adduserrole";
            } else if ($select_group != ""){
                $_REQUEST['arg'] = "addusergroup";
            }
        }
        $user_id = -1;
        $data['visible_roles'] = 'false';
        $data['visible_groups'] = 'false';
        if ($username != "") {
            $user_id = $signin_model->getUserId($username);
            if ($user_id) {
                $this->getUserRolesData($data, $user_id);
                $this->getUserGroupsData($data, $user_id);
            }
        }
        $data['CURRENT_USER'] = ["user_name" => "", "first_name" => "",
            "last_name" => "", "email" => "", "status" => "", "password" => "",
            "repassword" => ""];
        $data['PAGING'] = "";
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {
            $pass_len = (isset($_REQUEST['new_password'])) ?
                strlen($_REQUEST['new_password']) : 0;
            switch ($_REQUEST['arg']) {
                case "adduser":
                    if ($pass_len > C\ZKP_PASSWORD_LEN ||
                        (C\AUTHENTICATION_MODE != C\ZKP_AUTHENTICATION &&
                            $pass_len > C\LONG_NAME_LEN )) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_too_long'),
                            $request_fields);
                    } else if ($_REQUEST['retypepassword'] !=
                        $_REQUEST['password']) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_dont_match'),
                            $request_fields);
                    } else if (trim($username) == "") {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_invalid_username'),
                            $request_fields);
                    } else if ($signin_model->getUserId($username) > 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_exists'),
                            $request_fields);
                    } else if (!isset($data['STATUS_CODES'][
                        $_REQUEST['status']])) {
                        $_REQUEST['status'] = C\INACTIVE_STATUS;
                    } else {
                        $norm_password = "";
                        $zkp_password = "";
                        if (C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) {
                            $zkp_password =
                                substr($parent->clean($_REQUEST['password'],
                                    "string"), 0, C\ZKP_PASSWORD_LEN);
                        } else {
                            $norm_password =
                                substr($parent->clean($_REQUEST['password'],
                                "string"), 0, C\LONG_NAME_LEN);
                        }
                        $username = trim($username);
                        $user_model->addUser($username, $norm_password,
                            substr(trim($parent->clean($_REQUEST['first_name'],
                                "string")), 0, C\NAME_LEN),
                            substr(trim($parent->clean($_REQUEST['last_name'],
                                "string")), 0, C\NAME_LEN),
                            substr(trim($parent->clean($_REQUEST['email'],
                                "string")), 0, C\LONG_NAME_LEN),
                            $_REQUEST['status'], $zkp_password
                        );
                        $data['USER_NAMES'][$username] = $username;
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_added'),
                            $request_fields);
                    }
                    break;
                case "edituser":
                    $data['FORM_TYPE'] = "edituser";
                    $user = $user_model->getUser($username);
                    if (!$user) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_doesnt_exist'),
                            $request_fields);
                    }
                    $update = false;
                    $error = false;
                    if ($user["USER_ID"] == C\PUBLIC_USER_ID) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_cant_edit_public_user'),
                            $request_fields);
                    }
                    foreach ($data['CURRENT_USER'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'user_name') {
                            if ($field != "password" || ($_REQUEST["password"]
                                != md5("password") && $_REQUEST["password"] ==
                                $_REQUEST["retypepassword"])) {
                                $tmp = $parent->clean(
                                    $_REQUEST[$field], "string");
                                if ($tmp != $user[$upper_field]) {
                                    $user[$upper_field] = $tmp;
                                     if (C\AUTHENTICATION_MODE ==
                                        C\ZKP_AUTHENTICATION && $upper_field
                                        == "PASSWORD") {
                                        $user["ZKP_PASSWORD"] = $tmp;
                                        $user[$upper_field] ='';
                                    }
                                    if (!isset($_REQUEST['change_filter'])) {
                                        $update = true;
                                    }
                                }
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            } else if ($_REQUEST["password"] !=
                                $_REQUEST["retypepassword"]) {
                                $error = true;
                                break;
                            }
                        } else if (isset($user[$upper_field])){
                            if ($field != "password" &&
                                $field != "retypepassword") {
                                $data['CURRENT_USER'][$field] =
                                    $user[$upper_field];
                            }
                        }
                    }
                    $data['CURRENT_USER']['password'] = md5("password");
                    $data['CURRENT_USER']['retypepassword'] = md5("password");
                    if ($error) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_passwords_dont_match'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if ($update) {
                        $user_model->updateUser($user);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_updated'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if (isset($_REQUEST['change_filter'])) {
                        if ($_REQUEST['change_filter'] == "group") {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_group').
                                "</h1>');";
                        } else {
                            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                                tl('accountaccess_component_user_filter_role').
                                "</h1>');";
                        }
                    }
                    $data['CURRENT_USER']['id'] = $user_id;
                    break;
                case "deleteuser":
                    $user_id =
                        $signin_model->getUserId($username);
                    if ($user_id <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_username_doesnt_exists'
                            ), $request_fields);
                    } else if (in_array(
                        $user_id, [C\ROOT_ID, C\PUBLIC_USER_ID])) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_cant_delete_builtin'),
                            $request_fields);
                    } else {
                        $user_model->deleteUser($username);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_user_deleted'),
                            $request_fields);
                    }
                    break;
                case "adduserrole":
                    $_REQUEST['arg'] = 'edituser';
                    if ($user_id <= 0 ) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            $request_fields);
                    } else  if (!($role_id = $role_model->getRoleId(
                        $select_role))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if ($role_model->checkUserRole($user_id,
                        $role_id)) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_already_added'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else {
                        $role_model->addUserRole($user_id, $role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_added'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    }
                    break;
                case "addusergroup":
                    $_REQUEST['arg'] = 'edituser';
                    if ( $user_id <= 0 ) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            $request_fields);
                    } else if (!($group_id = $group_model->getGroupId(
                        $select_group))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_doesnt_exists'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else if ($group_model->checkUserGroup($user_id,
                        $group_id)){
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_already_added'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else {
                        $group_model->addUserGroup($user_id,
                            $group_id);
                        $this->getUserGroupsData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_added'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    }
                    break;
                case "deleteuserrole":
                    $_REQUEST['arg'] = 'edituser';
                    if ($user_id <= 0) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            $request_fields);
                    } else if (!($role_model->checkUserRole($user_id,
                        $select_role))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $role_model->deleteUserRole($user_id,
                            $select_role);
                        $this->getUserRolesData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_deleted'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    }
                    break;
                case "deleteusergroup":
                    $_REQUEST['arg'] = 'edituser';
                    if ($user_id <= 0) {
                        $_REQUEST['arg'] = 'adduser';
                        return $parent->redirectWithMessage(
                        tl('accountaccess_component_username_doesnt_exists'),
                            $request_fields);
                    } else if (!($group_model->checkUserGroup($user_id,
                        $select_group))) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_groupname_doesnt_exists'
                            ), array_merge(array('arg', 'user_name'),
                            $request_fields));
                    } else {
                        $data['FORM_TYPE'] = "edituser";
                        $group_model->deleteUserGroup($user_id,
                            $select_group);
                        $this->getUserGroupsData($data, $user_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_group_deleted'),
                            array_merge(array('arg', 'user_name'),
                            $request_fields));
                    }
                    break;
                case "search":
                    $data["FORM_TYPE"] = "search";
                    $search_array =
                        $parent->tableSearchRequestHandler($data,
                        ['user', 'first', 'last', 'email', 'status'],
                        ['status'], "_name");
                    break;
                case "updatestatus":
                    $user_id = $signin_model->getUserId($username);
                    if (!isset($data['STATUS_CODES'][$_REQUEST['userstatus']])||
                        $user_id == 1) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_username_doesnt_exists'
                            ), $request_fields);
                    } else {
                        $user_model->updateUserStatus($user_id,
                            $_REQUEST['userstatus']);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_userstatus_updated'),
                            $request_fields);
                    }
                    break;
            }
        }
        if ($search_array == []) {
            $search_array[] = ["user", "", "", "ASC"];
        }
        $parent->pagingLogic($data, $user_model, "USERS",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        $num_users = count($data['USERS']);
        for ($i = 0; $i < $num_users; $i++) {
            $data['USERS'][$i]['NUM_GROUPS'] =
                $group_model->countUserGroups($data['USERS'][$i]['USER_ID']);
        }
        return $data;
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the roles that a user
     * has subject to $_REQUEST['role_limit'] and $_REQUEST['role_filter'].
     * Information about these roles is added as fields to
     * $data[NUM_USER_ROLES'] and $data['USER_ROLES']
     *
     * @param array& $data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    public function getUserRolesData(&$data, $user_id)
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $data['visible_roles'] = (isset($_REQUEST['visible_roles']) &&
            $_REQUEST['visible_roles']=='true') ? 'true' : 'false';
        if ($data['visible_roles'] == 'false') {
            unset($_REQUEST['role_filter']);
            unset($_REQUEST['role_limit']);
        }
        if (isset($_REQUEST['role_filter'])) {
            $role_filter = substr($parent->clean(
                $_REQUEST['role_filter'], 'string'), 0, C\NAME_LEN);
        } else {
            $role_filter = "";
        }
        $data['ROLE_FILTER'] = $role_filter;
        $data['NUM_USER_ROLES'] =
            $role_model->countUserRoles($user_id, $role_filter);
        if (isset($_REQUEST['role_limit'])) {
            $role_limit = min($parent->clean(
                $_REQUEST['role_limit'], 'int'),
                $data['NUM_USER_ROLES']);
            $role_limit = max($role_limit, 0);
        } else {
            $role_limit = 0;
        }
        $data['ROLE_LIMIT'] = $role_limit;
        $data['USER_ROLES'] =
            $role_model->getUserRoles($user_id, $role_filter,
            $role_limit);
    }
    /**
     * Uses $_REQUEST and $user_id to look up all the groups that a user
     * belongs to subject to $_REQUEST['group_limit'] and
     * $_REQUEST['group_filter']. Information about these roles is added as
     * fields to $data[NUM_USER_GROUPS'] and $data['USER_GROUPS']
     *
     * @param array& $data data for the manageUsers view.
     * @param int $user_id user to look up roles for
     */
    public function getUserGroupsData(&$data, $user_id)
    {
        $parent = $this->parent;
        $group_model = $parent->model("group");
        $data['visible_groups'] = (isset($_REQUEST['visible_groups']) &&
            $_REQUEST['visible_groups']=='true') ? 'true' : 'false';
        if ($data['visible_groups'] == 'false') {
            unset($_REQUEST['group_filter']);
            unset($_REQUEST['group_limit']);
        }
        if (isset($_REQUEST['group_filter'])) {
            $group_filter = substr($parent->clean(
                $_REQUEST['group_filter'], 'string'), 0, C\SHORT_TITLE_LEN);
        } else {
            $group_filter = "";
        }
        $data['GROUP_FILTER'] = $group_filter;
        $data['NUM_USER_GROUPS'] =
            $group_model->countUserGroups($user_id, $group_filter);
        if (isset($_REQUEST['group_limit'])) {
            $group_limit = min($parent->clean(
                $_REQUEST['group_limit'], 'int'),
                $data['NUM_USER_GROUPS']);
            $group_limit = max($group_limit, 0);
        } else {
            $group_limit = 0;
        }
        $data['GROUP_LIMIT'] = $group_limit;
        $data['USER_GROUPS'] =
            $group_model->getUserGroups($user_id, $group_filter,
            $group_limit);
    }
    /**
     * Used to handle the manage role activity.
     *
     * This activity allows new roles to be added, old roles to be
     * deleted and allows activities to be added to/deleted from a role
     *
     * @return array $data information about roles in the system, activities,
     *     etc. as well as status messages on performing a given sub activity
     *
     */
    public function manageRoles()
    {
        $parent = $this->parent;
        $role_model = $parent->model("role");
        $possible_arguments = ["addactivity", "addrole",
                "deleteactivity","deleterole", "editrole", "search"];
        $data["ELEMENT"] = "manageroles";
        $data['SCRIPT'] = "";
        $data['FORM_TYPE'] = "addrole";

        $search_array = [];
        $data['CURRENT_ROLE'] = ["name" => ""];
        $data['PAGING'] = "";
        if (isset($_REQUEST['arg']) && $_REQUEST['arg'] == 'editrole') {
            if (isset($_REQUEST['selectactivity']) &&
                $_REQUEST['selectactivity'] >= 0) {
                $_REQUEST['arg'] = "addactivity";
            }
        }
        if (isset($_REQUEST['name'])) {
            $name = substr($parent->clean($_REQUEST['name'], "string"),
                0, C\NAME_LEN);
             $data['CURRENT_ROLE']['name'] = $name;
        } else {
            $name = "";
        }
        if ($name != "") {
            $role_id = $role_model->getRoleId($name);
            $data['ROLE_ACTIVITIES'] =
                $role_model->getRoleActivities($role_id);
            $all_activities = $parent->model("activity")->getActivityList();
            $activity_ids = [];
            $activity_names = [];
            foreach ($all_activities as $activity) {
                $activity_ids[] = $activity['ACTIVITY_ID'];
                $activity_names[$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }

            $available_activities = [];
            $role_activity_ids = [];
            foreach ($data['ROLE_ACTIVITIES'] as $activity) {
                $role_activity_ids[] = $activity["ACTIVITY_ID"];
            }
            $tmp = [];
            foreach ($all_activities as $activity) {
                if (!in_array($activity["ACTIVITY_ID"], $role_activity_ids) &&
                    !isset($tmp[$activity["ACTIVITY_ID"]])) {
                    $tmp[$activity["ACTIVITY_ID"]] = true;
                    $available_activities[] = $activity;
                }
            }
            $data['AVAILABLE_ACTIVITIES'][-1] =
                tl('accountaccess_component_select_activityname');
            foreach ($available_activities as $activity) {
                $data['AVAILABLE_ACTIVITIES'][$activity['ACTIVITY_ID']] =
                    $activity['ACTIVITY_NAME'];
            }
            if (isset($_REQUEST['selectactivity'])) {
                $select_activity =
                    $parent->clean($_REQUEST['selectactivity'], "int" );
            } else {
                $select_activity = "";
            }
            if ($select_activity != "") {
                $data['SELECT_ACTIVITY'] = $select_activity;
            } else {
                $data['SELECT_ACTIVITY'] = -1;
            }
        }
        if (isset($_REQUEST['arg']) &&
            in_array($_REQUEST['arg'], $possible_arguments)) {

            switch ($_REQUEST['arg']) {
                case "addactivity":
                    $_REQUEST['arg'] = "editrole";
                    if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name"]);
                    } else if (!in_array($select_activity, $activity_ids)) {
                        return $parent->redirectWithMessage(
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name"]);
                    } else {
                        $role_model->addActivityRole(
                            $role_id, $select_activity);
                        unset($data['AVAILABLE_ACTIVITIES'][$select_activity]);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_activity_added'),
                            ["arg", "start_row", "end_row", "num_show", "name"]
                            );
                    }
                    break;
                case "addrole":
                    $name = trim($name);
                    if ($name != "" && $role_model->getRoleId($name) > 0) {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_exists').
                            "</h1>')";
                    } else if ($name != "") {
                        $role_model->addRole($name);
                        $data['CURRENT_ROLE']['name'] = "";
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_added').
                            "</h1>')";
                   } else {
                        $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                            tl('accountaccess_component_rolename_blank').
                            "</h1>')";
                   }
                   $data['CURRENT_ROLE']['name'] = "";
                    break;
                case "deleteactivity":
                   $_REQUEST['arg'] = "editrole";
                   if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name"]);
                    } else if (!in_array($select_activity, $activity_ids)) {
                        return $parent->redirectWithMessage(
                            tl(
                            'accountaccess_component_activityname_doesnt_exists'
                            ), ["arg", "start_row", "end_row", "num_show",
                            "name"]);
                    } else {
                        $role_model->deleteActivityRole(
                            $role_id, $select_activity);
                        $data['ROLE_ACTIVITIES'] =
                            $role_model->getRoleActivities($role_id);
                        $data['AVAILABLE_ACTIVITIES'][$select_activity] =
                            $activity_names[$select_activity];
                        $data['SELECT_ACTIVITY'] = -1;
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_activity_deleted'),
                            ["arg", "start_row", "end_row", "num_show",
                            "name"]);
                    }
                    break;
                case "deleterole":
                    if (($role_id = $role_model->getRoleId($name)) <= 0) {
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_doesnt_exists'
                            ),["start_row", "end_row", "num_show"]);
                    } else {
                        $role_model->deleteRole($role_id);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_rolename_deleted'),
                            ["start_row", "end_row", "num_show"]);
                    }
                    $data['CURRENT_ROLE']['name'] = "";
                    break;
                case "editrole":
                    $data['FORM_TYPE'] = "editrole";
                    $role = false;
                    if ($name) {
                        $role = $role_model->getRole($name);
                    }
                    if ($role === false) {
                        $data['FORM_TYPE'] = "addrole";
                        break;
                    }
                    $update = false;
                    foreach ($data['CURRENT_ROLE'] as $field => $value) {
                        $upper_field = strtoupper($field);
                        if (isset($_REQUEST[$field]) && $field != 'name') {
                            $role[$upper_field] = $parent->clean(
                                $_REQUEST[$field], "string");
                            $data['CURRENT_ROLE'][$field] =
                                $role[$upper_field];
                            $update = true;
                        } else if (isset($role[$upper_field])){
                            $data['CURRENT_ROLE'][$field] =
                                $role[$upper_field];
                        }
                    }
                    if ($update) {
                        $role_model->updateRole($role);
                        return $parent->redirectWithMessage(
                            tl('accountaccess_component_role_updated'),
                            ["arg", "start_row", "end_row", "num_show"]);
                    }
                    break;
                case "search":
                    $search_array = $parent->tableSearchRequestHandler($data,
                        ['name']);
                    break;
            }
        }
        if ($search_array == []) {
            $search_array[] = ["name", "", "", "ASC"];
        }
        $parent->pagingLogic($data, $role_model, "ROLES",
            C\DEFAULT_ADMIN_PAGING_NUM, $search_array, "");
        return $data;
    }
}
