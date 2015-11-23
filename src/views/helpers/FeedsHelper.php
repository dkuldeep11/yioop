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
namespace seekquarry\yioop\views\helpers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;

/**
 * Helper used to draw links and snippets for RSS feeds
 *
 * @author Chris Pollett
 */
class FeedsHelper extends Helper implements CrawlConstants
{

    /**
     * Takes page summaries for RSS pages and the current query
     * and draws list of news links and a link to the news link subsearch
     * page if applicable.
     *
     * @param array $feed_pages page data from news feeds
     * @param string  $csrf_token token to prevent cross site request forgeries
     * @param string $query the current search query
     * @param string $subsearch name of subsearch page this image group on
     * @param boolean $open_in_tabs whether new links should be opened in
     *    tabs
     */
    public function render($feed_pages, $csrf_token, $query, $subsearch,
        $open_in_tabs = false)
    {
        $news_url = B\subsearchUrl("news");
        $query_array = (empty($csrf_token)) ? [] :
            [C\CSRF_TOKEN => $csrf_token];
        $delim = (C\REDIRECTS_ON) ? "?" : "&amp;";
        if ($subsearch != 'news') {
            $not_news = true;
            $query_string = http_build_query(array_merge($query_array,
                [ "q" => urldecode($query)]));
            ?>
            <h2><a href="<?= $news_url . $delim . $query_string ?>"
                ><?= tl('feeds_helper_view_feed_results',
                urldecode($query)) ?></a></h2>
        <?php
        } else {
            $not_news = false;
        }?>
            <div class="feed-list">
        <?php
        $time = time();
        foreach ($feed_pages as $page) {
            $pub_date = $page[self::SUMMARY_OFFSET][0][4];
            $encode_source = urlencode(
                urlencode($page[self::SOURCE_NAME]));
            if (isset($page[self::URL])) {
                if (strncmp($page[self::URL], "url|", 4) == 0) {
                    $url_parts = explode("|", $page[self::URL]);
                    $url = $url_parts[1];
                    $title = UrlParser::simplifyUrl($url, 60);
                    $subtitle = "title='".$page[self::URL]."'";
                } else {
                    $url = $page[self::URL];
                    $title = $page[self::TITLE];
                    if (strlen(trim($title)) == 0) {
                        $title = UrlParser::simplifyUrl($url, 60);
                    }
                    $subtitle = "";
                }
            } else {
                $url = "";
                $title = isset($page[self::TITLE]) ? $page[self::TITLE] :"";
                $subtitle = "";
            }
            $pub_date = $this->getPubdateString($time, $pub_date);
            $media_url = $news_url . $delim .
                http_build_query(array_merge($query_array,
                [ "q" => "media:news:".urldecode($encode_source)]));
            if ($not_news) {
                ?>
                <div class="blockquote">
                <a href="<?= $page[self::URL] ?>" rel="nofollow" <?php
                if ($open_in_tabs) {  ?> target="_blank" <?php }
                ?>><?= $page[self::TITLE] ?></a>
                <a class="gray-link" rel='nofollow' href="<?= $media_url
                     ?>" ><?= $page[self::SOURCE_NAME]?></a>
                    <span class='gray'> - <?=$pub_date ?></span>
                </div>
                <?php
            } else {
                $image_string = "";
                if (isset($page[self::IMAGE_LINK]) &&
                    $page[self::IMAGE_LINK]!= "") {
                    $image_string = "<img class='float-same' ".
                        "src='{$page[self::IMAGE_LINK]}' alt='' />";
                }
                ?>
                <div class="news-results">
                <?php e($image_string); ?>
                <h2><a href="<?= $page[self::URL] ?>" rel="nofollow" <?php
                if ($open_in_tabs) { ?> target="_blank" <?php }
                ?>><?= $page[self::TITLE] ?></a>.
                <a class="gray-link" rel='nofollow' href="<?= $media_url
                    ?>" ><?= $page[self::SOURCE_NAME] ?></a>
                    <span class='gray'> - <?= $pub_date ?></span>
                </h2>
                <p class="echo-link" <?=$subtitle?> ><?=
                    UrlParser::simplifyUrl($url, 100)." " ?></p>
                <?php
                $description = isset($page[self::DESCRIPTION]) ?
                    $page[self::DESCRIPTION] : "";
                e("<p>$description</p>");
                ?>
                </div>
        <?php
            }
        }
        ?>
        </div>
        <?php
    }
    /**
     * Write as an string in the current locale the difference between the
     * publication date of a post and the current time
     *
     * @param int $time timestamp for current time
     * @param int $pub_date timestamp for feed_item publication
     * @return string in the current locale the time difference
     */
    public function getPubdateString($time, $pub_date)
    {
        $delta = $time - $pub_date;
        if ($delta < C\ONE_DAY) {
            $num_hours = ceil($delta/C\ONE_HOUR);
            if ($num_hours <= 2) {
                if ($num_hours > 1) {
                    $pub_date = tl('feeds_helper_view_onehour');
                } else {
                    $num_minutes = floor($delta/C\ONE_MINUTE);
                    $remainder_seconds = $delta % C\ONE_MINUTE;
                    $pub_date =
                        tl('feeds_helper_view_minsecs', $num_minutes,
                            $remainder_seconds);
                }
            } else {
                $pub_date =
                    tl('feeds_helper_view_hourdate', $num_hours);
            }
        } else {
            $pub_date = date("d/m/Y", $pub_date);
        }
        return $pub_date;
    }
}
