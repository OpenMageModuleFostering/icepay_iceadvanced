<?php

/**
 *  ICEPAY Advanced - Block displays paymentmethods grid
 *  @version 1.0.1
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
class Icepay_IceAdvanced_Block_Adminhtml_Grid_PaymentMethods extends Mage_Adminhtml_Block_Widget implements Varien_Data_Form_Element_Renderer_Interface {

    protected $_element;
    protected $_scope;
    protected $_ajaxLoadPaymentMethodURL;
    protected $_ajaxSavePaymentMethodURL;
    protected $_ajaxGetPaymentMethodsURL;
    protected $debug;

    public function __construct()
    {
        $this->_scope = Mage::app()->getStore(Mage::helper("icecore")->getStoreFromRequest())->getId();
        $this->_ajaxLoadPaymentMethodURL = Mage::helper('adminhtml')->getUrl('icepayadvanced/config/index/paymentmethod/{{pm_code}}', array('_secure' => true, 'scope' => $this->_scope));
        $this->_ajaxSavePaymentMethodURL = Mage::helper('adminhtml')->getUrl('icepayadvanced/ajax/save_paymentmethod', array('_secure' => true, 'scope' => $this->_scope));
        $this->_ajaxGetPaymentMethodsURL = Mage::helper('adminhtml')->getUrl('icepayadvanced/ajax/get_paymentmethods', array('_secure' => true));

        $this->setTemplate('icepayadvanced/grid_paymentmethods.phtml');
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        return $this->toHtml();
    }

    public function setElement(Varien_Data_Form_Element_Abstract $element)
    {
        $this->_element = $element;
        return $this;
    }

    public function getElement()
    {
        return $this->_element;
    }

    public function getJS($uri)
    {
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS, true) . $uri;
    }

    public function getPaymentmethods()
    {
        return Mage::getSingleton('iceadvanced/mysql4_iceAdvanced')->getAdminPaymentmethodConfigForStore($this->_scope);
    }

    public function getAddButtonHtml()
    {
        return $this->getChildHtml('add_button');
    }

    protected function _prepareLayout()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
            'label' => Mage::helper('icecore')->__('Get paymentmethods'),
            'onclick' => 'return ICEPAY.retrieveFromICEPAY()',
            'class' => 'add'
        ));
        $button->setName('add_tier_price_item_button');

        $this->setChild('add_button', $button);

        if (version_compare(Mage::getVersion(), '1.7.0.0', '<')) {
            $this->getLayout()->getBlock('head')->addItem('js_css', 'prototype/windows/themes/magento.css');
        } else {
            $this->getLayout()->getBlock('head')->addItem('skin_css', 'lib/prototype/windows/themes/magento.css');
        }

        $this->getLayout()
                ->getBlock('head')
                ->addItem('js_css', 'prototype/windows/themes/default.css');

        return parent::_prepareLayout();
    }

}