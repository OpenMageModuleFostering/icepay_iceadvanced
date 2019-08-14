<?php

/**
 *  ICEPAY Core - Block generating store URLs
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
class Icepay_IceCore_Block_Adminhtml_GenerateURL extends Mage_Adminhtml_Block_System_Config_Form_Field {

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        return $this->enCase(Mage::helper("icecore")->getStoreFrontURL($this->setAction($this->getElement()->getName())));
    }

    protected function setAction($elementName)
    {
        switch ($elementName) {
            case "groups[settings][fields][merchant_url_ok][value]": return "result";
            case "groups[settings][fields][merchant_url_err][value]": return "result";
            case "groups[settings][fields][merchant_url_notify][value]": return "notify";
        }
    }

    protected function enCase($str)
    {
        return '<input type="text" name="" class="icepay_url_form" value="' . $str . '"/>';
    }

}