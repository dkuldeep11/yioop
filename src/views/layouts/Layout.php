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
namespace seekquarry\yioop\views\layouts;

use seekquarry\yioop\configs as C;

/** For tl, getLocaleTag and Yioop constants */
require_once __DIR__.'/../../library/LocaleFunctions.php';
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
/**
 * Base layout Class. Layouts are used to
 * render the headers and footer of the page
 * on which a View lives
 *
 * @author Chris Pollett
 */
class Layout
{
    /**
     * The view that is to be drawn on this layout
     * @var object
     */
    public $view;
    /**
     * The constructor sets the view that will be drawn inside the
     * Layout.
     *
     * @param object $v the View object that uses this layout
     */
    public function __construct($v)
    {
       $this->view = $v;
    }
    /**
     * The render method of Layout and its subclasses is responsible for drawing
     * the header of the document, calling the renderView method of the
     * View that lives on the layout and then drawing the footer of
     * the document.
     *
     * @param array $data   an array of data set up by the controller to be
     * be used in drawing the Layout and its View.
     */
    public function render($data) {
       $this->view->renderView($data);
    }
}
