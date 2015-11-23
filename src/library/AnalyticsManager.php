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
namespace seekquarry\yioop\library;

use seekquarry\yioop\library\CrawlConstants;

/**
 * Used to set and get SQL query and search query timing statistic
 * between models and index_bundle_iterators
 *
 * @author Chris Pollett
 */
class AnalyticsManager
{
    /**
     * Where get and set field values are stored
     * @var array
     */
    private static $data = [];
    /**
     * Used to get the timing statistic associated with $attribute
     * @param string $attribute to get statistic for
     * @return whatever was stored for that statistic
     */
    public static function get($attribute)
    {
        return isset(self::$data[$attribute]) ? self::$data[$attribute] : null;
    }
    /**
     * Used to set the timing statistic $value associated with $attribute
     * @param string $attribute to get statistic for
     * @param mixed $value whatever timing information is to be associated with
     *     value
     */
    public static function set($attribute, $value)
    {
        self::$data[$attribute] = $value;
    }
}
