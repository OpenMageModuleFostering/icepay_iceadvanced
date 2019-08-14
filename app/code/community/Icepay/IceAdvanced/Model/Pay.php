<?php

/**
 *  ICEPAY Advanced - Start payment
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

class Icepay_IceAdvanced_Model_Pay extends Mage_Payment_Model_Method_Abstract {


    protected $sqlModel;

    public function __construct() {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
    }

    public function getCheckoutResult(){

        $session = $this->getCheckout();
        $icedata = $this->sqlModel->loadPaymentByID( $session->getLastRealOrderId() );

        $paymentData = unserialize(urldecode($icedata["transaction_data"]));
        $webservice = Mage::getModel('icecore/icepay_webservice_api');

        $webservice->webservice(
                $this->getValueForStore($icedata["store_id"], Icepay_IceCore_Model_Config::MERCHANTID),
                $this->getValueForStore($icedata["store_id"], Icepay_IceCore_Model_Config::SECRETCODE)
        );

        $checkoutResult = null;

        try {
        $checkoutResult = $webservice->doCheckout(
                $paymentData['ic_amount'],
		$paymentData['ic_country'],
		$paymentData['ic_currency'],
		$paymentData['ic_language'],
		$paymentData['ic_description'],
		$paymentData['ic_paymentmethod'],
		$paymentData['ic_issuer'],
		$paymentData['ic_orderid'],
		$paymentData['ic_reference']
                );
        } catch (Exception $e){
            $checkoutResult = $e->getMessage();
        }

        return $checkoutResult;
    }

    protected function getValueForStore($store, $val) {
        return Mage::helper('icecore')->getConfigForStore($store, $val);
    }

    protected function getCheckout(){
        return Mage::getSingleton('checkout/session');
    }


}