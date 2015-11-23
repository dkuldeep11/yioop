<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2013 - 2014 Chris Pollett chris@pollett.org
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
namespace seekquarry\yioop\library\indexing_plugins;

use seekquarry\yioop\configs as C;
use seekquarry\yioop\library as L;
use seekquarry\yioop\library\CrawlConstants;

/** Used for guessLocaleFromString */
require_once C\BASE_DIR."/library/LocaleFunctions.php";
/**
 * Used to extract emails, phone numbers, and addresses from a web page.
 * These are extracted into the EMAILS, PHONE_NUMBERS, and
 * ADDRESSES fields of the page's summary.
 *
 * @author Chris Pollett
 */
class AddressesPlugin extends IndexingPlugin implements CrawlConstants
{
    /**
     * Associative array of world countries and country code. Some
     * entries are duplicated into country's local script
     * @var array
     */
    public $countries = ["ANDORRA" => "AD","UNITED ARAB EMIRATES" => "AE",
        "AFGHANISTAN" => "AF","ANTIGUA AND BARBUDA" => "AG",
        "ANGUILLA" => "AI","ALBANIA" => "AL","ARMENIA" => "AM","ANGOLA" =>"AO",
        "ANTARCTICA" => "AQ","ARGENTINA" => "AR","AMERICAN SAMOA" => "AS",
        "AUSTRIA" => "AT","AUSTRALIA" => "AU","ARUBA" => "AW",
        "ÅLAND ISLANDS" => "AX","AZERBAIJAN" => "AZ",
        "BOSNIA AND HERZEGOVINA" => "BA","BARBADOS" => "BB",
        "BANGLADESH" => "BD","BELGIUM" => "BE","BURKINA FASO" => "BF",
        "BULGARIA" => "BG","BAHRAIN" => "BH","BURUNDI" => "BI","BENIN" => "BJ",
        "SAINT BARTHELEMY" => "BL","BERMUDA" => "BM",
        "BRUNEI DARUSSALAM" => "BN", "BOLIVIA" => "BO",
        "BONAIRE, SINT EUSTATIUS AND SABA" => "BQ", "BRAZIL" => "BR",
        "BAHAMAS" => "BS","BHUTAN" => "BT",
        "BOUVET ISLAND" => "BV","BOTSWANA" => "BW","BELARUS" => "BY",
        "BELIZE" => "BZ","CANADA" => "CA","COCOS ISLANDS" => "CC",
        "DEMOCRATIC REPUBLIC OF THE CONGO" => "CD",
        "CENTRAL AFRICAN REPUBLIC" => "CF","CONGO" => "CG",
        "SWITZERLAND" => "CH", "COTE D'IVOIRE" => "CI",
        "COOK ISLANDS" => "CK","CHILE" => "CL",
        "CAMEROON" => "CM","CHINA" => "CN", "中国" => "China",
        "COLOMBIA" => "CO",
        "COSTA RICA" => "CR", "CUBA" => "CU","CAPE VERDE" => "CV",
        "CURACAO" => "CW", "CHRISTMAS ISLAND" => "CX","CYPRUS" => "CY",
        "CZECH REPUBLIC" => "CZ", "GERMANY" => "DE","DJIBOUTI" => "DJ",
        "DENMARK" => "DK","DOMINICA" => "DM",
        "DOMINICAN REPUBLIC" => "DO","ALGERIA" => "DZ","ECUADOR" => "EC",
        "ESTONIA" => "EE","EGYPT" => "EG","WESTERN SAHARA" => "EH",
        "ERITREA" => "ER","SPAIN" => "ES","ETHIOPIA" => "ET","FINLAND" => "FI",
        "FIJI" => "FJ","FALKLAND ISLANDS (MALVINAS)" => "FK",
        "MICRONESIA, FEDERATED STATES OF" => "FM","FAROE ISLANDS" => "FO",
        "FRANCE" => "FR","GABON" => "GA","UNITED KINGDOM" => "GB",
        "GRENADA" => "GD","GEORGIA" => "GE","FRENCH GUIANA" => "GF",
        "GUERNSEY" => "GG","GHANA" => "GH","GIBRALTAR" => "GI",
        "GREENLAND" => "GL", "GAMBIA" => "GM","GUINEA" => "GN",
        "GUADELOUPE" => "GP", "EQUATORIAL GUINEA" => "GQ","GREECE" => "GR",
        "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS" => "GS",
        "GUATEMALA" => "GT", "GUAM" => "GU","GUINEA-BISSAU" => "GW",
        "GUYANA" => "GY", "HONG KONG" => "HK",
        "HEARD ISLAND AND MCDONALD ISLANDS" => "HM",
        "HONDURAS" => "HN","CROATIA" => "HR","HAITI" => "HT","HUNGARY" => "HU",
        "INDONESIA" => "ID","IRELAND" => "IE","ISRAEL" => "IL",
        "ISLE OF MAN" => "IM","INDIA" => "IN",
        "BRITISH INDIAN OCEAN TERRITORY" => "IO","IRAQ" => "IQ",
        "IRAN" => "IR","ICELAND" => "IS","ITALY" => "IT","JERSEY" => "JE",
        "JAMAICA" => "JM","JORDAN" => "JO","JAPAN" => "JP",
        "日本"=>"JA","KENYA" => "KE",
        "KYRGYZSTAN" => "KG","CAMBODIA" => "KH","KIRIBATI" => "KI",
        "COMOROS" => "KM","SAINT KITTS AND NEVIS" => "KN",
        "NORTH KOREA" => "KP","SOUTH KOREA" => "KR",
        "한국"=>"KR","KUWAIT" => "KW",
        "CAYMAN ISLANDS" => "KY","KAZAKHSTAN" => "KZ",
        "LAOS" => "LA","LEBANON" => "LB","SAINT LUCIA" => "LC",
        "LIECHTENSTEIN" => "LI","SRI LANKA" => "LK","LIBERIA" => "LR",
        "LESOTHO" => "LS","LITHUANIA" => "LT","LUXEMBOURG" => "LU",
        "LATVIA" => "LV","LIBYA" => "LY","MOROCCO" => "MA","MONACO" => "MC",
        "MOLDOVA, REPUBLIC OF" => "MD","MONTENEGRO" => "ME",
        "SAINT MARTIN" => "MF","MADAGASCAR" => "MG","MARSHALL ISLANDS" => "MH",
        "MACEDONIA, THE FORMER YUGOSLAV REPUBLIC OF" => "MK","MALI" => "ML",
        "MYANMAR" => "MM","MONGOLIA" => "MN","MACAO" => "MO",
        "NORTHERN MARIANA ISLANDS" => "MP","MARTINIQUE" => "MQ",
        "MAURITANIA" => "MR","MONTSERRAT" => "MS","MALTA" => "MT",
        "MAURITIUS" => "MU","MALDIVES" => "MV","MALAWI" => "MW",
        "MEXICO" => "MX","MALAYSIA" => "MY","MOZAMBIQUE" => "MZ",
        "NAMIBIA" => "NA","NEW CALEDONIA" => "NC","NIGER" => "NE",
        "NORFOLK ISLAND" => "NF","NIGERIA" => "NG","NICARAGUA" => "NI",
        "NETHERLANDS" => "NL","NORWAY" => "NO","NEPAL" => "NP","NAURU" => "NR",
        "NIUE" => "NU","NEW ZEALAND" => "NZ","OMAN" => "OM","PANAMA" => "PA",
        "PERU" => "PE","FRENCH POLYNESIA" => "PF","PAPUA NEW GUINEA" => "PG",
        "PHILIPPINES" => "PH","PAKISTAN" => "PK","POLAND" => "PL",
        "SAINT PIERRE AND MIQUELON" => "PM","PITCAIRN" => "PN",
        "PUERTO RICO" => "PR","PALESTINE, STATE OF" => "PS",
        "PORTUGAL" => "PT","PALAU" => "PW","PARAGUAY" => "PY",
        "QATAR" => "QA","REUNION" => "RE","ROMANIA" => "RO",
        "SERBIA" => "RS","RUSSIA" => "RU",
        "Россия"=>"RU", "RWANDA" => "RW",
        "SAUDI ARABIA" => "SA","SOLOMON ISLANDS" => "SB","SEYCHELLES" => "SC",
        "SUDAN" => "SD","SWEDEN" => "SE","SINGAPORE" => "SG",
        "SAINT HELENA, ASCENSION AND TRISTAN DA CUNHA" => "SH",
        "SLOVENIA" => "SI","SVALBARD AND JAN MAYEN" => "SJ","SLOVAKIA" => "SK",
        "SIERRA LEONE" => "SL","SAN MARINO" => "SM","SENEGAL" => "SN",
        "SOMALIA" => "SO","SURINAME" => "SR","SOUTH SUDAN" => "SS",
        "SAO TOME AND PRINCIPE" => "ST","EL SALVADOR" => "SV",
        "SINT MAARTEN" => "SX","SYRIAN ARAB REPUBLIC" => "SY",
        "SWAZILAND" => "SZ","TURKS AND CAICOS ISLANDS" => "TC","CHAD" => "TD",
        "FRENCH SOUTHERN TERRITORIES" => "TF","TOGO" => "TG","THAILAND" =>"TH",
        "TAJIKISTAN" => "TJ","TOKELAU" => "TK","TIMOR-LESTE" => "TL",
        "TURKMENISTAN" => "TM","TUNISIA" => "TN","TONGA" => "TO",
        "TURKEY" => "TR","TRINIDAD AND TOBAGO" => "TT","TUVALU" => "TV",
        "TAIWAN" => "TW", "臺灣" => "TW",
        "TANZANIA, UNITED REPUBLIC OF" => "TZ",
        "UKRAINE" => "UA","UGANDA" => "UG",
        "UNITED STATES MINOR OUTLYING ISLANDS" => "UM",
        "UNITED STATES" => "US","URUGUAY" => "UY","UZBEKISTAN" => "UZ",
        "VATICAN CITY" => "VA","SAINT VINCENT AND THE GRENADINES" => "VC",
        "VENEZUELA, BOLIVARIAN REPUBLIC OF" => "VE",
        "BRITISH VIRGIN ISLANDS" => "VG","U.S. VIRGIN ISLANDS" => "VI",
        "VIETNAM" => "VN","VANUATU" => "VU","WALLIS AND FUTUNA" => "WF",
        "SAMOA" => "WS","YEMEN" => "YE", "MAYOTTE" => "YT",
        "SOUTH AFRICA" => "ZA","ZAMBIA" => "ZM", "ZIMBABWE" => "ZW"];
    /**
     * List of common regions, abbreviations, and local spellings of
     * regions of the US, Canada, Australia, UK, as well as major cities
     * elsewhere
     * @var array
     */
    public $regions = ["ALABAMA", "AL",
        "ALASKA", "AK", "ARIZONA", "AZ", "ARKANSAS", "AR",
        "CALIFORNIA", "CA", "COLORADO", "CO", "CONNECTICUT", "CT",
        "DELAWARE", "DE", "FLORIDA", "FL", "GEORGIA", "GA",
        "HAWAII", "HI", "IDAHO", "ID", "ILLINOIS", "IL",
        "INDIANA", "IN", "IOWA", "IA", "KANSAS", "KS",
        "KENTUCKY", "KY", "LOUISIANA", "LA", "MAINE", "ME",
        "MARYLAND", "MD", "MASSACHUSETTS", "MA", "MICHIGAN", "MI",
        "MINNESOTA", "MN", "MISSISSIPPI", "MS", "MISSOURI", "MO",
        "MONTANA", "MT", "NEBRASKA", "NE", "NEVADA", "NV",
        "HAMPSHIRE", "NH", "NEW JERSEY", "NJ",
        "MEXICO", "NM", "NEW YORK", "NY", "NC",
        "NORTH DAKOTA", "ND", "OHIO", "OH", "OKLAHOMA", "OK",
        "OREGON", "OR", "PENNSYLVANIA", "PA", "RHODE", "RI",
        "CAROLINA", "SC", "DAKOTA", "SD", "TENNESSEE", "TN",
        "TEXAS", "TX", "UTAH", "UT", "VERMONT", "VT", "VIRGINIA", "VA",
        "WASHINGTON", "WA", "WV", "WISCONSIN", "WI",
        "WYOMING", "WY", "SAMOA", "AS",
        "COLUMBIA", "DC",
        "MICRONESIA", "FM", "GUAM", "GU",
        "MARSHALL", "MH", "MARIANA", "MP",
        "PALAU", "PW", "PUERTO", "RICO", "PR", "VIRGIN", "ISLANDS", "VI",
        "ALBERTA", "AB", "BRITISH", "COLUMBIA", "BC", "MANITOBA", "MB",
        "NEW BRUNSWICK", "NB", "NEWFOUNDLAND", "NL",
        "NORTHWEST", "TERRITORIES", "NT", "NOVA SCOTIA", "NS",
        "NUNAVUT", "NU", "ONTARIO", "ON", "PRINCE EDWARD ISLAND", "PE",
        "QUEBEC", "QC", "SASKATCHEWAN", "SK", "YUKON", "YT",
        "CAPITAL", "ACT", "CHRISTMAS", "CX",
        "COCOS ISLANDS", "CC", "JERVIS","BAY", "JBT",
        "SOUTH","WALES", "NSW", "NORFOLK", "NF", "NT", "QUEENSLAND", "QLD",
        "SA", "TASMANIA", "TAS", "VICTORIA", "VIC",
        "WA", "ABERDEENSHIRE", "ABD",
        "ANGLESEY", "AGY", "ALDERNEY", "ALD", "ANGUS", "ANS",
        "ANTRIM", "ANT", "ARGYLLSHIRE", "ARL",
        "ARMAGH", "ARM", "AVON", "AVN", "AYRSHIRE", "AYR",
        "BANFFSHIRE", "BAN", "BEDFORDSHIRE", "BDF",
        "BERWICKSHIRE", "BEW", "BUCKINGHAMSHIRE", "BKM",
        "BORDERS", "BOR", "BRECONSHIRE", "BRE", "BERKSHIRE", "BRK",
        "BUTE", "BUT", "CAERNARVONSHIRE", "CAE",
        "CAITHNESS", "CAI", "CAMBRIDGESHIRE", "CAM", "CARLOW", "CAR",
        "CAVAN", "CAV", "CENTRAL", "CEN", "CARDIGANSHIRE", "CGN",
        "CHESHIRE", "CHS", "CLARE", "CLA", "CLACKMANNANSHIRE", "CLK",
        "CLEVELAND", "CLV", "CUMBRIA", "CMA", "CARMARTHENSHIRE", "CMN",
        "CORNWALL", "CON", "CORK", "COR", "CUMBERLAND", "CUL",
        "CLWYD", "CWD", "DERBYSHIRE", "DBY", "DENBIGHSHIRE", "DEN",
        "DEVON", "DEV", "DYFED", "DFD", "DUMFRIES-SHIRE", "DFS",
        "DUMFRIES", "GALLOWAY", "DGY", "DUNBARTONSHIRE", "DNB",
        "DONEGAL", "DON", "DORSET", "DOR", "DOWN", "DOW",
        "DUBLIN", "DUB", "DURHAM", "DUR", "ELN",
        "ERY", "ESSEX", "ESS",
        "FERMANAGH", "FER", "FIFE", "FIF", "FLINTSHIRE", "FLN",
        "GALWAY", "GAL", "GLAMORGAN", "GLA","GLOUCESTERSHIRE", "GLS",
        "GRAMPIAN", "GMP", "GWENT", "GNT", "GUERNSEY", "GSY",
        "MANCHESTER", "GTM", "GWYNEDD", "GWN","HAMPSHIRE", "HAM",
        "HEREFORDSHIRE", "HEF", "HIGHLAND", "HLD","HERTFORDSHIRE", "HRT",
        "HUMBERSIDE", "HUM", "HUNTINGDONSHIRE", "HUN",
        "HEREFORD", "WORCESTER", "HWR", "INVERNESS-SHIRE", "INV",
        "WIGHT", "IOW", "JERSEY", "JSY","KINCARDINESHIRE", "KCD",
        "KENT", "KEN", "KERRY", "KER", "KILDARE", "KID",
        "KILKENNY", "KIK", "KIRKCUDBRIGHTSHIRE", "KKD",
        "KINROSS-SHIRE", "KRS", "LANCASHIRE", "LAN",
        "LONDONDERRY", "LDY", "LEICESTERSHIRE", "LEI",
        "LEITRIM", "LET", "LAOIS", "LEX", "LIMERICK", "LIM",
        "LINCOLNSHIRE", "LIN", "LANARKSHIRE", "LKS",
        "LONGFORD", "LOG", "LOUTH", "LOU", "LOTHIAN", "LTN",
        "MAYO", "MAY", "MEATH", "MEA", "MERIONETHSHIRE", "MER",
        "GLAMORGAN", "MGM", "MONTGOMERYSHIRE", "MGY",
        "MIDLOTHIAN", "MLN", "MONAGHAN", "MOG",
        "MONMOUTHSHIRE", "MON", "MORAYSHIRE", "MOR",
        "MERSEYSIDE", "MSY", "NAIRN", "NAI", "NORTHUMBERLAND", "NBL",
        "NORFOLK", "NFK", "NORTH RIDING OF YORKSHIRE", "NRY",
        "NORTHAMPTONSHIRE", "NTH", "NOTTINGHAMSHIRE", "NTT",
        "NYK", "OFFALY", "OFF",
        "ORKNEY", "OKI", "OXFORDSHIRE", "OXF", "PEEBLES-SHIRE", "PEE",
        "PEMBROKESHIRE", "PEM", "PERTH", "PER",
        "POWYS", "POW", "RADNORSHIRE", "RAD",
        "RENFREWSHIRE", "RFW", "ROSS", "CROMARTY", "ROC",
        "ROSCOMMON", "ROS", "ROXBURGHSHIRE", "ROX",
        "RUTLAND", "RUT", "SHROPSHIRE", "SAL", "SELKIRKSHIRE", "SEL",
        "SUFFOLK", "SFK", "GLAMORGAN", "SGM", "SHETLAND", "SHI",
        "SLIGO", "SLI", "SOMERSET", "SOM", "SARK", "SRK",
        "SURREY", "SRY", "SUSSEX", "SSX", "STRATHCLYDE", "STD",
        "STIRLINGSHIRE", "STI", "STAFFORDSHIRE", "STS",
        "SUTHERLAND", "SUT", "SUSSEX", "SXE", "SXW",
        "SYK", "TAYSIDE", "TAY",
        "TIPPERARY", "TIP", "TYNE", "TWR",
        "TYRONE", "TYR", "WARWICKSHIRE", "WAR",
        "WATERFORD", "WAT", "WESTMEATH", "WEM",
        "WESTMORLAND", "WES", "WEXFORD", "WEX",
        "WEST GLAMORGAN", "WGM", "WICKLOW", "WIC",
        "WIGTOWNSHIRE", "WIG", "WILTSHIRE", "WIL",
        "ISLES", "WIS", "LOTHIAN", "WLN",
        "WEST MIDLANDS", "WMD", "WORCESTERSHIRE", "WOR",
        "WRY", "WEST", "WYK",
        "YORKSHIRE", "YKS", "HELSINKI",
        "МОСКВА", "上海","北京","南京","成都",
        "HONG", "KONG", "TOKYO", "SEOUL", "東京","香港","서울", "MADRID",
        "BARCELONA", "ROME", "PARIS", "MARSEILLE", "TOULOUSE", "LYON",
        "ORLEAN", "BRUSSELS", "DELHI", "UTRECHT", "COPENHAGEN", "BERLIN",
        "FRANKFURT", "MÜNCHEN", "MUNICH", "VIENNA", "ISTANBUL", "ΑΘΗΝΑ",
        "ATHENS", "ПЕТЕРБУРГ", "BUENOS", "AIRES", "RIO", "JANEIRO",
        "MANILA", "深圳", "CHICAGO", "KARACHI", "BANGKOK", "LAGOS",
        "JOHANNESBERG", "FRANSCICO", "TORONTO", "MIAMI", "PHILADELPHIA",
        "KUALA", "LAMPUR", "ESSEN", "LONDON", "KINSHASA", "BOSTON",
        "AMSTERDAM", "臺北", "武漢", "AHMEDABAD", "BANGALORE", "HYDERABAD",
        "BAGHDAD", "LIMA", "名古屋", "ANGELES",
        "SANTIAGO", "MILANO", "HOUSTON",
        "SHÀNGHAISHÌ", "AP", "ANDHRA", "AR", "ARUNACHAL", "AS", "ASSAM",
        "BR", "BIHAR", "CT", "CHHATTISGARH", "GA", "GOA", "GJ", "GUJARAT",
        "HR", "HARYANA", "HP", "HIMACHAL", "JK", "JAMMU", "KASHMIR",
        "JH", "JHARKHAND", "KA", "KARNATAKA", "KL", "KERALA", "MP", "MADHYA",
        "MH", "MAHARASHTRA", "MN", "MANIPUR", "ML", "MEGHALAYA",
        "MZ", "MIZORAM", "NL", "NAGALAND", "OR", "ORISSA", "PB", "PUNJAB",
        "RJ", "RAJASTHAN", "SK", "SIKKIM",
        "TN", "TAMIL", "NADU", "TR", "TRIPURA", "UT", "UTTARAKHAND",
        "UP", "UTTAR", "PRADESH", "WB", "BENGAL", "ANDAMAN", "NICOBAR",
        "CH", "CHANDIGARH", "DN", "DADRA", "NAGAR", "HAVELI", "DD", "DAMAN",
        "DIU", "DL", "LD", "LAKSHADWEEP", "PY", "PUDUCHERRY", "PONDICHERRY",
        "澳門半島"
    ];
    /**
     * This method is called by a PageProcessor in its handle() method
     * just after it has processed a web page. This method allows
     * an indexing plugin to do additional processing on the page
     * such as adding sub-documents, before the page summary is
     * handed back to the fetcher.
     *
     * @param string $page web-page contents
     * @param string $url the url where the page contents came from,
     *    used to canonicalize relative links
     *
     * @return array consisting of a sequence of subdoc arrays found
     *     on the given page.
     */
    public function pageProcessing($page, $url)
    {
        L\crawlLog("  Addresses plugin examining page..");
        $substitutions = ['@<script[^>]*?>.*?</script>@si',
            '/\&nbsp\;|\&rdquo\;|\&ldquo\;|\&mdash\;/si',
            '@<style[^>]*?>.*?</style>@si'
        ];
        $page = preg_replace($substitutions, ' ', $page);
        $new_page = preg_replace("/\<br\s*(\/)?\s*\>/", "\n", $page);
        $changed = false;
        if ($new_page != $page) {
            $changed = true;
            $page = $new_page;
        }
        $page = preg_replace("/\<\/(h1|h2|h3|h4|h5|h6|table|tr|td|div|".
            "p|address|section)\s*\>/", "\n\n", $page);
        $page = preg_replace("/\<a/", " <a", $page);
        $page = preg_replace("/\&\#\d{3}(\d?)\;|\&\w+\;/", " ", $page);
        $page = strip_tags($page);
        if ($changed) {
            $page = preg_replace("/(\r?\n[\t| ]*){2}/", "\n", $page);
        }
        $page = preg_replace("/(\r?\n[\t| ]*)/", "\n", $page);
        $page = preg_replace("/\n\n\n+/", "\n\n", $page);
        $subdocs = [$this->parseSubdoc($page)];
        return $subdocs;
    }
    /**
     * Adjusts the document summary of a page after the page processor's
     * process method has been called so that the subdoc's fields
     * associated with the addresses plugin get copied as fields of
     * the whole page summary. Then it deletes the subdoc fields.
     *
     * @param array $summary of current document. It will be adjusted
     *     by the code below
     * @param string $url the url where the summary contents came from
     */
    public function pageSummaryProcessing(&$summary, $url)
    {
        if (isset($summary[self::SUBDOCS])) {;
            $num_subdocs = count($summary[self::SUBDOCS]);
            for ($i = 0; $i < $num_subdocs; $i++) {
                if ($summary[self::SUBDOCS][$i][self::SUBDOCTYPE]=="addresses"){
                    $summary["EMAILS"] = $summary[self::SUBDOCS][$i][
                        "EMAILS"];
                    $summary["PHONE_NUMBERS"] = $summary[self::SUBDOCS][$i][
                        "PHONE_NUMBERS"];
                    $summary["ADDRESSES"] = $summary[self::SUBDOCS][$i][
                        "ADDRESSES"];
                    unset($summary[self::SUBDOCS][$i]);
                }
            }
            $meta_ids = [];
            foreach ($summary["EMAILS"] as $email) {
                $meta_ids[] ="email:$email";
            }
            foreach ($summary["PHONE_NUMBERS"] as $phone) {
                $meta_ids[] ="phone:$phone";
            }
            $summary[self::META_WORDS] = $meta_ids;
            $summary[self::SUBDOCS] = array_values($summary[self::SUBDOCS]);
        }
    }
    /**
     * Parses EMAILS, PHONE_NUMBERS and ADDRESSES from $text and returns
     * an array with these three fields containing sub-arrays of the given
     * items
     *
     * @param string $text to use for extraction
     * @return array with found emails, phone numbers, and addresses
     */
    public function parseSubdoc($text)
    {
        $lines = explode("\n", $text);
        $lines[] = "";
        $state = "dont";
        $addresses = [];
        $current_candidate = [];
        $num_lines = 0;
        $max_len = 45;
        $min_len = 2;
        $max_lines = 8;
        $min_lines = 2;
        $emails = [];
        $phones = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line_emails = $this->parseEmails($line);
            if ($line_emails) {
                $emails = array_merge($emails , $line_emails);
            }
            $line_phones = $this->parsePhones($line);
            if ($line_phones) {
                $phones = array_merge($phones, $line_phones);
            }
            $len = mb_strlen($line);
            $len_about_right = $len < $max_len && $len >= $min_len;
            switch ($state) {
                case "dont":
                    if ($len_about_right) {
                        $state = "maybe";
                        $current_candidate[] = $line;
                        $num_lines = 1;
                    }
                break;
                case "maybe":
                    if ($len_about_right){
                        if ($num_lines < $max_lines) {
                            $current_candidate[] = $line;
                            $num_lines++;
                        } else { //too many short lines, probably not address
                            $current_candidate = [];
                            $num_lines = 0;
                            $state = "advance";
                        }
                    } else {
                        $state = "dont";
                        if ($num_lines <= $max_lines&&$num_lines >= $min_lines){
                            $current_candidate = $this->checkCandidate(
                                $current_candidate);
                            if ($current_candidate) {
                                $addresses[] = $current_candidate;
                            }
                        }
                        $current_candidate = [];
                        $num_lines = 0;
                    }
                break;
                case "advance":
                    if ($len >= $max_len || $len == 0) {
                        $state = "dont";
                    }
                break;
            }
        }
        $subdocs["EMAILS"] = array_unique($emails);
        $subdocs["PHONE_NUMBERS"] = array_unique($phones);
        $subdocs["ADDRESSES"] = $addresses;
        return $subdocs;
    }
    /**
     * Checks if the passed sequence of lines has enough features of a
     * postal address to call it an address. If so, return the address as
     * a single string
     *
     * @param array $pre_address an array of potential address lines
     * @return mixed false if not address, the lines imploded together using
     *     space if an address
     */
    public function checkCandidate($pre_address)
    {
        $address = false;
        $found_count = 0;
        $num_lines = count($pre_address);
        $check_array = ["checkCountry"=>"checkCountry",
            "checkStreet"=>"checkStreet",
            "checkPhoneOrEmail" => "checkPhoneOrEmail",
            "checkRegion" => "checkRegion",
            "checkZipPostalCodeWords" => "checkZipPostalCodeWords"];
        foreach ($pre_address as $line) {
            foreach ($check_array as $check) {
                if ($this->$check($line)) {
                    $found_count++;
                    unset($check_array[$check]);
                }
            }
        }
        if ($found_count > 1) {
            $address = implode("\n", $pre_address);
        }
        return $address;
    }
    /**
     * Used to check if a line countains a word associated with a province,
     * state or major city.
     *
     * @param string $line from address to check
     * @return bool whether it contains  acountry term
     */
    public function checkRegion($line)
    {
        $locale = L\guessLocaleFromString($line);
        $line_parts = explode(" ", $line);
        if (in_array($locale, ["zh-CN", "ja", "ko"])) {
            //Chinese, Japanese or Korean so chargram size 2.
            $line_parts = [];
            $len = mb_strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $line_parts[] = mb_substr($line, $i, 2);
            }
        }
        foreach ($line_parts as $part) {
            $part = mb_strtoupper($part);
            if (in_array($part, $this->regions)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Used to check if a line countains either an email address or a phone
     * number
     *
     * @param string $line from address to check
     * @return bool whether it contains  acountry term
     */
    public function checkPhoneOrEmail($line)
    {
        $emails = $this->parseEmails($line);
        if (isset($emails) && count($emails) > 0) {
            return true;
        }
        $phones = $this->parsePhones($line);
        if (is_array($phones) && $phones) {
            return true;
        }
        return false;
    }
    /**
     * Extracts substrings from the provided $line that are in the format
     * of an email address. Returns first email from line
     *
     * @param string $line string to extract email from
     * @return string first email found on line
     */
    public function parseEmails($line)
    {
        $email_regex =
            '/[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+'.
            '(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,3})/';
        preg_match_all($email_regex, $line, $emails);
        return $emails[0];
    }
    /**
     * Checks for a phone number related keyword in the line and if
     * found extracts digits which are presumed to be a phone number
     *
     * @param string $line to check for phone numbers
     * @return array all phone numbers detected by this method from the $line
     */
    public function parsePhones($line)
    {
        $phones = [];
        $line = preg_replace('/('.C\PUNCT.'|\s)+/',"", $line);
        $phone_keywords = "/sales|mobile|phone|call|电话|電話|fono|fone|".
            "fon|foon|전화|φωνο|фон/ui";
        $phone_parts = preg_split($phone_keywords, $line);
        if (isset($phone_parts[1])) {
            $num_parts = count($phone_parts);
            for ($i = 1; $i < $num_parts; $i++) {
                $phone_sub_parts = preg_split("/[^\d]/",$phone_parts[$i]);
                $candidate_number = trim($phone_sub_parts[0]);
                if (strlen($candidate_number) > 6 &&
                    strlen($candidate_number) < 14 &&
                    is_numeric($candidate_number)){
                    $phones[] = $candidate_number;
                }
            }
        }
        return $phones;
    }
    /**
     * Used to check if a line contains a word associated with a World
     * country or country code.
     *
     * @param string $line from address to check
     * @return bool whether it contains a country term
     */
    public function checkCountry($line)
    {
        $line_parts = explode(",", $line);
        $num_parts = count($line_parts);
        $line = mb_strtoupper(trim($line_parts[$num_parts - 1]));
        $countries = $this->countries;

        $country_codes = array_flip($countries);
        if (strlen($line) == 2) {
            $line = substr($line, 0, 2);
        }
        if (isset($country_codes[$line])) {
            return true;
        }
        if (isset($countries[$line])) {
            return true;
        }
        return false;
    }
    /**
     * Used to check if a line contains a word associated with a ZIP
     * or Postal code
     *
     * @param string $line from address to check
     * @return bool whether it contains such a code
     */
    public function checkZipPostalCodeWords($line)
    {
        $line = preg_replace("/\.|\s+/", " ", $line);
        if (preg_match("/ZIP|POSTAL|邮编/", $line)){
            return true;
        }
        return false;
    }
    /**
     * Used to check if a given line in an address candidate has features
     * associated with being a street address.
     *
     * @param string $line address line to check
     * @return bool whether or not it contains a word identified with
     *     being a street address such as WAY, AVENUE, STREET, etc.
     */
    public function checkStreet($line)
    {
        $line = preg_replace("/\.|\s+/", " ", $line);
        if (preg_match("/\b(P O BOX|PO\s+BOX|AVE|AVENUE|".
            "BOULEVARD|BLVD|SQ|SQUARE|".
            "ROAD|RD|STREET|WAY|WY|LANE|LN|RUE|ROUTE|CALLE|DR|DRIVE".
            "|通り|거리|VIA|街道|街道|".
            "STRAAT|ΟΔΟΣ|RUA|УЛИЦА)\b/ui", $line)) {
            return true;
        }
        return false;
    }
    /**
     * Returns an array of additional meta words which have been added by
     * this plugin
     *
     * @return array meta words and maximum description length of results
     *     allowed for that meta word
     */
    public static function getAdditionalMetaWords()
    {

        return ["email:" =>  100,
            "phone:" => 100];
    }
    /**
     * Which mime type page processors this plugin should do additional
     * processing for
     *
     * @return array an array of page processors
     */
    public static function getProcessors()
    {
        return ["TextProcessor"]; //will apply to all subclasses
    }
}
