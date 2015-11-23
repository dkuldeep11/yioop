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

use seekquarry\yioop\configs as C;

/**
 * This element is used to render the Page Options admin activity
 * This activity lets a user control the amount of web pages downloaded,
 * the recrawl frequency, the file types, etc of the pages crawled
 *
 * @author Chris Pollett
 */
class PageOptionsElement extends Element
{
    /**
     * Draws the page options element to the output buffer
     *
     * @param array $data used to keep track of page range, recrawl frequency,
     * and file types of the page
     */
    public function render($data)
    {
    ?>
        <div class="current-activity">
        <form id="pageoptionsForm" method="post" action='?'>
        <ul class='tab-menu-list'>
        <li><a href="javascript:
                switchTab('crawltimetab', 'searchtimetab', 'testoptionstab');"
            id='crawltimetabitem'
            class="<?= $data['crawl_time_active'] ?>"><?=
            tl('pageoptions_element_crawl_time')?></a></li>
        <li><a href="javascript:
                switchTab('searchtimetab', 'crawltimetab', 'testoptionstab');"
            id='searchtimetabitem'
            class="<?= $data['search_time_active'] ?>"><?=
            tl('pageoptions_element_search_time')?></a></li>
        <li><a href="javascript:
                switchTab('testoptionstab', 'crawltimetab', 'searchtimetab');"
            id='testoptionstabitem'
            class="<?= $data['test_options_active'] ?>"><?=
            tl('pageoptions_element_test_options') ?></a></li>
        </ul>
        <div class='tab-menu-content'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="posted" value="posted" />
        <input type="hidden" id='option-type' name="option_type" value="<?=
            $data['option_type'] ?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="pageOptions" />
        <div id='crawltimetab'>
        <div class="top-margin"><label for="load-options"><b><?=
            tl('pageoptions_element_load_options')?></b></label><?php
            $this->view->helper("options")->render("load-options","load_option",
                $data['available_options'], $data['options_default'], true);
        ?></div>
        <div class="top-margin"><b><label for="page-range-request"><?=
            tl('pageoptions_element_page_range')?></label></b>
            <?php $this->view->helper("options")->render("page-range-request",
            "page_range_request", $data['SIZE_VALUES'], $data['PAGE_SIZE']);
            e($this->view->helper("helpbutton")->render(
                "Page Byte Ranges", $data[C\CSRF_TOKEN])); ?></div>
        <div class="top-margin"><label for="summarizer"><b><?=
            tl('pageoptions_element_summarizer') ?></b></label><?php
                $this->view->helper("options")->render("summarizer",
                "summarizer_option",$data['available_summarizers'],
                $data['summarizer_option']);
            e($this->view->helper("helpbutton")->render(
                "Kinds of Summarizers", $data[C\CSRF_TOKEN]));
            ?>
        </div>
        <div class="top-margin"><b><label for="max-description-len"><?=
            tl('pageoptions_element_max_description')?></label></b>
            <?php $this->view->helper("options")->render("max-description-len",
            "max_description_len", $data['LEN_VALUES'], $data['MAX_LEN']);
            e($this->view->helper("helpbutton")->render(
                "Summary Length", $data[C\CSRF_TOKEN]));?></div>
        <div class="top-margin"><b><label for="cache-pages"><?=
            tl('pageoptions_element_save_cache')?>
            </label></b><input
            id='cache-pages' type="checkbox" name="cache_pages"
            value="true"
            <?php if (isset($data['CACHE_PAGES']) && $data['CACHE_PAGES']) {
                e("checked='checked'");
             }?>
            />
        </div>
        <div class="top-margin"><b><label for="allow-recrawl"><?=
            tl('pageoptions_element_allow_recrawl') ?></label></b>
            <?php $this->view->helper("options")->render(
                "page-recrawl-frequency",
                "page_recrawl_frequency", $data['RECRAWL_FREQS'],
                $data['PAGE_RECRAWL_FREQUENCY']);
            ?></div>
        <div class="top-margin"><b><?=
            tl('pageoptions_element_file_types')?></b>
       </div>
       <table class="file-types-all"><tr>
       <?php $cnt = 0;
             $num_types_per_column = ceil(count($data['INDEXED_FILE_TYPES'])/3);
             foreach ($data['INDEXED_FILE_TYPES'] as $filetype => $checked) {
                 if ($cnt % $num_types_per_column == 0) {
                    ?><td><table class="file-types-table" ><?php
                 }
       ?>
            <tr><td><label for="filetype-<?= $filetype ?>-id"><?=$filetype ?>
            </label></td><td><input type="checkbox" <?= $checked ?>
                name="filetype[<?=$filetype ?>]"
                id="filetype-<?= $filetype ?>-id"
                value="true" /></td>
            </tr>
       <?php
                $cnt++;
                if ($cnt % $num_types_per_column == 0) {
                    ?></table></td><?php
                }
            }?>
        <?php
            if ($cnt % $num_types_per_column != 0) {
                ?></table></td><?php
            }
        ?>
        </tr></table>
        <div class="top-margin"><b><?=
            tl('pageoptions_element_classifiers_rankers') ?></b> <?=
            $this->view->helper("helpbutton")->render(
                "Using a Classifier or Ranker", $data[C\CSRF_TOKEN]) ?>
       </div>
       <?php if (!empty($data['CLASSIFIERS'])) {
            $data['TABLE_TITLE'] ="";
            $data['ACTIVITY'] = 'pageOptions';
            $data['VIEW'] = $this->view;
            $data['NO_SEARCH'] = true;
            $data['NO_FORM_TAG'] = true;
            $data['FORM_TYPE'] = "";
            $data['NO_FLOAT_TABLE'] = true;
            $this->view->helper("pagingtable")->render($data);
       ?>
            <table class="classifiers-table" >
            <tr><th></th>
                <th><?=tl('pageoptions_element_use_classify') ?></th>
                <th><?=tl('pageoptions_element_use_rank') ?></th>
            </tr>
            <?php
            foreach ($data['CLASSIFIERS'] as $label => $class_checked) {
                if (isset($data['RANKERS'][$label])) {
                    $rank_checked = $data['RANKERS'][$label];
                } else {
                    $rank_checked = "";
                }
                ?>
                <tr><td><label for="classifier-<?= $label ?>-id"><?=$label ?>
                </label></td><td class="check"><input type="checkbox"
                    <?= $class_checked ?>
                    name="classifier[<?=$label ?>]"
                    id="classifier-<?= $label ?>-id" value="true" /></td>
                    <td class="check"><input type="checkbox"
                    <?= $rank_checked ?>
                    name="ranker[<?= $label ?>]"
                    id="ranker-<?= $label ?>-id" value="true" /></td>
                </tr>
            <?php
            }
            ?>
            </table>
        <?php
        } else {
            e("<p class='red'>".
                tl('pageoptions_element_no_classifiers').'</p>');
        } ?>
        <div class="top-margin"><b><?=
            tl("pageoptions_element_indexing_plugins")?></b> <?=
            $this->view->helper("helpbutton")->render(
                "Indexing Plugins", $data[C\CSRF_TOKEN]) ?></div>
        <?php if (isset($data['INDEXING_PLUGINS']) &&
            count($data['INDEXING_PLUGINS']) > 0) { ?>
            <table class="indexing-plugin-table">
                <tr><th><?= tl('pageoptions_element_plugin') ?></th>
                <th><?= tl('pageoptions_element_plugin_include') ?></th></tr>
                <?php
                $k = 0;
                foreach ($data['INDEXING_PLUGINS'] as
                    $plugin => $plugin_data) {
                    if ($plugin == "") { continue; }
                ?>
                <tr><td><?= $plugin. "Plugin" ?></td>
                <td class="check"><input type="checkbox"
                    name="INDEXING_PLUGINS[<?= $k ?>]"
                    value = "<?= $plugin ?>"
                    <?= $plugin_data['checked'] ?>
                    /><?php
                    if ($plugin_data['configure']) { ?>
                        [<a href="javascript:setDisplay('plugin-<?=
                            $plugin ?>', true);" ><?=
                            tl('pageoptions_element_configure')
                        ?></a>]<?php
                    }
                ?></td></tr>
            <?php
                $k++;
            }
            ?>
            </table>
        <?php
        } else {
            e("<p class='red'>".
                tl('pageoptions_element_no_compatible_plugins')."</p>");
        } ?>
        <div class="top-margin"><label for="page-rules"><b><?=
            tl('pageoptions_element_page_rules')?></b> <?=
            $this->view->helper("helpbutton")->render(
                "Page Rules", $data[C\CSRF_TOKEN]) ?></label>
        </div>
        <textarea class="short-text-area" id="page-rules"
            name="page_rules" ><?=$data['page_rules'] ?></textarea>
        </div>
        <div id='searchtimetab'>
        <h2><?= tl('page_element_search_page')?>
        <?= $this->view->helper("helpbutton")->render(
            "Search Results Page Elements", $data[C\CSRF_TOKEN]) ?></h2>
        <table class="search-page-all"><tr><td>
        <table class="search-page-table">
        <tr>
        <td><label for="wd-suggest"><?=
            tl('pageoptions_element_wd_suggest') ?></label></td>
            <td><input id='wd-suggest' type="checkbox"
            name="WORD_SUGGEST" value="true"
            <?php if (isset($data['WORD_SUGGEST']) &&
                $data['WORD_SUGGEST']) {
                e("checked='checked'");}?>
            /></td></tr>
        <tr><td><label for="subsearch-link"><?=
            tl('pageoptions_element_subsearch_link')?></label></td><td>
            <input id='subsearch-link'
            type="checkbox" name="SUBSEARCH_LINK" value="true"
            <?php if (isset($data['SUBSEARCH_LINK']) &&
                $data['SUBSEARCH_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="signin-link"><?=
            tl('pageoptions_element_signin_link') ?></label></td><td>
            <input id='signin-link' type="checkbox"
            name="SIGNIN_LINK" value="true"
            <?php if (isset($data['SIGNIN_LINK']) &&
                $data['SIGNIN_LINK']){ e("checked='checked'");}?>
            />
        </td></tr>
        <tr><td><label for="cache-link"><?=
            tl('pageoptions_element_cache_link') ?></label>
        </td><td><input id='cache-link' type="checkbox"
            name="CACHE_LINK" value="true"
            <?php if (isset($data['CACHE_LINK']) && $data['CACHE_LINK']){
                e("checked='checked'");}?>
            /></td></tr>
        </table></td>
        <td><table class="search-page-table">
        <tr><td><label for="similar-link"><?=
            tl('pageoptions_element_similar_link') ?></label></td>
        <td><input id='similar-link'
            type="checkbox" name="SIMILAR_LINK" value="true"
            <?php if (isset($data['SIMILAR_LINK']) &&
                $data['SIMILAR_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="in-link"><?=
            tl('pageoptions_element_in_link') ?></label></td>
        <td><input id='in-link' type="checkbox"
            name="IN_LINK" value="true"
            <?php if (isset($data['IN_LINK']) && $data['IN_LINK']){
                e("checked='checked'");}?>
            /></td></tr>
        <tr><td><label for="ip-link"><?=
            tl('pageoptions_element_ip_link') ?></label></td>
        <td><input id='ip-link' type="checkbox"
            name="IP_LINK" value="true"
            <?php if (isset($data['IP_LINK']) && $data['IP_LINK']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        <tr><td><label for="result-score"><?=
            tl('pageoptions_element_result_score') ?></label></td>
        <td><input id='result-score' type="checkbox"
            name="RESULT_SCORE" value="true"
            <?php if (isset($data['RESULT_SCORE']) && $data['RESULT_SCORE']){
                e("checked='checked'");}?>
            /></td>
        </tr>
        </table></td>
        </tr></table>
        <h2><?= tl('pageoptions_element_ranking_factors')?>
        <?= $this->view->helper("helpbutton")->render(
            "Page Ranking Factors", $data[C\CSRF_TOKEN]) ?></h2>
        <table class="weights-table" >
        <tr><th><label for="title-weight"><?=
            tl('pageoptions_element_title_weight') ?></label></th><td>
            <input type="text" id="title-weight" class="very-narrow-field"
                maxlength="<?= C\NUM_FIELD_LEN ?>" name="TITLE_WEIGHT"
                value="<?= $data['TITLE_WEIGHT']  ?>" /></td></tr>
        <tr><th><label for="description-weight"><?=
            tl('pageoptions_element_description_weight')?></label></th><td>
            <input type="text" id="description-weight" class="very-narrow-field"
                maxlength="<?= C\NUM_FIELD_LEN ?>" name="DESCRIPTION_WEIGHT"
                value="<?= $data['DESCRIPTION_WEIGHT'] ?>" /></td></tr>
        <tr><th><label for="link-weight"><?=
            tl('pageoptions_element_link_weight')?></label></th><td>
            <input type="text" id="link-weight" class="very-narrow-field"
                maxlength="<?= C\NUM_FIELD_LEN ?>" name="LINK_WEIGHT"
                value="<?= $data['LINK_WEIGHT'] ?>" /></td></tr>
        </table>
        <h2><?= tl('pageoptions_element_results_grouping_options') ?>
        <?= $this->view->helper("helpbutton")->render(
            "Page Grouping Options", $data[C\CSRF_TOKEN]) ?></h2>
        <table class="weights-table" >
        <tr><th><label for="min-results-to-group"><?=
            tl('pageoptions_element_min_results_to_group')?></label></th><td>
            <input type="text" id="min-results-to-group"
                class="very-narrow-field"
                maxlength="<?= C\NUM_FIELD_LEN ?>" name="MIN_RESULTS_TO_GROUP"
                value="<?= $data['MIN_RESULTS_TO_GROUP'] ?>" /></td></tr>
        <tr><th><label for="server-alpha"><?=
            tl('pageoptions_element_server_alpha')?></label></th><td>
            <input type="text" id="server-alpha" class="very-narrow-field"
                maxlength="<?=C\NUM_FIELD_LEN ?>" name="SERVER_ALPHA"
                value="<?= $data['SERVER_ALPHA'] ?>" /></td></tr>
        </table>
        </div>
        <div id='testoptionstab'>
        <h2><?= tl('pageoptions_element_test_page') ?>
        <?= $this->view->helper("helpbutton")->render(
            "Test Indexing a Page", $data[C\CSRF_TOKEN]) ?></h2>
        <div class="top-margin"><b><label for="page-type"><?=
            tl('pageoptions_element_page_type')?></label></b>
            <?php
            $types = $data['MIME_TYPES'];
            $this->view->helper("options")->render("page-type",
            "page_type", array_combine($types, $types),
            $data["page_type"]);
            ?></div>
        <textarea class="tall-text-area" id="testpage"
            name="TESTPAGE" ><?=$data['TESTPAGE'] ?></textarea>
        </div>

        </div>

        <div class="center slight-pad"><button class="button-box"
            id="page-button"
            type="submit"><?php if ($data['test_options_active'] == "") {
                e(tl('pageoptions_element_save_options'));
            } else {
                e(tl('pageoptions_element_run_tests'));
            }
            ?></button></div>
        </form>
        <div id="test-results">
        <?php if ($data['test_options_active'] != "") { ?>
            <h2><?=tl('pageoptions_element_test_results')?></h2>
            <?php
            if (strlen($_REQUEST['TESTPAGE']) > $data["PAGE_RANGE_REQUEST"]) {
                e("<h3 class='red'>".tl('pageoptions_element_page_truncated',
                strlen($_REQUEST['TESTPAGE']), $data["PAGE_RANGE_REQUEST"]).
                "</h3>");
            }
            if (isset($data["AFTER_PAGE_PROCESS"])) {
                e("<h3>".tl('pageoptions_element_after_process')."</h3>");
                e("<pre>\n{$data['AFTER_PAGE_PROCESS']}\n</pre>");
            }
            if (isset($data["AFTER_RULE_PROCESS"])) {
                e("<h3>".tl('pageoptions_element_after_rules')."</h3>");
                e("<pre>\n{$data['AFTER_RULE_PROCESS']}\n</pre>");
            }
            if (isset($data["EXTRACTED_WORDS"])) {
                e("<h3>".tl('pageoptions_element_extracted_words')."</h3>");
                e("<pre>\n{$data['EXTRACTED_WORDS']}\n</pre>");
            }
            if (isset($data["EXTRACTED_META_WORDS"])) {
                e("<h3>".tl('pageoptions_element_extracted_metas')."</h3>");
                e("<pre>\n{$data['EXTRACTED_META_WORDS']}\n</pre>");
            } ?>
        <?php
        } ?>
        </div>
        </div>
        <?php
        foreach ($data['INDEXING_PLUGINS'] as
            $plugin => $plugin_data) {
            $class_name = C\NS_PLUGINS . $plugin."Plugin";
            if ($plugin_data['configure']) {
            ?>
                <div class="indexing-plugin-lightbox" id="plugin-<?=$plugin?>" >
                <div class="light-content">
                <div class="float-opposite"><a  href="javascript:setDisplay(
                    'plugin-<?= $plugin ?>', false);"><?=
                    tl('page_element_plugin_back');
                ?></a></div>
                <?php
                    $plugin_object = new $class_name();
                    $plugin_object->configureView($data);
                ?>
            </div>
            </div>
        <?php
            }
        }
        ?>
        <script type="text/javascript">
        function switchTab(newtab, oldtab, oldtab2)
        {
            setDisplay(newtab, true);
            setDisplay(oldtab, false);
            setDisplay(oldtab2, false);
            ntab = elt(newtab + "item");
            if (ntab) {
                ntab.className = 'active';
            }
            otab = elt(oldtab + "item");
            if (otab) {
                otab.className = '';
            }
            otab2 = elt(oldtab2 + "item");
            if (otab2) {
                otab2.className = '';
            }
            ctype = elt('option-type');
            if (ctype) {
                ctype.value = (newtab == 'crawltimetab')
                    ? 'crawl_time' : ((newtab == 'searchtimetab') ?
                    'search_time' : 'test_options' );
                if (ctype.value == 'test_options') {
                    elt('page-button').innerHTML =
                        '<?= tl('pageoptions_element_run_tests') ?>';
                    elt('test-results').style.display = 'block';
                } else {
                    elt('page-button').innerHTML =
                        '<?= tl('pageoptions_element_save_options') ?>';
                    elt('test-results').style.display = 'none';
                }
            }
        }
        </script>
    <?php
    }
}
