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
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\models\Model;

/** For tl, getLocaleTag and Yioop constants */
require_once __DIR__.'/../../library/Utility.php';
/**
 * Element responsible for drawing wiki pages in either admin or wiki view
 * It is also responsible for rendering wiki history pages, and listings of
 * wiki pages available for a group
 *
 * @author Chris Pollett
 */
class WikiElement extends Element implements CrawlConstants
{
    /**
     * Draw a wiki page for group, or, depending on $data['MODE'] a listing
     * of all pages for a group, or the history of revisions of a given page
     * or the edit page form
     *
     * @param array $data fields contain data about the page being
     *      displayed or edited, or the list of pages being displayed.
     */
    public function render($data)
    {
        $logged_in = isset($data["ADMIN"]) && $data["ADMIN"];
        $can_edit = $logged_in && isset($data["CAN_EDIT"]) && $data["CAN_EDIT"];
        $is_admin = ($data["CONTROLLER"] == "admin");
        $arrows = ($is_admin) ? "expand.png" : "collapse.png";
        $other_controller = ($is_admin) ? "group" : "admin";
        $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
            $data["GROUP"]["GROUP_ID"]));
        $other_base_query = B\wikiUrl($data['PAGE_NAME'], true,
            $other_controller, $data["GROUP"]["GROUP_ID"]) .
            "arg=".$data['MODE'];
        $csrf_token = "";
        if ($logged_in) {
            $csrf_token = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
            $base_query .= $csrf_token;
        }
        if (isset($data['OTHER_BACK_URL'])) {
            $other_base_query .= $data['OTHER_BACK_URL'];
        }
        if ($logged_in) {
            $other_base_query .= "&amp;". $csrf_token;
        }
        if (($is_admin || $logged_in) && !C\MOBILE &&
            (!isset($data['page_type']) ||
            $data['page_type'] != 'presentation')) { ?>
            <div class="float-same admin-collapse sidebar"><a id='arrows-link'
            href="<?= $other_base_query ?>" onclick="
            arrows=elt('arrows-link');
            arrows_url = arrows.href;
            caret = (elt('wiki-page').selectionStart) ?
                elt('wiki-page').selectionStart : 0;
            edit_scroll = elt('scroll-top').value= (elt('wiki-page').scrollTop)?
                elt('wiki-page').scrollTop : 0;
            arrows_url += '&amp;caret=' + caret + '&amp;scroll_top=' +
                edit_scroll;
            arrows.href = arrows_url;" ><?=
            "<img src='" . C\BASE_URL .
                "resources/" . $arrows . "'/>" ?></a></div>
        <?php
        }
        ?>
        <?php
        if ($is_admin) {
            e('<div class="current-activity">');
        } else if (isset($data['page_type']) && $data['page_type']
            == 'presentation') {
            e('<div class="presentation-activity">');
            if (!$is_admin && isset($data['QUERY_STATISTICS'])) {
                $this->view->layout_object->presentation = true;
            }
        } else {
            $page_border = "";
            if (isset($data["HEAD"]['page_border']) &&
                $data["HEAD"]['page_border'] &&
                $data['HEAD']['page_border'] != 'none') {
                $page_border = $data['HEAD']['page_border'];
            }
            e('<div class="small-margin-current-activity '.$page_border.'">');
        }
        if (isset($data['BACK_URL'])) {
            e("<div class=\"float-opposite back-button\">" .
                "<a href=\"" . C\BASE_URL . "?" . $data['BACK_URL'] .
                "&amp;" . C\CSRF_TOKEN . "=" . $data[C\CSRF_TOKEN] . "\">" .
                tl('wiki_view_back') . "</a>" .
                "</div>");
        }
        if (isset($data['MEDIA_NAME'])) {
            ?>
            <div class="top-margin"><b><a href="<?= $base_query .
                '&amp;arg=read&amp;page_name='.
                $data['PAGE_NAME'] ?>"><?=$data['PAGE_NAME'] ?></a></b> : <?php
                $name_parts = pathinfo($data['MEDIA_NAME']);
                e($name_parts['filename']);?>
            </div>
            <?php
        } else if ($is_admin) {
            ?>
            <h2><?= $data['GROUP']['GROUP_NAME'].
                "[<a href='". htmlentities(
                B\feedsUrl("group", $data["GROUP"]["GROUP_ID"],
                true, $data["CONTROLLER"])) . $csrf_token."'>" .
                tl('groupfeed_element_feed').
                "</a>|".tl('wiki_view_wiki')."]"  ?></h2>
            <div class="top-margin"><b>
            <?php
            $human_page_name = str_replace("_", " ", $data['PAGE_NAME']);
            e(tl('wiki_view_page', $human_page_name) . " - [");
            $modes = [];
            if ($can_edit) {
                $modes = [
                    "read" => tl('wiki_view_read'),
                    "edit" => tl('wiki_view_edit')
                ];
            }
            $modes["pages"] = tl('wiki_view_pages');
            $bar = "";
            foreach ($modes as $name => $translation) {
                if ($data["MODE"] == $name) {
                    e($bar); ?><b><?= $translation ?></b><?php
                } else if (!isset($data["PAGE_NAME"]) ||
                    $data["PAGE_NAME"]=="") {
                    e($bar); ?><span class="gray"><?= $translation
                    ?></span><?php
                } else {
                    $append = "";
                    $page_name = ($name == 'pages') ?
                        'pages' : $data['PAGE_NAME'];
                    $arg = ($name == 'edit') ? '&amp;arg=' . $name : "";
                    if (isset($_REQUEST['noredirect'])) {
                        $append .= '&amp;noredirect=true';
                    }
                    if (isset($data['OTHER_BACK_URL'])) {
                        $append .= $data['OTHER_BACK_URL'];
                    }
                    e($bar); ?><a href="<?=htmlentities(B\wikiUrl(
                    $page_name, true, $data['CONTROLLER'],
                    $data["GROUP"]["GROUP_ID"])) . $csrf_token .
                    $arg . $append ?>"><?php
                    e($translation); ?></a><?php
                }
                $bar = "|";
            }
            ?>]</b>
            </div>
            <?php
        }
        switch ($data["MODE"]) {
            case "edit":
                $this->renderEditPageForm($data);
                break;
            case "pages":
                $this->renderPages($data, $can_edit, $logged_in);
                break;
            case "history":
                $this->renderHistory($data);
                break;
            case "read":
                // no break
            case "show":
            default:
                $this->renderReadPage($data, $can_edit, $logged_in, $is_admin);
                break;
            case "resources":
                $this->renderResources($data);
                break;
        }
        e('</div>');
    }
    /**
     * Used to draw a Wiki Page for reading. If the page does not exist
     * various create/login-to-create etc messages are displayed depending
     * of it the user is logged in. and has write permissions on the group
     *
     * @param array $data fields PAGE used for page contents
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whether current user is logged in or not
     * @param bool $is_admin whether or not this is on an admin controller page
     */
    public function renderReadPage($data, $can_edit, $logged_in, $is_admin)
    {
        if ($is_admin &&
            isset($data['PAGE_HEADER']) && isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            ?><?= $data['PAGE_HEADER'] ?><?php
        }
        if (isset($data["HEAD"]['page_type']) && $data["HEAD"]['page_type'] ==
            'media_list') {
            $this->renderResources($data, true, $logged_in);
        } else if ($data["PAGE"]) {
            ?><?= $data["PAGE"] ?><?php
        } else if (!empty($data["HEAD"]['page_alias'])) {
            $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
                $data["GROUP"]["GROUP_ID"]));
            if ($logged_in) {
                $csrf_token = C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
                $base_query .= $csrf_token;
            }
            $alias = $data["HEAD"]['page_alias'];
            $data['PAGE']["DESCRIPTION"] = tl('wiki_element_redirect_to').
                " <a href='$base_query&amp;".
                "page_name=$alias'>$alias</a>";
            ?>
            <?=$data['PAGE']["DESCRIPTION"] ?>
            <?php
        } else if ($can_edit) {
            ?>
            <h2><?= tl("wiki_view_page_no_exist", $data["PAGE_NAME"]) ?></h2>
            <p><?= tl("wiki_view_create_edit") ?></p>
            <p><?= tl("wiki_view_use_form_below") ?></p>
            <form id="editpageForm" method="get">
            <input type="hidden" name="c" value="<?= $data['CONTROLLER']?>" />
            <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="text" name="page_name" class="narrow-field"
                value="" />
            <button class="button-box" type="submit"><?= 
                tl('wiki_element_submit') ?></button>
            </form>
            <?php
            e("<p><a href=\"" . htmlentities(B\wikiUrl('Syntax', true,
                $data['CONTROLLER'], C\PUBLIC_GROUP_ID)) .
                C\CSRF_TOKEN .'='.$data[C\CSRF_TOKEN] . '&amp;arg=read'.
                "\">". tl('wiki_view_syntax_summary') .
                "</a>.</p>");
        } else if (!$logged_in) {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
            e("<p>".tl("wiki_view_signin_edit")."</p>");
        } else {
            e("<h2>".tl("wiki_view_page_no_exist", $data["PAGE_NAME"]).
                "</h2>");
        }
        if ($is_admin &&
            isset($data['PAGE_FOOTER']) && isset($data["HEAD"]['page_type']) &&
            $data["HEAD"]['page_type'] != 'presentation') {
            e($data['PAGE_FOOTER']);
        }
    }

    /**
     * Used to drawn the form that let's someone edit a wiki page
     *
     * @param array $data fields contain data about the page being
     *      edited. In particular, PAGE contains the raw page data
     */
    public function renderEditPageForm($data)
    {
        $simple_base_url = B\wikiUrl("", true,
            $data['CONTROLLER'], $data['GROUP']['GROUP_ID']) .
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN];
        $base_url = htmlentities($simple_base_url);
        $append = "";
        if (isset($data['OTHER_BACK_URL'])) {
            $append = $data['OTHER_BACK_URL'];
        }
        ?>
        <div class="float-opposite wiki-history-discuss" >
        [<a href="<?= $base_url . $append ?>&amp;<?=
            '&amp;arg=history&amp;page_id='.$data['PAGE_ID'] ?>"
        ><?= tl('wiki_element_history')?></a>]
        [<a href="<?=htmlentities(B\feedsUrl("thread", 
            $data['DISCUSS_THREAD'], true, $data['CONTROLLER'])) .
            C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN] ?>" ><?=
            tl('wiki_element_discuss')?></a>]
        </div>
        <form id="editpageForm" method="post"
            enctype="multipart/form-data"
            onsubmit="elt('caret-pos').value =
            (elt('wiki-page').selectionStart) ?
            elt('wiki-page').selectionStart : 0;
            elt('scroll-top').value= (elt('wiki-page').scrollTop) ?
            elt('wiki-page').scrollTop : 0;" >
            <input type="hidden" name="c" value="<?=$data['CONTROLLER']
            ?>" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="edit" />
            <?php
            if (isset($data['BACK_PARAMS'])) {
                foreach ($data["BACK_PARAMS"] as
                         $back_param_key => $back_param_value) {
                    e('<input type="hidden" '
                        . 'name="' . $back_param_key .
                        '" value="' .
                        $back_param_value
                        . '" />');
                }
            }
            ?>
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="hidden" name="page_name" value="<?=
                $data['PAGE_NAME'] ?>" />
            <input type="hidden" name="caret" id="caret-pos"/>
            <input type="hidden" name="scroll_top" id="scroll-top"/>
            <input type="hidden" id="p-settings" name="settings" value="<?=
                $data['settings'] ?>"/>
            <div class="top-margin">
                <b><?=tl('wiki_element_locale_name',
                    $data['CURRENT_LOCALE_TAG']) ?></b><br />
                <label for="page-data"><b><?php
                $human_page_name = str_replace("_", " ", $data['PAGE_NAME']);
                e(tl('wiki_element_page', $human_page_name));
                ?></b></label> <span id="toggle-settings"
                >[<a href="javascript:toggleSettings()"><?=
                tl('configure_element_toggle_page_settings') ?></a>]</span>
            </div>
            <div id='page-settings'>
            <div class="top-margin">
            <label for="page-type"><b><?=tl('wiki_element_page_type')
            ?></b></label><?php
            $this->view->helper("options")->render("page-type","page_type",
                $data['page_types'], $data['current_page_type'], true);
            ?>
            </div>
            <div id='alias-type'>
            <div class="top-margin">
            <label for="page-alias"><b><?=tl('wiki_element_page_alias')
            ?></b></label><input type="text" id='page-alias'
                name="page_alias" value="<?= $data['page_alias']?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            <div id='non-alias-type'>
            <div class="top-margin">
            <label for="page-border"><b><?=tl('wiki_element_page_border')
            ?></b></label><?php
            $this->view->helper("options")->render("page-border","page_border",
                $data['page_borders'], $data['page_border']);
            ?>
            </div>
            <div class="top-margin">
            <label for="page-toc"><b><?=tl('wiki_element_table_of_contents')
            ?></b></label><input type="checkbox" name="toc" value="true"
                <?php
                    $checked = (isset($data['toc']) && $data['toc']) ?
                    'checked="checked"' : '';
                    e( $checked );
                ?> id='page-toc' />
            </div>
            <div class="top-margin">
            <label for="page-title"><b><?=tl('wiki_element_title')
            ?></b></label><input type="text" id='page-title'
                name="title" value="<?=$data['title'] ?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-author"><b><?=tl('wiki_element_meta_author')
            ?></b></label><input type="text" id='meta-author'
                name="author" value="<?= $data['author']?>"
                maxlength="<?= C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-robots"><b><?=tl('wiki_element_meta_robots')
            ?></b></label><input type="text" id='meta-robots'
                name="robots" value="<?= $data['robots'] ?>"
                maxlength="<?=C\LONG_NAME_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="meta-description"><b><?=
                tl('wiki_element_meta_description')
            ?></b></label>
            </div>
            <textarea id="meta-description" class="short-text-area"
                name="description" data-buttons='none'><?=$data['description']
            ?></textarea>
            <div class="top-margin">
            <label for="page-header"><b><?=tl('wiki_element_page_header')
            ?></b></label><input type="text" id='page-header'
                name="page_header" value="<?=$data['page_header']?>"
                maxlength="<?=C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            <div class="top-margin">
            <label for="page-footer"><b><?=tl('wiki_element_page_footer')
            ?></b></label><input type="text" id='page-footer'
                name="page_footer" value="<?=$data['page_footer'] ?>"
                maxlength="<?= C\SHORT_TITLE_LEN ?>" class="wide-field"/>
            </div>
            </div>
            </div>
            <div id='page-container'><textarea id="wiki-page"
                class="tall-text-area" name="page"
                <?php
                if ((!isset($data['page_type']) ||
                        $data['page_type'] != 'presentation')){
                    $data_buttons = 'all,!wikibtn-slide';
                }else{
                    $data_buttons = 'all';
                }?>
                data-buttons='<?=$data_buttons ?>' ><?= $data['PAGE']
            ?></textarea>
            <div class="green center"><?php
            $this->view->helper("fileupload")->render(
                'wiki-page', 'page_resource', 'wiki-page-resource',
                L\metricToInt(ini_get('upload_max_filesize')), 'textarea', null,
                true);
            e(tl('wiki_element_archive_info'));
            ?></div>
            <div class="top-margin">
            <label for="edit-reason"><b><?= tl('wiki_element_edit_reason')
            ?></b></label><input type="text" id='edit-reason' name="edit_reason"
                  value="" maxlength="<?= C\SHORT_TITLE_LEN ?>"
                  class="wide-field"/></div>
            </div>
            <div id="save-container" class="top-margin center">
            <button class="button-box" type="submit"><?=
                tl('wiki_element_savebutton') ?></button>
            </div>
        </form>
        <div class="top-margin" id="media-list-page">
        <h2><?= tl('wiki_element_media_list')?></h2>
        <p><?= tl('wiki_element_ml_description')?></p>
        </div>
        <div id="page-resources">
        <h3><?= tl('wiki_view_page_resources')?></h3>
        <form id="resource-upload-form" method="post"
            enctype="multipart/form-data">
        <input type="hidden" name="c" value="<?= $data['CONTROLLER']
            ?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="edit" />
        <?php
            if (isset($data['BACK_PARAMS'])) {
                foreach ($data["BACK_PARAMS"] as
                         $back_param_key => $back_param_value) {
                    e('<input type="hidden" '
                        . 'name="' . $back_param_key .
                        '" value="' .
                        $back_param_value
                        . '" />');
                }
            }
        ?>
        <input type="hidden" name="group_id" value="<?=
            $data['GROUP']['GROUP_ID'] ?>" />
        <input type="hidden" name="page_name" value="<?=
            $data['PAGE_NAME'] ?>" />
        <input type="hidden" id="r-settings" name="settings" value="<?=
            $data['settings'] ?>" />
        <div class="center">
        <div id="current-media-page-resource" class="media-upload-box"
                >&nbsp;</div>
        <?php
        $this->view->helper("fileupload")->render(
            'current-media-page-resource', 'page_resource',
            'media-page-resource',L\metricToInt(ini_get('upload_max_filesize')),
            'text', null, true);
        ?><button class="button-box" type="submit"><?=tl('wiki_view_upload')
            ?></button>
        </div>
        </form>
        <p><?= tl('wiki_element_resources_info') ?></p>
        </div>
        <?php
            $this->renderResources($data, false);
        ?>
        <script type="text/javascript">
        function renameResource(old_name, id)
        {
            var name_elt = elt("resource-"+id);
            var new_name = "";
            if (name_elt) {
                new_name = name_elt.value;
            }
            if (!name_elt || !new_name) {
                doMessage('<h1 class=\"red\" ><?=
                    tl("wiki_element_rename_failed") ?></h1>');
                return;
            }
            var location = "<?= "$simple_base_url&arg=edit&page_name=".
                $data['PAGE_NAME'] ?>" + "&new_resource_name=" + new_name +
                "&old_resource_name=" + old_name;
            window.location = location;
        }
        </script>
        <?php
    }
    /**
     * Draws a list of media resources associated with a wiki page
     *
     * @param array $data fields RESOURCES_INFO contains info on resources
     * @param bool $read_mode whether the readering should be for a media
     *      list in read mode or for use on the edit task of any wiki page
     * @param bool $logged_in whether the user is currently logged in or not
     */
    public function renderResources($data, $read_mode, $logged_in = true)
    {
        if (isset($data['RESOURCES_INFO']) && $data['RESOURCES_INFO']) {
            $base_url = htmlentities(B\wikiUrl("", false, $data['CONTROLLER'],
                $data['GROUP']['GROUP_ID']));
            if ($logged_in) {
                $base_url .= "&amp;".C\CSRF_TOKEN.'='.$data[C\CSRF_TOKEN];
            }
            $url_prefix = $data['RESOURCES_INFO']['url_prefix'];
            if ($read_mode) {
                $url_prefix = $base_url. "&amp;arg=media&amp;page_id=".
                    $data["PAGE_ID"];
            } else {
                $base_url .= "&amp;settings=".$data['settings'];
            }
            $thumb_prefix = $data['RESOURCES_INFO']['thumb_prefix'];
            $default_thumb = $data['RESOURCES_INFO']['default_thumb'];
            if (count($data['RESOURCES_INFO']['resources']) > 0) {
                e('<table >');
                $seen_resources = [];
                $i = 0;
                foreach ($data['RESOURCES_INFO']['resources'] as $resource) {
                    $name = $resource['name'];
                    $name_parts = pathinfo($name);
                    $file_name = $name_parts['filename'];
                    if ($read_mode && isset($seen_resources[$file_name])) {
                        continue;
                    }
                    $seen_resources[$file_name] = true;
                    if (!$read_mode) {
                        $file_name = $name;
                    }
                    e("<tr class='resource-list' >");
                    e("<td><a href='$url_prefix&amp;n=$name'>");
                    if ($resource['has_thumb']) {
                        e("<img src='$thumb_prefix&amp;n=$name' alt='' />");
                    } else {
                        e("<img src='".$default_thumb."'  alt='' />");
                    }
                    e("</a></td><td>");
                    if ($read_mode) {
                        e("<a href='$url_prefix&amp;n=$name'>$name</a>");
                    } else {
                        e("<input type='text' id='resource-$i' ".
                            "value='$file_name' /></td>");
                        ?><td><button onclick='javascript:renameResource("<?=
                            $name?>", <?= $i ?>)' ><?=
                            tl('wiki_element_rename') ?></button><?php
                        if ((!isset($data['page_type']) ||
                            $data['page_type'] != 'media_list')) { ?>
                            <button onclick='javascript:addToPage("<?= $name
                            ?>", "wiki-page")'><?=tl('wiki_element_add_to_page')
                            ?></button> <?php
                        }
                        e("</td>");
                        $append = "";
                        if (isset($data['OTHER_BACK_URL'])) {
                            $append .= $data['OTHER_BACK_URL'];
                        }
                        e("<td>[<a href='$base_url&amp;arg=edit&amp;page_name=".
                            $data['PAGE_NAME'].
                            "&amp;delete=$name$append'>X</a>]</td>");
                    }
                    e("</tr>");
                    $i++;
                }
                e('</table>');
                return;
            }
        }
        ?>
        <div class='red'><?=tl('wiki_element_no_resources')?></div><?php
    }
    /**
     * Used to draw a list of Wiki Pages for the current group. It also
     * draws a search form and can be used to create pages
     *
     * @param array $data fields for the current controller, CSRF_TOKEN
     *     etc needed to render the search for and paging queries
     * @param bool $can_edit whether the current user has permissions to
     *     edit or create this page
     * @param bool $logged_in whethe current user is logged in or not
     */
    public function renderPages($data, $can_edit, $logged_in)
    {
        $token_string = ($logged_in) ? C\CSRF_TOKEN."=". $data[C\CSRF_TOKEN] :
            "";
        $group_id = $data["GROUP"]["GROUP_ID"];
        $controller = $data['CONTROLLER'];
        $create_query = htmlentities(B\wikiUrl($data["FILTER"], true,
            $controller, $group_id)) . $token_string . "&amp;arg=edit";
        $paging_query = htmlentities(B\wikiUrl("pages", true, $controller,
            $group_id)) . $token_string;
        ?><h2><?=tl("wiki_view_wiki_page_list", $data["GROUP"]["GROUP_NAME"]) 
        ?></h2><?php
        ?>
        <form id="editpageForm" method="get">
        <input type="hidden" name="c" value="<?=$data['CONTROLLER'] ?>" />
        <input type="hidden" name="<?= C\CSRF_TOKEN ?>" value="<?=
            $data[C\CSRF_TOKEN] ?>" />
        <input type="hidden" name="a" value="wiki" />
        <input type="hidden" name="arg" value="pages" />
        <input type="hidden" name="group_id" value="<?=
            $data['GROUP']['GROUP_ID'] ?>" />
        <input type="text" name="filter" class="extra-wide-field"
            maxlength="<?= C\SHORT_TITLE_LEN ?>"
            placeholder="<?= tl("wiki_view_filter_or_create")
            ?>" value="<?= $data['FILTER'] ?>" />
        <button class="button-box" type="submit"><?=tl('wiki_element_go')
            ?></button>
        </form>
        <?php
        if ($data["FILTER"] != "") {
            ?><a href='<?= $create_query ?>'><?=tl("wiki_view_create_page",
                $data['FILTER']) ?></a><?php
        }
        ?>
        <div>&nbsp;</div>
        <?php
        if ($data['PAGES'] != []) {
            foreach ($data['PAGES'] as $page) {
                $ellipsis = (mb_strlen($page["DESCRIPTION"]) >
                    Model::MIN_SNIPPET_LENGTH) ? "..." : "";
                if ($page['TYPE'] == 'page_alias' && isset($page['ALIAS'])) {
                    $page["DESCRIPTION"] = tl('wiki_element_redirect_to').
                        " <a href='".htmlentities(B\wikiUrl($page['ALIAS'],
                        true, $controller, $group_id)) . $token_string .
                        "'>{$page['ALIAS']}</a>";
                } else {
                    $page["DESCRIPTION"] = strip_tags($page["DESCRIPTION"]);
                }
                ?>
                <div class='group-result'>
                <a href="<?= htmlentities(B\wikiUrl($page['TITLE'],
                    true, $controller, $group_id)) . $token_string
                    ?>&amp;noredirect=true" ><?=$page["TITLE"] ?></a></br />
                <?=$page["DESCRIPTION"].$ellipsis ?>
                </div>
                <div>&nbsp;</div>
                <?php
            }
            $this->view->helper("pagination")->render(
                $paging_query,
                $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        }
        if ($data['PAGES'] == []) {
            ?><div><?=tl('wiki_view_no_pages', "<b>".L\getLocaleTag()."</b>")?>
            </div><?php
        }
    }

    /**
     * Used to draw the revision history page for a wiki document
     * Has a form that can be used to draw the diff of two revisions
     *
     * @param array $data fields contain info about revisions of a Wiki page
     */
    public function renderHistory($data)
    {
        $base_query = htmlentities(B\wikiUrl("", true, $data['CONTROLLER'],
            $data["GROUP"]["GROUP_ID"]) .C\CSRF_TOKEN."=".
            $data[C\CSRF_TOKEN]);
        $append = "";
        if (isset($data['OTHER_BACK_URL']) && $data['OTHER_BACK_URL'] != '') {
            $append = $data['OTHER_BACK_URL'];
        }
        ?><div class="float-opposite"><a href="<?=$base_query .
            '&amp;arg=edit&amp;page_name=' .
            $data['PAGE_NAME'] . $append ?>"><?=
            tl("wiki_view_back") ?></a></div>
        <?php
        if (count($data['HISTORY']) > 1) { ?>
            <div>
            <form id="differenceForm" method="get">
            <input type="hidden" name="c" value="<?=$data['CONTROLLER']
             ?>" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="wiki" />
            <input type="hidden" name="arg" value="history" />
            <input type="hidden" name="group_id" value="<?=
                $data['GROUP']['GROUP_ID'] ?>" />
            <input type="hidden" name="page_id" value="<?=
                $data["page_id"] ?>" />
            <input type="hidden" name="diff" value="1" />
            <b><?=tl('wiki_view_difference') ?></b>
            <input type="text" id="diff-1" name="diff1"
                value="<?=$data['diff1'] ?>" /> -
            <input type="text" id="diff-2" name="diff2"
                value="<?= $data['diff2'] ?>" />
            <button class="button-box" type="submit"><?=
                tl('wiki_view_go') ?></button>
            </form>
            </div>
            <?php
        }
        ?>
        <div>&nbsp;</div>
        <?php
        $time = time();
        $feed_helper = $this->view->helper("feeds");
        $base_query .= "&amp;arg=history&amp;page_id=".$data["page_id"];
        $current = $data['HISTORY'][0]["PUBDATE"];
        $first = true;
        foreach ($data['HISTORY'] as $item) {
            ?>
            <div class='group-result'>
            <?php
            if (count($data['HISTORY']) > 1) { ?>
                (<a href="javascript:updateFirst('<?=$item['PUBDATE']
                    ?>');" ><?= tl("wiki_view_diff_first")
                    ?></a> | <a href="javascript:updateSecond('<?=
                    $item['PUBDATE']?>');" ><?= tl("wiki_view_diff_second")
                    ?></a>)
                <?php
            } else { ?>
                (<b><?= tl("wiki_view_diff_first")
                    ?></b> | <b><?= tl("wiki_view_diff_second")
                    ?></b>)
                <?php
            }
            e("<a href='$base_query&show={$item['PUBDATE']}'>" .
                date("c",$item["PUBDATE"])."</a>. <b>{$item['PUBDATE']}</b>. ");
            e(tl("wiki_view_edited_by", $item["USER_NAME"]));
            if (strlen($item["EDIT_REASON"]) > 0) {
                e("<i>{$item["EDIT_REASON"]}</i>. ");
            }
            e(tl("wiki_view_page_len", $item["PAGE_LEN"])." ");
            if ($first && $data['LIMIT'] == 0) {
                e("[<b>".tl("wiki_view_revert")."</b>].");
            } else {
                e("[<a href='$base_query&amp;revert=".$item['PUBDATE'].
                "'>".tl("wiki_view_revert")."</a>].");
            }
            $first = false;
            $next = $item['PUBDATE'];
            ?>
            </div>
            <div>&nbsp;</div>
            <?php
        }
        $this->view->helper("pagination")->render(
            $base_query,
            $data['LIMIT'], $data['RESULTS_PER_PAGE'], $data['TOTAL_ROWS']);
        ?>
        <script type="text/javascript">
        function updateFirst(val)
        {
            elt('diff-1').value=val;
        }
        function updateSecond(val)
        {
            elt('diff-2').value=val;
        }
        </script>
        <?php
    }
}
