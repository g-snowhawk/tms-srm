CREATE TABLE IF NOT EXISTS `TMS_bank` (
  `bank_code` varchar(4) NOT NULL,
  `branch_code` varchar(3) NOT NULL,
  `account_number` varchar(7) NOT NULL,
  `userkey` int(11) unsigned NOT NULL,
  `bank` varchar(255) NOT NULL,
  `branch` varchar(255) NOT NULL,
  `account_type` varchar(255) NOT NULL,
  `account_holder` varchar(255) NOT NULL,
  `item_code` varchar(4) DEFAULT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`bank_code`,`branch_code`,`account_number`,`userkey`),
  KEY `TMS_bank_ibfk_1` (`userkey`),
  CONSTRAINT `TMS_bank_ibfk_1` FOREIGN KEY (`userkey`) REFERENCES `TMS_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_receipt_template` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `userkey` int(11) unsigned NOT NULL,
  `title` varchar(50) NOT NULL,
  `line` tinyint(3) unsigned NOT NULL DEFAULT '10',
  `pdf_mapper` text NOT NULL,
  `create_date` datetime NOT NULL,
  `modify_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `TMS_receipt_template_ibfk_1` (`userkey`),
  CONSTRAINT `TMS_receipt_template_ibfk_1` FOREIGN KEY (`userkey`) REFERENCES `TMS_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_receipt_to` (
  `id` varchar(32) NOT NULL,
  `userkey` int(11) unsigned NOT NULL,
  `aliasto` int(11) unsigned DEFAULT NULL,
  `company` varchar(255) NOT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `fullname_rubi` varchar(255) DEFAULT NULL,
  `zipcode` varchar(8) DEFAULT NULL,
  `address1` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `division` text,
  `modified_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `TMS_receipt_to_ibfk_1` (`userkey`),
  KEY `TMS_receipt_to_ibfk_2` (`aliasto`),
  CONSTRAINT `TMS_receipt_to_ibfk_1` FOREIGN KEY (`userkey`) REFERENCES `TMS_user` (`id`),
  CONSTRAINT `TMS_receipt_to_ibfk_2` FOREIGN KEY (`aliasto`) REFERENCES `TMS_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_receipt` (
  `issue_date` date NOT NULL,
  `receipt_number` int(11) unsigned NOT NULL,
  `userkey` int(11) unsigned NOT NULL,
  `templatekey` int(11) unsigned NOT NULL,
  `draft` enum('0','1') NOT NULL DEFAULT '1',
  `client_id` varchar(32) NOT NULL,
  `subject` varchar(66) DEFAULT NULL,
  `bank_id` varchar(15) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `receipt` date DEFAULT NULL,
  `term` varchar(50) DEFAULT NULL,
  `valid` varchar(50) DEFAULT NULL,
  `sales` varchar(1) DEFAULT NULL,
  `delivery` varchar(255) DEFAULT NULL,
  `payment` varchar(255) DEFAULT NULL,
  `additional_1_item` varchar(50) DEFAULT NULL,
  `additional_1_price` int(11) DEFAULT NULL,
  `additional_2_item` varchar(50) DEFAULT NULL,
  `additional_2_price` int(11) DEFAULT NULL,
  `note` text,
  PRIMARY KEY (`issue_date`,`receipt_number`,`userkey`,`templatekey`,`draft`),
  KEY `TMS_receipt_ibfk_1` (`userkey`),
  KEY `TMS_receipt_ibfk_2` (`templatekey`),
  KEY `TMS_receipt_ibfk_3` (`client_id`),
  KEY `TMS_receipt_ibfk_4` (`receipt_number`),
  CONSTRAINT `TMS_receipt_ibfk_1` FOREIGN KEY (`userkey`) REFERENCES `TMS_user` (`id`),
  CONSTRAINT `TMS_receipt_ibfk_2` FOREIGN KEY (`templatekey`) REFERENCES `TMS_receipt_template` (`id`)
  CONSTRAINT `TMS_receipt_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `TMS_receipt_to` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_receipt_detail` (
  `issue_date` date NOT NULL,
  `receipt_number` int(11) unsigned NOT NULL,
  `userkey` int(11) unsigned NOT NULL,
  `templatekey` int(11) unsigned NOT NULL,
  `page_number` tinyint(3) unsigned NOT NULL,
  `line_number` tinyint(3) unsigned NOT NULL,
  `draft` enum('0','1') NOT NULL DEFAULT '1',
  `content` varchar(66) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `tax_rate` decimal(3,2) DEFAULT NULL,
  `unit` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`issue_date`,`receipt_number`,`userkey`,`templatekey`,`page_number`,`line_number`,`draft`),
  KEY `TMS_receipt_detail_ibfk_1` (`userkey`),
  KEY `TMS_receipt_detail_ibfk_2` (`templatekey`),
  KEY `TMS_receipt_detail_ibfk_3` (`issue_date`,`receipt_number`,`userkey`,`templatekey`,`draft`),
  CONSTRAINT `TMS_receipt_detail_ibfk_1` FOREIGN KEY (`userkey`) REFERENCES `TMS_user` (`id`),
  CONSTRAINT `TMS_receipt_detail_ibfk_2` FOREIGN KEY (`templatekey`) REFERENCES `TMS_receipt_template` (`id`),
  CONSTRAINT `tms_receipt_detail_ibfk_3` FOREIGN KEY (`issue_date`, `receipt_number`, `userkey`, `templatekey`, `draft`) REFERENCES `tms_receipt` (`issue_date`, `receipt_number`, `userkey`, `templatekey`, `draft`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `TMS_tax_rates` (
  `effective_date` date NOT NULL,
  `area_code` varchar(32),
  `tax_rate` decimal(3,2) NOT NULL,
  `reduced_tax_rate` decimal(3,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`effective_date`,`area_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `TMS_tax_rates` (`effective_date`,`area_code`,`tax_rate`,`reduced_tax_rate`) VALUES
('1989-04-01','ja','0.03','0.00'),
('1997-04-01','ja','0.05','0.00'),
('2014-04-01','ja','0.08','0.00'),
('2019-10-01','ja','0.10','0.08');
