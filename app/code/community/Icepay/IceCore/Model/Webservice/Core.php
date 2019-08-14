<?php

/**
 *  ICEPAY - Webservice Core Model
 * 
 *  @version 1.0.1
 *  @author Wouter van Tilburg <wouter@icepay.eu>
 *  @copyright ICEPAY <www.icepay.com>
 *  
 *  Disclaimer:
 *  The merchant is entitled to change de ICEPAY plug-in code,
 *  any changes will be at merchant's own risk.
 *  Requesting ICEPAY support for a modified plug-in will be
 *  charged in accordance with the standard ICEPAY tariffs.
 * 
 */
class Icepay_IceCore_Model_Webservice_Core {

    protected $merchantID;
    protected $secretCode;
    protected $client;
    protected $serviceURL = 'https://connect.icepay.com/webservice/icepay.svc?wsdl';

    /**
     * Create the SOAP client
     * 
     * @param int $merchantID
     * @param string $secretCode
     * 
     * @since 1.0.0
     */
    public function init($merchantID, $secretCode)
    {
        $this->merchantID = (int) $merchantID;
        $this->secretCode = (string) $secretCode;

        $this->client = new SoapClient($this->serviceURL, array('cache_wsdl' => 'WSDL_CACHE_NONE', 'encoding' => 'UTF-8'));
    }

    /**
     * Return Merchant ID
     * 
     * @since 1.0.1
     * @return string
     */
    public function getMerchantID()
    {
        return $this->merchantID;
    }

    /**
     * Return SecretCode
     * 
     * @since 1.0.1
     * @return string
     */
    public function getSecretCode()
    {
        return $this->secretCode;
    }

    /**
     * Return the user IP address
     * 
     * @since 1.0.0
     * @return string
     */
    protected function getIP()
    {
        return (string) $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Return the current timestamp
     * 
     * @since 1.0.0
     * @return type
     */
    protected function getTimestamp()
    {
        return (string) gmdate("Y-m-d\TH:i:s\Z");
    }

    /**
     * Generate and return the checksum
     * 
     * @param object $obj
     * @param string $secretCode
     * 
     * @since 1.0.0
     * @return string
     */
    protected function generateChecksum($obj = null, $secretCode = null)
    {
        $arr = array();

        if ($secretCode)
            array_push($arr, $secretCode);

        foreach ($obj as $val) {
            if (is_bool($val))
                $val = ($val) ? 'true' : 'false';
            array_push($arr, $val);
        }

        return (string) sha1(implode("|", $arr));
    }

}