<?php

/**
 *  ICEPAY Advanced - Database model
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
class Icepay_IceAdvanced_Model_Mysql4_IceAdvanced extends Mage_Core_Model_Mysql4_Abstract {

    private $issuer_table = 'icepay_issuerdata';
    private $pm_table = 'icepay_pmdata';
    private $db = null;
    private $_reference = "icepayadv_%s";
    private $_standardModel = "iceadvanced/checkout_placeholder_paymentmethod%s";
    private $scope = null;

    public function _construct()
    {
        // Note that the basicgateway_id refers to the key field in your database table.
        $this->_init('icecore/icecore', 'icecore_id');
    }

    private function doSQL()
    {
        if ($this->db == null)
            $this->db = $this->_getReadAdapter();
        return $this->db;
    }

    public function getPaymentmethods()
    {
        $config = array();
        $mod_code = $this->getConfigData("code");
        $mod_title = $this->getConfigData("title");
        $mod_active = $this->getConfigData("active");
        $mod_issuers = $this->getConfigData("issuer");
        $mod_info = $this->getConfigData("info");

        for ($i = 0; $i < count($mod_title); $i++) {
            array_push($config, array(
                'code' => $mod_code[$i]["value"],
                'name' => $mod_title[$i]["value"],
                'info' => $mod_info[$i]["value"],
                'issuers' => $mod_issuers[$i]["value"],
                'active' => $mod_active[$i]["value"]
            ));
        }

        return $config;
    }

    public function getPaymentmethodsForConfig()
    {
        $config = array();

        $mod_title = array();

        for ($i = 0; $i < count($mod_title); $i++) {
            array_push($config, array(
                'code' => $mod_code[$i]["value"],
                'name' => $mod_title[$i]["value"],
                'info' => $mod_info[$i]["value"],
                'issuers' => $mod_issuers[$i]["value"],
                'active' => $mod_active[$i]["value"]
            ));
        };

        return $config;
    }

    public function getAdminPaymentmethodConfigForStore($storeScope)
    {
        $this->setScope($storeScope);

        $total = $this->getAllConfigData();
        $returnArr = array();

        foreach ($total as $pm) {
            array_push($returnArr, array(
                'code' => $pm["config"]["pm_code"],
                'name' => $pm["config"]["title"],
                'issuers' => count($pm["issuers"]),
                'active' => $pm["config"]["active"],
                'info' => "",
                'image' => Mage::helper("icecore")->toIcon($pm["config"]["pm_code"])
            ));
        }
        
        return $returnArr;
    }

    public function setScope($storeScope)
    {
        /* allow overrides in same instance */
        //if ($this->scope != null) return;
        $this->scope = new stdClass();
        $this->scope->scope = ((int) $storeScope == 0) ? "default" : "stores";
        $this->scope->ID = $storeScope;
    }

    private function getScope()
    {
        return $this->scope;
    }

    private function getPMIDbyCode($code)
    {
        $pm = $this->getPMbyCode($code);
        return $pm['pm_id'];
    }

    public function getPMbyCode($code)
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable("iceadvanced/{$this->pm_table}"), array('pm_id'))
                ->where('pm_code=?', $code);
        return $this->doSQL()->fetchRow($select);
    }

    private function getModelFromID($id)
    {
        $sref = $this->createSrefFromID($id);
        return $this->getModelFromSref($sref);
    }

    private function getModelFromSref($sref)
    {
        return sprintf($this->_standardModel, $sref);
    }

    private function getReferenceFromID($id)
    {
        $ref = $this->createSrefFromID($id);
        return $this->getReferenceFromSref($ref);
    }

    private function getReferenceFromSref($sref)
    {
        return sprintf($this->_reference, $sref);
    }

    public function getReferenceFromPMCode($code)
    {
        return $this->getReferenceFromID($this->getPMIDbyCode($code));
    }

    private function createSrefFromID($id)
    {
        $id = intval($id);
        if ($id < 10)
            return "0{$id}";
        return "{$id}";
    }

    public function clearConfig()
    {
        // Clear the issuer table
        $condition = array('store_scope_id = ?' => $this->getScope()->ID);
        $this->doSQL()->delete($this->getTable("iceadvanced/{$this->issuer_table}"), $condition);

        $condition = array('store_scope_id = ?' => Mage::app()->getStore()->getId());
        $this->doSQL()->delete($this->getTable("iceadvanced/{$this->issuer_table}"), $condition);

        // Disable the config data
        $where = array('path LIKE ?' => "payment/icepayadv___/active",
            'scope = ?' => $this->getScope()->scope,
            'scope_id = ?' => $this->getScope()->ID
        );
        $data["value"] = 0;
        $this->doSQL()->update(
                $this->getTable('core/config_data'), $data, $where
        );
    }

    public function savePaymentMethod($code, $merchant, $storeScope, $issuerlist)
    {
        $id = $this->getPMIDbyCode($code);
        /* create main paymentmethod if not exists */
        if (!$id) {
            $this->doSQL()->insertOnDuplicate(
                    $this->getTable("iceadvanced/{$this->pm_table}"), array(
                'pm_code' => $code
                    ), array('pm_code')
            );
            $id = $this->getPMIDbyCode($code);
        }

        $this->setScope($storeScope);

        /* Save payment configuration for store view */

        $this->saveConfigValue($this->getReferenceFromID($id), "model", $this->getModelFromID($id));
        $this->saveConfigValue($this->getReferenceFromID($id), "group", "icepay");
        $this->saveConfigValue($this->getReferenceFromID($id), "pm_code", $code);
        $this->saveConfigValue($this->getReferenceFromID($id), "pm_id", $id);
        $this->saveConfigValue($this->getReferenceFromID($id), "pm_ref", $this->getReferenceFromID($id));

        $this->saveConfigValue($this->getReferenceFromID($id), "description", "");
        $this->saveConfigValue($this->getReferenceFromID($id), "allowspecific", 0);
        $this->saveConfigValue($this->getReferenceFromID($id), "specificcountry", "");
        $this->saveConfigValue($this->getReferenceFromID($id), "sort_order", $id);
        $this->saveConfigValue($this->getReferenceFromID($id), "min_order_total", "");
        $this->saveConfigValue($this->getReferenceFromID($id), "max_order_total", "");

        $this->saveConfigValue($this->getReferenceFromID($id), "active_issuers", $issuerlist);
    }

    public function saveIssuer($storeScope, $code, $merchant, $issuercode, $issuername, $countries, $currencies, $languages, $minimum, $maximum)
    {
        $id = $this->getPMIDbyCode($code);

        $this->setScope($storeScope);

        $result = $this->doSQL()->insertOnDuplicate(
                $this->getTable("iceadvanced/{$this->issuer_table}"), array(
            'pm_code' => $code,
            'store_scope_id' => $this->getScope()->ID,
            'merchant_id' => $merchant,
            'magento_code' => $this->getReferenceFromID($id),
            'issuer_code' => $issuercode,
            'issuer_name' => $issuername,
            'issuer_country' => $this->arrEncode($countries),
            'issuer_currency' => $this->arrEncode($currencies),
            'issuer_language' => $this->arrEncode($languages),
            'issuer_minimum' => $this->arrEncode($minimum),
            'issuer_maximum' => $this->arrEncode($maximum)
                ), array('issuer_code')
        );

        $select = $this->doSQL()
                ->select()
                ->from($this->getTable("iceadvanced/{$this->issuer_table}"))
                ->where('magento_code=?', $this->getReferenceFromID($id))
                ->where('store_scope_id =?', Mage::app()->getStore()->getId())
                ->where('issuer_name =?', $issuername);
        $res = $this->doSQL()->fetchRow($select);

        if (!$res) {
            $result = $this->doSQL()->insertOnDuplicate(
                    $this->getTable("iceadvanced/{$this->issuer_table}"), array(
                'pm_code' => $code,
                'store_scope_id' => Mage::app()->getStore()->getId(),
                'merchant_id' => $merchant,
                'magento_code' => $this->getReferenceFromID($id),
                'issuer_code' => $issuercode,
                'issuer_name' => $issuername,
                'issuer_country' => $this->arrEncode($countries),
                'issuer_currency' => $this->arrEncode($currencies),
                'issuer_language' => $this->arrEncode($languages),
                'issuer_minimum' => $this->arrEncode($minimum),
                'issuer_maximum' => $this->arrEncode($maximum)
                    ), array('issuer_code')
            );
        }
    }

    public function saveConfigFromAdmin($code, $option, $value)
    {
        $this->saveConfigValue($this->getReferenceFromPMCode($code), $option, $value);
    }

    public function saveConfigValue($reference, $option, $value)
    {
        $this->doSQL()->insertOnDuplicate(
                $this->getTable('core/config_data'), array(
            'path' => "payment/{$reference}/{$option}",
            'value' => $value,
            'scope' => $this->getScope()->scope,
            'scope_id' => $this->getScope()->ID
                ), array('value')
        );

        $this->doSQL()->insertOnDuplicate(
                $this->getTable('core/config_data'), array(
            'path' => "payment/{$reference}/{$option}",
            'value' => $value,
            'scope' => 'default',
            'scope_id' => Mage::app()->getStore()->getId()
                ), array('value')
        );
    }

    private function getPaymentMethodIDs()
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable('core/config_data'), array('scope', 'scope_id', 'path', 'value'))
                ->where(new Zend_Db_Expr("path LIKE 'payment/icepayadv_%%/pm_ref'"))
                ->where('scope=?', $this->getScope()->scope)
                ->where('scope_id=?', $this->getScope()->ID)
                ->order('path');
        return $this->doSQL()->fetchAll($select);
    }

    public function getAvailableIssuers($activeIssuers, $reference)
    {
        $issuers = explode(",", $activeIssuers);
        $arr = array();
        foreach ($issuers as $issuer) {
            $arr[] = $this->getIssuerData($issuer, $reference);
        }
        return $arr;
    }

    public function getIssuerData($issuerCode, $reference)
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable("iceadvanced/{$this->issuer_table}"))
                ->where('magento_code=?', $reference)
                ->where('store_scope_id=?', $this->getScope()->ID)
                ->where('issuer_code=?', $issuerCode);
        return $this->doSQL()->fetchRow($select);
    }

    public function getIssuersByPMReference($reference)
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable("iceadvanced/{$this->issuer_table}"))
                ->where('magento_code=?', $reference)
                ->where('store_scope_id=?', $this->getScope()->ID)
                ->order('config_id');
        return $this->doSQL()->fetchAll($select);
    }

    private function getPaymentMethodDataByReference($ref)
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable('core/config_data'), array('scope', 'scope_id', 'path', 'value'))
                ->where(new Zend_Db_Expr("path LIKE 'payment/" . $ref . "/%'"))
                ->where('scope=?', $this->getScope()->scope)
                ->where('scope_id=?', $this->getScope()->ID)
                ->order('path');

        return $this->doSQL()->fetchAll($select);
    }

    public function getPaymentMethodDataArrayByReference($ref)
    {
        $data = $this->getPaymentMethodDataByReference($ref);
        $returnArr = array();
        foreach ($data as $value) {
            $returnArr[$this->getFieldName($value["path"])] = $value["value"];
        }
        return $returnArr;
    }

    private function getFieldName($configValue)
    {
        $arr = explode("/", $configValue);
        return $arr[2];
    }

    public function getAllConfigData()
    {
        $pms = $this->getPaymentMethodIDs();
        $returnArr = array();

        foreach ($pms as $value) {
            array_push($returnArr, $this->getConfigDataByReference($value["value"]));
        }

        return $returnArr;
    }

    public function getConfigDataByReference($reference)
    {

        return array(
            "reference" => $reference,
            "config" => $this->getPaymentMethodDataArrayByReference($reference),
            "issuers" => $this->getIssuersByPMReference($reference)
        );
    }

    public function arrEncode($arr)
    {
        return serialize($arr);
        return urlencode(serialize($arr));
    }

    public function arrDecode($str)
    {
        return unserialize(urldecode($str));
    }

    /* Used for admin count and also to fallback to default if no paymentmethods are found for the current store view */

    public function countPaymentMethods()
    {
        $select = $this->doSQL()
                ->select()
                ->from($this->getTable('core/config_data'), array('count(value)'))
                ->where(new Zend_Db_Expr("path LIKE 'payment/icepayadv___/title'"))
                ->where('scope=?', $this->getScope()->scope)
                ->where('scope_id=?', $this->getScope()->ID)
                ->order('path');
        $data = $this->doSQL()->fetchRow($select);
        return $data["count(value)"];
    }

}

?>
