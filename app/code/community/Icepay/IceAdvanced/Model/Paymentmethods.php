<?php

/**
 *  ICEPAY Advanced - Webservice Paymentmethods
 *  @version 2.0.0
 *  @author Wouter van Tilburg
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
class Icepay_IceAdvanced_Model_Paymentmethods {

    protected $storeID = null;
    private $messages = array();

    public function retrieveAdminGrid($storeID)
    {
        $this->storeID = $storeID;

        return $this->retrievePaymentmethods();
    }

    public function retrievePaymentmethods()
    {
        $merchantID = Mage::getStoreConfig('icecore/settings/merchant_id', $this->storeID);
        $secretCode = Mage::getStoreConfig('icecore/settings/merchant_secret', $this->storeID);

        $submitObject = new stdClass();

        $webservice = Mage::getModel('Icepay_IceAdvanced_Model_Webservice_Advanced');
        $webservice->init($merchantID, $secretCode);

        try {
            $paymentMethods = $webservice->getMyPaymentMethods();
            $this->addMessage('SOAP connection established', 'ok');

            if (isset($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod)) {
                $pMethods = $this->clean($paymentMethods->GetMyPaymentMethodsResult->PaymentMethods->PaymentMethod);

                $this->addMessage(sprintf(Mage::helper('iceadvanced')->__('%s active paymentmethods found'), count($pMethods)), 'ok');

                // Add issuers
                $issuerObj = array();
                foreach ($pMethods as $value) {
                    $arr = array(
                        'merchant' => $merchantID,
                        'code' => $value->PaymentMethodCode,
                        'issuers' => $value->Issuers->Issuer
                    );
                    array_push($issuerObj, array(
                        'pmcode' => $value->PaymentMethodCode,
                        'data' => urlencode(serialize($arr))
                    ));
                }

                $submitObject->paymentmethods = Mage::helper("iceadvanced")->addIcons($pMethods);

                $submitObject->issuers = $issuerObj;
            } else {
                $this->addMessage(Mage::helper('iceadvanced')->__('No active paymentmethods found'));
            }
        } catch (Exception $e) {
            $this->addMessage('SOAP connection established', 'ok');
            $this->addMessage($e->getMessage());
        }

        $submitObject->msg = $this->messages;

        return $submitObject;
    }

    private function clean($paymentMethods)
    {
        //Convert to array (in case one payment method is active)
        $pMethodsArray = Mage::helper('icecore')->makeArray($paymentMethods);

        //Filter
        $pMethods = array_values($this->filterPaymentmethods($pMethodsArray));

        return $pMethods;
    }

    private function filterPaymentmethods($paymentMethods)
    {
        $filter = Mage::helper("iceadvanced")->filteredPaymentmethods;
        foreach (array_keys((array) $paymentMethods) as $key) {
            if (in_array($paymentMethods[$key]->PaymentMethodCode, $filter))
                unset($paymentMethods[$key]);
        };
        return $paymentMethods;
    }

    private function addMessage($val, $type = 'err')
    {
        $msg = new stdClass();
        $msg->type = $type;
        $msg->msg = Mage::helper('iceadvanced')->__($val);

        array_push($this->messages, $msg);
    }

}

?>