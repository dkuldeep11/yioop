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
 * Element responsible for displaying the form where users can input string
 * translations for a given locale
 *
 * @author Chris Pollett
 */
class EditlocalesElement extends Element
{
    /**
     * Draws a form with strings to translate and a text field for the
     * translation into
     * the given locale. Strings with no translations yet appear in red
     *
     * @param array $data  contains msgid and already translated msg_string info
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = $admin_url ."a=manageLocales&amp;arg=editstrings&amp;" .
            C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "&amp;selectlocale=" .
            $data['CURRENT_LOCALE_TAG'] . "&amp;show={$data['show']}&amp;" .
            "filter={$data['filter']}";
        ?>
        <div class="current-activity">
        <div class="<?=$data['leftorright'] ?>">
        <a href="<?=$admin_url . 'a='.$data['PREVIOUS_ACTIVITY'].'&amp;'.
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN] ?>"
        ><?= tl('editlocales_element_back_to_manage')?></a>
        </div>
        <h2><?= tl('editlocales_element_edit_locale',
            $data['CURRENT_LOCALE_NAME']) ?>
        <?= $this->view->helper("helpbutton")->render(
            "Editing Locales", $data[C\CSRF_TOKEN]) ?>
        </h2>
        <form id="editLocaleForm" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageLocales" />
        <input type="hidden" name="arg" value="editstrings" />
        <input type="hidden" name="selectlocale" value="<?=
            $data['CURRENT_LOCALE_TAG'] ?>" />
        <div class="slight-pad">
        <label for="show-strings"><b><?= tl('editlocales_element_show')
        ?></b></label><?php $this->view->helper("options")->render(
            "show-strings","show",  $data['show_strings'],
            $data['show'], true); ?>
        <label for="string-filter"><b><?= tl('editlocales_element_filter')
        ?></b></label><input type="text" id="string-filter" name="filter"
            value="<?= $data['filter'] ?>" maxlength="<?= C\LONG_NAME_LEN ?>"
            onchange="this.form.submit()"
            class="narrow-field" /> <button class="button-box"
            type="submit"><?= tl('editlocales_element_go') ?></button>
        </div>
        <?php
        if ($data['STRINGS'] == []) {
            e("<h3 class='red'>". tl('editlocales_element_no_matching').
                "</h3>");
        }
        ?>
        <table class="translate-table">
        <?php
        $mobile_tr = (C\MOBILE) ? "</tr><tr>" : "";
        foreach ($data['STRINGS'] as $msg_id => $msg_string) {
            $out_id = $msg_id;
            if (C\MOBILE && strlen($out_id) > 33) {
                $out_id = wordwrap($out_id, 30, "<br />\n", true);
            } else {
                $out_id = wordwrap($out_id, 45, "<br />\n", true);
            }
            if (strlen($msg_string) > 0) {
                e("<tr><td><label for='$msg_id'>$out_id</label>".
                    "</td>$mobile_tr<td><input type='text' title='".
                    $data['DEFAULT_STRINGS'][$msg_id].
                    "' id='$msg_id' name='STRINGS[$msg_id]' ".
                    "value='$msg_string' /></td></tr>");
            } else {
                e("<tr><td><label for='$msg_id'>$out_id</label></td>".
                    "$mobile_tr<td><input class='highlight' type='text' ".
                    "title='".$data['DEFAULT_STRINGS'][$msg_id].
                    "' id='$msg_id' name='STRINGS[$msg_id]' ".
                    "value='$msg_string' /></td></tr>");
            }
        }
        ?>
        </table>
        <?php
            $this->view->helper("pagination")->render($base_url, $data['LIMIT'],
                $data['NUM_STRINGS_SHOW'], $data['TOTAL_STRINGS']);
        ?>
        <div class="center slight-pad"><button class="button-box"
            name="save" value="save"
            type="submit"><?=tl('editlocales_element_save') ?></button></div>
        </form>
        </div>
    <?php
    }
}
