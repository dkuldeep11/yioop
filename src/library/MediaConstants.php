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

/**
 * Shared constants and enums used by components that are involved in the
 * media related operations
 *
 * @author Chris Pollett
 */
interface MediaConstants
{
     /**
     * Used to define folder used for 
     * placing video files to be converted. 
     */
    const CONVERT_FOLDER = "/schedules/media_convert";
     /**
     * Used to define folder used for 
     * placing video files after conversion. 
     */
    const CONVERTED_FOLDER = "/schedules/media_converted";
    /**
     * The text file used to recognize the video file is 
     * about to be split.
     */
    const SPLIT_FILE = "/split.txt";
    /* The text file used to store the info of video file. */
    const FILE_INFO = "/file_info.txt";
    /**
     * The text file used to store the count of split files
     * generated from a video file. 
     */
    const COUNT_FILE = "/count.txt";
    /**
     * The text file used to store the list of split file
     * names to concatenate them. 
     */
    const ASSEMBLE_FILE = "/ready_to_assemble.txt";
    /**
     * The text file used to recognize that the file has
     * been concatenated. 
     */
    const CONCATENATED_FILE = "/concatenated.txt";
    /**
     * Used to place text files(mailer lists) for sending
     * in batches.
     */
    const MAIL_FOLDER = "/schedules/mail";
    /**
     * Magic string used to separate mail messages
     */
    const MESSAGE_SEPARATOR = "+-7b6Ze3ef#a";
}
