<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 *  @author Eswara Rajesh Pinapala epinapala@live.com
 *  @license http://www.gnu.org/licenses/ GPL3
 *  @link http://www.seekquarry.com/
 *  @copyright 2009 - 2015
 *  @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop\configs as C;

/**
 * This element is used to display the list of available activities
 * in the AdminView
 *
 * @author Eswara Rajesh Pinapala
 */
class HelpElement extends Element
{
    /**
     * Displays a list of admin activities
     *
     * @param array $data available activities and CSRF token
     */
    public function render($data)
    {
        ?>
        <?php
        if (C\MOBILE) {
            ?>
            <div id="mobile-help">
                <div id="help-frame" class="frame help-pane">
                    <div  id="help-close" class="float-opposite">
                        [<a class="close" onclick="toggleHelp('help-frame',
                            true,'<?php e($_REQUEST['c']);?>');return
                            false; ">X
                        </a>]
                    </div>
                    <div id="help-frame-head">
                        <h2 id="page_name" class="help-title"></h2>
                    </div>

                    <div id="help-frame-body" class="wordwrap">

                    </div>
                    <div id="help-frame-editor" class="wordwrap">

                    </div>
                </div>
            </div>
        <?php
        } else {
            $help_class_add = "";
            $help_id = "";
            if ($data['c'] != 'admin') {
                $help_class_add = "small-margin-help-pane";
                $help_id = "small-margin-help";
            } else {
                $help_id = "help";
            }
            ?>
            <div id="<?php e($help_id); ?>">
                <div id="help-frame" class="frame help-pane <?php
                e($help_class_add); ?>">
                    <div id="help-close" class="float-opposite">
                        [<a class="close" onclick="toggleHelp('help-frame',
                            false,'<?php e($_REQUEST['c']);?>');return
                            false; ">X
                        </a>]
                    </div>
                    <div id="help-frame-head">
                        <h2 id="page_name" class="help-title"></h2>
                    </div>

                    <div id="help-frame-body" class="wordwrap">

                    </div>
                    <div id="help-frame-editor" class="wordwrap">

                    </div>
                </div>
            </div>
        <?php
        }
    }
}
