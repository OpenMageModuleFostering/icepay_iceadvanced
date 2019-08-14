<?php

$installer = $this;
$installer->startSetup();

$installer->run("CREATE INDEX icepay_trans_order_id ON {$this->getTable('icepay_transactions')} (order_id);");

$installer->endSetup();
