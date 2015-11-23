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
 * Element used to control how urls are filtered out of search results
 * (if desired) after a crawl has already been performed.
 *
 * @author Chris Pollett
 */
class ResultsEditorElement extends Element
{
    /**
     * Draws the Screen for the Search Filter activity. This activity is
     * used to filter urls out of the search results
     *
     * @param array $data keys used to store disallowed_sites
     */
    public function render($data)
    {
    ?>
        <div class="current-activity">
        <h2><?= tl('resultseditor_element_edit_page') ?>
        <?= $this->view->helper("helpbutton")->render(
        "Search Results Editor", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="urlLookupForm" method="post">
        <div  class="top-margin"><b><label for="edited-result-pages"><?=
            tl('resultseditor_element_edited_pages')?></label></b>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="load_url" />
        <?php $this->view->helper("options")->render(
                "edited-result-pages", "LOAD_URL",
                $data['URL_LIST'],
                tl('resultseditor_element_url_list'));
            ?><button class="button-box" type="submit" ><?=
            tl('resultseditor_element_load_page')
            ?></button>
        </div>
        </form>

        <form id="urlUpdateForm" method="post" >
        <div  class="top-margin">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="save_page" />
        <b><label for="urlfield"><?=
            tl('resultseditor_element_page_url')?></label></b>
        <input type="url" id="urlfield"
            name="URL"  class="extra-wide-field" value='<?=$data["URL"] ?>' />
        </div>
        <div  class="top-margin">
        <b><label for="titlefield"><?=
            tl('resultseditor_element_page_title')?></label></b>
        <input type="text" id="titlefield"
            name="TITLE"  class="extra-wide-field" value='<?=$data["TITLE"]
            ?>' />
        </div>
        <div class="top-margin"><label for="descriptionfield"><b><?=
            tl('resultseditor_element_description') ?></b></label></div>
        <textarea class="tall-text-area" id="descriptionfield"
            name="DESCRIPTION" ><?=$data['DESCRIPTION'] ?></textarea>
        <div class="center slight-pad"><button class="button-box"
            type="reset"><?=tl('resultseditor_element_reset')
            ?></button> &nbsp;&nbsp; <button class="button-box"
            type="submit" ><?=tl('resultseditor_element_save_page')
            ?></button></div>
        </form>
        <h2><?= tl('resultseditor_element_filter_websites')?>
         <?=$this->view->helper("helpbutton")->render(
            "Filtering Search Results", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="searchfiltersForm" method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="resultsEditor" />
        <input type="hidden" name="arg" value="urlfilter" />
        <input type="hidden" name="posted" value="posted" />

        <div class="top-margin"><label for="disallowed-sites"><b><?=
            tl('resultseditor_element_sites_to_filter') ?></b></label></div>
        <textarea class="tall-text-area" id="disallowed-sites"
            name="disallowed_sites" ><?= $data['disallowed_sites']
        ?></textarea>
        <div class="center slight-pad"><button class="button-box"
            type="submit"><?= tl('resultseditor_element_save_filter')
            ?></button></div>
        </form>
        </div>
    <?php
    }
}
