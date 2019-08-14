<?php

/**
 *  ICEPAY Advanced - Adminhtml config controller
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

class Icepay_IceAdvanced_ConfigController extends Mage_Adminhtml_Controller_Action {


    public function indexAction() {
        
        $paymentmethod = $this->getRequest()->get("paymentmethod");
        $scopeID = $this->getRequest()->get("store");
        
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock("iceadvanced/adminhtml_setting_paymentmethod")->setPaymentmethod($paymentmethod,$scopeID)->toHtml()
        );
        
    }



}
