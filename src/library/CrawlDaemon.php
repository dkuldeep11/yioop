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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library\CrawlConstants;

/**
 * Load the crawlLog function
 */
require_once C\BASE_DIR."/library/Utility.php";
/**
 * Used to run scripts as a daemon on *nix systems
 * To use CrawlDaemon need to declare ticks first in a scope that
 * won't go away after CrawlDaemon:init is called
 *
 * @author Chris Pollett
 */
class CrawlDaemon implements CrawlConstants
{
    /**
     * Name prefix to be used on files associated with this daemon
     * (such as lock like and messages)
     * @var string
     * @static
     */
    public static $name;
    /**
     * Subname of the name prefix used on files associated with this daemon
     * For example, the name might be fetcher, the subname might 2 to indicate
     * which fetcher daemon instance.
     *
     * @var string
     * @static
     */
    public static $subname;
    /**
     * Used by processHandler to decide whether run as daemon or not
     * @var string
     * @static
     */
    public static $mode;
    /**
     * Tick callback function used to update the timestamp in this processes
     * lock. If lock_file does not exist or more than PROCESS_TIMEOUT
     * time has elapsed since the last processHandler call it stops the process
     *
     * @param bool $continue if true only stop if lock file not present,
     *   ignore PROCESS_TIMEOUT time being exceeded.
     */
    public static function processHandler($continue = false)
    {
        static $time = 0;
        if (self::$mode != 'daemon') {
            return true;
        }
        $lock_file = CrawlDaemon::getLockFileName(self::$name, self::$subname);
        $now = time();
        if ($time == 0 ) {
            $time = $now;
        }
        $lock_exist = file_exists($lock_file);
        if (!$lock_exist || ($now - $time) > C\PROCESS_TIMEOUT) {
            $name_string = CrawlDaemon::getNameString(self::$name,
                self::$subname);
            if (($now - $time) > C\PROCESS_TIMEOUT) {
                crawlLog($name_string.": ".($now - $time) .
                    " seconds has elapsed since processHandler last called.",
                    null, true);
                crawlLog("Timeout exceeded...", null, true);
            }
            if (!$lock_exist || !$continue) {
                crawlLog("Stopping $name_string ...", null, true);
                exit();
            }
        }
        $time = $now;
        file_put_contents($lock_file, $now);
        return true;
    }
    /**
     * Used to send a message the given daemon or run the program in the
     * foreground.
     *
     * @param array $argv an array of command line arguments. The argument
     *     start will check if the process control functions exists if these
     *     do they will fork and detach a child process to act as a daemon.
     *     a lock file will be created to prevent additional daemons from
     *     running. If the message is stop then a message file is written to
     *     tell the daemon to stop. If the argument is terminal then the
     *     program won't be run as a daemon.
     * @param string $name the prefix to use for lock and message files
     * @param int $exit whether this function should exit > 0 or return (1)
     *     by default a lock file is only written if exit (this allows
     *     both queue server processes (Indexer and Scheduler) to use the
     *     same lock file. If exit is >=3 or <= -3 then doesn't check lock
     *     to see if already running before starting
     */
    public static function init($argv, $name, $exit_type = 1)
    {
        self::$name = $name;
        if (isset($argv[2]) && $argv[2] != "none") {
            self::$subname = $argv[2];
        } else {
            self::$subname = "";
        }
        //don't let our script be run from apache
        if (isset($_SERVER['DOCUMENT_ROOT']) &&
            strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
            echo "BAD REQUEST";
            exit();
        }
        if (!isset($argv[1])) {
            echo "$name needs to be run with a command-line argument.\n";
            echo "For example,\n";
            echo "php $name.php start //starts the $name as a daemon\n";
            echo "php $name.php stop //stops the $name daemon\n";
            echo "php $name.php terminal //runs $name within the current ".
                "process, not as a daemon, output going to the terminal\n";
            exit();
        }
        $messages_file = self::getMesssageFileName(self::$name, self::$subname);
        switch ($argv[1]) {
            case "start":
                $options = "";
                for ($i = 3; $i < count($argv); $i++) {
                    $options .= " ".$argv[$i];
                }
                $subname = (!isset($argv[2]) || $argv[2] == 'none') ?
                    'none' :self::$subname;
                $name_prefix = (isset($argv[3])) ? $argv[3] : self::$subname;
                $name_string = CrawlDaemon::getNameString($name, $name_prefix);
                echo "Starting $name_string...\n";
                CrawlDaemon::start($name, $subname, $options, $exit_type);
                break;
            case "stop":
                CrawlDaemon::stop($name, self::$subname);
                break;
            case "terminal":
                self::$mode = 'terminal';
                $info = [];
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));
                chmod($messages_file, 0777);
                C\nsdefine("LOG_TO_FILES", false);
                break;
            case "child":
                self::$mode = 'daemon';
                $info = [];
                $info[self::STATUS] = self::WAITING_START_MESSAGE_STATE;
                file_put_contents($messages_file, serialize($info));
                chmod($messages_file, 0777);
                C\nsdefine("LOG_TO_FILES", true);
                    // if false log messages are sent to the console
                break;
            default:
                exit();
        }
    }
    /**
     * Used to start a daemon running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *     or fetcher.
     * @param string $subname the instance name if it is possible for more
     *     than one copy of the daemon to be running at the same time
     * @param string $options a string of additional command line options
     * @param int $exit whether this function should exit > 0 or return (1)
     *     by default a lock file is only written if exit (this allows
     *     both queue server processes (Indexer and Scheduler) to use the
     *     same lock file. If exit is >=3 or <= -3 then doesn't check lock
     *     to see if already running before starting
     */
    public static function start($name, $subname = "", $options = "", $exit = 1)
    {
        $tmp_subname = ($subname == 'none') ? '' : $subname;
        $lock_file = CrawlDaemon::getLockFileName($name, $tmp_subname);
        if (file_exists($lock_file) && ($exit < 3 && $exit > -3)) {
            $time = intval(file_get_contents($lock_file));
            if (time() - $time < C\PROCESS_TIMEOUT) {
                echo "$name appears to be already running...\n";
                echo "Try stopping it first, then running start.";
                exit();
            }
        }
        $php = "php";
        if (C\nsdefined("PHP_PATH") ) {
            $php = C\PHP_PATH."/".$php;
        }
        /* make sure hhvm has write access to the folder
           of the owner of the webserver process so it can write
           a .hhvm.hhbc file
         */
        if(function_exists("posix_getpwuid")) {
            $process_user_info = posix_getpwuid(posix_getuid());
            $process_home = $process_user_info['dir'];
            if (C\nsdefined("FORCE_HHVM") || (
                stristr(phpversion(), "hhvm") !==false &&
                posix_access($process_home, POSIX_W_OK))) {
                $php = 'hhvm -f ';
                if (C\nsdefined("HHVM_PATH") ) {
                    $php = C\HHVM_PATH."/".$php;
                }
            }
        }
        if (strstr(PHP_OS, "WIN")) {
            $base_dir = str_replace("/", "\\", C\BASE_DIR);
            $script = "start /B $php ".
                $base_dir."\\executables\\$name.php child %s";
        } else {
            $script = "$php '".
                C\BASE_DIR."/executables/$name.php' child %s < /dev/null ".
                " > /dev/null &";
        }
        $total_options = "$subname $options";
        $at_job = sprintf($script, $total_options);
        pclose(popen($at_job, "r"));
        if ($exit != 0) {
            file_put_contents($lock_file,  time());
        }
        if ($exit > 0) {
            exit();
        }
    }
    /**
     * Used to execute a shell command in its own process
     *
     * @param string $cmd the command to execute
     */
    public static function execInOwnProcess($cmd)
    {
        if (strstr(PHP_OS, "WIN")) {
            $job = "start /B $cmd ";
        } else {
            $job = "$cmd < /dev/null > /dev/null &";
        }
        pclose(popen($job, "r"));
    }
    /**
     * Used to stop a daemon that is running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *     or fetcher.
     * @param string $subname the instance name if it is possible for more
     *     than one copy of the daemon to be running at the same time
     * @param bool $exit whether this method should just return (false) or
     *      call exit() (true)
     */
    public static function stop($name, $subname = "", $exit = true)
    {
        $name_string = CrawlDaemon::getNameString($name, $subname);
        $lock_file = CrawlDaemon::getLockFileName($name, $subname);
        $not_web_setting = (php_sapi_name() == 'cli');
        if (file_exists($lock_file)) {
            unlink($lock_file);
            if ($not_web_setting) {
                crawlLog("Sending stop signal to $name_string...");
            }
        } else if ($not_web_setting) {
            crawlLog("$name_string does not appear to running...");
        }
        if ($exit) {
            exit();
        }
    }
    /**
     * Used to return the string name of the messages file used to pass
     * messages to a daemon running in the background
     *
     * @param string $name the main name of this daemon such as queue_server
     *     or fetcher.
     * @param string $subname the instance name if it is possible for more
     *     than one copy of the daemon to be running at the same time
     *
     * @return string the name of the message file for the daemon with
     *     the given name and subname
     */
    public static function getMesssageFileName($name, $subname = "")
    {
        return C\CRAWL_DIR."/schedules/".self::getNameString($name, $subname)
            . "Messages.txt";
    }
    /**
     * Used to return the string name of the lock file used to pass
     * by a daemon
     *
     * @param string $name the main name of this daemon such as queue_server
     *     or fetcher.
     * @param string $subname the instance name if it is possible for more
     *     than one copy of the daemon to be running at the same time
     *
     * @return string the name of the lock file for the daemon with
     *     the given name and subname
     */
    public static function getLockFileName($name, $subname = "")
    {
        return C\CRAWL_DIR."/schedules/".self::getNameString($name, $subname)
            . "Lock.txt";
    }
    /**
     * Used to return a string name for a given daemon instance
     *
     * @param string $name the main name of this daemon such as queue_server
     *     or fetcher.
     * @param string $subname the instance name if it is possible for more
     *     than one copy of the daemon to be running at the same time
     *
     * @return string a single name that combines the name and subname
     */
    public static function getNameString($name, $subname)
    {
        return ($subname == "") ? $name : $subname."-".$name;
    }
    /**
     * Returns the statuses of the running daemons
     *
     * @return array 2d array active_daemons[name][instance] = true
     */
    public static function statuses()
    {
        $prefix = C\CRAWL_DIR . "/schedules/";
        $prefix_len = strlen($prefix);
        $suffix = "Lock.txt";
        $suffix_len = strlen($suffix);
        $lock_files = "$prefix*$suffix";
        clearstatcache();
        $time = time();
        $active_daemons = [];
        foreach (glob($lock_files) as $file) {
            if ($time - filemtime($file)  < C\PROCESS_TIMEOUT) {
                $len = strlen($file) - $suffix_len - $prefix_len;
                $pre_name = substr($file, $prefix_len, $len);
                $pre_name_parts = explode("-", $pre_name);
                if (count($pre_name_parts) == 1) {
                    $active_daemons[$pre_name][-1] = 1;
                } else {
                    $first = array_shift($pre_name_parts);
                    $rest = implode("-", $pre_name_parts);
                    $active_daemons[$rest][$first] = true;
                }
            }
        }
        return $active_daemons;
    }
}

