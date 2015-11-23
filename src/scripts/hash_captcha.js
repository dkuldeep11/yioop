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
 * Finds the nonce for the input parameters given by the server.
 *
 * @param Object nonce_for_string a DOM element to put the value of a nonce
 * @param Object random_string a DOM element to get the value of random_string
 * @param Object time a DOM element to get the value of the time
 * @param Object level a DOM element to get the value of a level
 */
function findNonce(nonce_for_string, random_string, time, level)
{
    if (elt(random_string).value) {
        var random_string = elt(random_string).value;
        var level = elt(level).value;
        var time = elt(time).value;
        var nonce = hashStamp(random_string, time, level);
        var input = elt(nonce_for_string);
        input.setAttribute('value', nonce);
        input.setAttribute('type', 'hidden');
    }
}
/*
 * This function calculates the sha1 of a string until
 * number of a leading zeroes in the sha1 value matchesa level
 * parameter.
 *
 * @param String random_string a string sent by the server
 * @param String time the time sent by the server
 * @param String level define number of leading zeroes
 * @return int nonce for which the sha1 of a string
 *     produces the level number of a zeroes
 */
function hashStamp(random_string, time, level)
{
    var nonce = -1;
    var input_string = '';
    var pattern = new RegExp("^0{"+level+"}");
    while (!generateSha1(input_string).match(pattern)) {
        nonce = nonce + 1;
        input_string = random_string +':'+time + ':' + nonce;
    }
    return nonce;
}
