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

use seekquarry\yioop\configs as C;

/**
 * This View is responsible for drawing forward-facing wiki pages in
 * a more static cleaned up way
 *
 * @author Chris Pollett
 */
class StaticView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws wiki page in a more static fashion.
     *
     * @param array $data  contains the static page contents
     * the view
     */
    public function renderView($data)
    {
        $logo = C\LOGO;
        $logged_in = (isset($data['ADMIN']) && $data['ADMIN']);
        $append_url = ($logged_in && isset($data[C\CSRF_TOKEN]))
                ? "?" . C\CSRF_TOKEN. "=".$data[C\CSRF_TOKEN] : "";
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }
        if (isset($data['PAGE_HEADER'])) {
            e($data['PAGE_HEADER']);
        } else {
            ?>
            <div class="current-activity-header center">
            <h1 class="logo"><a href="<?=C\BASE_URL .
                $append_url ?>"><img src="<?= C\BASE_URL . $logo ?>"
                alt="<?= tl('static_view_title') ?>" /></a><span><?=
                $data['subtitle']?></span></h1>
            </div>
            <?php
        }
        $head_objects = isset($this->head_objects[$data['page']]) ?
            $this->head_objects[$data['page']] : "";
        $page_border = "";
        if (isset($head_objects['page_border']) &&
            $head_objects['page_border'] &&
            $head_objects['page_border'] != 'none') {
            $page_border = $head_objects['page_border'];
        }
        if (isset($data["AD_LOCATION"]) &&
            in_array($data["AD_LOCATION"], ['top', 'both'] ) ) { ?>
            <div class="top-adscript top-ad-static"><?=
            $data['TOP_ADSCRIPT'] ?></div>
            <?php
        }
        ?>
        <div class="static small-margin-current-activity <?= $page_border ?>">
        <?php if (!C\MOBILE && isset($data["AD_LOCATION"]) &&
            in_array($data["AD_LOCATION"], ['side', 'both'] ) ) { ?>
            <div class="side-adscript"><?= $data['SIDE_ADSCRIPT'] ?></div>
        <?php
        } ?>
            <?php if (isset($data["value"])) {
                    $page = sprintf($this->page_objects[$data['page']],
                        $data["value"]);
                    e($page);
                } else {
                    e($this->page_objects[$data['page']]);
                }?>
        </div>
        <?php
        if (isset($data['PAGE_FOOTER'])) {
            ?><div class="current-activity-footer">
            <?= $data['PAGE_FOOTER']
            ?></div><?php
        } else {
            ?>
            <div class="current-activity-footer center">
                <?php $this->element("footer")->render($data);?>
            </div>
            <?php
        }
    }
}
