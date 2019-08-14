<?php

/**
 *  ICEPAY Advanced - Block displays payment method
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

class Icepay_IceAdvanced_Block_Payment_Form_Default extends Mage_Payment_Block_Form {

    public $_code;
    public $_issuer;
    public $_model;
    public $_countryArr = null;
    public $_country;

    protected function _construct() {
        $this->setTemplate('icepayadvanced/form/default.phtml');
        $this->model = Mage::getModel('iceadvanced/checkout_standard');
        parent::_construct();
    }

    protected function _toHtml() {
        $this->model->setCode($this->getMethodCode());
        $this->_issuer = $this->model->getIssuerOptionArray();
        Mage::dispatchEvent('payment_form_block_to_html_before', array(
                    'block' => $this
                ));
        return parent::_toHtml();
    }

    public function getConfig($config) {
        return $this->model->getConfigData($config);
    }

    public function getDescription() {
        return $this->getConfig("description");
    }

    public function getPMCode() {
        return $this->getConfig("pm_code");
    }

    public function getCountry(){
        return $this->_country;
    }

}

