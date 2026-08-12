<?php
// Connect to database
try {
	$conn = new PDO(DSN, DB_USER, DB_PASSWORD);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$schemaStatements = [
		"CREATE TABLE IF NOT EXISTS `stores` (
				`storeID` int(11) NOT NULL AUTO_INCREMENT,
				`storeName` varchar(120) NOT NULL,
				`storeCode` varchar(40) DEFAULT NULL,
				`status` varchar(20) NOT NULL DEFAULT 'Active',
				`createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`storeID`),
				UNIQUE KEY `storeCode` (`storeCode`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `user` (
				`userID` int(11) NOT NULL AUTO_INCREMENT,
				`fullName` varchar(255) NOT NULL,
				`username` varchar(255) NOT NULL,
				`password` varchar(255) NOT NULL,
				`status` varchar(255) NOT NULL DEFAULT 'Active',
				PRIMARY KEY (`userID`),
				UNIQUE KEY `username` (`username`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `item` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `vendor` (
				`vendorID` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`fullName` varchar(255) NOT NULL,
				`email` varchar(255) DEFAULT NULL,
				`mobile` varchar(255) DEFAULT NULL,
				`status` varchar(255) NOT NULL DEFAULT 'Active',
				PRIMARY KEY (`vendorID`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `customer` (
				`customerID` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`fullName` varchar(255) NOT NULL,
				`email` varchar(255) DEFAULT NULL,
				`mobile` varchar(255) DEFAULT NULL,
				`address` text DEFAULT NULL,
				`status` varchar(255) NOT NULL DEFAULT 'Active',
				PRIMARY KEY (`customerID`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `purchase` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `sale` (
				`saleID` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`itemNumber` varchar(255) NOT NULL,
				`customerID` int(11) NOT NULL,
				`customerName` varchar(255) NOT NULL,
				`itemName` varchar(255) NOT NULL,
				`saleDate` date NOT NULL,
				`discount` float NOT NULL DEFAULT '0',
				`quantity` int(11) NOT NULL DEFAULT '0',
				`unitPrice` float NOT NULL DEFAULT '0',
				`reason` varchar(255) NOT NULL DEFAULT 'Sales',
				PRIMARY KEY (`saleID`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `purchase_headers` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`transactionReference` varchar(50) NOT NULL,
				`vendorName` varchar(255) DEFAULT NULL,
				`purchaseDate` date NOT NULL,
				`createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				UNIQUE KEY `transactionReference` (`transactionReference`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `purchase_items` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `sale_headers` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`saleReference` varchar(50) NOT NULL,
				`customerID` int(11) DEFAULT NULL,
				`customerName` varchar(255) DEFAULT NULL,
				`saleDate` date NOT NULL,
				`amountPaid` float NOT NULL DEFAULT '0',
				`createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				UNIQUE KEY `saleReference` (`saleReference`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `sale_items` (
				`saleItemID` int(11) NOT NULL AUTO_INCREMENT,
				`storeID` int(11) NOT NULL DEFAULT '1',
				`saleReference` varchar(50) NOT NULL,
				`itemNumber` varchar(255) NOT NULL,
				`itemName` varchar(255) NOT NULL,
				`discount` float NOT NULL DEFAULT '0',
				`reason` varchar(255) NOT NULL DEFAULT 'Sales',
				`quantity` int(11) NOT NULL DEFAULT '0',
				`unitPrice` float NOT NULL DEFAULT '0',
				`lineTotal` float NOT NULL DEFAULT '0',
				`createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`saleItemID`),
				KEY `saleReference` (`saleReference`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `customer_ledger` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `customer_payments` (
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
			) ENGINE=InnoDB DEFAULT CHARSET=latin1"
	];

	foreach ($schemaStatements as $statement) {
		$conn->exec($statement);
	}

	$conn->exec("INSERT INTO `stores` (`storeID`, `storeName`, `storeCode`, `status`) VALUES (1, 'Main Store', 'MAIN', 'Active') ON DUPLICATE KEY UPDATE `storeName` = VALUES(`storeName`), `status` = VALUES(`status`)");

	$columnMigrations = [
		['table' => 'sale_items', 'column' => 'discount', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `discount` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_items', 'column' => 'reason', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `reason` varchar(255) NOT NULL DEFAULT 'Sales'"],
		['table' => 'sale_items', 'column' => 'lineTotal', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `lineTotal` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_headers', 'column' => 'amountPaid', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `amountPaid` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_headers', 'column' => 'saleDate', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `saleDate` date NOT NULL"],
		['table' => 'sale_headers', 'column' => 'customerName', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `customerName` varchar(255) DEFAULT NULL"],
		['table' => 'item', 'column' => 'storeID', 'definition' => "ALTER TABLE `item` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `productID`"],
		['table' => 'vendor', 'column' => 'storeID', 'definition' => "ALTER TABLE `vendor` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `vendorID`"],
		['table' => 'customer', 'column' => 'storeID', 'definition' => "ALTER TABLE `customer` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `customerID`"],
		['table' => 'purchase', 'column' => 'storeID', 'definition' => "ALTER TABLE `purchase` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `purchaseID`"],
		['table' => 'sale', 'column' => 'storeID', 'definition' => "ALTER TABLE `sale` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `saleID`"],
		['table' => 'purchase_headers', 'column' => 'storeID', 'definition' => "ALTER TABLE `purchase_headers` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `id`"],
		['table' => 'purchase_items', 'column' => 'storeID', 'definition' => "ALTER TABLE `purchase_items` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `purchaseItemID`"],
		['table' => 'sale_headers', 'column' => 'storeID', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `id`"],
		['table' => 'sale_items', 'column' => 'storeID', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `saleItemID`"],
		['table' => 'customer_ledger', 'column' => 'storeID', 'definition' => "ALTER TABLE `customer_ledger` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `ledgerID`"],
		['table' => 'customer_payments', 'column' => 'storeID', 'definition' => "ALTER TABLE `customer_payments` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `paymentID`"],
		['table' => 'customer_payments', 'column' => 'saleReference', 'definition' => "ALTER TABLE `customer_payments` ADD COLUMN `saleReference` varchar(50) DEFAULT NULL AFTER `customerID`"]
	];

	foreach ($columnMigrations as $migration) {
		$columnCheck = $conn->query("SHOW COLUMNS FROM `{$migration['table']}` LIKE '{$migration['column']}'");
		if ($columnCheck->rowCount() === 0) {
			try {
				$conn->exec($migration['definition']);
			} catch (PDOException $e) {
				// Ignore migration errors for already-updated tables.
			}
		}
	}

	$storeTables = ['item', 'vendor', 'customer', 'purchase', 'sale', 'purchase_headers', 'purchase_items', 'sale_headers', 'sale_items', 'customer_ledger', 'customer_payments'];
	foreach ($storeTables as $tableName) {
		try {
			$conn->exec("UPDATE `{$tableName}` SET `storeID` = 1 WHERE `storeID` IS NULL OR `storeID` = 0");
		} catch (PDOException $e) {
			// Keep backward compatibility if some optional tables are absent.
		}

		try {
			$storeIndexCheck = $conn->query("SHOW INDEX FROM `{$tableName}` WHERE Key_name = 'idx_storeID'");
			if ($storeIndexCheck->rowCount() === 0) {
				$conn->exec("ALTER TABLE `{$tableName}` ADD INDEX `idx_storeID` (`storeID`)");
			}
		} catch (PDOException $e) {
			// Ignore index migration errors to avoid blocking startup.
		}
	}

	$userCountStatement = $conn->query('SELECT COUNT(*) FROM `user`');
	if ((int) $userCountStatement->fetchColumn() === 0) {
		$conn->exec("INSERT INTO `user` (`fullName`, `username`, `password`, `status`) VALUES ('admin', 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Active')");
	}
} catch (PDOException $e) {
	$errorMessage = $e->getMessage();
	echo $errorMessage;
	exit();
}
