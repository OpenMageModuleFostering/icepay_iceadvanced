<?php

/**
 *  ICEPAY Advanced - Start payment
 *  @version 1.0.0
 *  @author Wouter van Tilburg
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

    private $sqlModel;
 
    public function __construct()
    {
        $this->sqlModel = Mage::getModel('icecore/mysql4_iceCore');
    }

    public function getCheckoutResult()
    {
        // Get Magento's checkout session
        $session = Mage::getSingleton('checkout/session');

        // Retrieve icepay order
        $icedata = $this->sqlModel->loadPaymentByID($session->getLastRealOrderId());

        // Retrieve payment data
        $paymentData = unserialize(urldecode($icedata["transaction_data"]));

        // Retrieve merchant id and secretcode
        $merchantID = Mage::app()->getStore($icedata["store_id"])->getConfig(Icepay_IceCore_Model_Config::MERCHANTID);
        $secretCode = Mage::app()->getStore($icedata["store_id"])->getConfig(Icepay_IceCore_Model_Config::SECRETCODE);

        // Initialize webservice
        $webservice = Mage::getModel('Icepay_IceAdvanced_Model_Webservice_Advanced');
        $webservice->init($merchantID, $secretCode);

        // Create the PaymentObject
        $paymentObject = Mage::getModel('Icepay_IceAdvanced_Model_Checkout_PaymentObject');
        $paymentObject->setAmount($paymentData['ic_amount'])
                ->setCountry($paymentData['ic_country'])
                ->setLanguage($paymentData['ic_language'])
                ->setCurrency($paymentData['ic_currency'])
                ->setPaymentMethod($paymentData['ic_paymentmethod'])
                ->setPaymentMethodIssuer($paymentData['ic_issuer'])
                ->setReference($paymentData['ic_reference'])
                ->setOrderID($paymentData['ic_orderid'])
                ->setDescription($paymentData['ic_description']);

        // Fetch the Icepay_Order class
        $ic_order = Mage::getModel('iceadvanced/order');

        if ($webservice->isExtendedCheckout($paymentData['ic_paymentmethod'])) {
            try {
                // Retrieve Magento Order
                $order = Mage::getModel('sales/order')->loadByIncrementId($paymentData['ic_orderid']);

                // Add the consumer information for Afterpay
                $consumer = $ic_order->createConsumer()
                        ->setConsumerID($order->getCustomerName())
                        ->setEmailAddress($order->getCustomerEmail())
                        ->setPhoneNumber($order->getBillingAddress()->getTelephone());

                $ic_order->setConsumer($consumer);

                // Add the billing address information for Afterpay
                $billingStreetaddress = implode(' ', $order->getBillingAddress()->getStreet());

                $billingAddress = $ic_order->createAddress()
                        ->setInitials($order->getBillingAddress()->getFirstname())
                        ->setPrefix($order->getBillingAddress()->getPrefix())
                        ->setLastName($order->getBillingAddress()->getLastname())
                        ->setStreetName(Icepay_Order_Helper::getStreetFromAddress($billingStreetaddress))
                        ->setHouseNumber(Icepay_Order_Helper::getHouseNumberFromAddress())
                        ->setHouseNumberAddition(Icepay_Order_Helper::getHouseNumberAdditionFromAddress())
                        ->setZipCode($order->getBillingAddress()->getPostcode())
                        ->setCity($order->getBillingAddress()->getCity())
                        ->setCountry($order->getBillingAddress()->getCountry());

                $ic_order->setBillingAddress($billingAddress);

                // Add the shipping address information for Afterpay
                $shippingStreetAddress = implode(' ', $order->getShippingAddress()->getStreet());

                $shippingAddress = $ic_order->createAddress()
                        ->setInitials($order->getShippingAddress()->getFirstname())
                        ->setPrefix($order->getShippingAddress()->getPrefix())
                        ->setLastName($order->getShippingAddress()->getLastname())
                        ->setStreetName(Icepay_Order_Helper::getStreetFromAddress($shippingStreetAddress))
                        ->setHouseNumber(Icepay_Order_Helper::getHouseNumberFromAddress())
                        ->setHouseNumberAddition(Icepay_Order_Helper::getHouseNumberAdditionFromAddress())
                        ->setZipCode($order->getShippingAddress()->getPostcode())
                        ->setCity($order->getShippingAddress()->getCity())
                        ->setCountry($order->getShippingAddress()->getCountry());

                $ic_order->setShippingAddress($shippingAddress);

                // Add item specification for Afterpay
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


                    // Add the products
                    $itemData['description'] = (isset($itemData['description'])) ? $itemData['description'] : '';

                    $ic_product = $ic_order->createProduct()
                            ->setProductID($itemData['item_id'])
                            ->setProductName($itemData['name'])
                            ->setDescription($itemData['description'])
                            ->setQuantity(round($itemData['qty_ordered']))
                            ->setUnitPrice($itemData['price_incl_tax'] * 100)
                            ->setVATCategory($ic_order->getCategoryForPercentage($itemData['tax_percent']));

                    $ic_order->addproduct($ic_product);
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
                    $ic_order = $giftWrapExtension->addGiftWrapPrices($session->getLastQuoteId(), $ic_order);
                }

                if (Mage::helper('icecore')->isModuleInstalled('Magestore_Customerreward')) {
                    $customerRewardExtension = Mage::getModel('iceadvanced/extensions_MS_Customerreward');
                    $ic_order = $customerRewardExtension->addCustomerRewardPrices($orderData, $ic_order);
                }

                // Log the XML Send
                Mage::helper("icecore")->log(serialize($ic_order->getXML()));
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }

        try {
            return $webservice->doCheckout($paymentObject, $ic_order);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

}