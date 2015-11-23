<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2015
 *  @filesource
 */
namespace seekquarry\yioop\views\layouts;

/**
 * Layout used for the seek_quarry Website
 * including pages such as search landing page
 * and settings page
 *
 * @author Eswara Rajesh Pinapala
 */
class ApiLayout extends Layout
{
    /**
     * Responsible for drawing the prefix/suffix (if any for the API response)
     *
     * @param array $data  an array of data set up by the controller to be
     * be used in drawing the WebLayout and its View.
     */
    public function render($data)
    {
        $this->view->renderView($data);
    }
}

