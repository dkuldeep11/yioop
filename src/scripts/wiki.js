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
 * @author Eswara Rajesh Pinapala (edited Chris Pollett)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2014
 * @filesource
 */
/*
 * This script adds buttons to textareas on a page which when clicked insert
 * the relevant way to format something using wiki syntax.
 *
 * The editor automatically renders the editor buttons using the following list
 * of button names for configuration. These can be set on the data-buttons
 * attribute of the textarea
 * {
 * "wikibtn-bold"
 * "wikibtn-italic"
 * "wikibtn-underline"
 *" wikibtn-strike"
 * "wikibtn-nowiki"
 * "wikibtn-hyperlink"
 * "wikibtn-bullets"
 * "wikibtn-numbers"
 * "wikibtn-hr"
 * "wikibtn-heading"
 * "wikibtn-search"
 * "wikibtn-table"
 * "wikibtn-slide"
 * "wikibtn-definitionlist"
 * }
 */
/*
 * Used to contain formatting info about all buttons on the wiki editor
 * @var Array
 */
var editor_all_buttons = [];
/*
 * Used to store formatting info buttons on a particular textarea
 * @var Array
 */
var editor_buttons = [];
/*
 * Object that buffers selection information.
 * @var Object
 */
var editor_buffer = {};
/*
 * Add buttons for common wiki operation before the textareas on a page.
 */
function editorizeAll()
{
    var text_areas = tag("textarea");
    var len = text_areas.length;
    var ids = new Array();

    for (i = 0; i < len; i++)
    {
        if (text_areas[i].id){
            editorize(text_areas[i].id);
        }
    }
}
/*
 * Adds Wiki editor buttons to a textarea with the given id
 *
 * @param String id identifier of the text area to have wiki buttons added to
 */
function editorize(id)
{
    /*
     check if the editor div exists.
     */
    var node = elt(id);

    if (node === null) {
        /*
         If the div to render wiki_editor is not found, do nothing.
         */
        alert("No textarea found with id = " + id);
        return false;
    }else{
        node.addEventListener("focus", function(event)
        {
            enableKBShortcuts(id);
        }, true);
    }

    var button_string = "";

    /*
        Initialize tool bar wiki_buttons object
     */
    editor_buttons[id] = {};
    /*
        filter the buttons according to the data-buttons
        attribute.
     */
    filterButtons(id);
    /*
        Buttons are rendered below.
     */
    for (var prop in editor_buttons[id]) {
        var no_buttons = ['wikibtn-search', 'wikibtn-table', 'wikibtn-heading'];
        if (editor_buttons[id].hasOwnProperty(prop)
            && (no_buttons.indexOf(prop) === -1)) {
            button_string += '<input type="button" class="' + prop +
                '" onclick="wikifySelection(\'' + prop + '\', \''+ id +'\');">';
        }
    }
    /*
        Render the wiki-popup-prompt div used to prompt
        user input.
     */
    var editor_toolbar = '<div id="wiki-popup-prompt-' + id +
        '" class="wiki-popup-prompt" style="display: none;"></div>' +
        '<div class="wiki-buttons">' + button_string;
    /*
        check if heading was desired and render if was.
     */
    if (editor_buttons[id].hasOwnProperty('wikibtn-heading')) {
        editor_toolbar += '<select id="wiki-heading-' +id +
            '" value="heading" ' +
            'onchange="addWikiHeading(\''+ id +'\');">' +
            '<option selected="" disabled="">'+
            tl['wiki_js_heading'] + '</option>' +
            '<option value="1">H1</option>' +
            '<option value="2">H2</option>' +
            '<option value="3">H3</option>' +
            '<option value="4">H4</option>' +
            '</select>';
    }
    /*
     check if table was desired and render if was.
     */
    if (editor_buttons[id].hasOwnProperty('wikibtn-table')) {
        editor_toolbar += '<input type="button" class="wikibtn-table" ' +
            ' onclick="addWikiTable(\''+ id +'\');" />';
    }

    /*
     check if search was desired and render if was.
     */
    if (editor_buttons[id].hasOwnProperty('wikibtn-search')) {
        editor_toolbar += '<input type="button" ' +
            ' class="wikibtn-search-widget" ' +
            ' onclick="addWikiSearch(\''+ id +'\');" />';
    }

    editor_toolbar += '</div>';

    /*
        Insert the toolbar div before the textarea
     */
    node.insertAdjacentHTML("beforebegin", editor_toolbar);

    return;
}
/*
 * Method to return standard buttons as an object.
 *
 * @return Object
 */
function getStandardButtonsObject()
{
    return {
        'wikibtn-bold': ['---', '---'],
        'wikibtn-italic': ['--', '--'],
        'wikibtn-underline': ['<u>', '</u>'],
        'wikibtn-strike': ['<s>', '</s>'],
        'wikibtn-nowiki': ['<nowiki>', '</nowiki>'],
        'wikibtn-hyperlink': ['[[', ']]'],
        'wikibtn-slide': ['=' + tl['wiki_js_slide_sample_title'] + '=\n'
        + '* ' + tl['wiki_js_slide_sample_bullet'] + '\n'
        + '* ' + tl['wiki_js_slide_sample_bullet'] + '\n'
        + '* ' + tl['wiki_js_slide_sample_bullet'] + '\n'
        + '....' + '\n'],
        'wikibtn-bullets': ['* ' + tl['wiki_js_bullet'] + ' \n'],
        'wikibtn-numbers': ['# ' + tl['wiki_js_enum'] + ' \n'],
        'wikibtn-definitionlist': [
            '; ' + tl['wiki_js_definitionlist_item']
            + ' : ' + tl['wiki_js_definitionlist_definition']
            + '' + '\n'
            + '; ' + tl['wiki_js_definitionlist_item']
            + ' : ' + tl['wiki_js_definitionlist_definition']
            + '' + '\n'],
        'wikibtn-leftaligned': ['{{left|','}}'],
        'wikibtn-centeraligned': ['{{center|','}}'],
        'wikibtn-rightaligned': ['{{right|','}}'],
        'wikibtn-hr': ['---- \n']
    };
}
/*
 * Filters the buttons according to exclusion/inclusion rules.
 * If the 'data-buttons' attribute value starts with 'all' - Only the buttons
 * need to be excluded should be followed, with their name specified with a '!'
 * prefix.
 * Any buttons with no '!' prefix will eb ignored
 * example : all,!bol,!italic will render editor with all buttns excluding bold
 * and italic
 *
 * If the 'data-buttons' attribute value does not starts with 'all', any button
 * names followed will only be included in the editor.
 * Any buttons with '!' prefix will be ignored.
 * example : bol,italic will render editor only with bold and italic buttons.
 *
 * @param String id identifier of the textarea that we want editor buttons on
 */
function filterButtons(id)
{
    editor_all_buttons[id] = getStandardButtonsObject();
    editor_all_buttons[id]['wikibtn-heading'] = '';
    editor_all_buttons[id]['wikibtn-search'] = '';
    editor_all_buttons[id]['wikibtn-table'] = '';
    var wiki_text_div = elt(id);
    var wiki_buttons = wiki_text_div.getAttribute("data-buttons");
    if (wiki_buttons) {
        var wiki_buttons_array = wiki_buttons.split(',');
        var buttons_array_length = wiki_buttons_array.length;
        var included_buttons = new Array();
        var excluded_buttons = new Array();
        var exc = false;
        if (wiki_buttons_array[0].trim() === 'all') {
            exc = true;
        }
        if (wiki_buttons_array[0].trim() === 'none') {
            editor_buttons[id] = [];
            return;
        }
        for (var i = 0; i < buttons_array_length; i++) {
            wiki_buttons_array[i] = wiki_buttons_array[i].trim();
            var firstChar = wiki_buttons_array[i].charAt(0);
            if (wiki_buttons_array[i] && (exc === true) && firstChar === '!') {
                wiki_buttons_array[i] = wiki_buttons_array[i].substr(1);
                if (editor_all_buttons[id].hasOwnProperty(
                    wiki_buttons_array[i])) {
                    excluded_buttons.push(wiki_buttons_array[i]);
                    delete editor_all_buttons[id][
                        wiki_buttons_array[i]];
                }
            } else if (wiki_buttons_array[i] && exc === false) {
                if (editor_all_buttons[id].hasOwnProperty(
                    wiki_buttons_array[i])) {
                    included_buttons.push(wiki_buttons_array[i]);
                    editor_buttons[id][wiki_buttons_array[i]] =
                        editor_all_buttons[id][wiki_buttons_array[i]];
                }
            }
        }
    } else {
        editor_buttons[id] = editor_all_buttons[id];
    }
    if (Object.keys(editor_buttons[id]).length === 0) {
        editor_buttons[id] = editor_all_buttons[id];
    }
}
/*
 * This function returns the selected text from text_area.
 * The returned Object has properties for
 * selected text, entire prefix and entire suffix strings to the
 * selected text.
 *
 * @param String id the identifier for the textarea to get the selected text for
 * @return Object with properties of the selected text set
 */
function getSelection(id)
{
    /*
     Select the DOM element
     */
    var text_area = elt(id);
    /*
     IE?
     */
    if (document.selection) {
        var selection_bookmark =
            document.selection.createRange().getBookmark();
        var sel = text_area.createTextRange();
        sel.moveToBookmark(selection_bookmark);
        var sleft = text_area.createTextRange();
        sleft.collapse(true);
        sleft.setEndPoint("EndToStart", sel);
        text_area.selectionStart = sleft.text.length;
        text_area.selectionEnd = sleft.text.length + sel.text.length;
        text_area.selectedText = sel.text;
        var selected_text_prefix = text_area.value.substring(0,
            text_area.selectionStart);
        var selection = sel.text;
        var selected_text_suffix = text_area.value.substring(
            text_area.selectionEnd, text_area.textLength);
    } else if (typeof (text_area.selectionStart) !== "undefined") {
        /**
         Things are pretty straightforward in Mozilla based
         browsers & IE > 10.
         We can get the selectionStart & selectionEnd character
         position directly.
         Then compute the prefix and suffix using substr method.
         */
        var selected_text_prefix = text_area.value.substr(0,
            text_area.selectionStart);
        var selection = text_area.value.substr(
            text_area.selectionStart,
            text_area.selectionEnd - text_area.selectionStart);
        var selected_text_suffix = text_area.value.substr(
            text_area.selectionEnd);
    }
    var obj = {};
    obj.selection = selection;
    obj.selected_text_prefix = selected_text_prefix;
    obj.selected_text_suffix = selected_text_suffix;
    setCaretPosition(text_area, text_area.selectionEnd);
    return obj;
}

/*
 * This function can be used to set
 * the caret position in a text field object like a text area.
 *
 * @param element text_field in which the caret is to be set.
 * @param int pos position of the caret to be set.
 * @return undefined
 */
function setCaretPosition(text_field, pos)
{
    if (text_field.setSelectionRange) {
        text_field.focus();
        text_field.setSelectionRange(pos, pos);
    } else if (text_field.createTextRange) {
        var range = text_field.createTextRange();
        range.collapse(true);
        range.moveEnd('character', pos);
        range.moveStart('character', pos);
        range.select();
    }
}
/*
 * Looks up the name'd wiki formatting task, then uses it type to
 * render it in the textarea with identifer id
 *
 * @param String name identifier of the wiki task to be performed
 * @param String id indentifer of the textarea to add wiki code for the given
 *     task
 */
function wikifySelection(name, id)
{
    var length = 0;
    length = editor_buttons[id][name].length;
    if (length === 2) {
        wikify(editor_buttons[id][name][0].replace(new RegExp('-', 'g'), "\'"),
           editor_buttons[id][name][1].replace(new RegExp('-', 'g'), "\'"),
           name, id);
    } else if (length === 1) {
        insertTextAtCursor(editor_buttons[id][name][0], id);
    }
}

/*
 * This is the main function that takes  prefix and suffix characters for
 * wikifying selected text. Selected text will be obtained using
 * the getSelection();
 *
 * @param String wiki_prefix prefix to be added to wikify the selected text.
 * @param String wiki_suffix suffix to be added to wikify the selected text.
 * @param String task_name name of the task to render default selection text.
 * @param String id of textarea to wikify
 */
function wikify(wiki_prefix, wiki_suffix, task_name, id)
{
    var br = '';
    /*
     Select the DOM element - wikiText
     */
    var text_area = elt(id);
    var obj = getSelection(id);
    var selection = obj.selection;
    var selected_text_prefix = obj.selected_text_prefix;
    var selected_text_suffix = obj.selected_text_suffix;
    if (!selection && wiki_prefix === '[[') {
        wiki_popup_prompt = elt('wiki-popup-prompt-'+id);
        if (wiki_popup_prompt.hasChildNodes()) {
            /*
             Remove childnodes, if any exist.
             */
            while (wiki_popup_prompt.hasChildNodes()) {
                wiki_popup_prompt.removeChild(wiki_popup_prompt.lastChild);
            }
        }
        wiki_popup_prompt.appendChild(createHyperlinkForm(id));
        editor_buffer = obj;
        toggleDisplay('wiki-popup-prompt-'+id);
    }

    if (!selection && wiki_prefix !== '[[') {
        br = '\n';
        selection = tl[task_name.replace('wikibtn-', 'wiki_js_')];
    }
    if (!selection && wiki_prefix !== '[[') {
        selection = task_name;
    }
    /*
     Now Add the wrap the selected text between the wiki stuff,
     and then wrap the selected
     text between actual selected text's prefix and suffix.
     Replace the text_area contents with the result.
     */
    if (selection) {
        var tmp = selected_text_prefix + wiki_prefix + selection + wiki_suffix
            + br;
        text_area.value = tmp + selected_text_suffix;
        setCaretPosition(text_area, tmp.length);
    }
}
/*
 * This is a special function for processing user input
 * when the user clicks on hyperlink, and inserts a hyper link.
 *
 * @param String id of textarea the wiki editor instance is associated with
 * @return String
 */
function addWikiHyperlink(id)
{
    toggleDisplay('wiki-popup-prompt-' + id);
    var title = elt('wikify-link-text-' + id).value;
    var link = elt('wikify-link-url-' + id).value;
    if (!link && !title) {
        return false;
    }
    if (!link || !title) {
        out = "" + link + title;
    } else {
        out = link + "|"  + title;
    }
    insertTextAtCursor("[[" + out + "]]" + '\n', id);
}
/*
 * This function takes in number of rows, columns and
 * header_text(if desired) and constructs a table in wiki markup.
 *
 * @param int rows number of rows intended in the table.
 * @param int cols number of cols intended in the table.
 * @param string example_text placeholder text to be inserted in each
 * table field.
 * @param string header_text if header text is intended, placeholder
 * text for column header.
 * @return string
 */
function createWikiTable(rows, cols, example_text, header_text)
{
    var br = "\n";
    var table = "{|" + br;
    for (i = 0; i < rows; i++) {
        if (header_text && i === 0) {
            table = table + "|- " + br;
            table = table + "! ";
            for (j = 0; j < cols; j++) {
                table = table + header_text + " ";
                if (j < (cols - 1)) {
                    table = table + "!!" + " ";
                } else {
                    table = table + br;
                }
            }
        }
        table = table + "|- " + br;
        table = table + "| ";
        for (j = 0; j < cols; j++) {
            table = table + example_text + " ";
            if (j < (cols - 1)) {
                table = table + "||" + " ";
            } else {
                table = table + br;
            }
        }
    }
    table = table + "|}";
    return table;
}
/*
 * Inserts wiki search widget in textarea indentified by id
 *
 * @param String id of textarea to insert wiki search widget
 */
function addWikiSearch(id)
{
    wiki_popup_prompt = elt('wiki-popup-prompt-'+id);

    if (wiki_popup_prompt.hasChildNodes()) {
        /*
         Remove child nodes, if any exist.
         */
        while (wiki_popup_prompt.hasChildNodes()) {
            wiki_popup_prompt.removeChild(wiki_popup_prompt.lastChild);
        }
    }
    wiki_popup_prompt.appendChild(createSearchWidgetForm(id));
    toggleDisplay('wiki-popup-prompt-'+id);
}

/*
 * Gets the size of the search widget to load.
 *
 * @param String id identifier of the textarea to put search form on
 */
function useInputForSearch(id)
{
    toggleDisplay('wiki-popup-prompt-'+id);
    size_elt = elt('wiki-search-size-'+id);
    var size = size_elt.options[size_elt.selectedIndex].value;
    insertTextAtCursor( "{{search:default|size:" + size+ "|placeholder:" +
        tl['wiki_js_placeholder'] + "}}\n", id);
}
/*
 * Util function to Stringify an JS Object
 *
 * @param Object js_object the javascript object to be converted to string.
 * @return String
 */
function objToString(js_object)
{
    var json_string = [];
    for (var property in js_object) {
        if (js_object.hasOwnProperty(property)) {
            json_string.push('"' + property + '"' + ':' + js_object[property] );
        }
    }
    json_string.push();
    return '{' + json_string.join(',') + '}';
}
/*
 * This is invoked by the editor to insert a wiki table.
 * This functions takes care okf the user input for rows/columns/etc
 * and leverages createWikiTable function to construct the table.
 *
 * @param String id
 */
function addWikiTable(id)
{
    wiki_popup_prompt = elt('wiki-popup-prompt-'+id);

    if (wiki_popup_prompt.hasChildNodes()) {
        /*
         Remove childnodes, if any exist.
         */
        while (wiki_popup_prompt.hasChildNodes()) {
            wiki_popup_prompt.removeChild(wiki_popup_prompt.lastChild);
        }
    }
    wiki_popup_prompt.appendChild(createTableForm(id));
    toggleDisplay('wiki-popup-prompt-'+id);
}
/*
 * Prompt/Draws a form to get input from a user, then uses that input to draw
 * a wiki table in the textarea with indentifier id
 *
 * @param String id string identifier of the textarea to draw table in
 */
function useInputForTable(id)
{
    var rows = elt('wiki-rows-'+id).value;
    var cols = elt('wiki-cols-'+id).value;
    var head_checked = elt('wiki-insert-heading-'+id).checked;
    addWikiTableFromInput(rows, cols, head_checked, id);
}
/*
 * Using the input from the user, like number of rows,cols etc.
 * builds a table in wiki markup and inserts at cursor.
 * @param int rows number of rows desired
 * @param int cols number of cols desired
 * @param Boolean head_checked heading desired or not.
 */
function addWikiTableFromInput(rows, cols, head_checked, id)
{
    toggleDisplay('wiki-popup-prompt-' + id);
    if (!cols || !rows) {
        return;
    }
    if (head_checked) {
        var table = createWikiTable(rows, cols,
            tl['wiki_js_example'], tl['wiki_js_table_title']);
    } else {
        table = createWikiTable(rows, cols, tl['wiki_js_example']);
    }
    insertTextAtCursor(table + '\n', id);
}
/*
 * Accepts text as parameter to be inserted at caret's
 * current position.
 *
 * @param string text string that is intended to be placed at current cursor.
 */
function insertTextAtCursor(text, textarea_id)
{
    var field = elt(textarea_id);
    if (document.selection) {
        var range = document.selection.createRange();
        if (!range || range.parentElement() !== field) {
            field.focus();
            range = field.createTextRange();
            range.collapse(false);
        }
        range.text = text;
        range.collapse(false);
        range.select();
    } else {
        field.focus();
        var val = field.value;
        var selStart = field.selectionStart;
        var caretPos = selStart + text.length;
        field.value = val.slice(0, selStart) +
                text + val.slice(field.selectionEnd);
        field.setSelectionRange(caretPos, caretPos);
    }
}

/*
 * Used to mark-up selected text in textarea given by id with a heading whose
 * size is decided by a select drop down
 *
 * @param String id of a text area that will mark
 */
function addWikiHeading(id)
{
    select_object = elt("wiki-heading-" + id);
    var heading_size = select_object.value;
    var markup_text = fillChars("=", heading_size);
    select_object.selectedIndex = 0;
    wikify(markup_text, markup_text, "wikibtn-heading" + heading_size , id);
}

/*
 * Fills a character array and returns as a string consisting given count
 * of a given character.
 *
 * @param String c character to be filled into an array.
 * @param int n number of times the character to be repeated.
 * @return String consistring of n many c's
 */
function fillChars(c, n)
{
    for (var e = ""; e.length < n; ) {
        e += c;
    }
    return e;
}

/*
 * Created an input prompt HTML element with 2 text fields and a checkbox.
 * @param String id
 * @return HTMLFormElement
 */
function createTableForm(id)
{
    var wiki_prompt_for_table = ce("div");
    var table_form =
        '<div class="wiki-popup-content">' +
        '<h2 class="center">'+tl['wiki_js_add_wiki_table']+'</h2>' +
        '<form><table><tr>'+
        '<th class="float-opposite"><label for="wiki-rows-' + id + '" >' +
             tl['wiki_js_for_table_rows'] + '</label></th>' +
        '<td><input id="wiki-rows-' + id +
            '" name="wiki-prompt-rows" '+ 'type="text"></td></tr>'+
        '<tr><th class="float-opposite"><label for="wiki-cols-' + id + '">' +
            tl['wiki_js_for_table_cols'] + '</label></th>'+
        '<td><input id="wiki-cols-' + id +'" name="wiki-prompt-cols" '+
            'type="text"></td></tr>'+
        '<tr><th  class="float-opposite"><label for="wiki-insert-heading-' +
            id + '" >' + tl['wiki_js_prompt_heading'] + '</label></th>' +
        '<td><input id="wiki-insert-heading-' + id + '"' +
            'name="wiki-insert-heading" type="checkbox"></td></tr>' +
        '</table>' +
        '<div class="center"><button onmousedown="useInputForTable(\'' + id
            +'\')" name="submit" class="button-box">'+ tl['wiki_js_submit'] +
        '</button> &nbsp;' +
        '<button onmousedown="toggleDisplay(\'wiki-popup-prompt-' + id +
            '\')" name="close" class="button-box" >' + tl['wiki_js_cancel'] +
        '</button></form></div>';
    wiki_prompt_for_table.innerHTML = table_form;
    return wiki_prompt_for_table;
}

/*
 * Creates an HTMLFormElement with two text fields to get the URL and text of
 * a link
 * @param String id identifier of the text area that we want to add a wiki link
 *     to
 * @return HTMLFormElement containing form that we will use to get info from
 *     the user so that we can later add a wiki link
 */
function createHyperlinkForm(id)
{
    var wiki_prompt_for_hyperlink = ce("div");
    wiki_prompt_for_hyperlink.innerHTML =
        '<div class="wiki-popup-content">' +
        '<h2 class="center">'+tl['wiki_js_add_hyperlink']+'</h2>' +
        '<form><table><tr><th><label for="wikify-link-text-'+id+'">' +
            tl['wiki_js_link_text'] + '</label></th>' +
        '<td><input id="wikify-link-text-'+id+'" '+
            'name="wikify-link-text" type="text"></td></tr>' +
        '<tr><th><label for="wiki-link-url-'+id+'">' +
            tl['wiki_js_link_url'] + '</label></th>' +
        '<td><input id="wikify-link-url-'+id+'" ' +
            'name="wikify-enter-link" type="text"></td></tr></table>' +
        '<div class="center"><button onmousedown="addWikiHyperlink(\''+
            id +'\')" ' + 'name="submit" class="button-box">'+
            tl['wiki_js_submit'] +'</button> &nbsp;' +
        '<button onmousedown="toggleDisplay(\'wiki-popup-prompt-' + id +
            '\')" name="close" class="button-box">' + tl['wiki_js_cancel'] +
        '</button></div></form></div>';
    return wiki_prompt_for_hyperlink;
}
/*
 * Creates an an HTMLFormElement with a form to get the kind of search bar
 * that is desired to be inserted into a wiki document
 * @param String id identifier of the textarea used to insert wiki text
 */
function createSearchWidgetForm(id)
{
    var search_elt = ce("div");
    search_form = '<div class="wiki-popup-content">'+
        '<h2 class="center">' + tl['wiki_js_add_search'] + '</h2>' +
        '<div class="center"><form>' +
        '<select name="wiki_search_size" id="wiki-search-size-' + id + '" >' +
        '<option disabled="" selected="" >' + tl['wiki_js_search_size'] +
        '</option>'+
        '<option value="small">' + tl['wiki_js_small'] + '</option>' +
        '<option value="medium">' + tl['wiki_js_medium'] + '</option>' +
        '<option value="large">' + tl['wiki_js_large'] + '</option>'+
        '</select></div>' +
        '<div class="center"><button onmousedown="useInputForSearch(\'' + id +
            '\');"'+ 'name="submit"  class="button-box">' +
            tl['wiki_js_submit'] + '</button> &nbsp;' +
        '<button onmousedown="toggleDisplay(\'wiki-popup-prompt-' + id +
        '\')" name="close"  class="button-box">' + tl['wiki_js_cancel'] +
        '</button></form></div></div>';
    search_elt.innerHTML = search_form;
    return search_elt;
}
/*
 * Sets up keyboard events for a wiki textarea after it is in focus
 * @param String id identifier of the textarea used to handle keyboard events
 *      for
 */
function enableKBShortcuts(id)
{
    var ctrl_down = false;
    document.onkeyup = function keyUp(e)
    {
        if (e.which == 17) ctrl_down = false;
    };
    document.onkeydown = function keyDown(e)
    {
        if (e.which == 17) ctrl_down = true;
        if (e.which == 66 && ctrl_down == true) {
            wikifySelection('wikibtn-bold', id);
            return false;
        }
        if (e.which == 73 && ctrl_down == true) {
            wikifySelection('wikibtn-italic', id);
            return false;
        }
        if (e.which == 85 && ctrl_down == true) {
            wikifySelection('wikibtn-underline', id);
            return false;
        }
    };
}
/*
 * Used to add the wiki text for including a resource into a wiki page
 * @param String id identifier of the textarea used to insert wiki text
 */
function addToPage(resource_name, textarea_id)
{
    wikify("((resource:", "|" + tl['wiki_js_resource_description'] +
        resource_name + "))",
        resource_name, textarea_id);
}
