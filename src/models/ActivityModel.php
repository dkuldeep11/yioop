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

/**
 * This is class is used to handle
 * db results related to Administration Activities
 *
 * @author Chris Pollett
 */
class ActivityModel extends Model
{
    /**
     * Given the method name of a method to perform an activity return the
     * translated activity name
     *
     * @param string $method_name  string with the name of the activity method
     * @return string  the translated name of the activity
     */
    public function getActivityNameFromMethodName($method_name)
    {
        $db = $this->db;
        $method_name = $db->escapeString($method_name);
        $roles = [];
        $locale_tag = L\getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = '$locale_tag' " . $db->limitOffset(1);
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT TL.TRANSLATION AS ACTIVITY_NAME  FROM ".
            " TRANSLATION_LOCALE TL, LOCALE L, ACTIVITY A ".
            "WHERE A.METHOD_NAME = :method_name ".
            "AND TL.TRANSLATION_ID = A.TRANSLATION_ID ".
            "AND L.LOCALE_ID=:locale_id AND ".
            "L.LOCALE_ID = TL.LOCALE_ID " . $db->limitOffset(1);
        $result = $db->execute($sql, [":method_name" => $method_name,
            ":locale_id" => $locale_id]);
        $row = $db->fetchArray($result);
        $activity_name = false;
        if (isset($row['ACTIVITY_NAME'])) {
            $activity_name = $row['ACTIVITY_NAME'];
        }
        if (!$activity_name) {
            $sql = "SELECT T.IDENTIFIER_STRING AS STRING_ID FROM ".
                " ACTIVITY A, TRANSLATION T ".
                "WHERE A.METHOD_NAME = :method_name ".
                " AND T.TRANSLATION_ID = A.TRANSLATION_ID " .
                $db->limitOffset(1);
            $result = $db->execute($sql, [":method_name" => $method_name]);
            $row = $db->fetchArray($result);
            $activity_name = $this->translateDb($row['STRING_ID'],
                C\DEFAULT_LOCALE);
        }
        return $activity_name;
    }
    /**
     * Returns the activity id of the the activity with the given
     * method name
     *
     * @param string $method_name activity to look
     * @return int $id of the given activity
     */
    public function getActivityIdFromMethodName($method_name)
    {
        $sql = "SELECT ACTIVITY_ID FROM ACTIVITY  WHERE METHOD_NAME = ?";
        $result = $this->db->execute($sql, [$method_name]);
        if ($result) {
            $row = $this->db->fetchArray($result);
            if (isset($row['ACTIVITY_ID'])) {
                return $row['ACTIVITY_ID'];
            }
        }
        return false;
    }
    /**
     * Gets a list of activity ids, method names, and translated
     * name of each available activity
     *
     * @return array activities
     */
    public function getActivityList()
    {
        $db = $this->db;
        $activities = [];
        $locale_tag = L\getLocaleTag();
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = :locale_tag " . $db->limitOffset(1);
        $result = $db->execute($sql, [":locale_tag" => $locale_tag]);
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];
        $sql = "SELECT A.ACTIVITY_ID AS ACTIVITY_ID, ".
            "A.METHOD_NAME AS METHOD_NAME, ".
            " T.IDENTIFIER_STRING AS IDENTIFIER_STRING, ".
            " T.TRANSLATION_ID AS TRANSLATION_ID FROM ".
            " ACTIVITY A, TRANSLATION T WHERE  ".
            " T.TRANSLATION_ID = A.TRANSLATION_ID";
        $result = $db->execute($sql);
        $i = 0;
        $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
            "FROM TRANSLATION_LOCALE ".
            " WHERE TRANSLATION_ID=:id AND LOCALE_ID=:locale_id " .
            $db->limitOffset(1);
            // maybe do left join at some point
        while ($activities[$i] = $db->fetchArray($result)) {
            $id = $activities[$i]['TRANSLATION_ID'];
            $result_sub =  $db->execute($sub_sql,
                [":id" => $id, ":locale_id" => $locale_id]);
            $translate = $db->fetchArray($result_sub);

            if ($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            } else {
                $activities[$i]['ACTIVITY_NAME'] = $this->translateDb(
                    $activities[$i]['IDENTIFIER_STRING'], C\DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null
        return $activities;
    }
}
