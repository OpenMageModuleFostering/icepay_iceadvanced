<?php

$installer = $this;
$installer->startSetup();

$installer->run("
ALTER TABLE `icepay_pmdata` RENAME TO `{$installer->getTable('icepay_pmdata')}`;
ALTER TABLE `icepay_issuerdata` RENAME TO `{$installer->getTable('icepay_issuerdata')}`;
");

$installer->endSetup();
