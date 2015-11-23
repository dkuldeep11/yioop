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
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
/**
 * Element responsible for drawing links to common subsearches
 *
 * @author Chris Pollett
 */
class SubsearchElement extends Element
{
    /**
     * Method responsible for drawing links to common subsearches
     *
     * @param array $data makes use of the CSRF token for anti CSRF attacks
     */
    public function render($data)
    {
        if (!C\SUBSEARCH_LINK) { return; }
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        if (!isset($data['SUBSEARCH'])) {
            $data['SUBSEARCH'] = "";
        }
        $drop_threshold = 4;
        if (C\MOBILE) {
            $drop_threshold = 0;
        }
        ?>
            <div class="subsearch" >
            <ul class="out-list">
                <?php
                $i = 0;
                $found = false;
                foreach ($data["SUBSEARCHES"] as $search) {
                    if ($i >= $drop_threshold) {
                        $append_token = ($logged_in) ? "&amp;".C\CSRF_TOKEN.
                                "=".$data[C\CSRF_TOKEN] : "";
                        e("<li class='outer'><a ".
                            " href='" . B\moreUrl() . "$append_token' ><b>".
                            tl('subsearch_element_more').
                            "</b></a>");
                        if (!$found && !C\MOBILE) {
                            foreach ($data["SUBSEARCHES"] as $subsearch) {
                                if ($subsearch['FOLDER_NAME']
                                    == $data['SUBSEARCH']) {
                                    e(" [<b>".
                                        $subsearch['SUBSEARCH_NAME']."</b>]");
                                    break;
                                }
                            }
                        }
                        break;
                    }
                    $i++;
                    $source = B\subsearchUrl($search["FOLDER_NAME"]);
                    $delim = (C\REDIRECTS_ON) ? "?" : "&amp;";
                    if ($search["FOLDER_NAME"] == "") {
                        $source = C\BASE_URL;
                        $delim = "?";
                    }
                    $b = ($search['FOLDER_NAME'] == $data['SUBSEARCH']) ?
                        "<b>" : "";
                    $b_close = ($b == "") ? "" : "</b>";
                    if ($b) {
                        $found = true;
                    }
                    if ($i <= $drop_threshold) {
                        $query = "";
                        if (isset($data[C\CSRF_TOKEN]) && $logged_in) {
                            $query .= $delim . C\CSRF_TOKEN .
                                "=" . $data[C\CSRF_TOKEN];
                        }
                        if (isset($data['QUERY']) &&
                            !isset($data['NO_QUERY'])) {
                            $query .= "{$delim}q={$data['QUERY']}";
                        }
                        e("<li class='outer'>$b<a href='$source$query'>".
                            "{$search['SUBSEARCH_NAME']}</a>$b_close</li>");
                    }
                }
                if ($i > $drop_threshold + 1) {
                    e("</ul></div></li>");
                }
                ?>
            </ul>
            </div>
        <?php
        }
}
