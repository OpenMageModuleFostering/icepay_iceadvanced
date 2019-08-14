<?php

/**
 *  ICEPAY Advanced - Block displays settings check
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

class Icepay_IceAdvanced_Block_Adminhtml_CheckSettings extends Mage_Adminhtml_Block_System_Config_Form_Field {

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $html = "";


        foreach (Mage::helper("iceadvanced")->doChecks() as $key => $value) {
            $html.= 'ICEPAY.addMessage(\'' . $value['result'] . '\',\''.$value['line'].'\');';
        }

        return '<div class="icepay_debug" id="icepay_debugger"></div>
        <script type="text/javascript">
        //<![CDATA[         
        '.$html.'
           //]]>
        </script>
        ';
    }

}