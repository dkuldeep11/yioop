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

/**
 * This element renders the initial edit page for a classifier, where the user
 * can update the classifier label and find documents to label and add to the
 * training set. The page displays some initial statistics and a form for
 * finding documents in any existing index, but after that it is heavily
 * modified by JavaScript in response to user actions and XmlHttpRequests
 * made to the server.
 *
 * @author Shawn Tice
 */
class EditclassifierElement extends Element
{
    /**
     * Draws the "edit classifier" element to the output buffers.
     *
     * @param array $data used to pass the class label, classifier instance,
     * and list of existing crawls
     */
    public function render($data)
    {
        $classifier = $data['classifier'];
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        ?>
        <div class="current-activity">
        <div class="<?= $data['leftorright'] ?>">
        <a href="<?=$admin_url ?>a=manageClassifiers&amp;<?=
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN] ?>"><?=
            tl('editclassifier_back') ?></a>
        </div>
        <h2><?= tl('editclassifier_edit_classifier') ?></h2>
        <form id="classifierForm" method="get">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageClassifiers" />
        <input type="hidden" name="arg" value="editclassifier" />
        <input type="hidden" name="update" value="update" />
        <input type="hidden" name="class_label"
            value="<?= $data['class_label'] ?>" />
        <div class="top-margin">
        <label for="rename-label"><?=
            tl('editclassifier_classifier_label') ?></label>
            <input type="text" id="rename-label" name="rename_label"
                value="<?= $data['class_label'] ?>"
                maxlength="<?= C\NAME_LEN ?>" class="wide-field"/>
            <button class="button-box" type="submit"><?=
                tl('editclassifier_change') ?></button><?=
                $this->view->helper("helpbutton")->render(
                "Changing the Classifier Label", $data[C\CSRF_TOKEN]) ?>
        </div>
        </form>
        <h3><?= tl('editclassifier_statistics') ?></h3>
        <p><b><?= tl('editclassifier_positive_examples')
            ?></b> <span id="positive-count"><?=
            $classifier->positive ?></span></p>
        <p><b><?= tl('editclassifier_negative_examples')
            ?></b> <span id="negative-count"><?=
            $classifier->negative ?></span></p>
        <p><b><?= tl('editclassifier_accuracy')
            ?></b> <span id="accuracy"><?php
            if (!is_null($classifier->accuracy)) {
                printf('%.1f%%', $classifier->accuracy * 100);
            } else {
                e(tl('crawl_component_na'));
            }?></span>
            [<a id="update-accuracy" href="#update-accuracy"
            <?php if ($classifier->total < 10) {
                e('class="disabled"');
            } ?>><?= tl('editclassifier_update') ?></a>]</p>
        <h3><?= tl('editclassifier_add_examples') ?> <?=
            $this->view->helper("helpbutton")->render(
                "Adding Examples to a Classifier", $data[C\CSRF_TOKEN]) ?></h3>
        <form id="label-docs-form" method="GET">
        <?php
            $td = (C\MOBILE) ? "</tr><td>" : "<td>";
        ?>
        <table>
            <tr>
            <th><?= tl('editclassifier_source') ?></th>
            <?= $td ?>
                <select id="label-docs-source" name="label_docs_source">
                    <option value="1" selected="selected"><?=
                    tl('editclassifier_default_crawl') ?></option><?php
                    foreach ($data['CRAWLS'] as $crawl) { ?>
                        <option value="<?= $crawl['CRAWL_TIME'] ?>"><?=
                        $crawl['DESCRIPTION'] ?></option><?php 
                    } ?>
                </select>
            </td>
            <?= $td ?>
                <select id="label-docs-type" name="label_docs_type">
                    <option value="manual" selected="selected"><?=
                        tl('editclassifier_label_by_hand') ?></option>
                    <option value="positive"><?=
                        tl('editclassifier_all_in_class') ?></option>
                    <option value="negative"><?=
                        tl('editclassifier_none_in_class') ?></option>
                </select>
            </td>
            </tr>
            <tr>
                <th><?= tl('editclassifier_keywords') ?></th><?php
                if (C\MOBILE) { ?>
                    </tr><tr><?php
                }?>
                <td <?php if (!C\MOBILE) {?>colspan="2" <?php } ?> >
                    <input type="text" maxlength="<?=C\LONG_NAME_LEN
                        ?>" id="label-docs-keywords"
                        name="label_docs_keywords" />
                    <button class="button-box" type="submit"><?=
                        tl('editclassifier_load') ?></button>
                    <button class="button-box back-dark-gray" type="button"
                        onclick="window.location='<?=
                        "?c=admin&a=manageClassifiers&arg=finalizeclassifier".
                        "&".C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN].
                        "&class_label=".$data['class_label'] ?>'"><?=
                        tl('editclassifier_finalize') ?></button>
                </td>
            </tr>
            <tr><?php
                if (!C\MOBILE) { ?>
                    <th>&nbsp;</th><?php
                }
                ?><td id="label-docs-status" colspan="2"><?=
                    tl('editclassifier_no_documents') ?></td>
            </tr>
        </table>
        </form>
    <?php
    }
}
