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

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\MediaConstants;
use seekquarry\yioop\library\UrlParser;

/**
 * This class is used to handle requests from a MediaUpdater to a name server
 * There are three main types of requests: getUpdateProperties, and
 * for any job that the MediaUpdater might be running, its getTasks, and
 * putTasks request. getUpdateProperties is supposed to provide configuration
 * settings for the MediaUpdater. A MediaUpdater might be running several
 * periodic jobs. The getTasks requests of a job is used to see if there
 * is any new work available of that job type on the name server. A
 * putTasks request is used to handle any computed data sent back from a
 * MediaUpdater to the name server.
 *
 * @author Chris Pollett
 */
class JobsController extends Controller implements CrawlConstants,
    MediaConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    public $activities = ["getUpdateProperties"];
    /**
     * Checks that the request seems to be coming from a legitimate
     * MediaUpdater then determines which job's activity is being
     * requested and calls that activity for processing.
     *
     */
    public function processRequest()
    {
        $data = [];
        /* do a quick test to see if this is a request seems like
           from a legitimate machine
         */
        if (!$this->checkRequest()) {
            return;
        }
        $activity = (isset($_REQUEST['a'])) ? $_REQUEST['a'] : false;
        if (in_array($activity, $this->activities)) {
            $this->call($activity);
        } else if (!empty($_REQUEST['job']) && 
            !empty($_REQUEST['machine_id']) &&
            in_array($activity, ["getTasks", "putTasks"])) {
            $job = $this->clean($_REQUEST['job'], "string");
            $machine_id = L\webdecode(
                $this->clean($_REQUEST['machine_id'], "string"));
            $args = null;
            if (isset($_REQUEST['args'])) {
                $args = unserialize(L\webdecode($_REQUEST['args']));
            }
            $class_name = C\NS_JOBS . lcfirst($job) . "Job";
            if (class_exists($class_name)) {
                $job_object = new $class_name(null, $this);
                $result = $job_object->$activity($machine_id, $args);
                echo L\webencode(serialize($result));
            }
        }
    }
    /**
     * Used to get the update properties of a media updater. Outputs
     * either name_server or distributed depending on whether there is
     * only supposed to be a media updater on the name server or on all
     * machines in the Yioop instance
     */
    public function getUpdateProperties()
    {
        $profile_model = $this->model("profile");
        $profile =  $profile_model->getProfile(C\WORK_DIRECTORY);
        $response = [];
        $response['MEDIA_MODE'] = (isset($profile['MEDIA_MODE'])) ?
            $profile['MEDIA_MODE'] : "name_server";
        $response['SEND_MAIL_MEDIA_UPDATER'] =
            (isset($profile['SEND_MAIL_MEDIA_UPDATER'])) ?
            $profile['SEND_MAIL_MEDIA_UPDATER'] : false;
        echo L\webencode(serialize($response));
    }
}
