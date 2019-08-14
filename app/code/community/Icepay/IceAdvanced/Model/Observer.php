<?php

/**
 *  ICEPAY Advanced - Observer to save admin paymentmethods and save checkout payment
 *  @version 1.2.0
 *  @author Wouter van Tilburg
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
class Icepay_IceAdvanced_Model_Observer extends Mage_Payment_Block_Form_Container
{

    public $_currencyArr = array();
    public $_countryArr = array();
    public $_minimumAmountArr = array();
    public $_maximumAmountArr = array();
    private $_setting = array();
    private $_issuers = array();
    private $_value;
    private $_advancedSQL = null;
    private $_coreSQL = null;

    /**
     * Checks if an Icepay quote id is set, if so make the checkout session active
     * Note: This is done so cancelled orders no longer have an empty card upon returning
     * 
     * @param Varien_Event_Observer $observer
     * 
     * @since 1.2.0
     * @author Wouter van Tilburg
     * @return \Varien_Event_Observer
     */
    public function custom_quote_process(Varien_Event_Observer $observer)
    {
        $session = Mage::getSingleton('core/session');
        $quoteID = $session->getData('ic_quoteid');

        if (!is_null($quoteID)) {
            $quoteDate = $session->getData('ic_quotedate');

            $diff = abs(strtotime(date("Y-m-d H:i:s")) - strtotime($quoteDate));

            if ($diff < 360) {
                $observer['checkout_session']->setQuoteId($quoteID);
                $observer['checkout_session']->setLoadInactive(true);
            }
        }

        return $observer;
    }

    /**
     * sales_order_place_before
     * 
     * @param Varien_Event_Observer $observer
     * 
     * @since 1.2.0
     * @author Wouter van Tilburg
     * @return \Icepay_IceAdvanced_Model_Observer
     */
    public function sales_order_place_before(Varien_Event_Observer $observer)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        
        $paymentMethodCode = $quote->getPayment()->getMethodInstance()->getCode();
        $paymentMethodTitle = $quote->getPayment()->getMethodInstance()->getTitle();

        if (false === strpos($paymentMethodCode, 'icepayadv_'))
            return;

        if (strtoupper($paymentMethodTitle) == 'AFTERPAY')
            $this->initAfterpayValidation($quote);

        return $this;
    }

    /**
     * Validate additional information (Only for Afterpay)
     * 
     * @param Object $quote
     * 
     * @since 1.2.0
     * @author Wouter van Tilburg
     * @throws Mage_Payment_Model_Info_Exception
     */
    public function initAfterpayValidation($observer)
    {
        $billingAddress = $observer->getBillingAddress();
        $shippingAddress = $observer->getShippingAddress();

        $billingCountry = $billingAddress->getCountry();

        $errorMessage = false;

        // Validate postcode
        if (!Mage::Helper('iceadvanced')->validatePostCode($billingCountry, $billingAddress->getPostcode()))
            $errorMessage = Mage::helper('iceadvanced')->__('It seems your billing address is incorrect, please confirm the postal code.');

        // Validate phonenumber
        if (!Mage::Helper('iceadvanced')->validatePhonenumber($billingCountry, $billingAddress->getTelephone()))
            $errorMessage = Mage::helper('iceadvanced')->__('It seems your billing address is incorrect, please confirm the phonenumber.');

        // Validate billing streetaddress
        if (!Mage::helper('iceadvanced')->validateStreetAddress($billingAddress->getStreet()))
            $errorMessage = Mage::helper('iceadvanced')->__('It seems your billing address is incorrect, please confirm the street and housenumber.');

        // Validate shipping streetaddress
        if (!Mage::helper('iceadvanced')->validateStreetAddress($shippingAddress->getStreet()))
            $errorMessage = Mage::helper('iceadvanced')->__('It seems your shipping address is incorrect, please confirm the street and housenumber.');

        if ($errorMessage) {
            Mage::getSingleton('checkout/session')->addError($errorMessage);
            Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
            session_write_close();
            Mage::app()->getResponse()->sendResponse();
            exit();
        }
    }

    /* Save order */

    public function sales_order_payment_place_end(Varien_Event_Observer $observer)
    {
        $payment = $observer->getPayment();
        $paymentMethodCode = $payment->getMethodInstance()->getCode();

        if (strpos($paymentMethodCode, 'icepayadv_') === false)
            return;

        if ($this->coreSQL()->isActive("iceadvanced"))
            $this->coreSQL()->savePayment($observer);

        return;
    }

    /* From admin */

    public function model_save_before(Varien_Event_Observer $observer)
    {
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

    private function set($setting)
    {
        $var = explode("_", $setting);
        $this->_setting = $var;
    }

    private function getValue()
    {
        return $this->_value;
    }

    private function getIssuers()
    {
        return Mage::helper("icecore")->makeArray($this->_issuers['issuers']);
    }

    private function getIssuerPMCode()
    {
        if (isset($this->_issuers['code']))
            return strtolower($this->_issuers['code']);
    }

    private function getIssuerMerchantID()
    {
        if (isset($this->_issuers['merchant']))
            return strtolower($this->_issuers['merchant']);
    }

    private function getPMCode()
    {
        return strtolower($this->_setting[1]);
    }

    private function handle($object)
    {
        $this->_value = $object->getData("value");

        if ($this->isIssuer())
            $this->_issuers = $this->advSQL()->arrDecode($this->getValue());
    }

    private function isActivate()
    {
        return ($this->_setting[2] == "active") ? true : false;
    }

    private function isTitle()
    {
        return ($this->_setting[2] == "title") ? true : false;
    }

    private function isIssuer()
    {
        return ($this->_setting[2] == "issuer") ? true : false;
    }

    protected function advSQL()
    {
        if ($this->_advancedSQL == null)
            $this->_advancedSQL = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced');
        return $this->_advancedSQL;
    }

    protected function coreSQL()
    {
        if ($this->_coreSQL == null)
            $this->_coreSQL = Mage::getSingleton('icecore/mysql4_iceCore');
        return $this->_coreSQL;
    }

    public function model_save_after(Varien_Event_Observer $observer)
    {
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
                if (!in_array($issuer->IssuerKeyword, Mage::helper("iceadvanced")->filteredPaymentMethodIssuers))
                    array_push($issuerListArr, $issuer->IssuerKeyword);
            }

            $issuerList = implode(",", $issuerListArr);

            /* Save paymentmethod through issuer data */
            $this->advSQL()->savePaymentMethod(
                    $this->getIssuerPMCode(), $this->getIssuerMerchantID(), $storeScope, $issuerList
            );


            foreach ($this->getIssuers() as $issuer) {
                if (in_array($issuer->IssuerKeyword, Mage::helper("iceadvanced")->filteredPaymentMethodIssuers))
                    continue;

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

    private function addCurrencies($arr, $currencyArr)
    {
        foreach ($currencyArr as $currency) {
            array_push($arr, trim($currency));
        }
        return $arr;
    }

}
