<?php

/**
 *  ICEPAY Advanced - Observer to save admin paymentmethods and save checkout payment
 *  @version 1.0.0
 *  @author Olaf Abbenhuis
 *  @copyright ICEPAY <www.icepay.com>
 *  
 *  Disclaimer:
 *  The merchant is entitled to change de ICEPAY plug-in code,
 *  any changes will be at merchant's own risk.
 *  Requesting ICEPAY support for a modified plug-in will be
 *  charged in accordance with the standard ICEPAY tariffs.
 * 
 */
class Icepay_IceAdvanced_Model_Observer extends Mage_Payment_Block_Form_Container {

    public $_currencyArr = array();
    public $_countryArr = array();
    public $_minimumAmountArr = array();
    public $_maximumAmountArr = array();
    private $_setting = array();
    private $_issuers = array();
    private $_value;
    private $_advancedSQL = null;
    private $_coreSQL = null;

    public function sales_quote_collect_totals_after(Varien_Event_Observer $observer) {

        $paymentMethod = Mage::app()->getFrontController()->getRequest()->getParam('payment');

        if (!isset($paymentMethod))
            return;

        $paymentMethodCode = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
        $paymentMethodTitle = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getTitle();

        if (substr($paymentMethodCode, 0, 10) != "icepayadv_")
            return;

        if ($paymentMethodTitle != 'AfterPay')
            return;

        $message = false;

        $postCode = str_replace(' ', '', Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()->getPostcode());

        switch (Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()->getCountry()) {
            case 'NL':
                if (!preg_match('/^[1-9]{1}[0-9]{3}[A-Z]{2}$/', $postCode))
                    $message = "Your postal code is incorrect, must be in 1111AA or 1111 AA format and cannot start with a 0.";
                break;
            case 'BE':
                if (!preg_match('/^[1-9]{4}$/', $postCode))
                    $message = "Your postal code is incorrect, must be in 1111 format.";
                break;
            case 'DE':
                if (!preg_match('/^[1-9]{5}$/', $postCode))
                    $message = "Your postal code is incorrect, must be in 11111 format.";
                break;
        }

        if ($message) {
            Mage::getSingleton('checkout/session')->addError($message);
            Mage::app()->getFrontController()->getResponse()->setRedirect('checkout/cart');
            return false;
        }

        return;
    }

    /* Save the payment */

    public function sales_order_payment_place_end(Varien_Event_Observer $observer) {
        $payment = $observer->getPayment();
        $pmName = $payment->getMethodInstance()->getCode();

        if (substr($pmName, 0, 10) != "icepayadv_")
            return;

        if ($this->coreSQL()->isActive("iceadvanced"))
            $this->coreSQL()->savePayment($observer);

        return;
    }

    /* From admin */

    public function model_save_before(Varien_Event_Observer $observer) {
        /* Make sure we clear all the previously stored paymentmethods if the new total is less than stored in the database */
        $data = $observer->getEvent()->getObject();
        if ($data->getData("path") != "icecore/iceadvanced/webservice_data")
            return;
        if ($data->getData("value") != "1")
            return;
        if ($data->getData("scope") == "default" || $data->getData("scope") == "stores") {
            $storeScope = $data->getData("scope_id");
        } else
            return;
        $this->advSQL()->setScope($storeScope);
        $this->advSQL()->clearConfig();
    }

    private function set($setting) {
        $var = explode("_", $setting);
        $this->_setting = $var;
    }

    private function getValue() {
        return $this->_value;
    }

    private function getIssuers() {
        return Mage::helper("icecore")->makeArray($this->_issuers['issuers']);
    }

    private function getIssuerPMCode() {
        if (isset($this->_issuers['code']))
            return strtolower($this->_issuers['code']);
    }

    private function getIssuerMerchantID() {
        if (isset($this->_issuers['merchant']))
            return strtolower($this->_issuers['merchant']);
    }

    private function getPMCode() {
        return strtolower($this->_setting[1]);
    }

    private function handle($object) {
        $this->_value = $object->getData("value");

        if ($this->isIssuer())
            $this->_issuers = $this->advSQL()->arrDecode($this->getValue());
    }

    private function isActivate() {
        return ($this->_setting[2] == "active") ? true : false;
    }

    private function isTitle() {
        return ($this->_setting[2] == "title") ? true : false;
    }

    private function isIssuer() {
        return ($this->_setting[2] == "issuer") ? true : false;
    }

    protected function advSQL() {
        if ($this->_advancedSQL == null)
            $this->_advancedSQL = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced');
        return $this->_advancedSQL;
    }

    protected function coreSQL() {
        if ($this->_coreSQL == null)
            $this->_coreSQL = Mage::getSingleton('icecore/mysql4_iceCore');
        return $this->_coreSQL;
    }

    public function model_save_after(Varien_Event_Observer $observer) {
        $data = $observer->getEvent()->getObject();

        /* Save all the dynamic paymentmethods */
        $object = $observer->getEvent()->getObject();
        $setting = strstr($object->getData("path"), "icecore/paymentmethod/pm_");
        if (!$setting)
            return;

        // Load models
        $this->set($setting);
        $this->handle($object);

        $storeScope = null;
        // Only allow payment and issuer data at default and store level
        if ($data->getData("scope") == "default" || $data->getData("scope") == "stores") {
            $storeScope = $data->getData("scope_id");
        } else
            return;
        $this->advSQL()->setScope($storeScope);

        // Issuer data is being saved from Admin
        if ($this->isIssuer()) {

            $issuerListArr = array();
            foreach ($this->getIssuers() as $issuer) {
                array_push($issuerListArr, $issuer->IssuerKeyword);
            }
            $issuerList = implode(",", $issuerListArr);

            /* Save paymentmethod through issuer data */
            $this->advSQL()->savePaymentMethod(
                    $this->getIssuerPMCode(), $this->getIssuerMerchantID(), $storeScope, $issuerList
            );


            foreach ($this->getIssuers() as $issuer) {

                $arrCountry = array();
                $arrCurrency = array();
                $arrMinimum = array();
                $arrMaximum = array();

                foreach (Mage::helper("icecore")->makeArray($issuer->Countries->Country) as $country) {
                    array_push($arrCountry, trim($country->CountryCode));
                    array_push($arrMinimum, $country->MinimumAmount);
                    array_push($arrMaximum, $country->MaximumAmount);

                    $arrCurrency = $this->addCurrencies($arrCurrency, explode(',', $country->Currency));
                }

                $this->advSQL()->saveIssuer(
                        $storeScope, $this->getIssuerPMCode(), $this->getIssuerMerchantID(), $issuer->IssuerKeyword, $issuer->Description, $arrCountry, $arrCurrency, $this->_countryArr, $arrMinimum, $arrMaximum
                );
            };
        }

        if ($this->isTitle()) {
            $this->advSQL()->saveConfigFromAdmin($this->getPMCode(), "title", $this->getValue());
        }

        if ($this->isActivate()) {
            $this->advSQL()->saveConfigFromAdmin($this->getPMCode(), "active", $this->getValue());
        }
        return;
    }

    private function addCurrencies($arr, $currencyArr) {
        foreach ($currencyArr as $currency) {
            array_push($arr, trim($currency));
        }
        return $arr;
    }

}

?>