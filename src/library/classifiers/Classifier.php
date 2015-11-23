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
namespace seekquarry\yioop\library\classifiers;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;
use seekquarry\yioop\library\PhraseParser;

/** For wedbencode/renameSerialized objects and Yioop constants*/
require_once __DIR__."/../Utility.php";
/**
 * The primary interface for building and using classifiers. An instance of
 * this class represents a single classifier in memory, but the class also
 * provides static methods to manage classifiers on disk.
 *
 * A single classifier is a tool for determining the likelihood that a document
 * is a positive instance of a particular class. In order to do this, a
 * classifier goes through a training phase on a labeled training set where it
 * learns weights for document features (terms, for our purposes). To classify
 * a new document, the learned weights for all terms in the document are
 * combined in order to yield a pdeudo-probability that the document belongs to
 * the class.
 *
 * A classifier is composed of a candidate buffer, a training set, a set of
 * features, and a classification algorithm. In addition to the set of all
 * features, there is a restricted set of features used for training and
 * classification. There are also two classification algorithms: a Naive Bayes
 * algorithm used during labeling, and a logistic regression algorithm used to
 * train the final classifier. In general, a fresh classifier will first go
 * through a labeling phase where a collection of labeled training documents is
 * built up out of existing crawl indexes, and then a finalization phase where
 * the logistic regression algorithm will be trained on the training set
 * established in the first phase. After finalization, the classifier may be
 * used to classify new web pages during a crawl.
 *
 * During the labeling phase, the classifier fills a buffer of candidate pages
 * from the user-selected index (optionally restricted by a query), and tries
 * to pick the best one to present to the user to be labeled (here `best' means
 * the one that, once labeled, is most likely to improve classification
 * accuracy). Each labeled document is removed from the buffer, converted to a
 * feature vector (described next), and added to the training set. The expanded
 * training set is then used to train an intermediate Naive Bayes
 * classification algorithm that is in turn used to more accurately identify
 * good candidates for the next round of labeling. This phase continues until
 * the user gets tired of labeling documents, or is happy with the estimated
 * classification accuracy.
 *
 * Instead of passing around terms everywhere, each document that goes into the
 * training set is first mapped through a Features instance that maps terms to
 * feature indices (e.g. "Pythagorean" => 1, "theorem" => 2, etc.). These
 * feature indices are used internally by the classification algorithms, and by
 * the algorithms that try to pick out the most informative features. In
 * addition to keeping track of the mapping between terms and feature indices,
 * a Features instance keeps term and label statistics (such as how often a
 * term occurs in documents with a particular label) used to weight features
 * within a document and to select informative features. Finally, subclasses of
 * the Features class weight features in different ways, presenting more or
 * less of everything that's known about the frequency or informativeness of a
 * feature to classification algorithms.
 *
 * Once a sufficiently-useful training set has been built, a FeatureSelection
 * instance is used to choose the most informative features, and copy these
 * into a reduced Features instance that has a much smaller vocabulary, and
 * thus a much smaller memory footprint. For efficiency, this is the Features
 * instance used to train classification algorithms, and to classify web pages.
 * Finalization is just the process of training a logistic regression
 * classification algorithm on the full training set. This results in a set of
 * feature weights that can be used to efficiently assign a psuedo-probability
 * to the proposition that a new web page is a positive instance of the class
 * that the classifier has been trained to recognize. Training logistic
 * regression on a large training set can take a long time, so this phase is
 * carried out asynchronously, by a daemon launched in response to the
 * finalization request.
 *
 * Because the full Features instance, buffer, and training set are only needed
 * during the labeling and finalization phases, and because they can get very
 * large and take up a lot of space in memory, this class separates its large
 * instance members into separate files when serializing to disk. When a
 * classifier is first loaded into memory from disk it brings along only its
 * summary statistics, since these are all that are needed to, for example,
 * display a list of classifiers. In order to actually add new documents to the
 * training set, finalize, or classify, the classifier must first be explicitly
 * told to load the relevant data structures from disk; this is accomplished by
 * methods like prepareToLabel and prepareToClassify.  These methods load in
 * the relevant serialized structures, and mark the associated data members for
 * storage back to disk when (or if) the classifier is serialized again.
 *
 * @author Shawn Tice
 */
class Classifier implements CrawlConstants
{
    /**
     * The maximum number of candidate documents to consider at once in order
     * to find the best candidate.
     */
    const BUFFER_SIZE = 51;
    /**
     * The number of Naive Bayes instances to use to calculate disagreement
     * during candidate selection.
     */
    const COMMITTEE_SIZE = 3;
    /**
     * The maximum disagreement score between candidates. This number depends
     * on committee size, and is used to provide a slightly more user-friendly
     * estimate of how much disagreement a document causes (between 0 and 1).
     */
    const MAX_DISAGREEMENT = 1.63652; // Depends on committee size
    /**
     * Lambda parameter used in the computation of a candidate document's
     * density (smoothing for 0-frequency terms).
     */
    const DENSITY_LAMBDA = 0.5;
    /**
     * Beta parameter used in the computation of a candidate document's density
     * (sharpness of the KL-divergence).
     */
    const DENSITY_BETA = 3.0;
    /**
     * Threshold used to convert a pseudo-probability to a hard classification
     * decision. Documents with pseudo-probability >= THRESHOLD are classified
     * as positive instances.
     */
    const THRESHOLD = 0.5;
    /**
     * Indicates that a classifier needs to be finalized before it can be used.
     */
    const UNFINALIZED = 0;
    /**
     * Indicates that a classifier is currently being finalized (this may take
     * a while).
     */
    const FINALIZING = 1;
    /**
     * Indicates that a classifier has been finalized, and is ready to be used
     * for classification.
     */
    const FINALIZED = 2;
    /**
     * Default per-classifier options, which may be overridden when
     * constructing a new classifier. The supported options are:
     *
     *    float density.lambda: Lambda parameter used in the computation of a
     *        candidate document's density (smoothing for 0-frequency terms).
     *
     *    float density.beta: Beta parameter used in the computation of a
     *        candidate document's density (sharpness of the KL-divergence).
     *
     *    int label_fs.max: Use the `label_fs' most informative features to
     *        train the Naive Bayes classifiers used during labeling to
     *        compute disagreement for a document.
     *
     *    float threshold: Threshold used to convert a pseudo-probability to a
     *        hard classification decision. Documents with pseudo-probability
     *        >= `threshold' are classified as positive instances.
     *
     *    string final_algo: Algorithm to use for finalization; 'lr' for
     *        logistic regression, or 'nb' for Naive Bayes; default 'lr'.
     *
     *    int final_fs.max: Use the `final_fs' most informative features to
     *        train the final classifier.
     *
     * @var array
     */
    public $options = [
        'density' => [
            'lambda' => 0.5,
            'beta' => 3.0],
        'threshold' => 0.5,
        'label_fs' => [
            'max' => 30],
        'final_fs' => [
            'max' => 200],
        'final_algo' => 'lr'];
    /**
     * The label applied to positive instances of the class learned by this
     * classifier (e.g., `spam').
     * @var string
     */
    public $class_label;
    /**
     * Creation time as a UNIX timestamp.
     * @var int
     */
    public $timestamp;
    /**
     * Language of documents in the training set (also how new documents will
     * be treated).
     * @var string
     */
    public $lang;
    /**
     * Whether or not this classifier has had any training examples added to
     * it, and consequently whether or not its Naive Bayes classification
     * algorithm has every been trained.
     * @var bool
     */
    public $fresh = true;
    /**
     * Finalization status, as determined by one of the three finalization
     * constants.
     * @var int
     */
    public $finalized = 0;
    /**
     * The number of positive examples in the training set.
     * @var int
     */
    public $positive = 0;
    /**
     * The number of negative examples in the training set.
     * @var int
     */
    public $negative = 0;
    /**
     * The total number of examples in the training set (sum of positive and
     * negative).
     * @var int
     */
    public $total = 0;
    /**
     * The estimated classification accuracy. This member may be null if the
     * accuracy has not yet been estimated, or out of date if examples have
     * been added to the training set since the last accuracy update, but no
     * new estimate has been computed.
     * @var float
     */
    public $accuracy;
    /*
       The following properties are all serialized, compressed, and stored in
       individual files, then loaded on demand.
    */
    /**
     * The current pool of candidates for labeling. The first element in the
     * buffer is always the active document, and as active documents are
     * labeled and removed, the pool is refreshed with new candidates (if there
     * are more pages to be drawn from the active index). The buffer is
     * represented as an associative array with three fields: 'docs', the
     * candidate page summaries; 'densities', an array of densities computed
     * for the documents in the candidate pool; and 'stats', statistics about
     * the terms and documents in the current pool.
     * @var array
     */
    public $buffer;
    /**
     * The training set, broken up into two fields of an associative array:
     * 'features', an array of document feature vectors; and 'labels', the
     * labels assigned to each document.
     * @var array
     */
    public $docs;
    /**
     * The Features subclass instance used to manage the full set of features
     * seen across all documents in the training set.
     * @var object
     */
    public $full_features;
    /**
     * The Features subclass instance used to manage the reduced set of
     * features used only by Naive Bayes classification algorithms during the
     * labeling phase.
     * @var object
     */
    public $label_features;
    /**
     * The NaiveBayes classification algorithm used during training to
     * tentatively classify documents presented to the user for labeling.
     * @var object
     */
    public $label_algorithm;
    /**
     * The Features subclass instance used to map documents at classification
     * time to the feature vectors expected by classification algorithms. This
     * will generally be a reduced feature set, just like that used during
     * labeling, but potentially larger than the set used by Naive Bayes.
     * @var object
     */
    public $final_features;
    /**
     * The finalized classification algorithm that will be used to classify new
     * web pages. Will usually be logistic regression, but may be Naive Bayes,
     * if set by the options. During labeling, this field is a reference to the
     * Naive Bayes classification algorithm (so that that algorithm will be
     * used by the `classify' method), but it won't be saved to disk as such.
     * @var object
     */
    public $final_algorithm;
    /**
     * The names of properties set by one of the prepareTo* methods; these
     * properties will be saved back to disk during serialization, while all
     * other properties not listed by the __sleep method will be discarded.
     * @var array
     */
    public $loaded_properties = [];
    /* PUBLIC INTERFACE */
    /**
     * Initializes a new classifier with a class label, and options to override
     * the defaults. The timestamp associated with the classifier is taken from
     * the time of construction.
     *
     * @param string $label class label applied to positive instances of the
     * class this classifier is trained to recognize
     * @param array $options optional associative array of options that will
     * override the default options
     */
    public function __construct($label, $options = [])
    {
        $this->class_label = $label;
        $this->timestamp = time();
        $this->options = array_merge($this->options, $options);
    }
    /**
     * Magic method that determines which member data will be stored when
     * serializing this class. Only lightweight summary data are stored with
     * the serialized version of this class. The heavier-weight properties are
     * stored in individual, compressed files.
     *
     * @return array names of properties to store when serializing this
     * instance
     */
    public function __sleep()
    {
        return [
            'options',
            'class_label',
            'timestamp',
            'lang',
            'fresh',
            'finalized',
            'positive',
            'negative',
            'total',
            'accuracy'];
    }
    /* PREPARING FOR A TASK */
    /**
     * Prepare this classifier instance for labeling. This operation requires
     * all of the heavyweight member data save the final features and
     * algorithm. Note that these properties are set to references to the
     * Naive Bayes features and algorithm, so that Naive Bayes will be used to
     * tentatively classify documents during labeling (purely to give the user
     * some feedback on how the training set is performing).
     */
    public function prepareToLabel()
    {
        $this->loadProperties('buffer', 'docs', 'full_features',
            'label_features', 'label_algorithm');
        if (is_null($this->full_features)) {
            $this->full_features = new BinaryFeatures();
        }
        if (is_null($this->label_algorithm)) {
            $this->label_algorithm = new NaiveBayes();
        }
        if (is_null($this->docs)) {
            $this->docs = ['features' => [],'labels' => []];
        }
        $this->final_features = $this->label_features;
        $this->final_algorithm = $this->label_algorithm;
    }
    /**
     * Prepare to train a final classification algorithm on the full training
     * set. This operation requires the full training set and features, but not
     * the candidate buffer used during labeling. Note that any existing final
     * features and classification algorithm are simply zeroed out; they are
     * only loaded from disk so that they will be written back after
     * finalization completes.
     */
    public function prepareToFinalize()
    {
        $this->finalized = self::FINALIZING;
        self::setClassifier($this);
        $this->loadProperties('docs', 'full_features', 'final_features',
            'final_algorithm');
        $this->final_features = null;
        if (strcasecmp($this->options['final_algo'], 'nb') != 0) {
            $this->final_algorithm = new LassoRegression();
        } else {
            $this->final_algorithm = new NaiveBayes();
        }
    }
    /**
     * Prepare to classify new web pages. This operation requires only the
     * final features and classification algorithm, which are expected to be
     * defined after the finalization phase.
     */
    public function prepareToClassify()
    {
        $this->loadProperties('final_features', 'final_algorithm');
    }
    /* LABELING PHASE */
    /**
     * Updates the buffer and training set to reflect the label given to a new
     * document. The label may be -1, 1, or 0, where the first two correspond
     * to a negative or positive example, and the last to a skip. The handling
     * for a skip is necessarily different from that for a positive or negative
     * label, and matters are further complicated by the possibility that we
     * may be changing a label for a document that's already in the training
     * set, rather than adding a new document. This function returns true if
     * the new label resulted in a change to the training set, and false
     * otherwise (i.e., if the user simply skipped labeling the candidate
     * document).
     *
     * When updating an existing document, we will either need to swap the
     * label in the training set and update the statistics stored by the
     * Features instance (since now the features are associated with a
     * different label), or drop the document from the training set and (again)
     * update the statistics stored by the Features instance. In either case
     * the negative and positive counts must be updated as well.
     *
     * When working with a new document, we need to remove it from the
     * candidate buffer, and if the label is non-zero then we also need to add
     * the document to the training set. That involves tokenizing the document,
     * passing the tokens through the full_features instance, and storing the
     * resulting feature vector, plus the new label in the docs attribute. The
     * positive and negative counts must be updated as well.
     *
     * Finally, if this operation is occurring active labeling (when the user
     * is providing labels one at a time), that information needs to be passed
     * along to dropBufferDoc, which can avoid doing some work in the
     * non-active case.
     *
     * @param string $key key used to select the document from the docs array
     * @param int $label new label (-1, 1, or 0)
     * @param bool $is_active whether this operation is being carried out
     * during active labeling
     * @return bool true if the training set was modified, and false otherwise
     */
    public function labelDocument($key, $label, $is_active = true)
    {
        $prev_label = 0;
        $labels_changed = true;
        if (isset($this->docs['labels'][$key])) {
            $prev_label = $this->docs['labels'][$key];
            if ($label != 0) {
                $this->full_features->updateExampleLabel(
                    $this->docs['features'][$key], $prev_label, $label);
                $this->docs['labels'][$key] = $label;
                // Effectively increment new label and decrement old.
                $this->negative += -$label;
                $this->positive -= -$label;
            } else {
                $this->full_features->updateExampleLabel(
                    $this->docs['features'][$key], $prev_label, 0);
                unset($this->docs['features'][$key]);
                unset($this->docs['labels'][$key]);
                if ($prev_label > 0) {
                    $this->positive--;
                } else {
                    $this->negative--;
                }
            }
        } else if ($label == 0) {
            $labels_changed = false;
            $this->dropBufferDoc($is_active);
        } else {
            if ($label > 0) {
                $this->positive++;
            } else {
                $this->negative++;
            }
            $doc = $this->buffer['docs'][0];
            $features = $this->full_features->addExample(
                $doc['TERMS'], $label);
            $this->docs['features'][$key] = $features;
            $this->docs['labels'][$key] = $label;
            $this->dropBufferDoc($is_active);
        }
        $this->total = $this->negative + $this->positive;
        $this->fresh = false;
        if ($labels_changed) {
            $this->finalized = self::UNFINALIZED;
        }
        return $labels_changed;
    }
    /**
     * Iterates entirely through a crawl mix iterator, adding each document
     * (that hasn't already been labeled) to the training set with a single
     * label. This function works by running through the iterator, filling up
     * the candidate buffer with all unlabeled documents, then repeatedly
     * dropping the first buffer document and adding it to the training set.
     * Returns the total number of newly-labeled documents.
     *
     * @param object $mix_iterator crawl mix iterator to draw documents from
     * @param int $label label to apply to every document; -1 or 1, but NOT 0
     * @param int $limit optional upper bound on the number of documents to
     * add; defaults to no limit
     * @return int total number of newly-labeled documents
     */
    public function addAllDocuments($mix_iterator, $label, $limit = INF) {
        $count = $this->initBuffer($mix_iterator, 0);
        while (!$mix_iterator->end_of_iterator && $count < $limit) {
            $new_pages = $mix_iterator->nextPages(500);
            if (isset($new_pages['NO_PROCESS'])) {
                unset($new_pages['NO_PROCESS']);
            }
            $num_pages = 0;
            while ($count + $num_pages < $limit &&
                (list($i, $page) = each($new_pages))) {
                $key = self::makeKey($page);
                if (!isset($this->docs['labels'][$key])) {
                    $this->addBufferDoc($page, false);
                    $num_pages++;
                }
            }
            for ($i = $num_pages; $i > 0; $i--) {
                $key = self::makeKey($this->buffer['docs'][0]);
                $this->labelDocument($key, $label, false);
            }
            $count += $num_pages;
        }
        return $count;
    }
    /**
     * Drops any existing candidate buffer, re-initializes the buffer
     * structure, then calls refreshBuffer to fill it. Takes an optional buffer
     * size, which can be used to limit the buffer to something other than the
     * number imposed by the runtime parameter. Returns the final buffer size.
     *
     * @param object $mix_iterator crawl mix iterator to draw documents from
     * @param int $buffer_size optional buffer size to use; defaults to the
     * runtime parameter
     * @return int final buffer size
     */
    public function initBuffer($mix_iterator, $buffer_size = null)
    {
        $this->buffer = [
            'docs' => [],
            'densities' => [],
            'stats' => [
                'terms' => [],
                'num_tokens' => 0,
                'docs' => [],
                'num_docs' => 0
            ]
        ];
        return $this->refreshBuffer($mix_iterator, $buffer_size);
    }
    /**
     * Adds as many new documents to the candidate buffer as necessary to reach
     * the specified buffer size, which defaults to the runtime parameter.
     * Returns the final buffer size, which may be less than that requested if
     * the iterator doesn't return enough documents.
     *
     * @param object $mix_iterator crawl mix iterator to draw documents from
     * @param int $buffer_size optional buffer size to use; defaults to the
     * runtime parameter
     * @return int final buffer size
     */
    public function refreshBuffer($mix_iterator, $buffer_size = null)
    {
        if (is_null($buffer_size)) {
            $buffer_size = self::BUFFER_SIZE;
        }
        $num_pages = count($this->buffer['docs']);
        while ($num_pages < $buffer_size &&
            !$mix_iterator->end_of_iterator) {
            $batch_size = $buffer_size - $num_pages;
            $new_pages = $mix_iterator->nextPages($batch_size);
            if (isset($new_pages['NO_PROCESS'])) {
                unset($new_pages['NO_PROCESS']);
            }
            foreach ($new_pages as $page) {
                $key = self::makeKey($page);
                if (!isset($this->docs['labels'][$key])) {
                    $this->addBufferDoc($page);
                    $num_pages++;
                }
            }
        }
        return $num_pages;
    }
    /**
     * Computes from scratch the buffer densities of the documents in the
     * current candidate pool. This is an expensive operation that requires
     * the computation of the KL-divergence between each ordered pair of
     * documents in the pool, approximately O(N^2) computations, total (where N
     * is the number of documents in the pool). The densities are saved in the
     * buffer data structure.
     *
     * The density of a document is approximated by its average overlap with
     * every other document in the candidate buffer, where the overlap between
     * two documents is itself approximated using the exponential, negative
     * KL-divergence between them. The KL-divergence is smoothed to deal with
     * features (terms) that occur in one distribution (document) but not the
     * other, and then multiplied by a negative constant and exponentiated in
     * order to convert it to a kind of linear overlap score.
     */
    public function computeBufferDensities()
    {
        $this->buffer['densities'] = [];
        $densities =& $this->buffer['densities'];
        $stats =& $this->buffer['stats'];
        $num_docs = $this->buffer['stats']['num_docs'];
        foreach ($stats['docs'] as $i => $doc_i) {
            $sum_i = 0.0;
            foreach ($stats['docs'] as $h => $doc_h) {
                if ($h == $i) {
                    continue;
                }
                $sum_ih = 0.0;
                foreach ($doc_h as $t => $doc_h_t) {
                    $p = $doc_h_t;
                    $q = self::DENSITY_LAMBDA *
                        (isset($doc_i[$t]) ? $doc_i[$t] : 0.0) +
                        (1.0 - self::DENSITY_LAMBDA) *
                        $stats['terms'][$t] / $stats['num_tokens'];
                    $sum_ih += $p * log($p / $q);
                }
                $sum_i += -self::DENSITY_BETA * $sum_ih;
            }
            $densities[] = exp($sum_i / $stats['num_docs']);
        }
    }
    /**
     * Finds the next best document for labeling amongst the documents in the
     * candidate buffer, moves that candidate to the front of the buffer, and
     * returns it.  The best candidate is the one with the maximum product of
     * disagreement and density, where the density has already been calculated
     * for each document in the current pool, and the disagreement is the
     * KL-divergence between the classification scores obtained from a
     * committee of Naive Bayes classifiers, each sampled from the current
     * set of features.
     *
     * @return array two-element array containing first the best candidate, and
     * second the disagreement score, obtained by dividing the disagreement
     * for the document by the maximum disagreement possible for the committee
     * size
     */
    public function findNextDocumentToLabel()
    {
        if (empty($this->buffer['docs'])) {
            return [null, 0.0];
        } else if ($this->fresh) {
            return [$this->buffer['docs'][0], 0.0];
        }
        $num_documents = count($this->buffer['docs']);
        $doc_ps = array_fill(0, $num_documents, []);
        for ($k = 0; $k < self::COMMITTEE_SIZE; $k++) {
            $m = new NaiveBayes();
            $m->sampleBeta($this->label_features);
            foreach ($this->buffer['docs'] as $i => $page) {
                $x = $this->label_features->mapDocument($page['TERMS']);
                $doc_ps[$i][$k] = $m->classify($x);
            }
        }
        $max_disagreement = -INF;
        $max_score = -INF;
        $best_i = 0;
        $densities =& $this->buffer['densities'];
        foreach ($doc_ps as $i => $ps) {
            $kld = 1.0 + self::klDivergenceToMean($ps);
            $score = $kld * $densities[$i];
            if ($score > $max_score) {
                $max_disagreement = $kld;
                $max_score = $score;
                $best_i = $i;
            }
        }
        $doc = $this->buffer['docs'][$best_i];
        $this->moveBufferDocToFront($best_i);
        return [$doc, $max_disagreement / self::MAX_DISAGREEMENT];
    }
    /**
     * Trains the Naive Bayes classification algorithm used during labeling on
     * the current training set, and optionally updates the estimated accuracy.
     *
     * @param bool $update_accuracy optional parameter specifying whether or not
     *      to update the accuracy estimate after training completes; defaults
     *      to false
     */
    public function train($update_accuracy = false)
    {
        $this->label_features = $this->full_features->restrict(
            new ChiSquaredFeatureSelection($this->options['label_fs']));
        $this->final_features = $this->label_features;
        $X = $this->label_features->mapTrainingSet($this->docs['features']);
        $y = array_values($this->docs['labels']);
        $this->label_algorithm->train($X, $y);
        if ($update_accuracy) {
            $this->updateAccuracy($X, $y);
        }
    }
    /**
     * Estimates current classification accuracy using a Naive Bayes
     * classification algorithm. Accuracy is estimated by splitting the current
     * training set into fifths, reserving four fifths for training, and the
     * remaining fifth for testing. A fresh classifier is trained and tested
     * on these splits, and the total accuracy recorded. Then the splits are
     * rotated so that the previous testing fifth becomes part of the training
     * set, and one of the blocks from the previous training set becomes the
     * testing set. A new classifier is trained and tested on the new splits,
     * and, again, the accuracy recorded. This process is repeated until all
     * blocks have been used for testing, and the average accuracy recorded.
     *
     * @param object $X optional sparse matrix representing the already-mapped
     * training set to use; if not provided, the current training set is
     * mapped using the label_features property
     * @param array $y optional array of document labels corresponding to the
     * training set; if not provided the current training set labels are used
     */
    public function updateAccuracy($X = null, $y = null)
    {
        if (is_null($X)) {
            $X = $this->label_features->mapTrainingSet(
                $this->docs['features']);
        }
        // Round $m down to nearest multiple of 10, and limit to 250 examples.
        $m = min(250, intval(floor($X->rows() / 10)) * 10);
        if ($m < 10) {
            return;
        }
        if (is_null($y)) {
            $y = array_values($this->docs['labels']);
        }
        $indices = array_rand($y, $m);
        shuffle($indices);
        $fold_size = $m / 5;
        $divide = 4 * $fold_size;
        $sum = 0.0;
        for ($i = 0; $i < 5; $i++) {
            if ($i > 0) {
                $last_block = array_splice($indices, $divide);
                array_splice($indices, 0, 0, $last_block);
            }
            $train_indices = array_slice($indices, 0, $divide);
            sort($train_indices);
            $test_indices = array_slice($indices, $divide);
            sort($test_indices);
            list($train_X, $test_X) = $X->partition(
                $train_indices, $test_indices);
            $train_y = [];
            foreach ($train_indices as $ii) {
                $train_y[] = $y[$ii];
            }
            $test_y = [];
            foreach ($test_indices as $ii) {
                $test_y[] = $y[$ii];
            }
            $nb = new NaiveBayes();
            $nb->train($train_X, $train_y);
            $correct = 0;
            foreach ($test_X as $ii => $x) {
                $label = $nb->classify($x) >= 0.5 ? 1 : -1;
                if ($label == $test_y[$ii]) {
                    $correct++;
                }
            }
            $sum += $correct / count($test_y);
        }
        $this->accuracy = $sum / 5;
    }
    /* FINALIZATION PHASE */
    /**
     * Trains the final classification algorithm on the full training set,
     * using a subset of the full feature set. The final algorithm will usually
     * be logistic regression, but can be set to Naive Bayes with the
     * appropriate runtime option. Once finalization completes, updates the
     * `finalized' attribute.
     */
    public function finalize()
    {
        $this->final_features = $this->full_features->restrict(
            new ChiSquaredFeatureSelection($this->options['final_fs']));
        $X = $this->final_features->mapTrainingSet($this->docs['features']);
        $y = array_values($this->docs['labels']);
        $this->final_algorithm->train($X, $y);
        $this->finalized = self::FINALIZED;
    }
    /* CLASSIFICATION PHASE */
    /**
     * Classifies a page summary using the current final classification
     * algorithm and features, and returns the classification score. This
     * method is also used during the labeling phase to provide a tentative
     * label for candidates, and in this case the final algorithm is actually a
     * reference to a Naive Bayes instance and final_features is a reference to
     * label_features; neither of these gets saved to disk, however.
     *
     * @param array $page page summary array for the page to be classified
     * @return float pseudo-probability that the page is a positive instance of
     * the target class
     */
    public function classify($page)
    {
        /*
           Without any features (i.e., no training) there's no support for
           either label, so we assume that the score is close to neutral, but
           just beneath the threshold.
        */
        if ($this->fresh) {
            return max(self::THRESHOLD - 1.0E-8, 0.0);
        }
        $doc = $this->tokenizeDescription($page[self::DESCRIPTION]);
        $x = $this->final_features->mapDocument($doc);
        return $this->final_algorithm->classify($x);
    }
    /* PRIVATE INTERFACE */
    /**
     * Adds a page to the end of the candidate buffer, keeping the associated
     * statistics up to date. During active training, each document in the
     * buffer is tokenized, and the terms weighted by frequency; the term
     * frequencies across documents in the buffer are tracked as well. With no
     * active training, the buffer is simply an array of page summaries.
     *
     * @param array $page page summary for the document to add to the buffer
     * @param bool $is_active whether this operation is part of active
     * training, in which case some extra statistics must be maintained
     */
    public function addBufferDoc($page, $is_active = true)
    {
        $page['TERMS'] = $this->tokenizeDescription($page[self::DESCRIPTION]);
        $this->buffer['docs'][] = $page;
        if ($is_active) {
            $doc = [];
            $doc_length = 0;
            foreach ($page['TERMS'] as $term => $count) {
                $doc[$term] = $count;
                $doc_length += $count;
                if (!isset($this->buffer['stats']['terms'][$term])) {
                    $this->buffer['stats']['terms'][$term] = $count;
                } else {
                    $this->buffer['stats']['terms'][$term] += $count;
                }
                $this->buffer['stats']['num_tokens'] += $count;
            }
            foreach ($doc as &$term_count) {
                $term_count /= $doc_length;
            }
            $this->buffer['stats']['docs'][] = $doc;
            $this->buffer['stats']['num_docs']++;
        }
    }
    /**
     * Removes the document at the front of the candidate buffer. During active
     * training the cross-document statistics for terms occurring in the
     * document being removed are maintained.
     *
     * @param bool $is_active whether this operation is part of active
     * training, in which case some extra statistics must be maintained
     */
    public function dropBufferDoc($is_active = true)
    {
        $page = array_shift($this->buffer['docs']);
        if ($is_active) {
            foreach ($page['TERMS'] as $term => $count) {
                $this->buffer['stats']['terms'][$term] -= $count;
                $this->buffer['stats']['num_tokens'] -= $count;
            }
            array_shift($this->buffer['stats']['docs']);
            $this->buffer['stats']['num_docs']--;
        }
    }
    /**
     * Moves a document in the candidate buffer up to the front, in preparation
     * for a label request. The document is specified by its index in the
     * buffer.
     *
     * @param int $i document index within the candidate buffer
     */
    public function moveBufferDocToFront($i)
    {
        list($doc) = array_splice($this->buffer['docs'], $i, 1);
        array_unshift($this->buffer['docs'], $doc);
        list($doc) = array_splice($this->buffer['stats']['docs'], $i, 1);
        array_unshift($this->buffer['stats']['docs'], $doc);
    }
    /**
     * Tokenizes a string into a map from terms to within-string frequencies.
     *
     * @param string $description string to tokenize
     * @return array associative array mapping terms to their within-string
     * frequencies
     */
    public function tokenizeDescription($description)
    {
        /*
           For now, adopt a very simple tokenizing strategy because
           extractPhrasesInLists is very slow.
         */
        $tokens = preg_split('/\s+/', $description);
        $out = [];
        foreach ($tokens as $token) {
            if (!$token)
                continue;
            if (!isset($out[$token])) {
                $out[$token] = 1;
            } else {
                $out[$token]++;
            }
        }
        return $out;
    }
    /**
     * Loads class attributes from compressed, serialized files on disk, and
     * stores their names so that they will be saved back to disk later. Each
     * property (if it has been previously set) is stored in its own file under
     * the classifier's data directory, named after the property. The file is
     * compressed using gzip, but without gzip headers, so it can't actually be
     * decompressed by the standard gzip utility. If a file doesn't exist, then
     * the instance property is left untouched. The property names are passed
     * as a variable number of arguments.
     *
     * @param string $property_name,... variably-sized list of property names
     * to try to load data for
     */
    public function loadProperties(/* args... */)
    {
        $properties = func_get_args();
        foreach ($properties as $property_name) {
            $this->$property_name = null;
            $filename = C\WORK_DIRECTORY."/classifiers/".$this->class_label.
                "/".$property_name.".txt";
            if (file_exists($filename)) {
                $serialized_data = gzuncompress(file_get_contents($filename));
                $data = unserialize($serialized_data);
                $this->$property_name = $data;
            }
        }
        $this->loaded_properties = $properties;
    }
    /**
     * Stores the data associated with each property name listed in the
     * loaded_properties instance attribute back to disk. The data for each
     * property is stored in its own serialized and compressed file, and made
     * world-writable.
     */
    public function storeLoadedProperties()
    {
        $properties = $this->loaded_properties;
        foreach ($properties as $property_name) {
            $filename = C\WORK_DIRECTORY."/classifiers/".$this->class_label .
                "/".$property_name.".txt";
            $serialized_data = serialize($this->$property_name);
            file_put_contents($filename, gzcompress($serialized_data));
            chmod($filename, 0777);
        }
    }
    /* PUBLIC STATIC INTERFACE */
    /**
     * Given a page summary (passed by reference) and a list of classifiers,
     * augments the summary meta words with the class label of each classifier
     * that scores the summary above a threshold. This static method is used by
     * fetchers to classify downloaded pages. In addition to the class label,
     * the pseudo-probability that the document belongs to the class is
     * recorded as well. This is recorded both as the score rounded down to the
     * nearest multiple of ten, and as "<n>plus" for each multiple of ten, n,
     * less than the score and greater than or equal to the threshold.
     *
     * As an example, suppose that a classifier with class label `label' has
     * determined that a document is a positive example with pseudo-probability
     * 0.87 and threshold 0.5. The following meta words are added to the
     * summary: class:label, class:label:80, class:label:80plus,
     * class:label:70plus, class:label:60plus, and class:label:50plus.
     *
     * @param array $summary page summary to classify, passed by reference
     * @param array $classifiers list of Classifier instances, each prepared
     * for classifying (via the prepareToClassify method)
     * @param array& $active_classifiers
     * @param array& $active_rankers
     */
    public static function labelPage(&$summary, $classifiers,
        &$active_classifiers, &$active_rankers)
    {
        foreach ($classifiers as $classifier) {
            $score = $classifier->classify($summary);
            $label = $classifier->class_label;
            if (in_array($label, $active_classifiers)
                && $score >= self::THRESHOLD) {
                if (!isset($summary[self::META_WORDS])) {
                    $summary[self::META_WORDS] = [];
                }
                $truncated_score = intval(floor(($score * 100) / 10) * 10);
                $label_score = sprintf("%d",
                    floor($truncated_score / 10) * 1000);
                $summary[self::META_WORDS][] = "class:{$label}";
                $summary[self::META_WORDS][] = "class:{$label}:{$label_score}";
                $min_score = intval(self::THRESHOLD * 100);
                for ($s = $truncated_score; $s >= $min_score; $s -= 10) {
                    $summary[self::META_WORDS][] = "class:{$label}:{$s}plus";
                }
            }
            if (in_array($label, $active_rankers)) {
                //scores for rankings are four bytes
                $summary[self::USER_RANKS][$label] =
                    intval(floor($score * 65536));
            }
        }
    }
    /**
     * Returns an array of classifier instances currently stored in the
     * classifiers directory. The array maps class labels to their
     * corresponding classifiers, and each classifier is a minimal instance,
     * containing only summary statistics.
     *
     * @return array associative array of class labels mapped to their
     *      corresponding classifier instances
     */
    public static function getClassifierList()
    {
        $classifiers = [];
        $dirname = C\WORK_DIRECTORY."/classifiers";
        foreach (glob($dirname."/*", GLOB_ONLYDIR) as $classifier_dir) {
            $classifier_file = $classifier_dir."/classifier.txt";
            if (file_exists($classifier_file) ) {
                $obj_string = file_get_contents($classifier_file);
                /*  code to handle the fact that name space of object may not
                    be the modern namespace name
                 */
                $serialized_data =
                    L\renameSerializedObject(get_called_class(), $obj_string);
                $classifier = unserialize($serialized_data);
                $classifiers[$classifier->class_label] = $classifier;
            }
        }
        return $classifiers;
    }
    /**
     * Returns the minimal classifier instance corresponding to a class label,
     * or null if no such classifier exists on disk.
     *
     * @param string $label classifier's class label
     * @return object classifier instance with the relevant class label, or
     *      null if no such classifier exists on disk
     */
    public static function getClassifier($label)
    {
        $filename = C\WORK_DIRECTORY."/classifiers/{$label}/classifier.txt";
        if (file_exists($filename)) {
            $serialized_data = file_get_contents($filename);
            /*  code to handle the fact that name space of object may not
                be the modern namespace name
             */
            $serialized_data =
                L\renameSerializedObject(get_called_class(), $serialized_data);
            $classifier = unserialize($serialized_data);
            return unserialize($serialized_data);
        }
        return null;
    }
    /**
     * Given a list of class labels, returns an array mapping each class label
     * to an array of data necessary for initializing a classifier for that
     * label. This static method is used to prepare a collection of classifiers
     * for distribution to fetchers, so that each fetcher can classify pages as
     * it downloads them. The only extra properties passed along in addition to
     * the base classification data are the final features and final algorithm,
     * both necessary for classifying new documents.
     *
     * @param array $labels flat array of class labels for which to load data
     * @return array associative array mapping class labels to arrays of data
     * necessary for initializing the associated classifier
     */
    public static function loadClassifiersData($labels)
    {
        $fields = ['classifier', 'final_features', 'final_algorithm'];
        $classifiers_data = [];
        foreach ($labels as $label) {
            $basedir = C\WORK_DIRECTORY."/classifiers/{$label}";
            $classifier_data = [];
            foreach ($fields as $field) {
                $filename = "{$basedir}/{$field}.txt";
                if (file_exists($filename)) {
                    /*
                       The data is web-encoded because it will be sent in an
                       HTTP response to each fetcher as it prepares for a new
                       crawl.
                     */
                    $classifier_data[$field] = L\webencode(
                        file_get_contents($filename));
                } else {
                    $classifier_data = false;
                    break;
                }
            }
            $classifiers_data[$label] = $classifier_data;
        }
        return $classifiers_data;
    }
    /**
     * The dual of loadClassifiersData, this static method reconstitutes a
     * Classifier instance from an array containing the necessary data. This
     * gets called by each fetcher, using the data that it receives from the
     * name server when establishing a new crawl.
     *
     * @param array $data associative array mapping property names to their
     * serialized and compressed data
     * @return object Classifier instance built from the passed-in data
     */
    public static function newClassifierFromData($data)
    {
        if (!isset($data['classifier'])) {
            return null;
        }
        $classifier = unserialize(L\webdecode($data['classifier']));
        unset($data['classifier']);
        foreach ($data as $field => $field_data) {
            $field_data = L\webdecode($field_data);
            $serialized_data = gzuncompress($field_data);
            $classifier->$field = unserialize($serialized_data);
        }
        $classifier->loaded_properties = array_keys($data);
        return $classifier;
    }
    /**
     * Stores a classifier instance to disk, first separating it out into
     * individual files containing serialized and compressed property data. The
     * basic classifier information, such as class label and summary
     * statistics, is stored uncompressed in a file called `classifier.txt'.
     * The classifier directory and all of its contents are made world-writable
     * so that they can be manipulated without hassle from the command line.
     *
     * @param object $classifier Classifier instance to store to disk
     */
    public static function setClassifier($classifier)
    {
        $dirname = C\WORK_DIRECTORY."/classifiers/".$classifier->class_label;
        if (!file_exists($dirname)) {
            mkdir($dirname);
            chmod($dirname, 0777);
        }
        $classifier->storeLoadedProperties();
        $label = $classifier->class_label;
        $filename = $dirname."/classifier.txt";
        $serialized_data = serialize($classifier);
        file_put_contents($filename, $serialized_data);
        chmod($filename, 0777);
    }
    /**
     * Deletes the directory corresponding to a class label, and all of its
     * contents. In the case that there is no classifier with the passed in
     * label, does nothing.
     *
     * @param string $label class label of the classifier to be deleted
     */
    public static function deleteClassifier($label)
    {
        $dirname = C\WORK_DIRECTORY."/classifiers/{$label}";
        if (file_exists($dirname)) {
            $db_class = C\NS_DATASOURCES . ucfirst(C\DBMS)."Manager";
            $db = new $db_class();
            $db->unlinkRecursive($dirname);
        }
    }
    /**
     * Removes all but alphanumeric characters and underscores from a label, so
     * that it may be easily saved to disk and used in queries as a meta word.
     *
     * @param string $label class label to clean
     */
    public static function cleanLabel($label)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $label);
    }
    /**
     * Returns a name for the crawl mix associated with a class label.
     *
     * @param string $label class label associated with the crawl mix
     * @return string name that can be used for the crawl mix associated with
     * $label
     */
    public static function getCrawlMixName($label)
    {
        return 'CLASSIFY_'.$label;
    }
    /**
     * Returns a key that can be used internally to refer internally to a
     * particular page summary.
     *
     * @param array $page page summary to return a key for
     * @return string key that uniquely identifies the page summary
     */
    public static function makeKey($page)
    {
        return md5($page[self::URL]);
    }
    /* PRIVATE STATIC INTERFACE */
    /**
     * Calculates the KL-divergence to the mean for a collection of discrete
     * two-element probability distributions. Each distribution is specified by
     * a single probability, p, since the second probability is just 1 - p. The
     * KL-divergence to the mean is used as a measure of disagreement between
     * members of a committee of classifiers, where each member assigns a
     * classification score to the same document.
     *
     * @param array $ps probabilities describing several discrete two-element
     * probability distributions
     * @return float KL-divergence to the mean for the collection of
     * distributions
     */
    public static function klDivergenceToMean($ps)
    {
        $k = count($ps);
        $mean = array_sum($ps) / $k;
        $mean = max(min($mean, 1.0 - 1.0E-8), 1.0E-8);
        $kld = 0.0;
        foreach ($ps as $p) {
            $p = max(min($p, 1.0 - 1.0E-8), 1.0E-8);
            $kld += $p * log($p / $mean);
            $kld += (1 - $p) * log((1 - $p) / (1 - $mean));
        }
        return $kld / $k;
    }
}
