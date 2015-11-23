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
use seekquarry\yioop\controllers\ClassifierController;
use seekquarry\yioop\library\classifiers\Classifier;

if (php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}
/** Load in global configuration settings */
require_once __DIR__.'/../configs/Config.php';
if (!C\PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}
/**
 * Immediately throw an exception for all notices and warnings, rather than
 * letting execution continue.
 * @ignore
 */
function handleError($errno, $err_str, $err_file, $err_line)
{
    if (error_reporting() == 0) {
        // Error suppressed by @, so ignore.
        return;
    }
    $msg = "$err_str in $err_file on line $err_line";
    if ($errno == E_NOTICE || $errno == E_WARNING) {
        throw new \ErrorException($msg, $errno);
    } else {
        echo $msg;
    }
}
set_error_handler(C\NS_LIB . 'classifiers\\handleError');

/**
 * Instructions for how to use classifier tool
 * @var string
 */
$INSTRUCTIONS = <<<EOD

This tool is used to automate the building and testing of classifiers,
providing an alternative to the web interface when a labeled training set is
available.

ClassifierTool.php takes an activity to perform, the name of a dataset to use,
and a label for the constructed classifier. The activity is the name of one
of the 'run*' functions implemented by this class, without the common 'run'
prefix (e.g., 'TrainAndTest'). The dataset is specified as the common prefix
of two indexes that have the suffixes "Pos" and "Neg", respectively.  So if
the prefix were "DATASET", then this tool would look for the two existing
indexes "DATASET Pos" and "DATASET Neg" from which to draw positive and
negative examples. Each document in these indexes should be a positive or
negative example of the target class, according to whether it's in the "Pos"
or "Neg" index. Finally, the label is just the label to be used for the
constructed classifier.

Beyond these options (set with the -a, -d, and -l flags), a number of other
options may be set to alter parameters used by an activity or a classifier.
These options are set using the -S, -I, -F, and -B flags, which correspond
to string, integer, float, and boolean parameters respectively. These flags
may be used repeatedly, and each expects an argument of the form NAME=VALUE,
where NAME is the name of a parameter, and VALUE is a value parsed according
to the flag. The NAME should match one of the keys of the options member of
this class, where a period ('.') may be used to specify nesting.  For
example:

    -I debug=1         # set the debug level to 1
    -B cls.use_nb=0    # tell the classifier to use Naive Bayes

To build and evaluate a classifier for the label 'spam', trained using the
two indexes "DATASET Neg" and "DATASET Pos", and a maximum of the top 25
most informative features:

php ClassifierTool.php -a TrainAndTest -d 'DATASET' -l 'spam'
    -I cls.chi2.max=25

The above assume we are in the folder of ClassifierTool.php
EOD;

/*
 * We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * Class used to encapsulate all the activities of the ClassifierTool.php
 * command line script. This script allows one to automate the building and
 * testing of classifiers, providing an alternative to the web interface when
 *
 * a labeled training set is available.
 * @author Shawn Tice
 */
class ClassifierTool
{
    /**
     * Reference to a classifier controller, used to manipulate crawl mixes in
     * the same way that the controller that handles web requests does.
     * @var object
     */
    protected $classifier_controller;

    /**
     * Reference to a crawl model object, also used to manipulate crawl mixes.
     * @var object
     */
    protected $crawl_model;
    /**
     * Options to be used by activities and constructed classifiers. These
     * options can be overridden by supplying an appropriate flag on the
     * command line, where nesting is denoted by a period (e.g., cls.chi2.max).
     * The supported options are:
     *
     *   debug: An integer, the level of debug statements to print. Larger
     *       integers specify more detailed debug output; the default value of
     *       0 indicates no debug output.
     *
     *   max_train: An integer, the maximum number of examples to use when
     *       training a classifier. The default value of null indicates that
     *       all available training examples should be used.
     *
     *   test_interval: An integer, the number of new training examples to be
     *       added before a round of testing on ALL test instances is to be
     *       executed. With an interval of 5, for example, after adding five
     *       new training examples, the classifier would be finalized and used
     *       to classify all test instances. The error is reported for each
     *       round of testing. The default value of null indicates that
     *       testing should only occur after all training examples have been
     *       added.
     *
     *   split: An integer, the number of examples from the entire set of
     *       labeled examples to use for training. The remainder are used for
     *       testing.
     *
     *   cls.use_nb: A boolean, whether or not to use the Naive Bayes
     *       classification algorithm instead of the logistic regression one
     *       in order to finalize the classifier.  The default value is false,
     *       indicating that logistic regression should be used.
     *
     *   cls.chi2.max: An integer, the maximum number of features to use when
     *       training the classifier.  The default is a relatively
     *       conservative 200.
     *
     * @var array
     */
    public $options = [
        'debug' => 0,
        'max_train' => null,
        'test_interval' => null,
        'split' => 3000,
        'cls' => [
            'use_nb' => false,
            'chi2' => [
                'max' => 200
                ]
            ]
        ];
    /**
     * Initializes the classifier controller and crawl model that will be used
     * to manage crawl mixes, used for iterating over labeled examples.
     */
    public function __construct()
    {
        $this->classifier_controller = new ClassifierController();
        $this->crawl_model = $this->classifier_controller->model("crawl");
    }
    /**
     * Parses the command-line options, returns the required arguments, and
     * updates the member variable $options with any parameters. If any of the
     * required arguments (activity, dataset, or label) are missing, then a
     * message is printed and the program exits. The optional arguments used to
     * set parameters directly modify the class state through the setOptions
     * method.
     *
     * @return array the parsed activity, dataset, and label
     */
    public function parseOptions()
    {
        $shortopts = 'l:a:d:S:I:F:B:';
        $options = getopt($shortopts);
        if (!isset($options['a'])) {
            echo "missing -a flag to choose activity to run\n";
            exit(1);
        }
        if (!isset($options['l'])) {
            echo "missing -l flag to set classifier label\n";
            exit(1);
        }
        if (!isset($options['d'])) {
            echo "missing -d flag to choose dataset to use\n";
            exit(1);
        }
        $activity = $options['a'];
        $label = Classifier::cleanLabel($options['l']);
        $dataset_name = $options['d'];
        unset($options['a'], $options['l'], $options['d']);
        foreach ($options as $opt_name => $value) {
            switch ($opt_name) {
            case 'S':
                $this->setOptions($value);
                break;
            case 'I':
                $this->setOptions($value, 'intval');
                break;
            case 'F':
                $this->setOptions($value, 'floatval');
                break;
            case 'B':
                $this->setOptions($value, 'boolval');
                break;
            default:
                echo "unsupported option: {$opt_name}\n";
                break;
            }
        }
        return [$activity, $dataset_name, $label];
    }

    /**
     * Parses the options, and if an appropriate activity exists, calls the
     * activity, passing in the label and dataset to be used; otherwise, prints
     * an error and exits.
     */
    public function main()
    {
        global $argv, $INSTRUCTIONS;
        if (count($argv) < 2) {
            echo $INSTRUCTIONS;
            exit(1);
        }
        list($activity, $dataset_name, $label) = $this->parseOptions();
        $method = "run{$activity}";
        if (method_exists($this, $method)) {
            $this->$method($label, $dataset_name);
        } else {
            echo "no activity: {$activity}\n\n";
            exit(1);
        }
    }

    /* ACTIVITIES */

    /**
     * Trains a classifier on a data set, testing at the specified intervals.
     * The testing interval is set by the test_interval parameter. Each time
     * this activity is run a new classifier is created (replacing an old one
     * with the same label, if necessary), and the classifier remains at the
     * end.
     *
     * @param string $label class label of the new classifier
     * @param string $dataset_name name of the dataset to train and test on
     */
    public function runTrainAndTest($label, $dataset_name)
    {
        $this->setDefault('max_train', 200);
        $this->logOptions();
        $classifier = $this->makeFreshClassifier($label);
        $data = $this->loadDataset($dataset_name, $label);
        $classifier->initBuffer($data['train'], 0);
        $pages = $data['train'];
        $classifier->prepareToLabel();
        $end = min($this->options['max_train'], $pages->length);
        for ($i = 1; $i <= $end; $i++) {
            $page = $pages->nextPage();
            $doc_label = $page['TRUE_LABEL'];
            $key = Classifier::makeKey($page);
            $classifier->addBufferDoc($page, false);
            $classifier->labelDocument($key, $doc_label, false);
            if ($this->isTestPoint($i, $end)) {
                Classifier::setClassifier($classifier);
                $this->testClassifier($classifier, $data);
                /*
                   Testing the classifier puts it into "classify" mode, which
                   will uses a different set of data from "label" mode, so it's
                   important to switch back.
                */
                $classifier->prepareToLabel();
            }
        }
    }
    /**
     * Like the TrainAndTest activity, but uses active training in order to
     * choose the documents to add to the training set. The method simulates
     * the process that an actual user would go through in order to label
     * documents for addition to the training set, then tests performance at
     * the specified intervals.
     *
     * @param string $label class label of the new classifier
     * @param string $dataset_name name of the dataset to train and test on
     */
    public function runActiveTrainAndTest($label, $dataset_name)
    {
        $this->setDefault('max_train', 200);
        $this->logOptions();
        $classifier = $this->makeFreshClassifier($label);
        $data = $this->loadDataset($dataset_name, $label);
        $pages = $data['train'];
        $classifier->prepareToLabel();
        $classifier->initBuffer($pages);
        $end = min($this->options['max_train'], $pages->length);
        for ($i = 1; $i <= $end; $i++) {
            list($new_doc, $disagreement) =
                $classifier->findNextDocumentToLabel();
            if ($new_doc) {
                $key = Classifier::makeKey($new_doc);
                $doc_label = $new_doc['TRUE_LABEL'];
                $classifier->labelDocument($key, $doc_label);
                $classifier->refreshBuffer($pages);
                $classifier->computeBufferDensities();
                $classifier->train();
            }
            if ($this->isTestPoint($i, $end)) {
                Classifier::setClassifier($classifier);
                $this->testClassifier($classifier, $data);
                $classifier->prepareToLabel();
            }
        }
    }

    /* UTILITY METHODS */

    /**
     * Creates a new classifier for a label, first deleting any existing
     * classifier with the same label.
     *
     * @param string $label class label of the new classifier
     * @return object created classifier instance
     */
    public function makeFreshClassifier($label)
    {
        if ($classifier = Classifier::getClassifier($label)) {
            $this->deleteClassifier($label);
        }
        $classifier = new Classifier($label, $this->options['cls']);
        Classifier::setClassifier($classifier);
        return $classifier;
    }

    /**
     * Deletes an existing classifier, specified by its label.
     *
     * @param string $label class label of the existing classifier
     */
    public function deleteClassifier($label)
    {
        Classifier::deleteClassifier($label);
        $mix_name = Classifier::getCrawlMixName($label);
        $mix_time = $this->crawl_model->getCrawlMixTimestamp($mix_name);
        if ($mix_time) {
            $this->crawl_model->deleteCrawlMixIteratorState($mix_time);
            $this->crawl_model->deleteCrawlMix($mix_time);
        }
    }
    /**
     * Fetches the summaries for pages in the indices specified by the passed
     * dataset name. This method looks for existing indexes with names matching
     * the dataset name prefix, and with suffix either "pos" or "neg" (ignoring
     * case). The pages in these indexes are shuffled into one large array, and
     * augmented with a TRUE_LABEL field that records which set they came from
     * originally. The shuffled array is then split according to the `split'
     * option, and all pages up to (but not including) the split index are used
     * for the training set; the remaining pages are used for the test set.
     *
     * @param string $dataset_name prefix of index names to draw examples from
     * @param string $class_label class label of the classifier the examples
     * will be used to train (used to name the crawl mix that iterates over
     * each index)
     * @return array training and test datasets in an associative array with
     * keys `train' and `test', where each dataset is wrapped up in a
     * PageIterator that implements the CrawlMixIterator interface.
     */
    public function loadDataset($dataset_name, $class_label)
    {
        $crawls = $this->crawl_model->getCrawlList(false, true, null);
        $dataset_name = preg_quote($dataset_name);
        $re = '/^RECRAWL::'.$dataset_name.' (pos|neg)$/i';
        $pages = [];
        foreach ($crawls as $crawl) {
            if (!preg_match($re, $crawl['DESCRIPTION'], $groups)) {
                continue;
            }
            $label = strtolower($groups[1]);
            $doc_label = $label == 'pos' ? 1 : -1;
            $mix_iterator =
                $this->classifier_controller->buildClassifierCrawlMix(
                    $class_label, $crawl['CRAWL_TIME']);
            while (!$mix_iterator->end_of_iterator) {
                $new_pages = $mix_iterator->nextPages(5000);
                /*
                   This field can be added to the results from a crawl mix
                   iterator, but we don't care about it, so we just discard it.
                */
                if (isset($new_pages['NO_PROCESS'])) {
                    unset($new_pages['NO_PROCESS']);
                }
                foreach ($new_pages as $page) {
                    $page['TRUE_LABEL'] = $doc_label;
                    $pages[] = $page;
                }
            }
        }
        shuffle($pages);
        if (count($pages) < $this->options['split']) {
            echo "split is larger than dataset\n";
            exit(1);
        }
        $data = [];
        $data['train'] = new PageIterator(
            array_slice($pages, 0, $this->options['split']));
        $data['test'] = new PageIterator(
            array_slice($pages, $this->options['split']));
        return $data;
    }

    /**
     * Determines whether to run a classification test after a certain number
     * of documents have been added to the training set. Whether or not to test
     * is determined by the `test_interval' option, which may be either null,
     * an integer, or a string. In the first case, testing only occurs after
     * all training examples have been added; in the second case, testing
     * occurs each time an additional constant number of training examples have
     * been added; and in the final case, testing occurs on a fixed schedule of
     * comma-separated offsets, such as "10,25,50,100".
     *
     * @param int $i the size of the current training set
     * @param int $total the total number of documents available to be added to
     * the training set
     * @return bool true if the `test_interval' option specifies that a round
     * of testing should occur for the current training offset, and false
     * otherwise
     */
    public function isTestPoint($i, $total)
    {
        if (is_null($this->options['test_interval'])) {
            return $i == $total;
        } else if (is_int($this->options['test_interval'])) {
            return $i % $this->options['test_interval'] == 0;
        } else {
            $re = '/(^|,)'.$i.'(,|$)/';
            return preg_match($re, $this->options['test_interval']);
        }
    }
    /**
     * Finalizes the current classifier, uses it to classify all test
     * documents, and logs the classification error.  The current classifier is
     * saved to disk after finalizing (though not before), and left in
     * `classify' mode. The iterator over the test dataset is reset for the
     * next round of testing (if any).
     *
     * @param object $classifier classifier instance to test
     * @param array $data the array of training and test datasets, constructed
     * by loadDataset, of which only the `test' dataset it used.
     */
    public function testClassifier($classifier, $data)
    {
        $classifier->prepareToFinalize();
        $classifier->finalize();
        Classifier::setClassifier($classifier);
        $classifier->prepareToClassify();
        $wrong = 0;
        $total = 0;
        $pages = $data['test'];
        while (!$pages->end_of_iterator) {
            $page = $pages->nextPage();
            $score = $classifier->classify($page);
            $page_label = $score >= 0.5 ? 1 : -1;
            if ($page_label != $page['TRUE_LABEL']) {
                $wrong++;
            }
            $total++;
        }
        $error = (float)$wrong / $total;
        $this->log(0, 'error = %.4f', $error);
        $pages->reset();
    }
    /**
     * Writes out logging information according to a detail level. The first
     * argument is an integer (potentially negative) indicating the level of
     * detail for the log message, where larger numbers indicate greater
     * detail. Each message is prefixed with a character according to its level
     * of detail, but if the detail level is greater than the level specified
     * by the `debug' option then nothing is printed. The treatment for the
     * available detail levels are as follows:
     *
     *    -2: Used for errors; always printed; prefix '! '
     *    -1: Used for log of set options; always printed; prefix '# '
     *    0+: Used for normal messages; prefix '> '
     *
     * The second argument is a printf-style string template specifying the
     * message, and each following (optional) argument is used by the template.
     * A newline is added automatically to each message.
     *
     * @param int $level level of detail for the message
     * @param string $message printf-style template for the message
     * @param string $args,... optional arguments to be used for the message
     * template
     */
    public function log(/* varargs */)
    {
        $args = func_get_args();
        $level = array_shift($args);
        if ($level > $this->options['debug']) {
            return;
        }
        if ($level == -2) {
            echo '! ';
        } else if ($level == -1) {
            echo '# ';
        } else {
            echo '> ';
        }
        call_user_func_array('printf', $args);
        echo "\n";
    }
    /**
     * Logs the current options using the log method of this class. This method
     * is used to explicitly state which settings were used for a given run of
     * an activity. The detail level passed to the log method is -1.
     *
     * @param string $root folder to write to
     * @param string $prefix to pre message (like Warning) to put at start of
     *  log message
     */
    public function logOptions($root = null, $prefix = '')
    {
        if (is_null($root)) {
            $root = $this->options;
        }
        foreach ($root as $key => $value) {
            if (is_array($value)) {
                $this->logOptions($value, $prefix.$key.'.');
            } else if (!is_null($value)) {
                if ($value === false) $value = 'false';
                else if ($value === true) $value = 'true';
                $this->log(-1, '%s%s = %s', $prefix, $key, strval($value));
            }
        }
    }
    /**
     * Sets one or more options of the form NAME=VALUE according to a converter
     * such as intval, floatval, and so on. The options may be passed in either
     * as a string (a single option) or as an array of strings, where each
     * string corresponds to an option of the same type (e.g., int).
     *
     * @param string|array $opts single option in the format NAME=VALUE, or
     * array of options, each for the same target type (e.g., int)
     * @param string $converter the name of a function that takes a string and
     * casts it to a particular type (e.g., intval, floatval)
     */
    public function setOptions($opts, $converter = null)
    {
        if (!is_array($opts)) {
            $opts = [$opts];
        }
        foreach ($opts as $opt) {
            $split = strpos($opt, '=');
            $name = substr($opt, 0, $split);
            $value = substr($opt, $split + 1);
            if ($converter) {
                if ($converter == 'boolval' && !function_exists('boolval')) {
                    $value = (bool)$value;
                } else {
                    $value = call_user_func($converter, $value);
                }
            }
            $fields = explode('.', $name);
            $field =& $this->options;
            while (!empty($fields)) {
                $top = array_shift($fields);
                if (array_key_exists($top, $field)) {
                    $field =& $field[$top];
                } else {
                    $this->log(-2, 'unknown option: "%s"', $name);
                    break;
                }
            }
            if (empty($fields)) {
                $field = $value;
            }
        }
    }

    /**
     * Sets a default value for a runtime parameter. This method is used by
     * activities to specify default values that may be overridden by passing
     * the appropriate command-line flag.
     *
     * @param string $name should end with name of runtime parameter to set
     * @param string $value what to set it to
     */
    public function setDefault($name, $value)
    {
        $fields = explode('.', $name);
        $field =& $this->options;
        while (count($fields) > 1) {
            $top = array_shift($fields);
            $field =& $field[$top];
        }
        $last = array_shift($fields);
        if (!isset($field[$last])) {
            $field[$last] = $value;
        }
    }
}
/**
 * This class provides the same interface as an iterator over crawl mixes, but
 * simply iterates over an array.
 *
 * This is used to gather all of the pages for a training set in one go (using
 * a crawl mix iterator), then repeatedly iterate over them in memory, as
 * though they were coming from the original crawl mix iterator.
 *
 * @author Shawn Tice
 */
class PageIterator
{
    /**
     * The array of pages to repeatedly iterate over.
     * @var array
     */
    public $pages;

    /**
     * The total number of pages.
     * @var int
     */
    public $length;

    /**
     * The current offset into the wrapped array.
     * @var int
     */
    public $pos;

    /**
     * Whether or not the last page has been reached.
     * @var bool
     */
    public $end_of_iterator;

    /**
     * Establishes a new iterator over a (potentially empty) array of pages.
     *
     * @param array $pages standard array of pages to iterate over
     */
    public function __construct($pages)
    {
        $this->pages = $pages;
        $this->length = count($pages);
        $this->reset();
    }

    /**
     * Resets the iterator so that the next page will be the first.
     */
    public function reset()
    {
        $this->pos = 0;
        $this->end_of_iterator = $this->length == 0;
    }

    /**
     * Returns up to the requested number of next pages, potentially an empty
     * array if there are no pages left. This method updates the
     * `end_of_iterator' flag according to whether the last page has been
     * returned.
     *
     * @param int $n maximum number of pages to return, or -1 to return all
     * remaining pages
     * @return array next $n pages, or less if there are fewer than $n
     * pages remaining
     */
    public function nextPages($n = -1)
    {
        if ($n == -1) {
            $n = $this->length - $this->pos;
        } else {
            $n = min($this->length - $this->pos, $n);
        }
        $start = $this->pos;
        $this->pos += $n;
        if ($this->pos == $this->length) {
            $this->end_of_iterator = true;
        }
        return array_slice($this->pages, $start, $n);
    }
    /**
     * Behaves like nextPages, but returns just the next page (not wrapped in
     * an array) if there is one, and null otherwise.
     *
     * @return array next page if available, and null otherwise
     */
    public function nextPage()
    {
        $next = $this->nextPages(1);
        return !empty($next) ? $next[0] : null;
    }
}
try {
    $classifier_tool = new ClassifierTool();
    $classifier_tool->main();
} catch (\ErrorException $e) {
    echo $e . "\n";
}
