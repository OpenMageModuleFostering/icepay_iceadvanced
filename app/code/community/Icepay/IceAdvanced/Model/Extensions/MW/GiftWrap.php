<?php

class Icepay_IceAdvanced_Model_Extensions_MW_GiftWrap extends Mage_Payment_Model_Method_Abstract {

    public function addGiftWrapPrices($quoteID, $ic_order)
    {
        $collections1 = Mage::getModel('giftwrap/quote')->getCollection()
                ->addFieldToFilter('quote_id', array('eq' => $quoteID));

        foreach ($collections1 as $collection1) {
            $productID = '00';
            $productQuantity = '1';
            $giftPrice = $collection1->getPrice();

            $collections2 = Mage::getModel('giftwrap/quoteitem')->getCollection()
                    ->addFieldToFilter('gw_quote_id', array('eq' => $collection1->getEntityId()));
            foreach ($collections2 as $collection2) {
                $productID = $collection2->getProductId();
                $productQuantity = $collection2->getQuantity();
            }

            $product = $ic_order->createProduct()
                    ->setProductID($productID)
                    ->setProductName('Gift Wrapping')
                    ->setDescription('Gift Wrapping')
                    ->setQuantity($productQuantity)
                    ->setUnitPrice($giftPrice * 100)
                    ->setVATCategory($ic_order->getCategoryForPercentage(0))
            ;
            
            $ic_order->addProduct($product);
            
            return $ic_order;
        }
    }

}