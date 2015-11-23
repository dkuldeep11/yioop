/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2015    Chris Pollett chris@pollett.org
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.    If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Akash Patel (edited by Chris Pollett chris@pollett.org)
 *     Ideas adapted Leemon Baird's bigint.js
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
/*
 * Constant for number of bits per element
 */
var bits_per_element = 15;
/*
 * Constant to mask the overflow value
 */
var mask = 32767;
/*
 * Constant to define radix
 */
var radix = mask + 1;
/*
 * To add BigInt and int number
 *
 * @param BigInt x first operand
 * @param int n second operand
 * @return BigInt result
 */
function addInteger(x, n)
{
    var new_x = expandBigInt(x, x.length + 1);
    addBigIntToInt(new_x, n);
    return trimBigInt(new_x, 1);
}
/*
 * Returns the BigInt with given number of leading zeroes
 *
 * @param BigInt x input number
 * @param int k expected number of leading zeroes
 * @return BigInt result
 */
function trimBigInt(x, k)
{
    var i = x.length;
    while(i > 0 && !x[i - 1]) {
        i--;
    }
    var y = new Array(i + k);
    copyBigIntFromBigInt(y, x);
    return y;
}
/*
 * Expands a BigInt to at least an n element array,
 * adding zeroes if needed
 *
 * @param BigInt x first operand
 * @param int n expected number of elements
 * @return BigInt result
 */
function expandBigInt(x, n)
{
    var bits = (x.length > n ? x.length : n) * bits_per_element;
    var result = int2BigInt(0, bits, 0);
    copyBigIntFromBigInt(result, x);
    return result;
}
/*
 * Converts a normal int to BigInt. It pads the array with leading zeros so
 * that it has at least minSize elements
 *
 * @param int t input number
 * @param int bits expected number of bits
 * @param int min_size minimum size of the BigInt.
 * @return Array stores the BigInt in bits_per_element-bit chunks,
 *    little endian
 */
function int2BigInt(t, bits, min_size)
{
    var size_of_array = Math.max(
        Math.ceil(bits / bits_per_element) + 1, min_size);
    var buffer = new Array(size_of_array);
    copyBigIntFromInt(buffer, t);
    return buffer;
}
/*
 * Copies one BigInt to another BigInt
 * x must be an array at least as big as y
 *
 * @param BigInt x input number
 * @param BigInt bits expected number of bits
 * @return BigInt result
 */
function copyBigIntFromBigInt(x, y)
{
    var len = (x.length < y.length) ? x.length : y.length;
    for (var i = 0; i < len; i++) {
        x[i] = y[i];
    }
    for (var i = len; i < x.length ; i++) {
        x[i] = 0;
    }
}
/*
 * Makes a Big Integer out of the supplied int
 *
 * @param BigInt x input number
 * @param int n input number
 * @return BigInt result
 */
function copyBigIntFromInt(x, n)
{
    var c = n;
    for (var i = 0; i < x.length; i++) {
        x[i] = c & mask;
        c >>= bits_per_element;
    }
}
/*
 * To perform x = x + n where x is a BigInt and n is an integer
 *
 * @param BigInt x input number
 * @param int n input number
 * @return BigInt result of the summation
 */
function addBigIntToInt(x, n)
{
    var i, c, b;
    x[0] += n;
    c = 0;
    for (i = 0; i < x.length; i++) {
        c += x[i];
        b = 0;
        if (c < 0) {
            b =- (c >> bits_per_element);
            c += b * radix;
        }
        x[i] = c & mask;
        c = (c >> bits_per_element) - b;
        if (!c) { return; }
    }
}
/*
 * Converts a string into a BigInt. It pads the array with leading zeros
 * so that it has at least min_size elements.
 * The array will always have at least one leading zero, unless base=-1
 * If base is less than 36 we use the digit_str below to convert (say for hex
 * or decimal numbers). Otherwise, we assume the base is 256 and just use
 * charCode
 *
 * @param String s input string
 * @param int base base of the output number
 * @param int min_size minimum size of the BigInt
 * @return BigInt
 */
function str2BigInt(s, base, min_size)
{
    var d, i, j, x, y, kk;
    var digits_str =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    var k = s.length;
    var x = int2BigInt(0, base * k, 0);

    for (i = 0; i < k; i++) {
        d = (base > 36) ? s.charCodeAt(i) :
            digits_str.indexOf(s.substring(i, i + 1), 0);
        if (base <= 36 && d >= 36) {
            d -= 26; //lower to upper
        }
        if (d >= base || d < 0) {
            break;
        }
        multInt(x, base);
        addBigIntToInt(x, d);
    }
    k = x.length;
    while(k > 0 && !x[k - 1]) {
         k--;
    }
    k = min_size > k + 1 ? min_size : k + 1;
    y = new Array(k);
    kk = k < x.length ? k : x.length;
    for (i = 0; i < kk; i++){
        y[i] = x[i];
    }
    // copy rest as 0
    for (j = i; j < k; j++) {
        y[j] = 0;
    }
    return y;
}
/*
 * Converts a BigInt into a string in a given base, from base 2 up to base 95
 *
 * @param BigInt x input number
 * @param int base base of the output number
 * @return String result
 */
function bigInt2Str(x, base)
{
    var i = "";
    var t = "";
    var s = "";
    var digits_str =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    var temp_array = new Array();
    if (temp_array.length != x.length){
        temp_array = dup(x);
    } else {
        copyBigIntFromBigInt(temp_array, x);
    }
    while (!isZero(temp_array)) {
            t = divInt(temp_array, base);    //t=s % base; s=floor(s/base);
            s = digits_str.substring(t, t + 1) + s;
        }
     if (s.length == 0){
        s = "0";
     }
    return s;
}
/*
 * Makes a copy of a BigInt
 *
 * @param BigInt x input number
 * @return BigInt buffer copy of the BigInt x
 */
function dup(x)
{
    var buffer = new Array(x.length);
    copyBigIntFromBigInt(buffer, x);
    return buffer;
}
/*
 * Checks whether BigInt is zero or not.
 * Returns 0 if the BigInt is zero otherwise return 1
 * @param BigInt x input number
 * @return int
 */
function isZero(x) {
    for (var i = 0; i < x.length; i++){
        if (x[i]) { return 0; }
    }
    return 1;
}
/*
 * Computes x = floor(x/n) for BigInt x and integer n, and
 *
 * @param BigInt x numerator
 * @param int n denomenator
 * @return int r reminder
 */
function divInt(x, n)
{
    var r = 0;
    var s;
    for (var i = x.length - 1; i >= 0; i--) {
        s = r*radix + x[i];
        x[i] = Math.floor(s/n);
        r = s % n;
    }
    return r;
}
/*
 * Multiplies BigInt with Int.
 *
 * @param BigInt x input number
 * @param int n input number
 */
function multInt(x, n)
{
    if (n == 0){
        return;
    }
    var i, carry, borrow;
    carry = 0;
    for (i = 0; i < x.length ; i++) {
        carry += x[i] * n;
        borrow = 0;
        if (carry < 0) {
            borrow =- (carry >> bits_per_element);
            carry += borrow * radix;
        }
        x[i] = carry & mask;
        carry = (carry >> bits_per_element) - borrow;
    }
}
/*
 * Performs x * y on BigInt's.
 *
 * @param BigInt x input number
 * @param BigInt y input number
 * @return BigInt ans result of multiplication
 */
function mult(x, y)
{

    var ans = expandBigInt(x, x.length + y.length);
    multEquals(ans, y);
    return trimBigInt(ans, 1);
}
/*
 * Performs x *= y (so the result is x)
 *
 * @param BigInt x input number
 * @param BigInt y input number
 */
function multEquals(x, y)
{
    var result = new Array(2 * x.length);
    copyBigIntFromInt(result, 0);
    for (var i = 0; i< y.length; i++) {
        if (y[i]) {
            linearCombShift(result, x, y[i], i);
        }
    }
    copyBigIntFromBigInt(x, result);
}
/*
 * Computes x += y*b*d^{ys}, where d is our base.
 * This correponds to one row of table needed to compute x*y
 *
 * @param BigInt x to store the result
 * @param BigInt y input number
 * @param integer b digit position in the second number
 * @param integer ys to get bit shift operator
 */
function linearCombShift(x, y, b, ys)
{
    var i, c, k;
    k = x.length < ys + y.length ? x.length : ys + y.length;
    for (c = 0, i = ys; i < k; i++) {
        c += x[i] + b * y[i - ys];
        x[i] = c & mask;
        c >>= bits_per_element;
    }
    for (i = k; c && i < x.length; i++) {
        c  += x[i];
        x[i] = c & mask;
        c >>= bits_per_element;
    }
}
/*
 * Performs BigInt divide operation
 *
 * @param BigInt x Dividend
 * @param BigInt y Divisor
 * @param BigInt q to store the quotient
 * @param BigInt r to store the reminder
 */
function divide(x, y, q, r)
{
    var kx, ky;
    var i, j, y1, y2, c, a, b;
    var tmp, out_check;
    copyBigIntFromBigInt(r, x);
    ky = y.length;
    while(y[ky - 1] == 0) { // find first non-zero position
        ky--;
    }
    b = y[ky - 1];
    a = 0;
    while(b > 0) {
        b >>= 1;
        a++;
    }
    a = bits_per_element - a;
    leftShift(y, a);
    leftShift(r, a);
    kx = r.length;
    while(r[kx - 1] == 0 && kx > ky) { // find first non-zero position
        kx--;
    }
    copyBigIntFromInt(q, 0, x.length);
    while (!greaterShift(y, r, kx - ky)) {
        subShift(r, y, kx-ky);
        q[kx - ky]++;
    }
    for (i = kx - 1; i >= ky; i--) {
        if (r[i] == y[ky-1]) {
            q[i - ky] = mask;
        } else {
            q[i - ky] = Math.floor((r[i] * radix + r[i-1]) / y[ky-1]);
        }
        while(true) {
            y2 = (ky > 1 ? y[ky - 2] : 0) * q[i-ky];
            c = y2 >> bits_per_element;
            y2 = y2 & mask;
            y1 =c + q[i-ky] * y[ky-1];
            c = y1 >> bits_per_element;
            y1 = y1 & mask;
            if (c == r[i]) {
                if (y1 == r[i - 1]) {
                    tmp = (i > 1) ? r[i - 2] : 0
                    out_check = (y2 > tmp);
                } else {
                    out_check = (y1 > r[i - 1]);
                }
            } else {
                out_check = (c > r[i]);
            }
            if (out_check) {
                q[i - ky]--;
            } else {
                break;
            }
        }
        linearCombShift(r, y, -q[i - ky], i - ky);
        if (negative(r)) {
            addShift(r, y, i - ky);
            q[i-ky]--;
        }
    }
    rightShift(y, a);
    rightShift(r, a);
}

/*
 * Performs left shift operation on BigInt by given number of bits
 *
 * @param BigInt x input number
 * @param integer n number of bits to be shifted
 */
function leftShift(x, n)
{
    var i;
    var len = Math.floor(n / bits_per_element);
    if (len) {
        for (i = x.length; i >= len; i--){
            x[i] = x[i - len];
        }
        for (;i >= 0; i--) {
            x[i] = 0;
        }
        n %= bits_per_element;
    }
    if (!n) { return; }
    for (i = x.length - 1; i > 0; i--) {
        x[i] = mask & ((x[i] << n) | (x[i-1] >> (bits_per_element - n)));
    }
    x[i] = mask & (x[i] << n);
}
/*
 * Performs right shift operation on BigInt by given number of bits
 * @param BigInt x input number
 * @param integer n number of bits to be shifted
 */
function rightShift(x, n)
{
    var i;
    var len = Math.floor(n / bits_per_element);
    if (len) {
        for (i = 0; i< x.length - len; i++) {
            x[i] = x[i + len];
        }
        for (; i< x.length; i++){
            x[i] = 0;
        }
        n %= bits_per_element;
    }
    for (i = 0; i < x.length - 1; i++) {
        x[i] = mask & ((x[i + 1] <<(bits_per_element - n)) | (x[i] >> n));
    }
    x[i] >>= n;
}
/*
 * To check whether BigInt is negative or not
 *
 * @return integer output 1 if it is negative otherwise 0
 */
function negative(x)
{
    var result = (x[x.length - 1] >> (bits_per_element - 1)) & 1;
    return result;
}
/*
 * To perfrom shift operation on y and add it to the x
 *
 * @param BigInt x first input number
 * @param BigInt y second input number
 * @return integer output 1 if it is negative otherwise 0
 */
function addShift(x, y, ys)
{
    var i, sum;
    len = x.length < ys + y.length ? x.length : ys + y.length;
    for (i = ys; i < len; i++) {
        sum += x[i] + y[i-ys];
        x[i] = sum & mask;
        sum >>= bits_per_element;
    }
    for (i = len; sum && i < x.length; i++) {
        sum += x[i];
        x[i] = sum & mask;
        sum >>= bits_per_element;
    }
}
/*
 * Right shifts the x by given number of bits and check
 * whether it is greater than y
 *
 * @param BigInt x nonnegative input number
 * @param BigInt y nonnegative input number
 * @param integer shift nonnegative integer
 * @return integer output 1 if the check passes otherwise returns 0
 */
function greaterShift(x, y, shift)
{
    var i;
    var len = ((x.length + shift) < y.length) ? (x.length + shift):y.length;
    for (i = y.length - 1 - shift; i < x.length && i >= 0; i++)
    {
        if (x[i] > 0) {
            return 1;
        }
    }
    for (i = x.length - 1 + shift; i < y.length; i++)
    {
        if (y[i] > 0) {
            return 0;
        }
    }
    for (i = len - 1; i >= shift; i--)
    {
        if (x[i-shift] > y[i]) {
            return 1;
        } else if (x[i-shift] < y[i]) {
            return 0;
        }
 }
    return 0;
}
/*
 * Left shift y by given number of bits and performs
 * subtraction operation. The result is stored in x
 *
 * @param BigInt x BigInt number
 * @param BigInt y BigInt number
 * @param integer shift number of shift bits
 */
function subShift(x, y, ys)
{
    var i, sum;
    var len = x.length < ys + y.length ? x.length : ys + y.length;
    for (i = ys; i < len; i++) {
        sum += x[i] - y[i - ys];
        x[i] = sum & mask;
        sum >>= bits_per_element;
    }
    for (i = len; sum && i < x.length; i++) {
        sum += x[i];
        x[i] = sum & mask;
        sum >>= bits_per_element;
    }
}
/*
 * Computes x mod n
 *
 * @param BigInt x argument to modr
 * @param integer n modulus
 * @return result which equals x mod n
 */
function bigMod(x, n)
{
    var ans = dup(x);
    modCalculation(ans, n);
    var result = trim(ans, 1);
    return result;
}
/*
 * Computes x mod n with the result stored in x
 *
 * @param BigInt x argument to mod
 * @param BigInt n modulus
 */
function modCalculation(x, n)
{
    var dividend = new Array(0);
    var divisor = new Array(0);
    if (dividend.length != x.length) {
        dividend = dup(x);
    } else {
        copyBigIntFromBigInt(dividend, x);
    }
    if (divisor.length != x.length) {
        divisor = dup(x);
    }
    divide(dividend, n, divisor, x);
}
/*
 * Returns x with exactly k leading zeroes
 *
 * @param BigInt x BigInt number
 * @param integer k expected number of leading zeroes in x
 * @return result result=x mod n
 */
function trim(x, k)
{
    var i, y;
    for (i= x.length; i > 0 && !x[i-1]; i--);
    y = new Array(i + k);
    copyBigIntFromBigInt(y, x);
    return y;
}
/*
 * Computes modulus x*y mod n using BigInt's
 *
 * @param BigInt x first parameter in above expression
 * @param BigInt y second parameter in above expression
 * @param BigInt n modulus
 * @return BigInt x * y mod n
 */
function multMod(x, y, n)
{
    var result = expand(x, n.length);
    multModOperation(result, y, n);
    return trim(result, 1);
}
/*
 * Computes modulus x*y mod n using BigInt's stores the result in x potential
 * leaving  the output untrimmed (this is an auxiliary method for multMod)
 *
 * @param BigInt x first parameter in above expression
 * @param BigInt y second parameter in above expression
 * @param BigInt n modulus
 * @return BigInt x * y mod n
 */
 function multModOperation(x, y, n)
 {
    var input_number = new Array(2 * x.length);
    copyBigIntFromInt(input_number, 0);
    for (var i = 0; i < y.length; i++){
        if (y[i]) {
            linearCombShift(input_number, x, y[i], i);
        }
    }
    modCalculation(input_number, n);
    copyBigIntFromBigInt(x, input_number);
}
/*
 * Expands BigInt to the given number of elements.
 * Leading zeros are added
 *
 * @param BigInt x BigInt number
 * @param integer n expected number of elements
 * @return ans x * y mod n
 */
function expand(x, n)
{
    var ans = int2BigInt(0,
        (x.length > n ? x.length : n) * bits_per_element, 0);
    copyBigIntFromBigInt(ans, x);
    return ans;
}
