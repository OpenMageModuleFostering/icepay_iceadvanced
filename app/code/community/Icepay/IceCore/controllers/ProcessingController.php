<?php

/**
 *  ICEPAY Core - Return process controller
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

class Icepay_IceCore_ProcessingController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        $this->_redirect('icepay/about');
    }

    public function notifyAction() {
        Mage::getModel('icecore/icepay_postback')->handle($this->getRequest()->getPost());
    }

    public function resultAction() {
        Mage::getModel('icecore/icepay_result')->handle($this->getRequest()->getQuery());
    }

}
