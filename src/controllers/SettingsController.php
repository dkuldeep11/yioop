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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;

/**
 * Controller used to handle search requests to SeekQuarry
 * search site. Used to both get and display
 * search results.
 *
 * @author Chris Pollett
 */
class SettingsController extends Controller
{
    /**
     * Sets up the available perpage language options.
     * If handling data sent from a  form, it stores cleaned versions of
     * the number of results per page and language options into a sesssion
     *
     */
    public function processRequest()
    {
        $data = [];
        $view = "settings";
        $changed_settings_flag = false;
        $crawl_model = $this->model("crawl");
        if (isset($_SESSION['USER_ID']) && isset($_REQUEST[C\CSRF_TOKEN])) {
            $user = $_SESSION['USER_ID'];
            $token_okay = $this->checkCSRFToken(C\CSRF_TOKEN, $user);
            $data['ADMIN'] = 1;
        } else {
            $user = $_SERVER['REMOTE_ADDR'];
            $token_okay = true;
        }
        if (!$token_okay) {
            $user = $_SERVER['REMOTE_ADDR'];
            unset($_SESSION['USER_ID']);
        }
        $data[C\CSRF_TOKEN] = $this->generateCSRFToken($user);
        $languages = $this->model("locale")->getLocaleList();
        foreach ($languages as $language) {
            $data['LANGUAGES'][$language['LOCALE_TAG']] =
                $language['LOCALE_NAME'];
        }
        if ($token_okay && isset($_REQUEST['lang']) &&
            in_array($_REQUEST['lang'], array_keys($data['LANGUAGES']))) {
            $_SESSION['l'] = $_REQUEST['lang'];
            L\setLocaleObject( $_SESSION['l']);
            $changed_settings_flag = true;
        }
        $data['LOCALE_TAG'] = L\getLocaleTag();
        $n = C\NUM_RESULTS_PER_PAGE;
        $data['PER_PAGE'] =
            [$n => $n, 2 * $n => 2 * $n, 5 * $n => 5 * $n, 10 * $n => 10 * $n];
        if ($token_okay && isset($_REQUEST['perpage']) &&
            in_array($_REQUEST['perpage'], array_keys($data['PER_PAGE']))) {
            $_SESSION['MAX_PAGES_TO_SHOW'] = $_REQUEST['perpage'];
            $changed_settings_flag = true;
        }
        if (isset($_SESSION['MAX_PAGES_TO_SHOW'])){
            $data['PER_PAGE_SELECTED'] = $_SESSION['MAX_PAGES_TO_SHOW'];
        } else {
            $data['PER_PAGE_SELECTED'] = C\NUM_RESULTS_PER_PAGE;
        }
        if ($token_okay &&  isset($_REQUEST['perpage'])) {
            $_SESSION['OPEN_IN_TABS'] = (isset($_REQUEST['open_in_tabs'])) ?
                true : false;
        }
        if (isset($_SESSION['OPEN_IN_TABS'])){
            $data['OPEN_IN_TABS'] = $_SESSION['OPEN_IN_TABS'];
        } else {
            $data['OPEN_IN_TABS'] = false;
        }
        $machine_urls = $this->model("machine")->getQueueServerUrls();
        $crawls = $crawl_model->getCrawlList(false, true, $machine_urls,
            true);
        $data['CRAWLS'] = [];
        foreach ($crawls as $crawl) {
            $data['CRAWLS'][$crawl['CRAWL_TIME']] = $crawl['DESCRIPTION'].
                " ... ".$crawl['COUNT']." urls";
        }
        $mixes = $crawl_model->getMixList($user);
        foreach ($mixes as $mix) {
            $data['CRAWLS'][$mix['TIMESTAMP']] = $mix['NAME'].
                " ... ".tl('settings_controller_crawl_mix');
        }
        $crawl_stamps = array_keys($data['CRAWLS']);
        if ($token_okay) {
            $changed_settings_flag = $this->loggedInChangeSettings($data);
        } else if (isset($_REQUEST['its']) &&
            in_array($_REQUEST['its'],$crawl_stamps)){
            $data['its'] = $_REQUEST['its'];
        } else {
            $data['its'] = $crawl_model->getCurrentIndexDatabaseName();
        }

        if ($changed_settings_flag) {
            $this->model("user")->setUserSession($user, $_SESSION);
            return $this->redirectWithMessage(
                tl('settings_controller_settings_saved'),
                ['return', 'oldc']);
        }
        $this->displayView($view, $data);
    }
    /**
     * Changes settings for a logged in user, this might involve storing
     * data into the active session.
     *
     * @param array& $data fields which might be sent to the view
     * @return bool if any settings were changed
     */
    public function loggedInChangeSettings(&$data)
    {
        $crawl_model = $this->model("crawl");
        $crawl_stamps = array_keys($data['CRAWLS']);
        $changed_settings_flag = false;
        if (isset($_REQUEST['index_ts']) &&
            in_array($_REQUEST['index_ts'], $crawl_stamps)) {
            $_SESSION['its'] = $_REQUEST['index_ts'];
            $data['its'] = $_REQUEST['index_ts'];
            $changed_settings_flag = true;
        } else if (isset($_SESSION['its']) &&
            in_array($_SESSION['its'], $crawl_stamps)) {
            $data['its'] = $_SESSION['its'];
        } else {
            $data['its'] = $crawl_model->getCurrentIndexDatabaseName();
        }
        if (isset($_REQUEST['return'])) {
            $c = "admin";
            if (isset($_REQUEST['oldc'])) {
                $c = $this->clean($_REQUEST['oldc'], "string");
                $data['oldc'] = $c;
            }
            $return = $this->clean($_REQUEST['return'], 'string');
            $data['return'] = $return;
            $delim = "?";
            if (C\REDIRECTS_ON && $c == 'search' && $return == 'more') {
                $data['RETURN'] = B\moreUrl();
            } else if ( substr($return, 0, 2) == 's/') {
                $data['RETURN'] = B\subsearchUrl(substr($return, 2));
            } else {
                $data['RETURN'] = B\controllerUrl($c, true) . "a=$return";
                $delim = '&amp;';
            }
            if (!empty($data['ADMIN'])) {
                $data['RETURN'] .= $delim .
                    C\CSRF_TOKEN."=".$data[C\CSRF_TOKEN];
            }
        }
        return $changed_settings_flag;
    }
}
