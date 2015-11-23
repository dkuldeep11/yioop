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
 * @author Akash Patel (edited by Chris Pollett chris@pollett.org)
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
/*
 * Returns RSA like modulus.
 *
 * @param String id identifier of a hidden input field containing the
 *     modulus to use in the Fiat Shamir Protocol
 * @return BigInt RSA-like modulus.
 */
function getN(id)
{
    var n = elt(id).value;
    return str2BigInt(n, 10, 0);
}
/*
 * Generates Fiat shamir parameters such as x and y and append
 * with the input form
 *
 * @param Object fiat_shamir_id id of the form
 * @param String sha1 of the user password
 * @param int e random value sent by the server. Either 0 or 1.
 * @param String user_name id of the form
 * @param String modulus_id identifier of hidden field with modulus to use in
 *     Fiat Shamir
 */
function dynamicForm(zkp_form_id, sha1, e, user_name, modulus_id)
{
    var zkp_form = elt(zkp_form_id);
    var n = getN(modulus_id);
    var r = getR(n);
    var x = multMod(r, r, n);
    var y = getY(sha1, e, r, n);
    var input_x = ce('input');
    input_x.type = 'hidden';
    input_x.name = 'x';
    input_x.value = bigInt2Str(x, 10);
    var input_y = ce('input');
    input_y.type = 'hidden';
    input_y.name = 'y';
    input_y.value = bigInt2Str(y, 10);
    var input_username = ce('input');
    input_username.type = 'hidden';
    input_username.name = 'u';
    input_username.value = user_name;
    zkp_form.appendChild(input_x);
    zkp_form.appendChild(input_y);
    zkp_form.appendChild(input_username);
    zkp_form.submit();
}
/*
 * Generates Fiat shamir parameters Y. When user gives username, password
 * for the first time it stored the password on the cookie. From rest of
 * the Fiat shamir iteration it uses password stores on the client side
 * cookie.
 *
 * @param String sha1 sha1 of the password
 * @param int e random value sent by the server. Either 0 or 1.
 * @param BigInt r random value picked by the client
 * @param BigInt n RSA like modulus
 * @return BigInt y fiat-shamir parameter Y.
 */
function getY(sha1, e, r, n)
{
    var s = str2BigInt(sha1, 16, 0);
    var se;
    if (e == 0) {
        se = '1';
        se = str2BigInt(se, 10, 0);
    } else {
        se = s;
    }
    y = multMod(r, se, n);
    return y;
}
/*
 * Generates random number and converts it into BigInteger in provided range
 *
 * @param BigInt range. Random number be from 0 to range - 1
 * @return BigInteger final_r random BigInteger
 */
function getR(range)
{
    var len = range.length;
    var random_words;
    var got_r = false;
    if (window.crypto && window.crypto.getRandomValues) {
        random_words = new Int32Array(Math.floor(len/4) + 1);
        window.crypto.getRandomValues(random_words);

    } else if (window.msCrypto && window.msCrypto.getRandomValues) {
        random_words = new Int32Array(Math.floor(len/4) + 1);
        window.msCrypto.getRandomValues(random_words);
    } else {
        /*  if you're in this case you are using an old browser and someone
            might be able to fool you (still hard)
         */
        var r = "";
        var digit;
        for (var i = 0; i < len; i++) {
            digit  = Math.floor((Math.random() * 10) + 1);
            r += digit.toString();
        }
        r = str2BigInt(r, 10, 0);
        got_r = true;
    }
    if (!got_r) {
        for (var i = 0; i < random_words.length; i++) {
            r += random_words[i].toString();
        }
        r = str2BigInt(r, 256, 0);
    }
    var final_r = bigMod(r, range);
    return final_r;
}
/*
 * Generates Fiat shamir parameters such as x and y and append
 * with the input form. This method calls first time when user
 * provides user name and password
 *
 * @param Object zkp_form_id identifier of the form with zkp data
 * @param String username_id identifier of the form element with the username
 * @param String password_id identifier of the form element with the password
 * @param int e random value send by the server. Either 0 or 1.
 * @param int auth_count number of Fiat-Shamir iterations
 */
function generateKeys(zkp_form_id, username_id, password_id,
    modulus_id, e, auth_count)
{
    var password = elt(password_id).value;
    var u = elt(username_id).value;
    var token_object = elt('CSRF-TOKEN');
    var token = token_object.value;
    var token_name = token_object.name;
    var sha1 = generateSha1(password);
    var n = new getN(modulus_id);
    var auth_message = elt('auth-message').value;
    $i = 0;
    for (var i = 0; i < 2 * auth_count; i++) {
        if (i% 5 == 0) {
            auth_message += ".";
            elt('message').innerHTML = "<h1 class=\"red\" >" + auth_message +
                "</h1>";
        }
        var r = getR(n);
        var x = multMod(r, r, n);
        var y = getY(sha1, e, r, n);
        var x_string = bigInt2Str(x, 10);
        var y_string = bigInt2Str(y, 10);
        sendFiatShamirParameters(x_string, y_string, u, token, token_name, i);
        var e_temp = elt("salt-value").value;
        e_temp = e_temp + '';
        if (e_temp == 'done1') {
            e = 1;
            break;
        } else if (e_temp == 'done0') {
            e = 0;
            break;
        }
        e = parseInt(e_temp);
        if (e == -1) {
            e = 1;
            break;
        }
    }
    elt(password_id).value = null;
    if (i == 2 * auth_count) {
        doMessage("<h1 class=\"red\" >"+elt('auth-fail-message').value+"</h1>");
        return;
    }
    dynamicForm(zkp_form_id, sha1, e, u, modulus_id);
}
/*
 * Sends Fiat-Shamir via AJAX parameters and receives parameter e from server
 *
 * @param BigInt x Fiat-Shamir parameter x
 *     (@see SigninModel::checkValidSigninForZKP for details)
 * @param BigInt y Fiat-Shamir parameter y
 * @param String u username provided by user
 * @param String token CSRF token sent by the server
 * @param String token_name name to use for CSRF token
 * @param String round_num on the server this is used only to see if 0
 *     in which case it restarts the count
 */
function sendFiatShamirParameters(x, y, u, token, token_name, round_num)
{
    var http = new XMLHttpRequest();
    var url = "./";
    var params = "c=admin&x=" + x + "&y=" + y +"&u=" + u +
        "&"+token_name+"=" + token + "&round_num=" + round_num;
    http.open("post", url, false);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.onreadystatechange = function () {
        if (http.readyState == 4 && http.status == 200) {
            elt("salt-value").value = http.responseText;
        }
    }
    http.send(params);
}
/*
 * This function is used during create account module and when
 * authentication mode is ZKP.
 *
 * @param String password_id element that holds ZKP password
 * @param String repassword_id element that holds retyped ZKP password
 * @param String modulus_id element that holds fiat shamir modulus
 */
function registration(password_id, repassword_id, modulus_id)
{
    var password = elt(password_id);
    var repassword = elt(repassword_id);
    var sha1 = generateSha1(password.value);
    var x = str2BigInt(sha1, 16, 0);
    var n = getN(modulus_id);
    var z = multMod(x, x, n);
    password.value = bigInt2Str(z, 10);
    repassword.value = bigInt2Str(z, 10);
}
