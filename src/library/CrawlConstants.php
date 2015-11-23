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
 * crawling process
 *
 * @author Chris Pollett
 */
interface CrawlConstants
{
    /**
     * Used to say what kind of queue_server this is
     */
    const BOTH = "IndexerAndScheduler";
    /**
     * Used to say what kind of queue_server this is
     */
    const INDEXER = "Indexer";
    /**
     * Used to say what kind of queue_server this is
     */
    const SCHEDULER = "Scheduler";
    const queue_base_name = "QueueBundle";
    const archive_base_name = "Archive";
    const name_archive_iterator = "NameArchiveIterator";
    const fetch_archive_iterator = "FetchArchiveIterator";
    const save_point = "SavePoint";
    const schedule_data_base_name = "ScheduleData";
    const schedule_name = "FetchSchedule";
    const robot_data_base_name = "RobotData";
    const etag_expires_data_base_name = "EtagExpiresData";
    const index_data_base_name = "IndexData";
    const network_base_name = "Network";
    const network_crawllist_base_name = "NetworkCrawlList";
    const statistics_base_name = "Statistics";
    const index_closed_name = "IndexClosed";
    const fetch_batch_name = "FetchBatch";
    const fetch_crawl_info = "FetchInfo";
    const fetch_closed_name = "FetchClosed";
    const data_base_name = "At";
    const schedule_start_name = "StartCrawlSchedule.txt";
    const robot_table_name = "robot_table.txt";
    const mirror_table_name = "mirror_table.txt";
    /** Used in priority queue*/
    const MAX = 1;
    const MIN = -1;
    /** starts of daemon processes*/
    const STOP_STATE = -1;
    const CONTINUE_STATE = 1;
    const NO_DATA_STATE = 2;
    const WAITING_START_MESSAGE_STATE = 3;
    const REDO_STATE = 4;
    const STATUS = 'a';
    const CRAWL_TIME = 'b';
    const HTTP_CODE = 'c';
    const TIMESTAMP = 'd';
    const TYPE = 'e';
    const ENCODING = 'f';
    const SEEN_URLS = 'g';
    const MACHINE = 'h';
    const INVERTED_INDEX = 'i';
    const SAVED_CRAWL_TIMES= 'j';
    const SCHEDULE_TIME = 'k';
    const URL = 'l';
    const WEIGHT = 'm';
    const ROBOT_PATHS = 'n';
    const HASH = 'o';
    const PAGE = 'q';
    const DOC_INFO = 'r';
    const TITLE = 's';
    const DESCRIPTION = 't';
    const THUMB = 'u';
    const CRAWL_DELAY = 'v';
    const LINKS = 'w';
    const ROBOT_TXT = 'x';
    const TO_CRAWL = 'y';
    const INDEX = 'z';
    const AVERAGE_TITLE_LENGTH = 'A';
    const AVERAGE_DESCRIPTION_LENGTH = 'B';
    const AVERAGE_TOTAL_LINK_TEXT_LENGTH = 'C';
    const TITLE_LENGTH = 'D';
    const DESCRIPTION_LENGTH = 'E';
    const LINK_LENGTH = 'F';
    const TITLE_WORDS = 'G';
    const DESCRIPTION_WORDS = 'H';
    const LINK_WORDS = 'I';
    const TITLE_WORD_SCORE = 'J';
    const DESCRIPTION_WORD_SCORE = 'K';
    const LINK_WORD_SCORE = 'L';
    const DOC_DEPTH = 'M';
    const DOC_RANK = 'N';
    const URL_WEIGHT = 'O';
    const INLINKS = 'P';
    const NEW_CRAWL = 'Q';
    const OFFSET = 'R';
    const PATHS = 'S';
    const HASH_URL = 'T';
    const SUMMARY_OFFSET = 'U';
    const DUMMY = 'V';
    const SITES = 'W';
    const SCORE = 'X';
    const CRAWL_ORDER = 'Y';
    const RESTRICT_SITES_BY_URL = 'Z';
    const ALLOWED_SITES = 'aa';
    const DISALLOWED_SITES = 'ab';
    const BREADTH_FIRST = 'ac';
    const PAGE_IMPORTANCE = 'ad';
    const MACHINE_URI = 'ae';
    const SITE_INFO = 'af';
    const FILETYPE = 'ag';
    const SUMMARY = 'ah';
    const URL_INFO = 'ai';
    const HASH_SEEN_URLS ='aj';
    const RECENT_URLS ='ak';
    const MEMORY_USAGE ='al';
    const DOC_ID ='am';
    const RELEVANCE ='an';
    const PAGE_RULES ='ao';
    const CACHE_PAGE_PARTITION = 'ap';
    const GENERATION = 'aq';
    const HASH_SUM_SCORE = 'ar';
    const HASH_URL_COUNT = 'as'; //not used
    const IS_DOC = 'at';
    const BOOST = 'av';
    const IP_ADDRESSES = 'au';
    const JUST_METAS = 'aw';
    const WEB_CRAWL = 'ax';
    const ARCHIVE_CRAWL = 'ay';
    const CRAWL_TYPE = 'az';
    const CRAWL_INDEX = 'ba';
    const HEADER = 'bb';
    const SERVER = 'bc';
    const SERVER_VERSION = 'bd';
    const OPERATING_SYSTEM = 'be';
    const MODIFIED = 'bf';
    const LANG = 'bg';
    const ROBOT_INSTANCE = 'bh';
    const DOC_LEN = 'bi';
    const SUBDOCS = 'bj';
    const SUBDOCTYPE = 'bk';
    const INDEXING_PLUGINS = 'bl';
    const DOMAIN_WEIGHTS = 'bm';
    const POSITION_LIST = 'bn';
    const PROXIMITY = 'bo';
    const LOCATION = 'bp';
    const INDEXED_FILE_TYPES = 'bq';
    const PAGE_RANGE_REQUEST = 'br';
    const PAGE_RECRAWL_FREQUENCY = 'bs';
    const DATA = 'bt';
    const QUEUE_SERVERS = "bu";
    const CURRENT_SERVER = "bv";
    const SIZE = "bw";
    const TOTAL_TIME = "bx";
    const DNS_TIME = "by";
    const AGENT_LIST = "bz";
    const ROBOT_METAS = "ca";
    const ARC_DIR = "cb";
    const ARC_TYPE = "cc";
    const ARC_DATA = "cd";
    const KEY = "ce";
    const MACHINE_ID = 'cf';
    const VIDEO_SOURCES = 'cg';
    const IS_FEED = 'ch';
    const SOURCE_NAME = 'ci';
    const LINK_SEEN_URLS = 'cj';
    const POST_MAX_SIZE = 'ck';
    const LOGGING = 'cl';
    const META_WORDS = 'cm';
    const CACHE_PAGES = 'cn';
    const WARC_ID = 'co';
    const START_PARTITION = 'cp';
    const INI = 'cq';
    const UI_FLAGS = 'cr';
    const KEYWORD_LINKS = 'cs';
    const END_ITERATOR = 'ct';
    const ACTIVE_CLASSIFIERS = 'cu';
    const ACTIVE_CLASSIFIERS_DATA = 'cv';
    const MAX_DESCRIPTION_LEN = 'cw';
    const CACHE_PAGE_VALIDATORS = 'cx';
    const CACHE_PAGE_VALIDATION_DATA = 'cy';
    const NUM_PARTITIONS = 'cz';
    const PARTITION_NUM = 'da';
    const ACTIVE_RANKERS = 'db';
    const USER_RANKS = "dc";
    const INDEXING_PLUGINS_DATA = "dd";
    const REPOSITORY_TYPE = 'de';
    const FILE_NAME = 'df';
    const SHA_HASH = 'dg';
    const TOR_PROXY = 'dh';
    const PROXY_SERVERS = 'di';
    const NEEDS_OFFSET_FLAG = 0x7FFFFFFF;
    const BASIC_SUMMARIZER = 'dk';
    const CENTROID_SUMMARIZER = 'dl';
    const SUMMARIZER_OPTION = 'dm';
    const WORD_CLOUD = 'dn';
    const THESAURUS_SCORE ='do';
    const IS_GOPHER_URL = "dp";
    const MINIMUM_FETCH_LOOP_TIME = "dq";
    const IMAGE_LINK = "dr";
    const GRAPH_BASED_SUMMARIZER = 'ds';
}
