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
namespace seekquarry\yioop\views\layouts;

use seekquarry\yioop\configs as C;

/**
 * Layout used for the seek_quarry Website
 * including pages such as search landing page
 * and settings page
 *
 * @author Chris Pollett
 */
class WebLayout extends Layout
{
    /**
     * Responsible for drawing the header of the document containing
     * Yioop! title and including basic.js. It calls the renderView method of
     * the View that lives on the layout. If the QUERY_STATISTIC config setting
     * is set, it output statistics about each query run on the database.
     * Finally, it draws the footer of the document.
     *
     * @param array $data  an array of data set up by the controller to be
     * be used in drawing the WebLayout and its View.
     */
    public function render($data)
    {
    ?>
    <!DOCTYPE html>
    <html lang="<?= $data['LOCALE_TAG'];
        ?>" dir="<?=$data['LOCALE_DIR']?>">
        <head>
        <title><?php if (isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['title']))
            e($this->view->head_objects[$data['page']]['title']);
        else e(tl('web_layout_title')); ?></title>
    <?php if (isset($this->view->head_objects['robots'])) {?>
        <meta name="ROBOTS" content="<?=$this->view->head_objects['robots']
        ?>" />
    <?php } ?>
        <meta name="description" content="<?php
        if (isset($data['page']) &&
            isset($this->view->head_objects[$data['page']]['description'])) {
                e($this->view->head_objects[$data['page']]['description']);
        } else {
            e(tl('web_layout_description'));
        } ?>" />
        <meta name="Author" content="<?=tl('web_layout_site_author') ?>" />
        <meta charset="utf-8" />
        <?php if (C\MOBILE) {?>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php }
            $path_info = C\BASE_URL;
            $aux_css = false;
            if (file_exists(C\APP_DIR.'/css/auxiliary.css')) {
                $aux_css = "$path_info?c=resource&a=get&f=css&n=auxiliary.css";
            }
            /* Remember to give complete paths to all link tag hrefs to
               avoid PRSSI attacks
               http://www.theregister.co.uk/2015/02/20/prssi_web_vuln/
             */
        ?>
        <link rel="shortcut icon"
            href="<?=C\FAVICON ?>" />
        <link rel="stylesheet" type="text/css"
            href="<?=$path_info ?>css/search.css" />
        <?php if ($aux_css) { ?>
        <link rel="stylesheet" type="text/css"
            href="<?=$aux_css ?>" />
        <?php
            }
            if (C\nsdefined("SEARCHBAR_PATH") && C\SEARCHBAR_PATH != "") {
        ?>
        <link rel="search" type="application/opensearchdescription+xml"
            href="<?=C\SEARCHBAR_PATH ?>"
            title="Content search" />
        <?php
            }
            if (isset($data['INCLUDE_STYLES'])) {
                foreach ($data['INCLUDE_STYLES'] as $style_name) {
                    e('<link rel="stylesheet" type="text/css"
                        href="'.C\BASE_URL.'css/'.
                        $style_name.'.css" />'."\n");
                }
            }
        ?>
        <style type="text/css">
        <?php
        $background_color = "#FFFFFF";
        if (C\nsdefined('BACKGROUND_COLOR')) {
            $background_color = isset($data['BACKGROUND_COLOR']) ?
                $data['BACKGROUND_COLOR'] : C\BACKGROUND_COLOR;
            ?>
            body
            {
                background-color: <?=$background_color ?>;
            }
            <?php
        }
        if (C\nsdefined('BACKGROUND_IMAGE') && C\BACKGROUND_IMAGE) {
            $background_image = isset($data['BACKGROUND_IMAGE']) ?
                $data['BACKGROUND_IMAGE'] : C\BACKGROUND_IMAGE;
            ?>
            body
            {
                background-image: url(<?=html_entity_decode(
                    $background_image) ?>);
                background-repeat: no-repeat;
                background-size: 12in;
            }
            body.mobile
            {
                background-size: 100%;
            }
            <?php
        }
        $foreground_color = "#FFFFFF";
        if (C\nsdefined('FOREGROUND_COLOR')) {
            $foreground_color = isset($data['FOREGROUND_COLOR']) ?
                $data['FOREGROUND_COLOR'] : C\FOREGROUND_COLOR;
            ?>
            .frame,
            .icon-upload,
            .current-activity,
            .light-content,
            .small-margin-current-activity,
            .suggest-list li span.unselected
            {
                background-color: <?=$foreground_color ?>;
            }
            .icon-upload
            {
                color: <?=$foreground_color ?>;
            }
            <?php
        }
        if (C\nsdefined('SIDEBAR_COLOR')) {
            ?>
            .activity-menu h2
            {
                background-color: <?=isset($data['SIDEBAR_COLOR']) ?
                    $data['SIDEBAR_COLOR'] : C\SIDEBAR_COLOR ?>;
            }
            .light-content,
            .mobile .light-content
            {
                border: 16px solid <?=isset($data['SIDEBAR_COLOR']) ?
                    $data['SIDEBAR_COLOR'] : C\SIDEBAR_COLOR ?>;
            }
            <?php
        }
        if (C\nsdefined('TOPBAR_COLOR')) {
            $top_color = (isset($data['TOPBAR_COLOR'])) ?
                $data['TOPBAR_COLOR'] : C\TOPBAR_COLOR;
            ?>
            .display-ad p,
            p.start-ad,
            .top-color,
            .suggest-list,
            .suggest-list li,
            .suggest-list li span.selected,
            .search-box {
                background-color: <?=$top_color ?>;
            }
            .top-bar,
            .landing-top-bar
            {
                background: <?=$top_color ?>;
                background: linear-gradient(to top, <?=
                    $background_color ?> 0%, <?=
                    $top_color ?> 30%, <?=$top_color ?> 70%);
            }
            <?php
        }
        ?>
        </style>
        </head>
        <?php
            $data['MOBILE'] = (C\MOBILE) ? 'mobile': '';
            flush();
        ?>
        <body class="html-<?=$data['BLOCK_PROGRESSION']?> html-<?=
            $data['LOCALE_DIR'] ?> html-<?= $data['WRITING_MODE'].' '.
            $data['MOBILE'] ?>" >
        <div class="body-container">
        <div id="message" ></div><?php
        $this->view->renderView($data);
        if (C\QUERY_STATISTICS && (!isset($this->presentation) ||
            !$this->presentation)) { ?>
        <div class="query-statistics">
        <?php
            e("<h1>".tl('web_layout_query_statistics')."</h1>");
            e("<div><b>".
                $data['YIOOP_INSTANCE']
                ."</b><br /><br />");
            e("<b>".tl('web_layout_total_elapsed_time',
                 $data['TOTAL_ELAPSED_TIME'])."</b></div>");
            foreach ($data['QUERY_STATISTICS'] as $query_info) {
                e("<div class='query'><div>".$query_info['QUERY'].
                    "</div><div><b>".
                    tl('web_layout_query_time',
                        $query_info['ELAPSED_TIME']).
                        "</b></div></div>");
            }
        ?>
        </div>
        <?php
        }
        ?>
        <script type="text/javascript" src="<?=C\BASE_URL
        ?>scripts/basic.js" ></script>
        <?php
        if ($this->view->helper('helpbutton')->is_help_initialized){
            if (!isset($data["INCLUDE_SCRIPTS"])) {
                $data["INCLUDE_SCRIPTS"] = [];
            }
            $data["INCLUDE_SCRIPTS"][] = "help";
        }
        if ($this->view->helper('helpbutton')->script) {
            if (!isset($data['SCRIPT'])) {
                $data['SCRIPT'] = "";
            }
            $data['SCRIPT'] = $this->view->helper('helpbutton')->script .
                   $data['SCRIPT'];
        }
        if (isset($data['INCLUDE_SCRIPTS'])) {
            foreach ($data['INCLUDE_SCRIPTS'] as $script_name) {
                if ($script_name == "math") {
                    e('<script type="text/javascript"
                        src="https://cdn.mathjax.org/mathjax/latest/MathJax.js'.
                        '?config=TeX-MML-AM_HTMLorMML"></script>');
                    // don't process math if html tag has class 'none'
                    e('<script type="text/x-mathjax-config">'.
                        'MathJax.Hub.Config({ asciimath2jax: { '.
                        'ignoreClass: "none" '.
                        '} });'.
                        '</script>');
                } else if ($script_name == "credit" &&
                    C\CreditConfig::isActive()) {
                    e('<script type="text/javascript" '.
                        'src="' . C\CreditConfig::getCreditTokenUrl() .
                        '" ></script>');
                } else {
                    e('<script type="text/javascript"
                        src="'.C\BASE_URL.'scripts/'.
                        $script_name.'.js" ></script>');
                }
            }
        }
        if (isset($data['INCLUDE_LOCALE_SCRIPT'])) {
            ?><script type="text/javascript"
                src="<?=C\BASE_URL
                ?>locale/<?=str_replace("-", "_", $data["LOCALE_TAG"])
                ?>/resources/locale.js" ></script><?php
        }
        ?>
        <script type="text/javascript" >
        <?php
        if (isset($data['SCRIPT'])) {
            e($data['SCRIPT']);
        }
        if (isset($data['DISPLAY_MESSAGE'])){
            e("\ndoMessage('<h1 class=\"red\" >".$data['DISPLAY_MESSAGE'].
                "</h1>');");
        }
        ?></script>
        </div>
        </body>
    </html><?php
    }
}
