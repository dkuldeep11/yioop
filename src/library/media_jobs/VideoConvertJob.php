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
use seekquarry\yioop\library\UrlParser;

/**
 * Media Job used to convert videos uploaded to the wiki or group feeds to
 * a common format (mp4)
 */
class VideoConvertJob extends MediaJob
{
    /**
     * Supported file types of videos that we can convert to mp4.
     * @var array
     */
    public $video_convert_types = ["mov", "avi"];
    /**
     * Datasource used to do directory level file manipulations (delete or
     * traverse)
     * @var object
     */
    public $db;
    /**
     * Sets up the datasource used for the video convert directories
     */
    public function init()
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS). "Manager";
        $this->db = new $db_class();
        $this->db->connect();
    }
    /**
     * Only run the VideoConvertJob if in distributed mode
     */
    public function checkPrerequisites()
    {
        return $this->media_updater->media_mode == 'distributed';
    }
    /**
     * Check for videos to convert. If found split to a common size to
     * send to client media updaters. (Run on name server)
     */
    public function prepareTasks()
    {
        $this->splitVideos();
    }
    /**
     * Checks if video convert task is complete for a video. If so, moves
     * movie segments to a converted folder, assembles the segments into
     * a single video file, and moves the result to the desired place.
     */
    public function finishTasks()
    {
       $this->moveVideoFoldersToConvertedDirectory();
       $this->generateAssembleVideoFile();
       $this->concatenateVideos();
    }
    /**
     * Checks name server for a video segment to convert. If there are
     * converts the mov or avi segment file to an mp4 file
     * This function would only be called by client media updaters.
     */
    public function doTasks($tasks)
    {
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        if (!file_exists($convert_folder)) {
            @mkdir($convert_folder);
            if (!file_exists($convert_folder)) {
                L\crawlLog("----Unable to create $convert_folder. Bailing!");
                return;
            }
        }
        $db = $this->db;
        $folders = glob($convert_folder."/*", GLOB_ONLYDIR);
        if (count($folders) > 0) {
            foreach($folders as $folder) {
                $db->unlinkRecursive($folder);
            }
        }
        if (!empty($tasks['data']) && !empty($tasks['file_name']) &&
            !empty($tasks['folder_name'])) {
            $data = $tasks['data'];
            $folder_name = $tasks['folder_name'];
            $file_name = $tasks['file_name'];
            $convert_path = $convert_folder . "/" . $folder_name;
            if(file_exists( $convert_path)) {
                $db->unlinkRecursive( $convert_path);
            }
            mkdir($convert_path);
            $downloaded_file =  $convert_path . "/" . $file_name;
            file_put_contents($downloaded_file, $data);
            $this->convertVideo($downloaded_file);
            $files = glob($convert_path . "/*.{mp4}", GLOB_BRACE);
            if (!$files[0]) {
                L\crawlLog("Will try to convert the file again later");
            } else {
                $converted_file_name = substr($files[0],
                    strlen($convert_path) + 1);
                /* Upload the file to the server */
                $file_data = file_get_contents($files[0]);
                $upload_task['data'] =  $file_data;
                $upload_task['file_name'] = $converted_file_name;
                $upload_task['folder_name'] = $folder_name;
                L\crawlLog("Bundling upload data");
                return $upload_task;
            }
        } else {
            L\crawlLog("No files on server to convert!");
            return false;
        }
    }
    /**
     * Generates a thumbnail from a video file assuming FFMPEG
     *
     * @param string $video_name full name and path of video file to make
     *      thumbnail from
     * @param string $thumb_name full name and path for thumbnail file
     */
    public function thumbFileFromVideo($video_name, $thumb_name)
    {
        $make_thumb_string =
            C\FFMPEG." -i \"$video_name\" -vframes 1 -map 0:v:0".
            " -vf \"scale=".C\THUMB_DIM.":".C\THUMB_DIM."\" ".
            "\"$thumb_name\" 2>&1";
        L\crawlLog("----Making thumb with $make_thumb_string");
        exec($make_thumb_string);
        clearstatcache($thumb_name);
    }
    /**
     * Splits a video into small chunks of 5 minutes
     *
     * @param string.$file_path full path of video file to be split
     * @param string file_name.name of video file along with extension
     * @param.string.$destination_directory.destination directory.name
     *      where split files would be produced
     */
    public function splitVideo($file_path, $file_name, $destination_directory)
    {
        L\crawlLog("----Splitting $file_path/$file_name...");
        $extension = "." . UrlParser::getDocumentType($file_name, "");
        $new_name = substr($file_name, 0, -strlen($extension));
        $ffmpeg = C\FFMPEG." -i \"$file_path/$file_name\" ".
            " -acodec copy -f segment -segment_time 150 ".
            "-vcodec copy -reset_timestamps 1 -map 0 ".
            "\"$destination_directory/%d$new_name$extension\"";
        L\crawlLog($ffmpeg);
        exec($ffmpeg);
    }
    /**
     * Function to look through all the video directories present in media.
     * convert folder generated by group model.and split the eligible.files.
     */
    public function splitVideos()
    {
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        if (!C\nsdefined('FFMPEG') || !file_exists($convert_folder)) {
            return;
        }
        L\crawlLog("----Looking for video files to split...");
        $type_string = "{" . implode(",", $this->video_convert_types) . "}";
        $video_paths = glob($convert_folder."/*");
        foreach ($video_paths as $video_path) {
            if (is_dir($video_path)){
                if (!file_exists($video_path . self::SPLIT_FILE)) {
                    return;
                }
                if (file_exists($video_path . self::SPLIT_FILE)) {
                    L\crawlLog("----Splitting the video $video_path");
                    $lines = file($video_path.self::FILE_INFO);
                    $folder_name = rtrim($lines[1]);
                    $file_name = rtrim($lines[3]);
                    L\crawlLog("----$folder_name : $file_name");
                    if ($folder_name && $file_name){
                        $this->splitVideo($folder_name, $file_name,
                            $video_path);
                        unlink($video_path . self::SPLIT_FILE);
                        file_put_contents($video_path . self::COUNT_FILE,
                            count(glob($video_path . "/*.$type_string",
                                GLOB_BRACE)));
                    }
                }
            }
        }
    }
    /**
     * Function to look through all the video directories present in media.
     * convert folder and move them to converted folders if all the split files.
     * are converted and are present in video.directory.under.converted.
     */
    public function moveVideoFoldersToConvertedDirectory()
    {
        L\crawlLog("----Moving video folders from media_convert to ".
            "converted...");
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        $converted_folder = C\WORK_DIRECTORY . self::CONVERTED_FOLDER;
        if(!file_exists($converted_folder)) {
            mkdir($converted_folder);
        }
        $video_paths = glob($convert_folder . "/*");
        foreach ($video_paths as $video_path) {
            L\crawlLog("----Video Path : $video_path");
            $actual_count = file_get_contents($video_path . self::COUNT_FILE);
            L\crawlLog("----Actual_count : $actual_count");
            $timestamp_files = glob($video_path."/*.time.txt");
            $checked_out = count($timestamp_files);
            L\crawlLog(" ----Checked out count : $checked_out");
            $video_folder = str_replace($convert_folder."/", "", $video_path);
            $converted_video_path = $converted_folder . "/" . $video_folder;
            $converted_count = count(glob($converted_video_path .
                "/*.{mp4}", GLOB_BRACE));
            L\crawlLog("----Converted count : $converted_count");
            if ($converted_count == $actual_count) {
                L\crawlLog("----Conversion of segments complete!");
                rename($video_path . self::COUNT_FILE,
                    $converted_video_path . self::COUNT_FILE);
                rename($video_path . self::FILE_INFO,
                    $converted_video_path . self::FILE_INFO);
                $this->db->unlinkRecursive($video_path);
            }
        }
    }
    /**
     * Function to look through all the converted video directories present in
     * media and generate the assemble video files needed for concatenating the
     * converted splitfiles.
     */
    public function generateAssembleVideoFile()
    {
        L\crawlLog("----Inside generateAssembleVideoFile function...");
        $converted_folder = C\WORK_DIRECTORY . self::CONVERTED_FOLDER;
        if(!file_exists($converted_folder)) {
            mkdir($converted_folder);
        }
        foreach (glob($converted_folder."/*") as $video_path) {
            if (file_exists($video_path . self::CONCATENATED_FILE) ||
                file_exists($video_path . self::ASSEMBLE_FILE)) {
                continue;
            }
            if (!file_exists($video_path.self::COUNT_FILE)) {
                continue;
            }
            $actual_count = file_get_contents($video_path.self::COUNT_FILE);
            $video_segments = glob($video_path . "/*.mp4");
            $converted_count = count($video_segments);
            if ($actual_count == $converted_count) {
                foreach($video_segments as $video_segment){
                    file_put_contents($video_path . self::ASSEMBLE_FILE,
                        "file "."'".(str_replace($video_path."/", "",
                        $video_segment))."'", FILE_APPEND);
                    file_put_contents($video_path.self::ASSEMBLE_FILE,
                        PHP_EOL, FILE_APPEND);
                }
            }
        }
    }
    /**
     * Concatenates split video files to generate one video file
     *
     * @param string.$text_file_name file path containing.the relative file.
     *      paths of the files to be concatenated
     * @param string file_name name of video file to be given to output file.
     * @param string $destination_directory.destination directory.name
     *      where concatenated file would be produced
     */
    public function mergeVideo($text_file_name , $file_name,
        $destination_directory)
    {
        $extension = "." . UrlParser::getDocumentType($file_name, "");
        $new_name = substr($file_name, 0, -strlen($extension));
        if (!file_exists($text_file_name)) {return; }
        $generate_output = $destination_directory."/$new_name.mp4";
        $ffmpeg = C\FFMPEG." -f concat -i \"$text_file_name\" -c copy ".
            "\"$generate_output\"";
        L\crawlLog($ffmpeg);
        exec($ffmpeg);
        if(file_exists($generate_output)) {
            return true;
        }
        return false;
    }
    /**
     * Function to look.through each video directory and call the function to
     * concatenate split files.
     */
    public function concatenateVideos()
    {
        L\crawlLog("--Concatenating videos...");
        $converted_folder = C\WORK_DIRECTORY . self::CONVERTED_FOLDER;
        if(!file_exists($converted_folder)) {
            mkdir($converted_folder);
        }
        foreach (glob($converted_folder."/*") as $video_path) {
            L\crawlLog("----Video Path " . $video_path);
            if (is_dir($video_path)){
                if(!file_exists($video_path . self::ASSEMBLE_FILE)) {
                    continue;
                }
                $assemble_file = $video_path . self::ASSEMBLE_FILE;
                $lines = file($video_path . self::FILE_INFO);
                $folder = trim($lines[1]);
                $thumb_folder = trim($lines[2]);
                $file_name = trim($lines[3]);
                if($this->mergeVideo($assemble_file, $file_name, $folder)){
                    $this->db->unlinkRecursive($video_path);
                    $video_name = $folder. "/" . $file_name;
                    $extension_len = strlen(
                        UrlParser::getDocumentType($video_name));
                    $file_prefix = substr($file_name, 0, -$extension_len - 1);
                    $thumb_file_name = $file_prefix . ".mp4.jpg";
                    $thumb_name = $thumb_folder . "/" . $thumb_file_name;
                    $this->thumbFileFromVideo($video_name, $thumb_name);
                }
            }
        }
    }
    /**
     * Function to convert avi or mov file to mp4 format.
     *
     * @param string $file_name full path of the file.
     */
    public function convertVideo($file_name)
    {
        $extension = "." . UrlParser::getDocumentType($file_name, "");
        $new_name = substr($file_name, 0, -strlen($extension));
        switch($extension)
        {
            case '.mov':
                $ffmpeg = C\FFMPEG." -i \"$file_name\" ".
                    " -vcodec h264 -acodec aac -preset veryfast -crf 28 ".
                    "-strict -2 \"$new_name.mp4\"";
            break;
            case '.avi':
                $ffmpeg = C\FFMPEG." -i \"$file_name\" ".
                    " -vcodec libx264  -preset slow -acodec aac -crf 28 ".
                    "-strict experimental -b:a 192k -ac 2 \"$new_name.mp4\"";
            break;
        }
        L\crawlLog($ffmpeg);
        exec($ffmpeg);
    }
    /**
     * Handles request to upload the posted data (video file data)
     * in correct location as per the request attributes such as
     * folder name and file name.
     * @return string message concerning success or non-success of upload
     */
    public function putTasks($machine_id, $data)
    {
        if (!isset($data['data']) || !isset($data['folder_name']) ||
            !isset($data['file_name'])) {
            return "Missing parameters in upload message";
        }
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        $converted_folder = C\WORK_DIRECTORY . self::CONVERTED_FOLDER;
        $file = $data['data'];
        $folder_name = $data['folder_name'];
        $file_name = $data['file_name'];
        $upload_path = $converted_folder . "/" . $folder_name . "/" .
            $file_name;
        $original_split_pre_file = $convert_folder . "/" . $folder_name."/".
            substr($file_name, 0, -4);
        if (!$data) {
            return "No data received by web server.";
        }
        if (file_exists($converted_folder . "/" .
            $folder_name . self::CONCATENATED_FILE)) {
            return "";
        }
        $upload_flag = false;
        if (file_exists($converted_folder . "/" . $folder_name)){
            if (file_exists($upload_path)){
                return "Video file had already been uploaded!";
            } else {
                file_put_contents($upload_path, $file);
                $upload_flag = true;
            }
        } else {
            if (!file_exists($converted_folder)) {
                mkdir($converted_folder);
            }
            mkdir($converted_folder . "/" . $folder_name);
            file_put_contents($upload_path, $data);
            $upload_flag = true;
        }
        $out = "Deleting pre-convert-segment:\n$original_split_pre_file\n";
        if ($upload_flag) {
            $originals =
                glob($original_split_pre_file.".{mov,avi}", GLOB_BRACE);
            foreach($originals as $original) {
                unlink($original);
            }
        }
        return $out . "Upload success!";
    }

    /**
     * Handles the request to get the video file from the video directory for
     * conversion. This selection is based upon if the file was taken
     * previously or not. If it was then its timestamp is checked.
     * Otherwise new file is sent for conversion along with its folder name.
     */
    public function getTasks($machine_id, $data = null)
    {
        $convert_folder = C\WORK_DIRECTORY . self::CONVERT_FOLDER;
        $current_time = time();
        $file_path = false;
        foreach (glob($convert_folder . "/*") as $folder) {
            foreach (glob($folder."/*.{mov,avi}", GLOB_BRACE) as $file) {
                $folder_name = str_replace("$convert_folder/", "", $folder);
                $file_name = str_replace($folder."/", "", $file);
                $time_file_name = $folder . "/" . $file_name . ".time.txt";
                if(file_exists($time_file_name)) {
                    $file_time = file_get_contents($time_file_name);
                    if ($current_time - $file_time >
                        C\MAX_FILE_TIMESTAMP_LIMIT) {
                        file_put_contents($time_file_name, $current_time);
                        $file_path = C\CRAWL_DIR . self::CONVERT_FOLDER .
                            "/$folder_name/$file_name";
                    }
                } else {
                    file_put_contents($time_file_name, $current_time);
                    $file_path = C\CRAWL_DIR . self::CONVERT_FOLDER .
                        "/$folder_name/$file_name";
                }
                if ($file_path) {
                    break 2;
                }
            }
        }
        if ($file_path) {
            $convert_task = [];
            $convert_task['data'] = file_get_contents($file_path);
            $convert_task['file_name'] = $file_name;
            $convert_task['folder_name'] = $folder_name;
            return $convert_task;
        }
        return false;
    }
}
