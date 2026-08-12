-- Multistore migration for legacy single-store database
-- Run this once in phpMyAdmin on the older machine after updating the PHP files.
-- It creates the stores table, seeds Main Store as storeID = 1, and adds storeID to legacy tables.
-- Newer runtime-created tables such as sale_headers, sale_items, customer_ledger, and customer_payments
-- are created and migrated automatically by the updated PHP bootstrap, so this script stays compatible
-- with the older single-store database layout.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `stores` (
  `storeID` int(11) NOT NULL AUTO_INCREMENT,
  `storeName` varchar(120) NOT NULL,
  `storeCode` varchar(40) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`storeID`),
  UNIQUE KEY `storeCode` (`storeCode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `stores` (`storeID`, `storeName`, `storeCode`, `status`)
VALUES (1, 'Main Store', 'MAIN', 'Active')
ON DUPLICATE KEY UPDATE
  `storeName` = VALUES(`storeName`),
  `storeCode` = VALUES(`storeCode`),
  `status` = VALUES(`status`);

ALTER TABLE `item`
  ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `productID`;

ALTER TABLE `vendor`
  ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `vendorID`;

ALTER TABLE `customer`
  ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `customerID`;

ALTER TABLE `purchase`
  ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `purchaseID`;

ALTER TABLE `sale`
  ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `saleID`;

UPDATE `item` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `vendor` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `customer` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `purchase` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;
UPDATE `sale` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0;

ALTER TABLE `item`
  ADD KEY `idx_storeID` (`storeID`);

ALTER TABLE `vendor`
  ADD KEY `idx_storeID` (`storeID`);

ALTER TABLE `customer`
  ADD KEY `idx_storeID` (`storeID`);

ALTER TABLE `purchase`
  ADD KEY `idx_storeID` (`storeID`);

ALTER TABLE `sale`
  ADD KEY `idx_storeID` (`storeID`);

ALTER TABLE `stores`
  MODIFY `storeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

COMMIT;
