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
use seekquarry\yioop\library\MailServer;

/**
 * MediaJob class for sending out emails from a Yioop instance (either in
 * response to account registrations or in response to group posts and similar
 * activities)
 */
class BulkEmailJob extends MediaJob
{
    /**
     * Mail Server object used to send mails from media updater
     * @var object
     */
    public $mail_server;
    /**
     * Set up the MailServer object used to actually send mail
     */
    public function init()
    {
        $this->mail_server = new MailServer(C\MAIL_SENDER, C\MAIL_SERVER,
            C\MAIL_SERVERPORT, C\MAIL_USERNAME, C\MAIL_PASSWORD,
            C\MAIL_SECURITY);
    }
    /**
     * Bulk mail runs if the media updater is in distributed mode or if
     * Yioop configured to send mail from media updater
     *
     * @return true if bulk mail task should be run.
     */
    public function checkPrerequisites()
    {
        $parent = $this->media_updater;
        return ($parent->media_mode == 'distributed' ||
            $parent->mail_mode);
    }
    /**
     * Function to send emails to mailer batches created by
     * mail_server. This function would periodically be invoked and
     * send emails reading data from the text files.
     */
    public function nondistributedTasks()
    {
        $mail_directory = C\WORK_DIRECTORY . self::MAIL_FOLDER;
        if(!file_exists($mail_directory)) {
            return;
        }
        $files = glob($mail_directory."/*.txt");
        if (!isset($files[0])) {
            return;
        }
        $sendable_file = false;
        foreach ($files as $email_file) {
            if(time() - filemtime($email_file) >
                C\MAX_MAIL_TIMESTAMP_LIMIT) {
                $sendable_file = $email_file;
                break;
            }
        }
        if (!$sendable_file) {
            return;
        }
        $emails_string = file_get_contents($sendable_file);
        unlink($email_file);
        $emails = explode(self::MESSAGE_SEPARATOR, $emails_string);
        foreach ($emails as $serialized_email) {
            $email = unserialize($serialized_email);
            if(count($email) == 4) {
                L\crawlLog(
                    "Sending email to {$email[2]} about {$email[0]}");
                $this->mail_server->sendImmediate(
                    $email[0], $email[1], $email[2], $email[3]);
            }
        }
    }
    /**
     * Emails a list of emails provided by the name server to the media updater
     * client
     *
     * @param array $tasks contains emails which should be sent out
     * @return mixed data to send back to name server (in this case the name
     *      of the email file that was completely sent)
     */
    public function doTasks($tasks)
    {
        if (!isset($tasks["name"]) || !isset($tasks["data"])) {
            L\crawlLog("...Email Task received incomplete !");
            return null;
        }
        L\crawlLog("----Email file name: {$tasks['name']}");
        $emails = explode(self::MESSAGE_SEPARATOR, $tasks["data"]);
        foreach ($emails as $serialized_email) {
            $email = unserialize($serialized_email);
            if(count($email) == 4) {
                L\crawlLog("Sending email to {$email[2]} about {$email[0]}");
                $this->mail_server->sendImmediate(
                    $email[0], $email[1], $email[2], $email[3]);
            }
        }
        return $tasks["name"];
    }
    /**
     * Handles the request to get the mailer list file for
     * sending emails. This selection is based upon if the file was taken
     * previously or not. If it was then it is skipped.
     * Otherwise new file is sent for sending emails and new text file
     * with taken prepended to the file name is generated.
     *
     * @param int $machine_id
     * @param array $data
     */
    public function getTasks($machine_id, $data = null)
    {
        $mail_directory = C\WORK_DIRECTORY . self::MAIL_FOLDER;
        if (!file_exists($mail_directory)) {
            return false;
        }
        $files = glob($mail_directory."/*.txt");
        foreach($files as $file){
            $file_name = str_replace($mail_directory."/","", $file);
            if (strpos($file_name, 'taken') !== false) {
                continue;
            } else {
                $is_taken_file = $mail_directory."/taken-".$file_name;
                if (in_array($is_taken_file, $files)) {
                    continue;
                } else {
                    $fp = fopen($mail_directory."/".$file_name, "a+");
                    if (flock($fp, LOCK_EX | LOCK_NB)) {
                        $taken_file_name = "taken-".$file_name;
                        file_put_contents($mail_directory."/".$taken_file_name,
                             $machine_id);
                        $task = [];
                        $task["name"] = $file_name;
                        $task["data"] = file_get_contents($file);
                        return $task;
                    } else {
                        continue;
                    }
                }
            }
        }
        return false;
    }
    /**
     * Handles request to unlock the mailing list file
     * and delete it.
     *
     * @param int $machine_id id of machine which is done sending emails
     * @param array $data file name to unlock
     */
    public function putTasks($machine_id, $data = null)
    {
        if (empty($data)) {
            return "No file name sent";
        }
        $file_name = $data;
        if (!preg_match("/\d+\.txt/", $file_name)) {
            return "Invalid file name: $file_name";
        }
        $mail_directory = C\WORK_DIRECTORY . self::MAIL_FOLDER;
        if (!file_exists($mail_directory. "/" . $file_name)) {
            return "File $file_name does not exist";
        }
        unlink($mail_directory. "/" . $file_name);
        unlink($mail_directory. "/" . "taken-".$file_name);
        return "Email task completed";
    }
}
