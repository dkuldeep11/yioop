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
 * View responsible for drawing the form where a user can suggest a URL
 *
 * @author Chris Pollett
 */
class SuggestView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the form where a user can suggest a url
     *
     * @param array $data  contains the anti CSRF token
     *     the view, data for captcha and recover dropdowns
     */
    public function renderView($data)
    {
        $logged_in = (isset($data['ADMIN']) && $data['ADMIN']);
        $append_url = ($logged_in && isset($data[C\CSRF_TOKEN]))
                ? C\CSRF_TOKEN. "=".$data[C\CSRF_TOKEN] : "";
        $logo = C\LOGO;
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }
        $missing = [];
        if (isset($data['MISSING'])) {
            $missing = $data['MISSING'];
        }
        ?>
        <div class="landing non-search">
        <div class="small-top">
            <h1 class="logo"><a href="<?= C\BASE_URL . $append_url ?>"><img
                src="<?= C\BASE_URL . $logo ?>" alt="<?= $this->logo_alt_text
                ?>"/></a><span> - <?=tl('suggest_view_suggest_url')
                ?></span></h1>
            <p class="center"><?= tl('suggest_view_instructions') ?></p>
            <form method="post">
            <?php  if (isset($_SESSION["random_string"])) { ?>
            <input type='hidden' name='nonce_for_string'
            id='nonce_for_string' />
            <input type='hidden' name='random_string' id='random_string'
                value='<?= $_SESSION["random_string"] ?>' />
            <input type='hidden' name='time1' id='time1'
                value='<?= $_SESSION["request_time"] ?>' />
            <input type='hidden' name='level' id='level'
                value='<?= $_SESSION["level"]?>' />
            <?php } ?>
            <input type='hidden' name='time' id='time'
                value='<?= time() ?>'/>
            <input type="hidden" name="c" value="register" />
            <input type="hidden" name="a" value="suggestUrl" />
            <input type="hidden" name="arg" value="save" />
            <input type="hidden" name="build_time" value="<?=
                $data['build_time'] ?>" />
                <div class="register">
                    <table>
                        <tr>
                            <th class="table-label"><label for="url">
                                <?php
                                e(tl('suggest_view_url')); ?></label>
                            </th>
                            <td class="table-input">
                                <input id="url" type="text"
                                    class="narrow-field" maxlength="<?=
                                    C\SHORT_TITLE_LEN ?>"
                                    name="url" value = "<?=$data['url'] ?>"/><?=
                                    in_array("url", $missing) ?
                                    '<span class="red">*</span>':'' ?></td>
                        </tr>
                        <tr>
                        <?php
                        if (!isset($_SESSION["random_string"]) &&
                            !isset($_SESSION["captcha_text"])) {
                            $question_sets = [tl('register_view_human_check') =>
                                $data['CAPTCHA']];
                            $i = 0;
                            foreach ($question_sets as $name => $set) {
                                $first = true;
                                $num = count($set);
                                foreach ($set as $question) {
                                    if ($first) {
                                        ?>
                                        <tr><th class="table-label"
                                            rowspan='<?=$num ?>'><?=
                                            $name ?>
                                        </th><td class="table-input border-top">
                                        <?php
                                    } else {
                                        ?>
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
                        }
                        if (isset($data['CAPTCHA_IMAGE'])) {
                            ?>
                            <tr><th class="table-label" rowspan='2'><label
                                for="user-captcha-text"><?=
                                tl('suggest_view_human_check')
                                ?></label></th><td><img class="captcha"
                                src="<?=$data['CAPTCHA_IMAGE']?>" alt="CAPTCHA">
                                </td></tr><tr><td>
                                <input type="text" maxlength="<?=C\CAPTCHA_LEN
                                ?>" id="user-captcha-text" class="narrow-field"
                                name="user_captcha_text"/></td></tr>
                            <?php
                        }
                        ?>
                        <tr>
                            <td></td>
                            <?php if (isset($_SESSION["random_string"])) { ?>
                            <td class="table-input">
                            <?php } else { ?>
                               <td class="table-input border-top">
                            <?php }?>
                               <input type="hidden"
                                name="<?= C\CSRF_TOKEN ?>"
                                value="<?= $data[C\CSRF_TOKEN] ?>"/>
                                <button class="sides-margin" type="submit">
                                <?= tl('suggest_view_submit_url') ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </form>
            <div class="signin-exit">
                <ul>
                <li><a href="."><?= tl('suggest_view_return') ?></a></li>
                </ul>
            </div>
        </div>
        </div>
        <div class='tall-landing-spacer'></div>
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
