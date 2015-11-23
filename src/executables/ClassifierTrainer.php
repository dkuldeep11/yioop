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
namespace seekquarry\yioop\executables;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlDaemon;
use seekquarry\yioop\library\classifiers\Classifier;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/*
   We must specify that we want logging enabled
 */
define("seekquarry\\yioop\\configs\\NO_LOGGING", false);
/*
   For crawlLog and Yioop Constants
 */
require_once __DIR__.'/../library/Utility.php';
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/*
    We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");
/*
   If possible, set the memory limit high enough to fit all of the features and
   training documents into memory.
 */
ini_set("memory_limit", "500M");
/**
 * This class is used to finalize a classifier via the web interface.
 *
 * Because finalizing involves training a logistic regression classifier on a
 * potentially-large set of training examples, it can take much longer than
 * would be allowed by the normal web execution time limit. So instead of
 * trying to finalize a classifier directly in the controller that handles the
 * web request, the controller kicks off a daemon that simply loads the
 * classifier, finalizes it, and saves it back to disk.
 *
 * The classifier to finalize is specified by its class label, passed as the
 * second command-line argument. The following command would be used to run
 * this script directly from the command-line:
 *
 *    $ php bin/ClassifierTrainer.php terminal LABEL
 *
 * @author Shawn Tice
 */
class ClassifierTrainer
{
    /**
     * This is the function that should be called to get the
     * ClassifierTrainer to start training a logistic regression instance for
     * a particular classifier. The class label corresponding to the
     * classifier to be finalized should be passed as the second command-line
     * argument.
     */
    public function start()
    {
        global $argv;
        CrawlDaemon::init($argv, "ClassifierTrainer");
        $label = $argv[2];
        L\crawlLog("Initializing classifier trainer log..",
            $label.'-ClassifierTrainer', true);
        $classifier = Classifier::getClassifier($label);
        $classifier->prepareToFinalize();
        $classifier->finalize();
        Classifier::setClassifier($classifier);
        L\crawlLog("Training complete.\n");
        CrawlDaemon::stop('ClassifierTrainer', $label);
    }
}
$classifier_trainer = new ClassifierTrainer();
$classifier_trainer->start();
