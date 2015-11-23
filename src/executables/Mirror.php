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
namespace seekquarry\yioop\executables;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\FetchUrl;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
ini_set("memory_limit","850M"); //so have enough memory to crawl big pages

/** CRAWLING means don't try to use memcache
 * @ignore
 */
define("seekquarry\\yioop\\configs\\NO_CACHE", true);
/** for crawlHash and crawlLog and Yioop constants */
require_once __DIR__."/../library/Utility.php";
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/**
 * This class is responsible for syncing crawl archives between machines using
 * the SeekQuarry/Yioop search engine
 *
 * Mirror periodically queries the queue server asking for a list of files that
 * have changed in its parent since the last sync time. It then proceeds to
 * download them.
 *
 * @author Chris Pollett
 */
class Mirror implements CrawlConstants
{
    /**
     * Reference to a database object. Used since has directory manipulation
     * functions
     * @var object
     */
    public $db;
    /**
     * Url or IP address of the name_server to get sites to crawl from
     * @var string
     */
    public $name_server;

    /**
     * Last time a sync list was obtained from master machines
     * @var string
     */
    public $last_sync;
    /**
     * Last time the machine being mirrored was notified Mirror.php is still
     * running
     * @var string
     */
    public $last_notify;
    /**
     * File name where last sync time is written
     * @var string
     */
    public $last_sync_file;
    /**
     * Time of start of current sync
     * @var string
     */
    public $start_sync;
    /**
     * Files to download for current sync
     * @var string
     */
    public $sync_schedule;
    /**
     * Directory to sync
     * @var string
     */
    public $sync_dir;
    /**
     * Url of the Yioop instance we are mirroring
     * @var string
     */
    public $parent_url;
    /**
     * Maximum number of bytes from a file to download in one go
     */
    const DOWNLOAD_RANGE = 50000000;
    /**
     * Sets up the field variables so that syncing can begin
     *
     * @param string $name_server URL or IP address of the name server
     */
    public function __construct($name_server)
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
        $this->db = new $db_class();
        $this->name_server = $name_server;
        $this->last_sync_file = C\CRAWL_DIR."/schedules/last_sync.txt";
        if (file_exists($this->last_sync_file)) {
            $this->last_sync = unserialize(
                file_get_contents($this->last_sync_file));
        } else {
            $this->last_sync = 0;
        }
        $this->start_sync = $this->last_sync;
        $this->last_notify = $this->last_sync;
        $this->sync_schedule = [];
        $this->sync_dir = C\CRAWL_DIR."/cache";
        $this->parent_url = $name_server;
    }
    /**
     * This is the function that should be called to get the mirror to start
     * syncing. Calls init to handle the command line arguments then enters
     * the syncer's main loop
     */
    public function start()
    {
        global $argv;
        CrawlDaemon::init($argv, "Mirror");
        L\crawlLog("\n\nInitialize logger..", "mirror", true);
        $this->loop();
    }
    /**
     * Main loop for the mirror script.
     *
     */
    public function loop()
    {
        L\crawlLog("In Sync Loop");
        L\crawlLog("PHP Version is use:" . phpversion());
        $info[self::STATUS] = self::CONTINUE_STATE;
        while (CrawlDaemon::processHandler()) {
            $syncer_message_file = C\CRAWL_DIR .
                "/schedules/MirrorMessages.txt";
            if (file_exists($syncer_message_file)) {
                $info = unserialize(file_get_contents($syncer_message_file));
                unlink($syncer_message_file);
                if (isset($info[self::STATUS]) &&
                    $info[self::STATUS] == self::STOP_STATE) {
                    continue;
                }
            }
            $parent_file = C\CRAWL_DIR . "/schedules/mirror_parent.txt";
            if (file_exists($parent_file)) {
                $this->parent_url = file_get_contents($parent_file);
                L\crawlLog("Read File: " . $parent_file . ".");
                L\crawlLog("Set parent server to: " . $this->parent_url);
            } else {
                L\crawlLog("File: " . $parent_file . " does not exist!");
                L\crawlLog("Assuming parent is name server: ".
                    $this->name_server);
                $this->parent_url = $this->name_server;
            }
            $info = $this->checkScheduler();
            if ($info === false) {
                L\crawlLog("Cannot connect to parent server...".
                    " will try again in ".
                    C\MIRROR_NOTIFY_FREQUENCY." seconds.");
                sleep(C\MIRROR_NOTIFY_FREQUENCY);
                continue;
            }
            if ($info[self::STATUS] == self::NO_DATA_STATE) {
                L\crawlLog("No data from parent server. Sleeping...");
                sleep(C\MIRROR_NOTIFY_FREQUENCY);
                continue;
            }
            $this->copyNextSyncFile();
        } //end while
        L\crawlLog("Mirror shutting down!!");
    }
    /**
     * Gets status and, if done processing all other mirroring activities,
     * gets a new list of files that have changed since the last synchronization
     * from the web app of the machine we are mirroring with.
     *
     * @return mixed array or bool. Returns false if weren't successful in
     *     contacting web app, otherwise, returns an array with a status
     *     and potentially a list of files ot sync
     */
    public function checkScheduler()
    {
        $info = [];
        $server = $this->parent_url;
        $start_time = microtime(true);
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $write_sync_time = true;
        $request =
            $server.
            "?c=resource&time=$time&session=$session".
            "&robot_instance=".C\ROBOT_INSTANCE."&machine_uri=".C\WEB_URI.
            "&last_sync=".$this->last_sync;
        if ($this->start_sync <= $this->last_sync &&
            $this->last_sync + C\MIRROR_SYNC_FREQUENCY < $time) {
            $request .= "&a=syncList";
            L\crawlLog("Getting Sync List...");
            $info_string = FetchUrl::getPage($request, null, true);
            if ($info_string === false) {
                return false;
            }
            $this->last_notify = $time;
            $info_string = trim($info_string);
            $info = unserialize(gzuncompress(base64_decode($info_string)));
            if (isset($info[self::STATUS]) &&
                $info[self::STATUS] == self::CONTINUE_STATE) {
                $this->start_sync = $time;
                $this->sync_schedule = $info[self::DATA];
                unset($info[self::DATA]);
            } else if (isset($info[self::STATUS]) &&
                $info[self::STATUS] == self::NO_DATA_STATE) {
                $this->last_sync = $time;
                $this->start_sync = $time;
                $write_sync_time = false;
            }
        } else {
            $info[self::STATUS] = ($this->last_sync == $this->start_sync) ?
                self::NO_DATA_STATE : self::CONTINUE_STATE;
            L\crawlLog("Current time $time, last notify time ".
                $this->last_notify."...");
            if ($time - $this->last_notify > C\MIRROR_NOTIFY_FREQUENCY) {
                $request .= "&a=syncNotify";
                FetchUrl::getPage($request, null, true);
                $this->last_notify = $time;
                L\crawlLog("Notifying master that mirror is alive..");
            } else {
                L\crawlLog("So not notifying scheduler..");
            }
        }
        if (count($this->sync_schedule) == 0 && $write_sync_time) {
            $this->last_sync = $this->start_sync;
            $this->db->setWorldPermissionsRecursive($this->sync_dir, true);
            file_put_contents($this->last_sync_file,
                serialize($this->last_sync));
        }
        L\crawlLog("  Time to check Scheduler ".
            L\changeInMicrotime($start_time));
        return $info;
    }
    /**
     * Downloads the next file from the schedule of files to download received
     * from the web app.
     */
    public function copyNextSyncFile()
    {
        $dir = $this->sync_dir;
        $server = $this->parent_url;
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        if (count($this->sync_schedule) <= 0) return;
        $file = array_pop($this->sync_schedule);
        L\crawlLog("Start syncing {$file['name']}..");
        if ($file['is_dir'] ) {
            if (!file_exists("$dir/{$file['name']}")) {
                mkdir("$dir/{$file['name']}");
                L\crawlLog(".. {$file['name']} directory created.");
            } else {
                L\crawlLog(".. {$file['name']} directory exists.");
            }
        } else {
            $request =
                "$server?c=resource&a=get&time=$time&session=$session".
                "&f=cache&n=" . urlencode($file["name"]);
            if ($file["size"] < self::DOWNLOAD_RANGE) {
                $data = FetchUrl::getPage($request, null, true);
                if ($file["size"] != strlen($data)) {
                    array_push($this->sync_schedule, $file);
                    L\crawlLog(".. {$file['name']} error ".
                        "downloading, retrying.");
                    return;
                }
                file_put_contents("$dir/{$file['name']}", $data);
                L\crawlLog(".. {$file['name']} file copied.");
            } else {
                $offset = 0;
                $fh = fopen("$dir/{$file['name']}", "wb");
                $request .= "&l=".self::DOWNLOAD_RANGE;
                while($offset < $file['size']) {
                    $data = FetchUrl::getPage($request."&o=$offset", null,
                        true);
                    $old_offset = $offset;
                    $offset += self::DOWNLOAD_RANGE;
                    $end_point = min($offset, $file["size"]);
                    //crude check if we need to redownload segment
                    if (strlen($data) != ($end_point - $old_offset)) {
                        $offset = $old_offset;
                        L\crawlLog(".. Download error re-requesting segment");
                        continue;
                    }
                    fwrite($fh, $data);
                    L\crawlLog(".. {$file['name']} downloaded bytes $old_offset ".
                        "to $end_point..");
                }
                L\crawlLog(".. {$file['name']} file copied.");
                fclose($fh);
            }
        }
    }
}
/*
 * Instantiate and runs the Mirror program
 */
$syncer =  new Mirror(C\NAME_SERVER);
$syncer->start();

