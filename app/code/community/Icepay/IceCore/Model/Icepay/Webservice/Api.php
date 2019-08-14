<?php

/**
 *  ICEPAY Core - ICEPAY Webservice API
 *  @version 0.1.0
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
class Icepay_IceCore_Model_Icepay_Webservice_Api {

    public $client;  //	SoapClient Object
    protected $merchantID; //	Integer
    protected $secretCode; //	String
    protected $url = "https://connect.icepay.com/webservice/icepay.svc?wsdl";

    public function webservice($merchantID, $secretCode) {
        $this->setMerchantID($merchantID);
        $this->setSecretCode($secretCode);

        $this->client = new SoapClient(
                        $this->url,
                        array(
                            "location" => $this->url,
                            'cache_wsdl' => 'WSDL_CACHE_NONE'
                        )
        );
        $this->client->soap_defencoding = "utf-8";
    }

    /* Setters */

    public function setMerchantID($val) {
        $this->merchantID = $val;
    }

    public function setSecretCode($val) {
        $this->secretCode = $val;
    }

    /* Getters */

    public function getMerchantID() {
        return intval($this->merchantID);
    }

    public function getSecretCode() {
        return $this->secretCode;
    }

    protected function getTimeStamp() {
        return gmdate("Y-m-d\TH:i:s\Z");
    }

    protected function generateChecksum($obj = null) {
        $arr = array();
        array_push($arr, $this->getSecretCode());
        
        foreach ($obj as $val) {
            array_push($arr, $val);
        }

        return sha1(implode("|", $arr));
    }

    protected function getIP() {
        return $_SERVER['REMOTE_ADDR'];
    }

    // Webservice methods below:
    public function doCheckout(
    $amount, $country, $currency, $lang, $descr, $paymentmethod, $issuer, $orderID, $reference, $URLCompleted = "", $URLError = ""
    ) {

        $obj = new stdClass();

        // Must be in specific order for checksum ---------
        $obj->MerchantID = $this->merchantID;
        $obj->Timestamp = $this->getTimeStamp();
        $obj->Amount = $amount;
        $obj->Country = $country;
        $obj->Currency = $currency;
        $obj->Description = $descr;
        $obj->EndUserIP = $this->getIP();
        $obj->Issuer = $issuer;
        $obj->Language = $lang;
        $obj->OrderID = $orderID;
        $obj->PaymentMethod = $paymentmethod;
        $obj->Reference = $reference;
        $obj->URLCompleted = $URLCompleted;
        $obj->URLError = $URLError;

        if (strtoupper($paymentmethod) == 'AFTERPAY')
            $obj->XML = Icepay_Order::getInstance()->createXML();

        // ------------------------------------------------
        $obj->Checksum = $this->generateChecksum($obj);

        if (strtoupper($paymentmethod) == 'AFTERPAY') {
            return (array) $this->client->CheckoutExtended(array('request' => $obj));
        } else {
            return (array) $this->client->Checkout(array('request' => $obj));
        }
    }

    public function getPayment($id) {

        $obj = null;

        // Must be in specific order for checksum ---------
        $obj->MerchantID = $this->merchantID;
        $obj->Timestamp = $this->getTimeStamp();
        $obj->PaymentID = $id;

        // ------------------------------------------------
        $obj->Checksum = $this->generateChecksum($obj);
        return (array) $this->client->GetPayment(array('request' => $obj));
    }

    public function getPremiumRateNumbers() {

        $obj = null;

        // Must be in specific order for checksum ---------
        $obj->MerchantID = $this->merchantID;
        $obj->Timestamp = $this->getTimeStamp();

        // ------------------------------------------------
        $obj->Checksum = $this->generateChecksum($obj);
        return (array) $this->client->GetPremiumRateNumbers(array('request' => $obj));
    }

    public function completeObject($obj) {
        $obj->Checksum = $this->generateChecksum($obj);
        return $obj;
    }

}

?>