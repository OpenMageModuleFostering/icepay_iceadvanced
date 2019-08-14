<?php

/**
 *  ICEPAY Advamced - Webservice Refund Model
 * 
 *  @version 1.0.0
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
class Icepay_IceAdvanced_Model_Webservice_Refund extends Icepay_IceCore_Model_Webservice_Core {
    
    protected $serviceURL = 'https://connect.icepay.com/webservice/refund.svc?wsdl';
    
    /**
     * Request a full or partial refund
     * 
     * @param int $paymentID
     * @param int $refundAmount
     * @param string $refundCurrency
     * 
     * @since 1.0.0
     * @return array
     */
    public function requestRefund($paymentID, $refundAmount, $refundCurrency)
    {
        $obj = new stdClass();

        // Must be in specific order for checksum --
        $obj->Secret = $this->getSecretCode();
        $obj->MerchantID = $this->getMerchantID();
        $obj->Timestamp = $this->getTimeStamp();
        $obj->PaymentID = $paymentID;
        $obj->RefundAmount = $refundAmount;
        $obj->RefundCurrency = $refundCurrency;
   
        // Generate Checksum
        $obj->Checksum = $this->generateChecksum($obj);

        // Ask for getPaymentRefunds and get response
        $result = $this->client->requestRefund($obj);

        return (array) $result->RequestRefundResult;
    }
}

