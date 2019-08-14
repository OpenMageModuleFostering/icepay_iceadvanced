<?php

/**
 *  ICEPAY Pro - Webservice PaymentObject
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
class Icepay_IceAdvanced_Model_Checkout_PaymentObject {

    private $amount;
    private $country;
    private $currency;    
    private $description = '';
    private $language;
    private $orderID;
    private $paymentMethod;
    private $paymentMethodIssuer;   
    private $reference = '';
    
    /**
     * Sets the total amount to be paid in cents
     * 
     * @param int $amount
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setAmount($amount) {
        $this->amount = $amount;
        
        return $this;
    }
    
    /**
     * sets the country
     * 
     * @param string $country
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setCountry($country) {
        $this->country = (string) strtoupper($country);
        
        return $this;
    }
    
    /**
     * Sets the currency
     * 
     * @param string $currency
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setCurrency($currency) {
        $this->currency = (string) strtoupper($currency);
        
        return $this;
    }
    
    /**
     * Sets the description
     * 
     * @param string $description
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setDescription($description = '') {
        $this->description = (string) $description;
        
        return $this;
    }
    
    /**
     * Sets the language
     * 
     * @param string $language
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setLanguage($language) {
        $this->language = (string) strtoupper($language);
        
        return $this;
    }
    
    /**
     * Sets the OrderID
     * 
     * @param string $orderID
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setOrderID($orderID) {
        $this->orderID = (string) $orderID;
        
        return $this;
    }
    
    /**
     * Sets the paymentmethod
     * 
     * @param string $paymentMethod
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setPaymentMethod($paymentMethod) {
        $this->paymentMethod = (string) $paymentMethod;
        
        return $this;
    }
    
    /**
     * Set the paymentmethod issuer
     * 
     * @param string $paymentMethodIssuer
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setPaymentMethodIssuer($paymentMethodIssuer) {
        $this->paymentMethodIssuer = (string) $paymentMethodIssuer;
        
        return $this;
    }
    
    /**
     * Sets the reference
     * 
     * @param string $reference
     * @since 1.0.0
     * @return Icepay_Pro_Model_Webservice_PaymentObject
     */
    public function setReference($reference = '') {
        $this->reference = (string) $reference;   
        
        return $this;
    }
    
    /**
     * Returns the total amount in cents
     * 
     * @since 1.0.0
     * @return int     
     */
    public function getAmount() {
        return $this->amount;
    }
    
    /**
     * Returns the country
     * 
     * @since 1.0.0 
     * @return string     
     */
    public function getCountry() {
        return $this->country;
    }
    
    /**
     * Returns the currency
     * 
     * @since 1.0.0
     * @return string 
     */    
    public function getCurrency() {
        return $this->currency;
    }
    
    /**
     * Returns the description
     * 
     * @since 1.0.0
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }
    
    /**
     * Returns the language
     * 
     * @since 1.0.0
     * @return string
     */
    public function getLanguage() {
        return $this->language;
    }
    
    /**
     * Returns the order ID
     * 
     * @since 1.0.0
     * @return string
     */    
    public function getOrderID() {
        return $this->orderID;
    }
    
    /**
     * Returns the paymentmethod
     * 
     * @since 1.0.0
     * @return string
     */
    public function getPaymentMethod() {
        return $this->paymentMethod;
    }
    
    /**
     * Returns the paymentmethod issuer
     * 
     * @since 1.0.0
     * @return string
     */
    public function getPaymentMethodIssuer() {
        return $this->paymentMethodIssuer;
    }
    
    /**
     * Returns the reference
     * 
     * @since 1.0.0
     * @return string
     */
    public function getReference() {
        return $this->reference;
    }

}