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
 * @author Sandhya Vissapragada, Chris Pollett
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015s
 * @filesource
 */
/*
 * Update the version number manually when ever
 * suggest.js undergoes changes
 */
SUGGEST_VERSION_NO = 0;
/*
 * Constants for key codes will handle
 */
KeyCodes = new Object();
KeyCodes.UP_ARROW = 38;
KeyCodes.DOWN_ARROW = 40;
/*
 * Maximum number of search terms to display
 */
MAX_DISPLAY = 6;
/*
 * Maximum number of characters in query to do spellsheck for
 */
MIN_SPELL_CHECK_WIDTH = 40;
/*
 * Height of a search term in pixels
 */
FONT_HEIGHT = 24;
/*
 * Used to delimit the end of a term in a trie.
 * The value below is the default define. Might be set
 * what the trie object loaded says in loadTrie
 */
END_OF_TERM_MARKER = " ";
/*
 * Process to follow once onsubmit event is fired
 *
 * @param None
 * @return None
 */
corrected_query="";
function processSubmit()
{
    updateLocalStorage();
}
/*
 * To check if the given English letter is a vowel
 */

function isVowel(c) {
    return ['a', 'e', 'i', 'o', 'u'].indexOf(c) !== -1;
}
/*
 * Steps to follow every time a key is up from the user end
 * Handles up/dowm arrow keys
 *
 * @param Event event current event
 * @return String text_field Current value from the search box
 *
 */
function onTypeTerm(event, text_field)
{
    var key_code_pressed;
    var term_array;
    var input_term = text_field.value.trim();
    var suggest_results = elt("suggest-results");
    var suggest_dropdown = elt("suggest-dropdown");
    var query = elt("query-field").value;
    var scroll_pos = 0;
    var tmp_pos = 0;
    var local_count = 0;
    locale_terms = new Object();
    local_terms_present = false;
    local_suggest = true;
    search_list_array = new Object();
    scroll_horz = false;

    out_query = false;
    if (typeof transliterate == 'function') {
        out_query = transliterate(query);
    }
    if (out_query && out_query.length > 0)
    {
       input_term = out_query;
    }
    //To find the length of an associative array
    Object.size = function(obj) {
        var size = 0, key;
        for (key in obj) {
            if (obj.hasOwnProperty(key)) size++;
        }
        return size;
    };
    concat_term = "";
    if (window.event) { // IE8 and earlier
        key_code_pressed = event.keyCode;
    } else if (event.which) { // IE9/Firefox/Chrome/Opera/Safari
        key_code_pressed = event.which;
    }
    term_array = input_term.split(" ");
    concat_array = input_term.split(" ", term_array.length - 1);
    if (input_term != "") {
        for (var i = 0; i < concat_array.length; i++) {
            concat_term += concat_array[i] + " ";
        }
        concat_term = concat_term.trim();
    }
    input_term = term_array[term_array.length - 1].trim(" ");

    // behavior if typing keys other than up or down (notice call termSuggest)
    if (key_code_pressed != KeyCodes.DOWN_ARROW &&
            key_code_pressed != KeyCodes.UP_ARROW) {
        search_list = "";

        // First search the local storage to fetch the suggestions
        if (localStorage) {
            var locale_ver = locale+'_' + SUGGEST_VERSION_NO;
            if (localStorage[locale_ver] == null) {
                localStorage.clear();
                count=0;
            } else if (localStorage[locale_ver] != null) {
                split_str = localStorage[locale_ver].split("@@");
                locale_terms = JSON.parse(split_str[1]);
                local_dict =JSON.parse(localStorage[locale_ver].split("@@", 1));
                if (local_dict != null) {
                    local_terms_present = true;
                    termSuggest(local_dict, input_term);
                    local_terms_present = false;
                }
                var sorted_local = sortLocalTerms();
                if (Object.size(search_list_array) > 0) {
                    search_list = "";
                    for (var i = 0; i < sorted_local.length ; i++) {
                        var split_array = sorted_local[i].split('*');

                        if (search_list_array[split_array[1]] != null) {
                            search_split =
                            search_list_array[split_array[1]].split("_");
                            search_list +=  "<li><span id='term" +local_count+
                                "' class='unselected' onclick = 'void(0)' " +
                                "title='"+search_split[0]+"' " +
                                "onmouseover='setSelectedTerm(\""+
                                local_count+"\",\"selected\")'" +
                                "onmouseout='setSelectedTerm(\""+
                                local_count+"\",\"unselected\")'" +
                                "onmouseup='termClick(\""+search_split[0]+
                                "\",this.id)'"+
                                ">" + search_split[1] + "</span></li>";
                            local_count++;
                        }

                    }
                }
                local_suggest = false;
            }
        }
        count = local_count;
        // Now search the actual dictionary trie
        termSuggest(dictionary, input_term);
        // insert nbsp of the number of suggestions are less than MAX_DISPLAY
        short_max = MAX_DISPLAY - count;
        for (var i = 0; i < short_max; i++) {
            search_list += "<li><span class='unselected'>&nbsp;</span></li>";
        }
        if (count < 1) {
            search_list = "";
        }
        suggest_dropdown.scrollTop =  0;
        suggest_results.innerHTML = search_list;
        cursor_pos = -1;
        num_items = count;
        if (num_items == 0 || search_list == "") {
            suggest_dropdown.className = "";
            suggest_dropdown.style.height = "0";
            suggest_dropdown.style.visibility = "hidden";
            suggest_results.style.visibility = "hidden";
        } else {
            suggest_dropdown.className = "dropdown";
            suggest_results.style.visibility = "visible";
            suggest_dropdown.style.visibility = "visible";
            suggest_dropdown.style.height = (FONT_HEIGHT * MAX_DISPLAY) + "px";
            if (scroll_horz) {
                suggest_dropdown.style.overflowX = "scroll";
            } else {
                suggest_dropdown.style.overflowX = "hidden";
            }
        }
    }
    // behavior on up down arrows
    if (suggest_results.style.visibility == "visible") {
        if (key_code_pressed == KeyCodes.DOWN_ARROW) {
            if (cursor_pos < 0) {
                cursor_pos = 0;
                setSelectedTerm(cursor_pos, "selected");
            } else {
                if (cursor_pos < num_items - 1) {
                    setSelectedTerm(cursor_pos, "unselected");
                    cursor_pos++;
                }
                setSelectedTerm(cursor_pos, "selected");
            }
            scroll_count = 1;
            scroll_pos = (cursor_pos - MAX_DISPLAY >= 0) ?
                (cursor_pos - MAX_DISPLAY + 1) : 0;
            suggest_dropdown.scrollTop = scroll_pos * FONT_HEIGHT;
        } else if (key_code_pressed == KeyCodes.UP_ARROW) {
            if (cursor_pos < 0) {
                cursor_pos = 0;
                setSelectedTerm(cursor_pos, "selected");
            } else {
                if (cursor_pos > 0) {
                    setSelectedTerm(cursor_pos, "unselected");
                    cursor_pos--;
                }
                setSelectedTerm(cursor_pos, "selected");
            }
            scroll_pos = (cursor_pos - MAX_DISPLAY + scroll_count >= 0) ?
                (cursor_pos - MAX_DISPLAY + scroll_count) : 0;
            scroll_count = (MAX_DISPLAY > scroll_count) ? scroll_count + 1:
                MAX_DISPLAY;
            suggest_dropdown.scrollTop = scroll_pos * FONT_HEIGHT;
        }
    }
}
/*
 * To correct the spelling of the query words
 *
 * @param String word Input word
 * @return String corrected_word Corrected word
 */
function correctSpelling(word)
{
    var prob = 0;
    trie_subtree = exist(dictionary, word);
    if (trie_subtree != false) {
        prob = parseInt(trie_subtree[END_OF_TERM_MARKER]);
    }
    var trie_subtree;
    var curr_prob = 0;
    var candidates = known(edits1(word));

    candidates.push(word);
    var corrected_word = "";
    var correct_threshold = 25;

    // Use the frequencies to get the best match
    for (var i = 0; i < candidates.length; i++) {
        trie_subtree = exist(dictionary, candidates[i]);
        if (trie_subtree != false) {
            curr_prob = parseInt(trie_subtree[END_OF_TERM_MARKER]);
        }
        if (curr_prob > correct_threshold * prob) {
            correct_threshold = 1;
            prob = curr_prob;
            corrected_word = candidates[i];
        }
    }
    return corrected_word;
}
/*
 * Gets the candidates for the spell correction with edit
 * distance 1
 *
 * @param String word Input word
 * @return Array set Words with edit distance - 1
 */
function edits1(word)
{
    var splits = new Object();
    var deletes = new Array();
    var transposes =new Array();
    var replaces = new Array();
    var inserts = new Array();
    var j = 0;

    splits[""] = word;
    for (var i = 0; i < word.length; i++) {
        splits[word.substring(0, i + 1)] = word.substring(i+1, word.length);
    }
    // Deletes
    for (key in splits) {
        if (splits[key] != "") {
            deletes[j] = key + splits[key].substring(1);
            j++;
        }
    }
    // Transposes
    j = 0;
    for (key in splits) {
        if (splits[key].length > 1) {
            transposes[j] = key + splits[key].substring(1, 2) +
            splits[key].substring(0,1) + splits[key].substring(2);
            j++;
        }
    }
    // Replaces
    j = 0;
    for (key in splits) {
        if (splits[key] != "") {
            for (var i = 0;i < alpha.length; i++) {
                replaces[j] = key + alpha.substring(i,i+1) +
                 splits[key].substring(1);
                j++;
            }
        }
    }
    // Inserts
    j = 0;
    for (key in splits) {
        for (var i=0; i < alpha.length; i++) {
            inserts[j] = key + alpha.substring(i, i + 1) + splits[key];
            j++;
        }
    }
    var set =
        deletes.concat(transposes).concat(replaces).concat(inserts).unique();
    return set;
}
Array.prototype.unique = function() {
    var a = this.concat();
    for (var i = 0; i<a.length; ++i) {
        for (var j=i+1; j < a.length; ++j) {
            if (a[i] === a[j])
                a.splice(j, 1);
        }
    }
    return a;
}
/*
 * To get the set of words which are known from the dictionary
 *
 * @param Array words_ip array of words
 * @return Array known_words array of known words
 */
function known(words_ip)
{
    var known_words = new Array(),j=0;
    var ret_array;
    for (var i=0;i < words_ip.length;i++) {
        ret_array = exist(dictionary, words_ip[i]);
        if (ret_array[END_OF_TERM_MARKER] != null) {
            known_words[j] = words_ip[i];
            j++;
        }
    }
    return known_words;
}
/*
 * To update the local storage with the previous query terms and
 * create a trie on those terms
 *
 */
function updateLocalStorage()
{
    var trie_to_store;
    trie_storage = {};
    var store_term = elt("query-field").value;
    var freq, k = 0;
    var sorted_locale_terms = new Array();
    if (localStorage) {
        if (locale_terms && locale_terms[store_term] == null) {
            locale_terms[store_term] = 1;
        } else {
            freq = parseInt(locale_terms[store_term]);
            freq++;
            locale_terms[store_term] = freq;
        }
        for (var key in locale_terms) {
            sorted_locale_terms[k] = key;
            k++;
        }
        sorted_locale_terms.sort();

        // Build the trie
        for (var i=0; i<sorted_locale_terms.length; i++) {
            var trie_word = sorted_locale_terms[i];
            var letters = trie_word.split("");
            var cur = trie_storage;
            for (var j=0; j < letters.length; j++) {
                var letter = encode(letters[j]);
                var pos = cur[ letter ];
                if (pos == null) {
                    if (j === letters.length - 1) {
                        cur = cur[ letter ] = {'$' : '$'};
                    } else {
                        cur = cur[ letter ] = {};
                    }
                } else if (pos === 0) {
                    cur = cur[ letter ] = { '$' : '$' };
                } else {
                    cur = cur[ letter ];
                }
            }
        }
    }
    trie_to_store = JSON.stringify(trie_storage);
    localStorage.setItem(locale + '_' + SUGGEST_VERSION_NO, trie_to_store +
     "@@" + JSON.stringify(locale_terms));
}
/*
 * Sort the local storage words based of number of times they are queried
 *
 * @return Array local storage words
 */
function sortLocalTerms()
{
    var local_storage_array = new Array();
    if (Object.size(locale_terms) > 0) {
        var j = 0;
        for (var key in locale_terms) {
            local_storage_array[j] = locale_terms[key] + "*"+ key;
            j++;
        }
    }
    local_storage_array.sort(termFrequencyComparison);
    local_storage_array.reverse();
    return local_storage_array;
}
/*
 * Callback used by a sort call in sortLocalTerms to compare two
 * string where before the * in the string is a term and after is a frequency
 *
 * @param String a in format described above
 * @param String b in format described above
 * @return number 0 - if same frequncy, negative if b has larger frequency,
 *     postive otherwise
 */
function termFrequencyComparison(a, b)
{
    var split_array1 = a.split('*');
    var split_array2 = b.split('*');
    var val1 = parseInt(split_array1[0]);
    var val2 = parseInt(split_array2[0]);
    return (val1 - val2);
}
/*
 * To select an suggest value while up/down arrow keys are being used
 * and place in the search box
 *
 * @param int pos index in the list items of suggest terms
 * @param String class_value value for CSS class attribute for that list item
 */
function setSelectedTerm(pos, class_value)
{
    var query_field_object = elt("query-field");
    query_field_object.value = elt("term" + pos).title;
    elt("term" + pos).className = class_value;
}

/*
 * To selects a term from the suggest dropdownlist and performs as search
 *
 * @param String term what was clicked on
 */
function termClick(term,termid)
{
    var results_dropdown = elt("suggest-results");
    var query_field_object = elt("query-field");
    query_field_object.value = term;
    results_dropdown.innerHTML = "";
    elt("suggest-dropdown").style.display = "none";
    elt("search-form").submit();
}
/*
 * Fetch words from the Trie and add to seachList with <li> </li> tags
 *
 * @param Array trie_array contains all search terms
 * @param String parent_word the prefix want to find sub-term for in trie
 * @param String highlighted_word parent_word, root_word + "<b>" + rest of
 *  parent
 */
function getTrieTerms(trie_array, parent_word, highlighted_word)
{
    var search_terms;
    var highlighted_terms;

    if (trie_array != null) {
        for (key in trie_array) {
            if (key != END_OF_TERM_MARKER ) {
                getTrieTerms(trie_array[key], parent_word + key,
                        highlighted_word + key);
            } else {
                if ( (locale_terms[decode(parent_word)] == null
                     && local_terms_present == false)) {
                    search_terms = concat_term.trim() + " " +
                        decode(parent_word);
                    search_terms = search_terms.trim();
                    highlighted_terms = concat_term.trim() + " " +
                    decode(highlighted_word) + "</b>";
                    search_list +=  "<li><span id='term" +count+
                        "' class='unselected' onclick = 'void(0)' " +
                        "title='"+search_terms+"' " +
                        "onmouseover='setSelectedTerm(\""
                        +count+"\",\"selected\")'" +
                        "onmouseout='setSelectedTerm(\""+count
                        +"\",\"unselected\")'" +
                        "onmouseup='termClick(\""+search_terms
                        +"\",this.id)'"+
                        ">" + highlighted_terms + "</span></li>";
                    count++;
                    //handle long suggests phrases with horizontal scrollbar
                    if (search_terms.length * 24 > 1200 &&  !scroll_horz)
                        scroll_horz = true;
                } else if (local_terms_present == true) {
                    search_terms = concat_term.trim() + " "
                     + decode(parent_word);
                    search_terms = search_terms.trim();
                    highlighted_terms = concat_term.trim() + " "
                    + decode(highlighted_word) + "</b>";
                    search_list_array[decode(parent_word)] = search_terms +
                     "_" +highlighted_terms;
                    //handle long suggests phrases with horizontal scrollbar
                    if (search_terms.length * 24 > 1200 &&  !scroll_horz)
                        scroll_horz = true;
                }
            }
        }
    }
}
/*
 * Returns the sub trie_array under term in
 * trie_array. If term does not exist in the trie_array
 * returns false
 *
 * @param String term  what to look up
 * @return Array trie_array sub trie_array under term
 */
function exist(trie_array, term)
{
    if (trie_array == null) {
        return false;
    }
    for (var i = 0; i < term.length; i++) {
        tmp = getUnicodeCharAndNextOffset(term, i);
        if (tmp == false) return false;
        next_char = tmp[0];
        i = tmp[1];
        enc_char = encode(next_char);
        trie_array = trie_array[enc_char];
        if (trie_array == null) {
            return false;
        }
    }
    return trie_array;
}
/*
 * Entry point to find word completions/suggestions. Finds the portion of
 * trie_aray beneath term. Then using this subtrie get the first six entries.
 * Six is specified in get values.
 *
 * @param Array trie_array - a nested array represent a trie
 * @param String term - what to look up suggestions for
 * @sideeffect global Array search_list has list of first six entries
 */
function termSuggest(trie_array, term)
{
    last_word = false;
    if (local_suggest == true) {
        count = 0;
        search_list = "";
    }
    // For US english ignore the case
    if (locale == 'en-US') {
        term = term.toLowerCase();
    }
    var tmp;
    if (trie_array == null) {
        return false;
    }
    if ((term.length) > 1) {
        trie_array = exist(trie_array, term);
        if (trie_array == false) {
            return false;
        }
    } else {
        trie_array = trie_array[term];
    }
    getTrieTerms(trie_array, term, term + "<b>");
}

/* wrappers to save typing */
function decode(str) {
    str = str.replace(/\+/g, '%20');
    return decodeURIComponent(str);
}
/* wrappers to save typing */
function encode(str)
{
    str = encodeURIComponent(str);
    str = str.replace(/\'/g, '%27'); // encodeURIComponent doesnt convert '
    return str;
}
/*
 * Extract next Unicode Char beginning at offset i in str returns Array
 * with this character and the next offset
 *
 * This is based on code found at:
 * https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects
 * /String/charAt
 *
 * @param String str what to get the next character out of
 * @param int i current offset into str
 * @return Array pair of Unicode character beginning at i and the next offset
 */
function getUnicodeCharAndNextOffset(str, i)
{
    var code = str.charCodeAt(i);
    if (isNaN(code)) {
        return '';
    }
    if (code < 0xD800 || code > 0xDFFF) {
        return [str.charAt(i), i];
    }
    if (0xD800 <= code && code <= 0xDBFF) {
        if (str.length <= i + 1) {
            return false;
        }
        var next = str.charCodeAt(i + 1);
        if (0xDC00 > next || next > 0xDFFF) {
            return false;
        }
        return [str.charAt(i) + str.charAt(i + 1), i + 1];
    }
    if (i === 0) {
        return false;
    }
    var prev = str.charCodeAt(i-1);
    if (0xD800 > prev || prev > 0xDBFF) {
        return false;
    }
    return [str.charAt(i + 1), i + 1];
}
/*
 * Load the Trie during the launch of website
 * Trie's are represented using nested arrays.
 */
function loadFiles()
{
    var request = makeRequest();
    if (request) {
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200 &&
                request.responseText != "") {
                trie = JSON.parse(request.responseText);
                dictionary = trie["trie_array"];
                END_OF_TERM_MARKER = trie["end_marker"];
                if (typeof alpha != 'undefined')
                    spellCheck();
            }
            END_OF_TERM_MARKER = (typeof END_OF_TERM_MARKER == 'undefined') ?
                ' ' : END_OF_TERM_MARKER;
        }
        locale = document.documentElement.lang;
        if (locale) {
            trie_loc = "./?c=resource&a=suggest&locale=" + locale;
            request.open("GET", trie_loc, true);
            request.send();
        }
    }
}
/*
 * To process spell correction
 */
function spellCheck()
{
    var referenceNode;
    if (document.getElementsByClassName) {
        referenceNode = document.getElementsByClassName("serp")[0];
    }
    if (referenceNode) {
        var corrected_spell = elt("spell-check");
        var thesaurus_results = elt("thesaurus-results");
        /* corrected_spell might not be present if WORD_SUGGEST off
           If there are already thesaurus results we don't want to
           clutter the top area so also don't suggest
         */
        if (!corrected_spell || thesaurus_results) {return; }
        var logged_in = elt("csrf-token");
        if (logged_in) {
            var csrf_token = elt("csrf-token").value;
        }
        var its_value = elt("its-value").value;

        var query = elt("query-field").value;
        if (query == "") return;
        var ret_array;
        var ret_word;
        if (localStorage) {
            var locale_ver = locale + '_' + SUGGEST_VERSION_NO;
        }
        if (locale_ver && localStorage[locale_ver] != null) {
            split_str = localStorage[locale_ver].split("@@");
            locale_terms = JSON.parse(split_str[1]);
            if (locale_terms[query] > 5) {
                return; // search for a lot so don't suggest
            }
        }
        var term_array = query.split(" ");
        for (var i = 0; i < term_array.length; i++) {
            ret_word = "";
            ret_word = correctSpelling(term_array[i].toLowerCase());
            if (ret_word.trim(" ") == "") {
                corrected_query += term_array[i] + " ";
            } else {
                corrected_query += ret_word + " ";
            }
        }
        if (query.length > MIN_SPELL_CHECK_WIDTH) {
            return;
        }
        if (corrected_query.trim() != query) {
            if (logged_in) {
                var token_name = csrf_name;
                var spell_link = "?" + token_name + "=" + csrf_token + "&q="
                    + corrected_query;
            } else {
                var spell_link = "?q=" + corrected_query;
            }
            corrected_spell.innerHTML = "<b>" + local_strings.spell
                +": <a rel='nofollow' href='" + spell_link +
                "'>"  + corrected_query + "</a></b>";
        }
    }
}
tag("body")[0].onload = loadFiles;
var ip_field = elt("query-field");
ip_field.onpaste = function(e) {
    setTimeout(function(){
        onTypeTerm(e,ip_field);
        }, 0);
}
ip_field.oncut = function(e) {
    setTimeout(function(){
            onTypeTerm(e,ip_field);
            }, 0);
}
