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
 * @copyright 2009 - 2015s
 * @filesource
 */
/**
 * Implements the client interface for finding and labeling documents.
 *
 * Classifier behaves like a static class with some private variables and
 * functions. The setup work is all done in the intitialize method, and after
 * that all work is done in response to timeouts or user actions, such as
 * button clicks.
 *
 * @author Shawn Tice
 */
var Classifier = (function() {
    /*
     * Maximum size of the document candidate pool. This constant is used to
     * decide when to display, e.g., 50+ instead of just 50.
     * @var int
     */
    var MAX_UNLABELLED_BUFFER_SIZE = 51;
    /*
     * The maximum number of previously-labeled document records to display.
     * @var int
     */
    var MAX_LABELLED = 20;
    /*
     * How long to wait before adding another '.' to the end of a loading
     * message. The advantage of choosing 333 is that the time to display three
     * periods is roughly one second.
     * @var int
     */
    var LOADING_REDRAW = 333;
    // We return this at the bottom, so this is Classifier's public interface.
    var self = {};
    /*
     * Gathers references to all relevant DOM elements, initializes state, and
     * adds event handlers. Because AJAX requests to the administrative areas
     * of Yioop must be authenticated, this method expects to be called with
     * its first authentication token; each request will then yield a new token
     * good for one more request.
     *
     * @param string classLabel label for the classifier being trained
     * @param string authSession authentication token good for one request
     * @param int authTime timestamp associated with the auth token
     */
    self.initialize = function(classLabel, authSession, authTime)
    {
        self.classLabel = classLabel;
        self.authTime = authTime;
        self.authSession = authSession;

        self.elt = {
            'positive_count': elt('positive-count'),
            'negative_count': elt('negative-count'),
            'accuracy': elt('accuracy'),
            'update_accuracy': elt('update-accuracy'),
            'label_docs_form': elt('label-docs-form'),
            'label_docs_source': elt('label-docs-source'),
            'label_docs_type': elt('label-docs-type'),
            'label_docs_keywords': elt('label-docs-keywords'),
            'label_docs_status': elt('label-docs-status'),
            'label_docs_queue': null,
        };

        self.docCounter = 1;
        self.documents = {};
        self.activeDocument = null;
        self.labelledDocQueue = [];
        self.lastSource = null;
        self.lastSourceType = null;
        self.lastKeywords = null;
        self.lastStatus = '';
        self.loadingTimer = null;

        self.elt.label_docs_form.onsubmit = function() {
            self.requestDocuments();
            return false;
        }

        self.elt.update_accuracy.onclick = function() {
            if (!hasClass('disabled', self.elt.update_accuracy)) {
                self.requestAccuracyUpdate();
            }
            return false;
        }
    };
    /*
     * Event handler called when the user clicks on any of the "In class", "Not
     * in class", and "Skip" links associated with a document. This method
     * updates the display and sends a request to the server to inform it of
     * the user's decision and get the next document to be labeled.
     *
     * @param int docid key for the associated document
     * @param string action 'inclass', 'notinclass', or 'skip'
     */
    self.handleAction = function(docid, action)
    {
        var doc = self.documents[docid];
        var label;
        switch (action) {
            case 'inclass':
                label = 1;
                break;
            case 'notinclass':
                label = -1;
                break;
            case 'skip':
                label = 0;
                break;
        }
        // Only send a request if something has changed.
        if (doc.label === undefined || doc.label != label) {
            self.sendNewLabel(doc, label);
        }
        // Update the class for benefit of the CSS
        doc.element.className = 'labelled ' + action;
        doc.label = label;
        /*
           If the labelled (or skipped) document was the active document, then
           push it down on the labeled queue, shifting off the oldest document
           if the queue is full.
         */
        if (doc == self.activeDocument) {
            self.activeDocument = null;
            if (self.labelledDocQueue.length == MAX_LABELLED) {
                var droppedDoc = self.labelledDocQueue.shift();
                droppedDoc.element.parentNode.removeChild(droppedDoc.element);
            }
            self.labelledDocQueue.push(doc);
        }

        return false;
    }
    /* PRIVATE INTERFACE */
    /*
     * Sends a request to load up a new candidate pool based on the selected
     * index, index action, and optional query. The response behavior differs
     * according to whether the index action specifies marking all candidates
     * as positive or negative examples, or manual labeling. In the latter case
     * the number of candidate documents (up to MAX_UNLABELLED_BUFFER_SIZE) is
     * displayed, while in the former case the number of documents added to the
     * pool is displayed.
     */
    self.requestDocuments = function()
    {
        self.lastSource = self.elt.label_docs_source.value;
        self.lastSourceType = self.elt.label_docs_type.value;
        self.lastKeywords = self.elt.label_docs_keywords.value;

        var loading = loadingText(self.elt.label_docs_status,
            tl['crawl_component_loading']);

        sendRequest({
            'url': '?c=classifier&a=classify&arg=getdocs',
            'postdata': {
                'session': self.authSession,
                'time': self.authTime,
                'label': self.classLabel,
                'index': self.lastSource,
                'type': self.lastSourceType,
                'keywords': self.lastKeywords
            },
            'onSuccess': function(response) {
                loading.clear();
                self.authSession = response.authSession;
                self.authTime = response.authTime;
                self.clearActiveDocument();
                if (response.new_doc) {
                    self.setActiveDocument(response.new_doc);
                }
                if (response.add_count) {
                    // Only present when mass-labeling.
                    msg = format(tl['crawl_component_added_examples'],
                        response.add_count, self.lastSourceType);
                    self.setStatus(msg);
                    self.drawStatistics(response);
                } else {
                    self.drawDocumentCount(response.num_docs);
                }
            },
            'onFailure': function() {
                loading.clear();
                self.setStatus(tl['crawl_component_load_failed']);
            }
        });
    }
    /*
     * Encodes any labels stored in the labels var as POST data, and sends a
     * request to add these labels (using the document url as a key) to
     * the classifier controller on the server. This method is called by the
     * handleAction method in order to actually send the new label (or skip) to
     * the server.
     *
     * @param object doc document to send a label for
     * @param int label user-assigned label
     */
    self.sendNewLabel = function(doc, label)
    {
        var loading = loadingText(self.elt.label_docs_status,
            tl['crawl_component_loading']);
        sendRequest({
            'url': '?c=classifier&a=classify&arg=addlabel',
            'postdata': {
                'session': self.authSession,
                'time': self.authTime,
                'label': self.classLabel,
                'index': self.lastSource,
                'type': self.lastSourceType,
                'keywords': self.lastKeywords,
                'doc_to_label': {
                    'docid': doc.id,
                    'key': doc.key,
                    'label': label
                }
            },
            'onSuccess': function(response) {
                loading.clear();
                self.authSession = response.authSession;
                self.authTime = response.authTime;
                if (response.new_doc) {
                    /*
                       There may still be an active document in the case that
                       we were re-labelling an old document, but now we want to
                       replace it.
                     */
                    self.clearActiveDocument();
                    self.setActiveDocument(response.new_doc);
                }
                self.drawStatistics(response);
                self.drawDocumentCount(response.num_docs);
            },
            'onFailure': function() {
                loading.clear();
                self.setStatus(tl['crawl_component_label_update_failed']);
            }
        });

    }

    /*
     * Sends a request to the server to initiate an accuracy update, and on
     * response updates the statistics (which includes reporting the current
     * accuracy estimate, if any). Normally, the accuracy is only estimated
     * each time a set number of documents have been added to the training set.
     * The update accuracy functionality lets the user request an update
     * without having to actually add more documents.
     */
    self.requestAccuracyUpdate = function()
    {
        var updating = tl['crawl_component_updating'];
        var loading = loadingText(self.elt.update_accuracy, updating, {
            'dots': false,
            'className': 'disabled'
        });
        sendRequest({
            'url': '?c=classifier&a=classify&arg=updateaccuracy',
            'postdata': {
                'session': self.authSession,
                'time': self.authTime,
                'label': self.classLabel,
                'index': self.lastSource,
                'type': self.lastSourceType,
                'keywords': self.lastKeywords,
            },
            'onSuccess': function(response) {
                self.authSession = response.authSession;
                self.authTime = response.authTime;
                self.drawStatistics(response);
                loading.clear();
            },
            'onFailure': function() {
                loading.clear();
                self.setStatus(tl['crawl_component_acc_update_failed']);
            }
        });
    }

    /**
     * Builds and displays a new active document record for the document data
     * received from the server. This method both registers the document data
     * in internal data structures, and creates the DOM structure to display
     * the document to the user. If this is the very first document to be
     * labeled since page load, then the table that holds documents is created
     * before the new document is inserted into the DOM.
     *
     * @param object doc data structure representing the new active document
     */
    self.setActiveDocument = function(doc) {
        doc.id = self.docCounter++;
        self.documents[doc.id] = doc;
        self.activeDocument = doc;

        // Create table if it doesn't yet exist.
        if (!self.elt.label_docs_queue) {
            var queue = document.createElement('table');
            queue.id = 'label-docs-queue';
            self.elt.label_docs_form.parentNode.insertBefore(
                    queue, self.elt.label_docs_form.nextElementSibling);
            self.elt.label_docs_queue = queue;
        }

        var newRow = self.buildDocumentRow(doc);
        doc.element = newRow;

        var topDoc = self.elt.label_docs_queue.firstChild;
        if (topDoc) {
            self.elt.label_docs_queue.insertBefore(newRow, topDoc);
        } else {
            self.elt.label_docs_queue.appendChild(newRow);
        }
    }
    /*
     * Removes the active document from the DOM and from the internal set of
     * documents completely. This is done when abandoning the current candidate
     * pool for another, and is NOT the same as skipping the active document.
     */
    self.clearActiveDocument = function()
    {
        if (self.activeDocument) {
            var topDoc = self.activeDocument.element;
            self.elt.label_docs_queue.removeChild(topDoc);
            delete self.documents[self.activeDocument.id];
        }
        self.activeDocument = null;
    }
    /*
     * Updates the display of the counts of positive and negative examples and
     * the estimated accuracy.  Each time the server responds to a request, it
     * passes along the classifier's current counts and accuracy estimate to
     * keep the client presentation of these statistics in sync.
     *
     * @param object response data from the last server request
     */
    self.drawStatistics = function(response)
    {
        self.elt.positive_count.innerHTML = response.positive;
        self.elt.negative_count.innerHTML = response.negative;
        if (response.accuracy === null) {
            self.elt.accuracy.innerHTML = tl['crawl_component_na'];
        } else {
            self.elt.accuracy.innerHTML = format('{1}%',
                (response.accuracy * 100).toFixed(1));
        }
    }
    /*
     * Updates the display of the number of documents currently in the
     * candidate pool. Since candidates are being iterated over on the server
     * rather than loaded in all at once, it is unknown exactly how many there
     * are until the pool has been exhausted. To reflect this situation, when
     * there are more candidates than will fit in the current pool, a plus sign
     * is appended to the current count.
     *
     * @param int num_docs number of documents in the server's candidate pool
     */
    self.drawDocumentCount = function(num_docs)
    {
        var msg;
        if (!num_docs) {
            msg = tl['crawl_component_no_docs'];
        } else {
            var count, plus;
            if (num_docs == MAX_UNLABELLED_BUFFER_SIZE) {
                count = MAX_UNLABELLED_BUFFER_SIZE - 1;
                plus = '+';
            } else {
                count = num_docs;
                plus = '';
            }
            msg = format(tl['crawl_component_num_docs'], count, plus);
        }
        self.setStatus(msg);
    }
    /*
     * A shortcut for setting the HTML of the element that displays document
     * counts.
     */
    self.setStatus = function(msg)
    {
        self.elt.label_docs_status.innerHTML = msg;
    }
    /*
     * Builds the DOM element representing a document. Each document is
     * represented by a row in a table, where the row has two cells, the first
     * dedicated to action links (e.g., for marking a document as a positive
     * example) and the second to summarizing the document.
     *
     * @param object doc data structure representing the new document
     * @return object table row DOM element representing the document
     */
    self.buildDocumentRow = function(doc)
    {
        var tr = document.createElement('tr');
        tr.id = 'doc-' + doc.id;
        tr.innerHTML =
            tags('td', {'class': 'actions'},
                self.buildActionLinkHTML(tl['crawl_component_in_class'],
                    'inclass', doc),
                self.buildActionLinkHTML(tl['crawl_component_not_in_class'],
                    'notinclass', doc),
                self.buildActionLinkHTML(tl['crawl_component_skip'],
                    'skip', doc)
            ) +
            tags('td', {'class': 'info'},
                tags('p', {'class': 'page-link'},
                    tags('a', {'href': doc.cache_link}, doc.title)),
                tags('p', {'class': 'echo-link'}, doc.url),
                tags('p', {'class': 'prediction'},
                    self.buildPredictionHTML(doc)),
                doc.description && doc.description.length > 0 ?
                    tags('p', {'class': 'description'}, doc.description) :
                    ''
            );
        return tr;
    }

    /*
     * Builds an anchor element used to allow a user to mark a document as a
     * positive or negative example, or to skip it. The anchor has an onclick
     * attribute that calls the handleAction method with the document id and
     * action.
     *
     * @param string label anchor text displayed to the user
     * @param string action action associated with this anchor
     * @param object doc data structure representing the document the action
     * should be applied to
     * @return object paragraph DOM element wrapping the created anchor
     */
    self.buildActionLinkHTML = function(label, action, doc)
    {
        var onclick = 'return Classifier.handleAction(' + doc.id +
            ",'" + action + "')";
        var link = tags('a', {
            'class': action,
            'href': '#' + action,
            'onclick': onclick
        }, label);
        return tags('p', {}, '[', link, ']');
    }
    /*
     * Builds an HTML string that displays the classification confidence and
     * disagreement score associated with a document, using data sent from the
     * server.
     *
     * @param object doc data structure representing the document
     * @return string HTML string to be used to display confidence and
     * disagreement
     */
    self.buildPredictionHTML = function(doc)
    {
        label = (doc.positive ? '' : 'not ') + self.classLabel;
        var prediction = format(tl['crawl_component_prediction'], label);
        var scores = format(tl['crawl_component_scores'],
            (doc.confidence * 100).toFixed(1),
            (doc.disagreement * 100).toFixed(1));
        return format('<b>{1}</b> ({2})', prediction, scores);
    }
    /* UTILITY FUNCTIONS */
    /*
     * Builds a string containing a pair of HTML tags with optional attributes
     * and nested elements. All arguments but the tag name are optional, but if
     * nested elements are to be supplied, then attributes for the opening tag
     * must be supplied as well, even if they're empty. Attributes are
     * specified as an object where the keys are attribute names and their
     * values are strings. Each nested element may be either an HTML string or
     * an array of HTML strings, all of which will be concatenated together.
     * This function creates ONLY closed HTML tags (e.g., <td>...</td>, and not
     * <img.../>); the tag function should be used to create self-closing HTML
     * tags.
     *
     * @param string tagname opening and closing tag name
     * @param object attributes optional object for which the keys are
     * attribute names, and the values are attribute values (may be empty)
     * @param string|array nested... optional sequence of HTML strings or
     * arrays of HTML strings to be nested within the opening and closing tags
     * @return string HTML string for the described element
     */
    function tags(tagname, attributes /* ... */)
    {
        var element = [makeOpenTag(tagname, attributes, '>')];
        for (var i = 2; i < arguments.length; i++) {
            var type = typeof(arguments[i]);
            switch (type) {
                case 'object':
                    element = element.concat(arguments[i]);
                    break;
                case 'string':
                    if (arguments[i].length > 0)
                        element.push(arguments[i]);
                    break;
            }
        }
        element.push('</' + tagname + '>');
        return element.join('');
    }
    /*
     * This function is just like the tags function, but creates a self-closing
     * tag (e.g., <img.../>), which by necessity cannot contain nested
     * elements.
     *
     * @param string tagname opening tag name
     * @param object attributes optional object for which the keys are
     * attribute names, and the values are attribute values (may be empty)
     * @return string HTML string for the described element
     */
    function tag(tagname, attributes)
    {
        return makeOpenTag(tagname, attributes, ' />');
    }
    /*
     * A utility function to construct the opening tag of an HTML element, or a
     * self-closing tag, along with optional attributes.
     *
     * @param string tagname opening tag name
     * @param object attributes optional object for which the keys are
     * attribute names, and the values are attribute values (may be empty)
     * @return string HTML string for the opening (or self-closing) tag
     */
    function makeOpenTag(tagname, attributes, endtag)
    {
        var tag = ['<' + tagname];
        if (attributes) {
            for (key in attributes) {
                tag.push(' ' + key + '=' + '"' + attributes[key] + '"');
            }
        }
        tag.push(endtag);
        return tag.join('');
    }
    /*
     * A simple string formatter that substitutes string arguments into a
     * template string. The template string should contain substrings with the
     * pattern '{\d+}' (e.g., {1}, {2}, ...), which will be replaced with the
     * corresponding arguments passed to the format function. For example, any
     * occurrence of '{1}' will be replaced by the first argument after the
     * template string.
     *
     * @param string template template string that optionally contains sentinel
     * sequences of the form '{\d+}' to be replaced
     * @param string arg... positional arguments to be substituted into the
     * template string
     * @return string the template string with each sentinel pattern replaced
     * by the appropriate argument
     */
    function format(template /* ... */)
    {
        var args = arguments;
        return template.replace(/\{(\d+)\}/g, function(match, i) {
            var arg = args[parseInt(i)];
            return typeof arg == 'object' ? JSON.stringify(arg) : arg;
        });
    }
    /*
     * Builds an XmlHttpRequest with optional POST data to be sent to the
     * server, and calls the appropriate continuation function when the request
     * completes or fails. The request is carried out asynchronously, and the
     * response handlers are defined by the onSuccess and onFailure keys of the
     * options object passed into this function. If the response content-type
     * is set to application/json, then the response is JSON-decoded before
     * being passed to the onSuccess handler. The options object supports the
     * following keys:
     *
     *    string url: URL to send the request to (required)
     *
     *    string method: HTTP method to use (default GET, but changes to POST
     *        if postdata is specified without also setting the method)
     *
     *    object postdata: object containing key/value pairs of POST arguments
     *        to be sent with the request; the values are automatically
     *        URI-encoded (optional)
     *
     *    function onSuccess: function to be called upon the completion of a
     *        successful request; the response body is passed as the first and
     *        only argument, JSON-decoded if the response content-type was
     *        application/json (optional)
     *
     *    function onFailure: function called if the request times out or
     *        otherwise can't be completed (optional)
     *
     * Example:
     *
     *    sendRequest({
     *        'url': '?c=classifier&a=classify&arg=getdocs',
     *        'postdata': {
     *            'time': self.authTime,
     *            'session': self.authSession,
     *            'label': self.classLabel,
     *            'mix': label_docs_source.value
     *            'keywords': label_docs_keywords.value
     *        },
     *        'onSuccess': function(response) {
     *            ...
     *        },
     *        'onFailure': function() {
     *            ...
     *        }
     *    });
     *
     * @param object options request options.
     */
    function sendRequest(options)
    {
        if (!options.url) {
            throw "sendRequest: 'url' option is required"
        }

        var method = options.method || 'GET';
        var onSuccess = options.onSuccess || function() {};
        var onFailure = options.onFailure || function() {};

        var request = makeRequest();
        if (!request) {
            onFailure();
            return false;
        }

        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                var response = request.responseText;
                var type = request.getResponseHeader('content-type');
                if (type.match(/application\/json/)) {
                    response = JSON.parse(response);
                }
                onSuccess(response);
            }
        }

        if (options.postdata) {
            var postdata = buildQueryString(options.postdata);
            if (!options.method) {
                method = 'POST';
            }
        }

        request.open(method, options.url, true);
        if (postdata) {
            request.setRequestHeader("Content-type",
                "application/x-www-form-urlencoded");
            request.send(postdata);
        } else {
            request.send();
        }
    }
    /*
     * Recursively builds a query string from an object, URI-encoding any
     * strings. Nested objects are handled using the standard HTTP notation for
     * nested arrays; for example, the element accessed in object notation by
     * a.b.c would be converted to a[b][c] in the query string.
     *
     * @param object obj optionally-nested object to be converted to a query
     * string
     * @param string prefix optional prefix to prepend to keys in obj (used in
     * recursive calls)
     * @return string query string representation of obj
     */
    function buildQueryString(obj, prefix)
    {
        var str = [];
        for (var p in obj) {
            p = encodeURIComponent(p);
            var k = prefix ? prefix + "[" + p + "]" : p;
            v = obj[p];
            str.push(typeof v == "object" ?
                    buildQueryString(v, k) :
                    encodeURIComponent(k) + "=" + encodeURIComponent(v));
        }
        return str.join("&");
    }
    /*
     * Removes a particular class from the passed-in element if it's present;
     * otherwise does nothing.
     *
     * @param string className class name to remove
     * @param object el DOM object to modify
     */
    function removeClass(className, el)
    {
        var re = RegExp('(^| )'+className+'( |$)');
        el.className = el.className.replace(re, '$1');
    }

    /*
     * Adds a particular class to the passed-in element; if the element already
     * has the class then it is deleted and the re-added, which should have no
     * significant effect.
     *
     * @param string className class name to add
     * @param object el DOM object to modify
     */
    function addClass(className, el)
    {
        removeClass(className, el);
        el.className += ' ' + className;
    }

    /*
     * Returns true if the passed in element has a particular class, and false
     * otherwise.
     *
     * @param string className the class to check for
     * @param object el DOM object to query
     * @return bool true if el has class className, and false otherwise
     */
    function hasClass(className, el)
    {
        var re = RegExp('(^| )'+className+'( |$)');
        return el.className.search(re) != -1;
    }
    /*
     * Places an element into a loading state, optionally adding a class and
     * setting some text, and provides a method to call in order to cancel the
     * loading state. The basic use case is to replace some text element with
     * 'Loading...' text at the beginning of an asynchronous request, then to
     * revert back to the pre-loading state once the request completes. This
     * function returns an object with a clear method, which may be called in
     * order to cancel the loading state. The options object may contain the
     * following fields:
     *
     *    bool dots: whether to automatically append dots to the loading text
     *        with the passage of a set time interval; the dots start over
     *        each time they reach three (default true)
     *
     *    int dotsInterval: how long to wait before drawing the next dot
     *        (default 333ms)
     *
     *    string className: class name to add to the element when loading
     *        starts, and to remove when it completes (default none)
     *
     * Example:
     *
     *    var loading = loadingText(el, 'Loading');
     *    someAsynchronousAction({
     *        onComplete: function() {
     *            loading.clear();
     *            ...
     *        }
     *    });
     *
     * @param object el DOM object to be manipulated
     * @param string text loading text with which to replace el's innerHTML
     * @param object options loading options
     * @return object object with a clear method, which can be called in order
     * to cancel the loading state, restoring everything to the way it was
     * before loading started
     */
    function loadingText(el, text, options)
    {
        if (options == undefined) {
            options = {};
        }
        var oldHTML = el.innerHTML;
        var drawDots = options.dots !== false;
        var interval = options.dotsInterval || 333;
        var timer;
        if (drawDots) {
            timer = window.setInterval(function() {
                if (el.innerHTML.match(/\.{3}$/)) {
                    el.innerHTML = text;
                }  else {
                    el.innerHTML += '.';
                }
            }, interval);
        }
        if (options.className) {
            addClass(options.className, el);
        }
        el.innerHTML = text;
        return obj = {
            'clear': function() {
                if (drawDots) {
                    window.clearInterval(timer);
                }
                el.innerHTML = oldHTML;
                if (options.className) {
                    removeClass(options.className, el);
                }
            }
        };
    }
    return self;
})();
