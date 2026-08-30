-- Consolidated Database Setup
-- Merges the table/column definitions from: shop_inventory.sql, seed_operational_baseline.sql
--         and multistore_migration.sql (storeID rollout), plus archiveTransactions.php's archive tables.
-- Adds: subscription/Pro-plan columns on `stores` and offline-sync idempotency columns.
-- Schema only, no seed/demo data. Safe to run on a fresh database AND on an existing legacy database (all steps are guarded).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ---------------------------------------------------------------------------
-- Core tables (final shape, safe for fresh installs)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `stores` (
  `storeID` int(11) NOT NULL AUTO_INCREMENT,
  `storeName` varchar(120) NOT NULL,
  `storeCode` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  -- Subscription / Pro-plan gating for offline sync feature
  `subscription_plan` varchar(20) NOT NULL DEFAULT 'free',
  `subscription_cycle` varchar(20) DEFAULT NULL,
  `subscription_expires_at` datetime DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '0',
  `createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`storeID`),
  UNIQUE KEY `storeCode` (`storeCode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `user` (
  `userID` int(11) NOT NULL AUTO_INCREMENT,
  `fullName` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`userID`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `user` (`userID`, `fullName`, `username`, `password`, `role`, `status`) VALUES
(2, 'Guest', 'guest', '81dc9bdb52d04dc20036dbd8313ed055', 'admin', 'Active'),
(1, 'admin', 'admin', '21232f297a57a5a743894a0e4a801fc3', 'super_admin', 'Active')
ON DUPLICATE KEY UPDATE
  `fullName` = VALUES(`fullName`),
  `password` = VALUES(`password`),
  `role` = VALUES(`role`),
  `status` = VALUES(`status`);

CREATE TABLE IF NOT EXISTS `item` (
  `productID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `itemNumber` varchar(255) NOT NULL,
  `itemName` varchar(255) NOT NULL,
  `unitAsSold` varchar(50) NOT NULL DEFAULT 'pcs',
  `discount` float NOT NULL DEFAULT '0',
  `stock` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `imageURL` varchar(255) NOT NULL DEFAULT 'imageNotAvailable.jpg',
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `description` text NOT NULL,
  PRIMARY KEY (`productID`),
  UNIQUE KEY `itemNumber` (`itemNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vendor` (
  `vendorID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `fullName` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `phone2` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(30) DEFAULT NULL,
  `district` varchar(30) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vendorID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `customer` (
  `customerID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `fullName` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `mobile` varchar(255) DEFAULT NULL,
  `phone2` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(30) DEFAULT NULL,
  `district` varchar(30) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Active',
  `createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`customerID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase` (
  `purchaseID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `itemNumber` varchar(255) NOT NULL,
  `purchaseDate` date NOT NULL,
  `itemName` varchar(255) NOT NULL,
  `unitPrice` float NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '0',
  `vendorName` varchar(255) NOT NULL DEFAULT 'Test Vendor',
  `vendorID` int(11) NOT NULL DEFAULT '0',
  `transactionReference` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`purchaseID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale` (
  `saleID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `itemNumber` varchar(255) NOT NULL,
  `customerID` int(11) NOT NULL,
  `customerName` varchar(255) NOT NULL,
  `itemName` varchar(255) NOT NULL,
  `saleDate` date NOT NULL,
  `saleReference` varchar(255) DEFAULT NULL,
  `discount` float NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT 'Sales',
  PRIMARY KEY (`saleID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase_headers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `transactionReference` varchar(50) NOT NULL,
  `vendorName` varchar(255) DEFAULT NULL,
  `purchaseDate` date NOT NULL,
  -- Offline sync idempotency key generated client-side (IndexedDB outbox)
  `client_reference_id` varchar(64) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactionReference` (`transactionReference`),
  UNIQUE KEY `client_reference_id` (`client_reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase_items` (
  `purchaseItemID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `transactionReference` varchar(50) NOT NULL,
  `itemNumber` varchar(255) NOT NULL,
  `itemName` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `lineTotal` float NOT NULL DEFAULT '0',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchaseItemID`),
  KEY `transactionReference` (`transactionReference`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale_headers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `saleReference` varchar(50) NOT NULL,
  `customerID` int(11) DEFAULT NULL,
  `customerName` varchar(255) DEFAULT NULL,
  `saleDate` date NOT NULL,
  `amountPaid` float NOT NULL DEFAULT '0',
  -- Offline sync idempotency key generated client-side (IndexedDB outbox)
  `client_reference_id` varchar(64) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saleReference` (`saleReference`),
  UNIQUE KEY `client_reference_id` (`client_reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale_items` (
  `saleItemID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `saleReference` varchar(50) NOT NULL,
  `itemNumber` varchar(255) NOT NULL,
  `itemName` varchar(255) NOT NULL,
  `discount` float NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `reason` varchar(255) NOT NULL DEFAULT 'Sales',
  `lineTotal` float NOT NULL DEFAULT '0',
  `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`saleItemID`),
  KEY `saleReference` (`saleReference`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `customer_ledger` (
  `ledgerID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `customerID` int(11) NOT NULL,
  `saleID` int(11) DEFAULT NULL,
  `entryType` varchar(255) NOT NULL,
  `amount` float NOT NULL DEFAULT '0',
  `balanceAfter` float NOT NULL DEFAULT '0',
  `entryDate` date NOT NULL,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`ledgerID`),
  KEY `customerID` (`customerID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `customer_payments` (
  `paymentID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `customerID` int(11) NOT NULL,
  `saleReference` varchar(50) DEFAULT NULL,
  `saleID` int(11) DEFAULT NULL,
  `amount` float NOT NULL DEFAULT '0',
  `paymentDate` date NOT NULL,
  `paymentMethod` varchar(255) NOT NULL DEFAULT 'Cash',
  `referenceNumber` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `receiptNumber` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`paymentID`),
  KEY `customerID` (`customerID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- Archive tables (created on-demand by model/admin/archiveTransactions.php)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `archive_batches` (
  `batchID` int(11) NOT NULL AUTO_INCREMENT,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `storeName` varchar(120) DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archivedByUserID` int(11) DEFAULT NULL,
  `archivedByUsername` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `purchaseRows` int(11) NOT NULL DEFAULT '0',
  `purchaseHeaderRows` int(11) NOT NULL DEFAULT '0',
  `purchaseItemRows` int(11) NOT NULL DEFAULT '0',
  `saleRows` int(11) NOT NULL DEFAULT '0',
  `saleHeaderRows` int(11) NOT NULL DEFAULT '0',
  `saleItemRows` int(11) NOT NULL DEFAULT '0',
  `ledgerRows` int(11) NOT NULL DEFAULT '0',
  `paymentRows` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `purchaseID` int(11) DEFAULT NULL,
  `itemNumber` varchar(255) DEFAULT NULL,
  `purchaseDate` date DEFAULT NULL,
  `itemName` varchar(255) DEFAULT NULL,
  `unitPrice` float NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '0',
  `vendorName` varchar(255) DEFAULT NULL,
  `vendorID` int(11) DEFAULT NULL,
  `transactionReference` varchar(255) DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase_headers_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `id` int(11) DEFAULT NULL,
  `transactionReference` varchar(50) DEFAULT NULL,
  `vendorName` varchar(255) DEFAULT NULL,
  `purchaseDate` date DEFAULT NULL,
  `createdAt` timestamp NULL DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `purchase_items_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `purchaseItemID` int(11) DEFAULT NULL,
  `transactionReference` varchar(50) DEFAULT NULL,
  `itemNumber` varchar(255) DEFAULT NULL,
  `itemName` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `lineTotal` float NOT NULL DEFAULT '0',
  `createdAt` timestamp NULL DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `saleID` int(11) DEFAULT NULL,
  `itemNumber` varchar(255) DEFAULT NULL,
  `customerID` int(11) DEFAULT NULL,
  `customerName` varchar(255) DEFAULT NULL,
  `itemName` varchar(255) DEFAULT NULL,
  `saleDate` date DEFAULT NULL,
  `discount` float NOT NULL DEFAULT '0',
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `reason` varchar(255) DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale_headers_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `id` int(11) DEFAULT NULL,
  `saleReference` varchar(50) DEFAULT NULL,
  `customerID` int(11) DEFAULT NULL,
  `customerName` varchar(255) DEFAULT NULL,
  `saleDate` date DEFAULT NULL,
  `amountPaid` float NOT NULL DEFAULT '0',
  `createdAt` timestamp NULL DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sale_items_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `saleItemID` int(11) DEFAULT NULL,
  `saleReference` varchar(50) DEFAULT NULL,
  `itemNumber` varchar(255) DEFAULT NULL,
  `itemName` varchar(255) DEFAULT NULL,
  `discount` float NOT NULL DEFAULT '0',
  `reason` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `unitPrice` float NOT NULL DEFAULT '0',
  `lineTotal` float NOT NULL DEFAULT '0',
  `createdAt` timestamp NULL DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `customer_ledger_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `ledgerID` int(11) DEFAULT NULL,
  `customerID` int(11) DEFAULT NULL,
  `saleID` int(11) DEFAULT NULL,
  `entryType` varchar(255) DEFAULT NULL,
  `amount` float NOT NULL DEFAULT '0',
  `balanceAfter` float NOT NULL DEFAULT '0',
  `entryDate` date DEFAULT NULL,
  `note` text DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `customer_payments_archive` (
  `archiveID` int(11) NOT NULL AUTO_INCREMENT,
  `batchID` int(11) NOT NULL,
  `storeID` int(11) NOT NULL DEFAULT '1',
  `paymentID` int(11) DEFAULT NULL,
  `customerID` int(11) DEFAULT NULL,
  `saleID` int(11) DEFAULT NULL,
  `amount` float NOT NULL DEFAULT '0',
  `paymentDate` date DEFAULT NULL,
  `paymentMethod` varchar(255) DEFAULT NULL,
  `referenceNumber` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `receiptNumber` varchar(255) DEFAULT NULL,
  `saleReference` varchar(255) DEFAULT NULL,
  `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`archiveID`),
  KEY `batchID` (`batchID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- Guarded upgrade helpers (safe re-run on an existing/legacy database)
-- ---------------------------------------------------------------------------

DELIMITER $$
DROP PROCEDURE IF EXISTS `_add_column_if_missing`$$
CREATE PROCEDURE `_add_column_if_missing`(
  IN inTable VARCHAR(128), IN inColumn VARCHAR(128), IN inDefinition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = inTable AND COLUMN_NAME = inColumn
  ) THEN
    SET @ddl = CONCAT('ALTER TABLE `', inTable, '` ADD COLUMN ', inDefinition);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `_add_index_if_missing`$$
CREATE PROCEDURE `_add_index_if_missing`(
  IN inTable VARCHAR(128), IN inIndexName VARCHAR(128), IN inDefinition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = inTable AND INDEX_NAME = inIndexName
  ) THEN
    SET @ddl = CONCAT('ALTER TABLE `', inTable, '` ADD ', inDefinition);
    PREPARE stmt FROM @ddl;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

-- storeID rollout for legacy single-store databases
CALL _add_column_if_missing('item', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `productID`");
CALL _add_column_if_missing('vendor', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `vendorID`");
CALL _add_column_if_missing('customer', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `customerID`");
CALL _add_column_if_missing('purchase', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `purchaseID`");
CALL _add_column_if_missing('sale', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `saleID`");
CALL _add_column_if_missing('purchase_headers', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `id`");
CALL _add_column_if_missing('purchase_items', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `purchaseItemID`");
CALL _add_column_if_missing('sale_headers', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `id`");
CALL _add_column_if_missing('sale_items', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `saleItemID`");
CALL _add_column_if_missing('customer_ledger', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `ledgerID`");
CALL _add_column_if_missing('customer_payments', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `paymentID`");
CALL _add_column_if_missing('customer_payments', 'saleReference', "`saleReference` varchar(50) DEFAULT NULL AFTER `customerID`");

-- Subscription / Pro-plan columns for legacy `stores` tables
CALL _add_column_if_missing('stores', 'subscription_plan', "`subscription_plan` varchar(20) NOT NULL DEFAULT 'free'");
CALL _add_column_if_missing('stores', 'subscription_cycle', "`subscription_cycle` varchar(20) DEFAULT NULL");
CALL _add_column_if_missing('stores', 'subscription_expires_at', "`subscription_expires_at` datetime DEFAULT NULL");
CALL _add_column_if_missing('stores', 'is_paid', "`is_paid` tinyint(1) NOT NULL DEFAULT '0'");

-- Offline-sync idempotency keys for legacy header tables
CALL _add_column_if_missing('sale_headers', 'client_reference_id', "`client_reference_id` varchar(64) DEFAULT NULL");
CALL _add_column_if_missing('purchase_headers', 'client_reference_id', "`client_reference_id` varchar(64) DEFAULT NULL");

-- storeID rollout for legacy archive tables
CALL _add_column_if_missing('archive_batches', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('archive_batches', 'storeName', "`storeName` varchar(120) DEFAULT NULL AFTER `storeID`");
CALL _add_column_if_missing('purchase_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('purchase_headers_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('purchase_items_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('sale_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('sale_headers_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('sale_items_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('customer_ledger_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('customer_payments_archive', 'storeID', "`storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`");
CALL _add_column_if_missing('customer_payments_archive', 'saleReference', "`saleReference` varchar(255) DEFAULT NULL");

UPDATE `item` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `vendor` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `customer` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `purchase` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `sale` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `purchase_headers` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `purchase_items` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `sale_headers` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `sale_items` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `customer_ledger` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `customer_payments` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;

CALL _add_index_if_missing('item', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('vendor', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('customer', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('purchase', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('sale', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('purchase_headers', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('purchase_items', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('sale_headers', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('sale_items', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('customer_ledger', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");
CALL _add_index_if_missing('customer_payments', 'idx_storeID', "INDEX `idx_storeID` (`storeID`)");

DROP PROCEDURE IF EXISTS `_add_column_if_missing`;
DROP PROCEDURE IF EXISTS `_add_index_if_missing`;

-- ---------------------------------------------------------------------------
-- Optional dashboard view for stock movement reporting
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS `stock_movement_view`;
CREATE VIEW `stock_movement_view` AS
SELECT
  'Purchase' AS movement_type,
  purchaseDate AS movement_date,
  itemNumber,
  itemName,
  quantity,
  unitPrice,
  vendorName AS reference_name,
  '' AS reason,
  'In' AS direction
FROM purchase
UNION ALL
SELECT
  'Stock Out' AS movement_type,
  saleDate AS movement_date,
  itemNumber,
  itemName,
  quantity,
  unitPrice,
  customerName AS reference_name,
  reason,
  'Out' AS direction
FROM sale;

COMMIT;
