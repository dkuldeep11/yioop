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
 * @author Sandhya Vissapragada
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
/*
 * The alphabet for this locale
 */
var alpha = "";
/*
 * Transliteration mapping for this locale
 */
var roman_array = {
    "a":"అ","aa":"ఆ","i":"ఇ","ii":"ఈ","u":"ఉ","uu":"ఊ",
    "ru":"ఋ","e":"ఎ","ee":"ఏ","ai":"ఐ","o":"ఒ","oo":"ఓ",
    "ou":"ఔ","am":"ం","aha":"అః","k":"క","kh":"ఖ","g":"గ",
    "gh":"ఘ","ch":"చ","chh":"ఛ","j":"జ","jh":"ఝ","t":"త",
    "tt":"ఠ","d":"డ","dd":"ఢ","th":"థ","dh":"ద","ddh":"ధ",
    "n":"న","p":"ప","ph":"ఫ","b":"బ","bh":"భ","m":"మ","y":"య",
    "r":"ర","l":"ల","v":"వ","s":"స","sh":"ష","h":"హ",
    "+e":"ె","+u":"ు","+uu":"ూ","+ou":"ౌ",
    "+ai":"ై","+aa":"ా","+ru":"ృ",
    "+oo":"ో","+ee":"ే","+i":"ి","+o":"ొ","*":"్"
};
/**
 * To analyze the query and generate actual input query from the
 * transliterated query
 *
 * @param String query to transliterate (if possible)
 * @return Array of transliterated characters
 */
function transliterate(query)
{
    var chunk_array = new Array();
    var cha2;
    var cha2_array = new Array();
    var len = query.length;
    var ini_chunk = true;
    for (var i=0; i < len;) {
        var cons_found = false;
        var vow_found = false;
        cnt = 0;
        vow_cnt = 0;
        cha2 = '';
        if (query.length == 1) {
            cha2 = query.trim();
            i++;
        }
        else {
            while(cons_found == false) {
                letter = query.substring(i, i + 1);
                if (!isVowel(letter)) {
                    cnt++;
                    if (cnt > 1) {
                        if (vow_found == true) {
                            cons_found = true;
                        }
                    }
                }
                else {
                    vow_found = true;
                    vow_cnt++;
                }
                if (cons_found == false) {
                    cha2 += letter;
                    i++;
                }
                if (i >= len) {
                    cons_found = true;
                }
            }

        }
        cha2_array = cha2.split("");
        if (cha2_array.length == 2 && !isVowel(cha2_array[0])
            && (cha2_array[1] == 'a')) {
            cha2 = cha2_array[0];
        }
        if (cha2_array.length == 3 && !isVowel(cha2_array[0])
            && !isVowel(cha2_array[1]) && (cha2_array[2] == 'a')) {
            cha2 = cha2_array[0] + cha2_array[1];
        }
        cha2_array = [];
        cha2_array = cha2.split("");
        if (cha2_array.length == 1){
            cha2 = cha2_array[0];
            if (roman_array[cha2] != null) {
                chunk_array.push(roman_array[cha2]);
            }
        }
        if (cha2_array.length == 2) {
            if (roman_array[cha2] != null) {
                chunk_array.push(roman_array[cha2]);
            } else if (roman_array['+' + cha2] != null) {
                chunk_array.push(roman_array['+' + cha2]);
            } else if (cha2.substring(0,1) == cha2.substring(1, 2))
            {
                var x = roman_array[cha2.substring(0,1)];
                chunk_array.push(x + roman_array['*'] + x);
            }
            else {
                for (var j = 0; j < 2; j++) {
                    cha1 = cha2.substring(j, j + 1);
                    if (roman_array['+' + cha1] != null && ini_chunk == false) {
                        chunk_array.push(roman_array['+' + cha1]);
                    } else  if (roman_array[cha1] != null) {
                        if (j == 1 && !isVowel(cha1)) {
                            chunk_array.pop();
                            chunk_array.push(roman_array[cha2.substring(0, 1)]
                            + roman_array['*'] + roman_array[cha1]);
                        }
                        else {
                            chunk_array.push(roman_array[cha1]);
                        }
                    }
                    ini_chunk = false;
                }
            }
        }
        if (cha2_array.length == 3 && vow_cnt == 2) {
            if (roman_array[cha2.substring(0, 1)] != null)
                chunk_array.push(roman_array[cha2.substring(0, 1)]);
            if (roman_array['+'+cha2.substring(1, 3)] != null)
                chunk_array.push(roman_array['+' + cha2.substring(1, 3)]);
        }
        if (cha2_array.length == 3 && vow_cnt == 1) {
            if (roman_array[cha2.substring(0, 2)] != null) {
                chunk_array.push(roman_array[cha2.substring(0, 2)]);
            } else {
                for (var j=0;j<2;j++) {
                    cha1 = cha2.substring(j, j + 1);
                    if (roman_array[cha1] != null) {
                        if (j==1 && !isVowel(cha1)) {
                            chunk_array.pop();
                            chunk_array.push(roman_array[cha2.substring(0, 1)]
                                + roman_array['*'] + roman_array[cha1]);
                        }
                        else {
                            chunk_array.push(roman_array[cha1]);
                        }
                    }
                }
            }
            if (roman_array['+' + cha2.substring(2, 3)] != null)
                chunk_array.push(roman_array['+' + cha2.substring(2, 3)]);
        }
        ini_chunk = false;
    }
    out_query = chunk_array.join().replace(/,/g,'').trim();
    return out_query;
}
