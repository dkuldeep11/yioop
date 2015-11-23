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
namespace seekquarry\yioop\library;

use seekquarry\yioop\configs as C;

/** For Yioop global defines */
require_once __DIR__."/../configs/Config.php";
/**
 * Has methods to parse user-defined page rules to apply documents
 * to be indexed.
 *
 * There are two types of statements that a user can define:
 * command statements and assignment statements
 *
 * A command statement takes a key field argument for the page associative array
 * and does a function call to manipulate that page.
 * These have the syntax:
 * addMetaWords(field)       ;add the field and field value to the META_WORD
 *                          ;array for the page
 * addKeywordLink(field)     ;split the field on a comma, view this as a search
 *                          ;keywords => link text association, and add this to
 *                          ;the KEYWORD_LINKS array.
 * setStack(field)           ;set which field value should be used as a stack
 * pushStack(field)          ;add the field value for field to the top of stack
 * popStack(field)           ;pop the top of the stack into the field value for
 *                          ;field
 * setOutputFolder(dir)      ;if auxiliary output, rather than just to the
 *                          ; a yioop index, is being done, then set the folder
 *                          ; for this output to be dir
 * setOutputFormat(format)   ;format of auxiliary output either CSV or SQL
 *                          ;SQL mean that writeOutput will write an insert
 *                          ;statement
 * setOutputTable(table)     ;if output is SQL then what table to use for the
 *                          ;insert statements
 * toArray(field)            ;splits field value for field on a comma and
 *                          ;assign field value to be the resulting array
 * toString(field)           ;if field value is an array then implode that
 *                          ;array using comma and store the result in field
 *                          ;value
 * unset(field)              ;unset that field value
 * writeOutput(field)        ;use the contents of field value viewed as an array
 *                          ;to fill in the columns of a SQL insert statement
 *                          ;or CSV row
 *
 * Assignments can either be straight assignments with '=' or concatenation
 * assignments with '.='. There are the following kinds of values that one
 * can assign:
 *
 * field = some_other_field ; sets $page['field'] = $page['some_other_field']
 * field = "some_string" ; sets $page['field'] to "some string"
 * field = /some_regex/replacement_where_dollar_vars_allowed/
 *    ; computes the results of replacing matches to some_regex in
 *    ; $page['field'] with replacement_where_dollar_vars_allowed
 * field = /some_regex/g ;sets $page['field'] to the array of all matches
 *    ; of some regex in $page['field']
 *
 * For each of the above assignments we could have used ".=" instead of "="
 *
 * @author Chris Pollett
 */
class PageRuleParser implements CrawlConstants
{
    /**
     * Used to store parse trees that this parser executes
     * @var array
     */
    public $rule_trees;
    /**
     * If outputting to auxiliary file is being done, the current folder to
     * use for such output
     *
     * @var string
     */
    public $output_folder="";
    /**
     * If outputting to auxiliary file is being done, the current file format
     * to output with (either SQL or CSV)
     *
     * @var string
     */
    public $output_format="";

    /**
     * If outputting to auxiliary file is being done, and the current file
     * format is SQL then what table to output insert statements for
     *
     * @var string
     */
    public $output_table="";
    /**
     * Name of field which will be used as a stack for push and popping other
     * fields values
     *
     * @var string
     */
    public $stack;
    /**
     * Constructs a PageRuleParser using the supplied page_rules
     *
     * @param string $page_rules a sequence of lines with page rules
     *     as described in the class comments
     */
    public function __construct($page_rules = "")
    {
        $this->rule_trees = $this->parseRules($page_rules);
    }
    /**
     * Parses a string of pages rules into parse trees that can be executed
     * later
     *
     * @param string $page_rules a sequence of lines with page rules
     *     as described in the class comments
     * @return array of parse trees which can be executed in sequence
     */
    public function parseRules($page_rules)
    {
        $quote_string = '"([^"\\\\]*(\\.[^"\\\\]*)*)"';
        $blank = '[ \t]';
        $comment = $blank.'*;[^\n]*';
        $literal = '\w+';
        $assignment = '\.?=';
        $start = '(?:\A|\n)';
        $end = '(?:\n|\Z)';
        $sub_or_match_all = '(/[^/\n]+/)(g|([^/\n]*)/)';
        $command = '(\w+)'."$blank*".'\('."$blank*".'([\w\/]+)'.
            "$blank*".'\)';
        $rule =
            "@(?:$command$blank*($comment)?$end".
            "|$blank*($literal)$blank*($assignment)$blank*".
            "((".$quote_string.")|($literal)|($sub_or_match_all))".
            "$blank*($comment)?$end)@";
        $matches = [];
        preg_match_all($rule, $page_rules, $matches);
        $rule_trees = [];
        if (!isset($matches[0]) ||
            ($num_rules = count($matches[0])) == 0) { return $rule_trees; }
        for ($i = 0; $i < $num_rules; $i++) {
            $tree = [];
            if ($matches[1][$i] != "" || $matches[3][$i] != "") {
                $tree["func_call"] = $matches[1][$i];
                if (isset($matches[2][$i])) {
                    $tree["arg"] = $matches[2][$i];
                } else if (isset($matches[4][$i])) {
                    $tree["arg"] = $matches[4][$i];
                } else {
                    $tree["arg"] = "";
                }
            } else {
                $tree["var"] = $matches[4][$i];
                $tree["assign_op"] = $matches[5][$i];
                $value_type_indicator = $matches[6][$i][0];
                if ($value_type_indicator == '"') {
                    $tree["value_type"] = "string";
                    $tree["value"] = $matches[8][$i];
                } else if ($value_type_indicator == '/') {
                    if (substr($matches[6][$i],-1) == "g") {
                        $tree["value_type"] = "match_all";
                    } else {
                        $tree["value_type"] = "substitution";
                    }
                    $tree["value"] = [$matches[12][$i], $matches[13][$i]];
                } else {
                    $tree["value_type"] = "literal";
                    $tree["value"] = $matches[10][$i];
                }
            }
            $rule_trees[] = $tree;
        }
        return $rule_trees;
    }
    /**
     * Executes either the internal $rule_trees or the passed $rule_trees
     * on the provided $page_data associative array
     *
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record (will be changed by this operation)
     * @param array $rule_trees an array of annotated syntax trees to
     *     for rules used to update $page_data
     */
    public function executeRuleTrees(&$page_data, $rule_trees = null)
    {
        if ($rule_trees == null) {
            $rule_trees = & $this->rule_trees;
        }
        foreach ($rule_trees as $tree) {
            if (isset($tree['func_call'])) {
                $this->executeFunctionRule($tree, $page_data);
            } else {
                $this->executeAssignmentRule($tree, $page_data);
            }
        }
    }
    /**
     * Used to execute a single command rule on $page_data
     *
     * @param array $tree annotated syntax tree of a function call rule
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record (will be changed by this operation)
     */
    public function executeFunctionRule($tree, &$page_data)
    {
        $allowed_functions = ["addMetaWord" => "addMetaWord",
            "addKeywordLink" => "addKeywordLink",
            "setOutputFolder" => "setOutputFolder",
            "setOutputFormat" => "setOutputFormat",
            "setOutputTable" => "setOutputTable",
            "setStack" => "setStack",
            "pushStack" => "pushStack",
            "popStack" => "popStack",
            "toArray" => "toArray",
            "toString" => "toString",
            "unset" => "unsetVariable",
            "writeOutput" => "writeOutput"
        ];
        if (in_array($tree['func_call'], array_keys($allowed_functions))) {
            $func = $allowed_functions[$tree['func_call']];
            $this->$func($tree['arg'], $page_data);
        }
    }
    /**
     * Used to execute a single assignment rule on $page_data
     *
     * @param array $tree annotated syntax tree of an assignment rule
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record (will be changed by this operation)
     */
    public function executeAssignmentRule($tree, &$page_data)
    {
        $field = $this->getVarField($tree["var"]);
        if (!isset($page_data[$field])) {
            $page_data[$field] = "";
        }
        $value = "";
        switch ($tree['value_type']) {
            case "literal":
                $literal = $this->getVarField($tree["value"]);
                if (isset($page_data[$literal])) {
                    $value = $page_data[$literal];
                }
                break;
            case "string":
                $value = $tree["value"];
                break;
            case "substitution":
                $value = preg_replace($tree["value"][0], $tree["value"][1],
                    $page_data[$field]);
                break;
            case "match_all":
                preg_match_all($tree["value"][0], $tree["value"][1],
                    $page_data[$field], $value);
                break;
        }
        if ($tree["assign_op"] == "=") {
            $page_data[$field] = $value;
        } else {
            $page_data[$field] .= $value;
        }
    }
    /**
     * Either returns $var_name or the value of the CrawlConstant with name
     * $var_name.
     *
     * @param string $var_name field to look up
     * @return string looked up value
     */
    public function getVarField($var_name)
    {
        if (defined("CrawlConstants::$var_name")) {
            return constant("CrawlConstants::$var_name");
        }
        return $var_name;
    }
    /**
     * Adds a meta word u:$field:$page_data[$field_name] to the array
     * of meta words for this page
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function addMetaWord($field, &$page_data)
    {
        $field_name = $this->getVarField($field);
        if (!isset($page_data[$field_name])) {return; }
        $meta_word = "u:$field_name:{$page_data[$field_name]}";
        if (!isset($page_data[CrawlConstants::META_WORDS])) {
            $page_data[CrawlConstants::META_WORDS] = [];
        }
        $page_data[CrawlConstants::META_WORDS][] = $meta_word;
    }
    /**
     * Adds a $keywords => $link_text pair to the KEYWORD_LINKS array fro
     * this page based on the value $field on the page. The pair is extracted
     * by splitting on comma. The KEYWORD_LINKS array can be used when
     * a cached version of a page is displayed to show a list of links
     * from the cached page in the header. These links correspond to search
     * in Yioop. for example the value:
     * madonna, rock star
     * would add a link to the top of the cache page with text "rock star"
     * which when clicked would perform a Yioop search on madonna.
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function addKeywordLink($field, &$page_data)
    {
        $field_name = $this->getVarField($field);
        if (!isset($page_data[$field_name])) {return; }
        $link_parts = explode(",", $page_data[$field_name]);
        if (count($link_parts) < 2) {return; }
        list($key_words, $link_text) = $link_parts;
        if (!isset($page_data[CrawlConstants::KEYWORD_LINKS])) {
            $page_data[CrawlConstants::KEYWORD_LINKS] = [];
        }
        $page_data[CrawlConstants::KEYWORD_LINKS][$key_words] = $link_text;
    }
    /**
     * Set field variable to be used as a stack
     *
     * @param $field what field variable to use for current stack
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function setStack($field, &$page_data)
    {
        $this->stack = $this->getVarField($field);
        if (!isset($page_data[$this->stack]) ||
            (!is_string($page_data[$this->stack]) &&
            !is_array($page_data[$this->stack]) )) {
            $page_data[$this->stack] = [];
        } else if (is_string($page_data[$this->stack])) {
            $page_data[$this->stack] = [$page_data[$this->stack]];
        }
    }
    /**
     * Pushes an element or items in an array stored in field onto the current
     * stack
     *
     * @param $field what field  to get data to push onto fcurrent stack
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function pushStack($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if (!isset($page_data[$this->stack]) || !isset($page_data[$var_field])
            || (!is_string($page_data[$var_field])
            && !is_array($page_data[$var_field])) ) {
            return;
        }
        if (is_string($page_data[$var_field])) {
            $page_data[$this->stack][] = $page_data[$var_field];
        } else {
            $this->stack = array_merge($page_data[$this->stack],
                $page_data[$var_field]);
        }
    }
    /**
     * Pop an element or items in an array stored in field onto the current
     * stack
     *
     * @param $field what field  to get data to push onto fcurrent stack
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function popStack($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if (!isset($page_data[$this->stack]) ) {
            return;
        }
        $page_data[$var_field] = array_pop($page_data[$this->stack]);
    }
    /**
     * Set output folder
     *
     * @param $dir output directory in which to write data.txt files containing
     *     the contents of some fields after writeOutput commands
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function setOutputFolder($dir, &$page_data)
    {
        $this->output_folder = realpath(trim($dir));
    }
    /**
     * Set output format
     *
     * @param $format can be either csv or sql
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function setOutputFormat($format, &$page_data)
    {
        if (in_array($format, ["csv", "sql"])) {
            $this->output_format = $format;
        }
    }
    /**
     * Set output table
     *
     * @param $table table to use if output format is sql
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function setOutputTable($table, &$page_data)
    {
            $this->output_table = $table;
    }
    /**
     * If $page_data[$field] is a string, splits it into an array on comma,
     * trims leading and trailing spaces from each item and stores the result
     * back into $page_data[$field]
     *
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function toArray($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if (is_string($page_data[$var_field])) {
            $field_parts = explode(",", $page_data[$var_field]);
            $page_data[$var_field] = [];
            foreach ($field_parts as $part) {
                $page_data[$var_field][] = trim($part);
            }
        }
    }
    /**
     * If $page_data[$field] is an array, implode it into a string on comma,
     * and stores the result back into $page_data[$field]
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function toString($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if (is_array($page_data[$var_field])) {
            $page_data[$var_field] = implode(",", $page_data[$var_field]);
        }
    }
    /**
     * Unsets the key $field (or the crawl constant it corresponds to)
     * in $page_data. If it is a crawlconstant it doesn't unset it --
     * it just sets it to the empty string
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function unsetVariable($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if ($var_field == $field) {
            unset($page_data[$var_field]);
        } else {
            $page_data[$var_field] = "";
        }
    }
    /**
     * Write the value of a field to the output folder in the current
     * format. If the field is not set nothing is written
     *
     * @param $field the key in $page_data to use
     * @param array& $page_data an associative array of containing summary
     *     info of a web page/record
     */
    public function writeOutput($field, &$page_data)
    {
        $var_field = $this->getVarField($field);
        if (isset($page_data[$var_field]) && $this->output_folder) {
            $data_file = "{$this->output_folder}/data.txt";
            if (file_exists($data_file) &&
                filesize($data_file) > C\MAX_LOG_FILE_SIZE) {
                clearstatcache(); //hopefully, this doesn't slow things too much
                $data_files = glob("$data_file.*.gz");
                $num_data_files = count($data_files);
                file_put_contents("$data_file.$num_data_files.gz",
                    gzcompress(file_get_contents($data_file)));
                unlink($data_file);
            }
            $out = $page_data[$var_field];
            if (!$out) {return; }
            if (!is_array($out)) {
                $out = [$out];
            }
            $fh = fopen($data_file, "a");
            if (!$fh) {return; }
            switch ($this->output_format) {
                case 'csv':
                    fputcsv($fh, $out);
                    break;
                case 'sql':
                    if (!$this->output_table) {break; }
                    $sql = "INSERT INTO {$this->output_table} ";
                    if (isset($out[0])) {
                        $sql .= " VALUES(";
                    } else {
                        $keys = array_keys($out);
                        $sql .= '(';
                        foreach ($keys as $key) {
                            $sql .= "$comma $key";
                            $comma = ",";
                        }
                        $sql .= ') VALUES(';
                    }
                    $comma = "";
                    foreach ($out as $value) {
                        $sql .= "$comma '". addslashes($value)."'";
                        $comma = ",";
                    }
                    $sql .= ");\n";
                    fwrite($fh, $sql);
                    break;
            }
            fclose($fh);
        }
    }
}
