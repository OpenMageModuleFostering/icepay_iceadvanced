<?php

/**
 *  ICEPAY Advanced - Process payment
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
class Icepay_IceAdvanced_ProcessingController extends Mage_Core_Controller_Front_Action {

    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function payAction()
    {
        $pay = Mage::getModel('iceadvanced/pay');
        $result = $pay->getCheckoutResult();

        if (!is_array($result)) {
            Mage::getSingleton('checkout/session')->setErrorMessage(sprintf($this->__("The payment provider has returned the following error message: %s"), $result));
            parent::_redirect('checkout/onepage/failure');
        } else {
            $checkoutResult = (isset($result['CheckoutExtendedResult'])) ? $result['CheckoutExtendedResult'] : $result['CheckoutResult'];

            $this->getResponse()->setBody($this->__("Redirecting"))->setRedirect($checkoutResult->PaymentScreenURL);
        }
    }

}
