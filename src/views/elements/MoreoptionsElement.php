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

/**
 * Element responsible for drawing the page with more
 * search source options, create account, and tool info
 *
 * @author Chris Pollett
 */
class MoreoptionsElement extends Element
{
    /**
     * Method responsible for drawing the page with more
     * search option, account, and tool info
     *
     * @param array $data to draw links on page
     */
    public function render($data)
    {
        $logged_in = (isset($data['ADMIN']) && $data['ADMIN']);
        $token_query = ($logged_in && isset($data[C\CSRF_TOKEN])) ?
            C\CSRF_TOKEN . "=".$data[C\CSRF_TOKEN] : "";
        if (C\SUBSEARCH_LINK) {
            $max_column_num = 10;
            if (C\MOBILE) {
                $num_columns = 1;
            } else {
                $num_columns = 4;
            }
            $max_items = $max_column_num * $num_columns;
            $subsearches = array_slice($data["SUBSEARCHES"], $max_items *
                $data['MORE_PAGE']);
            $spacer = "";
            $prev_link = false;
            $next_link = false;
            if ($data['MORE_PAGE'] > 0) {
                $prev_link = true;
            }
            $num_remaining = count($subsearches);
            if ($num_remaining > $max_items) {
                $next_link = true;
                $subsearches = array_slice($subsearches, 0,
                    $max_items);
            }
            if ($next_link && $prev_link) {
                $spacer = "&nbsp;&nbsp;--&nbsp;&nbsp;";
            }
            $num_rows = ceil(count($subsearches)/$num_columns);
            ?>
            <h2><?= tl('moreoptions_element_other_searches')?></h2>
            <table>
            <tr class="align-top">
            <?php
            $cur_row = 0;
            foreach ($subsearches as $search) {
                if ($cur_row == 0) {
                    e("<td><ul class='square-list'>");
                    $ul_open = true;
                }
                $cur_row++;
                if (!$search['SUBSEARCH_NAME']) {
                    $search['SUBSEARCH_NAME'] = $search['LOCALE_STRING'];
                }
                $query = ($search["FOLDER_NAME"] == "") ? C\BASE_URL . "?" :
                    B\subsearchUrl($search["FOLDER_NAME"], true);
                $query .= $token_query;
                $query = rtrim($query, "?&amp;");
                e("<li><a href='$query'>".
                    "{$search['SUBSEARCH_NAME']}</a></li>");
                if ($cur_row >= $num_rows) {
                    $ul_open = false;
                    e("</ul></td>");
                    $cur_row = 0;
                }
            }
            if ($ul_open) {
                e("</ul></td>");
            }
            $more_url = B\moreUrl(true);
            ?>
            </tr>
            </table>
            <div class="indent"><?php
                if ($prev_link) {
                    e("<a href='" . $more_url . $token_query.
                        "&amp;more_page=".($data['MORE_PAGE'] -1)."'>".
                        tl('moreoptions_element_previous')."</a>");
                }
                e($spacer);
                if ($next_link) {
                    e("<a href='" . $more_url . $token_query .
                        "&amp;more_page=".($data['MORE_PAGE'] + 1)."'>".
                        tl('moreoptions_element_next')."</a>");
                }
            ?></div>
        <?php
        }
        $return_url = 'oldc=search&amp;return=more';
        $return_url .= ($token_query) ? "&amp;$token_query" : "";
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $settings_url = htmlentities(B\controllerUrl('settings', true));
        $register_url = htmlentities(B\controllerUrl('register', true));
        ?>
        <h2 class="reduce-top"><?=
            tl('moreoptions_element_my_accounts')?></h2>
        <table class="reduce-top">
        <tr><td><ul class='square-list'><li><a href="<?=$settings_url .
                $return_url ?>&amp;l=<?=L\getLocaleTag() ?><?=
                (isset($data['its'])) ? '&amp;its='.$data['its'] : ''
                ?>"><?= tl('signin_element_settings') ?></a></li>
            <?php
            if (!C\MOBILE) { ?>
                </ul></td>
                <td><ul  class='square-list'>
                <?php
            }
            if (!$logged_in) {
                ?><li><a href="<?= B\controllerUrl('admin') ?>"><?=
                tl('signin_element_signin') ?></a></li><?php
            } else {
                ?><li><a href="<?=$admin_url . $token_query ?>"><?=
                tl('signin_element_admin') ?></a></li><?php
            }
            if (!C\MOBILE) {
                e('</ul></td>');
            }
            ?>
            <?php
            if ((!$logged_in) &&
                in_array(C\REGISTRATION_TYPE, ['no_activation',
                'email_registration', 'admin_activation'])) {
                if (!C\MOBILE){ e("<td><ul  class='square-list'>"); } ?>
                <li><a href="<?=rtrim($register_url . "a=createAccount&amp;" .
                    $token_query, "?&amp;") ?>"><?=
                    tl('signin_view_create_account')
                    ?></a></li>
                </ul></td>
                <?php
            }
            ?>
        </tr>
        </table>
        <?php
        $tools = [];
        if(empty($token_query))  {
            $suggest_url = B\suggestUrl();
            $pages_url = B\wikiUrl('pages');
        } else {
            $suggest_url = B\suggestUrl(true) . $token_query;
            $pages_url = B\wikiUrl('pages', true) . $token_query;
        }
        if (in_array(C\REGISTRATION_TYPE, ['no_activation',
            'email_registration', 'admin_activation'])) {
            $tools[$suggest_url] = tl('moreoptions_element_suggest');
        }
        $tools[$pages_url] = tl('moreoptions_element_wiki_pages');
        if ($tools != []) {
            $max_column_num = 10;
            if (C\MOBILE) {
                $num_columns = 1;
            } else {
                $num_columns = 4;
            }
            $num_rows = ceil(count($tools)/$num_columns);
            ?>
            <h2 id="tools" class="reduce-top"><?php
                e(tl('moreoptions_element_tools'))?></h2>
            <table class="reduce-top">
            <tr class="align-top">
            <?php
            $cur_row = 0;
            foreach ($tools as $tool_url => $tool_name) {
                if ($cur_row == 0) {
                    e("<td><ul class='square-list'>");
                    $ul_open = true;
                }
                $cur_row++;
                e("<li><a href='$tool_url'>$tool_name</a></li>");
                if ($cur_row >= $num_rows) {
                    $ul_open = false;
                    e("</ul></td>");
                    $cur_row = 0;
                }
            }
            if ($ul_open) {
                e("</ul></td>");
            }
            ?>
            </tr>
            </table>
            <?php
        }
    }
}
