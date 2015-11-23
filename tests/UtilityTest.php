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
namespace seekquarry\yioop\tests;

use seekquarry\yioop\library as L;
use seekquarry\yioop\library\UnitTest;

/**
 * Used to test the various methods in utility, in particular, those
 * related to posting lists.
 *
 * @author Chris Pollett
 */
class UtilityTest extends UnitTest
{
    /**
     * No set up being done for the time being
     */
    public function setUp()
    {
    }
    /**
     * No tear down being done for the time being
     */
    public function tearDown()
    {
    }
    /**
     * Used to check if posting lists can be properly encoded/decoded
     */
    public function postingListCodingTestCase()
    {
        $posting_list = [90, 101, 570, 581, 737, 950, 1100, 1119, 1127,
            1147, 1175, 1185, 1930, 1969, 2020, 2040, 2068, 2083, 2090, 2102,
            2126, 2170, 2182, 2191, 2217, 2228, 2250, 2260, 2370, 2392, 2403,
            2447, 2456, 2467, 2476, 2486, 2503, 2508, 2610, 2628, 2629, 2641,
            2674, 2693, 2710, 2753, 2761, 2770, 2847, 2885, 2899, 2920, 2934,
            3000, 3019, 3039, 3058, 3070, 3133, 3168, 3227, 3240, 3249, 3266,
            3277, 3296, 3309, 3327, 3348, 3366, 3368, 3375, 3424, 3456, 3458,
            3463, 3478, 3487, 3511, 3513, 3523, 3557, 3614, 3828, 3880, 3896,
            3910, 3999, 4039, 4056, 4165, 4226, 4248, 4269, 4308, 4324, 4338,
            4444, 4484, 4560, 4577, 4597, 4622, 4695, 4710, 4801, 4824, 4859,
            4876, 4981, 5071, 5109, 5131, 5199, 5232, 5270, 5287, 5317, 5330,
            5373, 5409, 5426, 5490, 5500, 5501, 5533, 5544, 5722, 5765, 5799,
            5821, 5854, 5938, 5967, 6004, 6036, 6195, 6262, 6319, 6337, 6345,
            6346, 6391, 6430, 6452, 6460, 6514, 6580, 6736, 6758, 6794, 6820,
            6976];
        $packed = L\packPosting(10, $posting_list);
        $offset = 0;
        $out_doc_list = L\unpackPosting($packed, $offset, true);
        $this->assertEqual($out_doc_list[0], 10,
            "Doc index from unpack of long packed posting equal");
        $this->assertEqual($out_doc_list[1], $posting_list,
            "Unpack of long packed posting equal");
        $posting_list = [6000, 12000, 24000];
        $packed = L\packPosting(100000, $posting_list);
        $offset = 0;
        $out_doc_list = L\unpackPosting($packed, $offset, true);
        $this->assertEqual($out_doc_list[0], 100000,
            "Bigger Doc index from unpack of long packed posting equal");
        $this->assertEqual($out_doc_list[1], $posting_list,
            "Bigger Delta unpack of posting equal");
    }
}
