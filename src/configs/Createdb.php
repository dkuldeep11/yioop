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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\configs;

use seekquarry\yioop\library as L;
use seekquarry\yioop\models\Model;
use seekquarry\yioop\models\ProfileModel;
use seekquarry\yioop\models\GroupModel;

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    echo "BAD REQUEST";
    exit();
}
/** For crawlHash function */
require_once __DIR__."/../library/Utility.php";
/** For wiki page translation stuff */
require_once __DIR__."/../library/LocaleFunctions.php";
/** To make it easy to insert translations */
require_once __DIR__."/../library/UpgradeFunctions.php";
$profile_model = new ProfileModel(DB_NAME, false);
$db_class = NS_DATASOURCES . ucfirst(DBMS)."Manager";
$dbinfo = ["DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME];
if (!in_array(DBMS, ['sqlite', 'sqlite3'])) {
    $db = new $db_class();
    $db->connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    /*  postgres doesn't let you drop a database while connected to it so drop
        tables instead first
     */
    $profile_model->initializeSql($db, $dbinfo);
    $database_tables = array_keys($profile_model->create_statements);
    foreach ($database_tables as $table) {
        $db->execute("DROP TABLE ".$table);
    }
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
    $db->disconnect();

    $db->connect(); // default connection goes to actual DB
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
    $db = new $db_class();
    $db->connect();
}
if (!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}
$db->execute("INSERT INTO VERSION VALUES (".YIOOP_VERSION.")");
$creation_time = L\microTimestamp();
//numerical value of the blank password
$profile = $profile_model->getProfile(WORK_DIRECTORY);
$new_profile = $profile;
$new_profile['FIAT_SHAMIR_MODULUS'] = L\generateFiatShamirModulus();
$profile_model->updateProfile(WORK_DIRECTORY, $new_profile, $profile);
$sha1_of_blank_string =  L\bchexdec(sha1(''));
//calculating V  = S ^ 2 mod N
$temp = bcpow($sha1_of_blank_string . '', '2');
$zkp_password = ($new_profile['FIAT_SHAMIR_MODULUS']) ?
    bcmod($temp, $new_profile['FIAT_SHAMIR_MODULUS']) : "";
//default account is root without a password
$sql ="INSERT INTO USERS VALUES (".ROOT_ID.", 'admin', 'admin','root',
        'root@dev.null', '".L\crawlCrypt('')."', '".ACTIVE_STATUS.
        "', '".L\crawlCrypt('root'.AUTH_KEY.$creation_time).
        "', 0,'$creation_time', 0, 0, '$zkp_password')";
$db->execute($sql);
/* public account is an inactive account for used for public permissions
   default account is root without a password
 */
$sql ="INSERT INTO USERS VALUES (".PUBLIC_USER_ID.", 'all', 'all','public',
        'public@dev.null', '".L\crawlCrypt('')."', '".INACTIVE_STATUS.
        "', '".L\crawlCrypt('public' . AUTH_KEY . $creation_time)."', 0,
        '$creation_time', 0, 0, '$zkp_password')";
$db->execute($sql);
//default public group with group id 1
$creation_time = L\microTimestamp();
$sql = "INSERT INTO GROUPS VALUES(".PUBLIC_GROUP_ID.",'Public','".
    $creation_time."','".ROOT_ID."', '".PUBLIC_JOIN."', '".GROUP_READ.
    "', ".NON_VOTING_GROUP.", " . FOREVER . ")";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO ROLE VALUES (".ADMIN_ROLE.", 'Admin' )");
$db->execute("INSERT INTO ROLE VALUES (".USER_ROLE.", 'User' )");
$db->execute("INSERT INTO ROLE VALUES (".BUSINESS_ROLE.", 'Business User' )");
$db->execute("INSERT INTO USER_ROLE VALUES (".ROOT_ID.", ".ADMIN_ROLE.")");
$db->execute("INSERT INTO USER_GROUP VALUES (".ROOT_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (".PUBLIC_USER_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
//Create a Group for Wiki HELP.
$sql = "INSERT INTO GROUPS VALUES(" . HELP_GROUP_ID . ",'Help','" .
    $creation_time . "','" . ROOT_ID . "',
    '" . PUBLIC_BROWSE_REQUEST_JOIN . "', '" . GROUP_READ_WIKI .
    "', " . UP_DOWN_VOTING_GROUP . ", " . FOREVER . ")";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (" . PUBLIC_USER_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$group_model = new GroupModel(DB_NAME, false);
$group_model->db = $db;
// Insert Default Public Wiki Pages
if (file_exists(APP_DIR . "/configs/PublicHelpPages.php")) {
    require_once APP_DIR."/configs/PublicHelpPages.php";
} else {
    require_once BASE_DIR."/configs/PublicHelpPages.php";
}
$default_locale = L\getLocaleTag();
foreach ($public_pages as $locale_tag => $locale_pages) {
    L\setLocaleObject($locale_tag);
    foreach ($locale_pages as $page_name => $page_content) {
        $group_model->setPageName(ROOT_ID, PUBLIC_USER_ID, $page_name,
            $page_content, $locale_tag, "",
            L\tl('social_component_page_created', $page_name),
            L\tl('social_component_page_discuss_here'));
    }
}
//Insert Default Public Help pages
foreach ($help_pages as $locale_tag => $locale_pages) {
    L\setLocaleObject($locale_tag);
    foreach ($locale_pages as $page_name => $page_content) {
        $group_model->setPageName(ROOT_ID, HELP_GROUP_ID, $page_name,
            $page_content, $locale_tag, "",
            L\tl('social_component_page_created', $page_name),
            L\tl('social_component_page_discuss_here'));
    }
}
L\setLocaleObject($default_locale);
/* End Help content insertion. */
/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$locales = [
    ['en-US', 'English', 'lr-tb'],
    ['ar', 'العربية', 'rl-tb'],
    ['de', 'Deutsch', 'lr-tb'],
    ['es', 'Español', 'lr-tb'],
    ['fr-FR', 'Français', 'lr-tb'],
    ['he', 'עברית', 'rl-tb'],
    ['in-ID', 'Bahasa', 'lr-tb'],
    ['it', 'Italiano', 'lr-tb'],
    ['ja', '日本語', 'lr-tb'],
    ['ko', '한국어', 'lr-tb'],
    ['nl', 'Nederlands', 'lr-tb'],
    ['pl', 'Polski', 'lr-tb'],
    ['pt', 'Português', 'lr-tb'],
    ['ru', 'Русский', 'lr-tb'],
    ['th', 'ไทย', 'lr-tb'],
    ['vi-VN', 'Tiếng Việt', 'lr-tb'],
    ['zh-CN', '中文', 'lr-tb'],
    ['kn', 'ಕನ್ನಡ', 'lr-tb'],
    ['hi', 'हिन्दी', 'lr-tb'],
    ['tr', 'Türkçe', 'lr-tb'],
    ['fa', 'فارسی', 'rl-tb'],
    ['te', 'తెలుగు', 'lr-tb'],
];
$i = 1;
foreach ($locales as $locale) {
    $db->execute("INSERT INTO LOCALE VALUES ($i, '{$locale[0]}',
        '{$locale[1]}', '{$locale[2]}', '1')");
    $locale_index[$locale[0]] = $i;
    $i++;
}
$activities = [
    "manageAccount" => ['db_activity_manage_account',
        [
            "en-US" => 'Manage Account',
            "fa" => 'مدیریت حساب',
            "fr-FR" => 'Modifier votre compte',
            "ja" => 'アカウント管理',
            "ko" => '사용자 계정 관리',
            "nl" => 'Account Beheren',
            "vi-VN" => 'Quản lý tài khoản',
            "zh-CN" => '管理帳號',
        ]],
    "manageUsers" => ['db_activity_manage_users',
        [
            "en-US" => 'Manage Users',
            "fa" => 'مدیریت کاربران',
            "fr-FR" => 'Modifier les utilisateurs',
            "ja" => 'ユーザー管理',
            "ko" => '사용자 관리',
            "nl" => 'Gebruikers beheren',
            "vi-VN" => 'Quản lý tên sử dụng',
            "zh-CN" => '管理使用者',
        ]],
    "manageRoles" => ['db_activity_manage_roles',
        [
            "en-US" => 'Manage Roles',
            "fa" => 'مدیریت نقش‌ها',
            "fr-FR" => 'Modifier les rôles',
            "ja" => '役割管理',
            "ko" => '사용자 권한 관리',
            "nl" => 'Rollen beheren',
            "vi-VN" => 'Quản lý chức vụ',
        ]],
    "manageGroups" => ['db_activity_manage_groups',
        [
            "en-US" => 'Manage Groups',
            "fr-FR" => 'Modifier les groupes',
            "nl" => 'Groepen beheren',
        ]],
    "manageCrawls" => ['db_activity_manage_crawl',
        [
            "en-US" => 'Manage Crawl',
            "fa" => 'مدیریت خزش‌ها',
            "fr-FR" => 'Modifier les indexes',
            "ja" => '検索管理',
            "ko" => '크롤 관리',
            "nl" => 'Beheer Crawl',
            "vi-VN" => 'Quản lý sự bò',
        ]],
    "groupFeeds" => ['db_activity_group_feeds',
        [
            "en-US" => 'Feeds and Wikis',
            "nl" => 'Feeds en Wikis',
        ]],
    "mixCrawls" => ['db_activity_mix_crawls',
        [
            "en-US" => 'Mix Crawls',
            "fa" => 'ترکیب‌های خزش‌ها',
            "fr-FR" => 'Mélanger les indexes',
            "nl" => 'Mix Crawls',
        ]],
    "manageClassifiers" => ['db_activity_manage_classifiers',
        [
            "en-US" => 'Manage Classifiers',
            "fa" => '',
            "fr-FR" => 'Classificateurs',
            "nl" => 'Beheer Classifiers',
        ]],
    "pageOptions" => ['db_activity_file_options',
        [
            "en-US" => 'Page Options',
            "fa" => 'تنظیمات صفحه',
            "fr-FR" => 'Options de fichier',
            "nl" => 'Opties voor de pagina',
        ]],
    "resultsEditor" => ['db_activity_results_editor',
        [
            "en-US" => 'Results Editor',
            "fa" => 'ویرایشگر نتایج',
            "fr-FR" => 'Éditeur de résultats',
            "nl" => 'Resultaten Editor',
        ]],
    "searchSources" => ['db_activity_search_services',
        [
            "en-US" => 'Search Sources',
            "fa" => 'منابع جستجو',
            "fr-FR" => 'Sources de recherche',
            "nl" => 'Zoek Bronnen',
        ]],
    "manageMachines" => ['db_activity_manage_machines',
        [
            "en-US" => 'Manage Machines',
            "fa" => 'مدیریت دستگاه‌ها',
            "fr-FR" => 'Modifier les ordinateurs',
            "nl" => 'Beheer Machines',
        ]],
    "manageLocales" => ['db_activity_manage_locales',
        [
            "en-US" => 'Manage Locales',
            "fa" => 'مدیریت زبان‌ها',
            "fr-FR" => 'Modifier les lieux',
            "ja" => 'ローケル管理',
            "ko" => '로케일 관리',
            "nl" => 'Beheer varianten',
            "vi-VN" => 'Quản lý miền địa phương',
        ]],
    "serverSettings" => ['db_activity_server_settings',
        [
            "en-US" => 'Server Settings',
            "fr-FR" => 'Serveurs',
            "nl" => 'Server Settings',
        ]],
    "security" => ['db_activity_security',
        [
            "en-US" => 'Security',
            "fr-FR" => 'Sécurité',
            "nl" => 'Veiligheid',
        ]],
    "appearance" => ['db_activity_appearance',
        [
            "en-US" => 'Appearance',
            "fr-FR" => 'Aspect',
            "nl" => 'Verschijning',
        ]],
    "configure" => ['db_activity_configure',
        [
            "en-US" => 'Configure',
            "fa" => 'پیکربندی',
            "fr-FR" => 'Configurer',
            "ja" => '設定',
            "ko" => '구성',
            "nl" => 'Configureren',
            "vi-VN" => 'Sắp xếp hoạt động dựa theo hoạch định',
        ]],
    "manageCredits" => ['db_activity_manage_credits',
        [
            "en-US" => 'Manage Credits',
        ]],
    "manageAdvertisements" => ['db_activity_manage_advertisements',
        [
            "en-US" => 'Manage Advertisements',
        ]]
];
$i = 1;
foreach ($activities as $activity => $translation_info) {
    // set-up activity
    $db->execute("INSERT INTO ACTIVITY VALUES ($i, $i, '$activity')");
    //give admin role the ability to have that activity (except ads)
    if (in_array($activity, ["manageCredits", "manageAdvertisements"] )) {
        $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (" .
            BUSINESS_ROLE . ", $i)");
    } else {
        $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (" .
            ADMIN_ROLE . ", $i)");
    }
    $db->execute("INSERT INTO TRANSLATION
        VALUES($i, '{$translation_info[0]}')");
    foreach ($translation_info[1] as $locale_tag => $translation) {
        $index = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES ($i, $index,
            '$translation')");
    }
    $i++;
}
$new_user_activities = [
    "manageAccount",
    "manageGroups",
    "mixCrawls",
    "groupFeeds"
];
foreach ($new_user_activities as $new_activity) {
    $i = 1;
    foreach ($activities as $key => $value) {
        if ($new_activity == $key){
        //give new user role the ability to have that activity
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".
                USER_ROLE . ", $i)");
        }
        $i++;
    }
}
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
    'YouTube', 'video', 'http://www.youtube.com/watch?v={}',
    'http://i1.ytimg.com/vi/{}/default.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
    'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}',
    'http://www.metacafe.com/thumb/{}.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
    'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
    'http://www.dailymotion.com/thumbnail/video/{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634198',
    'Vimeo', 'video', 'http://player.vimeo.com/video/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634199',
    'Break.com', 'video', 'http://www.break.com/index/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634200',
    'Yahoo News', 'rss', 'http://news.yahoo.com/rss/',
    '//content/@url', 'en-US')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 'media:video site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 1, 1,
    'media:news')");

$db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
    'images','m:2', 50)");
$db->execute("INSERT INTO TRANSLATION VALUES (1002,'db_subsearch_images')");
$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
    'videos','m:3', 10)");
$db->execute("INSERT INTO TRANSLATION VALUES (1003,'db_subsearch_videos')");
$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_news',
    'news','m:4',20)");
$db->execute("INSERT INTO TRANSLATION VALUES (1004,'db_subsearch_news')");
$subsearch_translations = [
    'db_subsearch_images' => [
        'en-US' => 'Images',
        'ar' => 'لصور',
        'fa' => 'تصاوی',
        'fr-FR' => 'Images',
        'nl' => 'Beelden',
        'vi-VN' => 'Hình',
        'zh-CN' => '图象'
    ],
    'db_subsearch_videos' => [
        'en-US' => 'Videos',
        'ar' => 'فيدي',
        'fa' => 'ویدیوها',
        'fr-FR' => 'Vidéos',
        'nl' => 'Videos',
        'vi-VN' => 'Thâu hình',
        'zh-CN' => '录影'
    ],
    'db_subsearch_news' => [
        'en-US' => 'News',
        'ar' => 'أخبار',
        'fa' => 'اخبا',
        'fr-FR' => 'Actualités',
        'nl' => 'Nieuws',
        'vi-VN' => 'Tin tức',
        'zh-CN' => '新闻'
    ]
];
foreach ($subsearch_translations as $identifier => $locale_translations) {
    foreach ($locale_translations as $locale_tag => $translation) {
        L\updateTranslationForStringId($db, $identifier, $locale_tag,
            $translation);
    }
}
if (stristr(DB_HOST, "pgsql") !== false) {
    /* For postgres count initial values of SERIAL sequences
       will be screwed up unless do
     */
    $auto_tables = ["ACTIVITY" =>"ACTIVITY_ID",
        "GROUP_ITEM" =>"GROUP_ITEM_ID", "GROUP_PAGE" => "GROUP_PAGE_ID",
        "GROUPS" => "GROUP_ID", "LOCALE"=> "LOCALE_ID", "ROLE" => "ROLE_ID",
        "TRANSLATION" => "TRANSLATION_ID", "USERS" => "USER_ID"];
    foreach ($auto_tables as $table => $auto_column) {
        $sql = "SELECT MAX($auto_column) AS NUM FROM $table";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $next = $row['NUM'];
        $sequence = strtolower("{$table}_{$auto_column}_seq");
        $sql = "SELECT setval('$sequence', $next)";
        $db->execute($sql);
        $sql = "SELECT nextval('$sequence')";
        $db->execute($sql);
    }
}

$db->disconnect();
if (in_array(DBMS, ['sqlite','sqlite3'])){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}
echo "Create DB succeeded\n";
