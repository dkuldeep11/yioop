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
namespace seekquarry\yioop\library\processors;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;

/** For Yioop global defines */
require_once __DIR__."/../Utility.php";
/**
 * Base class common to all processors of web page data
 *
 * A processor is used by the crawl portion of Yioop to extract indexable
 * data from a page that might contains tags/binary data/etc that should
 * not be indexed.
 * Subclasses of PageProcessor stored in
 *     WORK_DIRECTORY/app/lib/processors
 * will be detected by Yioop. So one can add code there if one want to
 * make a custom processor for a new mimetype.
 *
 * @author Chris Pollett
 */
abstract class PageProcessor implements CrawlConstants
{
    /**
     * indexing_plugins which might be used with the current processor
     *
     * @var array
     */
    public $plugin_instances;
    /**
     * Stores the name of the summarizer used for crawling.
     * Possible values are self::BASIC, self::GRAPH_BASED_SUMMARIZER
     * and self::CENTROID_SUMMARIZER
     * @var string
     */
    public $summarizer_option;
    /**
     * Max number of chars to extract for description from a page to index.
     * Only words in the description are indexed.
     * @var int
     */
    public static $max_description_len;
    /**
     * Associative array of mime_type => (page processor name that can process
     * that type)
     * Sub-classes add to this array with the types they handle
     * @var array
     */
    public static $mime_processor = [];
    /**
     * Array filetypes which should be considered images.
     * Sub-classes add to this array with the types they handle
     * @var array
     */
    public static $image_types = [];
    /**
     * Array of file extensions which can be handled by the search engine,
     * other extensions will be ignored.
     * Sub-classes add to this array with the types they handle
     * @var array
     */
    public static $indexed_file_types = ["unknown"];
    /**
     * Set-ups the any indexing plugins associated with this page
     * processor
     *
     * @param array $plugins an array of indexing plugins which might
     *     do further processing on the data handles by this page
     *     processor
     * @param int $max_description_len maximal length of a page summary
     * @param int $summarizer_option CRAWL_CONSTANT specifying what kind
     *      of summarizer to use self::BASIC_SUMMARIZER,
     *      self::GRAPH_BASED_SUMMARIZER and self::CENTROID_SUMMARIZER
     *      self::CENTROID_SUMMARIZER
     */
    public function __construct($plugins = [], $max_description_len = null,
        $summarizer_option = self::BASIC_SUMMARIZER) {
        $this->plugin_instances = $plugins;
        $this->summarizer_option = $summarizer_option;
        if ($max_description_len != null) {
            self::$max_description_len = $max_description_len;
        } else {
            self::$max_description_len = C\MAX_DESCRIPTION_LEN;
        }
    }
    /**
     * Method used to handle processing data for a web page. It makes
     * a summary for the page (via the process() function which should
     * be subclassed) as well as runs any plugins that are associated with
     * the processors to create sub-documents
     *
     * @param string $page string of a web document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of
     *     the information in $page also has a subdocs array containing any
     *     subdocuments returned from a plugin. A subdocumenst might be
     *     things like recipes that appeared in a page or tweets, etc.
     */
    public function handle($page, $url)
    {
        $summary = $this->process($page, $url);
        if ($summary != null && isset($this->plugin_instances) &&
            is_array($this->plugin_instances) ) {
            $summary[self::SUBDOCS] = [];
            foreach ($this->plugin_instances as $plugin_instance) {
                $subdoc = null;
                $class_name = get_class($plugin_instance);
                $subtype = lcfirst(substr($class_name, 
                    strlen(C\NS_PLUGINS), -strlen("Plugin")));
                $subdocs_description = $plugin_instance->pageProcessing(
                    $page, $url);
                if (is_array($subdocs_description)
                    && count($subdocs_description) != 0) {
                    foreach ($subdocs_description as $subdoc_description) {
                        $subdoc = $subdoc_description;
                        $subdoc[self::LANG] = $summary[self::LANG];
                        $subdoc[self::LINKS] = $summary[self::LINKS];
                        $subdoc[self::PAGE] = $page;
                        $subdoc[self::SUBDOCTYPE] = $subtype;
                        $summary[self::SUBDOCS][] = $subdoc;
                    }
                }
                $plugin_instance->pageSummaryProcessing($summary, $url);
            }
        }
        return $summary;
    }
    /**
     * Should be implemented to compute a summary based on a
     * text string of a document. This method is called from
     * @see handle($page, $url)
     *
     * @param string $page string of a document
     * @param string $url location the document came from
     *
     * @return array a summary of (title, description,links, and content) of
     *     the information in $page
     */
    public abstract function process($page, $url);

    /**
     * Get processors for different file types. constructing
     * them will populate the self::$indexed_file_types,
     * self::$image_types, and self::$mime_processor arrays
     *
     * @param array $processor_objects an array of page processor objects
     *      initialized by this functions
     */
    public static function initializeIndexedFileTypes()
    {
        $proc_prefixes = [C\BASE_DIR, C\APP_DIR];
        foreach($proc_prefixes as $proc_prefix) {
            $proc_dir = $proc_prefix . "/library/processors/";
            foreach (glob("$proc_dir*Processor.php") as $filename) {
                require_once $filename;
                $proc_name = substr($filename, strlen($proc_dir),
                    -strlen(".php"));
                if (!in_array($proc_name, ["PageProcessor",
                    "ImageProcessor"])) {
                    $proc_name = C\NS_PROCESSORS . $proc_name;
                    new $proc_name();
                }
            }
        }
        self::$indexed_file_types = array_unique(self::$indexed_file_types);
    }
}
