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
 * @author Chris Pollett
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2015
 * @filesource
 */
namespace seekquarry\yioop\views\elements;

use seekquarry\yioop as B;
use seekquarry\yioop\configs as C;
use seekquarry\yioop\Library as L;

/**
 * Element responsible for displaying Ad credits purchase form and
 * recent transaction table
 */
class ManagecreditsElement extends Element
{
    /**
     * Draws create advertisement form and existing campaigns
     * @param array $data
     */
    public function render($data)
    {
        ?>
        <div class="current-activity">
        <h2><?= tl('managecredits_element_purchase_credits') ?>
            <?= $this->view->helper("helpbutton")->render(
                "Manage Credits", $data[C\CSRF_TOKEN]) ?></h2>
        <form id="purchase-credits-form" method="post" >
            <input type="hidden" name="c" value="admin" />
            <input type="hidden" name="<?=C\CSRF_TOKEN ?>" value="<?=
                $data[C\CSRF_TOKEN] ?>" />
            <input type="hidden" name="a" value="manageCredits"/>
            <input type="hidden" name="arg" value="purchaseCredits" />
            <input type="hidden" id="credit-token"
                name="CREDIT_TOKEN" value="" />
            <table>
            <tr><th class="table-label"><label for="num-credit"><?=
                tl('managecredit_element_num_credits') ?>:
            </label></th>
            <td>
            <?php
            $this->view->helper('options')->render('num-dollars', 'NUM_DOLLARS',
                $data["AMOUNTS"], 0);
            ?>
            </td>
            </tr>
            <tr><th class="table-label"><label for="card-number"><?=
                tl('managecredit_element_card_number') ?>:
            </label></th>
            <td>
            <input class="narrow-field" id="card-number"
                type="text" size="20" <?=
                    C\CreditConfig::getAttribute('card-number','name')
                    ?>="<?=
                    C\CreditConfig::getAttribute('card-number','value')
                    ?>" />
            </td></tr>
            <tr><th class="table-label"><label for="cvc"><?=
                tl('managecredit_element_cvc') ?>:
            </label></th>
            <td>
            <input class="narrow-field" id="cvc"
                type="text" size="4" <?=
                    C\CreditConfig::getAttribute('cvc','name')?>="<?=
                    C\CreditConfig::getAttribute('cvc','value') ?>" />
            </td></tr>
            <tr><th class="table-label"><label for="expiration"><?=
                tl('managecredit_element_expiration') ?>:
            </label></th>
            <td>
            <?php
            $this->view->helper('options')->render('expiration', '',
                $data['MONTHS'], 0, false, [
                    C\CreditConfig::getAttribute('exp-month','name') =>
                    C\CreditConfig::getAttribute('exp-month','value')]);
            ?> / <?php
            $this->view->helper('options')->render('', '',
                $data['YEARS'], 0, false, [
                    C\CreditConfig::getAttribute('exp-year','name') => 
                    C\CreditConfig::getAttribute('exp-year','value')]);
            ?>
            </td></tr>
            <tr>
            <td></td>
            <td><div class="narrow-field green small"><?=
            tl('managecredits_element_charge_warning')
            ?> <a target="_blank" href="<?=B\wikiUrl(
                'ad_program_terms') ?>"><?=
            tl('managecredits_element_program_terms')
            ?></a>.</div></td>
            </tr>
            <tr>
            <td></td>
            <td class="center">
            <input class="button-box" id="purchase"
                name="PURCHASE" value="<?=
                tl('managecredits_element_purchase')
                ?>" type="submit" />
            <?php
            if (C\CreditConfig::isActive()) {
                $ad_script_found = false;
                for ($i = C\YIOOP_VERSION; $i >= C\MIN_AD_VERSION; $i++) {
                    $get_submit_purchase_script = "FN" . md5(
                        C\NAME_SERVER . C\YIOOP_VERSION .
                        "getSubmitPurchaseScript");
                    if (method_exists( C\NS_CONFIGS . "CreditConfig",
                        $get_submit_purchase_script)) {
                        $ad_script_found = true;
                        break;
                    }
                }
                if ($ad_script_found) {
                    $data['SCRIPT'] .=
                        e(C\CreditConfig::$get_submit_purchase_script());
                }
            }
            ?>
            </td>
            </tr>
            </table>
        </form>
        <h2><?=tl('managecredits_element_balance', $data['BALANCE']) ?></h2>
        <?php
            $data['TABLE_TITLE'] = tl('managecredits_element_transactions');
            $data['ACTIVITY'] = 'manageCredits';
            $data['FORM_TYPE'] = "";
            $data['NO_SEARCH'] = true;
            $data['VIEW'] = $this->view;
            $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="role-table">
            <tr>
                <th><?= tl('managecredits_element_type')?>
                </th>
                <th><?= tl('managecredits_element_amount')?>
                </th>
                <th><?= tl('managecredits_element_date')?>
                </th>
                 <th><?= tl('managecredits_element_total')?>
                </th>
            </tr>
            <?php
            foreach ($data['TRANSACTIONS'] as $tr) {
                ?>
                <tr>
                <td><?=tl($tr['TYPE']) ?></td>
                <td><?=$tr['AMOUNT'] ?></td>
                <td><?=date("r", $tr['TIMESTAMP']) ?></td>
                <td><?=$tr['BALANCE'] ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        </div>
        <?php
    }
}
