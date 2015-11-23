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

/**
 * This is a helper class is used to draw
 * an On-Off switch in a web page
 *
 * @author Chris Pollett
 */
class ToggleHelper extends Helper
{
    /**
     * Draws an On Off switch in HTML where to toggle state one
     * clicks a link
     *
     * @param bool $state whether the switch is on or off
     * @param string $on_url  url that is sent when one clicks on
     * @param string $off_url  url that is sent when one clicks off
     * @param bool $caution is set to true if on in the database but the
     *      status received from testing the state of the toggle some other
     *      way is off (causes things to draw in yellow rather than green)
     */
    public function render($state, $on_url, $off_url, $caution = false)
    {
        if ($state) {
            $oncolor = ($caution) ? "back-yellow" : "back-green";
            if ($caution) { ?>
                <table class="toggle-table"><tr><td
                class="<?= $oncolor
                    ?>"><a href="<?= $on_url ?>"><?=
                tl('toggle_helper_on') ?></a></td>
                <td><a href="<?= $off_url ?>"
                ><?= tl('toggle_helper_off') ?></a></td></tr></table>
            <?php } else { ?>
                <table class="toggle-table"><tr><td
                class="<?= $oncolor
                    ?>"><b><?= tl('toggle_helper_on') ?></b></td>
                <td><a href="<?= $off_url ?>"
                ><?= tl('toggle_helper_off') ?></a></td></tr></table>
            <?php } ?>
        <?php } else {?>
            <table class="toggle-table"><tr><td><a href="<?= $on_url ?>"
            ><?= tl('toggle_helper_on') ?></a></td>
            <td  class="back-red"><b><?=
                tl('toggle_helper_off') ?></b></td>
            </tr></table>
        <?php }
    }
}
