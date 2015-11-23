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
 * Element responsible for drawing the activity screen for User manipulation
 * in the AdminView.
 *
 * @author Chris Pollett
 */
class ManageusersElement extends Element
{
    /**
     * Draws a screen in which an admin can add users, delete users,
     * and manipulate user roles.
     *
     * @param array $data info about current users and current roles, CSRF token
     */
    public function render($data)
    {
    ?>
        <div class="current-activity">
        <?php
        if ($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderUserForm($data);
        }
        $data['TABLE_TITLE'] = tl('manageusers_element_users');
        $data['ACTIVITY'] = 'manageUsers';
        $data['VIEW'] = $this->view;
        $this->view->helper("pagingtable")->render($data);

        $default_accounts = [C\ROOT_ID, C\PUBLIC_USER_ID];
        ?>
        <table class="role-table">
            <tr>
                <th><?= tl('manageusers_element_username') ?></th>
                <?php if (!C\MOBILE) { ?>
                <th><?= tl('manageusers_element_firstname')?></th>
                <th><?= tl('manageusers_element_lastname') ?></th>
                <th><?= tl('manageusers_element_email') ?></th>
                <th><?= tl('manageusers_element_groups') ?></th>
                <?php } ?>
                <th><?= tl('manageusers_element_status') ?></th>
                <th colspan='2'><?= tl('manageusers_element_actions') ?></th>
            </tr>
        <?php
            $base_url = htmlentities(B\controllerUrl('admin', true)) .
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]. "&amp;a=manageUsers";
            if (isset($data['START_ROW'])) {
                $base_url .= "&amp;start_row=".$data['START_ROW'].
                    "&amp;end_row=".$data['END_ROW'].
                    "&amp;num_show=".$data['NUM_SHOW'];
            }
            $delete_url = $base_url . "&amp;arg=deleteuser&amp;";
            $edit_url = $base_url . "&amp;arg=edituser&amp;";
            $mobile_columns = ['USER_NAME', 'STATUS'];
            $stretch = (C\MOBILE) ? 1 :2;
            $out_columns = ['USER_NAME', 'FIRST_NAME', 'LAST_NAME',
                'EMAIL', 'NUM_GROUPS', 'STATUS'];
            foreach ($data['USERS'] as $user) {
                echo "<tr>";
                foreach ($out_columns as $colname) {
                    $user_column = $user[$colname];
                    if ($colname == "USER_ID" || (
                        C\MOBILE && !in_array($colname, $mobile_columns))) {
                        continue;
                    }
                    if (strlen($user_column) > $stretch * C\NAME_TRUNCATE_LEN) {
                        $user_column = wordwrap($user_column,
                            $stretch * C\NAME_TRUNCATE_LEN, "\n", true);
                    }
                    if (strcmp($colname,"STATUS") == 0) {
                        ?><td>
                        <?php
                        if (in_array($user['USER_ID'], $default_accounts)) {
                            e("<span class='gray'>&nbsp;&nbsp;".
                                $data['STATUS_CODES'][$user['STATUS']].
                                "</span>");
                        } else {
                        ?>
                        <form  method="get">
                        <input type="hidden" name="c" value="admin" />
                        <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                            value="<?= $data[C\CSRF_TOKEN] ?>" />
                        <input type="hidden" name="a" value="manageUsers" />
                        <input type="hidden" name="arg" value="updatestatus" />
                        <input type="hidden" name="user_name" value="<?=
                            $user['USER_NAME'] ?>" />
                        <?php
                        $this->view->helper("options")->render(
                            "update-userstatus-{$user['USER_NAME']}",
                            "userstatus", $data['STATUS_CODES'],
                            $user['STATUS'], true);
                        ?>
                        </form>
                        <?php
                        }
                        ?>
                        </td>
                        <?php
                    } else {
                        echo "<td>$user_column</td>";
                    }
                }
                ?>
                <td><?php
                    if ($user['USER_ID'] == C\PUBLIC_USER_ID) {
                        e('<span class="gray">'.
                            tl('manageusers_element_edit').'</span>');
                    } else {?>
                        <a href="<?php e($edit_url . 'user_name='.
                        $user['USER_NAME']); ?>"><?=
                        tl('manageusers_element_edit') ?></a></td>
                        <?php
                    } ?>
                <td><?php
                    if (in_array($user['USER_ID'], $default_accounts)) {
                        e('<span class="gray">'.
                            tl('manageusers_element_delete').'</span>');
                    } else {
                    ?>
                        <a onclick='javascript:return confirm("<?=
                        tl('confirm_delete_operation') ?>");'
                        href="<?= $delete_url . 'user_name='.
                        $user['USER_NAME'] ?>"><?php
                        e(tl('manageusers_element_delete'));
                    }?></a></td>
                </tr>
            <?php
            }
        ?>
        </table>
        <script type="text/javascript">
        function submitViewUserRole()
        {
            elt('viewUserRoleForm').submit();
        }
        </script>
        </div>
        <?php
    }
    /**
     * Draws the add user and edit user forms
     *
     * @param array $data consists of values of user fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderUserForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url .
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]. "&amp;a=manageUsers&amp;";
        $visibles = "visible_roles=".$data['visible_roles'].
            "&amp;visible_groups=".$data['visible_groups'];
        $limits = isset($data['ROLE_LIMIT']) ?
            "role_limit=".$data['ROLE_LIMIT']: "role_limit=0";
        $limits .= isset($data['GROUP_LIMIT']) ?
            "&amp;group_limit=".$data['GROUP_LIMIT']: "&amp;group_limit=0";
        $filters = isset($data['ROLE_FILTER']) ?
            "role_filter=".$data['ROLE_FILTER'] : "";
        $f_amp = isset($data['ROLE_FILTER']) ? "&amp;" : "";
        $filters .= isset($data['GROUP_FILTER']) ?
            "$f_amp;group_filter=".$data['GROUP_FILTER'] : "";
        $base_url .= $visibles;
        $edituser = ($data['FORM_TYPE'] == "edituser") ? true: false;
        if ($edituser) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('manageusers_element_adduser_form')."</a></div>");
            e("<h2>".tl('manageusers_element_user_info'). "</h2>");
        } else {
            e("<h2>".tl('manageusers_element_add_user'). "</h2>");
        }
        ?>
       <?php if ($data['AUTHENTICATION_MODE'] == C\ZKP_AUTHENTICATION) { ?>
                <form method="post"
                    onsubmit="registration('pass-word','retype-password',
                    'fiat-shamir-modulus')">
                <input type="hidden" name="fiat_shamir_modulus"
                    id="fiat-shamir-modulus"
                    value="<?= $data['FIAT_SHAMIR_MODULUS'] ?>"/>
       <?php } else { ?>
               <form id="user-form" method="post" autocomplete="off">
       <?php }?>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageUsers" />
        <input type="hidden" name="arg" value="<?=
            $data['FORM_TYPE'] ?>" />
        <input type="hidden" id="visible-roles" name="visible_roles"
            value="<?= $data['visible_roles'] ?>" />
        <input type="hidden" id="visible-groups" name="visible_groups"
            value="<?= $data['visible_groups'] ?>" />
        <table class="name-table">
        <tr><th class="table-label"><label for="user-name"><?= 
            tl('manageusers_element_username')?>:</label></th>
            <td><input type="text" id="user-name"
                name="user_name"  maxlength="<?= C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['user_name'] ?>"
                class="narrow-field" <?php
                if ($edituser) {
                    e(' disabled="disabled" ');
                }
                ?> /></td></tr>
        <tr><th class="table-label"><label for="first-name"><?=
            tl('manageusers_element_firstname') ?>:</label></th>
            <td><input type="text" id="first-name"
                name="first_name"  maxlength="<?= C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['first_name'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="last-name"><?=
            tl('manageusers_element_lastname') ?>:</label></th>
            <td><input type="text" id="last-name"
                name="last_name"  maxlength="<?=C\NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['last_name'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="e-mail"><?=
            tl('manageusers_element_email') ?>:</label></th>
            <td><input type="email" id="e-mail"
                name="email"  maxlength="<?= C\LONG_NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['email'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label
                for="update-userstatus-currentuser"><?=
                tl('manageusers_element_status') ?>:</label></th>
            <td><?php
                if ($data['CURRENT_USER']['user_name'] == 'root') {
                    e("<div class='light-gray-box'><span class='gray'>".
                        $data['STATUS_CODES'][$data['CURRENT_USER']['status']].
                        "</span></div><input type='hidden' name='status' ".
                        "value='".$data['CURRENT_USER']['status']."' />");
                } else {
                    $this->view->helper("options")->render(
                        "update-userstatus-currentuser",
                        "status", $data['STATUS_CODES'],
                        $data['CURRENT_USER']['status']);
                } ?></td></tr>
        <?php
        if ($edituser) {
        ?>
            <tr><th class="table-label" style="vertical-align:top"><?=
            tl('manageusers_element_roles') ?>:</th>
                <td><div class='light-gray-box'>
                <div class="center">
                    [<a href="javascript:toggleUserCollection('visible-roles');"
                        ><?= tl('manageusers_element_num_roles',
                        $data['NUM_USER_ROLES'])?></a>]
                </div>
                <?php
                if ($data['visible_roles'] == 'true') {
                ?>
                    <div id="user-roles">
                    <table><?php
                    foreach ($data['USER_ROLES'] as $role_array) {
                        e("<tr><td><b>".
                            $role_array['ROLE_NAME'].
                            "</b></td>");
                        if ($data['CURRENT_USER']['user_name'] == 'root' &&
                            $role_array['ROLE_NAME'] == 'Admin') {
                            e("<td><span class='gray'>".
                                tl('manageusers_element_delete').
                                "</span></td>");
                        } else {
                            e("<td><a href='{$admin_url}a=manageUsers".
                                "&amp;arg=deleteuserrole&amp;selectrole=".
                                $role_array['ROLE_ID']);
                            e("&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                                "&amp;$visibles&amp;$limits'>".
                                tl('manageusers_element_delete').
                                "</a></td>");
                        }
                        e("</tr>");
                    }
                    ?>
                    </table>
                    <?php
                    if ($data['ROLE_FILTER'] != "" ||
                        (isset($data['NUM_USER_ROLES']) &&
                        $data['NUM_USER_ROLES'] > C\NUM_RESULTS_PER_PAGE)) {
                        $limit = isset($data['ROLE_LIMIT']) ?
                            $data['ROLE_LIMIT']: 0;
                    ?>
                        <div class="center">
                        <?php
                            $action_url = $base_url. "&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;role_filter=".$data['ROLE_FILTER'].
                                "&amp;group_filter=".$data['GROUP_FILTER'];
                            if ($limit >= C\NUM_RESULTS_PER_PAGE ) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;role_limit=".
                                ($limit - C\NUM_RESULTS_PER_PAGE) ?>'
                                >&lt;&lt;</a><?php
                            }
                            ?>
                        <input class="very-narrow-field center"
                            name="role_filter" type="text" maxlength="<?=
                            C\NAME_LEN ?>" value='<?=$data['ROLE_FILTER'] ?>' />
                            <?php
                            if ($data['NUM_USER_ROLES'] > $limit +
                                C\NUM_RESULTS_PER_PAGE) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;role_limit=".
                                ($limit + C\NUM_RESULTS_PER_PAGE) ?>'
                                >&gt;&gt;</a>
                            <?php
                            }
                        ?><br />
                        <button type="submit" name="change_filter"
                            value="role"><?=tl('manageusers_element_filter')
                            ?></button>
                        <br />&nbsp;
                    </div>
                    <?php
                    }
                    ?>
                    <div class="center" >
                    <input type="text" name="selectrole" id='select-role'
                        class="very-narrow-field" />
                    <button type="submit"
                        class="button-box">
                        <label for='select-role'><?=
                        tl('manageusers_element_add_role') ?></label>
                    </button>
                    </div>
                    </div>
                <?php
                }
                ?>
                </div>
                </td></tr>
            <tr><th class="table-label" style="vertical-align:top"><?=
                tl('manageusers_element_groups') ?>:</th>
                <td><div class='light-gray-box'>
                <div class="center">
                    [<a href="javascript:toggleUserCollection('visible-groups')"
                        ><?= tl('manageusers_element_num_groups',
                        $data['NUM_USER_GROUPS'])?></a>]
                </div>
                <?php
                if ($data['visible_groups'] == 'true') {
                ?>
                    <div id="user-groups">
                    <table><?php
                    foreach ($data['USER_GROUPS'] as $group_array) {
                        e("<tr><td><b>".
                            $group_array['GROUP_NAME'].
                            "</b></td>");
                        e("<td class='gray'>".
                            $data["MEMBERSHIP_CODES"][$group_array['STATUS']].
                            "</td>");
                        e("<td><a href='{$admin_url}a=manageUsers".
                            "&amp;arg=deleteusergroup&amp;selectgroup=".
                            $group_array['GROUP_ID']);
                        e("&amp;user_name=".$data['CURRENT_USER']['user_name'].
                            "&amp;".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                            "&amp;$visibles&amp;$limits'>".
                            tl('manageusers_element_delete')."</a></td>");
                    }
                    ?>
                    </table>
                    <?php
                    if ($data['GROUP_FILTER'] != "" ||
                        (isset($data['NUM_USER_GROUPS']) &&
                        $data['NUM_USER_GROUPS'] > C\NUM_RESULTS_PER_PAGE)) {
                        $limit = isset($data['GROUP_LIMIT']) ?
                            $data['GROUP_LIMIT']: 0;
                    ?>
                        <div class="center">
                        <?php
                            $action_url = $base_url. "&amp;user_name=".
                                $data['CURRENT_USER']['user_name'].
                                "&amp;role_filter=".$data['ROLE_FILTER'].
                                "&amp;group_filter=".$data['GROUP_FILTER'];
                            if ($limit >= C\NUM_RESULTS_PER_PAGE ) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;group_limit=".
                                ($limit - C\NUM_RESULTS_PER_PAGE) ?>'
                                >&lt;&lt;</a><?php
                            }
                            ?>
                        <input class="very-narrow-field center"
                            name="group_filter" type="text" maxlength="<?=
                            C\SHORT_TITLE_LEN ?>" value='<?=
                            $data['GROUP_FILTER'] ?>' />
                        <?php
                            if ($data['NUM_USER_GROUPS'] > $limit +
                                C\NUM_RESULTS_PER_PAGE) {
                                ?><a href='<?=
                                "$action_url&amp;arg=edituser&amp;group_limit=".
                                ($limit + C\NUM_RESULTS_PER_PAGE)
                                ?>'>&gt;&gt;</a>
                            <?php
                            }
                        ?><br />
                        <button type="submit" name="change_filter"
                            value="group"><?= tl('manageusers_element_filter')
                        ?></button><br />&nbsp;
                    </div>
                    <?php
                    }
                    ?>
                    <div class="center" >
                    <input type="text" name="selectgroup" id='select-group'
                        class="very-narrow-field" />
                    <button type="submit"
                        class="button-box">
                        <label for='select-group'><?=
                        tl('manageusers_element_add_group')
                        ?></label></button>
                    </div>
                    </div>
                <?php
                }
                ?>
                </div>
                </td></tr>
        <?php
        }
        ?>
        <tr><th class="table-label"><label for="pass-word"><?=
            tl('manageusers_element_password') ?>:</label></th>
            <td><input type="password" id="pass-word"
                name="password" maxlength="<?= C\LONG_NAME_LEN?>"
                value="<?= $data['CURRENT_USER']['password'] ?>"
                class="narrow-field"/></td></tr>
        <tr><th class="table-label"><label for="retype-password"><?=
            tl('manageusers_element_retype_password') ?>:</label></th>
            <td><input type="password" id="retype-password"
                name="retypepassword" maxlength="<?= C\LONG_NAME_LEN ?>"
                value="<?= $data['CURRENT_USER']['password'] ?>"
                class="narrow-field"/></td></tr>

        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?= tl('manageusers_element_save')
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
            elt('user-form').submit();
        }
        </script>
        <?php
    }
    /**
     * Draws the search for users forms
     *
     * @param array $data consists of values of user fields set
     *     so far as well as values of the drops downs on the form
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageUsers";
        $view = $this->view;
        $title = tl('manageusers_element_search_user');
        $return_form_name = tl('manageusers_element_adduser_form');
        $fields = [
            tl('manageusers_element_username') => "user",
            tl('manageusers_element_firstname') => "first",
            tl('manageusers_element_lastname') => "last",
            tl('manageusers_element_email') => "email",
            tl('manageusers_element_status') =>
                ["status", $data['EQUAL_COMPARISON_TYPES']],
        ];
        $postfix = "name";
        $dropdowns = [
            "status" => $data['STATUS_CODES']
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $return_form_name, $fields, $dropdowns,
            $postfix);
    }
}
