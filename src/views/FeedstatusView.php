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
namespace seekquarry\yioop\views;

/**
 * This view is drawn to refresh a group feed that has recently been posted
 * to. Redrawing is invoked from a client script every so many seconds.
 *
 * @author Chris Pollett
 */
class FeedstatusView extends View
{
    /**
     * An Ajax call from the My Group Feeds element in Admin View triggers
     * this view to be instantiated. The renderView method then draws
     * the most recent feed posts.
     *
     * @param array $data info about the current crawl status
     */
    public function renderView($data) {
        $data["STATUS"] = true;
        $this->element("groupfeed")->render($data);
    }
}
