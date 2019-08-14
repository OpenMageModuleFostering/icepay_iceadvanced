<?php

/**
 *  ICEPAY Core - Block modules grid
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
class Icepay_IceCore_Block_Adminhtml_Grid_Modules extends Mage_Adminhtml_Block_Widget implements Varien_Data_Form_Element_Renderer_Interface {

    protected $_element;

    public function __construct()
    {
        $this->setTemplate('icepaycore/grid_modules.phtml');
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $this->_element = $element;
        return $this->toHtml();
    }

    public function getElement()
    {
        return $this->_element;
    }

    public function getModules()
    {

        $core_sql = Mage::getSingleton('icecore/mysql4_iceCore');
        return $core_sql->getModulesConfiguration();
    }



    protected function _prepareLayout()
    {
        
    }

}