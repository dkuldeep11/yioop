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

use seekquarry\yioop\configs as C;

/**
 * This is a helper class is used to handle
 * draw select options form elements
 *
 * @author Chris Pollett
 */
class OptionsHelper extends Helper
{
    /**
     * Draws an HTML select tag according to the supplied parameters
     *
     * @param string $id   the id attribute the select tag should have
     *      If empty string id attribute not echo'd
     * @param string $name   the name this form element should use
     *      If empty string name attribute not echo'd
     * @param array $options   an array of key value pairs for the options
     *    tags of this select element
     * @param string $selected   which option (note singular -- no support
     *     for selecting more than one) should be set as selected
     *     in the select tag
     * @param bool $onchange_submit whether to submit the parent form if
     *     this drop down is changed
     * @param array $additional_attributes associative array of attributes =>
     *      valuesto add to the open select tag if present
     */
    public function render($id, $name, $options, $selected,
        $onchange_submit = false, $additional_attributes = [])
    {
        $word_wrap_len = 28;
        $id_info = ($id != "") ? " id='$id' " : " ";
        $name_info = ($name != "") ? " name='$name' " : " ";
    ?>
        <select <?= $id_info ?> <?= $name_info ?> <?php
            if ($onchange_submit) {
                e(' onchange="this.form.submit()" ');
            }
            foreach ($additional_attributes as $attribute => $value) {
                e(" $attribute='$value' ");
            }
        ?> >
        <?php
        foreach ($options as $value => $text) {
        ?>
            <option value="<?= $value ?>" <?php
                if ($value== $selected) { e('selected="selected"'); }
                if (C\MOBILE && strlen($text) > $word_wrap_len + 3) {
                    $text = substr($text, 0, $word_wrap_len)."...";
                }
             ?>><?= $text ?></option>
        <?php
        }
        ?>
        </select>
        <?php
    }
}
