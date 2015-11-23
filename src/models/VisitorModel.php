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

/**
 * Used to keep track of ip address of failed account creation and login
 * attempts
 *
 * @author Chris Pollett
 */
class VisitorModel extends Model
{
    /**
     * Looks up an ip address to get the last time it was seen and the
     * duration of the last time out period. If the last time it was seen
     * was more than the forget age then it is removed from the visitor
     * table and treated as in never seen (i.e., this function returns
     * false if never seen before)
     *
     * @param string $ip_address the ipv4 or ipv6 address as a string
     * @param string $page_name name of timeout page that we are checking for
     * @return array associative array containing ADDRESS, the ip address;
     *     PAGE_NAME, the name of the static page to show if within the
     *     timeout period, END_TIME, time in seconds of current epoch until
     *     timeout period is over; DELAY, the current length of a timeout
     *     in seconds that a failed account creation or recovery should incur;
     *     FORGET_AGE, how long without a visit by this ip until the address
     *     should be treated as never seen before.
     */
    public function getVisitor($ip_address, $page_name = 'captcha_time_out')
    {
        $db = $this->db;
        $sql = "SELECT * FROM VISITOR WHERE ADDRESS=:address
            AND PAGE_NAME=:page_name " . $db->limitOffset(1);
        $result = $this->db->execute($sql, [":address" => $ip_address,
            ":page_name" => $page_name]);
        if (!$result || !$row = $this->db->fetchArray($result)) {
            return false;
        }
        $now = time();
        if ($row['FORGET_AGE'] > 0 &&
            ($now - $row["END_TIME"]) > $row['FORGET_AGE']) {
            $this->removeVisitor($ip_address, $page_name);
            return false;
        }
        return $row;
    }
    /**
     * Deletes an ip address from the VISITOR table
     *
     * @param string $ip_address the ipv4 or ipv6 address as a string
     * @param string $page_name
     */
    public function removeVisitor($ip_address, $page_name = 'captcha_time_out')
    {
        $sql = "DELETE FROM VISITOR WHERE ADDRESS = ? AND PAGE_NAME = ?";
        $this->db->execute($sql, [$ip_address, $page_name]);
    }
    /**
     * This creates or updates a visitor table entry for an ip address.
     * These entries are used to keep track of which ip should be made to
     * see a timeout static page because of failing to input captcha or
     * recovery info correctly.
     *
     * @param string $ip_address ipv4 or ipv6 address to insert or update.
     * @param string $page_name name of page (served by
     *     StaticController) to display if ip is in a timeout period
     * @param int $start_delay only is used if ip address does not
     *     already have an entry in the VISITOR table in which case it
     *     is the initial timeout period a user must wait if the there is
     *     a captcha or receovery info error
     * @param int $forget_age how long without a visit by this ip until the
     *     address should be treated as never seen before
     * @param int $count_till_double how many accesses before start
     *     double the delay
     */
    public function updateVisitor($ip_address, $page_name, $start_delay = 1,
        $forget_age = C\ONE_WEEK, $count_till_double = 1)
    {
        $visitor = $this->getVisitor($ip_address, $page_name);
        if (!$visitor) {
            $end_time = time();
            $sql = "INSERT INTO VISITOR VALUES (?, ?, ?, ?, ?, '1')";
            $this->db->execute($sql, [$ip_address, $page_name,
                $end_time, $start_delay, $forget_age]);
            return;
        }
        $access_count = $visitor['ACCESS_COUNT'];
        if ($access_count >= $count_till_double) {
            $delay = 2 * $visitor['DELAY'];
            $end_time = time() + $delay;
        } else {
            $access_count++;
            $delay = $visitor['DELAY'];
            $end_time = time();
        }
        $sql = "UPDATE VISITOR SET DELAY=:delay, END_TIME=:end_time,
            FORGET_AGE=:forget_age, ACCESS_COUNT=:account_count
            WHERE ADDRESS=:ip_address AND PAGE_NAME=:page_name";
        $this->db->execute($sql, [
            ":delay"=>$delay, ":end_time" => $end_time,
            ":forget_age" => $forget_age, ":account_count" => $access_count,
            ":ip_address" => $ip_address, ":page_name" => $page_name]);
    }
}
