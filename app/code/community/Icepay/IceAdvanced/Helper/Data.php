<?php

/**
 *  ICEPAY Advanced - Helper class
 *  @version 1.0.0
 *  @author Olaf Abbenhuis
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
class Icepay_IceAdvanced_Helper_Data extends Mage_Core_Helper_Abstract {
    /* Install values */

    public $title = 'Advanced';
    public $version = '1.1.10';
    public $id = 'ADV';
    public $fingerprint = '7f4de76ecbf7d847caeba64c42938a6a05821c4f';
    public $compatibility_oldest_version = '1.5.0.0';
    public $compatibility_latest_version = '1.7.0.2';
    public $section = 'iceadvanced';
    public $serial_required = '0';
    public $filteredPaymentmethods = array('SMS', 'PHONE');
    public $filteredPaymentMethodIssuers = array('CCAUTOCHECKOUT', 'IDEALINCASSO');

    public function doChecks()
    {
        $lines = array();
        
        /* Check SOAP */
        $check = Mage::helper("icecore")->hasSOAP();
        array_push($lines, array(
            'id' => "soap",
            'line' => ($check) ? $this->__("SOAP webservices available") : $this->__("SOAP was not found on this server"),
            'result' => ($check) ? "ok" : "err"));

        /* Check Paymentmethods */
        $showDefault = true;

        if (Mage::helper("icecore")->adminGetFrontScope()) {
            $check = $this->countStoredPaymentmethods(Mage::helper("icecore")->adminGetStoreScopeID());
            array_push($lines, array(
                'id' => "database",
                'line' => $check["msg"],
                'result' => ($check["val"]) ? "ok" : "err"));
            if ($check["val"])
                $showDefault = false;
        };

        /* Check Default Paymentmethods */
        if ($showDefault) {
            $check = $this->countStoredPaymentmethods(0);
            array_push($lines, array(
                'id' => "default_database",
                'line' => $check["msg"],
                'result' => ($check["val"]) ? "ok" : "err"));
        }

        $adv_sql = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced');

        if ($adv_sql->getPMbyCode('afterpay')) {
            if (Mage::getModel('tax/config')->getAlgorithm() != Mage_Tax_Model_Calculation::CALC_UNIT_BASE)
                array_push($lines, array(
                    'id' => "tax",
                    'line' => "In order to use Afterpay you must have set the Tax Calculation Method Based On Unit Price.",
                    'result' => "err"));
        }

        return $lines;
    }

    public function getPaymentmethodExtraSettings()
    {
        return array(
            "description",
            //"active_issuers",
            "allowspecific",
            "specificcountry",
            "min_order_total",
            "max_order_total",
            "sort_order"
        );
    }

    public function countStoredPaymentmethods($storeID)
    {
        $adv_sql = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced');
        $adv_sql->setScope($storeID);

        $count = $adv_sql->countPaymentMethods();

        if ($storeID == 0) {
            $return = array('val' => false, 'msg' => $this->__("No paymentmethods stored in Default settings"));
            $langvar = $this->__("%s paymentmethods stored in Default settings");
        } else {
            $return = array('val' => false, 'msg' => $this->__("No paymentmethods stored for this Store view"));
            $langvar = $this->__("%s paymentmethods stored for this Store view");
        }

        if ($count > 0)
            $return = array('val' => true, 'msg' => sprintf($langvar, $count));

        return $return;
    }

    public function addIcons($obj, $isArray = false)
    {
        if ($isArray) {
            foreach ($obj as $key => $value) {
                $img = Mage::helper("icecore")->toIcon(Mage::helper("icecore")->cleanString($value["code"]));
                $obj[$key]["Image"] = ($img) ? $img : $value["code"];
            }
        } else {
            foreach ($obj as $key => $value) {
                $img = Mage::helper("icecore")->toIcon(Mage::helper("icecore")->cleanString($value->PaymentMethodCode));
                $obj[$key]->Image = ($img) ? $img : $value->PaymentMethodCode;
            };
        }

        return $obj;
    }

    public function getIssuerArray($value)
    {
        return unserialize(urldecode($value));
    }

    protected function getValueForStore($val)
    {
        $store = Mage::helper('icecore')->getStoreScopeID();
        return Mage::helper('icecore')->getConfigForStore($store, $val);
    }

}
