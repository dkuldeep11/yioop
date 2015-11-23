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
/*
 * This file Contains Javascripts used to edit Crawl Mixes
 * A crawl mix consists of a sequence of fragments. Each fragment
 * represents a number of search results to be presented. The
 * sources of these search results is the contents of the fragment.
 * These sources are a weighted sum of individual crawls and the
 * edit crawl mix page allows you to create both fragments and select
 * which individuals crawls they contain.
 */
/*
 * Used to draw all of the list of fragments of crawl results for the
 * current crawl mix
 */
function drawFragments()
{
    var fcnt = 0;
    for (key in fragments) {
        var fragment = fragments[key];
        drawFragment(fcnt, fragment['num_results']);
        var rcnt = 0;
        for (var ckey in fragment['components']) {
            var comp = fragment['components'][ckey];
            drawCrawl(fcnt, rcnt, comp[0], comp[1], comp[2], comp[3]);
            rcnt++;
        }
        fcnt++;
    }
}
/*
 * Used to erase the current rendering of crawl grouls and then draw it again
 */
function redrawFragments()
{
    var mts = elt("mix-tables");
    mts.innerHTML = "";
    drawFragments();
}
/*
 * Adds a crawl fragment to the end of the list of crawl fragments.
 *
 * @param int num_results the number of results the crawl fragment should be
 *     used for
 * @param int the maximum number of fragments one is allowed to add
 * @param String error to give if too many fragments
 */
function addFragment(num_results, max_fragments, error_message)
{
    num_fragments = fragments.length;
    if (num_fragments >= max_fragments) {
        doMessage('<h1 class=\"red\" >' + error_message + '</h1>');
        return;
    }
    fragments[num_fragments] ={};
    fragments[num_fragments]['num_results'] = num_results;
    fragments[num_fragments]['components'] = [];
    drawFragment(num_fragments, num_results)
}
/*
 * Draws a single crawl fragment within the crawl mix
 *
 * @param int fragment_num the index of fragment to draw
 * @param int num_results the number of results to this crawl fragment
 */
function drawFragment(fragment_num, num_results)
{
    var mts = elt("mix-tables");
    var tbl = document.createElement("table");
    tbl.id = "mix-table-" + fragment_num;
    tbl.className = "mixes-table top-margin";
    makeBlankMixTable(tbl, fragment_num, num_results);
    mts.appendChild(tbl);
    addCrawlHandler(fragment_num);
}
/*
 * Draw a blank crawl mix fragment, without the Javascript functions attached
 * to it
 *
 * @param Object tbl the table object to store blank mix table in
 * @param int num_fragments which fragment this table will be
 * @param int num_results number of results this crawl fragment will be used for
 */
function makeBlankMixTable(tbl, num_fragments, num_results)
{
    var tdata = "<tr><td colspan=\"2\"><label for=\"add-crawls-"+num_fragments +
        "\">"+tl['social_component_add_crawls']+"</label>"+
        drawCrawlSelect(num_fragments)+"</td><td><label for=\"num-results-"+
        num_fragments+"\">"+tl['social_component_num_results']+"</label>"+
        drawNumResultSelect(num_fragments, num_results)+
            "<td><a href=\"javascript:removeFragment(" + num_fragments + ")\">"+
            tl['social_component_del_frag']+'</a></td></tr>'+
            "<tr><th>"+tl['social_component_weight']+'</th>'+
            "<th>"+tl['social_component_name']+'</th>'+
            "<th>"+tl['social_component_add_keywords']+'</th>'+
            "<th>"+tl['social_component_actions']+"</th></tr>";
    tbl.innerHTML = tdata;
}
/*
 * Removes the ith fragment from the current crawl mix and redraws the screen
 *
 * @param int i index of fragment to delete
 */
function removeFragment(i)
{
    num_fragments = fragments.length;
    for (j = i+1; j < num_fragments; j++) {
        fragments[j - 1] = fragments[j];
    }
    delete fragments[num_fragments - 1];
    fragments.length--;
    redrawFragments();
}
/*
 * Adds the javascript needed to handle adding a crawl when the crawl
 * selection done
 *
 * @param int i the fragment to add the Javascript handler for
 */
function addCrawlHandler(i)
{
    elt("add-crawls-"+i).onchange =
        function () {
            var  ac = elt("add-crawls-"+i);
            var sel = ac.selectedIndex;
            var name = ac.options[sel].text;
            var ts = ac.options[sel].value;
            ac.selectedIndex = 0;
            addCrawl(i, ts, name, 1, "");
        }
}
/*
 * Adds a crawl to the given crawl fragment with the listed parameters
 *
 * @param int i crawl fragment to add to
 * @param int ts timestamp of crawl that is being added
 * @param String name name of crawl
 * @param float weight the crawl should ahve within fragment
 * @param String keywords  words to add to search when using this crawl
 */
function addCrawl(i, ts, name, weight, keywords)
{
    var frg = fragments[i]['components'];
    var j = frg.length;
    fragments[i]['components'][j] = [ts, name, weight, keywords];
    drawCrawl(i, j, ts, name, weight, keywords)
}
/*
 * Draws a single crawl within a crawl fragment according to the passed
 * parameters
 *
 * @param int i crawl fragment to draw to
 * @param int j index of crawl that is being added
 * @param int ts timestamp of crawl that is being drawn
 * @param String name name of crawl
 * @param float weight the crawl should ahve within fragment
 *
 */
function drawCrawl(i, j, ts, name, weight, keywords)
{
    var tr =document.createElement("tr");
    tr.id = i+"-"+j;
    elt("mix-table-"+i).appendChild(tr);
    tr.innerHTML +=
        "<td>"+drawWeightSelect(i, j, weight)+"</td><td>"+name+
        "</td><td><input type='hidden' name= \"mix[FRAGMENTS]["+i+
        "][COMPONENTS]["+j+"][CRAWL_TIMESTAMP]\"' value=\""+ts+"\" />"+
        "<input title=\""+tl['social_component_add_query']+"\" "+
        "name=\"mix[FRAGMENTS]["+i+"][COMPONENTS]["+j+"][KEYWORDS]\" "+
        "value=\""+ keywords+"\" onchange=\"updateKeywords("+i+","+j+
        ", this.value)\""+
        "class=\"widefield\"/></td><td><a href=\""+
        "javascript:removeCrawl("+i+", "+j+");\">"+
        tl['social_component_delete']+"</a></td>";
}
/*
 * Used to update the keywords of a crawl in the fragments array whenever it is
 * changed in the form.
 *
 * @param int i fragment to update keywords in
 * @param int j crawl within fragment to update
 * @param String keywords the new keywords
 */
function updateKeywords(i, j, keywords)
{
    fragments[i]['components'][j][3] = keywords;

}
/*
 * Deletes the jth crawl from the ith fragment in the current crawl mix
 *
 * @param int i fragment to delete crawl from
 * @param int j index of the crawl within the fragment to delete
 */
function removeCrawl(i, j)
{
    var frg = fragments[i]['components'];
    var len = frg.length;
    for ( k = j + 1; k < len; k++) {
        frg[k-1] = frg[k];
    }
    delete frg[len - 1];

    redrawFragments();
}
/*
 * Used to draw the select drop down to allow users to select a weighting of
 * a given crawl within a crawl fragment
 *
 * @param int i which crawl fragment the crawl belongs to
 * @param int j which crawl index within the fragment to draw this weight select
 *     for
 * @param int selected_weight the originally selected weight value
 */
function drawWeightSelect(i, j, selected_weight)
{
    var weights = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1,
        2, 3, 4, 5, 6, 7, 8, 9, 10];
    var select =
        "<select name=\'mix[FRAGMENTS]["+i+"][COMPONENTS]["+j+"][WEIGHT]\'>";
    for ( wt in weights) {
        if (weights[wt] == selected_weight) {
            val = weights[wt] + "\' selected=\'selected";
        } else {
            val = weights[wt];
        }
        select += "<option value=\'"+val+"\'>" +
            weights[wt]+"</option>";
    }
    select += "</select>";
    return select;
}
/*
 * Used to draw the select drop down to allow users to select a crawl to be
 * added to a crawl fragment
 *
 * @param int i which crawl fragment to draw this for
 */
function drawCrawlSelect(i)
{
    select = "<select id=\'add-crawls-"+i+"\' name=\'add_crawls_"+i+"\'>";
    for ( var crawl in c) {
        val = c[crawl];
        if (crawl == 0) {
            val = "0\' selected=\'selected";
        }
        select += "<option value=\'"+crawl+"\'>" + c[crawl] + "</option>";
    }
    select += "</select>";
    return select;
}
/*
 * Used to draw the select drop down to allow users to select the number
 * results a crawl fragment will be used for
 *
 * @param int i which crawl fragment this selection drop down is for
 * @param int selected_num what number of results should be initially selected
 */
function drawNumResultSelect(i, selected_num)
{
    var num_results = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 20, 30, 40, 50, 100];

    var select = "<select id=\'num-results-"+i+
        "\' name=\'mix[FRAGMENTS]["+i+"][RESULT_BOUND]\'>";
    for ( nr in num_results) {
        if (num_results[nr] == selected_num) {
            val = num_results[nr] + "\' selected=\'selected";
        } else {
            val = num_results[nr];
        }
        select += "<option value=\'"+val+"\'>" +
            num_results[nr]+"</option>";
    }
    select += "</select>";
    return select;
}
