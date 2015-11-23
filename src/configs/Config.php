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
 * Used to set the configuration settings of the SeekQuarry project.
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\configs;

/**
 * So can autoload classes. We try to use the autoloader that
 * Composer would define but if that fails we use a default autoloader
 */
if (file_exists(__DIR__."/../../vendor/autoload.php")) {
    require_once __DIR__."/../../vendor/autoload.php";
} else {
    spl_autoload_register(function ($class) {
        // project-specific namespace prefix
        $prefix = 'seekquarry\\yioop\\tests';
        // does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            $prefix = 'seekquarry\\yioop';
            $len = strlen($prefix);
            // no, move to the next registered autoloader
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            } else {
                $check_dirs = [WORK_DIRECTORY . "/app", BASE_DIR];
            }
        } else {
            $check_dirs = [PARENT_DIR . "/tests"];
        }
        // get the relative class name
        $relative_class = substr($class, $len);
        // use forward-slashes, add ./php
        $unixify_class_name = "/".str_replace('\\', '/', $relative_class) .
            '.php';
        foreach($check_dirs as $dir) {
            $file = $dir . $unixify_class_name;
            if (file_exists($file)) {
                require $file;
                break;
            }
        }
    });
}
/**
 * Define a constant in the Yioop configs namespace (seekquarry\yioop)
 * @param string $constant the name of the constant to define
 * @param $value the value to give it
 */
function nsdefine($constant, $value)
{
    define("seekquarry\\yioop\\configs\\" . $constant, $value);
}
/**
 * Check if a constant has been defined in the yioop configuration
 * namespace.
 * @param string $constant the constant to check if defined
 * @return bool whether or not it was
 */
function nsdefined($constant)
{
    return defined("seekquarry\\yioop\\configs\\" . $constant);
}
/** 
 * Version number for upgrade function
 * @var int
 */
nsdefine('YIOOP_VERSION', 35);
/**
 * Minimum Version fo Yioop for which keyword ad script
 * still works with this version
 * @var int
 */
nsdefine('MIN_AD_VERSION', 35);
/**
 * nsdefine's the BASE_URL constant for this script
 */
function computeBaseUrl()
{
    $pathinfo = pathinfo($_SERVER['SCRIPT_NAME']);
    $server_port = isset($_SERVER['HTTP_X_FORWARDED_PORT']) ?
        $_SERVER['HTTP_X_FORWARDED_PORT'] : (isset($_SERVER['SERVER_PORT']) ?
            $_SERVER['SERVER_PORT'] : 80);
    $http = (!empty($_SERVER['HTTPS']) || $server_port == 443) ? 
        "https://" : "http://";
    $port = ( ($http == "http://" && ($server_port != 80) ||
        ($http == "https://" && $server_port != 443))) ?
        ":" . $server_port : "";
    $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] :
        "localhost";
    $dir_name = $pathinfo["dirname"];
    $extra_slash = ($dir_name == '/') ? "" : '/';
    //used in register controller to create links back to server
    nsdefine("BASE_URL", $http . $server_name . $port . $dir_name .
        $extra_slash);
}
/*
    pcre is an external library to php which can cause Yioop
    to seg fault if given instances of reg expressions with
    large recursion depth on a string.
    https://bugs.php.net/bug.php?id=47376
    The goal here is to cut off these problems before they happen.
    We do this in config.php because it is included in most Yioop
    files.
 */
ini_set('pcre.recursion_limit', 3000);
ini_set('pcre.backtrack_limit', 1000000);
    /** Calculate base directory of script
     * @ignore
     */
if (!nsdefined("BASE_DIR")) {
    nsdefine("BASE_DIR", str_replace("\\", "/", realpath(__DIR__ ."/../")));
    nsdefine("PARENT_DIR",  substr(BASE_DIR, 0, -strlen("/src")));
}
computeBaseUrl();
/** Yioop Namespace*/
nsdefine('NS', "seekquarry\\yioop\\");
/** controllers sub-namespace */
nsdefine('NS_CONFIGS', NS . "configs\\");
/** controllers sub-namespace */
nsdefine('NS_CONTROLLERS', NS . "controllers\\");
/** components sub-namespace */
nsdefine('NS_COMPONENTS', NS_CONTROLLERS . "components\\");
/** executables sub-namespace */
nsdefine('NS_EXEC', NS . "executables\\");
/** library sub-namespace */
nsdefine('NS_LIB', NS . "library\\");
/** library sub-namespace */
nsdefine('NS_JOBS', NS_LIB . "media_jobs\\");
/** Models sub-namespace */
nsdefine('NS_MODELS', NS . "models\\");
/** datasources sub-namespace */
nsdefine('NS_DATASOURCES', NS_MODELS . "datasources\\");
/** archive_bundle_iterators sub-namespace */
nsdefine('NS_ARCHIVE', NS_LIB . "archive_bundle_iterators\\");
/** indexing_plugins sub-namespace */
nsdefine('NS_PLUGINS', NS_LIB . "indexing_plugins\\");
/** indexing_plugins sub-namespace */
nsdefine('NS_PROCESSORS', NS_LIB . "processors\\");
/** locale sub-namespace */
nsdefine('NS_LOCALE', NS . "locale\\");
/** views sub-namespace */
nsdefine('NS_VIEWS', NS . "views\\");
/** elements sub-namespace */
nsdefine('NS_ELEMENTS', NS_VIEWS . "elements\\");
/** helpers sub-namespace */
nsdefine('NS_HELPERS', NS_VIEWS . "helpers\\");
/** layouts sub-namespace */
nsdefine('NS_LAYOUTS', NS_VIEWS . "layouts\\");
/** tests sub-namespace */
nsdefine('NS_TESTS', NS . "tests\\");
/** Don't display any query info*/
nsdefine('NO_DEBUG_INFO', 0);
/** bit of DEBUG_LEVEL used to indicate test cases should be displayable*/
nsdefine('TEST_INFO', 1);
/** bit of DEBUG_LEVEL used to indicate query statistics should be displayed*/
nsdefine('QUERY_INFO', 2);
/** bit of DEBUG_LEVEL used to indicate php messages should be displayed*/
nsdefine('ERROR_INFO', 4);
/** Maintenance mode restricts access to local machine*/
nsdefine("MAINTENANCE_MODE", false);
/** Constant used to indicate lasting an arbitrary number of seconds */
nsdefine('FOREVER', -2);
/** Number of seconds in a day*/
nsdefine('ONE_DAY', 86400);
/** Number of seconds in a week*/
nsdefine('ONE_WEEK', 604800);
/** Number of seconds in a 30 day month */
nsdefine('ONE_MONTH', 2592000);
/** Number of seconds in an hour */
nsdefine('ONE_HOUR', 3600);
/** Number of seconds in a minute */
nsdefine('ONE_MINUTE', 60);
/** Number of seconds in a second */
nsdefine('ONE_SECOND', 1);
if (file_exists(BASE_DIR."/configs/LocalConfig.php")) {
    /** Include any locally specified defines (could use as an alternative
        way to set work directory) */
    require_once(BASE_DIR."/configs/LocalConfig.php");
}
/** setting Profile.php to something else in LocalConfig.php allows one to have
 *  two different yioop instances share the same work_directory but maybe have
 *  different configuration settings. This might be useful if one was production
 *  and one was more dev.
 */
if (!nsdefined('PROFILE_FILE_NAME')) {
    nsdefine('PROFILE_FILE_NAME', "/Profile.php");
}
if (!nsdefined('MAINTENANCE_MESSAGE')) {
    nsdefine('MAINTENANCE_MESSAGE', <<<EOD
This Yioop! installation is undergoing maintenance, please come back later!
EOD
);
}
if (MAINTENANCE_MODE && $_SERVER["SERVER_ADDR"] != $_SERVER["REMOTE_ADDR"]) {
    echo MAINTENANCE_MESSAGE;
    exit();
}

/** */
nsdefine('DEFAULT_WORK_DIRECTORY', PARENT_DIR . "/work_directory");

if (!nsdefined('WORK_DIRECTORY')) {
/*+++ The next block of code is machine edited, change at 
your own risk, please use configure web page instead +++*/
nsdefine('WORK_DIRECTORY', DEFAULT_WORK_DIRECTORY);
/*++++++*/
// end machine edited code
}
/** Directory for local versions of web app classes*/
nsdefine('APP_DIR', WORK_DIRECTORY."/app");
/** Directory to place files such as dictionaries that will be
   converted to Bloom filter using token_tool.php. Similarly,
   can be used to hold files which will be used to prepare
   a file to assist in crawling or serving search results
*/
nsdefine('PREP_DIR', WORK_DIRECTORY."/prepare");
/** Locale dir to use in case LOCALE_DIR does not exist yet or is
 * missing some file
 */
nsdefine('FALLBACK_LOCALE_DIR', BASE_DIR."/locale");
/** Captcha mode indicating to use a text captcha*/
nsdefine('TEXT_CAPTCHA', 1);
/** Captcha mode indicating to use a hash cash computation for a captcha*/
nsdefine('HASH_CAPTCHA', 2);
/** Captcha mode indicating to use a classic image based captcha*/
nsdefine('IMAGE_CAPTCHA', 3);
/** Authentication Mode Possibility*/
nsdefine('NORMAL_AUTHENTICATION', 1);
/** Authentication Mode Possibility*/
nsdefine('ZKP_AUTHENTICATION', 2);
/** If ZKP Authentication via Fiat Shamir Protocol used how many iterations
 * to do
 */
nsdefine('FIAT_SHAMIR_ITERATIONS', 20);
if (file_exists(WORK_DIRECTORY . PROFILE_FILE_NAME)) {
    if((file_exists(WORK_DIRECTORY . "/locale/en-US") &&
        !file_exists(WORK_DIRECTORY . "/locale/en_US"))
        || (file_exists(WORK_DIRECTORY . "/app/locale/en-US") &&
        !file_exists(WORK_DIRECTORY . "/app/locale/en_US"))) {
        $old_profile = file_get_contents(WORK_DIRECTORY . PROFILE_FILE_NAME);
        $new_profile = preg_replace('/\<\?php/', "<?php\n".
            "namespace seekquarry\\yioop\\configs;\n",
            $old_profile);
        $new_profile = preg_replace("/(define(?:d?))\(/", 'ns$1(',
            $new_profile);
        file_put_contents(WORK_DIRECTORY . PROFILE_FILE_NAME, $new_profile);
    }
    require_once WORK_DIRECTORY . PROFILE_FILE_NAME;
    nsdefine('PROFILE', true);
    nsdefine('CRAWL_DIR', WORK_DIRECTORY);
    if (is_dir(APP_DIR."/locale")) {
        nsdefine('LOCALE_DIR', WORK_DIRECTORY."/app/locale");
    } else if (is_dir(WORK_DIRECTORY."/locale")) {
        //old work directory location
        nsdefine('LOCALE_DIR', WORK_DIRECTORY."/locale");
    } else {
        /** @ignore */
        nsdefine('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    }
    nsdefine('LOG_DIR', WORK_DIRECTORY."/log");
    if (nsdefined('DB_URL') && !nsdefined('DB_HOST')) {
        nsdefine('DB_HOST', DB_URL); //for backward compatibility
    }
    if (nsdefined('QUEUE_SERVER') && !nsdefined('NAME_SERVER')) {
        nsdefine('NAME_SERVER', QUEUE_SERVER); //for backward compatibility
    }
    if (NAME_SERVER == 'http://' || NAME_SERVER == 'https://') {
        nsdefine("FIX_NAME_SERVER", true);
    }
} else {
    if ((!isset( $_SERVER['SERVER_NAME']) ||
        $_SERVER['SERVER_NAME']!=='localhost')
        && !nsdefined("NO_LOCAL_CHECK") && !nsdefined("WORK_DIRECTORY")
        && php_sapi_name() != 'cli' ) {
        echo "SERVICE AVAILABLE ONLY VIA LOCALHOST UNTIL CONFIGURED";
        exit();
    }
    /** @ignore */
    nsdefine('PROFILE', false);
    nsdefine('DBMS', 'Sqlite3');
    nsdefine('AUTHENTICATION_MODE', NORMAL_AUTHENTICATION);
    nsdefine('DEBUG_LEVEL', NO_DEBUG_INFO);
    nsdefine('USE_FILECACHE', false);
    nsdefine('WEB_ACCESS', true);
    nsdefine('RSS_ACCESS', true);
    nsdefine('API_ACCESS', true);
    nsdefine('REGISTRATION_TYPE', 'disable_registration');
    nsdefine('USE_MAIL_PHP', true);
    nsdefine('MAIL_SERVER', '');
    nsdefine('MAIL_PORT', '');
    nsdefine('MAIL_USERNAME', '');
    nsdefine('MAIL_PASSWORD', '');
    nsdefine('MAIL_SECURITY', '');
    nsdefine('MEDIA_MODE', 'name_server');
    nsdefine('DB_NAME', "default");
    nsdefine('DB_USER', '');
    nsdefine('DB_PASSWORD', '');
    nsdefine('DB_HOST', '');
    /** @ignore */
    nsdefine('CRAWL_DIR', BASE_DIR);
    /** @ignore */
    nsdefine('LOCALE_DIR', FALLBACK_LOCALE_DIR);
    /** @ignore */
    nsdefine('LOG_DIR', BASE_DIR."/log");
    nsdefine('NAME_SERVER', "http://localhost/");
    nsdefine('USER_AGENT_SHORT', "NeedsNameBot");
    nsdefine('DEFAULT_LOCALE', "en-US");
    nsdefine('AUTH_KEY', 0);
    nsdefine('USE_MEMCACHE', false);
    nsdefine('USE_PROXY', false);
    nsdefine('TOR_PROXY', '127.0.0.1:9150');
    nsdefine('PROXY_SERVERS', null);
    nsdefine('WORD_SUGGEST', true);
    nsdefine('CACHE_LINK', true);
    nsdefine('SIMILAR_LINK', true);
    nsdefine('IN_LINK', true);
    nsdefine('IP_LINK', true);
    nsdefine('RESULT_SCORE', true);
    nsdefine('SIGNIN_LINK', true);
    nsdefine('NEWS_MODE', 'news_off');
    /** BM25F weight for title text */
    nsdefine ('TITLE_WEIGHT', 4);
    /** BM25F weight for other text within doc*/
    nsdefine ('DESCRIPTION_WEIGHT', 1);
    /** BM25F weight for other text within links to a doc*/
    nsdefine ('LINK_WEIGHT', 2);
    /** If that many exist, the minimum number of results to get
        and group before trying to compute the top x (say 10) results
     */
    nsdefine ('MIN_RESULTS_TO_GROUP', 200);
    /** For a given number of search results total to return (total_num)
        server_alpha*total_num/num_servers will be returned any a given
        queue server machine*/
    nsdefine ('SERVER_ALPHA', 1.6);
    nsdefine('BACKGROUND_COLOR', "#FFFFFF");
    nsdefine('FOREGROUND_COLOR', "#FFFFFF");
    nsdefine('SIDEBAR_COLOR', "#88AA44");
    nsdefine('TOPBAR_COLOR', "#EEEEFF");
    nsdefine('AD_LOCATION','none');
}
if (!nsdefined("BASE_URL")) {
    nsdefine('BASE_URL', NAME_SERVER);
}
if (!nsdefined('LOGO')) {
    /*  these defines were added to the profile at same time. So we add them
        all in  one go to both the case where we have no profile and in the
        older  profile case where they were not defined.
     */
    nsdefine('LOGO', "resources/yioop.png");
    nsdefine('M_LOGO', "resources/m-yioop.png");
    nsdefine('FAVICON', BASE_URL."favicon.ico");
    nsdefine('TIMEZONE', 'America/Los_Angeles');
    /* name of the cookie used to manage the session
       (store language and perpage settings), define CSRF token
     */
    nsdefine('SESSION_NAME', "yioopbiscuit");
    nsdefine('CSRF_TOKEN', "YIOOP_TOKEN");
}
if (!nsdefined("AD_LOCATION")) {
    nsdefine('AD_LOCATION', "none");
}
date_default_timezone_set(TIMEZONE);
if ((DEBUG_LEVEL & ERROR_INFO) == ERROR_INFO) {
    error_reporting(-1);
} else {
    error_reporting(0);
}
/** if true tests are diplayable*/
nsdefine('DISPLAY_TESTS', ((DEBUG_LEVEL & TEST_INFO) == TEST_INFO));
/** if true query statistics are diplayed */
if (!nsdefined('QUERY_STATISTICS')) {
    nsdefine('QUERY_STATISTICS', ((DEBUG_LEVEL & QUERY_INFO) == QUERY_INFO));
}
//check if mobile css and formatting should be used or not
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $agent = $_SERVER['HTTP_USER_AGENT'];
    if ((stristr($agent, "mobile") || stristr($agent, "fennec")) &&
        !stristr($agent, "ipad") ) {
        nsdefine("MOBILE", true);
    } else {
        nsdefine("MOBILE", false);
    }
} else {
    nsdefine("MOBILE", false);
}
/*
 * Various groups and user ids. These must be nsdefined before the
 * profile check and return below
 */
/** ID of the root user */
nsdefine('ROOT_ID', 1);
/** Role of the root user */
nsdefine('ADMIN_ROLE', 1);
/** Default role of an active user */
nsdefine('USER_ROLE', 2);
/** Default role of an advertiser */
nsdefine('BUSINESS_ROLE', 3);
/** ID of the group to which all Yioop users belong */
nsdefine('PUBLIC_GROUP_ID', 2);
/** ID of the group to which all Yioop users belong */
nsdefine('PUBLIC_USER_ID', 2);
/** ID of the group to which all Yioop Help Wiki articles belong */
nsdefine('HELP_GROUP_ID', 3);
if (!PROFILE) {
    return;
}
/*+++ End machine generated code, feel free to edit the below as desired +++*/
/** this is the User-Agent names the crawler provides
 * a web-server it is crawling
 */
nsdefine('USER_AGENT',
    'Mozilla/5.0 (compatible; '.USER_AGENT_SHORT.'; +'.NAME_SERVER.'bot.php)');
/**
 * To change the Open Search Tool bar name overrride the following variable
 * in your local_config.php file
 */
if (!nsdefined('SEARCHBAR_PATH')) {
    nsdefine('SEARCHBAR_PATH', NAME_SERVER . "yioopbar.xml");
}
/**
 * Phantom JS is used by some optional Javascript tests of the Yioop interface.
 * The constant PHANTOM_JS should point to the path to phantomjs
 */
if (!nsdefined("PHANTOM_JS")) {
    nsdefine("PHANTOM_JS", "phantomjs");
}
/** maximum size of a log file before it is rotated */
nsdefine("MAX_LOG_FILE_SIZE", 5000000);
/** number of log files to rotate amongst */
nsdefine("NUMBER_OF_LOG_FILES", 5);
/**
 * how long in seconds to keep a cache of a robot.txt
 * file before re-requesting it
 */
nsdefine('CACHE_ROBOT_TXT_TIME', ONE_DAY);
/**
 * Whether the scheduler should track ETag and Expires headers.
 * If you want to turn this off set the variable to false in
 * local_config.php
 */
if (!nsdefined('USE_ETAG_EXPIRES')) {
    nsdefine('USE_ETAG_EXPIRES', true);
}
/**
 * if the robots.txt has a Crawl-delay larger than this
 * value don't crawl the site.
 * maximum value for this is 255
 */
nsdefine('MAXIMUM_CRAWL_DELAY', 64);
/** maximum number of active crawl-delayed hosts */
nsdefine('MAX_WAITING_HOSTS', 250);
/** Minimum weight in priority queue before rebuilt */
nsdefine('MIN_QUEUE_WEIGHT', 1/100000);
/**  largest sized object allowed in a web archive (used to sanity check
 *  reading data out of a web archive)
 */
nsdefine('MAX_ARCHIVE_OBJECT_SIZE', 100000000);
/** Treat earlier timestamps as being indexes of format version 0 */
if (!nsdefined('VERSION_0_TIMESTAMP')) {
    nsdefine('VERSION_0_TIMESTAMP', 1369754208);
}

defineMemoryProfile();
/**
 * Code to determine how much memory current machine has
 */
function defineMemoryProfile()
{

    $memory = 4000000000; //assume have at least 4GB on a Mac (could use vm_stat)
    if (strstr(PHP_OS, "WIN")) {
        if (function_exists("exec")) {
            exec('wmic memorychip get capacity', $memory_array);
            $memory = array_sum($memory_array);
        }
    } else if (stristr(PHP_OS, "LINUX")) {
        $data = preg_split("/\s+/", file_get_contents("/proc/meminfo"));
        $memory = 1024 * intval($data[1]);
    }
    /**
     * Factor to multiply sizes of Yioop data structures with in low ram memory
     * setting (2GB)
     */
    nsdefine('MEMORY_LOW', 1);
    /**
     * Factor to multiply sizes of Yioop data structures with if have more than
     * (2GB)
     */
    nsdefine('MEMORY_STANDARD', 4);
    if ($memory < 2200000000) {
        /**
         * Based on system memory, either the low or high memory factor
         */
        nsdefine('MEMORY_PROFILE', MEMORY_LOW);
    } else {
        /**
         * @ignore
         */
        nsdefine('MEMORY_PROFILE', MEMORY_STANDARD);
    }
}

/**
 * bloom filters are used to keep track of which urls are visited,
 * this parameter determines up to how many
 * urls will be stored in a single filter. Additional filters are
 * read to and from disk.
 */
nsdefine('URL_FILTER_SIZE', MEMORY_PROFILE * 5000000);
/**
 * maximum number of urls that will be held in ram
 * (as opposed to in files) in the priority queue
 */
nsdefine('NUM_URLS_QUEUE_RAM', MEMORY_PROFILE * 80000);
/** number of documents before next gen */
nsdefine('NUM_DOCS_PER_GENERATION', MEMORY_PROFILE *10000);
/** precision to round floating points document scores */
nsdefine('PRECISION', 10);
/** maximum number of links to extract from a page on an initial pass*/
nsdefine('MAX_LINKS_TO_EXTRACT', MEMORY_PROFILE * 80);
/** maximum number of links to keep after initial extraction*/
nsdefine('MAX_LINKS_PER_PAGE', 50);
/** Estimate of the average number of links per page a document has*/
nsdefine('AVG_LINKS_PER_PAGE', 24);
/** maximum number of links to consider from a sitemap page */
nsdefine('MAX_LINKS_PER_SITEMAP', MEMORY_PROFILE * 80);
/**  maximum number of words from links to consider on any given page */
nsdefine('MAX_LINKS_WORD_TEXT', 100);
/**  maximum length of urls to try to queue, this is important for
 *  memory when creating schedule, since the amount of memory is
 *  going to be greater than the product MAX_URL_LEN*MAX_FETCH_SIZE
 *  text_processors need to promise to implement this check or rely
 *  on the base class which does implement it in extractHttpHttpsUrls
 */
nsdefine('MAX_URL_LEN', 512);
/** request this many bytes out of a page -- this is the default value to
 * use if the user doesn't set this value in the page options GUI
 */
nsdefine('PAGE_RANGE_REQUEST', 50000);
/**
 * When getting information from an index dictionary in word iterator
 * how many distinct generations to read in in one go
 */
nsdefine('NUM_DISTINCT_GENERATIONS', 20);
/**
 * Max number of chars to extract for description from a page to index.
 * Only words in the description are indexed.
 */
nsdefine('MAX_DESCRIPTION_LEN', 2000);
/**
 * Allow pages to be recrawled after this many days -- this is the
 * default value to use if the user doesn't set this value in the page options
 * GUI. What this controls is how often the page url filter is deleted.
 * A nonpositive value means the filter will never be deleted.
 */
nsdefine('PAGE_RECRAWL_FREQUENCY', -1);
/** number of multi curl page requests in one go */
nsdefine('NUM_MULTI_CURL_PAGES', 100);
/** number of pages to extract from an archive in one go */
nsdefine('ARCHIVE_BATCH_SIZE', 100);
/** time in seconds before we give up on multi page requests*/
nsdefine('PAGE_TIMEOUT', 30);
/** time in seconds before we give up on a single page request*/
nsdefine('SINGLE_PAGE_TIMEOUT', ONE_MINUTE);
/** max time in seconds in a process before write a log message if
    crawlTimeoutLog is called repeatedly from a loop
 */
nsdefine('LOG_TIMEOUT', 30);
/**
 * Maximum time a crawl daemon process can go before calling
 * @see CrawlDaemon::processHandler
 */
nsdefine('PROCESS_TIMEOUT', 4 * ONE_MINUTE);
/**
 * Number of error page 400 or greater seen from a host before crawl-delay
 * host and dump remainder from current schedule
 */
nsdefine('DOWNLOAD_ERROR_THRESHOLD', 50);
/** Crawl-delay to set in the event that DOWNLOAD_ERROR_THRESHOLD exceeded*/
nsdefine('ERROR_CRAWL_DELAY', 20);
/**
 * if FFMPEG defined, the maximum size of a uploaded video file which will
 * be automatically transcode by Yioop to mp4 and webm
 */
if (!nsdefined("MAX_VIDEO_CONVERT_SIZE")) {
    nsdefine("MAX_VIDEO_CONVERT_SIZE", 2000000000);
}
/**
 * The maximum time limit in seconds where if a file is not converted by the
 * time it will be picked up again by the client media updater
 * This value largely depends on the no of client media updaters that we have
 * and also the maximum video size that would be uploaded to yioop.
 * This value should be kept more than the sleeping time of media updater
 * loop to avoid conversion of same file multiple times.
 */
if(!nsdefined("MAX_FILE_TIMESTAMP_LIMIT")) {
    nsdefine('MAX_FILE_TIMESTAMP_LIMIT', 600);
}
/**
 * This mail timestamp limit allows mail server to create a new file 
 * and write next mailer batch in the new file. Otherwise, new mailer 
 * batch will be written in old file. For eg. new file will be created every 
 * 5 minutes as per below value.
 */
if(!nsdefined("MAX_MAIL_TIMESTAMP_LIMIT")) {
    nsdefine('MAX_MAIL_TIMESTAMP_LIMIT', 300);
}
/**
 * Default edge size of square image thumbnails in pixels
 */
nsdefine('THUMB_DIM', 128);
/**
 * Maximum size of a user thumb file that can be uploaded
 */
nsdefine('THUMB_SIZE', 1000000);
/** Characters we view as not part of words, not same as POSIX [:punct:]*/
nsdefine ('PUNCT', "\.|\,|\:|\;|\"|\'|\[|\/|\%|\?|-|" .
    "\]|\{|\}|\(|\)|\!|\||\&|\`|" .
    "\’|\‘|©|®|™|℠|…|\/|\>|，|\=|。|）|：|、|" .
    "”|“|《|》|（|「|」|★|【|】|·|\+|\*|；".
        "|！|—|―|？|！|،|؛|؞|؟|٪|٬|٭");
/** Number of total description deemed title */
nsdefine ('AD_HOC_TITLE_LENGTH', 50);
/** Used to say number of bytes in histogram bar (stats page) for file
    download sizes
 */
nsdefine('DOWNLOAD_SIZE_INTERVAL', 5000);
/** Used to say number of secs in histogram bar for file download times*/
nsdefine('DOWNLOAD_TIME_INTERVAL', 0.5);
/**
 * How many non robot urls the fetcher successfully downloads before
 * between times data sent back to queue server
 */
nsdefine ('SEEN_URLS_BEFORE_UPDATE_SCHEDULER', MEMORY_PROFILE * 95);
/** maximum number of urls to schedule to a given fetcher in one go */
nsdefine ('MAX_FETCH_SIZE', MEMORY_PROFILE * 1000);
/** fetcher must wait at least this long between multi-curl requests */
nsdefine ('MINIMUM_FETCH_LOOP_TIME', 5);
/** an idling fetcher sleeps this long between queue_server pings*/
nsdefine ('FETCH_SLEEP_TIME', 10);
/** an a queue_server minimum loop idle time*/
nsdefine ('QUEUE_SLEEP_TIME', 5);
/** How often mirror script tries to synchronize with machine it is mirroring*/
nsdefine ('MIRROR_SYNC_FREQUENCY', ONE_HOUR);
/** How often mirror script tries to notify machine it is mirroring that it
is still alive*/
nsdefine ('MIRROR_NOTIFY_FREQUENCY', ONE_MINUTE);
/** Max time before dirty index (queue_server) and
    filters (fetcher) will be force saved in seconds*/
nsdefine('FORCE_SAVE_TIME', ONE_HOUR);
/** Number of seconds of no fetcher contact before crawl is deemed dead*/
nsdefine("CRAWL_TIME_OUT", 1800);
/** maximum number of terms allowed in a conjunctive search query */
nsdefine ('MAX_QUERY_TERMS', 10);
/** When to switch to using suffice tree approach */
nsdefine ('SUFFIX_TREE_THRESHOLD', 3);
/** default number of search results to display per page */
nsdefine ('NUM_RESULTS_PER_PAGE', 10);
/** Number of recently crawled urls to display on admin screen */
nsdefine ('NUM_RECENT_URLS_TO_DISPLAY', 10);
/** Maximum time a set of results can stay in query cache before it is
    invalidated */
nsdefine ('MAX_QUERY_CACHE_TIME', 2 * ONE_DAY); //two days
/** Minimum time a set of results can stay in query cache before it is
    invalidated */
nsdefine ('MIN_QUERY_CACHE_TIME', ONE_HOUR); //one hour
/**
 * Default number of items to page through for users,roles, mixes, etc
 * on the admin screens
 */
nsdefine ('DEFAULT_ADMIN_PAGING_NUM', 50);
/** Maximum number of bytes that the file that the suggest-a-url form
 * send data to can be.
 */
nsdefine ('MAX_SUGGEST_URL_FILE_SIZE', 100000);
/** Maximum number of a user can suggest to the suggest-a-url form in one day
 */
nsdefine ('MAX_SUGGEST_URLS_ONE_DAY', 10);
/**
 * Length after which to truncate names for users/groups/roles when
 * they are displayed (not in DB)
 */
nsdefine ('NAME_TRUNCATE_LEN', 7);
/** USER STATUS value used for someone who is not in a group by can browse*/
nsdefine('NOT_MEMBER_STATUS', -1);
/** USER STATUS value used for a user who can log in and perform activities */
nsdefine('ACTIVE_STATUS', 1);
/**
 * USER STATUS value used for a user whose account is created, but which
 * still needs to undergo admin or email verification/activation
 */
nsdefine('INACTIVE_STATUS', 2);
/**
 * USER STATUS used to indicate an account which can no longer perform
 * activities but which might be retained to preserve old blog posts.
 */
nsdefine('SUSPENDED_STATUS', 3);
/** Group status used to indicate a user that has been invited to join
 * a group but who has not yet accepted
 */
nsdefine('INVITED_STATUS', 4);
/**
 * Group registration type that only allows people to join a group by
 * invitation
 */
nsdefine('NO_JOIN', 1);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner
 */
nsdefine('REQUEST_JOIN', 2);
/**
 * Group registration type that only allows people to request a membership
 * in a group from the group's owner, but allows people to browse the groups
 * content without join
 */
nsdefine('PUBLIC_BROWSE_REQUEST_JOIN', 3);
/**
 * Group registration type that allows anyone to obtain membership
 * in the group
 */
nsdefine('PUBLIC_JOIN', 4);
/**
 *  Group access code signifying only the group owner can
 *  read items posted to the group or post new items
 */
nsdefine('GROUP_PRIVATE', 1);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
nsdefine('GROUP_READ', 2);
/**
 *  Group access code signifying members of the group can
 *  read items posted to the group but only the owner can post
 *   new items
 */
nsdefine('GROUP_READ_COMMENT', 3);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 */
nsdefine('GROUP_READ_WRITE', 4);
/**
 *  Group access code signifying members of the group can both
 *  read items posted to the group as well as post new items
 *  and can edit the group's wiki
 */
nsdefine('GROUP_READ_WIKI', 5);
/**
 * Indicates a group where people can't up and down vote threads
 */
nsdefine("NON_VOTING_GROUP", 0);
/**
 * Indicates a group where people can vote up threads (but not down)
 */
nsdefine("UP_VOTING_GROUP", 1);
/**
 * Indicates a group where people can vote up and down threads
 */
nsdefine("UP_DOWN_VOTING_GROUP", 2);
/**
 *  Typical posts to a group feed are on user created threads and
 *  so are of this type
 */
nsdefine('STANDARD_GROUP_ITEM', 0);
/**
 *  set to true if Multiple news updaters are running
 *  otherwise set to false if name server is running the news updater
 */
if(!nsdefined("SEND_MAIL_MEDIA_UPDATER")) {
    nsdefine('SEND_MAIL_MEDIA_UPDATER', false);
}
/**
 *  This value will block the name server for some time 
 *  before sending mails to next mailer batch. One can set this to 0 
 *  if one does not want name server to stop.
 */
if(!nsdefined("MAIL_SENDING_INTERVAL")) {
    nsdefine('MAIL_SENDING_INTERVAL', 60);
}
/**
 *  Indicates the thread was created to go alongside the creation of a wiki
 *  page so that people can discuss the pages contents
 */
nsdefine('WIKI_GROUP_ITEM', 1);
/*
 * Database Field Sizes
 */
/* Length for names of things like first name, last name, etc */
nsdefine('NAME_LEN', 32);
/** Used for lengths of media sources, passwords, and emails */
nsdefine('LONG_NAME_LEN', 64);
/** Length for names of things like group names, etc */
nsdefine('SHORT_TITLE_LEN', 128);
/** Length for names of things like titles of blog entries, etc */
nsdefine('TITLE_LEN', 512);
/** Length of a feed item or post, etc */
nsdefine('MAX_GROUP_POST_LEN', 8192);
/** Length for for the contents of a wiki_page */
nsdefine('MAX_GROUP_PAGE_LEN', 524288);
/** Length for base 64 encode timestamps */
nsdefine('TIMESTAMP_LEN', 11);
/** Length for timestamps down to microseconds */
nsdefine('MICROSECOND_TIMESTAMP_LEN', 20);
/** Length for a CAPTCHA */
nsdefine('CAPTCHA_LEN', 6);
/** Length for a number field */
nsdefine('MAX_IP_ADDRESS_AS_STRING_LEN', 39);
/** Length for a number field */
nsdefine('NUM_FIELD_LEN', 4);
/** Length for writing mode in locales */
nsdefine('WRITING_MODE_LEN', 5);
/** Length of zero knowledge password string */
nsdefine('ZKP_PASSWORD_LEN', 200);
/** Length of advertisement name string */
nsdefine('ADVERTISEMENT_NAME_LEN', 25);
/** Length of advertisement text description */
nsdefine('ADVERTISEMENT_TEXT_LEN', 35);
/** Length of advertisement keywords */
nsdefine('ADVERTISEMENT_KEYWORD_LEN', 60);
/** Length of advertisement date */
nsdefine('ADVERTISEMENT_DATE_LEN', 20);
/** Length of advertisement destination */
nsdefine('ADVERTISEMENT_DESTINATION_LEN', 60);
/** value used for the created advertisement*/
nsdefine('ADVERTISEMENT_ACTIVE_STATUS', 1);
/** value used to stop advertisement campaign */
nsdefine('ADVERTISEMENT_DEACTIVATED_STATUS',2);
/** value used to admin suspend advertisement campaign */
nsdefine('ADVERTISEMENT_SUSPENDED_STATUS',3);
/** value used to indicate campaign completed succesfulle */
nsdefine('ADVERTISEMENT_COMPLETED_STATUS',4);
/** Truncate length for ad description and keywords*/
nsdefine ('ADVERTISEMENT_TRUNCATE_LEN', 8);
/** Initial bid amount for advertisement keyword */
nsdefine ('AD_KEYWORD_INIT_BID',1);
/** advertisement date format for start date and end date*/
nsdefine ('AD_DATE_FORMAT','Y-m-d');
/** advertisement save click action */
nsdefine('AD_SAVE_CLICK','recordClick');
/** advertisement logo*/
nsdefine('AD_LOGO','resources/adv-logo.png');
