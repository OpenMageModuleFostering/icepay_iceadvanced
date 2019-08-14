<?php

/**
 *  ICEPAY Core - Block loading CSS
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
class Icepay_IceCore_Block_Adminhtml_Init extends Mage_Adminhtml_Block_System_Config_Form_Field {

    protected function _prepareLayout()
    {
        /* General pages CSS */
        $this->getLayout()
                ->getBlock('head')
                ->addCss('icepay/general.css');

        /* ADMIN page CSS */
        if (Mage::helper('icecore')->isAdminPage()) {
            $this->getLayout()
                    ->getBlock('head')
                    ->addCss('icepay/admin.css');
        }

        parent::_prepareLayout();
    }

    /* Don't use a template */

    public function setTemplate($template)
    {
        return "";
    }

}