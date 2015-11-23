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
 * This script can be used to export the public and help wiki pages for
 * Yioop system to the file public_help_pages.php . This page is then
 * used by createdb.php when creating a fresh version of the Yioop database.
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\configs;

use seekquarry\yioop\library as L;

 if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    echo "BAD REQUEST";
    exit();
}
/** For crawlHash function */
require_once __DIR__."/../library/Utility.php";
$db_class = NS_DATASOURCES . ucfirst(DBMS)."Manager";
$dbinfo = ["DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME];
$db = new $db_class();
if (!in_array(DBMS, ['sqlite', 'sqlite3'])) {
    $db->connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
} else {
    $db->connect();
}
$sql = "SELECT GPH.TITLE AS TITLE, GPH.PAGE AS PAGE, ".
    " GPH.LOCALE_TAG AS LOCALE_TAG FROM GROUP_PAGE_HISTORY GPH WHERE ".
    " GPH.GROUP_ID='".PUBLIC_GROUP_ID."' AND GPH.LOCALE_TAG <> '' AND ".
    " NOT EXISTS (SELECT * FROM GROUP_PAGE_HISTORY GP WHERE ".
    " GPH.PAGE_ID=GP.PAGE_ID AND ".
    " GPH.PUBDATE < GP.PUBDATE) ORDER BY GPH.LOCALE_TAG, GPH.TITLE";
$result = $db->execute($sql);
$app_config_dir = APP_DIR . "/configs";
if (!file_exists($app_config_dir)) {
    L\crawlLog("$app_config_dir does not exists, trying to make it...\n");
    if (!mkdir($app_config_dir)) {
        L\crawlLog("Make $app_config_dir failed, quitting");
        exit();
    }
}
$out_file = "$app_config_dir/PublicHelpPages.php";
$out = "<"."?php\n";
$out .= <<< EOD
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
 * Default Public Wiki Pages
 *
 * This file should be generated using ExportPublicHelpDb.php
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
EOD;
$out .= "\nnamespace " . substr(NS_CONFIGS, 0, -1) . ";\n\n";
$out .= <<< EOD
/**
 * Public wiki pages
 * @var array
 */

EOD;
$out .= '$public_pages = [];'."\n";
if ($result) {
    while($row = $db->fetchArray($result)) {
        $out .= '$public_pages["' . $row['LOCALE_TAG'] . '"]["' .
            $row['TITLE'] . '"] = <<< '."'EOD'\n";
        $out .= $row['PAGE'] ."\nEOD;\n";
    }
}
$out .= "//\n// Default Help Wiki Pages\n//\n";
$sql = "SELECT GPH.TITLE AS TITLE, GPH.PAGE AS PAGE, ".
    " GPH.LOCALE_TAG AS LOCALE_TAG FROM GROUP_PAGE_HISTORY GPH WHERE ".
    " GPH.GROUP_ID='".HELP_GROUP_ID."' AND GPH.LOCALE_TAG <> '' AND ".
    " NOT EXISTS (SELECT * FROM GROUP_PAGE_HISTORY GP WHERE ".
    " GPH.PAGE_ID=GP.PAGE_ID AND ".
    " GPH.PUBDATE < GP.PUBDATE) ORDER BY GPH.LOCALE_TAG, GPH.TITLE";
$result = $db->execute($sql);
$out .= <<< EOD
/**
 * Help wiki pages
 * @var array
 */
EOD;
$out .= '$help_pages = [];'."\n";
if ($result) {
    while($row = $db->fetchArray($result)) {
        $out .= '$help_pages["' . $row['LOCALE_TAG'] . '"]["' .
            $row['TITLE'] . '"] = <<< '."EOD\n";
        $out .= $row['PAGE'] ."\nEOD;\n";
    }
}
$out .= "\n";
file_put_contents($out_file, $out);
L\crawlLog("Wrote export data to $out_file");
 ?>
