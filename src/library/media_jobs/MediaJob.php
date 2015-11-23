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
 * @author Chris Pollett chris@pollett.org (initial MediaJob class
 *      and subclasses based on work of Pooja Mishra for her master's)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\library\media_jobs;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\FetchUrl;
use seekquarry\yioop\library\MediaConstants;

/**
 * Base class for jobs to be carried out by a MediaUpdater process
 * Subclasses of this class correspond to specific jobs for MediaUpdater.
 * Subclasses should implement methods they use among init(),
 * checkPrerequisites(), nondistributedTasks(), prepareTasks(), finishTasks(),
 * getTasks(), doTasks(), and putTask(). MediaUpdating can be configured to
 * run in either distributed or nameserver only mode. In the former mode,
 * prepareTasks(), finishTasks() run on the name server, getTasks() and
 * putTask() run in the name server's web app, and doTasks() run on
 * any MediaUpdater clients. In the latter mode, only the method
 * nondistributedTasks() is called by the MediaUpdater and by only the updater
 * on the name server.
 */
class MediaJob implements CrawlConstants, MediaConstants
{
    /**
     * If MediaJob was instantiated in the web app, the controller that
     * instatiated it
     * @var object
     */
    public $controller;
    /**
     * If the MediaJob was instantiated in a MediaUpdater, this is a reference
     * to that updater
     * @var object
     */
    public $media_updater;
    /**
     * Whether to run the job's client tasks on the name server in addition to
     * prepareTasks and finishTasks
     *
     * @var bool
     */
    public $name_server_does_client_tasks;
    /**
     * Whether this MediaJob performs name server only tasks
     * @var bool
     */
    public $name_server_does_client_tasks_only;
    /**
     * The most recently received from the name server tasks for this MediaJob
     * @var array
     */
    public $tasks;
    /**
     * Instiates the MediaJob with a reference to the object that instatiated it
     *
     * @param object $media_updater a reference to the media updater that
     *      instatiated this object (if being run in MediaUpdater)
     * @param object $controller  a reference to the controller that
     *      instatiated this object (if being run in the web app)
     */
    public function __construct($media_updater = null, $controller = null)
    {
        $this->media_updater = $media_updater;
        $this->controller = $controller;
        $this->tasks = [];
        $this->name_server_does_client_tasks = false;
        $this->name_server_does_client_tasks_only = false;
        $this->init();
    }
    /**
     * Overridable methods in which a job can carry out any initialization 
     * needed before it is run
     */
    public function init()
    {
    }
    /**
     * Method executed by MediaUpdater to perform the MediaJob. This method
     * shouldn't need to be overriden. Instead, the various callbacks it calls
     * (listed in the class description) wshould be overriden.
     */
    public function run()
    {
        if (!$this->checkPrerequisites()) {
            return;
        }
        $current_machine = $this->getCurrentmachine();
        $is_name_server = ($current_machine == L\crawlHash(C\NAME_SERVER));
        if ($is_name_server) {
            $current_machine = "NAME SERVER";
        }
        $job_name = $this->getJobName();
        L\crawlLog("Running Job: $job_name");
        L\crawlLog("Current Machine: $current_machine");
        if ($this->media_updater->media_mode == 'distributed') {
            $name_server_does_client_tasks = false;
            if ($is_name_server && ! $this->name_server_does_client_tasks_only){
                L\crawlLog("--Preparing job $job_name tasks on Name Server");
                $this->prepareTasks();
                L\crawlLog("--Finishing job $job_name tasks on Name Server");
                $this->finishTasks();
            }
            if (!$is_name_server || ($is_name_server &&
                $this->name_server_does_client_tasks)) {
                L\crawlLog("--Checking for $job_name tasks to do");
                $this->tasks = $this->execNameServer("getTasks");
                if ($this->tasks) {
                    L\crawlLog("--Executing tasks for job $job_name");
                    $results = $this->doTasks($this->tasks);
                    if ($results) {
                        L\crawlLog("--Sending task results for job $job_name".
                            " to name server");
                        $response = $this->execNameServer("putTasks", $results);
                        if (is_array($response)) {
                            $response = print_r($response, true);
                        }
                        L\crawlLog("--Name server response was:\n" . $response);
                    }
                } else {
                    L\crawlLog("--No tasks found for job $job_name");
                }
            }
        } else {
            if ($is_name_server) {
                L\crawlLog("Executing job: $job_name in nondistributed mode.");
                $this->nondistributedTasks();
            }
        }
        L\crawlLog("Finished job: $job_name");
    }
    /**
     * Checks if the preconditions for the current job's task have been
     * met. If yes, the run() method will then invoke methods to carry them
     * out.
     *
     * @return bool whether or not the prerequisites have been met for
     *      the job's tasks to be performed.
     */
    public function checkPrerequisites()
    {
        return true;
    }
    /**
     * Tasks done by this job when run in nondistributed mode
     */
    public function nondistributedTasks()
    {
    }
    /**
     * This method is called on the name server to prepare data for
     * any MediaUpdater clients.
     */
    public function prepareTasks()
    {
    }
    /**
     * This method is called on the name server to finish processing any
     * data returned by MediaUpdater clients.
     */
    public function finishTasks()
    {
    }
    /**
     * This method is run on MediaUpdater client with data gotten from the
     * name server by getTasks. The idea is the client is supposed to then
     * this information and if need be send the results back to the name server
     *
     * @param array $tasks data that the MediaJob running on a client
     *      MediaUpdater needs to process
     * @return mixed the result of carrying out that processing
     */
    public function doTasks($tasks)
    {
    }
    /**
     * Method called from JobController when a MediaUpdater client contacts
     * the name server's web app. This method is supposed to marshal any
     * data on the name server that the requesting client should process.
     *
     * @param int $machine_id id of client requesting data
     * @param array $data any additional info about data being requested
     * @return array work for the client to process
     */
    public function getTasks($machine_id, $data = null)
    {
    }
    /**
     * After a MediaUpdater client is done with the task given to it by the
     * name server's media updater, the client contact the name server's
     * web app. The name servers web app's JobController then calls this
     * method to receive the data on the name server
     *
     * @param int $machine_id id of client that is sending data to name server
     * @param mixed $data results of computation done by client
     * @return array any response information to send back to the client
     */
    public function putTasks($machine_id, $data)
    {
    }
    /**
     * Executes a method on the name server's JobController.
     * It will typically execute either getTask or putTask for a specific 
     * Mediajob or getUpdateProperties to find out the current MediaUpdater
     * should be configured.
     *
     * @param string $command the method to invoke on the name server
     * @param string $arg additional arguments to be passed to the name
     *      server
     * @return array data returned by the name server.
     */
    public static function execNameServer($command, $args = null)
    {
        $time = time();
        $session = md5($time . C\AUTH_KEY);
        $query = "c=jobs&a=$command&time=$time&session=$session";
        if ($args != null) {
            $args = L\webencode(serialize($args));
            $query .= "&args=$args";
        }
        $job_name = self::getJobName();
        if($job_name) {
            $query .= "&job=$job_name";
        }
        $current_machine = self::getCurrentmachine();
        $post_data = $query."&machine_id=" . L\webencode($current_machine);
        L\crawlLog("Contacting Name server...".
            "The url, '?', and first 256 bytes of posted query are:\n" .
            C\NAME_SERVER . "?" . substr($post_data, 0, 256));
        L\crawlLog("Will send: " . strlen($post_data) . " bytes");
        $response = FetchUrl::getPage(C\NAME_SERVER, $post_data);
        $output = false;
        $data_len = 0;
        if ($response) {
            $data_len = strlen($response);
            $output = unserialize(L\webdecode($response));
        }
        L\crawlLog("Response received: " . $data_len . " bytes");
        return $output;
    }
    /**
     * Gets the class name (less namespace and the word Job )
     * of the current MediaJob
     *
     * @return string name of the current job
     */
    public static function getJobName()
    {
        $class_name = get_called_class();
        if (substr($class_name, -3) == "Job") {
            return substr($class_name, strrpos($class_name, "\\") + 1, -3);
        }
        return "";
    }
    /**
     * Returns a hash of the url of the current machine based on the value
     * saved to current_machine_info.txt by a machine statuses request
     *
     * @return string hash of current machine url
     */
     public static function getCurrentMachine()
     {
         $current_machine_path = C\WORK_DIRECTORY .
            "/schedules/current_machine_info.txt";
         if (file_exists($current_machine_path)) {
             $current_machine = file_get_contents($current_machine_path);
         } else {
             $current_machine = L\crawlHash(C\NAME_SERVER);
         }
         return $current_machine;
     }
}
