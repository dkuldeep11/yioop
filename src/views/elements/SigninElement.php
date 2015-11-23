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
 * Element responsible for drawing links to settings and login panels
 *
 * @author Chris Pollett
 */
class SigninElement extends Element
{
    /**
     * Method responsible for drawing links to settings and login panels
     *
     * @param array $data makes use of the CSRF_TOKEN for anti CSRF attacks
     */
    public function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        ?>
        <div class="user-nav" >
        <ul>
        <?php
        if ($logged_in) {
            ?><li><b>[<a href="<?=B\controllerUrl('admin', true) ?><?=
             C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]?>"><?=
             $data['USERNAME'] ?></a>]</b></li>
            <?php
        }
        if (C\WEB_ACCESS) {
            ?>
            <li><a href="<?=B\controllerUrl('settings', true)?><?php
            if ($logged_in) {
                e(C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]."&amp;");
            } ?>l=<?php
            e(L\getLocaleTag());
            e((isset($data['its'])) ? '&amp;its='.$data['its'] : '');
            e((isset($data['ACTIVITY_METHOD'])) ?
                '&amp;return='.$data['ACTIVITY_METHOD']:'');
            e((isset($data['ACTIVITY_CONTROLLER'])) ?
                '&amp;oldc='.$data['ACTIVITY_CONTROLLER']:'');
            ?>"><?=tl('signin_element_settings') ?></a></li><?php
        }
        if (C\SIGNIN_LINK && !$logged_in) {
            ?><li><a href="<?=B\controllerUrl('admin') ?>"><?=
            tl('signin_element_signin') ?></a></li><?php
        }
        if ($logged_in) {
            ?><li><a href="<?=C\BASE_URL ?>?a=signout"><?=
            tl('signin_element_signout') ?></a></li><?php
        }
        ?>
        </ul>
        </div>
        <?php
    }
}
