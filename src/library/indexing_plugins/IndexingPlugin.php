<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2011 - 2014 Priya Gangaraju priya.gangaraju@gmail.com,
 *     Chris Pollett
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
 * @author Priya Gangaraju priya.gangaraju@gmail.com, Chris Pollett
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2011 - 2014
 * @filesource
 */
namespace seekquarry\yioop\library\indexing_plugins;

use seekquarry\yioop\configs as C;

/** For Yioop global defines and functions used by implementors */
require_once __DIR__."/../Utility.php";
/**
 * Flag to say that post_processing is occurring (used to control logging in
 * models)
 */
C\nsdefine("POST_PROCESSING", true);
/** import a  tl function into Controller Namespace */
function tl()
{
    return call_user_func_array(C\NS_LIB . "tl", func_get_args());
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/**
 * Base indexing plugin Class. An indexing plugin allows a developer
 * to do additional processing on web pages during a crawl, then after
 * the web crawl is over do post processing on the additional data
 * that was collected. For example, during a crawl one might by analysing
 * web pages mark pages that have recipes on them with the meta word
 * recipe:all, then after the crawl is over do post processing such
 * as clustering the recipe's found and add additional meta words to
 * retrieve recipe's by principle ingredient.
 *
 * Yioop comes included with two example subclasses of IndexingPlugins to
 * illustrate how to write plugins: recipe_plugin.php and word_filter.php.
 *
 * Subclasses of IndexingPlugin typically override some of the following four
 * methods:
 *
 * static getProcessors() -- returns an array of strings of page processor names
 *     which a plugin should be used with. For example, a plugin might want to
 *     alter the summary whenever an HtmlProcessor is used on a page, so
 *     this array should contain HtmlProcessor, but on the other hand, the
 *     plugin might not need to alter anything when the JpgProcessor is in use,
 *     so the returned array shouldn't contain JpgProcessor
 *
 * pageProcessing($page, $url) -- which is called by a page processor
 *    when a page is being processed. It returns additional subdoc page summary
 *    info which is then handed back to the fetcher (@see pageProcessing method
 *    below for more info.)
 *
 * pageSummaryProcessing(&$summary) -- which is called by a page processor in a
 *    fetcher after the initial summary has been generated (by processor itself
 *    and all plugins which are associated with the processor). This method can
 *    be used to further modify the summary
 *
 * getAdditionalMetaWords() -- which is called when meta words are extracted
 *     from a query at search time. This allows the plugin to specify its own
 *     meta words to be extracted from the query. @see getAdditionalMetaWords
 *     for more details on the return type of this method.
 *
 * If you would like to write a plugin which can be configured on the
 * Admin > Page Options page, then you need to write four other methods:
 *
 * loadConfiguration() -- which can read plugin configuration data from
 *    persistent storage on the name server into an array or object when a
 *    crawl is started. This data is then automatically serialized and sent to
 *    queue servers as part of starting a crawl
 *
 * setConfiguration() -- which takes a configuration array or object and uses
 *     it to initialize an instance of the plugin on a queue_server or on a
 *     fetcher.
 *
 * configureHandler(&$data) -- which is called by the AdminController
 *     pageOptions activity method to let the plugin handle any configuration
 *     $_REQUEST data sent by this activity with regard to the plugin and to
 *     also let plugin modify the $data which might be sent to the plugin's
 *     view. This method would typically be called on the name server and
 *     so can be used to save (or to call a method which saves) any
 *     configuration data extracted from the request.
 *
 * configureView(&$data) -- which is called to draw the HTML configure screen
 *     used by the plugin given the information in &$data. This might display
 *     a form a user would use to alter the behavior of the plugin
 *
 * Subclasses of IndexingPlugin stored in
 *     WORK_DIRECTORY/app/lib/indexing_plugins
 * will be detected by Yioop. So one can add code there to make it easier
 * to upgrade Yioop. I.e., your site specific code can stay in the work
 * directory and you merely need to replace the Yioop folder when upgrading.
 *
 * @author Priya Gangaraju, Chris Pollett
 */
abstract class IndexingPlugin
{
    /**
     * The IndexArchiveBundle object that this indexing plugin might
     * make changes to in its postProcessing method
     * @var object
     */
    public $index_archive;
    /**
     * Reference to a database object that might be used by models on this
     * plugin
     * @var object
     */
    public $db;
    /**
     * Builds an IndexingPlugin object. Loads in the appropriate
     * models for the given plugin object
     */
    public function __construct()
    {
        $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
        $this->db = new $db_class();
    }
    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher.
     *
     * @param string $page web-page contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array consisting of a sequence of subdoc arrays found
     *     on the given page. Each subdoc array has a self::TITLE and
     *     a self::DESCRIPTION
     */
    public function pageProcessing($page, $url) {return null;}
    /**
     * Optionally modifies the page summary array produced by the PageProcessor
     * handle method in place. This hook provides a way to easily modify the
     * title, description, and meta words of a page. Only the PAGE,
     * CRAWL_DELAY, ROBOT_PATHS, ROBOT_METAS, AGENT_LIST, TITLE,
     * DESCRIPTION, META_WORDS, LANG, LINKS, and THUMB fields of the summary
     * will be respected. If you add custom meta words, then you must define
     * them in the getAdditionalMetaWords function for this plugin, or they
     * will not be recognized in queries.
     *
     * @param array& $summary the summary data produced by the relevant page
     *     processor's handle method; modified in-place.
     * @param string $url the url where the summary contents came from
     */
    public function pageSummaryProcessing(&$summary, $url) {return null;}
    /**
     * This method is called by the queue_server with the name of
     * a completed index. This allows the indexing plugin to
     * perform searches on the index and using the results, inject
     * new page/index data into the index before it becomes available
     * for end use.
     *
     * @param string $index_name the name/timestamp of an IndexArchiveBundle
     *     to do post processing for
     */
    public function postProcessing($index_name) {return null;}
    /**
     * Returns a list of page processors that can use this plugin
     *
     * @return array string names of page processors that this plugin
     *     associates with
     */
    public static function getProcessors() {return null;}
    /**
     * Returns an associative array of meta words => description length
     * for each meta word injected by this plugin into an index. The
     * description length is used to say how the maximum length of
     * the web snippet show in search results for this meta owrd should be
     *
     * @return array meta words => description length pairs
     */
    public static function getAdditionalMetaWords() {return [];}

}
