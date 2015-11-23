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
 * @author Mallika Perepa (Creator), Chris Pollett (rewrote)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;

/**
 * Used to draw the admin screen on which users can create groups, delete
 * groups and add and delete users and roles to a group
 *
 * @author Mallika Perepa (started) Chris Pollett (rewrite)
 */
class ManagegroupsElement extends Element
{
    /**
     * Renders the screen in which groups can be created, deleted, and added or
     * deleted
     *
     * @param array $data  contains antiCSRF token, as well as data on
     *     available groups or which user is in what group
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $token_string = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
        ?>
        <div class="current-activity" >
        <?php
        switch ($data['FORM_TYPE']) {
            case "changeowner":
                $this->renderChangeOwnerForm($data);
                break;
            case "inviteusers":
                $this->renderInviteUsersForm($data);
                break;
            case "search":
                $this->renderSearchForm($data);
                break;
            default:
                $this->renderGroupsForm($data);
        }
        if (isset($data['browse']) && $data['browse'] == 'true') {
            $data['TABLE_TITLE'] = tl('managegroups_element_not_my_groups');
        } else {
            $data['TABLE_TITLE'] = tl('managegroups_element_groups');
        }
        $data['ACTIVITY'] = 'manageGroups';
        $data['VIEW'] = $this->view;
        $data['NO_FLOAT_TABLE'] = true;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="role-table table-margin">
            <tr>
                <th><?= tl('managegroups_element_groupname')?></th>
                <th><?= tl('managegroups_element_groupowner')?></th>
                <?php if (!C\MOBILE) { ?>
                <th><?= tl('managegroups_element_registertype')?></th>
                <th><?= tl('managegroups_element_memberaccess')?></th>
                <th><?= tl('managegroups_element_voting')?></th>
                <th><?= tl('managegroups_element_post_lifetime') ?></th>
                <?php } ?>
                <th colspan='2'><?=tl('managegroups_element_actions') ?></th>
            </tr>
        <?php
            $group_url = $admin_url . $token_string;
            $base_url = $group_url . "&amp;a=manageGroups";
            $wiki_url = $group_url . "&amp;a=wiki&amp;group_id=";
            $group_url .= "&amp;a=groupFeeds&amp;just_group_id=";
            if (isset($data['browse'])) {
                $base_url .= "&amp;browse=".$data['browse'];
            }
            if (isset($data['START_ROW'])) {
                $base_url .= "&amp;start_row=".$data['START_ROW'].
                    "&amp;end_row=".$data['END_ROW'].
                    "&amp;num_show=".$data['NUM_SHOW'];
            }
            $is_root = ($_SESSION['USER_ID'] == C\ROOT_ID);
            $delete_url = $base_url . "&amp;arg=deletegroup&amp;";
            $unsubscribe_url = $base_url . "&amp;arg=unsubscribe&amp;";
            $join_url = $base_url . "&amp;arg=joingroup&amp;";
            $add_url = $base_url . "&amp;arg=addgroup&amp;";
            $edit_url = $base_url . "&amp;arg=editgroup&amp;";
            $transfer_url = $base_url . "&amp;arg=changeowner&amp;";
            $mobile_columns = ['GROUP_NAME', 'OWNER'];
            $ignore_columns = ["GROUP_ID", "OWNER_ID", "JOIN_DATE",
                "NUM_MEMBERS"];
            if (isset($data['browse'])) {
                $igore_columns[] = 'STATUS';
            }
            $access_columns = ["MEMBER_ACCESS"];
            $dropdown_columns = ["MEMBER_ACCESS", "REGISTER_TYPE",
                "VOTE_ACCESS", "POST_LIFETIME"];
            $choice_arrays = [
                "MEMBER_ACCESS" => ["ACCESS_CODES", "memberaccess"],
                "REGISTER_TYPE" => ["REGISTER_CODES", "registertype"],
                "VOTE_ACCESS" => ["VOTING_CODES", "voteaccess"],
                "POST_LIFETIME" => ["POST_LIFETIMES", "postlifetime"],
            ];
            $stretch = (C\MOBILE) ? 1 : 1.5;
            foreach ($data['GROUPS'] as $group) {
                e("<tr>");
                foreach ($group as $col_name => $group_column) {
                    if (in_array($col_name, $ignore_columns) || (
                        C\MOBILE && !in_array($col_name, $mobile_columns))) {
                        continue;
                    }
                    if (in_array($col_name, $mobile_columns)) {
                        if (strlen($group_column) >
                            $stretch * C\NAME_TRUNCATE_LEN) {
                            $group_column = wordwrap($group_column,
                                $stretch * C\NAME_TRUNCATE_LEN, "\n", true);
                        }
                    }
                    if ($col_name == "STATUS") {
                        $group_column =
                            $data['MEMBERSHIP_CODES'][$group[$col_name]];
                        if ($group['STATUS'] == C\ACTIVE_STATUS) {
                            continue;
                        }
                    }
                    if ($col_name == "MEMBER_ACCESS" &&
                        isset($group['STATUS'])&&
                        $group['STATUS'] != C\ACTIVE_STATUS) {
                        continue;
                    }
                    if (in_array($col_name, $dropdown_columns)) {
                        ?><td>
                        <?php
                        $choice_array = $choice_arrays[$col_name][0];
                        $arg_name = $choice_arrays[$col_name][1];

                        if ($group['GROUP_ID'] == C\PUBLIC_GROUP_ID ||
                            $group["OWNER_ID"] != $_SESSION['USER_ID']) {
                            ?><span class='gray'><?=
                                $data[$choice_array][$group[$col_name]]?>
                                </span><?php
                        } else {
                        ?>
                            <form  method="get">
                            <input type="hidden" name="c" value="admin" />
                            <input type="hidden" name="<?=C\CSRF_TOKEN ?>"
                                value="<?= $data[C\CSRF_TOKEN] ?>" />
                            <input type="hidden" name="a" value="manageGroups"
                            />
                            <input type="hidden" name="arg" value="<?=
                                $arg_name ?>" />
                            <input type="hidden" name="group_id" value="<?=
                                $group['GROUP_ID'] ?>" />
                            <?php
                            $this->view->helper("options")->render(
                                "update-$arg_name-{$group['GROUP_ID']}",
                                $arg_name, $data[$choice_array],
                                $group[$col_name], true);
                            ?>
                            </form>
                        <?php
                        }
                        ?>
                        </td>
                        <?php
                    } else if ($col_name == 'OWNER') {
                        if ($group['GROUP_ID'] != C\PUBLIC_GROUP_ID &&
                            ($is_root ||
                            $group["OWNER_ID"] == $_SESSION['USER_ID'])) {
                            e("<td><b><a href='".$transfer_url."group_id=".
                                $group['GROUP_ID']."'>$group_column".
                                "</a></b><br/ >[".
                            tl('managegroups_element_num_users',
                            $group['NUM_MEMBERS'])."]</td>");
                        } else {
                            e("<td><b>$group_column</b><br/ >[".
                            tl('managegroups_element_num_users',
                            $group['NUM_MEMBERS'])."]</td>");
                        }
                    } else if ($col_name == 'GROUP_NAME' &&
                        (!isset($data['browse']) || !$data['browse']
                            || in_array($group['REGISTER_TYPE'],
                            [C\PUBLIC_JOIN, C\PUBLIC_BROWSE_REQUEST_JOIN] ) ) &&
                        ($group['MEMBER_ACCESS'] != C\GROUP_PRIVATE ||
                        $group["OWNER_ID"] == $_SESSION['USER_ID'])) {
                        e("<td><a href='".$group_url.$group['GROUP_ID']."' >".
                            $group_column."</a> [<a href=\""
                            . htmlentities(B\wikiUrl("Main", true,
                            "admin", $group['GROUP_ID'])) .
                            $token_string ."\">"
                            . (tl('manageaccount_element_group_wiki'))
                            . "</a>]</td>");
                    } else {
                        e("<td>$group_column</td>");
                    }
                }
                ?>
                <td><?php
                    if ($group['OWNER_ID'] != $_SESSION['USER_ID']||
                        $group['GROUP_NAME'] == 'Public') {
                        if (isset($group['STATUS']) &&
                            $group['STATUS'] == C\INVITED_STATUS) {
                            ?><a href="<?=$join_url . 'group_id='.
                                $group['GROUP_ID'].'&amp;user_id=' .
                                $_SESSION['USER_ID'] ?>"><?=
                                tl('managegroups_element_join')
                            ?></a><?php
                        } else {
                            e('<span class="gray">'.
                                tl('managegroups_element_edit').'</span>');
                        }
                    } else {
                        ?><a href="<?= $edit_url . 'group_id='.
                            $group['GROUP_ID'] ?>"><?=
                            tl('managegroups_element_edit')?><?php
                    }?></a></td>
                <td><?php
                    if ($group['GROUP_NAME'] == 'Public') {
                        e('<span class="gray">'.
                            tl('managegroups_element_delete').'</span>');
                    } else if (isset($data['browse']) &&
                        $data['browse'] == 'true') {
                        if ( $group['REGISTER_TYPE'] == C\NO_JOIN &&
                            $_SESSION['USER_ID']  != C\ROOT_ID) {
                            e('<span class="gray">'.
                                tl('managegroups_element_join').'</span>');
                        } else {
                            ?><a href="<?= $add_url . 'name='.
                                urlencode($group['GROUP_NAME']).'&amp;user_id='.
                                $_SESSION['USER_ID'] ?>"><?=
                                tl('managegroups_element_join')
                            ?></a><?php
                        }
                    } else if ($_SESSION['USER_ID']!=$group['OWNER_ID']) {?>
                        <a href="<?= $unsubscribe_url . 'group_id='.
                            $group['GROUP_ID'].'&amp;user_id=' .
                            $_SESSION['USER_ID'] ?>"><?php
                            if (isset($group['STATUS']) &&
                                $group['STATUS'] == C\INVITED_STATUS) {
                                e(tl('managegroups_element_decline'));
                            } else {
                                e(tl('managegroups_element_unsubscribe'));
                            }
                        ?></a></td><?php
                    }  else {?>
                        <a onclick='javascript:return confirm("<?=
                        tl('confirm_delete_operation') ?>");'
                        href="<?= $delete_url . 'group_id='.
                        $group['GROUP_ID'] ?>"><?=
                        tl('managegroups_element_delete')?><?php
                    }?></a></td>
                </tr>
            <?php
            }
        ?>
        </table>
        <?php if (C\MOBILE) { ?>
            <div class="clear">&nbsp;</div>
        <?php } ?>
        </div>
        <?php
    }
    /**
     * Draws the add groups and edit groups forms
     *
     * @param array $data consists of values of groups fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderGroupsForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=manageGroups&amp;visible_users=".$data['visible_users'];
        $browse_url = $base_url . '&amp;arg=search&amp;browse=true';
        $editgroup = ($data['FORM_TYPE'] == "editgroup") ? true: false;
        $creategroup = ($data['FORM_TYPE'] == "creategroup") ? true: false;
        $addgroup = !$editgroup && !$creategroup;
        if ($editgroup) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('managegroups_element_addgroup_form')."</a></div>");
            e("<h2>".tl('managegroups_element_group_info'). "</h2>");
        } else if ($creategroup) {
            e("<h2>".tl('managegroups_element_create_group'));
            e("&nbsp;" . $this->view->helper("helpbutton")->render(
            "Create Group", $data[C\CSRF_TOKEN]). "</h2>");
        } else {
            e("<h2>".tl('managegroups_element_add_group'). "</h2>");
        }

        ?>
        <form id="group-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="add_refer" value="0" />
        <input type="hidden" name="arg" value="<?=$data['FORM_TYPE'] ?>" />
        <input type="hidden" id="visible-users" name="visible_users"
            value="<?= $data['visible_users'] ?>" />
        <input type="hidden" name="group_id" value="<?=
            $data['CURRENT_GROUP']['id'] ?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="group-name"><?=
            tl('managegroups_element_groupname') ?></label>:</th>
            <td><input type="text" id="group-name"
                name="name"  maxlength="<?= C\SHORT_TITLE_LEN ?>"
                value="<?= $data['CURRENT_GROUP']['name'] ?>"
                class="narrow-field" <?php
                if ($editgroup) {
                    e(' disabled="disabled" ');
                }
                ?> /></td><?php
                if ($addgroup) { ?>
                    <td>[<a href="<?=$browse_url ?>"><?=
                        tl('managegroups_element_browse') ?></a>]
                    <?=$this->view->helper("helpbutton")->render(
                            "Browse Groups", $data[C\CSRF_TOKEN])
                    ?></td>
                <?php
                }
        ?></tr>
        <?php
        if ($creategroup || $editgroup) { ?>
            <tr><th class="table-label"><label for="register-type"><?=
                tl('managegroups_element_register')?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "register-type", "register", $data["REGISTER_CODES"],
                         $data['CURRENT_GROUP']['register']);
                    ?></td></tr>
            <tr><th class="table-label"><label for="member-access"><?=
                tl('managegroups_element_memberaccess')?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "member-access", "member_access", $data["ACCESS_CODES"],
                        $data['CURRENT_GROUP']['member_access']);
                    ?></td></tr>
            <tr><th class="table-label"><label for="vote-access"><?=
                tl('managegroups_element_voting')?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "vote-access", "vote_access", $data["VOTING_CODES"],
                        $data['CURRENT_GROUP']['vote_access']);
                    ?></td></tr>
            <tr><th class="table-label"><label for="post-lifetime"><?=
                tl('managegroups_element_post_lifetime')?></label>:</th>
                <td><?php
                    $this->view->helper("options")->render(
                        "post-lifetime", "post_lifetime",
                        $data["POST_LIFETIMES"],
                        $data['CURRENT_GROUP']['post_lifetime']);
                    ?></td></tr>
        <?php
        }
        if ($editgroup) {
        ?>
            <tr><th class="table-label" style="vertical-align:top"><?=
                tl('managegroups_element_group_users') ?>:</th>
                <td><div class='light-gray-box'>
                <div class="center">
                    [<a href="javascript:toggleUserCollection('visible-users')"
                        ><?=tl('managegroups_element_num_users',
                        $data['NUM_USERS_GROUP'])?></a>]
                </div>
                <?php
                if ($data['visible_users'] == 'true') {
                ?>
                    <table><?php
                    $stretch = (C\MOBILE) ? 1 :2;
                    foreach ($data['GROUP_USERS'] as $user_array) {
                        $action_url = $base_url."&amp;user_id=" .
                            $user_array['USER_ID'] . "&amp;group_id=".
                            $data['CURRENT_GROUP']['id'].
                            "&amp;user_filter=".$data['USER_FILTER'];
                        $out_name = $user_array['USER_NAME'];
                        if (strlen($out_name) > $stretch *
                            C\NAME_TRUNCATE_LEN) {
                            $out_name = wordwrap($out_name,
                                $stretch * C\NAME_TRUNCATE_LEN, "\n", true);
                        }
                        e("<tr><td><b>".
                            $out_name.
                            "</b></td>");
                        if ($data['CURRENT_GROUP']['owner'] ==
                            $user_array['USER_NAME']) {
                            e("<td>".
                            $data['MEMBERSHIP_CODES'][$user_array['STATUS']] .
                                "</td>");
                            e("<td>" . tl('managegroups_element_groupowner') .
                                "</td><td><span class='gray'>".
                            tl('managegroups_element_delete')."</span></td>");
                        } else {
                            e("<td>".$data['MEMBERSHIP_CODES'][
                                $user_array['STATUS']]);
                            e("</td>");
                            switch ($user_array['STATUS']) {
                                case C\INACTIVE_STATUS:
                                    e("<td><a href='$action_url".
                                        "&amp;arg=activateuser'>".
                                        tl('managegroups_element_activate').
                                        '</a></td>');
                                    break;
                                case C\ACTIVE_STATUS:
                                    e("<td><a href='$action_url".
                                        "&amp;arg=banuser'>".
                                        tl('managegroups_element_ban').
                                        '</a></td>');
                                    break;
                                case C\SUSPENDED_STATUS:
                                    e("<td><a href='$action_url".
                                        "&amp;arg=reinstateuser'>".
                                        tl('managegroups_element_unban')
                                        .'</a></td>');
                                    break;
                                default:
                                    e("<td></td>");
                                    break;
                            }
                            e("<td><a href='$action_url&amp;arg=deleteuser'>".
                                tl('managegroups_element_delete')."</a></td>");
                        }
                        e("</tr>");
                    }
                    $center = (C\MOBILE) ? "" : 'class="center"';
                    if ($data['USER_FILTER'] != "" ||
                        (isset($data['NUM_USERS_GROUP']) &&
                        $data['NUM_USERS_GROUP'] > C\NUM_RESULTS_PER_PAGE)) {
                        $limit = isset($data['GROUP_LIMIT']) ?
                            $data['GROUP_LIMIT']:  0;
                    ?>
                        <tr>
                        <td class="right"><?php
                            if ($limit >= C\NUM_RESULTS_PER_PAGE) {
                                ?><a href='<?= $action_url .
                                    "&amp;arg=editgroup&amp;group_limit=".
                                    ($limit - C\NUM_RESULTS_PER_PAGE) ?>'
                                    >&lt;&lt;</a><?php
                            }
                            ?>
                            </td>
                            <td colspan="2" class="center">
                                <input class="very-narrow-field center"
                                    name="user_filter" type="text"
                                    maxlength="<?= C\NAME_LEN ?>"
                                    value='<?= $data['USER_FILTER'] ?>' /><br />
                                <button type="submit" name="change_filter"
                                    value="true"><?=
                                    tl('managegroups_element_filter')
                                    ?></button>
                                </td>
                            <td class="left"><?php
                            if ($data['NUM_USERS_GROUP'] > $limit +
                                C\NUM_RESULTS_PER_PAGE) {
                                ?><a href='<?=$action_url.
                                    "&amp;arg=editgroup&amp;group_limit=".
                                    ($limit + C\NUM_RESULTS_PER_PAGE) ?>'
                                    >&gt;&gt;</a>
                            <?php
                            }
                            ?>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                    <td colspan="4" <?=$center ?>>&nbsp;&nbsp;[<a href='<?=
                        $action_url ?>&amp;arg=inviteusers'><?=
                        tl('managegroups_element_invite')?></a>]&nbsp;&nbsp;
                    </td>
                    </tr>
                    </table>
                </div>
                </td></tr>
            <?php
            }
        }
        ?>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?= tl('managegroups_element_save')
            ?></button></td>
        </tr>
        </table>
        </form>
        <script type="text/javascript">
        function toggleUserCollection(collection_name)
        {
            var collection = elt(collection_name);
            collection.value = (collection.value =='true')
                ? 'false' : 'true';
            elt('group-form').submit();
        }
        </script>
        <?php
    }
    /**
     * Draws form used to invite users to the current group
     * @param array $data from the admin controller with a
     *     'CURRENT_GROUP' field providing information about the
     *     current group as well as info about the current CSRF_TOKEN
     */
    public function renderInviteUsersForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=manageGroups&amp;arg=editgroup&amp;group_id=".
            $data['CURRENT_GROUP']['id'];
        ?>
        <div class='float-opposite'><a href='<?= $base_url ?>'><?=
            tl('managegroups_element_group_info') ?></a></div>
        <h2><?= tl('managegroups_element_invite_users_group') ?></h2>
        <form id="group-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="<?=
            $data['FORM_TYPE']?>" />
        <input type="hidden" name="group_id" value="<?=
            $data['CURRENT_GROUP']['id'] ?>" />
        <div>
        <b><label for="group-name"><?=
            tl('managegroups_element_groupname') ?></label>:</b>
            <input type="text" id="group-name"
                name="name"  maxlength="<?= C\SHORT_TITLE_LEN ?>"
                value="<?= $data['CURRENT_GROUP']['name'] ?>"
                class="narrow-field" disabled="disabled" />
        </div>
        <div>
        <b><label for="users-names"><?=
            tl('managegroups_element_usernames') ?></label></b>
        </div>
        <?php $center = (!C\MOBILE) ? 'class="center"' : ""; ?>
        <div <?= $center ?>>
        <textarea class="short-text-area" id='users-names'
            name='users_names'></textarea>
        <button class="button-box"
            type="submit"><?=tl('managegroups_element_invite')
            ?></button>
        </form>
        </div>
        <?php
    }
    /**
     * Draws the form used to change the owner of a group
     * @param array $data from the admin controller with a
     *     'CURRENT_GROUP' field providing information about the
     *     current group as well as info about the current CSRF_TOKEN
     */
    public function renderChangeOwnerForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=manageGroups";
        ?>
        <div class='float-opposite'><a href='<?=$base_url ?>'><?=
            tl('managegroups_element_addgroup_form') ?></a></div>
        <h2><?= tl('managegroups_element_transfer_group_owner') ?></h2>
        <form id="group-form" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="<?=$data['FORM_TYPE'] ?>" />
        <input type="hidden" name="group_id" value="<?=
            $data['CURRENT_GROUP']['id'] ?>" />
        <table class="name-table">
        <tr>
            <th class="table-label"><label for="group-name"><?=
                tl('managegroups_element_groupname')?></label>:</th>
            <td><input type="text" id="group-name"
                name="name"  maxlength="<?= C\SHORT_TITLE_LEN ?>"
                value="<?= $data['CURRENT_GROUP']['name'] ?>"
                class="narrow-field" disabled="disabled" /></td>
        </tr>
        <tr>
            <th class="table-label"><label for="new-owner"><?=
                tl('managegroups_element_new_group_owner') ?></label>:</th>
            <td><input type="text"  id='new-owner'
                name='new_owner' maxlength="<?= C\NAME_LEN ?>"
                class="narrow-field" /></td>
        </tr>
        <tr>
            <th>&nbsp;</th><td>&nbsp;<button class="button-box"
                type="submit"><?=tl('managegroups_element_change_owner')
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }
    /**
     * Draws the search for groups forms
     *
     * @param array $data consists of values of role fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageGroups";
        $view = $this->view;
        if (isset($data['browse'])) {
            $title = tl('managegroups_element_discover_groups');
            $title .= "&nbsp;" . $view->helper("helpbutton")->render(
            "Discover Groups", $data[C\CSRF_TOKEN]);
        } else {
            $title = tl('managegroups_element_search_group');
        }
        $return_form_name = tl('managegroups_element_addgroup_form');
        $fields = [
            tl('managegroups_element_groupname') => "name",
            tl('managegroups_element_groupowner') => "owner",
            tl('managegroups_element_registertype') =>
                ["register", $data['EQUAL_COMPARISON_TYPES']],
            tl('managegroups_element_memberaccess') =>
                ["access", $data['EQUAL_COMPARISON_TYPES']],
            tl('managegroups_element_post_lifetime') =>
                ["lifetime", $data['EQUAL_COMPARISON_TYPES']]
        ];
        $dropdowns = [
            "register" => $data['REGISTER_CODES'],
            "access" => $data['ACCESS_CODES'],
            "voting" => $data['VOTING_CODES'],
            "lifetime" => $data['POST_LIFETIMES']
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $return_form_name, $fields, $dropdowns);
    }
}
