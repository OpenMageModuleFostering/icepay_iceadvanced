<?php

/**
 *  ICEPAY Core - Controller Check, misc. check scripts
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

class Icepay_IceCore_CheckController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
    	$this->_redirect('icepay/about');
    }

    //Example: icepay/check/template/module/icepayadvanced
    public function templateAction(){
        $module = strtolower($this->getRequest()->module);
        if (substr($module, 0, 6) != "icepay") die("module must start with icepay");
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock("icecore/front_template")->setTemplate(sprintf("%s/front/check.phtml",$module))->toHtml()
            );
    }

}