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

use seekquarry\yioop\library\CrawlConstants;

/**
 * Web page used to present search results
 * It is also contains the search box for
 * people to types searches into
 *
 * @author Chris Pollett
 */
class RssView extends View implements CrawlConstants
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "rss";
    /**
     * Draws the main landing pages as well as search result pages
     *
     * @param array $data  PAGES contains all the summaries of web pages
     * returned by the current query, $data also contains information
     * about how the the query took to process and the total number
     * of results, how to fetch the next results, etc.
     *
     */
    public function renderView($data)
    {
        if (isset($data['PAGES'])) {
        ?>
            <?php
            foreach ($data['PAGES'] as $page) {?>
                <item>
                <title><?php  e(strip_tags($page[self::TITLE]));
                    if (isset($page[self::TYPE])) {
                        $this->helper("filetype")->render($page[self::TYPE]);
                    }?></title>
                <link><?php if (!isset($page[self::TYPE]) ||
                    (isset($page[self::TYPE])
                    && $page[self::TYPE] != "link")) {
                        e(htmlentities($page[self::URL]));
                    } else {
                        e(htmlentities(strip_tags($page[self::TITLE])));
                    } ?></link>
                <description><?php
                e(htmlentities(strip_tags($page[self::DESCRIPTION])));
                if (isset($page[self::THUMB]) && $page[self::THUMB] != 'null'
                    && $page[self::THUMB] != 'NULL') {
                    $img = "<img src='{$page[self::THUMB]}' ".
                        "alt='Image' />";
                    e(htmlentities($img));
                }
                ?></description>
                </item>
            <?php
            } //end foreach
        }
    }
}
