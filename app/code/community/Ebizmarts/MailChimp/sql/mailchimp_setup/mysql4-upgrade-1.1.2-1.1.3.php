<?php
/**
 * mc-magento Magento Component
 *
 * @category Ebizmarts
 * @package mc-magento
 * @author Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @date: 6/9/16 4:05 PM
 * @file: mysql4-upgrade-1.0.1-1.0.2.php
 */

$installer = $this;

$installer->startSetup();


$installer->run(
    "
	CREATE TABLE IF NOT EXISTS `{$this->getTable('mailchimp_customer_sync_data')}` (
	  `id`     INT(10) unsigned NOT NULL auto_increment,
	  `item_id` INT(10) DEFAULT 0,
	  `scope`  VARCHAR(16) DEFAULT '',
	  `mailchimp_sync_error` VARCHAR(255) DEFAULT '',
	  `mailchimp_sync_delta` DATETIME NOT NULL,
	  `mailchimp_sync_modified` INT(1) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	
	CREATE TABLE IF NOT EXISTS `{$this->getTable('mailchimp_product_sync_data')}` (
	  `id`     INT(10) unsigned NOT NULL auto_increment,
	  `item_id` INT(10) DEFAULT 0,
	  `scope`  VARCHAR(16) DEFAULT '',
	  `mailchimp_sync_error` VARCHAR(255) DEFAULT '',
	  `mailchimp_sync_delta` DATETIME NOT NULL,
	  `mailchimp_sync_modified` INT(1) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	
	CREATE TABLE IF NOT EXISTS `{$this->getTable('mailchimp_order_sync_data')}` (
	  `id`     INT(10) unsigned NOT NULL auto_increment,
	  `item_id` INT(10) DEFAULT 0,
	  `scope`  VARCHAR(16) DEFAULT '',
	  `mailchimp_sync_error` VARCHAR(255) DEFAULT '',
	  `mailchimp_sync_delta` DATETIME NOT NULL,
	  PRIMARY KEY  (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
	
	CREATE TABLE IF NOT EXISTS `{$this->getTable('mailchimp_quote_sync_data')}` (
	  `id`     INT(10) unsigned NOT NULL auto_increment,
	  `item_id` INT(10) DEFAULT 0,
	  `scope`  VARCHAR(16) DEFAULT '',
	  `mailchimp_sync_error` VARCHAR(255) DEFAULT '',
	  `mailchimp_sync_delta` DATETIME NOT NULL,
	  `mailchimp_deleted` INT(1) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;
"
);

$installer->endSetup();