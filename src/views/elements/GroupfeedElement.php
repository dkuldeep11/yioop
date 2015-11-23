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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;

/**
 * Element responsible for draw the feeds a user is subscribed to
 *
 * @author Chris Pollett
 */
class GroupfeedElement extends Element implements CrawlConstants
{
    /**
     * Draws the Feeds for the Various Groups a User is a associated with.
     *
     * @param array $data feed items should be prepared by the controller
     *     and stored in the $data['PAGES'] variable.
     *     makes use of the CSRF token for anti CSRF attacks
     */
    public function render($data)
    {
        $is_admin = ($data["CONTROLLER"] == "admin");
        $logged_in = !empty($data["ADMIN"]);
        $arrows = ($is_admin) ? "expand.png" : "collapse.png";
        $is_status = isset($data['STATUS']);
        $token_string = ($logged_in) ? C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        $base_query = B\feedsUrl("", "", true, $data['CONTROLLER']) .
            $token_string;
        if (isset($data["WIKI_QUERY"])) {
            $wiki_query = $data["WIKI_QUERY"] . "&amp;". $token_string;
        }
        $paging_query = $data['PAGING_QUERY'] . $token_string;
        $other_paging_query = $data['OTHER_PAGING_QUERY'] . $token_string;
        if (isset($data['LIMIT'])) {
            $other_paging_query .= "&amp;limit=".$data['LIMIT'];
        }
        if (isset($data['RESULTS_PER_PAGE'])) {
            $other_paging_query .= "&amp;num=".$data['RESULTS_PER_PAGE'];
        }
        if (!$is_status) {
            if (!C\MOBILE &&($is_admin || $logged_in)) {
                ?>
                <div class="float-same admin-collapse sidebar"><a
                    href="<?php e($other_paging_query);
                if (isset($data['MODE']) && $data['MODE'] == 'grouped') {
                    e("&amp;v=grouped");
                }
                ?>" ><img src='<?=C\BASE_URL ?>resources/<?=$arrows
                ?>'/></a></div>
                <div class="float-same admin-collapse"><?php
                if (isset($data['SUBSCRIBE_LINK'])) {
                    if ($data['SUBSCRIBE_LINK'] == C\PUBLIC_JOIN) {
                        e('[<a href="'.$paging_query.'arg=addgroup">'.
                        tl('groupfeed_element_add_group').
                        '</a>]');
                    } else if ($data['SUBSCRIBE_LINK'] != C\NO_JOIN) {
                        e('[<a href="'.$paging_query.'arg=addgroup">'.
                        tl('groupfeed_element_request_add').
                        '</a>]');
                    }
                }
                ?></div>
            <?php
            }
            ?>
            <div id="feedstatus" <?php if ($is_admin) {
                e(' class="current-activity" ');
            } else {
                e(' class="small-margin-current-activity" ');
            }?> >
            <?php
            if (!C\MOBILE && !$is_admin && isset($data["AD_LOCATION"]) &&
                in_array($data["AD_LOCATION"], ['side', 'both'] ) ) { ?>
                <div class="side-adscript"><?=$data['SIDE_ADSCRIPT'] ?></div>
                <?php
            }
        }
        if (isset($data['SUBTITLE']) &&
            $data['SUBTITLE'] != "" && $logged_in) { ?>
            <div class="float-opposite">
            <?php
            if (isset($data["WIKI_PAGE_NAME"])) { ?>
                [<a href="<?= $wiki_query ?>"><?=
                    tl('groupfeed_element_wiki_page') ?></a>]
                [<a href="<?=$base_query ?>"><?=
                    tl('groupfeed_element_back') ?></a>]
            <?php } else {?>
                    <a href="<?= $base_query ?>"><?=
                        tl('groupfeed_element_back')?></a>
            <?php } ?>
            </div>
        <?php
        }
        if ($is_admin) {
            ?>
            <h2>
            <?php
            if (!isset($data['SUBTITLE']) || $data['SUBTITLE'] == "") {
                e(tl('groupfeed_element_group_activity'));
            } else {
                if (isset($data['JUST_THREAD'])) {
                    if (isset($data['WIKI_PAGE_NAME'])) {
                        e(tl('groupfeed_element_wiki_thread',
                            $data['WIKI_PAGE_NAME']));
                    } else {
                        e("<a href='" . htmlentities(B\feedsUrl("group",
                            $data['PAGES'][0]["GROUP_ID"], false,
                            $data['CONTROLLER'])) . $token_string
                            ."'>".
                            $data['PAGES'][0][self::SOURCE_NAME]. "</a> :".
                            $data['SUBTITLE']);
                        if (!C\MOBILE) {
                            $group_base_query = ($is_admin) ?
                                $other_paging_query : $base_query;
                            e(" [<a href='$group_base_query".
                                "&amp;f=rss'>RSS</a>]");
                        }
                    }
                } else if (isset($data['JUST_GROUP_ID'])) {
                    $manage_groups = B\controllerUrl($data['CONTROLLER'], true).
                        $token_string . "&amp;a=manageGroups";
                    e( $data['SUBTITLE']);
                    e(" [".tl('groupfeed_element_feed')."|".
                    "<a href='". htmlentities(B\wikiUrl(
                        "Main", true, $data['CONTROLLER'],
                        $data['JUST_GROUP_ID'])).
                        $token_string . "'>" .
                        tl('group_view_wiki') . "</a>]");
                } else if (isset($data['JUST_USER_ID'])) {
                    e(tl('groupfeed_element_user',
                        $data['PAGES'][0]["USER_NAME"]));
                } else {
                    if (isset($data['SUBTITLE'])) {
                        e("[{$data['SUBTITLE']}]");
                    }
                }
            }
            if (!isset($data['JUST_THREAD']) &&
                !isset($data['JUST_GROUP_ID'])) {
                ?><span style="position:relative;top:5px;" >
                <a href="<?= $paging_query. '&amp;v=ungrouped' ?>" ><img
                src="resources/list.png" /></a>
                <a href="<?= $paging_query. '&amp;v=grouped' ?>" ><img
                src="resources/grouped.png" /></a>
                </span><?php
            }
            ?>
            </h2>
            <?php
        }
        ?>
        <div>
        &nbsp;
        </div>
        <?php
        if (isset($data['MODE']) && $data['MODE'] == 'grouped') {
            $this->renderGroupedView($paging_query, $data);
            $page = false;
        } else {
            $page = $this->renderUngroupedView($logged_in, $base_query,
                $paging_query, $data);
        }
        $data['FRAGMENT'] = "";
        if (isset($data['JUST_THREAD']) && $logged_in && $page &&
            isset($data['GROUP_STATUS']) &&
            $data['GROUP_STATUS'] == C\ACTIVE_STATUS) {
            $data['FRAGMENT'] = '#result-'.$page['ID'];
            ?>
            <div class='button-group-result'>
            <button class="button-box" onclick='comment_form(<?=
                "\"add-comment\", ".
                "{$data['PAGES'][0]['PARENT_ID']},".
                "{$data['PAGES'][0]['GROUP_ID']}" ?>)'><?=
                tl('groupfeed_element_comment') ?></button>
            <div id='add-comment'></div>
            </div>
            <?php
        }
        $this->view->helper("pagination")->render($paging_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        </div>
        <?php
        if (!$is_status) {
            $this->renderScripts($data);
        }
    }
    /**
     * Used to draw group feeds items when we are grouping feeds items by group
     *
     * @param string $paging_query stem for all links
     *      drawn in view
     * @param array& $data fields used to draw the queue
     */
    public function renderGroupedView($paging_query, &$data)
    {
        $token_string = (!empty($data['ADMIN'])) ? C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        foreach ($data['GROUPS'] as $group) {
            e("<div class=\"access-result\">" .
                "<div><b>" .
                "<a href=\"". htmlentities(
                B\feedsUrl("group", $group['GROUP_ID'], true,
                $data['CONTROLLER'])) . $token_string . "&amp;v=grouped" .
                "\" rel=\"nofollow\">" .
                $group['GROUP_NAME'] . "</a> " .
                "[<a href=\"".htmlentities(
                B\wikiUrl("", true, $data['CONTROLLER'])) . $token_string .
                "\">" . tl('manageaccount_element_group_wiki') . "</a>] " .
                "(" . tl('manageaccount_element_group_stats',
                        $group['NUM_POSTS'],
                        $group['NUM_THREADS']) . ")</b>" .
                "</div>" .
                "<div class=\"slight-pad\">" .
                "<b>" . tl('manageaccount_element_last_post')
                . "</b> " .
                "<a href=\"" . B\feedsUrl("thread", $group['THREAD_ID'], true,
                $data['CONTROLLER']) . $token_string . "\">" .
                $group['ITEM_TITLE'] . "</a>" .
                "</div>" .
                "</div>");
            $data['TOTAL_ROWS'] = $data['NUM_GROUPS'];
        }
    }
    /**
     * Used to draw feed items as a combined thread of all groups
     *
     * @param bool $logged_in where or not the session is of a logged in user
     * @param string $base_query url that serves as the stem for all links
     *      drawn in view
     * @param string $paging_query base_query concatenated with limit and num
     * @param array& $data fields used to draw the queue
     * @return array $page last feed item processed
     */
    public function renderUngroupedView($logged_in, $base_query, $paging_query,
        &$data)
    {
        $open_in_tabs = $data['OPEN_IN_TABS'];
        $time = time();
        $can_comment = [C\GROUP_READ_COMMENT, C\GROUP_READ_WRITE,
            C\GROUP_READ_WIKI];
        $start_thread = [C\GROUP_READ_WRITE, C\GROUP_READ_WIKI];
        if (!isset($data['GROUP_STATUS']) ||
            $data['GROUP_STATUS'] != C\ACTIVE_STATUS) {
            $can_comment = [];
            $start_thread = [];
        }
        $token_string = ($logged_in) ? C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        $page = [];
        if (isset($data['PAGES'][0]["MEMBER_ACCESS"]) &&
            in_array($data['PAGES'][0]["MEMBER_ACCESS"], $can_comment)) {
            if (isset($data['JUST_THREAD'])) {
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='comment_form(<?=
                    "\"add-comment\", {$data['PAGES'][0]['PARENT_ID']},".
                    "{$data['PAGES'][0]['GROUP_ID']}" ?>)'><?=
                    tl('groupfeed_element_comment') ?></button>
                <div></div>
                </div>
                <?php
            } else if (isset($data['JUST_GROUP_ID']) &&
                in_array($data['PAGES'][0]["MEMBER_ACCESS"], $start_thread)) {
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='start_thread_form(<?=
                    "\"add-comment\", {$data['PAGES'][0]['GROUP_ID']}" ?>)'><?=
                    tl('groupfeed_element_start_thread') ?></button>
                <div id='add-comment'></div>
                </div>
                <?php
            }
        }
        if (isset($data['NO_POSTS_YET'])) {
            if (isset($data['NO_POSTS_START_THREAD'])) {
                //no read case where no posts yet
                ?>
                <div class='button-group-result'>
                <button class="button-box" onclick='start_thread_form(<?=
                    "\"add-comment\", {$data['JUST_GROUP_ID']}" ?>)'><?=
                    tl('groupfeed_element_start_thread') ?></button>
                <div id='add-comment'></div>
                </div>
                <?php
            }
            ?>
            <p class="red"><?= tl('groupfeed_element_no_posts_yet') ?></p>
            <?php
        }
        if (isset($data['NO_POSTS_IN_THREAD'])) {
            ?>
            <p class="red"><?=tl('groupfeed_element_thread_no_exist') ?></p>
            <?php
        }
        foreach ($data['PAGES'] as $page) {
            $pub_date = $page['PUBDATE'];
            $pub_date = $this->view->helper("feeds")->getPubdateString(
                $time, $pub_date);
            $edit_date = false;
            if (isset($page['EDIT_DATE']) && $page['EDIT_DATE'] &&
                $page['EDIT_DATE'] != $page['PUBDATE']) {
                $edit_date = $this->view->helper("feeds")->getPubdateString(
                    $time, $page['EDIT_DATE']);
            }
            $encode_source = urlencode(urlencode($page[self::SOURCE_NAME]));
            ?>
            <div class='group-result'>
            <?php
            $subsearch = (isset($data["SUBSEARCH"])) ? $data["SUBSEARCH"] :
                "";
            $edit_list = ($page['ID'] == $page['PARENT_ID']) ?
                $start_thread : $can_comment;
            if (in_array($page["MEMBER_ACCESS"], $edit_list) &&
                !isset($data['JUST_GROUP_ID']) &&
                isset($_SESSION['USER_ID']) &&
                (($page['USER_ID'] != "" &&
                $page['USER_ID'] == $_SESSION['USER_ID']) ||
                $_SESSION['USER_ID'] == C\ROOT_ID ||
                $_SESSION['USER_ID'] == $page['OWNER_ID']) &&
                isset($page['TYPE']) && $page['TYPE'] != C\WIKI_GROUP_ITEM) {
                ?>
                <div class="float-opposite" ><?php
                if (!isset($page['NO_EDIT'])) {
                    ?>[<a href="javascript:update_post_form(<?=$page['ID']
                    ?>)"><?=tl('groupfeed_element_edit') ?></a>]<?php
                }
                ?>
                [<a href="<?=$paging_query.'&amp;arg=deletepost&amp;'.
                    "post_id=".$page['ID'] ?>" title="<?=
                    tl('groupfeed_element_delete') ?>">X</a>]
                </div>
            <?php
            }
            ?>
            <div id='result-<?= $page['ID'] ?>' >
            <div class="float-same center" >
            <img class="feed-user-icon" src="<?=$page['USER_ICON'] ?>" /><br />
            <a class="feed-user-link echo-link" rel='nofollow'
                href="<?= htmlentities(B\feedsUrl("user", $page['USER_ID'],
                    true, $data['CONTROLLER'])) . $token_string;
                ?>" ><?=$page['USER_NAME'] ?></a>
            </div>
            <div class="feed-item-body">
            <h2><a href="<?= htmlentities(B\feedsUrl('thread',
                $page['PARENT_ID'], true, $data['CONTROLLER'])) . $token_string
                ?>" rel="nofollow"
                id='title<?=$page['ID']?>' <?php
                if ($open_in_tabs) { ?> target="_blank" <?php }
                ?>><?= $page[self::TITLE] ?></a><?php
                if (isset($page['NUM_POSTS'])) {
                    e(" (");
                    e(tl('groupfeed_element_num_posts',
                        $page['NUM_POSTS']));
                    if (!C\MOBILE &&
                        $data['RESULTS_PER_PAGE'] < $page['NUM_POSTS']) {
                        $thread_query = htmlentities(B\feeds_url("thread",
                            $page['PARENT_ID'], false, $data['CONTROLLER']));
                        $this->view->helper("pagination")->render($thread_query,
                            0, $data['RESULTS_PER_PAGE'], $page['NUM_POSTS'],
                            true);
                    }
                    e(", " . tl('groupfeed_element_num_views',
                        $page['NUM_VIEWS']));
                    e(") ");
                } else if (!isset($data['JUST_GROUP_ID'])) {
                    if (isset($page["VOTE_ACCESS"]) &&
                        $page["VOTE_ACCESS"] == C\UP_DOWN_VOTING_GROUP ) {
                        e(' (+'.$page['UPS'].'/'.($page['UPS'] +
                            $page['DOWNS']).')');
                    } else if (isset($page["VOTE_ACCESS"]) &&
                        $page["VOTE_ACCESS"] == C\UP_VOTING_GROUP) {
                        e(' (+'.$page['UPS'].')');
                    }
                }
                ?>.
                <?= "<span class='gray'> - $pub_date</span>"
                ?>
            <b><a class="gray-link" rel='nofollow' href="<?=htmlentities(
                B\feedsUrl('group', $page['GROUP_ID'], true,
                $data['CONTROLLER'])). $token_string ?>" ><?php
                e($page[self::SOURCE_NAME]."</a></b>");
                if (!isset($data['JUST_GROUP_ID']) &&
                        in_array($page["MEMBER_ACCESS"], $start_thread) ) {
                    ?>
                    <a  class='gray-link' href='javascript:start_thread_form(<?=
                        "{$page['ID']}, {$page['GROUP_ID']},\"".
                        tl('groupfeed_element_start_thread_in_group',
                            $page[self::SOURCE_NAME]) ?>")' title='<?=
                        tl('groupfeed_element_start_thread_in_group',
                            $page[self::SOURCE_NAME]) ?>'><img
                        class="new-thread-icon" src='<?=C\BASE_URL
                        ?>resources/new_thread.png' /></a>
                    <?php
                }
                ?>
                </h2>
            <?php
            if (!isset($data['JUST_GROUP_ID'])) {
                $description = isset($page[self::DESCRIPTION]) ?
                    $page[self::DESCRIPTION] : "";?>
                <div id='description<?= $page['ID']?>'><?php
                    e($description);
                    if ($edit_date) {
                        e("<br /><b>".
                        tl('groupfeed_element_last_edited', $edit_date)."</b>");
                    } ?></div>
                <?php
                if (!isset($page['NO_EDIT']) &&
                    isset($page['OLD_DESCRIPTION'])){
                    ?>
                    <div id='old-description<?= $page['ID'] ?>'
                        class='none'><?=$page['OLD_DESCRIPTION'] ?></div>
                    <?php
                }
                if ($logged_in && isset($page["VOTE_ACCESS"]) &&
                    in_array($page["VOTE_ACCESS"], [C\UP_DOWN_VOTING_GROUP,
                        C\UP_VOTING_GROUP])) {
                    ?>
                    <div class="gray"><b>
                    <?php
                    e(tl('groupfeed_element_post_vote'));
                    $up_vote = $paging_query."&amp;post_id=".$page['ID'].
                        "&amp;arg=upvote&amp;group_id=".$page['GROUP_ID'];
                    $down_vote = $paging_query."&amp;post_id=".$page['ID'].
                        "&amp;arg=downvote&amp;group_id=".$page['GROUP_ID'];
                    if ($page["VOTE_ACCESS"] == C\UP_DOWN_VOTING_GROUP) {
                        ?>
                        <button onclick='window.location="<?=
                            $up_vote ?>"'>+</button><button
                            onclick='window.location="<?=
                            $down_vote ?>"'>-</button>
                        <?php
                    } else if ($page["VOTE_ACCESS"] == C\UP_VOTING_GROUP) {
                        ?>
                        <button onclick='window.location="<?=
                            $up_vote ?>"'>+</button>
                        <?php
                    }
                    ?>
                    </b></div>
                    <?php
                }
            } else if (isset($page['LAST_POSTER']) ){
                ?>
                <div id='description<?= $page['ID'] ?>'><?php
                $recent_date = $this->view->helper("feeds"
                    )->getPubdateString($time, $page['RECENT_DATE']);
                e("<b>".tl('groupfeed_element_last_post_info')."</b> ".
                    $recent_date." - <a href='" . B\feedsUrl('user',
                    $page['LAST_POSTER_ID'], true, $data['CONTROLLER'] ).
                    $token_string . "'>".
                    $page['LAST_POSTER'] . "</a>");
                    ?></div>
            <?php
            }
            ?>
            <div class="float-opposite">
                <?php if (!isset($data['JUST_GROUP_ID']) &&
                    in_array($page["MEMBER_ACCESS"], $can_comment) &&
                    !isset($data['JUST_THREAD'])){?>
                    <a href='javascript:comment_form(<?=
                        "{$page['ID']}, {$page['PARENT_ID']}, ".
                        "{$page['GROUP_ID']}" ?>)'><?=
                        tl('groupfeed_element_comment') ?></a>.<?php
                }
                ?>
            </div>
            </div>
            </div>
            <div id='<?= $page["ID"] ?>'></div>
            </div>
            <div>
            &nbsp;
            </div>
            <?php
        } //end foreach
        return $page;
    }
    /**
     * Used to render the Javascript that appears at the non-status updating
     * portion of the footer of this element.
     *
     * @param array $data contains arguments needs to draw urls correctly.
     */
    public function renderScripts($data)
    {
        if ($data['LIMIT'] + $data['RESULTS_PER_PAGE'] == $data['TOTAL_ROWS']){
            $data['LIMIT'] += $data['RESULTS_PER_PAGE'] - 1;
        }
        $paging_query = $data['PAGING_QUERY'];
        $token_string = (!empty($data['ADMIN'])) ? C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN] : "";
        $limit_hidden = "";
        $delim = "";
        if (isset($data['LIMIT'])) {
            $paging_query .= "limit=".$data['LIMIT'];
            $delim = "&";
        }
        $num_hidden = "";
        if (isset($data['RESULTS_PER_PAGE'])) {
            $paging_query .= "{$delim}num=".$data['RESULTS_PER_PAGE'];
            $delim = "&";
        }
        $just_fields = ["LIMIT" => "limit", "RESULTS_PER_PAGE" => "num",
            "JUST_THREAD" => 'just_thread', "JUST_USER_ID" => "just_user_id",
            "JUST_GROUP_ID" => "just_group_id"];
        $hidden_form = "\n";
        foreach ($just_fields as $field => $form_field) {
            if (isset($data[$field])) {
                $hidden_form .= "'<input type=\"hidden\" ".
                    "name=\"$form_field\" value=\"{$data[$field]}\" />' +\n";
            }
        }
        $this->view->helper("fileupload")->setupFileUploadParams();
        ?>
        <script type="text/javascript"><?php
            $clear = (C\MOBILE) ? " clear" : "";
            $drag_above_text = tl('fileupload_helper_drag_textarea');
            $click_link_text = tl('fileupload_helper_click_textarea');
        ?>
        var updateId = null;
        function comment_form(id, parent_id, group_id)
        {
            clearInterval(updateId);
            tmp = '<div class="post<?= $clear ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length);
            if (start_elt != tmp) {
                elt(id).innerHTML =
                    tmp +
                    '<form action="./<?= $data['FRAGMENT']
                    ?>" >' + <?= $hidden_form ?>
                    '<input type="hidden" name="c" value="<?=
                        $data['CONTROLLER'] ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="addcomment" />' +
                    '<input type="hidden" name="parent_id" value="' +
                        parent_id + '" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?= C\CSRF_TOKEN ?>" '+
                    'value="<?= $data[C\CSRF_TOKEN] ?>" />' +
                    '<h2><b><label for="comment-'+ id +'" ><?=
                        tl("groupfeed_element_add_comment")
                    ?></label></b></h2>'+
                    '<textarea class="short-text-area" '+
                    'id="comment-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,'+
                    '!wikibtn-slide" '+
                    '></textarea>' +
                    '<div class="upload-gray-box center black">' +
                    '<input type="file" id="file-' + id + '" name="file_' + id +
                    '"  class="none" multiple="multiple" />' +
                    '<?= $drag_above_text ?>' +
                    '<a href="javascript:elt(\'file-' + id + '\').click()">'+
                    '<?= $click_link_text ?></a></div>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?= tl("groupfeed_element_save") ?>'+
                    '</button><div>&nbsp;</div>' +
                    '</form>';
                var comment_id = 'comment-' + id;
                initializeFileHandler(comment_id , "file-" + id,
                    <?= L\metricToInt(ini_get('upload_max_filesize'))
                    ?>, "textarea", null, true);
                editorize(comment_id);
                elt(comment_id).focus();
            } else {
                elt(id).innerHTML = "";
            }
        }
        function start_thread_form(id, group_id, group_name)
        {
            clearInterval(updateId);
            tmp = '<div class="post<?=$clear ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if (start_elt != tmp) {
                var group_header = "";
                if (typeof(group_name) !== 'undefined') {
                    group_header = '<h2><b>' + group_name + '</b></h2>';
                }
                elt(id).innerHTML =
                    tmp +
                    '<br />'
                    +'<form action="./<?= $data['FRAGMENT']
                    ?>" method="post" >' + <?= $hidden_form ?>
                    '<input type="hidden" name="c" value="<?=
                        $data['CONTROLLER'] ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="newthread" />' +
                    '<input type="hidden" name="group_id" value="' +
                        group_id + '" />' +
                    '<input type="hidden" name="<?= C\CSRF_TOKEN ?>" '+
                        'value="<?= $data[C\CSRF_TOKEN] ?>" />' +
                    group_header+
                    '<p><b><label for="title-'+ id +'" ><?=
                        tl("groupfeed_element_subject")
                    ?></label></b></p>' +
                    '<p><input type="text" id="title-'+ id +'" '+
                    'name="title" value="" '+
                    ' maxlength="<? C\TITLE_LEN ?>" '+
                    'class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?=
                        tl("groupfeed_element_post")
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,' +
                    '!wikibtn-slide" ></textarea>' +
                    '<div class="upload-gray-box center black">' +
                    '<input type="file" id="file-' + id + '" name="file_' + id +
                    '"  class="none" multiple="multiple" />' +
                    '<?= $drag_above_text ?>' +
                    '<a href="javascript:elt(\'file-' + id + '\').click()">'+
                    '<?= $click_link_text ?></a></div>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?= tl("groupfeed_element_save")
                    ?></button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
                var description_id = 'description-' + id;
                initializeFileHandler(description_id , "file-" + id,
                    <?= L\metricToInt(ini_get('upload_max_filesize'))
                    ?>, "textarea", null, true);
                editorize(description_id);
            } else {
                elt(id).innerHTML = "";
            }
        }
        function update_post_form(id)
        {
            clearInterval(updateId);
            var title = elt('title'+id).innerHTML;
            var description = elt('old-description'+id).innerHTML;
            var tmp = '<div class="post<?= $clear ?>"></div>';
            start_elt = elt(id).innerHTML.substr(0, tmp.length)
            if (start_elt != tmp) {
                setDisplay('result-'+id, false);
                elt(id).innerHTML =
                    tmp +
                    '<form action="./<?= $data['FRAGMENT']
                    ?>" method="post" >' + <?= $hidden_form ?>
                    '<input type="hidden" name="c" value="<?=
                        $data['CONTROLLER'] ?>" />' +
                    '<input type="hidden" name="a" value="groupFeeds" />' +
                    '<input type="hidden" name="arg" value="updatepost" />' +
                    '<input type="hidden" name="post_id" value="' +
                        id + '" />' +
                    '<input type="hidden" name="<?= C\CSRF_TOKEN ?>" '+
                    'value="<?= $data[C\CSRF_TOKEN] ?>" />' +
                    '<h2><b><?=
                        tl("groupfeed_element_edit_post")
                    ?></b></h2>'+
                    '<p><b><label for="title-'+ id +'" ><?=
                        tl("groupfeed_element_subject")
                    ?></label></b></p>' +
                    '<p><input type="text" name="title" value="'+title+'" '+
                    ' maxlength="<?= C\TITLE_LEN ?>" class="wide-field"/></p>' +
                    '<p><b><label for="description-'+ id +'" ><?=
                        tl("groupfeed_element_post")
                    ?></label></b></p>' +
                    '<textarea class="short-text-area" '+
                    'id="description-'+ id +'" name="description" '+
                    'data-buttons="all,!wikibtn-search,!wikibtn-heading,' +
                    '!wikibtn-slide" >' + description + '</textarea>'+
                    '<div class="upload-gray-box center black">' +
                    '<input type="file" id="file-' + id + '" name="file_' + id +
                    '"  class="none" multiple="multiple" />' +
                    '<?= $drag_above_text?>' +
                    '<a href="javascript:elt(\'file-' + id + '\').click()">'+
                    '<?=$click_link_text ?></a></div>' +
                    '<button class="button-box float-opposite" ' +
                    'type="submit"><?= tl("groupfeed_element_save")
                    ?></button>' +
                    '<div>&nbsp;</div>'+
                    '</form>';
                var description_id = 'description-' + id;
                initializeFileHandler(description_id , "file-" + id,
                    <?= L\metricToInt(ini_get('upload_max_filesize'))
                    ?>, "textarea", null, true);
                editorize(description_id);
            } else {
                elt(id).innerHTML = "";
                setDisplay('result-'+id, true);
            }
        }
        function feedStatusUpdate()
        {
            var startUrl = "<?=html_entity_decode($paging_query) .
                $delim . $token_string . '&arg=status' ?>";
            var feedTag = elt('feedstatus');
            getPage(feedTag, startUrl);
            elt('feedstatus').style.backgroundColor = "#EEE";
            setTimeout("resetBackground()", 0.5*sec);
        }
       function clearUpdate()
        {
             clearInterval(updateId);
             var feedTag = elt('feedstatus');
             feedTag.innerHTML= "<h2 class='red'><?=
                tl('groupfeed_element_no_longer_update')?></h2>";
        }
        function resetBackground()
        {
             elt('feedstatus').style.backgroundColor = "#FFF";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60*sec;
             updateId = setInterval("feedStatusUpdate()", 15 * sec);
             setTimeout("clearUpdate()", 20 * minute + sec);
        }
        </script>
        <?php
    }
}
