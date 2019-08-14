<?php

class Icepay_IceAdvanced_Model_Extensions_MageWorx_MultiFees extends Mage_Payment_Model_Method_Abstract {

    private $helper;

    public function __construct()
    {
        $this->helper = Mage::helper('multifees');
    }

    public function addPrice($fee, $ic_order)
    {
        $feeDetails = $fee[1];

        // Get Total price in cents
        $price = (int) (string) ($feeDetails['price'] * 100);

        // Calculate VAT percentage        
        $vatPercentage = 0;
        
        if ($feeDetails['tax_class_id'] != '0') {            
            $tax = (int) (string) ($feeDetails['tax'] * 100);            
            $priceExTax = $price - $tax;
            $vatPercentage = (int)(string)(($tax / $priceExTax) * 100);
        }

        $product = $ic_order->createProduct()
                ->setProductID('00')
                ->setProductName($feeDetails['title'])
                ->setDescription($feeDetails['options'][1]['title'])
                ->setQuantity('1')
                ->setUnitPrice($price)
                ->setVATCategory($ic_order->getCategoryForPercentage($vatPercentage))
        ;

        $ic_order->addProduct($product);

        return $ic_order;
    }

}
