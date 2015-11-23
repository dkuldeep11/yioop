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
 * @author Mallika Perepa
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015s
 * @filesource
 */

/*
 * The alphabet for this locale
 */
var alpha =
"अआइईउऊऋए" +
"ऐओऔअंअँकख" +
"गघङचछजझञट" +
"ठडढणतथदधनप" +
"फबभमयरलव" +
"शषसहर्हर्" +
"सर्मर्नर्जर्दर्टर्"+
"तब्रह्रग्रद्र" +
"ज्रड्रप्रर्रक्र" +
"त्रच्रट्रन्रन्" +
"रव्रल्रस्रय्र" +
"ब्नह्नग्नद्नप्" +
"नर्नक्नत्नम्नव्"+
"नस्नक्कट्"+
"टठ्ठत्तन्नड्ड" +
"द्दटमट्मसससक" +
"स्सस्कक्षत्"+
"रज्ञश्रऋा"+
"िीोौुूेैंकाकि"+
"कीकुकूकेक"+
"ैकोकौकंकः"+
"खखाखिखीख"+
"ुखूखेखैखो"+
"खौखंखःगगाग"+
"िगीगुगूगेगैगो"+
"गौगंगःघघाघिघ"+
"ीघुघूघेघैघ"+
"ोघौघंघःचच"+
"ाचिचीचुचूच"+
"ेचैचोचौचंचःछ"+
"छाछिछीछुछू"+
"छेछैछोछौछं" +
"छःजाजिजीजु" +
"जूजेजैजोजौ" +
"जंजःझाझिझीझ" +
"ुझूझेझैझो" +
"झौझंझःटाटिटी"+
"टुटूटेटैटोटौ"+
"टंटःठाठिठीठु" +
"ठूठेठैठोठौठ" +
"ंठःडाडिडीडुड" +
"ूडेडैडोडौडं" +
"डःढाढिढीढुढू" +
"ढेढैढोढौ" +
"ढंढःणाण"+
"िणीणुणूण" +
"ेणैणोणौणंण" +
"ःतातितीतुतूत" +
"ेतैतोतौतंतः" +
"थाथिथीथुथूथे" +
"थैथोथौथंथःदाद" +
"िदीदुदूदे" +
"दैदोदौदंदः"+
"धाधिधीधुधूध" +
"ेधैधोधौधंध" +
"ःनानिनीनुनूनेनै" +
"नोनौनंनःपापिपीपु" +
"पूपेपैपोपौपंप" +
"ःफाफिफीफु" +
"फूफेफैफो" +
"फौफंफः"+
"बाबिबीबुबूबे" +
"बैबोबौबंबःभा" +
"भिभीभुभूभेभ" +
"ैभोभौभंभःमाम" +
"िमीमुमूमेमै" +
"मोमौमंमःयायि" +
"यीयुयूयेय" +
"ैयोयौयंयः"+
"रारिरीरुरूर" +
"ेरैरोरौरंरःल" +
"ालिलीलुलूल" +
"ेलैलोलौलंलःव" +
"ाविवीवुवूवेवैव" +
"ोवौवंवःक्षाक्ष"+
"िक्षीक्षुक्ष" +
"ूक्षेक्ष"+
"ैक्षोक्षौक्" +
"षंक्षःज्ञाज" +
"्ञिज्ञीज्ञु" +
"ज्ञूज्ञेज्ञ" +
"ैज्ञोज्ञौज्" +
"ञंज्ञःस्वस्न" +
"स्थस्तस्टस्ज" +
"स्कष्रव्रल्मल्द"+
"र्सर्यर्तर्चम" +
"्हम्बभ्रब्रब्ब" +
"फ्रफ़्तप्रन्स" +
"न्रन्तध्रद्वद्" +
"रद्धथ्रत्नण्र" +
"ण्डण्टढ्रड्रठ" +
"्रट्रट्टच्छच्च"+
"ग्यग्रग्ञख" +
"्यख्मक्यक्ल" +
"क्सक्रश्वक्ष" +
"ज्ञत्तत्रद्म" +
"द्यन्नश्चश्र";
/*
 * Transliteration maping for this locale
 */
var roman_array = {};
/*
 * To analyze the query and generate actual input query from the
 * transliterated query
 */
function analyzeQuery()
{
}
