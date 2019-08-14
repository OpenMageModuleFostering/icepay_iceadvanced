<?php

/**
 *  ICEPAY Advanced - Custom webservice extension
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


class Icepay_IceAdvanced_Model_Webservice 
extends Icepay_IceCore_Model_Icepay_Webservice_Api {

    protected $_storeID = null;

    public function connection($merchantID, $secretCode) {

        return $this->webservice($merchantID, $secretCode);
    }

    protected function generateChecksum($obj = null, $paymentID = null) {
        $arr = array();

        array_push($arr, $this->getMerchantID());
        array_push($arr, $this->getSecretCode());
        array_push($arr, $this->getTimeStamp());
        
        if ($paymentID)
            array_push($arr, $paymentID);

        return sha1(implode("|", $arr));
    }

    public function getMyPaymentMethods() {

        $obj = new stdClass();

        $obj->MerchantID = $this->getMerchantID();
        $obj->Timestamp = $this->getTimeStamp();
        $obj->Checksum = $this->generateChecksum($obj);
        
        return $this->client->GetMyPaymentMethods(array('request'=>$obj));
    }

    public function setScopeID($storeID){
        $this->_storeID = $storeID;
    }

    private function getScopeID(){
        return $this->_storeID;
    }

    public function retrieveAdminGrid(){

        $obj = $this->retrievePaymentmethods();
        
        //Alter codes to images
        $obj->paymentmethods = Mage::helper("iceadvanced")->addIcons($obj->paymentmethods);
        
        return $obj;
    }
    
    public function retrievePaymentmethods() {

        $submitObject = new stdClass();
        $msgs = array();

        if (!$this->doBasicChecks()) {
            $submitObject->msg = array($this->addMessage("Basic configuration not properly set"));
            $this->getResponse()->setBody(Zend_Json::encode($submitObject));
            return;
        }

        // Start connection
        $this->connection(
                $this->getValueForStore(Icepay_IceCore_Model_Config::MERCHANTID),
                $this->getValueForStore(Icepay_IceCore_Model_Config::SECRETCODE)
        );

        // Catch Exception for display
        try {
            $paymentMethods = $this->getMyPaymentMethods();
            array_push($msgs, $this->addMessage("SOAP connection established", "ok"));
        } catch (SoapFault $e) {
            array_push($msgs, $this->addMessage($e->faultstring));
        }

        //Convert to array (in case one payment method is active)
        $paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod = Mage::helper("icecore")->makeArray($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod);
        
        //Filter
        $paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod = $this->filterPaymentmethods($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod);

        //Count number of payment methods
        array_push($msgs,
                (($count = count($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod)) > 0 && $this->containsData($paymentMethods)) ?
                        $this->addMessage(sprintf(Mage::helper("iceadvanced")->__("%s active paymentmethods found"),$count), "ok") : $this->addMessage(Mage::helper("iceadvanced")->__("No active paymentmethods found"))
        );
        // Add issuers
        $issuerObj = array();
        foreach ($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod as $value) {
            
            $arr = array(
                'merchant'  => $this->getValueForStore(Icepay_IceCore_Model_Config::MERCHANTID),
                'code'      => $value->PaymentMethodCode,
                'issuers'   => $value->Issuers->Issuer
            );
            array_push($issuerObj,  array(
                
                'pmcode' => $value->PaymentMethodCode,
                //'data' => $arr
                'data' => urlencode(serialize($arr))
                ));
        }

        // Create submit object
        $submitObject->msg = $msgs;
        $submitObject->merchant = $this->getValueForStore(Icepay_IceCore_Model_Config::MERCHANTID);
        
        if (!$this->containsData($paymentMethods)) return $submitObject;
            

        $submitObject->paymentmethods = $paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod;
        $submitObject->issuers = $issuerObj;


  

        return $submitObject;
    }
    
    private function containsData($obj){
        return ($obj->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod[0] == null)?false:true;
    }

    private function filterPaymentmethods($obj) {
        $arr = array();
        $filter = Mage::helper("iceadvanced")->filteredPaymentmethods;
        foreach ($obj as $key => $value) {
            if (!in_array($obj[$key]->PaymentMethodCode, $filter))
                array_push($arr, $obj[$key]); 
        };
        return $arr;
    }

    private function populateData($obj) {

        $configArray = Mage::getSingleton('iceadvanced/mysql4_iceAdvanced')->getPaymentmethodsConfiguration();

        foreach ($obj as $key => $value) {

            $obj[$key]->ConfigDescription = "";
            $obj[$key]->ConfigTitle = "";
            $obj[$key]->ConfigActive = 0;

            foreach ($configArray as $ckey => $cvalue) {
                if ($cvalue["code"] == $obj[$key]->PaymentMethodCode) {
                    $obj[$key]->ConfigDescription = $cvalue["info"];
                    $obj[$key]->ConfigTitle = $cvalue["name"];
                    $obj[$key]->ConfigActive = $cvalue["active"];
                }
            }
        };

        return $obj;
    }

    private function addIcons($obj) {

        foreach ($obj as $key => $value) {
            $img = Mage::helper("icecore")->toIcon(Mage::helper("icecore")->cleanString($value->PaymentMethodCode));
            $obj[$key]->Image = ($img) ? $img : $value->PaymentMethodCode;
        };

        return $obj;
    }

    private function doBasicChecks() {
        foreach (Mage::helper("iceadvanced")->doChecks() as $key => $value) {
            switch ($value['id']) {
                case "merchantid": if ($value['result'] == "err")
                        return false; break;
                case "secretcode": if ($value['result'] == "err")
                        return false; break;
                case "soap": if ($value['result'] == "err")
                        return false; break;
            }
        }
        return true;
    }

    private function addMessage($val, $type = "err") {
        $msg = new stdClass();
        $msg->type = $type;
        $msg->msg = Mage::helper('iceadvanced')->__($val);
        return $msg;
    }

    private function getValue($val) {
        return Mage::helper('icecore')->getConfig($val);
    }

    private function getValueForStore($val){
        return Mage::helper('icecore')->getConfigForStore($this->getScopeID(), $val);
    }

}




?>