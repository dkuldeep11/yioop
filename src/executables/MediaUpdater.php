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
use seekquarry\yioop\library\MediaConstants;
use seekquarry\yioop\library\media_jobs\MediaJob;
use seekquarry\yioop\library\WikiParser;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
ini_set("memory_limit", "1300M");
/** We do want logging, but crawl model and other will try to turn off
 * if we don't set this
 */
define("seekquarry\\yioop\\configs\\NO_LOGGING", false);
/** To guess language based on page encoding */
require_once __DIR__."/../library/LocaleFunctions.php";
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
 * Separate process/command-line script which can be used to update
 * news sources for Yioop and also handle other kinds of activities such as
 * video conversion. This is as an alternative to using the web app
 * for updating. Makes use of the web-apps code.
 *
 * @author Chris Pollett
 */
class MediaUpdater implements CrawlConstants
{
    /**
     * Shortest time through one iteration of news updater's loop
     */
    const MINIMUM_UPDATE_LOOP_TIME = 10;
    /**
     * The last time feeds were checked for updates
     * @var int
     */
    public $update_time;
    /**
     * If true then it is assumed that mail should be
     * sent using a media updater rather than from within the web app
     *
     * @var bool
     */
    public $mail_mode;
    /**
     * Controls whether media updating should be viewed as only occurring
     * on the name server or should it be viewed as a distributed process
     * amongst all machines in this Yioop instance
     * @var string
     */
    public $media_mode;
    /**
     * List of job this media updater performs
     * @var array
     */
    public $jobs;
    /**
     * Sets up the field variables so that media updating can begin
     */
    public function __construct()
    {
        $this->delete_time = 0;
        $this->retry_time = 0;
        $this->update_time = 0;
        $this->media_mode = "name_server";
        $this->media_mode = false;
        $job_path = C\BASE_DIR ."/library/media_jobs/";
        $len_path = strlen($job_path);
        $job_files = glob("$job_path*Job.php");
        foreach ($job_files as $job_file) {
            require_once $job_file;
            $job_name = C\NS_JOBS . substr($job_file, $len_path, -4);
            if ($job_name != C\NS_JOBS . "MediaJob") {
                $job = new $job_name($this);
                $this->jobs[] = $job;
            }
        }
    }
    /**
     * This is the function that should be called to get the MediaUpdater to
     * start to start updating. Calls init to handle the command-line
     * arguments then enters news_updaters main loop
     */
    public function start()
    {
        global $argv;
        CrawlDaemon::init($argv, "MediaUpdater");
        L\crawlLog("\n\nInitialize logger..", "MediaUpdater", true);
        $this->loop();
    }
    /**
     * Main loop for the news updater.
     */
    public function loop()
    {
        L\crawlLog("In Media Update Loop");
        L\crawlLog("PHP Version is use: " . phpversion());
        $info[self::STATUS] = self::CONTINUE_STATE;
        $local_archives = [""];
        while (CrawlDaemon::processHandler()) {
            $start_time = microtime(true);
            $this->getUpdateProperties();
            foreach ($this->jobs as $job) {
                $job->run();
            }
            $sleep_time = max(0, ceil(self::MINIMUM_UPDATE_LOOP_TIME -
                    L\changeInMicrotime($start_time)));
            if ($sleep_time > 0) {
                L\crawlLog("Ensure minimum loop time by sleeping...".
                    $sleep_time);
                sleep($sleep_time);
            }
        } //end while
        L\crawlLog("Media Updater shutting down!!");
    }
    /**
     * Makes a request to the name server to find out if we are running
     * as a media updater just on the name server or on both the name server
     * as well as all other machines in the Yioop instance
     */
    public function getUpdateProperties()
    {
        L\crawlLog("Checking Name Server for Media Updater properties...");
        $current_machine = MediaJob::getCurrentMachine();
        $properties = MediaJob::execNameServer(
            "getUpdateProperties");
        if ($properties) {
            if (isset($properties['MEDIA_MODE'])) {
                $this->media_mode = $properties['MEDIA_MODE'];
                L\crawlLog("...Setting media mode to: " .
                    $properties['MEDIA_MODE']);
            }
            if (isset($properties['SEND_MAIL_MEDIA_UPDATER'])) {
                $this->mail_mode = (
                    $properties['SEND_MAIL_MEDIA_UPDATER']== "true") ?
                    true : false;
                L\crawlLog("...Setting mail mode to: " .
                    (($this->mail_mode) ? "true" : "false"));
            }
        }
        L\crawlLog("Done checking Name Server for Media Updater properties");
    }
}
/*
 * Instantiate and run the MediaUpdater program
 */
$media_updater =  new MediaUpdater();
$media_updater->start();

