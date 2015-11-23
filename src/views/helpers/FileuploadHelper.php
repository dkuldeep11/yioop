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
namespace seekquarry\yioop\views\helpers;

/**
 * This helper is used to render a drag and drop file upload region
 *
 * @author Chris Pollett
 */
class FileUploadHelper extends Helper
{
    /**
     *  Sets up that the common translation scripts for upload regions on
     *  the page have not been rendered yet.
     */
    public function __construct()
    {
        $this->isFileUploadInitialized = false;
        parent::__construct();
    }
    /**
     * Renders the UI needed to do a drag and file upload
     *
     * @param string $drop_id the id of the HTMLElement used as a target
     *      for dropping items in
     * @param string $form_name the name attribute of the web form input
     *      element used to handle file uploads
     * @param string $elt_id the id attribute of the web form input element
     *      used to handle file uploads
     * @param int $max_size the maximum size in bytes of the objects allowed
     *      to uploaded
     * @param string $drop_kind text or image this controls the way the drop
     *      zone is handled. Image will draw the to-upload image immediately
     * @param array $allowed_types what mime types are legal to upload
     * @param bool $multiple whether multiple files can be selected when
     *      file picker used
     */
    public function render($drop_id, $form_name, $elt_id, $max_size,
        $drop_kind, $allowed_types, $multiple = false)
    {
        if ($this->isFileUploadInitialized == false) {
            $this->setupFileUploadParams();
        }
        if ($drop_kind == "textarea") {
            $drag_above_text = tl('fileupload_helper_drag_textarea');
            $click_link_text = tl('fileupload_helper_click_textarea');
        } else {
            $drag_above_text = tl('fileupload_helper_drag_above');
            $click_link_text = tl('fileupload_helper_click_upload');
        }
        ?>
        <div class="upload-gray-box center black">
        <input type="file" id="<?= $elt_id ?>"
            name="<?= $form_name ?>" class="none"
            <?php
            if ($drop_kind == "image") {
                e(' accept="image/*" capture="true" ');
            } else if ($multiple) {
                e(' multiple="multiple" ');
            } ?> />
        <?= $drag_above_text ?>
        <a href="javascript:elt('<?= $elt_id ?>').click()"><?=
            $click_link_text ?></a>
        </div>
        <script type="text/javascript">
        window.addEventListener("load",
            function(event) {
                initializeFileHandler('<?php e($drop_id); ?>', '<?=
                    $elt_id ?>', <?= $max_size ?>, '<?= $drop_kind ?>', <?=
                    json_encode($allowed_types) ?>, <?=
                    ($multiple) ? "true" : "false" ?>);
            },
            false
        );
        </script>
        <?php
    }
    /**
     * Writes the common Javascript strings associated with file upload
     */
    public function setupFileUploadParams()
    {
        $this->isFileUploadInitialized = true;
        ?>
        <script type="text/javascript">
        if (typeof tl === 'undefined') {
            tl = Array();
        }
        tl["basic_js_invalid_filetype"] = '<?php
            e(tl("basic_js_invalid_filetype")); ?>';
        tl["basic_js_file_too_big"] = '<?php
            e(tl("basic_js_file_too_big")); ?>';
        tl["basic_js_upload_progress"] = '<?php
            e(tl("basic_js_upload_progress")); ?>';
        tl["basic_js_progress_meter_disabled"] = '<?php
            e(tl("basic_js_progress_meter_disabled")); ?>';
        tl["basic_js_upload_error"] = '<?php
            e(tl("basic_js_upload_error")); ?>';
        tl["basic_js_upload_cancelled"] = '<?php
            e(tl("basic_js_upload_cancelled")); ?>';
        tl["basic_js_too_many_files"] = '<?php
            e(tl("basic_js_too_many_files")); ?>';
        document.tl = tl;
        </script>
        <?php
    }
}
