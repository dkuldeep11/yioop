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
 * @author Pushkar Umaranikar
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\models;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\PhraseParser;

/**
 * This class is used to handle
 * database statements related to Advertisements
 *
 * @author Pushkar Umaranikar
 */
class AdvertisementModel extends Model
{
    /**
     * Associations of the form
     *     name of field for web forms => database column names/abbreviations
     * In this case, things will in general map to the ADVERTISEMENTS table
     * in the Yioop data base
     * var array
     */
    public $search_table_column_map = ["name"=>"NAME",
        "description" => "DESCRIPTION", "destination" => "DESTINATION",
        "keywords"=>"KEYWORDS","status"=>"STATUS",
        "budget" => "BUDGET", 'start_date' => 'START_DATE',
        'end_date' => 'END_DATE'];
    /**
     * These fields if present in $search_array (used by @see getRows() ),
     * but with value "-1", will be skipped as part of the where clause
     * but will be used for order by clause
     * @var array
     */
    public $any_fields = ["status"];
    /**
     * Controls which columns and the names of those columns from the tables
     * underlying the given model should be return from a getRows call.
     * This defaults to *, but in general will be overriden in subclasses of
     * Model
     *
     * @param mixed $args any additional arguments which should be used to
     *     determine the columns
     * @return string a comma separated list of columns suitable for a SQL
     *     query
     */
    public function selectCallback($args = null)
    {
        if($args['ADMIN']) {
            return "A.*, U.USER_NAME";
        }
        return "*";
    }
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
        if($args['ADMIN']) {
            return "ADVERTISEMENT A, USERS U";
        }
        return "ADVERTISEMENT";
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
        if($args['ADMIN']) {
            return "A.USER_ID = U.USER_ID";
        }
        return "USER_ID = '".$args['USER_ID']."'";
    }
    /**
     * Adds newly created advertisement to database
     *
     * @param array $ad advertisement to be added
     * @param array $keyword_min_prices (keyword, date) => min_bid_price
     *      array for keywords and dates in question
     * @param float $min_bid smallest bid required to place an ad for all
     *      the given keywords and dates provided. This is used to
     *      determine how an overbid will be distributed among the dates
     * @param string $user_id user id of user who created advertisement
     */
    public function addAdvertisement($ad, $keyword_min_prices, $min_bid,
        $user_id)
    {
        $db = $this->db;
        $sql = "INSERT INTO ADVERTISEMENT (USER_ID, NAME, DESCRIPTION,
            DESTINATION, KEYWORDS, BUDGET, STATUS, IMPRESSIONS,
            CLICKS, START_DATE, END_DATE) VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = $db->execute($sql, [$user_id,
            $ad['NAME'], $ad['DESCRIPTION'], $ad['DESTINATION'],
            mb_strtoupper($ad['KEYWORDS']), $ad['BUDGET'], 
            C\ADVERTISEMENT_ACTIVE_STATUS, 0, 0,
            $ad['START_DATE'], $ad['END_DATE']]);
        if(!$result) {return; }
        $ad_id = $db->insertID('ADVERTISEMENT');
        $ad_date_diff = strtotime($ad['END_DATE']) -
            strtotime($ad['START_DATE']) + C\ONE_DAY;
        $ad_day_count = $ad_date_diff/C\ONE_DAY;
        $keywords = array_keys($keyword_min_prices);
        $overbid_ratio = $ad['BUDGET']/$min_bid;
        $left_over = 0;
        foreach ($keywords as $keyword) {
            $date = $ad['START_DATE'];
            for ($j = 0 ; $j < $ad_day_count ; $j++ ) {
                $rational_day_bid_amt = $overbid_ratio *
                    $keyword_min_prices[$keyword][$date];
                $day_bid_amt = floor($rational_day_bid_amt);
                $left_over += $rational_day_bid_amt - $day_bid_amt;
                // left over code used to handle rounding
                if ($left_over >= 1) {
                    $day_bid_amt++;
                    $left_over--;
                }
                $this->addBid($ad_id, $keyword, $day_bid_amt, $date);
                $old_date = $date;
                $date = date(C\AD_DATE_FORMAT, strtotime($date .' +1 day'));
            }
        }
        if ($left_over > 0) {
            $this->addBid($ad_id, $keyword, 1, $old_date);
        }
    }
    /**
     * Update an existing advertisement in the database
     *
     * @param object $advertisement_advertisement to be updated
     * @param string $id an advertisement id
     */
    public function updateAdvertisement($advertisement, $id)
    {
        $sql = "UPDATE ADVERTISEMENT SET ";
        $comma = "";
        $params = [];
        if ($advertisement == []) {
            return;
        }
        foreach ($advertisement as $field => $value) {
            $sql .= "$comma $field= ? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE ID = ?";
        $params[] = $id;
        $result = $this->db->execute($sql, $params);
    }
    /**
     * Gets the relevant advertisement for the entered search query
     *
     * @param string $query words from user entered query
     * @return array associative array containing details of relevant
     *      advertisement
     */
    public function getRelevantAdvertisement($query)
    {
        //don't serve ads of disjunctive or presentational queries
        if(stristr($query, "|") || stristr($query, "#")) {
            return [];
        }
        $db = $this->db;
        PhraseParser::canonicalizePunctuatedTerms($query);
        $query = trim(preg_replace('/( |'.C\PUNCT.')+/u', ' ',
            mb_strtoupper($query)));
        $query = $db->escapeString($query);
        $keywords = "('". preg_replace('/ /u', "', '", $query)."')";
        $today_date = date(C\AD_DATE_FORMAT);
        $total_sql = "
            SELECT B.KEYWORD AS KEYWORD, SUM(B.BID_AMOUNT) AS TOTAL_AMOUNT
            FROM ADVERTISEMENT A, ACCEPTED_AD_BIDS B
            WHERE A.ID = B.AD_ID AND B.KEYWORD = ? AND BID_DATE = ?
                AND A.STATUS=".C\ADVERTISEMENT_ACTIVE_STATUS;
        $result = $db->execute($total_sql, [$query, $today_date]);
        $total_row = false;
        if ($result) {
            $total_row = $db->fetchArray($result);
            if (!isset($total_row['TOTAL_AMOUNT']) ||
                !$total_row['TOTAL_AMOUNT']) {
                $total_row = false;
            }
        }
        if (!$total_row) {
            $total_sql = "
                SELECT B.KEYWORD AS KEYWORD, SUM(B.BID_AMOUNT) AS TOTAL_AMOUNT
                FROM ADVERTISEMENT A, ACCEPTED_AD_BIDS B 
                WHERE A.ID = B.AD_ID AND B.KEYWORD IN $keywords
                AND BID_DATE = ?
                AND A.STATUS=".C\ADVERTISEMENT_ACTIVE_STATUS. "
                GROUP BY B.KEYWORD
                ORDER BY TOTAL_AMOUNT DESC ".
                $db->limitOffset(1);
            $result = $db->execute($total_sql, [$today_date]);
            $total_row = false;
            if ($result) {
                $total_row = $db->fetchArray($result);
                if (!isset($total_row['TOTAL_AMOUNT']) ||
                    !$total_row['TOTAL_AMOUNT']) {
                    $total_row = false;
                }
            }
            if (!$total_row) {
                return [];
            }
        }
        $rand_amount = mt_rand(1, $total_row['TOTAL_AMOUNT'] + 1);
        $bid_sql = "SELECT A.*, B.BID_AMOUNT
            FROM ADVERTISEMENT A, ACCEPTED_AD_BIDS B
            WHERE A.ID = B.AD_ID AND B.KEYWORD = ? AND BID_DATE = ?
            AND A.STATUS=".C\ADVERTISEMENT_ACTIVE_STATUS."
            ORDER BY B.BID_AMOUNT DESC";
        $sum_seen = 0;
        $result = $db->execute($bid_sql, [$total_row['KEYWORD'],
            $today_date]);
        $row = [];
        if($result) {
            // since $rand_amount is at least 1 will go through at least once
            while($sum_seen < $rand_amount) {
                $next_row = $db->fetchArray($result);
                if(!$next_row) {
                    break;
                }
                $row = $next_row;
                $sum_seen += $row['BID_AMOUNT'];
            }
        }
        return $row;
    }
    /**
     * Get advertisement for given advertisement id
     *
     * @param string $id id of ad to get
     * @return array associative array of ad details
     */
    public function getAdvertisementById($id)
    {
        $db = $this->db;
        $sql = "SELECT * FROM ADVERTISEMENT WHERE ID = ?";
        $result = $db->execute($sql, [$id] );
        if($result) {
            $row = $db->fetchArray($result);
            return $row;
        }
        return false;
    }
    /**
     * Change advertisement status for input advertisement id
     *
     * @param int $id id of ad to change
     * @param int $status value representing advertisement status
     */
    public function setAdvertisementStatus($id, $status)
    {
        $db = $this ->db;
        $sql = "UPDATE ADVERTISEMENT SET STATUS= ? WHERE ID = ? ";
        $result = $db->execute($sql, [$status, $id]);
        return $result;
    }
    /**
     * Comparator to sort array of advertisements by date
     *
     * @param string $date1 a Y-M-D formated date to compare
     * @param string $date2 a Y-M-D formated date to compare
     */
    public function sortByDate($date1, $date2)
    {
        return strtotime($date1) - strtotime($date2);
    }
    /**
     * Records number of impressions for an advertisement
     *
     * @param string $ad_id of an advertisement
     */
    public function addImpression($ad_id)
    {
        $sql= "UPDATE ADVERTISEMENT SET IMPRESSIONS = IMPRESSIONS + 1
            WHERE ID = ?";
        $result = $this->db->execute($sql, [$ad_id]);
    }
    /**
     * Records the number of clicks for an advertisement
     *
     * @param integer $ad_id advertisement id
     */
    public function addClick($ad_id)
    {
        $sql = "UPDATE ADVERTISEMENT SET CLICKS = CLICKS + 1 WHERE ID = ?";
        $result = $this->db->execute($sql, [$ad_id]);
    }
    /**
     * Returns bid amount associated with keyword
     *
     * @param string $keyword the keyword associated with advertisement
     * @param date $bid_date date associated with keyword
     * @return integer value representing bid amount
     */
    public function getBidAmount($keyword, $bid_date)
    {
        $db = $this->db;
        $sql = "SELECT SUM(BID_AMOUNT) AS BID_TOTAL FROM ACCEPTED_AD_BIDS
            WHERE KEYWORD=? AND BID_DATE=?";
        $result = $db->execute($sql, [mb_strtoupper($keyword), $bid_date]);
        if (!$result) {
            return C\AD_KEYWORD_INIT_BID;
        }
        if($row = $db->fetchArray($result)) {
            return max($row['BID_TOTAL'], C\AD_KEYWORD_INIT_BID);
        }
        return C\AD_KEYWORD_INIT_BID;
    }
    /**
     * Adds a successful add bid to the list of bids for a keyword on a given
     * day
     *
     * @param int $ad_id of ad this was a succesful bid for
     * @param string $keyword the keyword associated with advertisement
     * @param integer $bid_amount associated with keyword
     * @param date $bid_date associated with keyword
     */
    public function addBid($ad_id, $keyword, $bid_amount, $bid_date)
    {
        $sql= "INSERT INTO ACCEPTED_AD_BIDS (AD_ID,  KEYWORD, BID_AMOUNT,
            BID_DATE) VALUES (?, ?, ?, ?)";
        $result = $this->db->execute($sql, [$ad_id, mb_strtoupper(
            $keyword), $bid_amount, $bid_date]);
    }
}
