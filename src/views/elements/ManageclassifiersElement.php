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
use seekquarry\yioop\library\classifiers\Classifier;

/**
 * This element renders the page that lists classifiers, provides a form to
 * create new ones, and provides per-classifier action links to edit, finalize,
 * and delete the associated classifier.
 *
 * @author Shawn Tice
 */
class ManageclassifiersElement extends Element
{
    /**
     * Draws the "new classifier" form and table of existing classifiesr
     *
     * @param array $data used to pass the list of existing classifier
     * instances
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
        <?php
        if ($data['FORM_TYPE'] == "search") {
            $this->renderSearchForm($data);
        } else {
            $this->renderClassifierForm($data);
        }
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        $base_url = "{$admin_url}a=manageClassifiers&amp;". C\CSRF_TOKEN . "=".
            $data[C\CSRF_TOKEN]."&amp;arg=";
        if (!empty($data['classifiers'])) {
            $data['TABLE_TITLE'] =
                tl('manageclassifiers_available_classifiers');
            $data['ACTIVITY'] = 'manageClassifiers';
            $data['VIEW'] = $this->view;
            $data['NO_FLOAT_TABLE'] = true;
            $this->view->helper("pagingtable")->render($data);
            ?>
            <table class="classifiers-table">
                <tr>
                    <th><?= tl('manageclassifiers_label_col') ?></th>
                    <?php
                    if (!C\MOBILE) { ?>
                        <th><?=tl('manageclassifiers_positive_col') ?></th>
                        <th><?=tl('manageclassifiers_negative_col') ?></th>
                    <?php
                    }
                    ?>
                    <th colspan="3"><?=tl('manageclassifiers_actions_col')
                    ?></th>
                </tr>
                <?php
                foreach ($data['classifiers'] as $label => $classifier) { ?>
                <tr>
                    <td><b><?= $label ?></b><br />
                        <small><?= date("d M Y H:i:s",
                            $classifier->timestamp) ?></small>
                    </td>
                    <?php
                    if (!C\MOBILE) { ?>
                        <td><?= $classifier->positive ?></td>
                        <td><?= $classifier->negative ?></td>
                    <?php
                    }
                    ?>
                    <td><a href="<?= $base_url ?>editclassifier&amp;name=<?=
                        $label ?>"><?=tl('manageclassifiers_edit') ?></a></td>
                    <td><?php
                    if ($classifier->finalized == Classifier::FINALIZED) {
                        e(tl('manageclassifiers_finalized'));
                    } else if ($classifier->finalized ==
                        Classifier::UNFINALIZED) {
                        if ($classifier->total > 0) {
                            ?><a href="<?=$base_url
                                ?>finalizeclassifier&amp;name=<?=$label ?>"><?=
                                tl('manageclassifiers_finalize') ?></a><?php
                        } else {
                            e(tl('manageclassifiers_finalize'));
                        }
                    } else if (
                        $classifier->finalized == Classifier::FINALIZING) {
                        ?><span class='red'><?=
                        tl('manageclassifiers_finalizing')?></span><?php
                    }
                    ?></td>
                    <td><a onclick='javascript:return confirm("<?=
                        tl('confirm_delete_operation')
                        ?>");' href="<?=$base_url
                        ?>deleteclassifier&amp;name=<?=$label ?>"><?=
                            tl('manageclassifiers_delete') ?></a></td>
                </tr>
            <?php } // end foreach over classifiers ?>
            </table>
            <?php
            } // endif for available classifiers
            ?>
        </div>
        <?php
        if ($data['reload']) {
            ?>
            <script type="text/javascript">
            var sec = 1000;
            function classifierUpdate()
            {
                window.location = "<?=$admin_url . C\CSRF_TOKEN."=".
                    $data[C\CSRF_TOKEN] ?>&a=manageClassifiers";
            }
            setTimeout(classifierUpdate, 5 * sec);
            </script>
            <?php
        }
    }
    /**
     * Used to draw the form to create a new classifier
     *
     * @param array $data data for the view in this case we just make
     *     use of the CSRF_TOKEN
     */
     public function renderClassifierForm($data)
     {
        ?>
        <h2><?=tl('manageclassifiers_manage_classifiers')?> <?=
            $this->view->helper("helpbutton")->render(
                "Page Classifiers", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="classifiersForm" method="get">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageClassifiers" />
        <input type="hidden" name="arg" value="createclassifier" />
        <div class="top-margin"><label for="class-label"><?=
            tl('manageclassifiers_classifier_name') ?></label>:
            <input type="text" id="class-label" name="name"
                value="" maxlength="<?= C\NAME_LEN?>"
                    class="wide-field"/>
            <button class="button-box"  type="submit"><?=
                tl('manageclassifiers_create_button') ?></button>
        </div>
        </form>
        <?php
     }
    /**
     * Used to draw the form to search and filter through existing classifiers
     *
     * @param array $data data for the view
     */
     public function renderSearchForm($data)
     {
        $controller = "admin";
        $activity = "manageClassifiers";
        $view = $this->view;
        $title = tl('manageclassifiers_element_search');
        $return_form_name = tl('manageclassifiers_element_create_form');
        $fields = [ tl('manageclassifiers_classifier_name') => "name", ];
        $view->helper("searchform")->render($data, $controller, $activity,
                $view, $title, $return_form_name, $fields);
     }
}
