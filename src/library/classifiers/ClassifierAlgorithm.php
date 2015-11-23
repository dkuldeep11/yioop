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
namespace seekquarry\yioop\library\classifiers;

use seekquarry\yioop\library as L;

/** For crawlLog*/
require_once __DIR__."/../Utility.php";
/**
 * An abstract class shared by classification algorithms that implement a
 * common interface.
 *
 * This base class implements a few administrative utility methods that all
 * classification algorithms can take advantage of.
 *
 * @author Shawn Tice
 */
abstract class ClassifierAlgorithm
{
    /**
     * Flag used to control level of debug messages for now 0 == no messages,
     * anything else causes messages to be output
     * @var int
     */
    public $debug = 0;
    /**
     * Write a message to log file depending on debug level for this subpackage
     * @param string $message what to write to the log
     */
    public function log($message)
    {
        if ($this->debug > 0) {
            L\crawlLog($message);
        }
    }
}
