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
/*
 *
 */
function jslog(msg) {
    setTimeout(function() {
        throw new Error(msg);
    }, 0);
}
/*
 * Display a two second message in the message div at the top of the web page
 *
 * @param String msg  string to display
 */
function doMessage(msg)
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = msg;
    msg_timer = setInterval("undoMessage()", 2000);
}
/*
 * Undisplays the message display in the message div and clears associated
 * message display timer
 */
function undoMessage()
{
    message_tag = document.getElementById("message");
    message_tag.innerHTML = "";
    clearInterval(msg_timer);
}
/*
 * Function to set up a request object even in  older IE's
 *
 * @return Object the request object
 */
function makeRequest()
{
    try {
        request = new XMLHttpRequest();
    } catch (e) {
        try {
            request = new ActiveXObject('MSXML2.XMLHTTP');
        } catch (e) {
            try {
            request = new ActiveXObject('Microsoft.XMLHTTP');
            } catch (e) {
            return false;
            }
        }
    }
    return request;
}
/*
 * Make an AJAX request for a url and put the results as inner HTML of a tag
 * If the response is the empty string then the tag is not replaced
 *
 * @param Object tag  a DOM element to put the results of the AJAX request
 * @param String url  web page to fetch using AJAX
 */
function getPage(tag, url)
{
    var request = makeRequest();
    if (request) {
        var self = this;
        request.onreadystatechange = function()
        {
            if (self.request.readyState == 4) {
                tag.innerHTML = self.request.responseText;
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}
/*
 * Returns the position of the caret within a node
 *
 * @param String input type element
 */
function caret(node)
{
    if (node.selectionStart) {
        return node.selectionStart;
    } else if (!document.selection) {
        return false;
    }
    // old ie hack
    var insert_char = "\001",
    sel = document.selection.createRange(),
    dul = sel.duplicate(),
    len = 0;

    dul.moveToElementText(node);
    sel.text = insert_char;
    len = dul.text.indexOf(insert_char);
    sel.moveStart('character',-1);
    sel.text = "";
    return len;
}
/*
 * Shorthand for document.createElement()
 *
 * @param String name tag name of element desired
 * @return Element the create element
 */
function ce(name)
{
    return document.createElement(name);
}
/*
 * Shorthand for document.getElementById()
 *
 * @param String id  the id of the DOM element one wants
 */
function elt(id)
{
    return document.getElementById(id);
}
/*
 * Shorthand for document.getElementsByTagName()
 *
 * @param String name the name of the DOM element one wants
 */
function tag(name)
{
    return document.getElementsByTagName(name);
}
/*
 *
 * @param object object
 * @param String event_type
 * @param Function handler
 */
function listen(object, event_type, handler)
{
    object.addEventListener(event_type, handler, false);
}
/*
 *
 */
function updateCharCountdown(text_field_id, display_box_id)
{
    text_field = elt(text_field_id);
    display_box = elt(display_box_id);
    if(typeof text_field.maxLength != 'undefined' && display_box) {
        display_box.innerHTML = text_field.maxLength - text_field.value.length;
    }
} 
/*
 * Global used by initializeFileHandler fileUploadSubmit to determine if
 * a submit event has already occurred
 * @var Boolean
 */
was_submitted = false;
/*
 * Global used by initializeFileHandler to keep track of all files
 * associated with a form to be uploaded. Assume only one form on a page.
 * @var Boolean
 */
file_list = new Array();
/*
 * Used to handle drag and drop file attachment and uploads on wiki and
 * group feed pages
 *
 * @param String drop_id id of element to listen for drop events
 * @param String file_id id of form file input that dropped objects
 *      will be associated with
 * @param String drop_kind what kind of element drop_id is. One of text (for
 *      textfield), textarea will add text to textarea, image
 *      (will replace image with what's dropped), or all.
 * @param Array types what file types can be upload
 * @param Boolean multiple whether multiple items dan be dropped in one go
 *      or selected from the file input picker.
 */
function initializeFileHandler(drop_id, file_id, max_size, drop_kind, types,
    multiple)
{
    var drop_elt = document.getElementById(drop_id);
    var file_elt = document.getElementById(file_id);
    var parent_form = file_elt.form;
    var last_call = "clear";
    var tl = document.tl;
    listen(parent_form, "submit", fileUploadSubmit);
    listen(drop_elt, "dragenter", stopNoPropagate);
    listen(drop_elt, "dragexit", stopNoPropagate);
    listen(drop_elt, "dragover", stopNoPropagate);
    listen(drop_elt, "drop", drop);
    listen(file_elt, "change",
        function(event)
        {
            if (last_call != "drop") {
                checkAndSetFile(file_elt.files);
            }
            last_call = "clear";
        }
    );
    function fileUploadSubmit(event)
    {
        stopNoPropagate(event);
        if (was_submitted) {
            return;
        }
        was_submitted = true;
        var form_data = new FormData();
        var form_elements = parent_form.elements;
        var k = 0;
        for (var i = 0; i <  form_elements.length; i++) {
            var element = form_elements[i];
            if (element.type == "file") {
                var name = element.name;
                if (file_list[name] === undefined) {
                    continue;
                }
                if (file_list[name].length == 1 &&
                    file_list[name][0].length == 1) {
                    form_data.append(name,
                        file_list[name][0][0]);
                        k++;
                } else {
                    for (var j = 0; j < file_list[name].length; j++) {
                        for (var m = 0; m < file_list[name][j].length; m++) {
                            form_data.append(name + "[" + k + "]",
                                file_list[name][j][m]);
                            k++;
                        }
                    }
                }
            } else if (element.type == "checkbox") {
                if (element.checked) {
                    form_data.append(element.name, element.value);
                }
            } else {
                form_data.append(element.name, element.value);
            }
        }
        var request = new XMLHttpRequest();
        if (k > 0) {
            request.upload.addEventListener("progress", uploadProgress, false);
        }
        request.addEventListener("load", uploadComplete, false);
        request.addEventListener("error", uploadFailed, false);
        request.addEventListener("abort", uploadCanceled, false);
        //keep ie happy
        var submit_to = (parent_form.action) ? parent_form.action :
            document.location;
        request.open("post", submit_to);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.send(form_data);
    }
    function drop(event)
    {
        stopNoPropagate(event);
        var files = event.dataTransfer.files;
        var count = files.length;
        if (count > 0) {
            last_call = "drop";
            checkAndSetFile(files);
        }
    }
    function checkAndSetFile(files)
    {
        if (!multiple && files.length > 1) {
            doMessage('<h1 class=\"red\" >' +
                tl["basic_js_too_many_files"] + '</h1>');
            return;
        }
        for (var i = 0; i < files.length; i++) {
            if (!checkAllowedType(files[i])) {
                doMessage('<h1 class=\"red\" >' +
                    tl["basic_js_invalid_filetype"] + '</h1>');
                return;
            }
            if (max_size > 0 && files[i].size > max_size) {
                doMessage('<h1 class=\"red\" >' +
                    tl["basic_js_file_too_big"] + '</h1>');
                return;
            }
        }
        if (file_list[file_elt.name] === undefined) {
            file_list[file_elt.name] = new Array();
        }
        if (multiple) {
            file_list[file_elt.name][file_list[file_elt.name].length] = files;
        } else {
            file_list[file_elt.name][0] = files;
        }
        if (drop_kind == "image") {
            var img_url = URL.createObjectURL(files[0]);
            drop_elt.src = img_url;
        } else if (drop_kind == "textarea") {
            for (var j = 0; j < files.length; j++) {
                addToPage(files[j].name, drop_id);
            }
        } else if (drop_kind == "all" || drop_kind == "text") {
            if (drop_elt.innerHTML == "&nbsp;") {
                drop_elt.innerHTML = "";
            }
            for (var i = 0; i < files.length; i++) {
                var br = (drop_elt.innerHTML == "") ? "" : "<br />";
                drop_elt.innerHTML += br + files[i].name;
            }
        }
    }
    function stopNoPropagate(event)
    {
        event.stopPropagation();
        event.preventDefault();
    }
    function checkAllowedType(to_check)
    {
        if (types == null) {
            return true;
        }
        for (type in types) {
            if (to_check.type == types[type]) {
                return true;
            }
        }
        return false;
    }
    function uploadProgress(event)
    {
        var progress = elt('message');
        if (event.lengthComputable) {
            var percent_complete =
                Math.round(event.loaded * 100 / event.total);
            progress.innerHTML = '<h1 class=\"red\" >' +
                tl["basic_js_upload_progress"] +
                percent_complete.toString() + '%</h1>';
        } else {
            progress.innerHTML = '<h1 class=\"red\" >' +
                tl["basic_js_progress_meter_disabled"] +'</h1>';
        }
    }
    function uploadComplete(event)
    {
        /* This event is raised when the server sends back a response */
        if (event.target.responseText.substring(0,2) == "go") {
            window.location = event.target.responseText.substring(2);
        } else {
            document.open();
            document.write(event.target.responseText);
            document.close();
        }
    }
    function uploadFailed(event)
    {
        doMessage('<h1 class=\"red\" >' +
            tl["basic_js_upload_error"] +'</h1>');
    }
    function uploadCanceled(event)
    {
        doMessage('<h1 class=\"red\" >' +
            tl["basic_js_upload_cancelled"] +'</h1>');
    }
}
/*
 * Sets whether an elt is styled as display:none or block
 *
 * @param String id  the id of the DOM element one wants
 * @param mixed value  true means display display_type false display none;
 *     anything else will display that value
 * @param mixed display_type type to set CSS display property to in the event
 *      value is true (might be block or inline, etc).
 */
function setDisplay(id, value, display_type)
{
    if (display_type === undefined){
        display_type = "block";
    }
    obj = elt(id);
    if (value == true)  {
        value = display_type;
    }
    if (value == false) {
        value = "none";
    }
    obj.style.display = value;
}
/*
 * Toggles an element between display:none and display block
 * @param String id  the id of the DOM element one wants
 */
function toggleDisplay(id, display_type)
{
    if (display_type === undefined){
        display_type = "block";
    }
    obj = elt(id);
    if (obj.style.display == display_type)  {
        value = "none";
    } else {
        value = display_type;
    }
    obj.style.display = value;
}
/*
 * Make an AJAX request for a url
 *
 * @param String url  web page to fetch using AJAX
 */
function getPageWithMessage(url)
{
    var request = makeRequest();
    if (request) {
        var self = this;
        request.onreadystatechange = function()
        {
            if (self.request.readyState == 4) {
                 doMessage(self.request.responseText);
            }
        }
        request.open("GET", url, true);
        request.send();
    }
}

