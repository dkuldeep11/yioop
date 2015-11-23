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

/**
 * This view is used to display information about
 * the on/off state of the queue_servers and fetchers managed by
 * this instance of Yioop.
 *
 * @author Chris Pollett
 */
class MachinestatusView extends View
{
    /**
     * Draws the ManagestatusView to the output buffer
     *
     * @param array $data  contains on/off status info for each of the machines
     *     managed by this Yioop instance.
     */
    public function renderView($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $csrf_token = C\CSRF_TOKEN."=". $data[C\CSRF_TOKEN];
        $base_url = "{$admin_url}a=manageMachines&amp;$csrf_token&amp;arg=";
        if (count($data['MACHINES']) == 0) {
            e(tl('machinestatus_view_no_monitored'));
        } else {
        ?>
        <div class="no-margin"><b><?php
            e(tl('machinestatus_media_updatemode'));
            $log_url = $base_url ."log&amp;name=NAME_SERVER&type=MediaUpdater".
                "&id=0";
            $on_media_updater = $base_url . "update&amp;action=start&amp;".
                "name=NAME_SERVER&amp;type=MediaUpdater&amp;id=0";
            $off_media_updater = $base_url ."update&amp;action=stop&amp;".
                "name=NAME_SERVER&amp;type=MediaUpdater&amp;id=0";
            $name_server_update = $data['MEDIA_MODE']=='name_server';
            $update_mode_url = $base_url . "updatemode";
            $caution = !isset($data['MACHINES']['NAME_SERVER']["MediaUpdater"])
                || $data['MACHINES']['NAME_SERVER']["MediaUpdater"] == 0;
        ?></b> [<?php
        if ($name_server_update) {
            e("<b>".tl('machinestatus_name_server'));
            ?></b>|<a href="<?php e($update_mode_url); ?>"><?php
            e(tl('machinestatus_distributed'));?></a><?php
        } else {
            ?><a href="<?php e($update_mode_url); ?>"><?php
            e(tl('machinestatus_name_server'));
            ?></a>|<b><?php
            e(tl('machinestatus_distributed'));?></b><?php
        }
        ?>]</div>
        <div class="box">
        <h3 class="no-margin"><?=
            tl('machinestatus_name_server')
        ?></h3>
        <form id="mediaModeForm" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= CSRF_TOKEN ?>" value="<?=
            $data[CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="mediamode" />
        <table class="machine-table"><tr>
        <th><?= tl('machinestatus_view_media_updater') ?></th>
        <td>[<a href="<?= $log_url ?>"><?=
            tl('machinestatus_view_log') ?></a>]</td>
        <td><?php
            $this->helper("toggle")->render(
            ($data['MACHINES']['NAME_SERVER']["MEDIA_UPDATER_TURNED_ON"] == 1),
            $on_media_updater,
            $off_media_updater, $caution);?>
        </td>
        </tr></table>
        </form>
        </div>
        <?php
        if (count($data['MACHINES']) > 1) {
            $data['TABLE_TITLE'] = "";
            $data['ACTIVITY'] = 'manageMachines';
            $data['VIEW'] = $this;
            $data['FORM_TYPE'] = null;
            $data['NO_SEARCH'] = true;
            $data['NO_FLOAT_TABLE'] = true;
            $this->helper("pagingtable")->render($data);;
        }
        foreach ($data['MACHINES'] as $k => $m) {
            if (!is_numeric($k)) {
                continue;
            }
            ?>
            <div class="box">
            <div class="float-opposite" >[<a href='<?=
                $base_url . "deletemachine&amp;name={$m['NAME']}"
                ?>' onclick='javascript:return confirm("<?=
                tl('confirm_delete_operation') ?>");' ><?=
                tl('machinestatus_view_delete') ?></a>]</div>
            <h3 class="no-margin"><?php e($m['NAME']);?><small
            style="position:relative;top:-3px;font-weight: normal;">[<?=
            $m['URL'] ?>]</small></h3>
            <table class="machine-table">
            <?php
            $on_queue_server = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=QueueServer&amp;action=start";
            $off_queue_server = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=QueueServer&amp;action=stop";
            $on_mirror = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=Mirror&amp;action=start";
            $off_mirror = $base_url . "update&amp;name={$m['NAME']}".
                "&amp;type=Mirror&amp;action=stop";
            $on_media_updater = $base_url . "update&amp;action=start&amp;".
                "name={$m['NAME']}&amp;type=MediaUpdater&amp;id=0";
            $off_media_updater = $base_url ."update&amp;action=stop&amp;".
                "name={$m['NAME']}&amp;type=MediaUpdater&amp;id=0";
            if ($m['STATUSES'] == 'NOT_CONFIGURED_ERROR') {
                ?>
                </table>
                <span class='red'><?=
                    tl('machinestatus_view_not_configured') ?></span>
                </div>
                <?php
                continue;
            }
            if ($m['PARENT'] != "") {
                    $log_url = $base_url . "log&name={$m['NAME']}".
                        "&type=mirror&id=0;";
                ?>
                <tr>
                <th><?= tl('machinestatus_view_mirrors', $m['PARENT']) ?>
                    </th>
                <td><table><tr><td>#00[<a href="<?php e($log_url);?>"><?=
                    tl('machinestatus_view_log') ?>]</td></tr><tr><td><?php
                    $caution = isset($m['STATUSES']["mirror"]) && (
                        !isset($m['STATUSES']["mirror"][-1]) ||
                        !$m['STATUSES']["mirror"][-1]);
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']["mirror"]),
                        $on_mirror, $off_mirror, $caution);
                ?></td></tr></table></td></tr>
                </table>
                </div><br /><?php
                continue;
            }
            if ($m['HAS_QUEUE_SERVER'] == "1") {
                $log_url = $base_url . "log&name={$m['NAME']}";
                ?>
                <tr><th><?= tl('machinestatus_view_queue_server') ?>
                </th><td><table><tr><td>#00[<a href="<?= $log_url .
                    "&type=QueueServer&id=0" ?>"><?=
                    tl('machinestatus_view_log') ?>]</a>
                    </td></tr><tr><td><?php
                    $caution = isset($m['STATUSES']["QueueServer"]) && (
                        !isset($m['STATUSES']["QueueServer"][-1]) ||
                        !$m['STATUSES']["QueueServer"][-1]);
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']["QueueServer"]) ,
                        $on_queue_server, $off_queue_server, $caution);
                ?></td></tr></table></td>
                <?php
            } else {
                ?>
                <tr><th><?= tl('machinestatus_view_queue_server')
                ?></th><td style="width:100px;"><?php
                e(tl('machinestatus_view_no_queue_server'));
                ?></td>
                <?php
            }
            if (!$name_server_update) {
                $colspan = " colspan='2' ";
                if(C\MOBILE) { 
                    e('</tr><tr>');
                    $colspan = "";
                }
                ?>
                <th <?=$colspan ?>><?=tl('machinestatus_view_media_updater') ?>
                </th><td><table><tr><td>#00[<a href="<?= $log_url .
                    "&type=MediaUpdater&id=0" ?>"><?=
                    tl('machinestatus_view_log')?>]</a>
                    </td></tr><tr><td><?php
                    $caution = isset($m['STATUSES']["MediaUpdater"]) && (
                        !isset($m['STATUSES']["MediaUpdater"][-1]) ||
                        !$m['STATUSES']["MediaUpdater"][-1]);
                    $this->helper("toggle")->render(
                        isset($m['STATUSES']["MediaUpdater"]),
                        $on_media_updater, $off_media_updater, $caution);
                ?></td></tr></table></td>
                <?php
            }
            ?>
            </tr>
            <?php
            if(!C\MOBILE) {
                ?>
                <tr class="machine-table-hr"><td class="machine-table-hr"
                    colspan="10"><hr/></td></tr>
                <?php
            }
            if ($m['NUM_FETCHERS'] == 0) {
                e("<tr class='border-top'><td colspan='10'><h3>".
                    tl('machinestatus_view_no_fetchers')."</h3></td></tr>");
            } else {
                $machine_wrap_number = (C\MOBILE) ? 2 : 4;
                for ($i = 0; $i < $m['NUM_FETCHERS']; $i++) {
                    $on_fetcher = $base_url . "update&amp;name={$m['NAME']}".
                        "&amp;action=start&amp;type=Fetcher&amp;id=$i";
                    $off_fetcher = $base_url . "update&amp;name={$m['NAME']}".
                        "&amp;action=stop&amp;type=Fetcher&amp;id=$i";
                    if ($i  == 0) { ?>
                        <tr><th rowspan="<?=
                            ceil($m['NUM_FETCHERS'] / $machine_wrap_number);
                            ?>"><?=
                            tl('machinestatus_view_fetchers') ?></th><?php
                    }
                    ?><td><table><tr><td>#<?php
                    $log_url = $base_url .
                        "log&amp;name={$m['NAME']}&amp;type=Fetcher&id=$i";
                    if ($i < 10){e("0");} e($i);
                    ?>[<a href="<?= $log_url ?>"><?=
                        tl('machinestatus_view_log') ?></a>]</td>
                    </tr><tr><td><?php
                    $toggle = false;
                    $caution = false;
                    if (isset($m['STATUSES']["Fetcher"][$i])) {
                        $toggle = true;
                        $caution = ($m['STATUSES']["Fetcher"][$i] == 0);
                    }
                    $this->helper("toggle")->render(
                        $toggle, $on_fetcher, $off_fetcher, $caution);?></td>
                    </tr>
                    </table></td><?php
                    if($i % $machine_wrap_number == 
                        ($machine_wrap_number - 1) % $machine_wrap_number){
                        ?>
                        </tr><tr>
                        <?php
                    }
                }
                ?></tr><?php
            }
        ?></table></div><br /><?php
        }
    }
    }
}
