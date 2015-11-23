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
 * Element responsible for displaying info about starting, stopping, deleting,
 * and using a crawl. It makes use of the CrawlStatusView
 *
 * @author Chris Pollett
 */
class ManagecrawlsElement extends Element
{
    /**
     * Draw form to start a new crawl, has div place holder and ajax code to
     * get info about current crawl
     *
     * @param array $data  information about a crawl such as its description
     */
    public function render($data)
    {?>
        <div class="current-activity">
        <h2><?= tl('managecrawls_element_create_crawl') ?></h2>
        <form id="crawlStartForm" method="get">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="manageCrawls" />
        <input type="hidden" name="arg" value="start" />

        <p><label for="description-name"><?=
            tl('managecrawls_element_description') ?></label>:
            <input type="text" id="description-name" name="description"
                value="<?php if (isset($data['DESCRIPTION'])) {
                    e($data['DESCRIPTION']); } ?>" maxlength="<?=
                    C\TITLE_LEN ?>"
                    class="wide-field"/>
            <button class="button-box" type="submit"><?=
                tl('managecrawls_element_start_new_crawl') ?></button>
            <a href="?c=admin&amp;a=manageCrawls<?php
                ?>&amp;arg=options&amp;<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"><?=
                tl('managecrawls_element_options') ?></a>
            <?= "&nbsp;" .$this->view->helper("helpbutton")->render(
                "Start Crawl", $data[C\CSRF_TOKEN]) ?>
        </p>
        </form>
        <div id="crawlstatus" >
        <h2><?=tl('managecrawls_element_awaiting_status')?></h2>
        </div>
        <script type="text/javascript" >
        var updateId;
        function crawlStatusUpdate()
        {
            var startUrl = "?c=admin&<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>&a=crawlStatus<?=
                '&num_show='.$data['NUM_SHOW']."&start_row=".
                    $data['START_ROW']."&end_row=".$data['END_ROW'] ?>";
            var crawlTag = elt('crawlstatus');
            getPage(crawlTag, startUrl);
        }
        function clearUpdate()
        {
             clearInterval(updateId );
             var crawlTag = elt('crawlstatus');
             crawlTag.innerHTML= "<h2 class='red'><?=
                tl('managecrawls_element_up_longer_update')?></h2>";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60*sec;
             crawlStatusUpdate();
             updateId = setInterval("crawlStatusUpdate()", 30*sec);
             setTimeout("clearUpdate()", 20*minute + sec);
        }
        </script>
        </div>
    <?php
    }
}
