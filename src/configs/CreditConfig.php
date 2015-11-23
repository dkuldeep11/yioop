<?php
/**
 * SeekQuarry/Yioop -- Credit Card Configuration
 *
 * Copyright (C) 2009 - 2015  Chris Pollett chris@pollett.org
 * All rights reserved
 */
namespace seekquarry\yioop\configs;

/**
 * Class containing methods used to handle payment processing when keyword
 * advertising is enabled.
 *
 * This class is a "blank" implementation that does not charge credit cards
 * An implementation that uses stripe.com for payment processing can be
 * obtained from seekquarry.com. Putting that implementation in the
 * APP_DIR/configs/ folder would that enable real credit card processing in
 * Yioop
 */
class CreditConfig
{
    /**
     * Returns whether a version of CreditConfig actually capable of charging
     * cards, receiving bitcoins, etc is in use.
     *
     * @return bool whether a real credit card processing class is use
     */
    public static function isActive()
    {
        return false;
    }
    /**
     * Returns the URL to the credit processing Javascript library 
     * responsible for  sending securely the credit card details to the
     * credit payment agency
     * (for example, stripe.com) then sending along a authorization token
     * as part of the form to the Yioop backend
     * @return string
     */
    public static function getCreditTokenUrl()
    {
        return "";
    }
    /**
     * Used to get field values from input tag with attribute name set to $name
     * and attribute value set to value
     * @param string $name of attribute (usually data-)
     * @param string $value value of attribute 
     * @return string field value of the correspond input tag
     */
    public static function getAttribute($name, $value)
    {
        return "data-ignore";
    }
    /**
     * Server side method that is actually responsible for charging the
     * credit card
     *
     * @param float $amount dollar amount to charge the card
     * @param string $token token issued for transaction from the card
     *      processing agency
     * @param string& $message message to use as for reason for charge
     * @return bool whether or not the charge was successful
     */
    public static function charge($amount, $token, &$message)
    {
        return true;
    }
}
