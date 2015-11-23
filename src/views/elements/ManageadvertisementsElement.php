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
 * @author Pushkar Umaranikar
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\Library as L;

/**
 * Element responsible for displaying advertisements information
 * that someone can create, view, and modify for their own
 * SeekQuarry/Yioop account.
 *
 * @author Pushkar Umaranikar
 */
class ManageadvertisementsElement extends Element
{
    /**
     * Draws create advertisement form and existing campaigns
     * @param array $data
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
            <?php
            if ($data['FORM_TYPE'] == "search") {
                $this->renderSearchForm($data);
            } else {
                $this->renderAdvertisementForm($data);
            }
            $data['TABLE_TITLE'] = tl('manageadvertisements_element_list');
            $data['ACTIVITY'] = 'manageAdvertisements';
            $data['VIEW'] = $this->view;
            $this->view->helper("pagingtable")->render($data);
            ?>
            <table class="role-table">
                <tr>
                    <th><?= tl('manageadvertisements_element_adname')?>
                    </th>
                    <?php
                    if (!C\MOBILE) { 
                        if($data['HAS_ADMIN_ROLE']) { ?>
                            <th><?=tl('manageadvertisements_element_username')
                            ?></th><?php
                        } ?>
                        <th><?=tl('manageadvertisements_element_keywords')
                        ?></th>
                        <th><?=tl('manageadvertisements_element_budget')?></th>
                        <th><?=tl('manageadvertisements_element_dates')?></th>
                        <th><?=tl('manageadvertisements_element_viewclicks')
                        ?></th><?php
                    } ?>
                    <th><?=tl('manageadvertisements_element_status') ?></th>
                    <th colspan='2'><?=
                        tl('manageadvertisements_element_actions')?></th>
                </tr>
                <?php
                $admin_url = htmlentities(B\controllerUrl('admin', true)) . 
                    C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN];
                $base_url = $admin_url . "&amp;a=manageAdvertisements";
                $user_url = $admin_url . "&amp;a=manageUsers&amp;arg=edituser".
                    "&amp;user_name=";
                if (isset($data['START_ROW'])) {
                    $base_url .= "&amp;start_row=" . $data['START_ROW'] .
                        "&amp;end_row=" . $data['END_ROW'] .
                        "&amp;num_show=" . $data['NUM_SHOW'];
                }
                $status_url = $base_url . "&amp;arg=changestatus&amp;";
                $edit_url = $base_url . "&amp;arg=editadvertisement&amp;";
                $mobile_columns = ['NAME', 'STATUS'];
                $skip_columns = ["DESTINATION"];
                $stretch = (C\MOBILE) ? 1 : 2;
                foreach ($data['ADVERTISEMENTS'] as $ad) {
                    $is_active = ($ad['STATUS'] ==
                        C\ADVERTISEMENT_ACTIVE_STATUS &&
                        $ad['USER_ID']==$_SESSION['USER_ID']) ? true : false;
                    $is_active_admin = ($ad['STATUS'] ==
                        C\ADVERTISEMENT_ACTIVE_STATUS &&
                        $data['HAS_ADMIN_ROLE']) ? true : false;
                    $time = strtotime(date(C\AD_DATE_FORMAT, time()));
                    $time_okay = $time <= strtotime($ad['END_DATE']);
                    $ad_trans = [
                        C\ADVERTISEMENT_ACTIVE_STATUS =>
                            tl('manageadvertisements_element_active'),
                        C\ADVERTISEMENT_DEACTIVATED_STATUS =>
                            tl('manageadvertisements_element_deactivated'),
                        C\ADVERTISEMENT_SUSPENDED_STATUS =>
                            tl('manageadvertisements_element_suspended'),
                        C\ADVERTISEMENT_COMPLETED_STATUS =>
                            tl('manageadvertisements_element_completed'),
                    ];
                    $ad['STATUS'] =
                        ($ad['STATUS'] == C\ADVERTISEMENT_ACTIVE_STATUS &&
                        !$time_okay) ? C\ADVERTISEMENT_COMPLETED_STATUS :
                        $ad['STATUS'];
                    ?>
                    <tr>
                    <td><a href='<?=$ad['DESTINATION'] ?>'><?=
                        $ad['NAME'] ?></a></td><?php
                    if (!C\MOBILE) { 
                        if($data['HAS_ADMIN_ROLE']) { ?>
                            <td><a href="<?=$user_url . $ad['USER_NAME']?>"><?=
                            $ad['USER_NAME'] ?></a></td><?php
                        }
                        ?>
                        <td><?=$ad['KEYWORDS'] ?></td>
                        <td><?=$ad['BUDGET'] ?></td>
                        <td><?=$ad['START_DATE'] ?> / <?=$ad['END_DATE']?></td>
                        <td><?=$ad['IMPRESSIONS'] ?> / <?=$ad['CLICKS']?></td>
                        <?php
                    }
                    ?>
                    <td><?=$ad_trans[$ad['STATUS']] ?></td>
                    <td><?php
                    if(($is_active || $is_active_admin) && $time_okay) { ?>
                        <a href="<?= $edit_url . 'id=' . $ad['ID'] ?>"><?=
                        tl('manageadvertisements_element_edit') ?></a><?php
                    } else {?>
                        <span class='gray'><?=
                            tl('manageadvertisements_element_edit')
                            ?></span>
                        <?php
                    }?>
                    </td>
                    <td><?php
                    if($time_okay) {
                        if ($is_active) {
                            ?>
                            <a onclick='return confirm("<?=
                                tl('manageadvertisements_element_deconfirm')
                                ?>");' href="<?= $status_url . 'id=' .
                                $ad['ID'].'&amp;'. "status=" .
                                C\ADVERTISEMENT_DEACTIVATED_STATUS ?>" ><?=
                                tl('manageadvertisements_element_deactivate')
                                ?></a><?php
                        } else if ($is_active_admin) {
                            ?>
                            <a onclick='return confirm("<?=
                                tl('manageadvertisements_element_deconfirm')
                                ?>");' href="<?= $status_url . 'id=' .
                                $ad['ID'].'&amp;'. "status=" .
                                C\ADVERTISEMENT_SUSPENDED_STATUS ?>" ><?=
                                tl('manageadvertisements_element_suspend')
                                ?></a><?php
                        } else if ($data['HAS_ADMIN_ROLE'] || $ad['STATUS'] !=
                            C\ADVERTISEMENT_SUSPENDED_STATUS) {
                            ?>
                            <a onclick='return confirm("<?=
                            tl('manageadvertisements_element_reconfirm') ?>");'
                            href="<?=$status_url . 'id=' .
                            $ad['ID'] . '&amp;'. 'status=' .
                            C\ADVERTISEMENT_ACTIVE_STATUS ?>"><?=
                            tl('manageadvertisements_element_reactivate')
                            ?></a><?php
                        } else { ?>
                            <span class='gray'><?= $ad_trans[$ad['STATUS']]
                            ?></span><?php
                        }
                    } else { ?>
                        <span class='gray'><?=$ad_trans[$ad['STATUS']] ?></span>
                        <?php
                    } ?>
                    </td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </div>
    <?php
}
    /**
     * Draws the form that let's a user create an ad
     *
     * @param array $data previous values for the form as wells as other
     *      data to render the view
     */
    public function renderAdvertisementForm($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true)) . 
            C\CSRF_TOKEN . "=" .$data[C\CSRF_TOKEN];
        $base_url = $admin_url . "&amp;a=manageAdvertisements";
        $user_url = $admin_url . "&amp;a=manageUsers&amp;arg=edituser".
            "&amp;user_name=";
        $paging = "";
        if (isset($data['START_ROW'])) {
            $paging = "&amp;start_row=".$data['START_ROW'].
                "&amp;end_row=".$data['END_ROW'].
                "&amp;num_show=".$data['NUM_SHOW'];
            $base_url .= $paging;
        }
        $edit_advertisement = $data['FORM_TYPE'] == "editadvertisement";
        $preview_script = (!C\MOBILE) ? 'onkeyup="preview(this);"' : "";
        $preview_script2 = (!C\MOBILE) ? 'onkeyup="preview(this,'."'ad-name'".
            ');"' : "";
        if ($edit_advertisement) {
            e("<div class='float-opposite'><a href='$base_url'>".
                tl('manageadvertisement_element_addadvertisement_form')."</a>
                </div>");
            e("<h2>".tl('manageadvertisement_element_ad_info'));
            e("&nbsp;".$this->view->helper("helpbutton")->render(
                "Manage Advertisements", $data[C\CSRF_TOKEN]). "</h2>");
        } else {
            e("<h2>".tl('manageadvertisement_element_purchase_ad'));
            e("&nbsp;".$this->view->helper("helpbutton")->render(
                "Manage Advertisements", $data[C\CSRF_TOKEN]). "</h2>");
        } ?>
        <form id="create-ad-form"  method="post" >
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="manageAdvertisements"/>
            <input type="hidden" name="arg" value="<?=$data['FORM_TYPE']?>" />
            <table>
            <?php
            if($edit_advertisement && $data['HAS_ADMIN_ROLE']) { ?>
                <tr>
                <th class="table-label">
                <label for="ad-name"><?=
                    tl('manageadvertisement_element_ad_user') ?>:
                </label>
                </th>
                <td>[<a href="<?=$user_url . $data['AD_USER_NAME']?>"><?=
                    $data['AD_USER_NAME'] ?></a>]
                </td>
                </tr>
                <?php
            }
            ?>
            <tr>
            <th class="table-label">
            <label for="ad-name"><?=
                tl('manageadvertisement_element_displayname') ?>:
            </label>
            </th>
            <td>
            <input type="text" id="ad-name"
                name="NAME"  maxlength="<?= C\ADVERTISEMENT_NAME_LEN ?>"
                onkeypress="updateCharCountdown('ad-name',
                'ad-name-count')"
                onblur="toggleDisplay('ad-name-count', 'none')"
                onfocus="toggleDisplay('ad-name-count', 'inline')"
                value="<?= isset($data['NAME']) ? $data['NAME'] : ""?>"
                class="narrow-field" <?= $preview_script ?>
                <?php
                if (isset($data['AD_MIN_BID'])) {
                    e(' readonly="readonly"');
                } ?> /> <small class="gray" id="ad-name-count" ></small>
            </td>
            </tr>
            <tr><th class="table-label"><label for="ad-description"><?=
                tl('manageadvertisement_element_text') ?>
            </label>:</th>
            <td>
            <input type="text" id="ad-description" name="DESCRIPTION"
                maxlength="<?= C\ADVERTISEMENT_TEXT_LEN ?>"
                onkeypress="updateCharCountdown('ad-description',
                'ad-desc-count')"
                onblur="toggleDisplay('ad-desc-count', 'none')"
                onfocus="toggleDisplay('ad-desc-count', 'inline')"
                value="<?php
                if(isset($data['DESCRIPTION'])) {
                    e($data['DESCRIPTION']);
                }
                ?>" class="narrow-field" <?php e($preview_script);
                if (isset($data['AD_MIN_BID'])) {
                    e(' readonly="readonly"');
                }?> /> <small class="gray" id="ad-desc-count" ></small>
            </td></tr>
            <tr>
            <th class="table-label"><label for="ad-destination"><?=
                tl('manageadvertisement_element_ad_url') ?></label>:
            </th>
            <td>
            <input type="text" id="ad-destination" name="DESTINATION"
                maxlength="<?= C\MAX_URL_LEN ?>"
                onkeypress="updateCharCountdown('ad-destination',
                'ad-dest-count')"
                onblur="toggleDisplay('ad-dest-count', 'none')"
                onfocus="toggleDisplay('ad-dest-count', 'inline')"
                value="<?php
                if (isset($data['DESTINATION'])) {
                    e($data['DESTINATION']);
                }
                ?>" class="narrow-field" <?php
                e($preview_script2);
                if (isset($data['AD_MIN_BID'])) {
                    e(' readonly="readonly"');
                }
                ?> /> <small class="gray" id="ad-dest-count" ></small>
            </td></tr>
            <tr><th class="table-label"><label for="ad-duration"><?=
                tl('manageeadvertisement_element_ad_duration') ?>:
            </label>
            </th>
            <td>
            <?php
                $attributes = [];
                if ($edit_advertisement || isset($data['AD_MIN_BID'])) {
                    $attributes['disabled'] ="disabled";
                    ?>
                    <input type="hidden" name="DURATION" value="<?=
                        $data["DURATION"] ?>" />
                    <?php
                }
                $this->view->helper('options')->render('duration', 'DURATION',
                    $data['DURATIONS'], $data['DURATION'], false,
                    $attributes);
            ?></td></tr>
            <tr>
            <td></td><td>
            <span class="small green" style="position:relative; top:-7px;"><?=
                tl('manageeadvertisement_element_start_day')
            ?></span>
            </td></tr>
            <tr><th class="table-label">
            <label for="ad-keywords"><?=
                tl('manageeadvertisement_element_keywords') ?>:
            </label></th>
            <td>
            <textarea class="short-text-area-two narrow-field"
                name="KEYWORDS" placeholder="<?=
                tl('manageadvertisement_element_keyword_help')?>" <?php
                if ($edit_advertisement || isset($data['AD_MIN_BID'])) {
                    e(' readonly="readonly"');
                }?> ><?php
                if (isset($data['KEYWORDS'])) {
                    e($data['KEYWORDS']);
                }
            ?></textarea>
            </td>
            </tr>
            <tr>
            <?php
            if (isset($data['AD_MIN_BID'])) {
                ?><th class="table-label"><label for="ad-min-bid"><?=
                    tl('manageadvertisement_element_keyword_bid_amount')
                    ?>:</label></th><td>
                    <input type="text" id='ad-min-bid' name="AD_MIN_BID"
                    value="<?= isset($data['AD_MIN_BID']) ?
                    $data['AD_MIN_BID']: "" ?>"
                    class="narrow-field" disabled="disabled"/></td></tr>
                <tr><th class="table-label"><label for="expensive-word"><?=
                    tl('manageadvertisement_element_expensive_word') ?>
                </label></th>
                <td>
                <input type="text" id="expensive-word"
                    name="EXPENSIVE_WORD"
                    value="<?=isset($data['EXPENSIVE_KEYWORD']) ?
                     $data['EXPENSIVE_KEYWORD']: "" ?>"
                    class="narrow-field" disabled="disabled"/>
                </td></tr>
                <?php
            }
            if (!$edit_advertisement && !isset($data['AD_MIN_BID'])) {
                ?>
                <tr><td></td>
                <td>
                <div class="narrow-field center">
                <input class="button-box" name="CALCULATE" value="<?=
                    tl('manageadvertisement_element_calculate_bid') ?>"
                    type="submit"/></div></td></tr>
                <?php
            }
            if (!$edit_advertisement) {
                ?>
                <tr>
                <td>
                <input type="hidden" id="ad-min-bid"
                    name="AD_MIN_BID" value="<?= isset($data['AD_MIN_BID']) ?
                     $data['AD_MIN_BID']:"" ?>" class="narrow-field" />
                </td>
                </tr>
                <?php
            } else {
                ?>
                <tr>
                <td></td>
                <td class="center">
                <input class="button-box" name="save" value="<?=
                    tl('manageadvertisement_element_update')
                    ?>" type="submit" />
                </td>
                </tr>
                <?php
            }
            if (isset($data['AD_MIN_BID']) && !$edit_advertisement) {
                ?>
                <tr><th class="table-label"><label for="ad-budget"><?=
                    tl('manageadvertisement_element_budget') ?>:
                </label></th>
                <td>
                <input type="text" id="ad-budget"
                    name="BUDGET" value="<?= isset($data['BUDGET']) ?
                    $data['BUDGET']:"";?>" class="narrow-field"
                    <?php if ($edit_advertisement) {
                        e('disabled="disabled"');
                    } ?> />
                </td></tr>
                <tr>
                <td></td>
                <td><div class="narrow-field green small"><?=
                tl('manageadvertisement_element_buy_info',
                    $data['BALANCE'])
                ?> <a href="<?=B\controllerUrl('admin', true)
                    . C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN].
                    "&amp;a=manageCredits" ?>"><?=
                tl('manageadvertisement_element_buy_credits')
                ?></a>.</div></td>
                </tr>
                <tr>
                <td></td>
                <td>
                <input class="button-box" name="EDIT_AD" value="<?=
                    tl('manageadvertisement_element_edit_ad')
                    ?>" type="submit" />
                <input class="button-box" id="purchase"
                    name="PURCHASE" value="<?=
                    tl('manageadvertisement_element_purchase')
                    ?>" type="submit" />
                </td>
                </tr>
                <?php
            } ?>
            </table>
            <?php
            if(C\MOBILE) {
                ?>
               <div class="clear">&nbsp;</div>
                <?php
            }
            ?>
        </form>
        <?php
        if(!C\MOBILE){ ?>
            <div class="ad-preview">
                <p><b><?=tl('manageadvertisement_element_preview')
                ?>:</b></p>
                <p class='start-ad'><img src="<?= C\AD_LOGO ?>" />
                    <a id="ad-name-preview" href="./" class="ad-preview-anchor"
                        target="_blank">
                    </a>
                    <span id="ad-description-preview"></span>
                </p>
            </div>
            <?php
        } ?>
        <script type="text/javascript">
            function preview(obj)
            {
                var preview_str = "-preview";
                if (arguments.length > 1) {
                    var preview_id = arguments[1].concat(preview_str);
                    var prefix = 'http';
                    var protocol = (obj.value.substr(0, prefix.length)
                        !="http") ? "http://" : "";
                    elt(preview_id).href = protocol + obj.value ;
                } else {
                    var preview_id = obj.id.concat(preview_str);
                    elt(preview_id).innerHTML = obj.value;
                }
            }
        </script>
        <?php
    }
    /**
     * Draws search advertisement form
     * @param array $data anti-CSRF token
     */
    public function renderSearchForm($data)
    {
        $controller = "admin";
        $activity = "manageAdvertisements";
        $view = $this->view;
        $title = tl('manageadvertisements_element_search_advertisement');
        $return_form_name =
            tl('manageadvertisement_element_addadvertisement_form');
        $fields = [
            tl('manageadvertisements_element_adname') => "name",
            tl('manageadvertisements_element_description') => "description",
            tl('manageadvertisements_element_destination_url') => "destination",
            tl('manageadvertisements_element_keywords') => "keywords",
            tl('manageadvertisements_element_budget') => "budget",
            tl('manageadvertisements_element_start_date') => "start_date",
            tl('manageadvertisements_element_end_date') => "end_date",
        ];
        $view->helper("searchform")->render($data, $controller, $activity,
            $view, $title, $return_form_name, $fields);
    }
}
