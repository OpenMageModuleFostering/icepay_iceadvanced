<?php

/**
 *  ICEPAY Core - Return page processing
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

class Icepay_IceCore_Model_Icepay_Result {

    protected $sqlModel;

    public function  __construct() {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
    }

    public function handle(array $_vars){
        if (count($_vars) == 0) die("ICEPAY result page installed correctly.");
        if (!$_vars['OrderID']) die("No orderID found");

	$order = Mage::getModel('sales/order');
        $order->loadByIncrementId(($_vars['OrderID'] == "DUMMY")?$_vars['Reference']:$_vars['OrderID'])
                ->addStatusHistoryComment(sprintf(Mage::helper("icecore")->__("Customer returned with status: %s"),$_vars['StatusCode']))
                ->save();

        switch(strtoupper($_vars['Status'])){
            case "ERR":
                $cart = Mage::getModel('checkout/cart')->getQuote()->getData();
                $msg = sprintf(Mage::helper("icecore")->__("The payment provider has returned the following error message: %s"),Mage::helper("icecore")->__($_vars['Status']. ": ".$_vars['StatusCode']));
                if(!isset($cart['items_qty'])) $msg .= sprintf("<p>".Mage::helper("icecore")->__("Click <a href='%s'>here</a> to reorder.")."</p>",Mage::getUrl('sales/order/reorder', array('order_id'=>$order->getRealOrderId())));
                Mage::getSingleton('checkout/session')->setErrorMessage($msg);
                $url = 'checkout/onepage/failure';
                break;
            case "OK":
            case "OPEN":
            default:
                Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
                $url = 'checkout/onepage/success';
        };

        /* Redirect based on store */
        //Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl($url));
        $url = Mage::app()->getStore($order->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK,true) . $url;
        Mage::app()->getFrontController()->getResponse()->setRedirect($url);
        
    }


}