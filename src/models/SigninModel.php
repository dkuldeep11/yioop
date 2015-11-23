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
 * @author Chris Pollett chris@pollett.orgs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\library as L;

/**
 * This is class is used to handle
 * db results needed for a user to login
 *
 * @author Chris Pollett
 */
class SigninModel extends Model
{
    /**
     * Checks that a username password pair is valid
     *
     * @param string $username the username to check
     * @param string $password the password to check
     * @return bool  where the password is that of the given user
     *     (or at least hashes to the same thing)
     */
    public function checkValidSignin($username, $password)
    {
        $db = $this->db;
        $result = $this->getUserDetails($username);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return ($username == $row['USER_NAME'] &&
            L\crawlCrypt($password, $row['PASSWORD']) == $row['PASSWORD']) ;
    }
    /**
     * Get user details from database
     *
     * @param string $username username
     * @return array $result array of user data
     */
    public function getUserDetails($username)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME, PASSWORD,ZKP_PASSWORD FROM USERS ".
            "WHERE USER_NAME = ? " . $db->limitOffset(1);
        $i = 0;
        do {
            if ($i > 0) {
                sleep(3);
            }
            $result = $db->execute($sql, [$username]);
            $i++;
        } while (!$result && $i < 2);
        return $result;
    }
    /**
     * Checks one round of username and password in case of ZKP authentication
     * via the Fiat Shamir protocol. Let S be the user's password (not stored
     * on server, secret, and co-prime to some public $n = pq).
     * V = S^2 mod $n is stored on the server and is assumed to be publicly
     * known.
     * Idea is that previously
     * (1) client sent server an x = r^2 mod $n
     * (2) the server sent a random e in {0, 1} to client
     * Now the server has just received y = r S^e mod $n.
     * and verifies y^2 = r^2(S^2)^e = xV^e mod $n.
     *
     * @param string $username which login to verify
     * @param string $x as described above in the comment
     * @param string $y as described above in the comment
     * @param string $e random exponent to use
     * @param string $n modulus to use for Fiat Shamir
     * @return bool
     */
    public function checkValidSigninForZKP($username, $x, $y, $e, $n)
    {
        $db = $this->db;
        $result = $this->getUserDetails($username);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        $v = $row['ZKP_PASSWORD'];
        $rp = bcmod(bcmul($x, bcmod(bcpow($v, $e), $n)), $n);
        $lp = bcmod(bcmul($y, $y), $n);
        return ($username == $row['USER_NAME'] && bccomp($rp, $lp) == 0);
    }
    /**
     * Checks that a username email pair is valid
     *
     * @param string $username the username to check
     * @param string $email the email to check
     * @return bool  where the email is that of the given user
     *     (or at least hashes to the same thing)
     */
    public function checkValidEmail($username, $email)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME, EMAIL FROM USERS ".
            "WHERE USER_NAME = ? " . $db->limitOffset(1);

        $result = $db->execute($sql, [$username]);
        if (!$result) {
            return false;
        }
        $row = $db->fetchArray($result);

        return ($username == $row['USER_NAME'] && $email == $row['EMAIL']) ;
    }
    /**
     * Get the user_name associated with a given userid
     *
     * @param string $user_id the userid to look up
     * @return string the corresponding username
     */
   public function getUserName($user_id)
   {
       $db = $this->db;
       $sql = "SELECT USER_NAME FROM USERS WHERE USER_ID = ? " .
            $db->limitOffset(1);
       $result = $db->execute($sql, [$user_id]);
       $row = $db->fetchArray($result);
       $username = $row['USER_NAME'];
       return $username;
   }
     /**
     * Get the email associated with a given user_id
     *
     * @param string $user_id the userid to look up
     * @return string the corresponding email
     */
   public function getEmail($user_id)
   {
       $db = $this->db;
       $sql = "SELECT EMAIL FROM USERS WHERE
            USER_ID = ?  " . $db->limitOffset(1);
       $result = $db->execute($sql, [$user_id]);
       $row = $db->fetchArray($result);
       $email = $row['EMAIL'];
       return $email;
   }
    /**
     * Changes the email of a given user
     *
     * @param string $username username of user to change email of
     * @param string $email new email for user
     * @return bool update successful or not.
     */

    public function changeEmail($username, $email)
    {
        $sql = "UPDATE USERS SET EMAIL= ? WHERE USER_NAME = ? ";
        $result = $this->db->execute($sql, [$email, $username]);
        return $result != false;
    }
    /**
     * Changes the password of a given user
     *
     * @param string $username username of user to change password of
     * @param string $password new password for user
     * @return bool update successful or not.
     */
    public function changePassword($username, $password)
    {
        $sql = "UPDATE USERS SET PASSWORD=? WHERE USER_NAME = ? ";
        $result = $this->db->execute($sql,
            [L\crawlCrypt($password), $username]);
        return $result != false;
    }
    /**
     * Changes the password of a given user in case of ZKP authentication
     *
     * @param string $username username of user to change password of
     * @param string $password new password for user
     * @return bool update successful or not.
     */
    public function changePasswordZKP($username, $password)
    {
        $sql = "UPDATE USERS SET ZKP_PASSWORD=? WHERE USER_NAME = ? ";
        $result = $this->db->execute($sql, [$password, $username]);
        return $result != false;
    }
}
