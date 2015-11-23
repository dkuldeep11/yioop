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
use seekquarry\yioop\library\classifiers\Classifier;

// to allow the calulation of longer archive schedules
ini_set('max_execution_time', 60);
/**
 * This class handles data coming to a queue_server from a fetcher
 * Basically, it receives the data from the fetcher and saves it into
 * various files for later processing by the queue server.
 * This class can also be used by a fetcher to get status information.
 *
 * @author Chris Pollett
 */
class FetchController extends Controller implements CrawlConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    public $activities = ["schedule", "archiveSchedule", "update", "crawlTime"];
    /**
     * Number of seconds that must elapse after last call before doing
     * cron activities (mainly check liveness of fetchers which should be
     * alive)
     */
    const CRON_INTERVAL = 300;
    /**
     * Checks that the request seems to be coming from a legitimate fetcher then
     * determines which activity the fetcher is requesting and calls that
     * activity for processing.
     */
    public function processRequest()
    {
        $data = [];
        /* do a quick test to see if this is a request seems like
           from a legitimate machine
         */
        if (!$this->checkRequest()) {return; }
        $activity = $_REQUEST['a'];
        $robot_table_name = C\CRAWL_DIR."/".self::robot_table_name;
        $robot_table = [];
        if (file_exists($robot_table_name)) {
            $robot_table = unserialize(file_get_contents($robot_table_name));
        }
        if (isset($_REQUEST['robot_instance']) &&
            (isset($_REQUEST['machine_uri']))) {
            $robot_table[$this->clean($_REQUEST['robot_instance'], "string")] =
                [$_SERVER['REMOTE_ADDR'],
                $this->clean($_REQUEST['machine_uri'], "string"),
                time()];
            file_put_contents($robot_table_name, serialize($robot_table),
                LOCK_EX);
        }
        if (in_array($activity, $this->activities)) {
            $this->call($activity);
        }
    }
    /**
     * Checks if there is a schedule of sites to crawl available and
     * if so present it to the requesting fetcher, and then delete it.
     */
    public function schedule()
    {
        $view = "fetch";
        // set up query
        $data = [];
        if (isset($_REQUEST['crawl_time'])) {;
            $crawl_time = substr($this->clean($_REQUEST['crawl_time'], 'int'),
                0, C\TIMESTAMP_LEN);
        } else {
            $crawl_time = 0;
        }
        $schedule_filename = C\CRAWL_DIR."/schedules/".
            self::schedule_name."$crawl_time.txt";
        if (file_exists($schedule_filename)) {
            $data['MESSAGE'] = file_get_contents($schedule_filename);
            unlink($schedule_filename);
        } else {
            /*  check if scheduler part of queue server went down
                and needs to be restarted with current crawl time.
                Idea is fetcher has recently spoken with name server
                so knows the crawl time. queue server knows time
                only by file messages never by making curl requests
             */
            $this->checkRestart(self::WEB_CRAWL);
            $info = [];
            $info[self::STATUS] = self::NO_DATA_STATE;
            $data['MESSAGE'] = base64_encode(serialize($info))."\n";
        }
        $this->displayView($view, $data);
    }
    /**
     * Checks to see whether there are more pages to extract from the current
     * archive, and if so returns the next batch to the requesting fetcher. The
     * iteration progress is automatically saved on each call to nextPages, so
     * that the next fetcher will get the next batch of pages. If there is no
     * current archive to iterate over, or the iterator has reached the end of
     * the archive then indicate that there is no more data by setting the
     * status to NO_DATA_STATE.
     */
    public function archiveSchedule()
    {
        $view = "fetch";
        $request_start = time();
        if (isset($_REQUEST['crawl_time'])) {;
            $crawl_time = substr($this->clean($_REQUEST['crawl_time'], 'int'),
                0, C\TIMESTAMP_LEN);
        } else {
            $crawl_time = 0;
        }
        $messages_filename = C\CRAWL_DIR.'/schedules/NameServerMessages.txt';
        $lock_filename = C\WORK_DIRECTORY."/schedules/NameServerLock.txt";
        if ($crawl_time > 0 && file_exists($messages_filename)) {
            $fetch_pages = true;
            $info = unserialize(file_get_contents($messages_filename));
            if ($info[self::STATUS] == 'STOP_CRAWL') {
                /* The stop crawl message gets created by the admin_controller
                   when the "stop crawl" button is pressed.*/
                if (file_exists($messages_filename)) {
                    unlink($messages_filename);
                }
                if (file_exists($lock_filename)) {
                    unlink($lock_filename);
                }
                $fetch_pages = false;
                $info = [];
            }
            $this->checkRestart(self::ARCHIVE_CRAWL);
        } else {
            $fetch_pages = false;
            $info = [];
        }
        $pages = [];
        $got_lock = true;
        if (file_exists($lock_filename)) {
            $lock_time = unserialize(file_get_contents($lock_filename));
            if ($request_start - $lock_time < ini_get('max_execution_time')){
                $got_lock = false;
            }
        }
        $chunk = false;
        $archive_iterator = null;
        if ($fetch_pages && $got_lock) {
            file_put_contents($lock_filename, serialize($request_start));
            if ($info[self::ARC_DIR] == "MIX" ||
                    file_exists($info[self::ARC_DIR])) {
                $iterate_timestamp = $info[self::CRAWL_INDEX];
                $result_timestamp = $crawl_time;
                $result_dir = C\WORK_DIRECTORY.
                    "/schedules/".self::name_archive_iterator.$crawl_time;
                $arctype = $info[self::ARC_TYPE];
                $iterator_name = C\NS_ARCHIVE . $arctype."Iterator";
                try {
                    if ($info[self::ARC_DIR] == "MIX") {
                        //recrawl of crawl mix case
                        $archive_iterator = new $iterator_name(
                            $iterate_timestamp, $result_timestamp);
                    } else {
                        //any other archive crawl except web archive recrawls
                        $archive_iterator = new $iterator_name(
                            $iterate_timestamp, $info[self::ARC_DIR],
                            $result_timestamp, $result_dir);
                    }
                } catch (\Exception $e) {
                    $info['ARCHIVE_BUNDLE_ERROR'] =
                        "Invalid bundle iterator: '{$iterator_name}' \n".
                        $e->getMessage();
                }
            }
            $pages = false;
            if ($archive_iterator && !$archive_iterator->end_of_iterator) {
                if (L\generalIsA($archive_iterator,
                    C\NS_ARCHIVE . "TextArchiveBundleIterator")) {
                    $pages = $archive_iterator->nextChunk();
                    $chunk = true;
                } else {
                    $pages = $archive_iterator->nextPages(
                        C\ARCHIVE_BATCH_SIZE);
                }
            }
            if (file_exists($lock_filename)) {
                unlink($lock_filename);
            }
        }
        if ($archive_iterator && $archive_iterator->end_of_iterator) {
            $info[self::END_ITERATOR] = true;
        }
        if (($chunk && $pages) || ($pages && !empty($pages))) {
            $pages_string = L\webencode(gzcompress(serialize($pages)));
        } else {
            $info[self::STATUS] = self::NO_DATA_STATE;
            $info[self::POST_MAX_SIZE] = L\metricToInt(
                ini_get("post_max_size"));
            $pages = [];
            $pages_string = L\webencode(gzcompress(serialize($pages)));
        }
        $info[self::DATA] = $pages_string;
        $info_string = serialize($info);
        $data['MESSAGE'] = $info_string;
        $this->displayView($view, $data);
    }
    /**
     * Checks if the queue server crawl needs to be restarted
     * @param string $crawl_type if it does use restart the crawl as a crawl
     *     of this type. For example, self::WEB_CRAWL or self::ARCHIVE_CRAWL
     */
    public function checkRestart($crawl_type)
    {
        if (isset($_REQUEST['crawl_time'])) {;
            $crawl_time = substr($this->clean($_REQUEST['crawl_time'], 'int'),
                0, C\TIMESTAMP_LEN);
            if (isset($_REQUEST['check_crawl_time'])) {
                $check_crawl_time = substr($this->clean(
                    $_REQUEST['check_crawl_time'], 'int'), 0, C\TIMESTAMP_LEN);
            }
        } else {
            $crawl_time = 0;
            $check_crawl_time = 0;
        }
        $index_schedule_file = C\CRAWL_DIR."/schedules/" .
            self::index_closed_name . $crawl_time. ".txt";
        if ($crawl_time > 0 && file_exists($index_schedule_file) &&
            $check_crawl_time > intval(fileatime($index_schedule_file)) &&
            !file_exists(C\CRAWL_DIR.
                "/schedules/QueueServerMessages.txt") ) {
            $restart = true;
            if (file_exists(C\CRAWL_DIR."/schedules/crawl_status.txt")) {
                $crawl_status = unserialize(file_get_contents(
                    C\CRAWL_DIR."/schedules/crawl_status.txt"));
                if ($crawl_status['CRAWL_TIME'] != 0) {
                    $restart = false;
                }
            }
            if ($restart == true && file_exists(C\CRAWL_DIR.'/cache/'.
                self::index_data_base_name.$crawl_time)) {
                $crawl_params = [];
                $crawl_params[self::STATUS] = "RESUME_CRAWL";
                $crawl_params[self::CRAWL_TIME] = $crawl_time;
                $crawl_params[self::CRAWL_TYPE] = $crawl_type;
                /*
                    we only set crawl time. Other data such as allowed sites
                    should come from index.
                */
                $this->model("crawl")->sendStartCrawlMessage($crawl_params,
                    null, null);
            }
        }
    }
    /**
     * Processes Robot, To Crawl, and Index data sent from a fetcher
     * Acknowledge to the fetcher if this data was received okay.
     */
    public function update()
    {
        $view = "fetch";
        $info_flag = false;
        $logging = "";
        $necessary_fields = ['byte_counts', 'current_part', 'hash_data',
            'hash_part', 'num_parts', 'part'];
        $part_flag = true;
        $missing = "";
        foreach ($necessary_fields as $field) {
            if (!isset($_REQUEST[$field])) {
                $part_flag = false;
                $missing = $field;
            }
        }
        if (isset($_REQUEST['crawl_type'])) {
            $this->checkRestart($this->clean(
                $_REQUEST['crawl_type'], 'string'));
        }
        if ($part_flag && 
            L\crawlHash($_REQUEST['part']) == $_REQUEST['hash_part']) {
            $upload = false;
            if (intval($_REQUEST['num_parts']) > 1) {
                $info_flag = true;
                if (!file_exists(C\CRAWL_DIR."/temp")) {
                    mkdir(C\CRAWL_DIR."/temp");
                    L\setWorldPermissionsRecursive(C\CRAWL_DIR."/temp/");
                }
                $filename = C\CRAWL_DIR."/temp/".$_REQUEST['hash_data'];
                file_put_contents($filename, $_REQUEST['part'], FILE_APPEND);
                L\setWorldPermissions($filename);
                if ($_REQUEST['num_parts'] == $_REQUEST['current_part']) {
                    $upload = true;
                }
            } else if (intval($_REQUEST['num_parts']) == 1) {
                $info_flag = true;
                $upload = true;
                $filename = "";
            }
            if ($upload) {
                $logging = $this->handleUploadedData($filename);
            } else {
                $logging = "...".(
                    $_REQUEST['current_part']/$_REQUEST['num_parts']).
                    " of data uploaded.";
            }
        }
        $info =[];
        if ($logging != "") {
            $info[self::LOGGING] = $logging;
        }
        if ($info_flag == true) {
            $info[self::STATUS] = self::CONTINUE_STATE;
        } else {
            $info[self::STATUS] = self::REDO_STATE;
            if (!$part_flag) {
                $info[self::SUMMARY] = "Missing request field: $missing.";
            } else {
                $info[self::SUMMARY] = "Hash of uploaded data was:".
                    L\crawlHash($_REQUEST['part']).". Sent checksum was:".
                    $_REQUEST['hash_part'];
            }
        }
        $info[self::MEMORY_USAGE] = memory_get_peak_usage();
        $info[self::POST_MAX_SIZE] = L\metricToInt(ini_get("post_max_size"));
        if (file_exists(C\CRAWL_DIR . "/schedules/crawl_status.txt")) {
            $change = false;
            $crawl_status = unserialize(
                file_get_contents(C\CRAWL_DIR . "/schedules/crawl_status.txt"));
            if (isset($_REQUEST['fetcher_peak_memory'])) {
                if (!isset($crawl_status['FETCHER_MEMORY']) ||
                    $_REQUEST['fetcher_peak_memory'] >
                    $crawl_status['FETCHER_PEAK_MEMORY']
                ) {
                    $crawl_status['FETCHER_PEAK_MEMORY'] =
                        $_REQUEST['fetcher_peak_memory'];
                    $change = true;
                }
            }
            if (!isset($crawl_status['WEBAPP_PEAK_MEMORY']) ||
                $info[self::MEMORY_USAGE] >
                $crawl_status['WEBAPP_PEAK_MEMORY']) {
                $crawl_status['WEBAPP_PEAK_MEMORY'] =
                    $info[self::MEMORY_USAGE];
                $change = true;
            }
            if (!isset($crawl_status[self::CRAWL_TIME])) {
                $network_filename = C\CRAWL_DIR."/schedules/network_status.txt";
                if (file_exists($network_filename)) {
                    $info[self::CRAWL_TIME] = unserialize(file_get_contents(
                        $network_filename));
                    $change = true;
                } else {
                    $info[self::CRAWL_TIME] = 0;
                }
            } else {
                $info[self::CRAWL_TIME] = $crawl_status['CRAWL_TIME'];
            }
            if ($change == true) {
                file_put_contents(C\CRAWL_DIR."/schedules/crawl_status.txt",
                    serialize($crawl_status), LOCK_EX);
            }
        } else {
            $info[self::CRAWL_TIME] = 0;
        }
        $info[self::MEMORY_USAGE] = memory_get_peak_usage();
        $data = [];
        $data['MESSAGE'] = serialize($info);
        $this->displayView($view, $data);
    }
    /**
     * After robot, schedule, and index data have been uploaded and reassembled
     * as one big data file/string, this function splits that string into
     * each of these data types and then save the result into the appropriate
     * schedule sub-folder. Any temporary files used during uploading are then
     * deleted.
     *
     * @param string $filename name of temp file used to upload big string.
     *     If uploaded data was small enough to be uploaded in one go, then
     *     this should be "" -- the variable $_REQUEST["part"] will be used
     *     instead
     * @return string $logging diagnostic info to be sent to fetcher about
     *     what was done
     */
    public function handleUploadedData($filename = "")
    {
        if ($filename == "") {
            $uploaded = $_REQUEST['part'];
        } else {
            $uploaded = file_get_contents($filename);
            unlink($filename);
        }
        $logging = "... Data upload complete\n";
        $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']);
        $address = str_replace(":", "_", $address);
        $time = time();
        $day = floor($time/C\ONE_DAY);
        $byte_counts = [];
        if (isset($_REQUEST['byte_counts'])) {
            $byte_counts = unserialize(L\webdecode($_REQUEST['byte_counts']));
        }
        $robot_data = "";
        $cache_page_validation_data = "";
        $schedule_data = "";
        $index_data = "";
        if (isset($byte_counts["TOTAL"]) &&
            $byte_counts["TOTAL"] > 0) {
            $pos = 0;
            $robot_data = substr($uploaded, $pos,$byte_counts["ROBOT"]);
            $pos += $byte_counts["ROBOT"];
            $cache_page_validation_data = substr($uploaded, $pos,
                $byte_counts["CACHE_PAGE_VALIDATION"]);
            $pos += $byte_counts["CACHE_PAGE_VALIDATION"];
            $schedule_data =
                substr($uploaded, $pos, $byte_counts["SCHEDULE"]);
            $pos += $byte_counts["SCHEDULE"];
            $index_data =
                substr($uploaded, $pos);
        }
        if (strlen($robot_data) > 0) {
            $this->addScheduleToScheduleDirectory(self::robot_data_base_name,
                $robot_data);
        }
        if (C\USE_ETAG_EXPIRES && strlen($cache_page_validation_data) > 0) {
            $this->addScheduleToScheduleDirectory(
                self::etag_expires_data_base_name,
                $cache_page_validation_data);
        }
        if (strlen($schedule_data) > 0) {
            $this->addScheduleToScheduleDirectory(self::schedule_data_base_name,
                $schedule_data);
        }
        if (strlen($index_data) > 0) {
            $this->addScheduleToScheduleDirectory(self::index_data_base_name,
                $index_data);
        }
        return $logging;
    }
    /**
     * Adds a file with contents $data and with name containing $address and
     * $time to a subfolder $day of a folder $dir
     *
     * @param string $schedule_name the name of the kind of schedule being saved
     * @param string& $data_string encoded, compressed, serialized data the
     *     schedule is to contain
     */
    public function addScheduleToScheduleDirectory($schedule_name,
        &$data_string)
    {
        $crawl_time = substr($this->clean($_REQUEST['crawl_time'], "int"), 0,
            C\TIMESTAMP_LEN);
        $dir = C\CRAWL_DIR . "/schedules/".$schedule_name . $crawl_time;
        $address = str_replace(".", "-", $_SERVER['REMOTE_ADDR']);
        $address = str_replace(":", "_", $address);
        $time = time();
        $day = floor($time/C\ONE_DAY);
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $dir .= "/$day";
        if (!file_exists($dir)) {
            mkdir($dir);
            chmod($dir, 0777);
        }
        $data_hash = L\crawlHash($data_string);
        file_put_contents($dir."/At".$time."From".$address.
            "WithHash$data_hash.txt", $data_string);
    }
    /**
     * Checks for the crawl time according either to crawl_status.txt or to
     * network_status.txt, and presents it to the requesting fetcher, along
     * with a list of available queue servers.
     */
    public function crawlTime()
    {
        $info = [];
        $info[self::STATUS] = self::CONTINUE_STATE;
        $view = "fetch";
        $cron_model = $this->model("cron");
        if (isset($_REQUEST['crawl_time'])) {;
            $prev_crawl_time = substr(
                $this->clean($_REQUEST['crawl_time'], 'int'), 0,
                C\TIMESTAMP_LEN);
        } else {
            $prev_crawl_time = 0;
        }
        $cron_time = $cron_model->getCronTime("fetcher_restart");
        $delta = time() - $cron_time;
        if ($delta > self::CRON_INTERVAL) {
            $cron_model->updateCronTime("fetcher_restart");
            $this->doFetcherCronTasks();
        } else if ($delta == 0) {
            $cron_model->updateCronTime("fetcher_restart");
        }
        $local_filename = C\CRAWL_DIR."/schedules/crawl_status.txt";
        $network_filename = C\CRAWL_DIR."/schedules/network_status.txt";
        if (file_exists($local_filename)) {
            $crawl_status = unserialize(file_get_contents($local_filename));
            $crawl_time = (isset($crawl_status["CRAWL_TIME"])) ?
                $crawl_status["CRAWL_TIME"] : 0;
        } else if (file_exists($network_filename)){
            $crawl_time = unserialize(file_get_contents($network_filename));
        } else {
            $crawl_time = 0;
        }
        $info[self::CRAWL_TIME] = $crawl_time;
        $status_filename = C\CRAWL_DIR."/schedules/NameServerMessages.txt";
        if ($crawl_time != 0 && file_exists($status_filename)) {
            $status = unserialize(file_get_contents($status_filename));
            if ($status[self::STATUS] == 'STOP_CRAWL') {
                $info[self::STATUS] == 'STOP_CRAWL';
                $info[self::CRAWL_TIME] = 0;
            } else {
                $tmp_base_dir = C\CRAWL_DIR."/schedules/".
                    self::index_data_base_name . $crawl_time;
                $tmp_dirs = glob($tmp_base_dir.'/*', GLOB_ONLYDIR);
                $mult_factor = max(1, count($tmp_dirs));
                $info[self::MINIMUM_FETCH_LOOP_TIME] = max(min(
                    $mult_factor * C\MINIMUM_FETCH_LOOP_TIME,
                    C\PROCESS_TIMEOUT/2), C\MINIMUM_FETCH_LOOP_TIME);
            }
            if ($status[self::STATUS] != 'STOP_CRAWL'  &&
                $crawl_time != $prev_crawl_time) {
                $to_copy_fields = [self::ALLOWED_SITES, self::ARC_DIR,
                    self::ARC_TYPE, self::CRAWL_INDEX, self::CRAWL_TYPE,
                    self::DISALLOWED_SITES, self::INDEXED_FILE_TYPES,
                    self::PROXY_SERVERS, self::RESTRICT_SITES_BY_URL,
                    self::SUMMARIZER_OPTION, self::TOR_PROXY
                    ];
                foreach ($to_copy_fields as $field) {
                    if (isset($status[$field])) {
                        $info[$field] = $status[$field];
                    }
                }
                /*
                   When initiating a new crawl AND there are active
                   classifiers (an array of class labels), then augment the
                   info with compressed, serialized versions of each active
                   classifier so that each fetcher can reconstruct the same
                   classifiers.
                 */
                $classifier_array = [];
                if (isset($status[self::ACTIVE_CLASSIFIERS])) {
                    $classifier_array = array_merge(
                        $status[self::ACTIVE_CLASSIFIERS]);
                    $info[self::ACTIVE_CLASSIFIERS] =
                        $status[self::ACTIVE_CLASSIFIERS];
                }
                if (isset($status[self::ACTIVE_RANKERS])) {
                    $classifier_array = array_merge($classifier_array,
                        $status[self::ACTIVE_RANKERS]);
                    $info[self::ACTIVE_RANKERS] =
                        $status[self::ACTIVE_RANKERS];
                }
                if ($classifier_array != []) {
                    $classifiers_data = Classifier::loadClassifiersData(
                            $classifier_array);
                    $info[self::ACTIVE_CLASSIFIERS_DATA] = $classifiers_data;
                }
            }
        }
        $info[self::QUEUE_SERVERS] =
            $this->model("machine")->getQueueServerUrls();
        $info[self::SAVED_CRAWL_TIMES] = $this->getCrawlTimes();
        $info[self::POST_MAX_SIZE] = L\metricToInt(ini_get("post_max_size"));
        if (count($info[self::QUEUE_SERVERS]) == 0) {
            $info[self::QUEUE_SERVERS] = [C\NAME_SERVER];
        }
        $data = [];
        $data['MESSAGE'] = serialize($info);
        $this->displayView($view, $data);
    }
    /**
     * Used to do periodic maintenance tasks for the Name Server.
     * For now, just checks if any fetchers which the user turned on
     * have crashed and if so restarts them
     */
    public function doFetcherCronTasks()
    {
        $this->model("machine")->restartCrashedFetchers();
    }
    /**
     * Gets a list of all the timestamps of previously stored crawls
     *
     * This could probably be moved to crawl model. It is a little lighter
     * than getCrawlList and should be only used with a name server so leaving
     * it here so it won't be confused.
     *
     * @return array list of timestamps
     */
    public function getCrawlTimes()
    {
        $list = [];
        $dirs = glob(C\CRAWL_DIR.'/cache/*');

        foreach ($dirs as $dir) {
            if (strlen($pre_timestamp = strstr($dir,
                self::index_data_base_name)) > 0) {
                $list[] = substr($pre_timestamp,
                    strlen(self::index_data_base_name));
            }
            if (strlen($pre_timestamp = strstr($dir,
                self::network_base_name)) > 0) {
                $tmp = substr($pre_timestamp,
                    strlen(self::network_base_name), -4);
                if (is_numeric($tmp)) {
                    $list[] = $tmp;
                }
            }
        }
        $list = array_unique($list);
        return $list;
    }
}
