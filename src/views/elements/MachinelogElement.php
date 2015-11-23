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
 * Element responsible for displaying the queue_server or fetcher log
 * of a machine
 *
 * @author Chris Pollett
 */
class MachinelogElement extends Element
{
    /**
     * Draws the log file of a queue_server or a fetcher
     *
     * @param array $data LOG_FILE_DATA has the log data
     */
    public function render($data)
    {
        $admin_url = htmlentities(B\controllerUrl('admin', true));
        ?>
        <div class="current-activity">
        <div class="<?=$data['leftorright'] ?>">
            <div class="<?=$data['leftorright']?>">
            <a href="<?=$admin_url ?>a=manageMachines&amp;<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>"
            ><?= tl('machinelog_element_back_to_manage')?></a>
            </div>
            <form method="get" action="javascript:logUpdate();" >
                <table class="clear">
                <tr><th><label for="set-filter">
                <?= tl('machinelog_element_filter') ?></label></th>
                 <td><input type="text" class="narrow-field" id="set-filter"
                    onchange="javascript:logUpdate();"
                    value="<?= $data['filter']; ?>" /></td></tr>
                </table>
            </form>
        </div>
        <h2><?= tl('machinelog_element_log_file',$data['LOG_TYPE']) ?></h2>
        <?php if (!$data['NO_REFRESH']) {?>
        <p>[<a href="<?=$admin_url .
            C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]?>&amp;a=manageMachines<?=
                $data['REFRESH_LOG'] ?>&amp;NO_REFRESH=true" ><?=
                tl('machinelog_element_refresh_off')  ?></a>]</p>
        <?php } else { ?>
        <p>[<a href="<?=$admin_url . C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]
                ?>&amp;a=manageMachines<?=$data['REFRESH_LOG'] ?>"><?=
                tl('machinelog_element_refresh_on') ?></a>]</p>
        <?php } ?>
        <pre><?=htmlentities(wordwrap($data["LOG_FILE_DATA"], 60, "\n", true))
        ?></pre>
        <?php if (!$data['NO_REFRESH']) {?>
         <script type="text/javascript" >
        var updateId;
        function logUpdate()
        {
            var filter = elt('set-filter').value;
            var refreshUrl= "<?=B\controllerUrl('admin', true)
                ?>f=" + filter + "&<?=
                C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN] ?>&a=manageMachines<?=
                $data['REFRESH_LOG'] ?>";
            document.location = refreshUrl;
        }
        function doUpdate()
        {
             var sec = 1000;
             updateId = setInterval("logUpdate()", 30*sec);
        }
        </script>
        <?php } else {?>
         <script type="text/javascript" >
        function doUpdate() {}
        </script>
        <?php } ?>
    <?php
    }
}
