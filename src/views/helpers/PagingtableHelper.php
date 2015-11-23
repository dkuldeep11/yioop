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

/**
 * Used to create links to go backward/forwards and search a database
 * tables. HTML table with data representing a
 * database table  might have millions of rows so want to
 * limit what the user actually gets at one time and just
 * allow the user to "page" through in increments of
 * 10, 20, 50, 100, 200 rows at a time.
 *
 * @author Chris Pollett
 */
class PagingtableHelper extends Helper
{
    /**
     * The choices for how many rows out of the database table to display
     * @var array
     */
    public $show_choices = [
        10 => 10, 20 => 20, 50 => 50, 100 => 100, 200=> 200
    ];
    /**
     * Used to render the  links to go
     * backwards and forwards through a databse table. We have two separate
     * functions for the mobile and desktop drawers.
     *
     * @param array $data fields of this contain values from teh controller
     *      for the CSRF_TOKEN; NUM_TOTAL of rows; NUM_SHOW, the number to show;
     *      etc.
     */
    public function render($data)
    {
        if (!isset($data['PAGING'])) {
            $data['PAGING'] = "";
        }
        if (C\MOBILE) {
            $this->mobileTableControls($data);
        } else {
            $this->desktopTableControls($data);
        }
    }
    /**
     * Draws the heading before a paging table as well as the controls
     * for what rows to see (mobile phone case).
     *
     * @param array $data needed for dropdown values for number of groups to
     *     display
     */
    public function mobileTableControls($data)
    {
        $activity = $data['ACTIVITY'];
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=$activity";
        $data_fields = ['NUM_TOTAL', 'NUM_SHOW', 'START_ROW', 'END_ROW',
            'NEXT_START', 'NEXT_END', 'PREV_START', 'PREV_END', 'FORM_TYPE'];
        $var_prefix = (isset($data['VAR_PREFIX'])) ?
            strtoupper($data['VAR_PREFIX']) : "";
        foreach ($data_fields as $field) {
            $d[$field] = $var_prefix . $field;
        }
        $start_row = $var_prefix."start_row";
        $end_row = $var_prefix."end_row";
        $num_show = $var_prefix."num_show";
        ?>
        <h2><?php e($data['TABLE_TITLE']); ?>&nbsp;&nbsp;<?php
            if ($data['FORM_TYPE'] != 'search' &&
                    !isset($data['NO_SEARCH'])){ ?>
                [<a
                href="<?=$base_url . '&amp;arg=search'
                ?>"><?= tl('pagingtable_helper_search') ?></a>]<?php
            }?></h2>
        <div>
        <?php
            $form_tag = !isset($data["NO_FORM_TAG"]) || !$data["NO_FORM_TAG"];
            if ($form_tag) {
                e('<form  method="get">');
            }
            $name = isset($data['NAME']) ? $data['NAME'] : "";
            $bound_url = $base_url."&amp;arg=".$data[$d['FORM_TYPE']];
            if (isset($data['browse'])) {
                $bound_url .= "&amp;browse=".$data['browse'];
            }
            if ($name != "") {
                $bound_url .="&amp;name=".$name;
            } ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="changeshow" value="true" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                value="<?php e($data[C\CSRF_TOKEN]); ?>" />
            <input type="hidden" name="a" value="<?= $activity ?>" />
            <?php
            e("<b>".tl('pagingtable_helper_show')."</b>");
            $data['VIEW']->helper("options")->render(
                "{$var_prefix}num-show", "{$var_prefix}num_show",
                $this->show_choices, $data[$d['NUM_SHOW']], true);
            e("<br />");
            if ($data[$d['START_ROW']] > 0) {
                ?>
                <a href="<?= $bound_url."&amp;$start_row=".
                    $data[$d['PREV_START']]."&amp;$end_row=".
                    $data[$d['PREV_END']]."&amp;$num_show=".
                    $data[$d['NUM_SHOW']].$data['PAGING'] ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('pagingtable_helper_row_range', $data[$d['START_ROW']],
                $data[$d['END_ROW']], $data[$d['NUM_TOTAL']])."</b>");
            if ($data[$d['END_ROW']] < $data[$d['NUM_TOTAL']]) {
                ?>
                <a href="<?= $bound_url."&amp;$start_row=".
                    $data[$d['NEXT_START']]."&amp;$end_row=".
                    $data[$d['NEXT_END']]."&amp;$num_show=".
                    $data[$d['NUM_SHOW']].$data['PAGING'] ?>" >&gt;&gt;</a>
                <?php
            }
            if ($form_tag) {
                e('</form>');
            }
        ?>
        </div>
        <?php
    }
    /**
     * Draws the heading before the user table as well as the controls
     * for what user to see (desktop, laptop, tablet case).
     *
     * @param array $data needed for dropdown values for number of groups to
     *     display
     */
    public function desktopTableControls($data)
    {
        $activity = $data['ACTIVITY'];
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
            "&amp;a=$activity";
        $data_fields = ['NUM_TOTAL', 'NUM_SHOW', 'START_ROW', 'END_ROW',
            'NEXT_START', 'NEXT_END', 'PREV_START', 'PREV_END', 'FORM_TYPE'];
        $var_prefix = (isset($data['VAR_PREFIX'])) ?
            strtoupper($data['VAR_PREFIX']) : "";
        foreach ($data_fields as $field) {
            $d[$field] = $var_prefix . $field;
        }
        $start_row = $var_prefix."start_row";
        $end_row = $var_prefix."end_row";
        $num_show = $var_prefix."num_show";
        $class = 'class="table-margin float-opposite"';
        $target_fragment = (isset($data["TARGET_FRAGMENT"])) ?
            $data["TARGET_FRAGMENT"] : "";
        $top = false;
        if (isset( $data['NO_FLOAT_TABLE'] ) &&  $data['NO_FLOAT_TABLE'] ){
            $class = "";
            $top = true;
        }
        if ($top) {
            ?>
            <h2><?= $data['TABLE_TITLE']?></h2>
            <?php
        }
        ?>
        <div <?php e($class); ?>>
            <?php
            $form_tag = !isset($data["NO_FORM_TAG"]) || !$data["NO_FORM_TAG"];
            if ($form_tag) {
                e('<form  method="get">');
            }
            $name = isset($data['NAME']) ? $data['NAME'] : "";
            $bound_url = $base_url."&amp;arg=".$data[$d['FORM_TYPE']];
            if ($name != "") {
                $bound_url .="&amp;name=".$name;
            }
            if (isset($data['browse'])) {
                $bound_url .= "&amp;browse=".$data['browse'];
            }
            if ($data[$d['START_ROW']] > 0) {
                ?>
                <a href="<?= $bound_url."&amp;$start_row=".
                    $data[$d['PREV_START']]."&amp;$end_row=".
                    $data[$d['PREV_END']]."&amp;$num_show=".
                    $data[$d['NUM_SHOW']].$data['PAGING'].
                    '#'.$target_fragment ?>">&lt;&lt;</a>
                <?php
            }
            e("<b>".tl('pagingtable_helper_row_range', $data[$d['START_ROW']],
                $data[$d['END_ROW']], $data[$d['NUM_TOTAL']])."</b>");
            if ($data[$d['END_ROW']] < $data[$d['NUM_TOTAL']]) {
                ?>
                <a href="<?= $bound_url."&amp;$start_row=".
                    $data[$d['NEXT_START']]."&amp;$end_row=".
                    $data[$d['NEXT_END']]."&amp;$num_show=".
                    $data[$d['NUM_SHOW']].$data['PAGING'].
                    '#'.$target_fragment ?>" >&gt;&gt;</a>
                <?php
            }
            ?>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="changeshow" value="true" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                value="<?= $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="<?= $activity ?>" />
            <?php
                e("<b>".tl('pagingtable_helper_show')."</b>");
                $data['VIEW']->helper("options")->render(
                    "{$var_prefix}num-show", "{$var_prefix}num_show",
                    $this->show_choices,
                    $data[$d['NUM_SHOW']], true);
                if ( $data[$d['FORM_TYPE']] != "search" &&
                    !isset($data['NO_SEARCH'])) {?>
                    [<a href="<?= $base_url . '&amp;arg=search'
                        ?>"><?= tl('pagingtable_helper_search') ?></a>]
                <?php
                }
            if ($form_tag) {
                e('</form>');
            }?>
        </div>
        <?php if (!$top) {
            ?>
            <h2><?= $data['TABLE_TITLE'] ?></h2>
            <?php
        }
    }
}
