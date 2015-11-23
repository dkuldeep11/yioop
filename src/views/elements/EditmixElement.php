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
use seekquarry\yioop\controllers\components\SocialComponent as SC;

/**
 * Element responsible for displaying info about a given crawl mix
 *
 * @author Chris Pollett
 */
class EditmixElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  form about about a crawl such as its description
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        ?>
        <div class="current-activity">
        <div class="<?= $data['leftorright'] ?>">
        <a href="<?=$admin_url ?>a=mixCrawls&amp;<?=
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>" ><?=
            tl('editmix_element_back_to_mix') ?></a>
        </div>
        <h2><?= tl('mixcrawls_element_edit_mix')?></h2>
        <form id="mixForm" method="get">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="mixCrawls" />
        <input type="hidden" name="arg" value="editmix" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="mix[TIMESTAMP]"
            value="<?= $data['MIX']['TIMESTAMP'] ?>" />
        <div class="top-margin"><label for="mix-name"><?=
            tl('mixcrawls_element_mix_name') ?></label>
            <input type="text" id="mix-name" name="mix[NAME]"
                value="<?php if (isset($data['MIX']['NAME'])) {
                    e($data['MIX']['NAME']); } ?>" maxlength="<?=
                    C\NAME_LEN ?>" class="wide-field"/>
        </div>
        <h3><?= tl('mixcrawls_element_mix_components')?>
        <?= $this->view->helper("helpbutton")->render(
            "Editing a Crawl Mix", $data[C\CSRF_TOKEN]) ?>
        </h3>
        <div>
        [<a href='javascript:addFragment(1, <?=SC::MAX_MIX_FRAGMENTS?>, <?='"'.
            tl('mixcrawls_element_too_many').'"'
            ?>)'><?=tl('mixcrawls_element_add_fragment') ?></a>]
        </div>
        <div id="mix-tables" >
        </div>
        <div class="center slight-pad"><button class="button-box"
            type="submit"><?= tl('mixcrawls_element_save_button')
            ?></button></div>
        </form>
        </div>
    <?php
    }
}
