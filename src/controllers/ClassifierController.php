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
namespace seekquarry\yioop\controllers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\UrlParser;
use seekquarry\yioop\library\classifiers\Classifier;
use seekquarry\yioop\library\archive_bundle_iterators\MixArchiveBundleIterator;

/**
 * This class handles XmlHttpRequests to label documents during classifier
 * construction.
 *
 * Searching for new documents to label and add to the training set is a
 * heavily-interactive operation, so it is implemented using asynchronous
 * requests to this controller in order to fetch candidates for labeling and
 * add labels without reloading the classifier edit page. The admin controller
 * takes care of first displaying the "edit classifier" page, and handles
 * requests to change a classifier's class label, but this controller handles
 * the other asynchronous requests issued by the JavaScript on the page.
 *
 * @author Shawn Tice
 */
class ClassifierController extends Controller implements CrawlConstants
{
    /**
     * These are the activities supported by this controller
     * @var array
     */
    public $activities = ["classify"];
    /**
     * Checks that the request seems to be coming from a legitimate, logged-in
     * user, then dispatches to the appropriate activity.
     */
    public function processRequest()
    {
        if (!isset($_REQUEST['a']) || !$this->checkRequest()) {return;}
        $activity = $_REQUEST['a'];
        if (in_array($activity, $this->activities)) {
            $this->call($activity);
        }
    }
    /**
     * Finds the next document for which to request a label, sometimes first
     * recording the label that the user selected for the last document. This
     * method should only be called via an XmlHttpRequest initiated by the edit
     * classifier JavaScript, and consequently it always writes out
     * JSON-encoded data, which is easily decoded by the page JavaScript.
     */
    public function classify()
    {
        $arg = $this->clean($_REQUEST['arg'], 'string');
        $label = $this->clean($_REQUEST['label'], 'string');

        if (isset($_REQUEST['index'])) {
            $index = $this->clean($_REQUEST['index'], 'int');
            if (intval($index) == 1) {
                $index = $this->model("crawl")->getCurrentIndexDatabaseName();
            }
            $source_type = $this->clean($_REQUEST['type'], 'string');
            $keywords = $this->clean($_REQUEST['keywords'], 'string');
        }
        /*
           The call to prepareToLabel is important; it loads all of the data
           required to manage the training set from disk, and also determines
           what will be saved *back* to disk later.
         */
        $classifier = Classifier::getClassifier($label);
        $classifier->prepareToLabel();
        $data = [];
        switch ($arg) {
            case 'getdocs':
                /*
                   Load documents in from a user-specified index, and find the
                   next best one to label (for 'manual' source type), or label
                   them all with a single label (for either the 'positive' or
                   'negative' source types).
                 */
                $mix_iterator = $this->buildClassifierCrawlMix(
                    $label, $index, $keywords);
                if ($source_type == 'manual') {
                    $num_docs = $classifier->initBuffer($mix_iterator);
                    $classifier->computeBufferDensities();
                    $data['num_docs'] = $num_docs;
                    list($new_doc, $disagreement) =
                        $classifier->findNextDocumentToLabel();
                    if ($new_doc) {
                        $score = $classifier->classify($new_doc);
                        $data['new_doc'] = $this->prepareUnlabelledDocument(
                            $new_doc, $score, $disagreement,
                            $index, $keywords);
                    }
                    Classifier::setClassifier($classifier);
                } else if ($source_type == 'positive' ||
                    $source_type == 'negative') {
                    $doc_label = ($source_type == 'positive') ? 1 : -1;
                    $add_count = $classifier->addAllDocuments(
                        $mix_iterator, $doc_label);
                    if ($add_count > 0) {
                        /*
                           Pass true to always update accuracy after adding a
                           batch of documents all at once.
                         */
                        $classifier->train(true);
                        Classifier::setClassifier($classifier);
                    }
                    $data['add_count'] = $add_count;
                }
                break;
            case 'addlabel':
                /*
                   First label the last candidate document presented to the
                   user (potentially skipping it instead of actually applying a
                   label), then pick the next best candidate for labeling.
                   When skipping a document instead of adding a label, avoid
                   re-training since the training set hasn't actually changed.
                 */
                $doc = $_REQUEST['doc_to_label'];
                $docid = $this->clean($doc['docid'], 'int');
                $key = L\webdecode($this->clean($doc['key'], 'string'));
                $doc_label = $this->clean($doc['label'], 'int');
                $mix_iterator = $this->retrieveClassifierCrawlMix($label);
                $labels_changed = $classifier->labelDocument($key, $doc_label);
                $num_docs = $classifier->refreshBuffer($mix_iterator);
                $classifier->computeBufferDensities();
                $data['num_docs'] = $num_docs;
                if ($labels_changed) {
                    $update_accuracy = $classifier->total > 0 &&
                        $classifier->total % 10 == 0;
                    $classifier->train($update_accuracy);
                }
                list($new_doc, $disagreement) =
                    $classifier->findNextDocumentToLabel();
                if ($new_doc) {
                    $score = $classifier->classify($new_doc);
                    $data['new_doc'] = $this->prepareUnlabelledDocument(
                        $new_doc, $score, $disagreement,
                        $index, $keywords);
                }
                Classifier::setClassifier($classifier);
                break;
            case 'updateaccuracy':
                /*
                   Don't do anything other than re-compute the accuracy for the
                   current training set.
                 */
                $classifier->updateAccuracy();
                Classifier::setClassifier($classifier);
                break;
        }

        /*
           No matter which activity we ended up carrying out, always include
           the statistics that *might* have changed so that the client can just
           naively keep them up to date.
         */
        $data['positive'] = $classifier->positive;
        $data['negative'] = $classifier->negative;
        $data['total'] = $classifier->total;
        $data['accuracy'] = $classifier->accuracy;

        /*
           Pass along a new authentication token so that the client can make a
           new authenticated request after this one.
         */
        $data['authTime'] = strval(time());
        $data['authSession'] = md5($data['authTime'] . C\AUTH_KEY);

        $response = json_encode($data);
        header("Content-Type: application/json");
        header("Content-Length: ".strlen($response));
        echo $response;
    }
    /* PRIVATE METHODS */
    /**
     * Creates a new crawl mix for an existing index, with an optional query,
     * and returns an iterator for the mix. The crawl mix name is derived from
     * the class label, so that it can be easily retrieved and deleted later
     * on.
     *
     * @param string $label class label of the classifier the new crawl mix
     * will be associated with
     * @param int $crawl_time timestamp of the index to be iterated over
     * @param string $keywords an optional query used to restrict the pages
     * retrieved by the crawl mix
     * @return object A MixArchiveBundleIterator instance that will iterate
     * over the pages of the requested index
     */
    public function buildClassifierCrawlMix($label, $crawl_time, $keywords)
    {
        $crawl_model = $this->model("crawl");
        $mix_time = time();
        $mix_name = Classifier::getCrawlMixName($label);

        // Replace any existing crawl mix.
        $old_time = $crawl_model->getCrawlMixTimestamp($mix_name);
        if ($old_time) {
            $crawl_model->deleteCrawlMixIteratorState($old_time);
            $crawl_model->deleteCrawlMix($old_time);
        }

        $crawl_model->setCrawlMix(array(
            'TIMESTAMP' => $mix_time,
            'NAME' => $mix_name,
            'OWNER_ID' => $_SESSION['USER_ID'],
            'PARENT' => -1,
            'FRAGMENTS' => [
                ['RESULT_BOUND' => 1,
                 'COMPONENTS' => [[
                    'CRAWL_TIMESTAMP' => $crawl_time,
                    'WEIGHT' => 1.0,
                    'KEYWORDS' => $keywords]]]]));
        return new MixArchiveBundleIterator($mix_time, $mix_time);
    }
    /**
     * Retrieves an iterator for an existing crawl mix. The crawl mix remembers
     * its previous offset, so that the new iterator picks up where the
     * previous one left off.
     *
     * @param string $label class label of the classifier this crawl mix is
     * associated with
     * @return object new MixArchiveBundleIterator instance that picks up where
     * the previous one left off
     */
    public function retrieveClassifierCrawlMix($label)
    {
        $mix_name = Classifier::getCrawlMixName($label);
        $mix_time = $this->model("crawl")->getCrawlMixTimestamp($mix_name);
        return new MixArchiveBundleIterator($mix_time, $mix_time);
    }
    /**
     * Creates a fresh array from an existing page summary array, and augments
     * it with extra data relevant to the labeling interface on the client.
     *
     * @param array $page original page summary array
     * @param float $score classification score (estimated by the Naive Bayes
     * text classification algorithm) for $page
     * @param float $disagreement disagreement score computed for $page
     * @param int $crawl_time index the page came from
     * @param string $keywords query supplied to the crawl mix used to find
     * $page
     * @return array reduced page summary structure containing only the
     * information that the client needs to display a summary of the page
     */
    public function prepareUnlabelledDocument($page, $score, $disagreement,
        $crawl_time, $keywords)
    {
        $phrase_model = $this->model("phrase");
        // Highlight the query keywords, if any.
        $disjunct_phrases = explode("|", $keywords);
        $words = [];
        foreach ($disjunct_phrases as $disjunct_phrase) {
            list($word_struct, $format_words) =
                $phrase_model->parseWordStructConjunctiveQuery(
                    $disjunct_phrase);
            $words = array_merge($words, $format_words);
        }
        $title = $phrase_model->boldKeywords(
            $page[self::TITLE], $words);
        $description = $phrase_model->getSnippets(
            strip_tags($page[self::DESCRIPTION]), $words, 400);
        $description = $phrase_model->boldKeywords(
            $description, $words);
        $cache_link = "?c=search&amp;a=cache".
            "&amp;q=".urlencode($keywords).
            "&amp;arg=".urlencode($page[self::URL]).
            "&amp;its=".$crawl_time;
        /*
           Note that the confidence is a transformation of the score that
           converts it into a value between 0 and 1, where it's 0 if the score
           was exactly 0.5, and increases toward 1 as the score either
           increases toward 1 or decreases toward 0.
         */
        return [
            'title' => $title,
            'url' => $page[self::URL],
            'key' => L\webencode(Classifier::makeKey($page)),
            'cache_link' => $cache_link,
            'description' => $description,
            'score' => $score,
            'positive' => $score >= 0.5 ? 1 :0,
            'confidence' => abs($score - 0.5) / 0.5,
            'disagreement' => $disagreement];
    }
}
