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
use seekquarry\yioop\library as L;

/**
 * Mysql DatasourceManager
 *
 * This is concrete class, implementing
 * the abstract class DatasourceManager
 * for the MySql DBMS. Method explanations
 * are from the parent class. Originally,
 * it was implemented using php mysql_ interface.
 * In July, 2013, it was rewritten to use
 * mysqli_ interface as the former interface was
 * deprecated. This was a minimal rewrite and
 * does not yet use the more advanced features
 * of mysqli_
 *
 * @author Chris Pollett
 */
class MysqlManager extends PdoManager
{
    /** Used when to quote column names of db names that contain a
     * a keyword or special character
     * @var string
     */
    public $special_quote = "`";
    /**
     * {@inheritDoc}
     *
     * @param string $db_host the hostname of where the database is located
     *     (not used in all dbms's)
     * @param string $db_user the user to connect as
     * @param string $db_password the password of the user to connect as
     * @param string $db_name the name of the database on host we are
     * connecting to
     * @return mixed return false if not successful and some kind of
     *     connection object/identifier otherwise
     */
    public function connect($db_host = C\DB_HOST, $db_user = C\DB_USER,
        $db_password = C\DB_PASSWORD, $db_name = C\DB_NAME)
    {
        $host_parts = explode(":", $db_host);
        $db_port_string = "";
        if (isset($host_parts[1])) {
            $db_host = $host_parts[0];
            $db_port_string = ";port=".$host_parts[1];
        }
        $db_name_string = "";
        if ($db_name != "") {
            $db_name_string = ";dbname=".$db_name;
        }
        try {
            $this->pdo = new \PDO("mysql:host={$db_host}".
                $db_port_string.$db_name_string,
                $db_user, $db_password);
        } catch (\PDOException $e) {
            $this->pdo = false;
            L\crawlLog('Connection failed: ' . $e->getMessage());
        }
        return $this->pdo;
    }
}
