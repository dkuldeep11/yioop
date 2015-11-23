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
 * @author Chris Pollett
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;

/**
 * This class is used to manage Advertising Credits
 * a user may purchase or spend
 */
class CreditModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * In this case, things will in general map to the ADVERTISEMENTS table
     * in the Yioop data base
     * var array
     */
    public $search_table_column_map = ["timestamp"=>"TIMESTAMP"];
    
    /**
     * Controls which tables and the names of tables
     * underlie the given model and should be used in a getRows call
     * This defaults to the single table whose name is whatever is before
     * Model in the name of the model. For example, by default on FooModel
     * this method would return "FOO". If a different behavior, this can be
     * overriden in subclasses of Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine these tables
     * @return string a comma separated list of tables suitable for a SQL
     *     query
     */
    public function fromCallback($args = null)
    {
        return "CREDIT_LEDGER";
    }
    /**
     * Controls the WHERE clause of the SQL query that
     * underlies the given model and should be used in a getRows call.
     * This defaults to an empty WHERE clause.
     *
     * @param mixed $args additional arguments that might be used to construct
     *     the WHERE clause.
     * @return string a SQL WHERE clause
     */
    public function whereCallback($args = null)
    {
        return "USER_ID = '".$args['USER_ID']."'";
    }
    /**
     * Gets the ad credit balance for the supplied user id
     *
     * @param int $user_id id of user to look up balance for
     * @return int current number of ad credits user has
     */
    public function getCreditBalance($user_id)
    {
        $db = $this->db;
        $sql = "SELECT BALANCE FROM CREDIT_LEDGER WHERE USER_ID=?
            ORDER BY TIMESTAMP DESC " . $db->limitOffset(1);
        $result = $db->execute($sql, [$user_id]);
        if($result && $row = $db->fetchArray($result)) {
            return $row['BALANCE'];
        } else {
            $time = time();
            $init_sql = "INSERT INTO CREDIT_LEDGER VALUES (?, 0,
                'advertisement_model_init_ledger', 0, $time)";
            $db->execute($init_sql, [$user_id]);
        }
        return 0;
    }
    /**
     * Credits of debits the ad credit balance of a user by a given amount
     * for the reason provided
     *
     * @param int $user_id  id of user to add credit or debit to credit balance
     * @param int $amount credit (positive) or debit (negative) to add to
     *      current balance ad credit balance
     * @param string $type explanation of change
     */
    public function updateCredits($user_id, $amount, $type)
    {
        $db = $this->db;
        $balance = $this->getCreditBalance($user_id);
        $time = time();
        $ledger_sql = "INSERT INTO CREDIT_LEDGER VALUES (?, ?, ?, ?, ?)";
        $db->execute($ledger_sql, [$user_id, $amount, $type,
            $balance + $amount, $time]);
    }
}
