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
require_once('icepay_api_order.php');

class Icepay_IceAdvanced_Model_Pay extends Mage_Payment_Model_Method_Abstract {

    protected $sqlModel;

    public function __construct() {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
    }

    public function getCheckoutResult() {
        $session = $this->getCheckout();
        $icedata = $this->sqlModel->loadPaymentByID($session->getLastRealOrderId());

        $paymentData = unserialize(urldecode($icedata["transaction_data"]));
        $webservice = Mage::getModel('icecore/icepay_webservice_api');

        $webservice->webservice(
                $this->getValueForStore($icedata["store_id"], Icepay_IceCore_Model_Config::MERCHANTID), $this->getValueForStore($icedata["store_id"], Icepay_IceCore_Model_Config::SECRETCODE)
        );

        $checkoutResult = null;

        $order = Mage::getModel('sales/order')->loadByIncrementId($paymentData['ic_orderid']);

        if (strtoupper($paymentData['ic_paymentmethod']) == 'AFTERPAY') {
            // Fetch the Icepay_Order class
            $ic_order = Icepay_Order::getInstance();

            // Set consumer information
            $ic_order->setConsumer(Icepay_Order_Consumer::create()
                            ->setConsumerID($order->getCustomerName())
                            ->setEmail($order->getCustomerEmail())
                            ->setPhone($order->getBillingAddress()->getTelephone())
            );

            $billingStreetaddress = implode(' ', $order->getBillingAddress()->getStreet());

            // Set the billing address
            $ic_order->setBillingAddress(Icepay_Order_Address::create()
                            ->setInitials($order->getBillingAddress()->getFirstname())
                            ->setPrefix($order->getBillingAddress()->getPrefix())
                            ->setLastName($order->getBillingAddress()->getLastname())
                            ->setStreet(Icepay_Order_Helper::getStreetFromAddress($billingStreetaddress))
                            ->setHouseNumber(Icepay_Order_Helper::getHouseNumberFromAddress())
                            ->setHouseNumberAddition(Icepay_Order_Helper::getHouseNumberAdditionFromAddress())
                            ->setZipCode($order->getBillingAddress()->getPostcode())
                            ->setCity($order->getBillingAddress()->getCity())
                            ->setCountry($order->getBillingAddress()->getCountry())
            );

            $shippingStreetAddress = implode(' ', $order->getShippingAddress()->getStreet());

            // Set the shipping address
            $ic_order->setShippingAddress(Icepay_Order_Address::create()
                            ->setInitials($order->getShippingAddress()->getFirstname())
                            ->setPrefix($order->getShippingAddress()->getPrefix())
                            ->setLastName($order->getShippingAddress()->getLastname())
                            ->setStreet(Icepay_Order_Helper::getStreetFromAddress($shippingStreetAddress))
                            ->setHouseNumber(Icepay_Order_Helper::getHouseNumberFromAddress())
                            ->setHouseNumberAddition(Icepay_Order_Helper::getHouseNumberAdditionFromAddress())
                            ->setZipCode($order->getShippingAddress()->getPostcode())
                            ->setCity($order->getShippingAddress()->getCity())
                            ->setCountry($order->getShippingAddress()->getCountry())
            );

            $orderItems = $order->getItemsCollection();

            foreach ($orderItems as $item) {
                $itemData = $item->getData();

                if ($itemData['price'] == 0)
                    continue;

                $product = Mage::getModel('catalog/product')->load($itemData['product_id']);
                $productData = $product->getData();

                $itemData['price'] * $itemData['tax_percent'];

                $itemData['price_incl_tax'] = number_format($itemData['price_incl_tax'], 2);
                
                if ($productData['tax_class_id'] == '0')
                    $itemData['tax_percent'] = -1;

                $itemData['description'] = (isset($itemData['description'])) ? $itemData['description'] : '';
                // Add the products
                $ic_order->addProduct(Icepay_Order_Product::create()
                                ->setProductID($itemData['item_id'])
                                ->setProductName($itemData['name'])
                                ->setDescription($itemData['description'])
                                ->setQuantity(round($itemData['qty_ordered']))
                                ->setUnitPrice($itemData['price_incl_tax'] * 100)
                                ->setVATCategory(Icepay_Order_VAT::getCategoryForPercentage($itemData['tax_percent']))
                );
            }

            $orderData = $order->getData();

            // Set total order discount if any
            $discount = $orderData['base_discount_amount'] * 100;

            if ($discount != '0')
                $ic_order->setOrderDiscountAmount(-$discount);

            // Set shipping costs           
            if ($orderData['shipping_amount'] != 0) {
                $shippingCosts = ($orderData['shipping_amount'] + $orderData['shipping_tax_amount']) * 100;
                $shippingTax = $orderData['shipping_tax_amount'] / $orderData['shipping_amount'] * 100;

                $ic_order->setShippingCosts($shippingCosts, $shippingTax);
            } else {
                $ic_order->setShippingCosts(0, -1);
            }

            if (Mage::helper('icecore')->isModuleInstalled('MW_GiftWrap')) {
                $giftWrapExtension = Mage::getModel('iceadvanced/extensions_MW_GiftWrap');
                $giftWrapExtension->addGiftWrapPrices($session->getLastQuoteId());
            }

            if (Mage::helper('icecore')->isModuleInstalled('Magestore_Customerreward')) {
                $customerRewardExtension = Mage::getModel('iceadvanced/extensions_MS_Customerreward');
                $customerRewardExtension->addCustomerRewardPrices($orderData);
            }

            // Log the XML Send
            Mage::helper("icecore")->log(serialize(Icepay_Order::getInstance()->createXML()));
        }

        try {
            $checkoutResult = $webservice->doCheckout(
                    $paymentData['ic_amount'], $paymentData['ic_country'], $paymentData['ic_currency'], $paymentData['ic_language'], $paymentData['ic_description'], $paymentData['ic_paymentmethod'], $paymentData['ic_issuer'], $paymentData['ic_orderid'], $paymentData['ic_reference']
            );
        } catch (Exception $e) {
            $checkoutResult = $e->getMessage();
        }

        return $checkoutResult;
    }

    protected function getValueForStore($store, $val) {
        return Mage::helper('icecore')->getConfigForStore($store, $val);
    }

    protected function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

}