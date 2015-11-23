<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015 Chris Pollett chris@pollett.org
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
 * Element responsible for drawing the screen used to set up the search engine
 * appearance.
 *
 * @author Chris Pollett
 */
class AppearanceElement extends Element
{
    /**
     * Draws the forms used to modify the search engine appearance.
     *
     * This element has a form to set foreground, background appearance and
     * icons used in the display of the Yioop web app. It can also be used to
     * configure if the main landing page should be the main wiki page
     *
     * @param array $data holds data on the profile elements related to site
     *      appearance
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $appearance_url = $admin_url . 'a=appearance&amp;'.
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
        ?>
        <div class="current-activity">
        <div class="bold">
            <form id="appearanceForm" method="post"
                action='<?=$appearance_url ?>'
                enctype='multipart/form-data'>
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="appearance" />
            <input type="hidden" name="arg" value="profile" />
            <div class="top-margin">
            <div class="top-margin"><label for="landing-page"><?=
                tl('appearance_element_use_wiki_landing') ?></label>
            <input type="checkbox" id="landing-page"
                name="LANDING_PAGE" value='true' <?php
                if ($data['LANDING_PAGE'] == true) {
                    e("checked='checked'");} ?>/></div>
            <div class="top-margin"><label for="back-color"><?= 
                tl('appearance_element_background_color') ?></label>
            <input type="color" id="back-color"
                name="BACKGROUND_COLOR" class="narrow-field" value='<?=
                $data["BACKGROUND_COLOR"] ?>' /></div>
            <div class="top-margin">
            <table><tr>
            <td><label for="back-image"><?= 
                tl('appearance_element_background_image')
                ?></label></td><td class="user-icon-td"><?php
                $image = (isset($data['BACKGROUND_IMAGE']) &&
                $data['BACKGROUND_IMAGE']) ? $data['BACKGROUND_IMAGE'] :
                    C\BASE_URL."/resources/drag.png";
            ?><img id='current-back-image' class="user-icon"
                src="<?= $image ?>" alt="<?=
                tl('appearance_element_background_image') ?>" /><?php
            $this->view->helper("fileupload")->render(
                'current-back-image',
                'BACKGROUND_IMAGE', 'back-image', C\THUMB_SIZE, 'image',
                ['image/png', 'image/gif', 'image/jpeg', 'image/x-icon']);
            ?></td></tr></table>
            </div>
            <div class="top-margin"><label for="fore-color"><?=
                tl('appearance_element_foreground_color') ?></label>
            <input type="color" id="fore-color"
                name="FOREGROUND_COLOR" class="narrow-field" value='<?=
                $data["FOREGROUND_COLOR"] ?>' /></div>
            <div class="top-margin"><label for="top-color"><?=
                tl('appearance_element_topbar_color') ?></label>
            <input type="color" id="top-color"
                name="TOPBAR_COLOR" class="narrow-field" value='<?= 
                $data["TOPBAR_COLOR"]?>' /></div>
            <div class="top-margin"><label for="side-color"><?=
                tl('appearance_element_sidebar_color') ?></label>
            <input type="color" id="side-color"
                name="SIDEBAR_COLOR" class="narrow-field" value='<?=
                $data["SIDEBAR_COLOR"] ?>' /></div>
            <div class="top-margin">
            <table><tr>
            <td><label for="site-logo"><?=tl('appearance_element_site_logo')
                ?></label></td><td class="user-icon-td"><?php
            $image = (isset($data['LOGO']) &&
                $data['LOGO']) ? $data['LOGO'] :
                    C\BASE_URL."/resources/drag.png";
            ?><img id='current-site-logo' class="user-icon"
                src="<?=$image ?>" alt="<?=
                tl('appearance_element_site_logo') ?>" /><?php
            $this->view->helper("fileupload")->render(
                'current-site-logo',
                'LOGO', 'site-logo', C\THUMB_SIZE, 'image',
                ['image/png', 'image/gif', 'image/jpeg', 'image/x-icon']);
            ?></td></tr></table>
            </div>
            <div class="top-margin">
            <table><tr>
            <td><label for="mobile-logo"><?=
                tl('appearance_element_mobile_logo')
                ?></label></td><td class="user-icon-td"><?php
            $image = (isset($data['M_LOGO']) &&
                $data['M_LOGO']) ? $data['M_LOGO'] :
                    C\BASE_URL."/resources/drag.png";
            ?><img id='current-mobile-logo' class="user-icon"
                src="<?= $data['M_LOGO'] ?>" alt="<?=
                tl('appearance_element_mobile_logo') ?>" /><?php
            $this->view->helper("fileupload")->render(
                'current-mobile-logo',
                'M_LOGO', 'mobile-logo', C\THUMB_SIZE, 'image',
                ['image/png', 'image/gif', 'image/jpeg', 'image/x-icon']);
            ?></td></tr></table>
            </div>
            <div class="top-margin">
            <table><tr>
            <td><label for="favicon"><?=tl('appearance_element_favicon')
                ?></label></td><td class="user-icon-td"><?php
            $image = (isset($data['FAVICON']) &&
                $data['FAVICON']) ? $data['FAVICON'] :
                    C\BASE_URL."/resources/drag.png";
            ?><img id='current-favicon' class="user-icon"
                src="<?=$data['FAVICON'] ?>" alt="<?=
                tl('appearance_element_favicon') ?>" /><?php
            $this->view->helper("fileupload")->render(
                'current-favicon',
                'FAVICON', 'favicon',  C\THUMB_SIZE, 'image',
                ['image/png', 'image/gif', 'image/jpeg', 'image/x-icon']);
            ?></td></tr></table>
            </div>
            <div class="top-margin"><table><tr>
            <td><label for="toolbar"><?=tl('appearance_element_toolbar')
                ?></label></td><td class="user-icon-td"><?php
            ?><div id="current-toolbar" class="upload-file"
            >&nbsp;</div><?php
            $this->view->helper("fileupload")->render(
                'current-toolbar',
                'SEARCHBAR_PATH', 'toolbar',
                1000000, 'text', ["text/xml"]);
            ?></td></tr></table>
            </div>
            <div class="top-margin"><label for="timezone"><?=
                tl('appearance_element_site_timezone') ?></label>
            <input type="text" id="timezone"
                name="TIMEZONE" class="extra-wide-field" value='<?=
                $data["TIMEZONE"] ?>' /></div>
            <div class="top-margin"><label for="cookie-name"><?=
                tl('appearance_element_cookie_name') ?></label>
            <input type="text" id="cookie-name"
                name="SESSION_NAME" class="extra-wide-field" value='<?=
                $data["SESSION_NAME"] ?>' /></div>
            <div class="top-margin"><label for="token-name"><?=
                tl('appearance_element_token_name') ?></label>
            <input type="text" id="token-name"
                name="CSRF_TOKEN" class="extra-wide-field" value='<?=
                $data["CSRF_TOKEN"] ?>' /></div>
            <div class="top-margin"><label for="auxiliary-css"><?=
                tl('appearance_element_auxiliary_css') ?></label>
            <textarea class="short-text-area" name="AUXILIARY_CSS" ><?=
                $data['AUXILIARY_CSS'] ?></textarea></div>
            <div class="center">
            [<a href="<?= $appearance_url . '&amp;arg=reset' ?>"><?=
                tl('appearance_element_reset_customizations') ?></a>]
            </div>
            </div>
            <div class="top-margin center">
            <button class="button-box" type="submit"><?=
                tl('appearance_element_save') ?></button>
            </div>
            </div>
        </form>
        </div>
    <?php
    }
}
