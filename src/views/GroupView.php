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

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;

/**
 * View used to draw and allow editing of group feeds when not in the admin view
 * (so activities panel on side is not present.) This is also used to draw
 * group feeds for public feeds when not logged.
 *
 * @author Chris Pollett
 */
class GroupView extends View implements CrawlConstants
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws a minimal container with a GroupElement in it on which a group
     * feed can be drawn
     *
     * @param array $data with fields used for drawing the container and feed
     */
    public function renderView($data) {
        $logo = C\LOGO;
        $logged_in = !empty($data["ADMIN"]);
        $base_query = $data['PAGING_QUERY'];
        $other_base_query = $data['OTHER_PAGING_QUERY'];
        $token_string = ($logged_in) ? C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]
            : "";
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }
        if (C\PROFILE) {
        ?>
        <div class="top-bar"><?php
            $this->element("signin")->render($data);
        ?>
        </div><?php
        }
        ?>
        <h1 class="group-heading"><a href="<?=C\BASE_URL ?><?php
            if ($logged_in) {
                e("?$token_string");
            }
            ?>"><img class='logo'
            src="<?= C\BASE_URL . $logo ?>" alt="<?= $this->logo_alt_text
            ?>" /></a><small> - <?php
        if (isset($data['JUST_THREAD'])) {
            if (isset($data['WIKI_PAGE_NAME'])) {
                e(tl('groupfeed_element_wiki_thread',
                    $data['WIKI_PAGE_NAME']));
            } else {
                e("<a href='". htmlentities(B\feedsUrl(
                    "group", $data['PAGES'][0]["GROUP_ID"],
                    true, $data['CONTROLLER'])) . $token_string . "' >".
                    $data['PAGES'][0][self::SOURCE_NAME]."</a> : ".
                    $data['SUBTITLE']);
            }
            if (!C\MOBILE) {
                e(" [<a href='{$base_query}f=rss'>RSS</a>]");
            }
        } else if (isset($data['JUST_GROUP_ID'])){
            e($data['SUBTITLE']);
            e(" [".tl('group_view_feed'));
            if (!C\MOBILE && !$logged_in) {
                e("|<a href='{$base_query}f=rss' >RSS</a>");
            }
            if ($token_string) {
                $token_string .= "&";
            }
            e("|<a href='{$base_query}a=wiki'>" .
                tl('group_view_wiki') . "</a>]");
        } else if (isset($data['JUST_USER_ID'])) {
            e(tl('group_view_user',
                $data['PAGES'][0]["USER_NAME"]));
        } else {
            e(tl('group_view_myfeeds'));
        }
        if (!isset($data['JUST_THREAD']) && !isset($data['JUST_GROUP_ID'])) {
            ?><span style="position:relative;top:5px;" >
            <a href="<?= $base_query. 'v=ungrouped&amp;'. $token_string
             ?>" ><img
            src="<?=C\BASE_URL ?>resources/list.png" /></a>
            <a href="<?= $base_query. 'v=grouped&amp;' . $token_string ?>" ><img
            src="<?=C\BASE_URL ?>resources/grouped.png" /></a>
            </span><?php
        }
        ?></small>
        </h1>
        <?php
        if (isset($data["AD_LOCATION"]) &&
            in_array($data["AD_LOCATION"], ['top', 'both'] ) ) { ?>
            <div class="top-adscript group-ad-static"><?=
            $data['TOP_ADSCRIPT']
            ?></div>
            <?php
        }
        if (isset($data['ELEMENT'])) {
            $element = $data['ELEMENT'];
            $this->element($element)->render($data);
        }
        $this->element("help")->render($data);
        if (C\PROFILE) {
        ?>
        <script type="text/javascript">
        /*
            Used to warn that user is about to be logged out
         */
        function logoutWarn()
        {
            doMessage(
                "<h2 class='red'><?php
                e(tl('adminview_auto_logout_one_minute'))?></h2>");
        }
        /*
            Javascript to perform autologout
         */
        function autoLogout()
        {
            document.location='<?=C\BASE_URL ?>?a=signout';
        }
        //schedule logout warnings
        var sec = 1000;
        var minute = 60 * sec;
        setTimeout("logoutWarn()", 59 * minute);
        setTimeout("autoLogout()", 60 * minute);
        </script>
        <?php
        }
    }
}
