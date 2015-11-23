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

use seekquarry\yioop\configs as C;

/**
 *
 * Draws the view on which people can control
 * their search settings such as num links per screen
 * and the language settings
 *
 * @author Chris Pollett
 */
class SettingsView extends View
{
    /** This view is drawn on a web layout
     * @var string
     */
    public $layout = "web";
    /**
     * sDraws the web page on which users can control their search settings.
     *
     * @param array $data   contains anti CSRF token as well
     *     the language info and the current and possible per page settings
     */
    public function renderView($data) {
    $logo = C\LOGO;
    $logged_in = isset($data['ADMIN']) && $data['ADMIN'];
    if (C\MOBILE) {
        $logo = C\M_LOGO;
    }
?>
<div class="landing non-search">
<h1 class="logo"><a href="<?=C\BASE_URL ?>?<?php if ($logged_in) {
        e(C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN]. "&amp;");
    } ?>its=<?= $data['its']?>"><img src="<?=C\BASE_URL . $logo ?>" alt="<?=
    $this->logo_alt_text ?>" /></a><span> - <?=
    tl('settings_view_settings') ?></span>
</h1>
<div class="settings">
<form method="post">
<table>
<tr>
<td class="table-label"><label for="per-page"><b><?=
    tl('settings_view_results_per_page') ?></b></label></td><td
    class="table-input"><?php $this->helper("options")->render(
    "per-page", "perpage", $data['PER_PAGE'], $data['PER_PAGE_SELECTED']); ?>
</td></tr>
<tr>
<td class="table-label"><label for="open-in-tabs"><b><?=
    tl('settings_view_open_in_tabs') ?></b></label></td><td
    class="table-input"><input type="checkbox" id="open-in-tabs"
        name="open_in_tabs" value="true"
        <?php  if ($data['OPEN_IN_TABS']) {?>checked='checked'<?php } ?> />
</td></tr>
<tr>
<td class="table-label"><label for="index-ts"><b><?=
    tl('settings_view_search_index') ?></b></label></td><td
    class="table-input"><?php $this->helper("options")->render(
    "index-ts", "index_ts", $data['CRAWLS'], $data['its']); ?>
</td></tr><?php
if (count($data['LANGUAGES']) > 1) { ?>
<tr><td class="table-label"><label for="locale"><b><?=
    tl('settings_view_language_label') ?></b></label></td><td
    class="table-input"><?php $this->element("language")->render($data); ?>
</td></tr><?php
} ?>
<tr><td class="cancel"><input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
    $data[C\CSRF_TOKEN] ?>" /><?php if (isset($data['return'])){ ?><input
        type="hidden" name="return" value="<?= $data['return'] ?>" />
    <?php } ?><?php if (isset($data['oldc'])){ ?><input
        type="hidden" name="oldc" value="<?= $data['oldc'] ?>" />
    <?php } ?><input type="hidden" name="its" value="<?=$data['its'] 
    ?>" /><button class="top-margin" name="c" value="search" <?php
        if (isset($data['RETURN'])) {
            e(' onclick="javascript:window.location.href='."'".
            $data['RETURN']."'".';return false;"');
        } ?>><?php e(tl('settings_view_return'));
    ?></button></td><td class="table-input">
<button class="top-margin" type="submit" name="c" value="settings"><?=
    tl('settings_view_save') ?></button>
</td></tr>
</table>
</form>
</div>
<div class="setting-footer"><a
    href="javascript:window.external.AddSearchProvider('<?= C\SEARCHBAR_PATH
    ?>')"><?= tl('settings_install_search_plugin')
?></a>.</div>
</div>
<div class='landing-spacer'></div><?php
    }
}
