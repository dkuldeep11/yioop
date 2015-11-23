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
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\Processors\ImageProcessor;

/** For getLocaleTag*/
require_once __DIR__.'/../library/LocaleFunctions.php';
/**
 * This class is used to handle
 * database statements related to User Administration
 *
 * @author Chris Pollett
 */
class UserModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * In this case, things will in general map to the USERS table in the
     * Yioop data base
     * var array
     */
    public $search_table_column_map = ["first"=>"FIRST_NAME",
        "last" => "LAST_NAME", "user" => "USER_NAME", "email"=>"EMAIL",
        "status"=>"STATUS", "accounttype" => "IS_ADVERTISER"];
    /**
     * These fields if present in $search_array (used by @see getRows() ),
     * but with value "-1", will be skipped as part of the where clause
     * but will be used for order by clause
     * @var array
     */
    public $any_fields = ["status"];
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     */
    public function selectCallback($args = null)
    {
        return "USER_ID, USER_NAME, FIRST_NAME, LAST_NAME, EMAIL, STATUS";
    }
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     */
    public function fromCallback($args = null)
    {
        return "USERS";
    }
    /**
     * {@inheritDoc}
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables (in this case none)
     */
    public function whereCallback($args = null)
    {
        return "USER_ID != '" . C\PUBLIC_USER_ID . "'";
    }
    /**
     * Get a list of admin activities that a user is allowed to perform.
     * This includes their name and their associated method.
     *
     * @param string $user_id  id of user to get activities fors
     */
    public function getUserActivities($user_id)
    {
        $db = $this->db;
        $activities = [];
        $status = $this->getUserStatus($user_id);
        if (!$status || in_array($status,
            [C\SUSPENDED_STATUS, C\INACTIVE_STATUS])) {
            return [];
        }
        $locale_tag = L\getLocaleTag();
        $limit_offset = $db->limitOffset(1);
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = ? $limit_offset";
        $result = $db->execute($sql, [$locale_tag]);
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT DISTINCT A.ACTIVITY_ID AS ACTIVITY_ID, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID, A.METHOD_NAME AS METHOD_NAME,".
            " T.IDENTIFIER_STRING AS IDENTIFIER_STRING FROM ACTIVITY A, ".
            " USER_ROLE UR, ROLE_ACTIVITY RA, TRANSLATION T ".
            "WHERE UR.USER_ID = ? ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND T.TRANSLATION_ID=A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID ORDER BY A.ACTIVITY_ID ASC";
        $result = $db->execute($sql, [$user_id]);
        $i = 0;
        $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
            "FROM TRANSLATION_LOCALE ".
            "WHERE TRANSLATION_ID=? AND LOCALE_ID=? $limit_offset";
            // maybe do left join at some point
        while ($activities[$i] = $this->db->fetchArray($result)) {
            $id = $activities[$i]['TRANSLATION_ID'];
            $result_sub =  $db->execute($sub_sql, [$id, $locale_id]);
            $translate = $db->fetchArray($result_sub);
            if ($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            }
            if (!isset($activities[$i]['ACTIVITY_NAME']) ||
                $activities[$i]['ACTIVITY_NAME'] == "") {
                $activities[$i]['ACTIVITY_NAME'] = $this->translateDb(
                    $activities[$i]['IDENTIFIER_STRING'], C\DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null
        return $activities;
    }
    /**
     * Checks if a user is allowed to perform the activity given by
     * method name
     *
     * @param string $user_id  id of user to check
     * @param string $method_name to see if user allowed to do
     * @param bool $get_roles whether to return a list of role ids
     *          granting that access
     * @return bool whether or not the user is allowed
     */
    public function isAllowedUserActivity($user_id, $method_name,
        $get_roles = false)
    {
        $db = $this->db;
        if($get_roles) {
            $select = "UR.ROLE_ID";
        } else {
            $select = "COUNT(*)";
        }
        $sql = "SELECT $select AS ALLOWED FROM ACTIVITY A, ".
            "USER_ROLE UR, ROLE_ACTIVITY RA WHERE UR.USER_ID = ? ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND A.METHOD_NAME = ? ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID";
        $result = $db->execute($sql, [$user_id, $method_name]);
        $role_ids = [];
        if ($result) {
            while($row = $db->fetchArray($result)) {
                if($get_roles) {
                    $role_ids[] = $row["ALLOWED"];
                } else if (isset($row["ALLOWED"]) && $row["ALLOWED"] > 0) {
                    return true;
                }
            }
        }
        if($role_ids == []) {
            return false;
        }
        return $role_ids;
    }
    /**
     * Returns $_SESSION variable of given user from the last time
     * logged in.
     *
     * @param int $user_id id of user to get session for
     * @return array user's session data
     */
    public function getUserSession($user_id)
    {
        $db = $this->db;
        $sql = "SELECT SESSION FROM USER_SESSION ".
            "WHERE USER_ID = ? " . $db->limitOffset(1);
        $result = $db->execute($sql, [$user_id]);
        $row = $db->fetchArray($result);
        if (isset($row["SESSION"])) {
            return unserialize($row["SESSION"]);
        }
        return null;
    }
    /**
     * Stores into DB the $session associative array of given user
     *
     * @param int $user_id id of user to store session for
     * @param array $session session data for the given user
     */
    public function setUserSession($user_id, $session)
    {
        $sql = "DELETE FROM USER_SESSION ".
            "WHERE USER_ID = ?";
        $this->db->execute($sql, [$user_id]);
        $session_string = serialize($session);
        $sql = "INSERT INTO USER_SESSION ".
            "VALUES (?, ?)";
        $this->db->execute($sql, [$user_id, $session_string]);
    }
    /**
     * Get a username by user_id
     *
     * @param string $user_id id of the user
     * @return string
     */
    public function getUsername($user_id)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, [$user_id]);
        $row = $db->fetchArray($result);
        return $row['USER_NAME'];
    }
    /**
     * Get the status of user by user_id
     *
     * @param string $user_id id of the user
     * @return array
     */
    public function getUserStatus($user_id)
    {
        $db = $this->db;
        $sql = "SELECT STATUS FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, [$user_id]);
        $row = $db->fetchArray($result);
        return $row['STATUS'];
    }
    /**
     * Returns a row from the USERS table based on a username (case-insensitive)
     *
     * @param string $username user login to be used for look up
     * @return array corresponds to the row of that user in the USERS table
     */
    public function getUser($username)
    {
        $db = $this->db;
        $sql = "SELECT * FROM USERS WHERE UPPER(USER_NAME) = UPPER(?) " .
            $db->limitOffset(1);
        $result = $db->execute($sql, [$username]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        if (isset($row['USER_ID'])) {
            $row['USER_ICON'] = $this->getUserIconUrl($row['USER_ID']);
        }
        return $row;
    }
    /**
     * Looks up a USERS row based on their $email (potentially not unique)
     * and the time at which their account was create in microseconds
     * @param string $email of user to lookup
     * @param string $creation_time when the user's account was created in
     *     the current epoch
     * @return array row from USERS table
     */
    public function getUserByEmailTime($email, $creation_time)
    {
        $db = $this->db;
        $sql = "SELECT * FROM USERS WHERE UPPER(EMAIL) = UPPER(?)
            AND CREATION_TIME=? " . $db->limitOffset(1);
        $result = $db->execute($sql, [$email, $creation_time]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        if (isset($row['USER_ID'])) {
            $row['USER_ICON'] = $this->getUserIconUrl($row['USER_ID']);
        }
        return $row;
    }
    /**
     * Returns the relative url needed to request the given users avatar icon
     *
     * @param int $user_id user to look up path for
     * @return string path to icon
     */
    public function getUserIconUrl($user_id)
    {
        $user_icon = C\BASE_URL . "resources/anonymous.png";
        $user_folder = L\crawlHash("user" . $user_id . C\AUTH_KEY);
        $user_prefix = substr($user_folder, 0, 3);
        if (file_exists(C\APP_DIR .
            "/resources/$user_prefix/$user_folder/user_icon.jpg")) {
            $user_icon = "./?c=resource&amp;a=get&amp;f=resources&amp;".
                "s=$user_folder&amp;n=user_icon.jpg";
        }
        return $user_icon;
    }
    /**
     * Returns the path to a user's resource folder (where uploaded files
     * will be stored). It creates the folder if it does not exist
     *
     * @param int $user_id user id of user to get path for
     */
    public function getUserIconFolder($user_id)
    {
        $user_folder = L\crawlHash("user" . $user_id . C\AUTH_KEY);
        $user_prefix = substr($user_folder, 0, 3);
        $resource_path = C\APP_DIR . "/resources";
        $prefix_path = $resource_path."/$user_prefix";
        $user_path = "$prefix_path/$user_folder";
        if (file_exists($user_path)) {
            return $user_path;
        }
        if (!file_exists(C\APP_DIR) && !mkdir(C\APP_DIR)) {
            return false;
        }
        if (!file_exists($resource_path) && !mkdir($resource_path)) {
            return false;
        }
        if (!file_exists($prefix_path) && !mkdir($prefix_path)) {
            return false;
        }
        if (mkdir($user_path)) {
            return $user_path;
        }
        return false;
    }
    /**
     * Set status of user by user_id
     *
     * @param string $user_id id of the user
     * @param int $status one of ACTIVE_STATUS, INACTIVE_STATUS, or
     *      SUSPENDED_STATUS
     */
    public function updateUserStatus($user_id, $status)
    {
        $db = $this->db;
        if (!in_array($status, [C\ACTIVE_STATUS, C\INACTIVE_STATUS,
            C\SUSPENDED_STATUS])) {
            return;
        }
        $sql = "UPDATE USERS SET STATUS=? WHERE USER_ID=?";
        $db->execute($sql, [$status, $user_id]);
    }
    /**
     *  Does the insert into Users table portion of the creation of a new
     *  user
     *
     * @param string $username  the username of the user to be added
     * @param string $password  the password of the user to be added
     * @param string $firstname the firstname of the user to be added
     * @param string $lastname the lastname of the user to be added
     * @param string $email the email of the user to be added
     * @param int $status one of ACTIVE_STATUS, INACTIVE_STATUS, or
     *      SUSPENDED_STATUS
     * @param string $zkp_password  the password parameters need to
     *  verify a Fiat-Shamir password
     */
    public function addUserToUsersTable($username, $password, $firstname='',
        $lastname='', $email='', $status = C\ACTIVE_STATUS, $zkp_password='')
    {
        $db = $this->db;
        $sql = "INSERT INTO USERS(FIRST_NAME, LAST_NAME,
            USER_NAME, EMAIL, PASSWORD, STATUS, HASH,
            CREATION_TIME, ZKP_PASSWORD) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,?)";
        $result = $db->execute($sql, [$firstname, $lastname,
            $username, $email, L\crawlCrypt($password), $status,
            L\crawlCrypt($username . C\AUTH_KEY . $creation_time),
            $creation_time, $zkp_password]);
    }
    /**
     * Add a user with a given username and password to the list of users
     * that can login to the admin panel
     *
     * @param string $username  the username of the user to be added
     * @param string $password  the password in plaintext
     *      of the user to be added, and ZKP auth not being used (else
     *      this can be the empty string)
     * @param string $firstname the firstname of the user to be added
     * @param string $lastname the lastname of the user to be added
     * @param string $email the email of the user to be added
     * @param int $status one of ACTIVE_STATUS, INACTIVE_STATUS, or
     *      SUSPENDED_STATUS
     * @param string $zkp_password  the password parameters needed to
     *      verify a Fiat-Shamir password
     * @return mixed false if operation not successful, user_id otherwise
     */
    public function addUser($username, $password, $firstname = '', $lastname='',
        $email = '', $status = C\ACTIVE_STATUS, $zkp_password = '')
    {
        $creation_time = L\microTimestamp();
        $db = $this->db;
        $sql = "INSERT INTO USERS(FIRST_NAME, LAST_NAME,
            USER_NAME, EMAIL, PASSWORD, STATUS, HASH,
            CREATION_TIME, ZKP_PASSWORD) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?,?)";
        $result = $db->execute($sql, [$firstname, $lastname,
            $username, $email, L\crawlCrypt($password), $status,
            L\crawlCrypt($username . C\AUTH_KEY . $creation_time),
            $creation_time, $zkp_password]);
        if (!$user_id = $this->getUserId($username)) {
            return false;
        }
        $now = time();
        $user_id = $db->escapeString($user_id);
        $sql = "INSERT INTO USER_GROUP (USER_ID, GROUP_ID, STATUS,
            JOIN_DATE) VALUES(?, ?, ?, ?)";
        $result = $db->execute($sql, [$user_id, C\PUBLIC_GROUP_ID,
            C\ACTIVE_STATUS, $now]);
        $sql = "INSERT INTO USER_ROLE  VALUES (?, ?) ";
        $result_id = $db->execute($sql, [$user_id, C\USER_ROLE]);
        return $user_id;
    }
    /**
     * Deletes a user by username from the list of users that can login to
     * the admin panel
     *
     * @param string $user_name  the login name of the user to delete
     */
    public function deleteUser($user_name)
    {
        $db = $this->db;
        $user_id = $this->getUserId($user_name);
        if ($user_id) {
            $sql = "DELETE FROM USER_ROLE WHERE USER_ID=?";
            $result = $db->execute($sql, [$user_id]);
            $sql = "DELETE FROM USER_GROUP WHERE USER_ID=?";
            $result = $db->execute($sql, [$user_id]);
            $sql = "DELETE FROM USER_SESSION WHERE USER_ID=?";
            $result = $db->execute($sql, [$user_id]);
        }
        $sql = "DELETE FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, [$user_id]);
    }
    /**
     * Used to update the fields stored in a USERS row according to
     * an array holding new values
     *
     * @param array $user updated values for a USERS row
     */
    public function updateUser($user)
    {
        $user_id = $user['USER_ID'];
        if (isset($user['IMAGE_STRING'])) {
            $folder = $this->getUserIconFolder($user_id);
            $image = @imagecreatefromstring($user['IMAGE_STRING']);
            $thumb_string = ImageProcessor::createThumb($image);
            file_put_contents($folder."/user_icon.jpg",
                $thumb_string);
            clearstatcache($folder."/user_icon.jpg");
        }
        unset($user['USER_ID']);
        unset($user['USER_NAME']);
        unset($user['IMAGE_STRING']);
        unset($user['USER_ICON']);
        $sql = "UPDATE USERS SET ";
        $comma ="";
        $params = [];
        if ($user == []) {
            return;
        }
        foreach ($user as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            if ($field == "PASSWORD") {
                $params[] = L\crawlCrypt($value);
            } else {
                $params[] = $value;
            }
        }
        $sql .= " WHERE USER_ID=?";
        $params[] = $user_id;
        $this->db->execute($sql, $params);
    }
}
