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
 * Web page used to HTTP display error pages for
 * the SeekQuarry/Yioop Search engine
 *
 * @author Chris Pollett chris@pollett.org
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop;

use seekquarry\yioop\library as L;
use seekquarry\yioop\controllers\StaticController;

if (!isset($_REQUEST['p']) ||
    !in_array($_REQUEST['p'], ["404", "409"])) {
    $_REQUEST['p'] = "404";
}
switch ($_REQUEST['p']) {
    case "404":
        header("HTTP/1.0 404 Not Found");
        break;
    case "409":
        header("HTTP/1.0 409 Conflict");
        break;
}
$_REQUEST['c'] = "static";
define('seekquarry\\yioop\\configs\\SKIP_BOOTSTRAP', true);
/**
 * load in main entry point
 */
require_once(__DIR__."/index.php");
bootstrap();
exit();
