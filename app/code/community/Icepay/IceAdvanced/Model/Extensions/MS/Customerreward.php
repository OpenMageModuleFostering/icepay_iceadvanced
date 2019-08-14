<?php

class Icepay_IceAdvanced_Model_Extensions_MS_Customerreward extends Mage_Payment_Model_Method_Abstract {

    public function addCustomerRewardPrices($orderData, $ic_order)
    {
        $rawDiscount = $orderData['current_money'];

        $discount = (int) ($rawDiscount * 100);

        $product = $ic_order->createProduct()
                ->setProductID('03')
                ->setProductName('Reward Points')
                ->setDescription('Reward Points')
                ->setQuantity(1)
                ->setUnitPrice($discount)
                ->setVATCategory(Icepay_Order_VAT::getCategoryForPercentage(21))
        ;

        $ic_order->addProduct($product);

        return $ic_order;
    }

}