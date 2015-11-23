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
 * This View is responsible for drawing the login
 * screen for the admin panel of the Seek Quarry app
 *
 * @author Chris Pollett
 */
class SigninView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the login web page.
     *
     * @param array $data  contains the anti CSRF token
     * the view
     */
    public function renderView($data)
    {
        $logged_in = isset($data['ADMIN']) && $data['ADMIN'];
        $logo = C\LOGO;
        $user_value = isset($_SESSION["USER_NAME"]) &&
            isset($_SESSION['USER_ID']) ? " value='{$_SESSION["USER_NAME"]}' " :
            "";
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }?>
        <div class="landing non-search">
        <h1 class="logo"><a href="<?=C\BASE_URL ?><?php if ($logged_in) {
                e('?'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
            }?>"><img src="<?=C\BASE_URL .
            $logo ?>" alt="<?= $this->logo_alt_text
            ?>" /></a><span> - <?=tl('signin_view_signin') ?></span></h1>
        <?php if (isset($data['AUTH_ITERATION'])) { ?>
                <form  method="post" id="zkp-form"
                    onsubmit="generateKeys('zkp-form','username', <?php
                    ?>'password', 'fiat-shamir-modulus', '<?=
                    $_SESSION['SALT_VALUE'] ?>', <?=
                    $data['AUTH_ITERATION'] ?>)" >
                <input type="hidden" name="fiat_shamir_modulus"
                    id="fiat-shamir-modulus"
                    value="<?=$data['FIAT_SHAMIR_MODULUS'] ?>"/>
                <input type="hidden" id="salt-value" name="salt_value" />
                <input type="hidden" id="auth-message"
                    name="auth_message" value="<?=
                    tl('sigin_view_signing_in') ?>" />
                <input type="hidden" id="auth-fail-message"
                    name="auth_fail_message" value="<?=
                    tl('sigin_view_login_failed') ?>" />
        <?php } else {?>
                <form method="post">
        <?php } ?>
        <div class="login">
            <table>
            <tr>
            <td class="table-label" ><b><label for="username"><?=
                tl('signin_view_username') ?></label>:</b></td><td
                    class="table-input"><input id="username" type="text"
                    class="narrow-field" maxlength="<?= C\NAME_LEN
                    ?>" name="u" <?=$user_value ?> />
            </td><td></td></tr>
            <tr>
            <td class="table-label" ><b><label for="password"><?=
                tl('signin_view_password') ?></label>:</b></td><td
                class="table-input"><input id="password" type="password"
                class="narrow-field" maxlength="<?= C\LONG_NAME_LEN
                ?>" name="p" /></td>
            <td><input type="hidden" name="<?= C\CSRF_TOKEN ?>"
                    id="CSRF-TOKEN" value="<?= $data[C\CSRF_TOKEN] ?>" />
                <input type="hidden" name="c" value="admin" />
            </td>
            </tr>
            <tr><td>&nbsp;</td><td>
            <button  type="submit" ><?=tl('signin_view_login') ?></button>
            </td><td>&nbsp;</td></tr>
            </table>
        </div>
        </form>
        <div class="signin-exit">
            <ul>
                <?php
                if (in_array(C\REGISTRATION_TYPE, ['no_activation',
                    'email_registration', 'admin_activation'])) {
                    ?>
                    <li><a href="<?=B\controllerUrl('register', true)
                        ?>a=recoverPassword<?php
                        if ($logged_in) {
                            e('&amp;'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
                        } ?>" ><?=tl('signin_view_recover_password') ?></a></li>
                    <li><a href="<?=B\controllerUrl('register', true)
                        ?>a=createAccount<?php
                        if ($logged_in) {
                            e('&amp;'.C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]);
                        }?>"><?=tl('signin_view_create_account') ?></a></li>
                <?php
                }
            ?>
                <li><a href="."><?=tl('signin_view_return') ?></a></li>
            </ul>
        </div>
        </div>
        <div class='landing-spacer'></div>
        <?php
    }
}
