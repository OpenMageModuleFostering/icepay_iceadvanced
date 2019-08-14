<?php

/**
 *  ICEPAY Advanced - Main Paymentmethod class
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
class Icepay_IceAdvanced_Model_Checkout_Standard extends Mage_Payment_Model_Method_Abstract {

    public $_code;
    public $_formBlockType = 'iceadvanced/payment_form_default';
    private $_issuer;
    private $_avail_issuer;
    public $_quote;
    protected $_coreSQL = null;
    protected $_advancedSQL = null;

    /**
     * Payment Method features
     * @var bool
     */
    public $_isGateway = true;
    public $_canOrder = true;
    public $_canAuthorize = false;
    public $_canCapture = false;
    public $_canCapturePartial = false;
    public $_canRefund = true;
    public $_canRefundInvoicePartial = true;
    public $_canVoid = false;
    public $_canUseInternal = true;
    public $_canUseCheckout = true;
    public $_canUseForMultishipping = true;
    public $_isInitializeNeeded = true;
    public $_canFetchTransactionInfo = false;
    public $_canReviewPayment = false;
    public $_canCreateBillingAgreement = false;
    public $_canManageRecurringProfiles = false;

    public function __construct() {
        $this->advSQL()->setScope(Mage::app()->getStore()->getID());
        if ((int) $this->advSQL()->countPaymentMethods() == 0)
            $this->advSQL()->setScope(0); //Fallback to default store
    }

    public function initialize($paymentAction, $stateObject) {
        $stateObject->setState(Mage_Sales_Model_Order::STATE_NEW);
        $stateObject->setStatus(Icepay_IceCore_Model_Config::STATUS_MAGENTO_NEW);
        $stateObject->setIsNotified(false);
    }

    public function getDescription() {
        return $this->getConfigData("info");
    }

    public function getIssuers() {
        if (!$this->_issuer)
            $this->_issuer = $this->advSQL()->getIssuersByPMReference($this->_code);
        return $this->_issuer;
    }

    public function getAvailableIssuers() {
        if (!$this->_avail_issuer)
            $this->_avail_issuer = $this->advSQL()->getAvailableIssuers($this->getConfigData("active_issuers"), $this->_code);

        return $this->_avail_issuer;
    }

    public function getIssuerOptionArray() {
        $options = array();
        foreach ($this->getAvailableIssuers() as $issuer) {
            $options[] = array('value' => $issuer['issuer_code'], 'label' => $issuer['issuer_name']);
        }

        return $options;
    }

    public function isAvailable($quote = null) {
        $this->_quote = $quote;
        if ($this->getActive() && parent::isAvailable($quote))
            return true;
        return false;
    }

    public function getActive() {
        if ($this->getConfigData("active") != 1)
            return false;
        if ($this->getConfigData("active_issuers") == "")
            return false;
        return $this->coreSQL()->isActive(Mage::helper("iceadvanced")->section);
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('iceadvanced/processing/pay');
    }

    public function setCode($str) {
        $this->_code = $str;
        return;
    }

    public function canUseForCurrency($currencyCode) {
        return (count($this->filterByCurrency($currencyCode)) > 0);
    }

    private function filterByCurrency($currencyCode) {
        $filtered_issuers = array();
        foreach ($this->getAvailableIssuers() as $issuer) {
            $currencies = unserialize($issuer['issuer_currency']);
            if (in_array($currencyCode, $currencies))
                $filtered_issuers[] = $issuer;
        }
        return $filtered_issuers;
    }

    public function canUseForCountry($country) {

        if ($this->getConfigData('allowspecific') == 1) {
            $availableCountries = explode(',', $this->getConfigData('specificcountry'));
            if (!in_array($country, $availableCountries)) {
                return false;
            }
        }

        $this->_avail_issuer = $this->filterByCountry($country);
        if (count($this->_avail_issuer) == 0)
            return false;

        return true;
    }

    private function filterByCountry($country) {
        $filtered_issuers = array();
        foreach ($this->getAvailableIssuers() as $issuer) {
            $countries = unserialize($issuer['issuer_country']);
            if (in_array($country, $countries) || in_array("00", $countries))
                $filtered_issuers[] = $issuer;
        }
        $filtered_issuers = $this->filterByAmountForCountry($filtered_issuers, $country);
        return $filtered_issuers;
    }

    public function filterByAmountForCountry($issuers, $country) {
        if (Mage::app()->getFrontController()->getRequest()->getParam('section', false) == $this->section) {
            return $issuers;
        }

        $grandTotal = Mage::getModel('checkout/cart')->getQuote()->getGrandTotal();
        $grandTotal = $grandTotal * 100;

        $filtered_issuers = array();

        foreach ($issuers as $key => $issuer) {
            $issuerMinimum = unserialize($issuer['issuer_minimum']);
            $issuerMaximum = unserialize($issuer['issuer_maximum']);
            $issuerCountry = unserialize($issuer['issuer_country']);

            $countryValues = array();

            foreach ($issuerCountry as $key => $value) {
                $countryValues[$value]['minimum'] = $issuerMinimum[$key];
                $countryValues[$value]['maximum'] = $issuerMaximum[$key];
            }

            if ((isset($countryValues['00'])) && $grandTotal < $countryValues['00']['maximum'] && $grandTotal > $countryValues['00']['minimum'])
                $filtered_issuers[] = $issuer;

            if ((isset($countryValues[$country])) && $grandTotal < $countryValues[$country]['maximum'] && $grandTotal > $countryValues[$country]['minimum'])
                $filtered_issuers[] = $issuer;
        }

        return $filtered_issuers;
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

}

?>