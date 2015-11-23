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
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;

/**
 * This is class is used to handle
 * db results related to Machine Administration
 *
 * @author Chris Pollett
 */
class MachineModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * @var array
     */
    public $search_table_column_map =  ["name" => "NAME"];
    /**
     * Called after getRows has retrieved all the rows that it would retrieve
     * but before they are returned to give one last place where they could
     * be further manipulated. This callback
     * is used to make parallel network calls to get the status of each machine
     * returned by getRows. The default for this method is to leave the
     * rows that would be returned unchanged
     *
     * @param array $rows that have been calculated so far by getRows
     * @return array $rows after this final manipulation
     *
     */
    public function postQueryCallback($rows)
    {
        return $this->getMachineStatuses($rows);
    }
    /**
     * Returns urls for all the queue_servers stored in the DB
     *
     * @param string $crawl_time of a crawl to see the machines used in
     *     that crawl
     * @return array machine names
     */
    public function getQueueServerUrls($crawl_time = 0)
    {
        static $machines = [];
        $db = $this->db;
        if (isset($machines[$crawl_time])) {
            return $machines[$crawl_time];
        }
        $network_crawl_file = C\CRAWL_DIR."/cache/".self::network_base_name.
                    $crawl_time.".txt";
        if ($crawl_time != 0 && file_exists($network_crawl_file)) {
            $info = unserialize(file_get_contents($network_crawl_file));
            if (isset($info["MACHINE_URLS"])) {
                $machines[$crawl_time] = $info["MACHINE_URLS"];
                return $info["MACHINE_URLS"];
            }
        }
        $sql = "SELECT URL FROM MACHINE WHERE HAS_QUEUE_SERVER > 0 ".
            "ORDER BY NAME DESC";
        $result = $db->execute($sql);
        $i = 0;
        $machines[$crawl_time] =[];
        while ($row = $db->fetchArray($result)) {
            $machines[$crawl_time][$i] = $row["URL"];
            $i++;
        }
        unset($machines[$crawl_time][$i]); //last one will be null
        return $machines[$crawl_time];
    }
    /**
     * Check if there is a machine with $column equal to value
     *
     * @param string $field to use to look up machine (either name or url)
     * @param string $value for that field
     * @return bool whether or not has machine
     */
    public function checkMachineExists($field, $value)
    {
        $db = $this->db;
        $params = [$value];
        $sql = "SELECT COUNT(*) AS NUM FROM MACHINE WHERE
            $field=? ";
        $result = $db->execute($sql, $params);
        if (!$row = $db->fetchArray($result)) {
            return false;
        }
        if ($row['NUM'] <= 0) {
            return false;
        }
        return true;
    }
    /**
     * Add a machine to the database using provided string
     *
     * @param string $name  the name of the machine to be added
     * @param string $url the url of this machine
     * @param boolean $has_queue_server - whether this machine is running a
     *     queue_server
     * @param int $num_fetchers - how many managed fetchers are on this
     *     machine.
     * @param string $parent - if this machine replicates some other machine
     *     then the name of the parent
     */
    public function addMachine($name, $url, $has_queue_server, $num_fetchers,
        $parent = "")
    {
        $db = $this->db;
        $has_string = ($has_queue_server) ? $has_string = "1" :
            $has_string = "0";
        $sql = "INSERT INTO MACHINE VALUES (?, ?, ?, ?, ?)";
        $this->db->execute($sql, [$name, $url, $has_string, $num_fetchers,
            $parent]);
    }
    /**
     * Delete a machine by its name
     *
     * @param string $machine_name the name of the machine to delete
     */
    public function deleteMachine($machine_name)
    {
        $sql = "DELETE FROM MACHINE WHERE NAME=?";
        $this->db->execute($sql, [$machine_name]);
    }
    /**
     *  Returns all the machine names stored in the DB
     *
     *  @return array machine names
     */
    public function getMachineList()
    {
        $machines = [];
        $sql = "SELECT * FROM MACHINE ORDER BY NAME DESC";
        $result = $this->db->execute($sql);
        $i = 0;
        while ($machines[$i] = $this->db->fetchArray($result)) {
            $i++;
        }
        unset($machines[$i]); //last one will be null
        return $machines;
    }
    /**
     * Returns the statuses of machines in the machine table of their
     * fetchers and queue_server as well as the name and url's of these machines
     *
     * @param array $machines an array of machines to check the status for
     * @return array  a list of machines, together with all their properties
     * and the statuses of their fetchers and queue_servers
     */
    public function getMachineStatuses($machines = [])
    {
        $num_machines = count($machines);
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        for ($i = 0; $i < $num_machines; $i++) {
            $hash_url = L\crawlHash($machines[$i]["URL"]);
            $machines[$i][CrawlConstants::URL] =
                $machines[$i]["URL"] ."?c=machine&a=statuses&time=$time".
                "&session=$session&arg=$hash_url";
        }
        $statuses = FetchUrl::getPages($machines);
        for ($i = 0; $i < $num_machines; $i++) {
            foreach ($statuses as $status) {
                if ($machines[$i][CrawlConstants::URL] ==
                    $status[CrawlConstants::URL]) {
                    $pre_status =
                        json_decode($status[CrawlConstants::PAGE], true);
                    if (is_array($pre_status)) {
                        $machines[$i]["STATUSES"] = $pre_status;
                    } else {
                        $machines[$i]["STATUSES"] = "NOT_CONFIGURED_ERROR";
                    }
                }
            }
        }
        $sql = "SELECT * FROM ACTIVE_PROCESS";
        $result = $this->db->execute($sql);
        if (!$result) {
            return $machines;
        }
        $active_fetchers = [];
        $name_server_updater_on = false;
        while ($row = $this->db->fetchArray($result)) {
            for ($i = 0; $i < $num_machines; $i++) {
                if ($machines[$i]['NAME'] == $row['NAME']) {
                    if (isset($row['ID']) &&
                        isset($machines[$i]["STATUSES"][$row['TYPE']]) &&
                        !isset($machines[$i]["STATUSES"][$row['TYPE']][
                        $row['ID']])) {
                        $machines[$i]["STATUSES"][$row['TYPE']][
                            $row['ID']] = 0;
                    }
                    if ($machines[$i]['URL'] == C\NAME_SERVER && $row['TYPE'] ==
                        "MediaUpdater") {
                        $name_server_updater_on = true;
                    }
                }
                if ($row['NAME'] == "NAME_SERVER" && $row['TYPE'] ==
                    "MediaUpdater" && $row["ID"] == 0) {
                    $name_server_updater_on = true;
                }
            }
        }
        L\stringROrderCallback("", "", "NAME");
        if ($machines != []) {
            usort($machines, C\NS_LIB . "stringROrderCallback");
        }
        $name_server_statuses = CrawlDaemon::statuses();
        $machines['NAME_SERVER']['MEDIA_UPDATER_TURNED_ON'] =
            $name_server_updater_on;
        $machines['NAME_SERVER']['MediaUpdater'] = 0;
        if (isset($name_server_statuses['MediaUpdater'])) {
            $machines['NAME_SERVER']['MediaUpdater'] = 1;
            if (isset($name_server_statuses['MediaUpdater'][-1]) &&
                $name_server_statuses['MediaUpdater'][-1]) {
                $machines['NAME_SERVER']['MEDIA_UPDATER_TURNED_ON'] = 1;
            }
        }
        return $machines;
    }
    /**
     * Get either a fetcher or queue_server log for a machine
     *
     * @param string $machine_name the name of the machine to get the log file
     *      for
     * @param int $id  if a fetcher, which instance on the machine
     * @param string $type one of queue_server, fetcher, mirror,
     *      or MediaUpdater
     * @param string $filter only lines out of log containing this string
     *      returned
     * @return string containing the last MachineController::LOG_LISTING_LEN
     *     bytes of the log record
     */
    public function getLog($machine_name, $id, $type, $filter="")
    {
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $name_server = ($machine_name == "NAME_SERVER");
        if ($name_server) {
            $row = [];
            $row["URL"] = C\NAME_SERVER;
        } else {
            $sql = "SELECT URL FROM MACHINE WHERE NAME='$machine_name'";
            $result = $this->db->execute($sql);
            $row = $this->db->fetchArray($result);
        }
        if ($row) {
            $url = $row["URL"]. "?c=machine&a=log&time=$time".
                "&session=$session&f=$filter&type=$type&id=$id";
            $log_page = FetchUrl::getPage($url);
            if (defined("ENT_SUBSTITUTE")) {
                $log_data = htmlentities(urldecode(json_decode($log_page)),
                    ENT_SUBSTITUTE);
            } else {
                $log_data = htmlentities(urldecode(json_decode($log_page)));
            }
        } else {
            $log_data = "";
        }
        return $log_data;
    }
    /**
     * Used to start or stop a queue_server, fetcher, mirror instance on
     * a machine managed by the current one
     *
     * @param string $machine_name name of machine
     * @param string $action "start" or "stop"
     * @param int $id
     * @param string $type
     */
    public function update($machine_name, $action, $id, $type)
    {
        $db = $this->db;
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        if ($machine_name == "NAME_SERVER") {
            $row = ["URL" => C\NAME_SERVER, "PARENT" => ""];
        } else {
            $sql = "SELECT URL, PARENT FROM MACHINE WHERE NAME=?";
            $result = $db->execute($sql, [$machine_name]);
            $row = $db->fetchArray($result);
        }
        if ($row) {
            $url = $row["URL"]. "?c=machine&a=update&time=$time".
                "&session=$session&action=$action&id=$id&type=$type";
            $sql = "DELETE FROM ACTIVE_PROCESS WHERE NAME=? AND
                ID=? AND TYPE=?";
            $db->execute($sql, [$machine_name, $id, $type]);
            if ($action == "start") {
                $sql = "INSERT INTO ACTIVE_PROCESS VALUES (?, ?, ?)";
            }
            $db->execute($sql, [$machine_name, $id, $type]);
            if ($type == "mirror") {
                if ($row["PARENT"]) {
                    $sql = "SELECT URL FROM MACHINE WHERE NAME='".
                        $row["PARENT"] ."'";
                    $result = $this->db->execute($sql);
                    if ($result &&
                        $parent_row = $this->db->fetchArray($result)) {
                        $url .= "&parent=" . webencode($parent_row["URL"]);
                    }
                }
            }
            echo FetchUrl::getPage($url);
        }
    }
    /**
     * Used to restart any fetchers which the user turned on, but which
     * happened to have crashed. (Crashes are usually caused by CURL or
     * memory issues)
     */
    public function restartCrashedFetchers()
    {
        $machine_list = $this->getMachineList();
        $machines = $this->getMachineStatuses($machine_list);
        foreach ($machines as $machine) {
            if (isset($machine["STATUSES"]["Fetcher"])) {
                $fetchers = $machine["STATUSES"]["Fetcher"];
                foreach ($fetchers as $id => $status) {
                    if ($status === 0) {
                        $this->update($machine["NAME"], "start", $id,
                            "Fetcher");
                    }
                }
            }
        }
    }
}
