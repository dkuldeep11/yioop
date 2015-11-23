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
 * @author Chris Pollett
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\views;

use seekquarry\yioop\configs as C;

/**
 * This View is responsible for drawing the
 * screen for recovering a forgotten password
 *
 * @author Chris Pollett
 */
class RecoverView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the recover password web page and the page one get after
     * following the recover password email
     *
     * @param array $data  contains the anti CSRF token
     *     the view, data for captcha and recover dropdowns
     */
    public function renderView($data)
    {
        $logo = C\LOGO;
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }
        $missing = [];
        if (isset($data['MISSING'])) {
            $missing = $data['MISSING'];
        }
        $activity = (isset($data["RECOVER_COMPLETE"])) ?
            "recoverComplete" : "processRecoverData";
        ?>
        <div class="landing non-search">
        <div class="small-top">
            <h1 class="logo"><a href="<?=C\BASE_URL ?>?<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"><img
                src="<?= C\BASE_URL . $logo ?>" alt="<?= $this->logo_alt_text
                ?>"/></a><span> - <?= tl('recover_view_recover_password')
                ?></span></h1>
            <form method="post">
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="<?= $activity ?>" />
            <?php if (isset($_SESSION["random_string"])) { ?>
            <input type='hidden' name='nonce_for_string'
                id='nonce_for_string' />
            <input type='hidden' name='random_string' id='random_string'
                value='<?= $_SESSION["random_string"] ?>' />
            <input type='hidden' name='time1' id='time1'
                value='<?= $_SESSION["request_time"] ?>' />
            <input type='hidden' name='level' id='level'
                value='<?= $_SESSION["level"] ?>' />
            <input type='hidden' name='time' id='time' value='<?= time() ?>'/>
            <?php
            }
            if (isset($data["RECOVER_COMPLETE"])) { ?>
                <input type="hidden" name="user" value="<?=$data['user'] ?>" />
                <input type="hidden" name="time" value="<?=$data['time'] ?>" />
                <input type="hidden" name="finish_hash" value="<?=
                    $data['finish_hash'] ?>" />
            <?php
            }
            ?>
            <div class="register">
                <table>
                    <?php
                    if (isset($data["RECOVER_COMPLETE"])) {
                        ?>
                        <tr>
                            <th class="table-label">
                                <label for="password"><?php
                                    e(tl('register_view_new_password'));
                                ?></label>
                            </th>
                            <td class="table-input">
                                <input id="password" type="password"
                                    class="narrow-field" maxlength="<?=
                                C\LONG_NAME_LEN?>"
                                    name="password" value="" /></td>
                        </tr>
                        <tr>
                            <th class="table-label">
                                <label for="repassword"><?=
                                     tl('register_view_retypepassword')
                                ?></label>
                            </th>
                            <td class="table-input">
                                <input id="repassword" type="password"
                                    class="narrow-field" maxlength="<?=
                                C\LONG_NAME_LEN?>"
                                    name="repassword" value="" /></td>
                        </tr>
                        <?php
                    } else {
                    ?>
                        <tr>
                        <th class="table-label"><label for="username">
                            <?php
                            e(tl('recover_view_username')); ?></label>
                        </th>
                        <td class="table-input">
                            <input id="username" type="text"
                                class="narrow-field" maxlength="<?=
                                C\NAME_LEN ?>"
                                name="user" autocomplete="off"
                                value = "<?= $data['USER'] ?>"/>
                            <?= in_array("user", $missing)
                                ?'<span class="red">*</span>':''?></td>
                        </tr>
                    <?php
                    }
                    if ($activity == "recoverComplete") {
                        $question_sets = [
                            tl('register_view_account_recovery') =>
                            $data['RECOVERY']];
                    } else {
                       if (C\CAPTCHA_MODE != C\TEXT_CAPTCHA) {
                            $question_sets = [];
                       } else {
                            $question_sets = [
                                tl('register_view_human_check') =>
                                $data['CAPTCHA'] ];
                       }
                    }
                    $i = 0;
                    foreach ($question_sets as $name => $set) {
                        $first = true;
                        $num = count($set);
                        foreach ($set as $question) {
                            if ($first) { ?>
                                <tr><th class="table-label"
                                    rowspan='<?= $num ?>'><?=$name
                                ?></th><td class="table-input border-top">
                            <?php
                            } else { ?>
                                <tr><td class="table-input">
                            <?php
                            }
                            $this->helper("options")->render(
                                "question-$i", "question_$i",
                                $question, $data["question_$i"]);
                            $first = false;
                            e(in_array("question_$i", $missing)
                                ?'<span class="red">*</span>':'');
                            e("</td></tr>");
                            $i++;
                        }
                    }
                    if (isset($data['CAPTCHA_IMAGE'])) {
                        ?>
                        <tr><th class="table-label" rowspan='2'><label
                            for="user-captcha-text"><?=
                            tl('register_view_human_check')
                            ?></label></th><td><img class="captcha"
                            src="<?= $data['CAPTCHA_IMAGE'] ?>" alt="CAPTCHA">
                            </td></tr><tr><td>
                            <input type="text" maxlength="<?=C\CAPTCHA_LEN ?>"
                            id="user-captcha-text" class="narrow-field"
                            name="user_captcha_text"/></td></tr>
                        <?php
                    }
                    ?>
                    <tr>
                        <td></td>
                        <td class="table-input border-top">
                            <input type="hidden"
                                name="<?= C\CSRF_TOKEN ?>"
                                value="<?= $data[C\CSRF_TOKEN] ?>"/>
                            <button  type="submit"><?=
                                tl('recover_view_recover_password')
                            ?></button>
                        </td>
                    </tr>
                </table>
            </div>
            </form>
            <div class="signin-exit">
                <ul>
                <li><a href="."><?= tl('recover_view_return') ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <div class='tall-landing-spacer'></div>
        <?php  if (isset($_SESSION["random_string"])) {?>
        <script type="text/javascript" >
            document.addEventListener('DOMContentLoaded', function() {
            var body = tag(body);
            body.onload = findNonce('nonce_for_string', 'random_string'
                , 'time', 'level');
            }, false);
        </script>
        <?php
        }
    }
}
