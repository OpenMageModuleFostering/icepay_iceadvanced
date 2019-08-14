<?php

/**
 *  ICEPAY Core - Block checking settings
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

class Icepay_IceCore_Block_Adminhtml_CheckSettings
	extends Mage_Adminhtml_Block_System_Config_Form_Field
{

	
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
		$html = "";
		$lines = array();
		
		/* Check Merchant ID */
		$check = Mage::helper("icecore")->validateMerchantID($this->getValueForStore(Icepay_IceCore_Model_Config::MERCHANTID));
		array_push($lines, array(
			'line'		=> $check["msg"],
			'result'	=> ($check["val"]?"ok":"err")));
		
		
		/* Check SecretCode */
		$check = Mage::helper("icecore")->validateSecretCode($this->getValueForStore(Icepay_IceCore_Model_Config::SECRETCODE));
		array_push($lines, array(
			'line'		=> $check["msg"],
			'result'	=> ($check["val"]?"ok":"err")));
			
		foreach ($lines as $key => $value) {
			$html.= '<p class="'.$value['result'].'">'.$value['line'].'</p>';
		}
		
        return '<div class="icepay_debug">'.$html.'</div>';
    }


    protected function getValueForStore($val) {
        return Mage::helper('icecore')->getConfigForStore(Mage::helper('icecore')->adminGetStoreScopeID(), $val);
    }
	
	
  
  
}