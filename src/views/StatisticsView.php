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
 * Draws a view displaying statistical information about a
 * web crawl such as number of hosts visited, distribution of
 * file sizes, distribution of file type, distribution of languages, etc
 *
 * @author Chris Pollett
 */
class StatisticsView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * Draws the web page used to display statistics about the default crawl
     *
     * @param array $data   contains anti CSRF token as well
     *     statistics info about a web crawl
     */
    public function renderView($data) {
        $base_url = C\BASE_URL;
        $delim = "?";
        $logo = C\BASE_URL . C\LOGO;
        $query_array = [];
        if (!empty($data['ADMIN'])) {
            $query_array[C\CSRF_TOKEN] = $data[C\CSRF_TOKEN];
            $base_url = $base_url . "?" .http_build_query($query_array);
            $delim = "&";
        }
        $query_array['its'] = $data['its'];
        $its_url = B\controllerUrl('statistics') . "?" .
            http_build_query($query_array);
        if (C\MOBILE) {
            $logo = C\M_LOGO;
        }
        if (isset($data["UNFINISHED"])) {
            ?><div class="landing" style="clear:both"><?php
        }?>
        <h1 class="stats logo"><a href="<?=$base_url
            ?>"><img src="<?= $logo ?>" alt="<?= $this->logo_alt_text
            ?>" /></a><span> - <?=tl('statistics_view_statistics')?></span></h1>
        <div class="statistics">
        <?php

        if (isset($data["UNFINISHED"])) {
            e("<h1 class='center'>".tl('statistics_view_calculating')."</h1>");
            e("<h2 class='red center' style='text-decoration:blink'>"
                .$data["stars"]."</h2>");
            ?>
            <script type="text/javascript">
                function continueCalculate()
                {
                    window.location = '<?=
                        "$its_url&stars=".$data["stars"] ?>';
                }
                setTimeout("continueCalculate()", 2000);
            </script>
        <?php } else {
            $headings = [
                tl("statistics_view_error_codes") => "CODE",
                tl("statistics_view_sizes") => "SIZE",
                tl("statistics_view_links_per_page") => "NUMLINKS",
                tl("statistics_view_page_date") => "MODIFIED",
                tl("statistics_view_dns_time") => "DNS",
                tl("statistics_view_download_time") => "TIME",
                tl("statistics_view_top_level_domain") => "SITE",
                tl("statistics_view_file_extension") => "FILETYPE",
                tl("statistics_view_media_type") => "MEDIA",
                tl("statistics_view_language") => "LANG",
                tl("statistics_view_server") => "SERVER",
                tl("statistics_view_os") => "OS",
            ];
        ?>
        <h2><?= tl("statistics_view_general_info") ?></h2>
        <p><b><?= tl("statistics_view_description") ?></b>:
        <?= $data["DESCRIPTION"] ?></p>
        <p><b><?= tl("statistics_view_timestamp") ?></b>:
        <?= $data["TIMESTAMP"] ?></p>
        <p><b><?= tl("statistics_view_crawl_date") ?></b>:
        <?= date("r",$data["TIMESTAMP"]) ?></p>
        <p><b><?= tl("statistics_view_pages") ?></b>:
        <?= $data["VISITED_URLS_COUNT"] ?></p>
        <p><b><?= tl("statistics_view_url") ?></b>:
        <?= $data["COUNT"] ?></p>
        <?php if (isset($data["HOST"]["DATA"]["all"])) { ?>
            <p><b><?= tl("statistics_view_number_hosts") ?></b>:
            <?= $data["HOST"]["DATA"]["all"] ?></p>
        <?php
        }
            foreach ($headings as $heading => $group_name) {
                if (isset($data[$group_name]["TOTAL"])) { ?>
                    <h2><?= $heading ?></h2>
                    <table summary= "<?=$heading ?> TABLE" class="box">
                        <?php
                            $total = $data[$group_name]["TOTAL"];
                            $lower_name = strtolower($group_name);
                            foreach ($data[$group_name]["DATA"] as
                                $name => $value) {
                                $width = round(500 * $value / (max($total,1)));
                                e("<tr><th><a href='".$base_url . $delim .
                                    "q=$lower_name:$name' rel='nofollow'>".
                                    "$name</a></th>".
                                    "<td><div style='background-color:green;".
                                        "width:{$width}px;' >$value</div>".
                                    " </td></tr>");
                            } ?>
                    </table>
                <?php
                }
            }
        }
    ?>
    </div>
    <?php
        if (isset($data["UNFINISHED"])) {
            ?></div><div class='landing-spacer'></div><?php
        }
    }
}
