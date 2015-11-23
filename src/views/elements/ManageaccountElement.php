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
 * Element responsible for displaying the user account features
 * that someone can modify for their own SeekQuarry/Yioop account.
 *
 * @author Chris Pollett
 */
class ManageaccountElement extends Element
{
    /**
     * Draws a view with a summary of a user's account together with
     * a form for updating user info such as password as well as with
     * useful links for groups, etc
     *
     * @param array $data anti-CSRF token
     */
    public function render($data)
    {
        $token = C\CSRF_TOKEN . "=". $data[C\CSRF_TOKEN];
        $set_url = htmlentities(B\controllerUrl('settings', true));
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $settings_url = "$set_url$token&amp;return=manageAccount";
        $feed_url =  htmlentities(B\feedsUrl("", "",
            true, "admin")). "$token";
        $group_url = "{$admin_url}a=manageGroups&amp;$token";
        $mix_url = "{$admin_url}a=mixCrawls&amp;$token";
        $crawls_url = "{$admin_url}a=manageCrawls&amp;$token";
        $base_url = "{$admin_url}a=manageAccount&amp;$token";
        $edit_or_no_url = $base_url .(
            (isset($data['EDIT_USER'])) ? "&amp;edit=false":"&amp;edit=true");
        $edit_or_no_text = tl('manageaccount_element_edit_or_no_text');
        $edit_or_no_img = C\BASE_URL . ((isset($data['EDIT_USER'])) ?
            "resources/unlocked.png" : "resources/locked.png");
        $password_or_no_url = $base_url .(
            (isset($data['EDIT_PASSWORD'])) ? "&amp;edit_pass=false":
            "&amp;edit_pass=true");
        $disabled = (isset($data['EDIT_USER'])) ? "" : "disabled='disabled'";
        ?>
        <div class="current-activity">
            <h2><?= tl('manageaccount_element_welcome',
                $data['USERNAME']) ?></h2>
            <p><?= tl('manageaccount_element_what_can_do') ?></p>
            <h2><?=tl('manageaccount_element_account_details') ?> <small><a
                href="<?=$edit_or_no_url ?>"
                style="position:relative; top:3px;" ><img src="<?=
                $edit_or_no_img?>" title='<?=$edit_or_no_text ?>' /></a>
                </small></h2>
            <?php
            if (isset($data['EDIT_PASSWORD']) &&
                C\AUTHENTICATION_MODE == C\ZKP_AUTHENTICATION) { ?>
                <form method="post"
                    onsubmit="registration('new-password','retype-password',
                    'fiat-shamir-modulus')">
                <input type="hidden" name="fiat_shamir_modulus"
                    id="fiat-shamir-modulus"
                    value="<?= $data['FIAT_SHAMIR_MODULUS'] ?>"/>
                <?php
            } else { ?>
                <form id="changeUserForm" method="post"
                    autocomplete="off" enctype="multipart/form-data">
            <?php
            }?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="manageAccount" />
            <input type="hidden" name="arg" value="updateuser" />

            <table class="name-table">
            <tr>
            <td rowspan="8" class="user-icon-td" ><img class='user-icon'
                id='current-icon'
                src="<?= $data['USER']['USER_ICON'] ?>" alt="<?=
                    tl('manageaccounts_element_icon') ?>" /><?php
                if (isset($data['EDIT_USER'])) {
                    ?>
                    <?php
                    $this->view->helper("fileupload")->render('current-icon',
                        'user_icon', 'user-icon',  C\THUMB_SIZE, 'image',
                        ['image/png', 'image/gif', 'image/jpeg']);
                }
                ?></td>
            <th class="table-label"><label for="user-name"><?=
                tl('manageusers_element_username') ?>:</label></th>
                <td><input type="text" id="user-name"
                    name="user_name"  maxlength="<?= C\NAME_LEN ?>"
                    value="<?= $data['USER']['USER_NAME'] ?>"
                    class="narrow-field" disabled="disabled" /></td>
                    </tr>
            <tr><th class="table-label"><label for="first-name"><?php
                    e(tl('manageusers_element_firstname')); ?>:</label></th>
                <td><input type="text" id="first-name"
                    name="FIRST_NAME"  maxlength="<?= C\NAME_LEN?>"
                    value="<?php e($data['USER']['FIRST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="last-name"><?php
                    e(tl('manageusers_element_lastname')); ?>:</label></th>
                <td><input type="text" id="last-name"
                    name="LAST_NAME"  maxlength="<?= C\NAME_LEN ?>"
                    value="<?php e($data['USER']['LAST_NAME']); ?>"
                    class="narrow-field" <?php e($disabled);?> /></td></tr>
            <tr><th class="table-label"><label for="e-mail"><?php
                    e(tl('manageusers_element_email')); ?>:</label></th>
                <td><input type="email" id="e-mail"
                    name="EMAIL"  maxlength="<?= C\LONG_NAME_LEN ?>"
                    <?php e($disabled);?>
                    value="<?php e($data['USER']['EMAIL']); ?>"
                    class="narrow-field"/></td></tr>
            <?php
            if (isset($data['EDIT_USER'])) {
                if(!empty($data['yioop_advertisement'])) { ?>
                    <tr><th class="table-label"><label for="is_advertiser"><?php
                    e(tl('manageaccount_element_is_advertiser'));
                    ?></label></th>
                    <td><input type="checkbox" id="is_advertiser"
                        name="IS_ADVERTISER" value="true"
                        <?php if(isset($data['USER']['IS_ADVERTISER'])) {
                            if($data['USER']['IS_ADVERTISER'] == true) {
                                e("checked='checked'");
                            }
                        }?>/>
                    </td></tr><?php
                } ?>
                <tr><th class="table-label"><label for="password"><a href="<?php
                e($password_or_no_url);?>"><?php
                e(tl('manageaccount_element_password'))?></a></label></th>
                <td><input type="password" id="password"
                    name="password"  maxlength="<?= C\LONG_NAME_LEN
                    ?>" class="narrow-field"/>
                </td></tr>
                <?php if (isset($data['EDIT_PASSWORD'])) { ?>
                <tr><th class="table-label"><label for="new-password"><?php
                    e(tl('manageaccount_element_new_password'))?></label></th>
                    <td><input type="password" id="new-password"
                        name="new_password"  maxlength="<?=
                        LONG_NAME_LEN?>" class="narrow-field"/>
                    </td></tr>
                <tr><th class="table-label"><label for="retype-password"><?php
                    e(tl('manageaccount_element_retype_password'));
                    ?></label></th>
                    <td><input type="password" id="retype-password"
                        name="retype_password"  maxlength="<?=
                        C\LONG_NAME_LEN?>" class="narrow-field" />
                    </td></tr>
                <?php
                }
                ?>
                <tr><td></td>
                <td class="center"><button
                    class="button-box" type="submit"><?php
                    e(tl('manageaccount_element_save')); ?></button></td></tr>
                <?php
            } ?>
            </table>
            </form>
            <p>[<a href="<?php e($settings_url); ?>"><?php
                e(tl('manageaccount_element_search_lang_settings')); ?></a>]</p>
            <?php
            if (isset($data['CRAWL_MANAGER']) && $data['CRAWL_MANAGER']) {
                ?>
                <h2><?php
                e(tl('manageaccount_element_crawl_and_index')); ?></h2>
                <p><?php e(tl('manageaccount_element_crawl_info')); ?></p>
                <p><?php e(tl('manageaccount_element_num_crawls',
                    $data["CRAWLS_RUNNING"], $data["NUM_CLOSED_CRAWLS"]));?></p>
                <p>[<a href="<?php e($crawls_url); ?>"><?php
                    e(tl('manageaccount_element_manage_crawls'));
                    ?></a>]</p>
                <?php
            }
            ?>
            <h2><?=tl('manageaccount_element_groups_and_feeds')?></h2>
            <p><?= tl('manageaccount_element_group_info') ?></p>
            <p><?php if ($data['NUM_GROUPS'] > 1 || $data['NUM_GROUPS'] == 0) {
                e(tl('manageaccount_element_num_groups',
                    $data['NUM_GROUPS']));
            } else {
                e(tl('manageaccount_element_num_group',
                    $data['NUM_GROUPS']));
            }?></p>
            <?php
            foreach ($data['GROUPS'] as $group) {
                ?>
                <div class="access-result">
                    <div><b><a href="<?=htmlentities(B\feedsUrl("group",
                    $group['GROUP_ID'], true, "admin")) . $token ?>"
                    rel="nofollow"><?=$group['GROUP_NAME']
                    ?></a> [<a href="<?=htmlentities(B\wikiUrl("Main", true,
                        "admin", $group['GROUP_ID'])) .
                        $token ?>"><?=
                        tl('manageaccount_element_group_wiki')?></a>] (<?=
                        tl('manageaccount_element_group_stats',
                        $group['NUM_POSTS'], $group['NUM_THREADS']) ?>)</b>
                    </div>
                    <div class="slight-pad">
                    <b><?=tl('manageaccount_element_last_post') ?></b>
                    <a href="<?=htmlentities(B\feedsUrl("thread",
                    $group['THREAD_ID'], true, "admin")) . $token ?>"
                    ><?= $group['ITEM_TITLE'] ?></a>
                    </div>
                </div>
                <?php
            }
            ?>
            <p>[<a href="<?= $group_url ?>"><?=
                tl('manageaccount_element_manage_all_groups')
                ?></a>] [<a href="<?=$feed_url ?>"><?=
                tl('manageaccount_element_go_to_group_feed') ?></a>]</p>
            <h2><?=tl('manageaccount_element_crawl_mixes') ?></h2>
            <p><?=tl('manageaccount_element_mixes_info') ?></p>
            <p><?php if ($data['NUM_MIXES'] > 1 || $data['NUM_MIXES'] == 0) {
                e(tl('manageaccount_element_num_mixes',
                    $data['NUM_MIXES']));
            } else {
                e(tl('manageaccount_element_num_mix',
                    $data['NUM_MIXES']));
            }?></p>
            <p>[<a href="<?= $mix_url ?>"><?=
                tl('manageaccount_element_manage_mixes')
                ?></a>]</p>
        </div>
        <?php
    }
}
