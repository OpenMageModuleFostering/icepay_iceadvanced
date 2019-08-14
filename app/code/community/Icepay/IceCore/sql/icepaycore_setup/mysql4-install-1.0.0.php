<?php

$namespace = Mage::helper('icecore')->section;

$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();
$conn->insertOnDuplicate($this->getTable('core/config_data'), array('path' => $namespace . '/module/title', 'value' => Mage::helper($namespace)->title), array('value'));
$conn->insertOnDuplicate($this->getTable('core/config_data'), array('path' => $namespace . '/module/namespace', 'value' => $namespace), array('value'));
$conn->insertOnDuplicate($this->getTable('core/config_data'), array('path' => $namespace . '/' . $namespace . '/active', 'value' => '1'), array('value'));


$installer->run("

CREATE TABLE IF NOT EXISTS `icepay_transactions` (
    `local_id` smallint(6) NOT NULL AUTO_INCREMENT,
    `order_id` varchar(36) NOT NULL DEFAULT '0',
    `model` varchar(32) NOT NULL,
    `transaction_id` int(36) NOT NULL DEFAULT '0',
    `status` varchar(255) NOT NULL DEFAULT '',
    `transaction_data` text,
    `creation_time` datetime DEFAULT NULL,
    `update_time` datetime DEFAULT NULL,
    `store_id` tinyint(4) NOT NULL DEFAULT '0',
    PRIMARY KEY (`local_id`,`order_id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='ICEPAY transactions';
");

$installer->run("
        INSERT INTO `{$installer->getTable('sales_order_status')}` (`status`, `label`) VALUES
                ('icecore_cback',    'Payment chargeback request'),
                ('icecore_ok',       'Payment received'),
                ('icecore_err',      'Payment error'),
                ('icecore_open',     'Awaiting payment'),
                ('icecore_new',      'New'),
                ('icecore_refund',   'Payment refund request');

        INSERT INTO `{$installer->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES
                ('icecore_ok',      'processing', 0),
                ('icecore_err',     'canceled', 0),
                ('icecore_open',    'pending_payment', 0),
                ('icecore_new',     'new', 0);

");


$installer->endSetup();
