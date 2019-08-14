<?php

class Icepay_IceAdvanced_Model_Extensions_MW_GiftWrap extends Mage_Payment_Model_Method_Abstract {

    public function isGiftWrapInstalled() {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modulesArray = (array) $modules;

        if (isset($modulesArray['MW_GiftWrap'])) {
            return true;
        }

        return false;
    }

    public function addGiftWrapPrices($quoteID) {
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

            Icepay_Order::getInstance()
                    ->addProduct(Icepay_Order_Product::create()
                            ->setProductID($productID)
                            ->setProductName('Gift Wrapping')
                            ->setDescription('Gift Wrapping')
                            ->setQuantity($productQuantity)
                            ->setUnitPrice($giftPrice * 100)
                            ->setVATCategory(Icepay_Order_VAT::getCategoryForPercentage(0))
            );
        }
    }

}