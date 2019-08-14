<?php

$installer = $this;
$installer->startSetup();

$installer->run("
ALTER TABLE `icepay_transactions` RENAME TO `{$installer->getTable('icepay_transactions')}`;
");

$installer->endSetup();
