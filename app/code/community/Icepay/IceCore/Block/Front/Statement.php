<?php

/**
 *  ICEPAY Core - Block displaying the ICEPAY statement
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

class Icepay_IceCore_Block_Front_Statement extends Mage_Core_Block_Template {

    protected   $_template = 'icepaycore/front/statement.phtml';
    public      $_info;

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        return Mage::helper("icecore")->getStatementHTML(false);
    }

}