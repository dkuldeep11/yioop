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
namespace seekquarry\yioop\views;

use seekquarry\yioop\configs as C;

/** For tl, getLocaleTag and Yioop constants */
require_once __DIR__.'/../library/LocaleFunctions.php';
/**
 * Translate the supplied arguments into the current locale.
 *
 * This function is a convenience copy of the same function
 * @see seekquarry\yioop\library\tl() to this subnamespace
 *
 * @param string string_identifier  identifier to be translated
 * @param mixed additional_args  used for interpolation in translated string
 * @return string  translated string
 */
function tl()
{
    return call_user_func_array(C\NS_LIB . "tl", func_get_args());
}
/**
 * shorthand for echo
 *
 * @param string $text string to send to the current output
 */
function e($text)
{
    echo $text;
}
/* Used to control if update all the locales with translations*/
if (php_sapi_name() != 'cli') {
    $locale_version = tl('view_locale_version14');
}
/**
 * Base View Class. A View is used to display
 * the output of controller activity
 *
 * @author Chris Pollett
 */
abstract class View
{
    /** The name of the type of layout object that the view is drawn on
     * @var string
     */
    public $layout = "";
    /** The reference to the layout object that the view is drawn on
     * @var object
     */
    public $layout_object;
    /**
     * Logo image text name
     * @var string
     */
    public $logo_alt_text;
    /**
     * The constructor reads in any Element and Helper subclasses which are
     * needed to draw the view. It also reads in the Layout subclass on which
     * the View will be drawn.
     *
     */
    public function __construct()
    {
        $layout_name = C\NS_LAYOUTS . ucfirst($this->layout)."Layout";
        $this->logo_alt_text = tl('view_logo_alt_text');
        $this->layout_object = new $layout_name($this);
    }
    /**
     * Dynamic loader for Element objects which might live on the current
     * View
     *
     * @param string $element name of Element to return
     */
    public function element($element)
    {
        if (!isset($this->element_instances[$element])) {
            $element_name = C\NS_ELEMENTS . ucfirst($element)."Element";
            $this->element_instances[$element] = new $element_name($this);
        }
        return $this->element_instances[$element];
    }
    /**
     * Dynamic loader for Helper objects which might live on the current
     * View
     *
     * @param string $helper name of Helper to return
     */
    public function helper($helper)
    {
        if (!isset($this->helper_instances[$helper])) {
            $helper_name = C\NS_HELPERS . ucfirst($helper)."Helper";
            $this->helper_instances[$helper] = new $helper_name();
        }
        return $this->helper_instances[$helper];
    }
    /**
     * This method is responsible for drawing both the layout and the view. It
     * should not be modified to change the display of then view. Instead,
     * implement renderView.
     *
     * @param array $data  an array of values set up by a controller to be used
     *     in rendering the view
     */
    public function render($data) {
        $this->layout_object->render($data);
    }
    /**
     * This abstract method is implemented in sub classes with code which
     * actually draws the view. The current layouts render method calls this
     * function.
     *
     * @param array $data  an array of values set up by a controller to be used
     *     in rendering the view
     */
    abstract function renderView($data);
}

