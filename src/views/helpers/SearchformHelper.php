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
 * Used to draw the form to do advanced search for items in a user, group,
 * locale, etc folder
 *
 * @author Chris Pollett
 */
class SearchformHelper extends Helper
{
    /**
     * Draw the form for advanced search for any HTML table drawn based on
     * using a model's getRow function
     *
     * @param array  $data from the controller with info of what fields might
     *     already be filled.
     * @param object $controller what controller is being used to handle logic
     * @param string $activity what activity the controller was executing
     *     (for return link)
     * @param object $view which view is responsible for calling this helper
     * @param string $title what to display as the header of this form
     * @param string $return_form_name string to use for return link to previous
     *     page
     * @param array $fields a list of searchable fields
     * @param array $dropdowns which fields should be rendered as dropdowns
     * @param string $postfix string to tack on to form variables (might use
     *     to make var names unique on page)
     */
    public function render($data, $controller, $activity, $view, $title,
        $return_form_name, $fields, $dropdowns = [], $postfix = "")
    {
        $base_url = htmlentities(B\controllerUrl($controller, true)) .
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] . "&amp;a=$activity";
        $old_base_url = $base_url;
        $browse = false;
        if (isset($data['browse'])) {
            $base_url .= "&amp;browse=".$data['browse'];
            $browse = true;
        }
        ?>
        <div class='float-opposite'><a href='<?=$old_base_url?>'><?=
            $return_form_name ?></a></div>
        <h2><?= $title . "&nbsp;" ?> </h2><?php
        $item_sep = (C\MOBILE) ? "<br />" : "</td><td>";
        ?>
        <form id="search-form" method="post" autocomplete="off">
        <input type="hidden" name="c" value="<?= $controller ?>" />
        <input type="hidden" name="<?=CSRF_TOKEN  ?>" value="<?=
            $data[CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="<?= $activity ?>" />
        <input type="hidden" name="arg" value="search" />
        <?php
        if ($browse) { ?>
            <input type="hidden" name="browse" value="true" />
            <?php
        }
        ?>
        <table class="name-table">
        <?php
        foreach ($fields as $label => $name) {
            if (is_array($name)) {
                $comparison_types = $name[1];
                $name = $name[0];
            } else {
                $comparison_types = $data['COMPARISON_TYPES'];
            }
            e("<tr><td class='table-label'><label for='{$name}-id'>".
                "$label:</label>");
            e($item_sep);
            ?>
            <style type="text/css">
            #<?= $name ?>-comparison {
                width:100%;
            }
            </style>
            <?php
            $view->helper("options")->render(
                "{$name}-comparison", "${name}_comparison",
                $comparison_types, $data["{$name}_comparison"]);
            e($item_sep);
            $out_name = $name;
            if ($postfix != "") {
                $out_name = $name."_$postfix";
            }
            if (isset($dropdowns[$name])) {
                $dropdowns[$name] =
                    ['-1' => tl('searchform_helper_any')] +
                    $dropdowns[$name];
                ?>
                <style type="text/css">
                #<?= $name ?>-id {
                    width:100%;
                }
                </style>
                <?php
                if ($data["{$out_name}"] == "") { $data["{$out_name}"] = '-1'; }
                $view->helper("options")->render("{$name}-id",
                    "{$out_name}", $dropdowns[$name], $data["{$out_name}"]);
            } else {
                e("<input type='text' id='{$name}-id' name='$out_name' ".
                    "maxlength='". C\LONG_NAME_LEN. "' ".
                    "value='{$data[$out_name]}' ".
                    "class='narrow-field'  />");
            }
            e($item_sep);
            $view->helper("options")->render("{$name}-sort",
                "{$name}_sort", $data['SORT_TYPES'], $data["{$name}_sort"]);
            e("</td></tr>");
        }
        ?>
        <tr><?php if (!C\MOBILE) {?><td></td><td></td> <?php } ?>
            <td <?php if (!C\MOBILE) {
                    ?>class="center" <?php
                }
                ?>><button class="button-box"
                type="submit"><?= tl('searchform_helper_search')
                ?></button></td>
        </tr>
        </table>
        </form>
        <?php
    }

}
