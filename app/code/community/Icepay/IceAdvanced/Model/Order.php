<?php

/**
 *  ICEPAY Advanced - Webservice Order
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
class Icepay_IceAdvanced_Model_Order {

    private $data = array();
    private $defaultVATCategories = array();
    private $orderData;
    private $consumerNode;
    private $addressesNode;
    private $productsNode;
    private $debug = false;

    public function __construct()
    {
        $this->setData('products', array());

        $this->defaultVATCategories = array(
            'zero' => 0,
            'reduced-low' => array('1', '6'),
            'reduced-middle' => array('7', '12'),
            'standard' => array('13', '100')
        );
    }

    public function getProducts()
    {
        return $this->data['products'];
    }

    private function setData($tag, $object)
    {
        $this->data[$tag] = $object;
    }

    public function setShippingAddress(Icepay_Order_Address $shippingAddress)
    {
        $this->setData('shippingAddress', $shippingAddress);
        return $this;
    }

    public function setBillingAddress(Icepay_Order_Address $billingAddress)
    {
        $this->setData('billingAddress', $billingAddress);
        return $this;
    }

    public function setConsumer(Icepay_Order_Consumer $consumer)
    {
        $this->setData('consumer', $consumer);
        return $this;
    }

    public function addProduct(Icepay_Order_Product $product)
    {
        array_push($this->data["products"], $product);
        return $this;
    }

    public function createAddress()
    {
        return new Icepay_Order_Address();
    }

    public function createConsumer()
    {
        return new Icepay_Order_Consumer();
    }

    public function createProduct()
    {
        return new Icepay_Order_Product();
    }

    public function getCategoryForPercentage($number = null, $default = "exempt")
    {
        foreach ($this->defaultVATCategories as $category => $value) {
            if (!is_array($value)) {
                if ($value == $number)
                    return $category;
            }

            if ($number >= $value[0] && $number <= $value[1])
                return $category;
        }

        return $default;
    }

    public function setOrderDiscountAmount($amount, $name = 'Discount', $description = 'Order Discount')
    {
        $obj = $this->createProduct();
        $obj->setProductID('02')
                ->setProductName($name)
                ->setDescription($description)
                ->setQuantity('1')
                ->setUnitPrice(-$amount)
                ->setVATCategory($this->getCategoryForPercentage(-1));

        $this->addProduct($obj);

        return $this;
    }

    public function setShippingCosts($amount, $vat = -1, $name = 'Shipping Costs')
    {
        $obj = $this->createProduct();
        $obj->setProductID('01')
                ->setProductName($name)
                ->setDescription('')
                ->setQuantity('1')
                ->setUnitPrice($amount)
                ->setVATCategory($this->getCategoryForPercentage($vat));

        $this->addProduct($obj);

        return $this;
    }

    private function array_to_xml($childs, $node = 'Order')
    {
        $childs = (array) $childs;

        foreach ($childs as $key => $value) {
            $node->addChild(ucfirst($key), $value);
        }

        return $node;
    }

    public function getXML()
    {

        $this->orderData = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Order></Order>");
        $this->consumerNode = $this->orderData->addChild('Consumer');
        $this->addressesNode = $this->orderData->addChild('Addresses');
        $this->productsNode = $this->orderData->addChild('Products');

        // Set Consumer
        $this->array_to_xml($this->data['consumer'], $this->consumerNode);

        // Set Addresses
        $shippingNode = $this->addressesNode->addChild('Address');
        $shippingNode->addAttribute('id', 'shipping');

        $this->array_to_xml($this->data['shippingAddress'], $shippingNode);

        $billingNode = $this->addressesNode->addChild('Address');
        $billingNode->addAttribute('id', 'billing');

        $this->array_to_xml($this->data['billingAddress'], $billingNode);

        // Set Products
        foreach ($this->data['products'] as $product) {
            $productNode = $this->productsNode->addChild('Product');
            $this->array_to_xml($product, $productNode);
        }

        if ($this->debug == true) {
            header("Content-type: text/xml");
            echo $this->orderData->asXML();
            exit;
        }

        return $this->orderData->asXML();
    }

    public function validateOrder($paymentObj)
    {
        switch (strtoupper($paymentObj->getPaymentMethod())) {
            case 'AFTERPAY':
                if ($this->data['shippingAddress']->country !== $this->data['billingAddress']->country)
                    throw new Exception('Billing and Shipping country must be equal in order to use Afterpay.');

                if (!Icepay_Order_Helper::validateZipCode($this->data['shippingAddress']->zipCode, $this->data['shippingAddress']->country))
                    throw new Exception('Zipcode format for shipping address is incorrect.');

                if (!Icepay_Order_Helper::validateZipCode($this->data['billingAddress']->zipCode, $this->data['billingAddress']->country))
                    throw new Exception('Zipcode format for billing address is incorrect.');

                if (!Icepay_Order_Helper::validatePhonenumber($this->data['consumer']->phone))
                    throw new Exception('Phonenumber is incorrect.');

                break;
        }
    }

}

/**
 * ICEPAY - Webservice Order Consumer Class
 * 
 * @version 1.0.0
 * @author Wouter van Tilburg <wouter@icepay.eu>
 * @copyright ICEPAY <www.icepay.com>
 */
class Icepay_Order_Consumer {

    public $consumerID = '';
    public $email = '';
    public $phone = '';

    /**
     * Sets the consumer ID
     * 
     * @since 1.0.0
     * @param string $consumerID
     * @return \Icepay_Order_Consumer
     */
    public function setConsumerID($consumerID)
    {
        $this->consumerID = (string) $consumerID;
        return $this;
    }

    /**
     * Sets the consumer email address
     * 
     * @since 1.0.0
     * @param string $emailAddress
     * @return \Icepay_Order_Consumer
     */
    public function setEmailAddress($emailAddress)
    {
        $this->email = (string) $emailAddress;
        return $this;
    }

    /**
     * sets the consumer phonenumber
     * 
     * @since 1.0.0
     * @param string $phoneNumber
     * @return \Icepay_Order_Consumer
     */
    public function setPhoneNumber($phoneNumber)
    {
        $this->phone = (string) $phoneNumber;
        return $this;
    }

}

/**
 * ICEPAY - Webservice Order Product Class
 * 
 * @version 1.0.0
 * @author Wouter van Tilburg <wouter@icepay.eu>
 * @copyright ICEPAY <www.icepay.com>
 */
class Icepay_Order_Product {

    public $productID = '00';
    public $productName = '';
    public $description = '';
    public $quantity = '1';
    public $unitPrice = '0';
    public $VATCategory = 'standard';

    /**
     * Sets the Product's ID
     * 
     * @since 1.0.0
     * @param string $productID
     * @return \Icepay_Order_Product
     */
    public function setProductID($productID)
    {
        $this->productID = (string) $productID;
        return $this;
    }

    /**
     * Sets the Product's name
     * 
     * @since 1.0.0
     * @param string $productName
     * @return \Icepay_Order_Product
     */
    public function setProductName($productName)
    {
        $this->productName = (string) $productName;
        return $this;
    }

    /**
     * Sets the description
     * 
     * @since 1.0.0
     * @param string $description
     * @return \Icepay_Order_Product
     */
    public function setDescription($description)
    {
        $this->description = (string) $description;
        return $this;
    }

    /**
     * Sets the quantity
     * 
     * @since 1.0.0
     * @param string $quantity
     * @return \Icepay_Order_Product
     */
    public function setQuantity($quantity)
    {
        $this->quantity = (string) $quantity;
        return $this;
    }

    /**
     * Sets the unitprice
     * 
     * @since 1.0.0
     * @param string $unitPrice
     * @return \Icepay_Order_Product
     */
    public function setUnitPrice($unitPrice)
    {
        $this->unitPrice = (string) $unitPrice;
        return $this;
    }

    /**
     * Sets the VAT Category
     * 
     * @since 1.0.0
     * @param string $category
     * @return \Icepay_Order_Product
     */
    public function setVATCategory($category)
    {
        $this->VATCategory = (string) $category;
        return $this;
    }

}

/**
 * ICEPAY - Webservice Order Address Class
 * 
 * @version 1.0.0
 * @author Wouter van Tilburg <wouter@icepay.eu>
 * @copyright ICEPAY <www.icepay.com>
 */
class Icepay_Order_Address {

    public $initials = '';
    public $prefix = '';
    public $lastName = '';
    public $street = '';
    public $houseNumber = '';
    public $houseNumberAddition = '';
    public $zipCode = '';
    public $city = '';
    public $country = '';

    /**
     * Sets the address initials
     * 
     * @since 1.0.0
     * @param string $initials
     * @return \Icepay_Order_Address
     */
    public function setInitials($initials)
    {
        $initials = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities(utf8_encode($initials), ENT_QUOTES, 'UTF-8'));

        $this->initials = (string) $initials;
        return $this;
    }

    /**
     * Sets the address prefix
     * 
     * @since 1.0.0
     * @param string $prefix
     * @return \Icepay_Order_Address
     */
    public function setPrefix($prefix)
    {
        $this->prefix = (string) $prefix;
        return $this;
    }

    /**
     * Sets the address lastname
     * 
     * @since 1.0.0
     * @param string $lastName
     * @return \Icepay_Order_Address
     */
    public function setLastname($lastName)
    {
        $lastName = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities(utf8_encode($lastName), ENT_QUOTES, 'UTF-8'));

        $this->lastName = (string) $lastName;
        return $this;
    }

    /**
     * Sets the address streetname
     * 
     * @since 1.0.0
     * @param string $streetName
     * @return \Icepay_Order_Address
     */
    public function setStreetName($streetName)
    {
        $streetName = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', htmlentities(utf8_encode($streetName), ENT_QUOTES, 'UTF-8'));
        $this->street = (string) $streetName;
        return $this;
    }

    /**
     * Sets the address housenumber
     * 
     * @since 1.0.0
     * @param string $houseNumber
     * @return \Icepay_Order_Address
     */
    public function setHouseNumber($houseNumber)
    {
        $this->houseNumber = (string) $houseNumber;
        return $this;
    }

    /**
     * Sets the address housenumber addition
     * 
     * @since 1.0.0
     * @param string $houseNumberAddition
     * @return \Icepay_Order_Address
     */
    public function setHouseNumberAddition($houseNumberAddition)
    {
        $this->houseNumberAddition = (string) $houseNumberAddition;
        return $this;
    }

    /**
     * Sets the address zipcode
     * 
     * @since 1.0.0
     * @param string $zipCode
     * @return \Icepay_Order_Address
     */
    public function setZipCode($zipCode)
    {
        $this->zipCode = (string) $zipCode;
        return $this;
    }

    /**
     * Sets the address city
     * 
     * @since 1.0.0
     * @param string $city
     * @return \Icepay_Order_Address
     */
    public function setCity($city)
    {
        $this->city = (string) $city;
        return $this;
    }

    /**
     * Sets the address country
     * 
     * @since 1.0.0
     * @param string $country
     * @return \Icepay_Order_Address
     */
    public function setCountry($country)
    {
        $this->country = (string) $country;
        return $this;
    }

}

/**
 * ICEPAY - Webservice Order Helper Class
 *  
 * The Order Helper class contains handy fuctions to validate the input, such as a telephonenumber and zipcode check
 *  
 * @version 1.0.0
 * 
 * @author Wouter van Tilburg 
 * @author Olaf Abbenhuis 
 * @copyright Copyright (c) 2011-2012, ICEPAY  
 */
class Icepay_Order_Helper {

    private static $street;
    private static $houseNumber;
    private static $houseNumberAddition;

    /**
     * Sets and explodes the streetaddress
     * 
     * @since 1.0.0     
     * @param string Contains the street address     
     * @return Icepay_Order_Helper
     */
    public static function setStreetAddress($streetAddress)
    {
        self::explodeStreetAddress($streetAddress);

        return new self;
    }

    /**
     * Get the street from address
     * 
     * @since 1.0.0     
     * @param string Contains the street address      
     * @return Icepay_Order_Helper
     */
    public static function getStreetFromAddress($streetAddress = null)
    {
        if ($streetAddress)
            self::explodeStreetAddress($streetAddress);

        return self::$street;
    }

    /**
     * Get the housenumber from address
     * 
     * @since 1.0.0     
     * @param string Contains the street address     
     * @return Icepay_Order_Helper
     */
    public static function getHouseNumberFromAddress($streetAddress = null)
    {
        if ($streetAddress)
            self::explodeStreetAddress($streetAddress);

        return self::$houseNumber;
    }

    /**
     * Get the housenumber addition from address
     * 
     * @since 1.0.0     
     * @param string Contains the street address     
     * @return Icepay_Order_Helper
     */
    public static function getHouseNumberAdditionFromAddress($streetAddress = null)
    {
        if ($streetAddress)
            self::explodeStreetAddress($streetAddress);

        return self::$houseNumberAddition;
    }

    /**
     * Validates a zipcode based on country
     * 
     * @since 1.0.0
     * @param string $zipCode A string containing the zipcode
     * @param string $country A string containing the ISO 3166-1 alpha-2 code of the country
     * @example validateZipCode('1122AA', 'NL')
     * @return boolean
     */
    public static function validateZipCode($zipCode, $country)
    {
        switch (strtoupper($country)) {
            case 'NL':
                if (preg_match('/^[1-9]{1}[0-9]{3}[A-Z]{2}$/', $zipCode))
                    return true;
                break;
            case 'BE':
                if (preg_match('/^[1-9]{4}$/', $zipCode))
                    return true;
                break;
            case 'DE':
                if (preg_match('/^[1-9]{5}$/', $zipCode))
                    return true;
                break;
        }

        return false;
    }

    /**
     * Validates a phonenumber
     * 
     * @since 1.0.0
     * @param string Contains the phonenumber
     * @return boolean
     */
    public static function validatePhonenumber($phoneNumber)
    {
        if (strlen($phoneNumber) < 10) {
            return false;
        }

        if (preg_match('/^(?:\((\+?\d+)?\)|\+?\d+) ?\d*(-?\d{2,3} ?){0,4}$/', $phoneNumber)) {
            return true;
        }

        return false;
    }

    private static function explodeStreetAddress($streetAddress)
    {

        $streetAddress = utf8_decode($streetAddress);

        $pattern = '#^(.+\D+){1} ([0-9]{1,})\s?([\s\/]?[0-9]{0,}?[\s\S]{0,}?)?$#i';

        $aMatch = array();

        if (preg_match($pattern, $streetAddress, $aMatch)) {
            self::$street = $aMatch[1];
            self::$houseNumber = $aMatch[2];

            $houseNumberAddition = $aMatch[3];
            $hNa = str_replace('/', '', $houseNumberAddition);
            self::$houseNumberAddition = $hNa;
        }
    }

}