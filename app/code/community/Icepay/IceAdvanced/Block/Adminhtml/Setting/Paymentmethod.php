<?php

/**
 *  ICEPAY Advanced - Block displays settings check
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

class Icepay_IceAdvanced_Block_Adminhtml_Setting_Paymentmethod extends Mage_Core_Block_Template {

    protected   $_template = 'icepayadvanced/paymentmethod.phtml';
    private     $viewData = null;
    private     $method = "";
    protected     $scopeID = null;
    
    private $sqlModel = null;
    
    private function useAdvancedSQL(){
        if ($this->sqlModel == null) $this->sqlModel = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced');
        return $this->sqlModel;
    }
    
    
    public function setPaymentmethod($method, $scopeID){
        $this->method = $method;
        $this->scopeID = $this->getRequest()->getParam('scope');
        $this->setViewData();
        return $this;
    }
    
    private function setViewData(){
        if ($this->viewData != null) return;

        $this->useAdvancedSQL()->setScope($this->getRequest()->getParam('scope'));

        $reference = $this->useAdvancedSQL()->getReferenceFromPMCode($this->method);
        $data = $this->useAdvancedSQL()->getConfigDataByReference($reference);
        $this->viewData = new stdClass();
        $this->viewData->module   = $reference;
        $this->viewData->config   = $data['config'];
        $this->viewData->issuers  = $data['issuers'];

        $this->viewData->countryCollection = Mage::getModel('adminhtml/system_config_source_country')->toOptionArray(true);
    }
    
    public function getViewData(){
        return $this->viewData;
    }

}