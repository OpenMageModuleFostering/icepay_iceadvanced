<?php

$namespace 	= Mage::helper('iceadvanced')->section;

$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();
$conn->insertOnDuplicate($this->getTable('core/config_data'), array('path'  => $namespace.'/module/title',			'value'	=> Mage::helper($namespace)->title), 		array('value'));
$conn->insertOnDuplicate($this->getTable('core/config_data'), array('path'  => $namespace.'/module/namespace',		'value'	=> $namespace), 		array('value'));

$installer->run("
CREATE TABLE IF NOT EXISTS `icepay_pmdata` (
  `pm_id` int(32) NOT NULL AUTO_INCREMENT,
  `pm_code` varchar(120) NOT NULL,
  PRIMARY KEY (`pm_id`),
  UNIQUE KEY `pm_name` (`pm_code`),
  KEY `pm_code` (`pm_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `icepay_issuerdata` (
  `config_id` int(32) NOT NULL AUTO_INCREMENT,
  `pm_code` varchar(32) NOT NULL,
  `store_scope_id` int(32) NOT NULL,
  `merchant_id` int(32) NOT NULL DEFAULT '0',
  `magento_code` varchar(32) NOT NULL,
  `issuer_code` varchar(120) NOT NULL,
  `issuer_name` varchar(120) NOT NULL,
  `issuer_country` text NOT NULL COMMENT 'serialized array',
  `issuer_currency` text NOT NULL COMMENT 'serialized array',
  `issuer_language` text NOT NULL COMMENT 'serialized array',
  `issuer_minimum` varchar(255) NOT NULL COMMENT 'array',
  `issuer_maximum` varchar(255) NOT NULL COMMENT 'array',
  PRIMARY KEY (`config_id`),
  KEY `code` (`pm_code`),
  KEY `magento_code` (`magento_code`),
  KEY `issuer_code` (`issuer_code`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

");



$installer->endSetup(); 