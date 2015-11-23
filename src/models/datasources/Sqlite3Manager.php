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
namespace seekquarry\yioop\models\datasources;

use seekquarry\yioop\configs as C;

/**
 * SQLite3 DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager
 * for the Sqlite3 DBMS (file format not compatible with versions less than 3).
 * Method explanations are from the parent class.
 *
 * @author Chris Pollett
 */
class Sqlite3Manager extends PdoManager
{
    /** {@inheritDoc} */
    public function __construct()
    {
        parent::__construct();
        if (!file_exists(C\CRAWL_DIR."/data")) {
            mkdir(C\CRAWL_DIR."/data");
            chmod(C\CRAWL_DIR."/data", 0777);
        }
        if (class_exists("\PDO") &&
            in_array("sqlite", \PDO::getAvailableDrivers())) {
            $this->pdo_flag = true;
        } else {
            echo "PDO sqlite needs to be installed!";
            $this->pdo_flag = false;
        }
        $this->db_name = null;
    }
    /**
     * Select file name of database. If the
     * @param string $db_host  not used but in base constructor
     * @param string $db_user  not used but in base constructor
     * @param string $db_password  not used but in base constructor
     * @param string $db_name filename of sqlite database. If the name
     *     does not contain any "/" symbols assume it is in the
     *     crawl directory data folder and we don't have a file extension;
     *     otherwise assume the name is a complete filepath
     */
    public function connect($db_host = C\DB_HOST, $db_user = C\DB_USER,
        $db_password = C\DB_PASSWORD, $db_name = C\DB_NAME)
    {
        if (!stristr($db_name, "/")) {
            $this->pdo = new \PDO("sqlite:".
                C\CRAWL_DIR . "/data/$db_name.db");
        } else {
            $this->pdo = new \PDO("sqlite:$db_name");
        }
        return $this->pdo;
    }
}
