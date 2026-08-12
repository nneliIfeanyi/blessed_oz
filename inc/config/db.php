<?php
// Connect to database
try {
	$conn = new PDO(DSN, DB_USER, DB_PASSWORD);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$schemaStatements = [
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
				`fullName` varchar(255) NOT NULL,
				`email` varchar(255) DEFAULT NULL,
				`mobile` varchar(255) DEFAULT NULL,
				`status` varchar(255) NOT NULL DEFAULT 'Active',
				PRIMARY KEY (`vendorID`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `customer` (
				`customerID` int(11) NOT NULL AUTO_INCREMENT,
				`fullName` varchar(255) NOT NULL,
				`email` varchar(255) DEFAULT NULL,
				`mobile` varchar(255) DEFAULT NULL,
				`address` text DEFAULT NULL,
				`status` varchar(255) NOT NULL DEFAULT 'Active',
				PRIMARY KEY (`customerID`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `purchase` (
				`purchaseID` int(11) NOT NULL AUTO_INCREMENT,
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
				`transactionReference` varchar(50) NOT NULL,
				`vendorName` varchar(255) DEFAULT NULL,
				`purchaseDate` date NOT NULL,
				`createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				UNIQUE KEY `transactionReference` (`transactionReference`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1",

		"CREATE TABLE IF NOT EXISTS `purchase_items` (
				`purchaseItemID` int(11) NOT NULL AUTO_INCREMENT,
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
				`customerID` int(11) NOT NULL,
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

	$columnMigrations = [
		['table' => 'sale_items', 'column' => 'discount', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `discount` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_items', 'column' => 'reason', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `reason` varchar(255) NOT NULL DEFAULT 'Sales'"],
		['table' => 'sale_items', 'column' => 'lineTotal', 'definition' => "ALTER TABLE `sale_items` ADD COLUMN `lineTotal` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_headers', 'column' => 'amountPaid', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `amountPaid` float NOT NULL DEFAULT '0'"],
		['table' => 'sale_headers', 'column' => 'saleDate', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `saleDate` date NOT NULL"],
		['table' => 'sale_headers', 'column' => 'customerName', 'definition' => "ALTER TABLE `sale_headers` ADD COLUMN `customerName` varchar(255) DEFAULT NULL"]
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

	$userCountStatement = $conn->query('SELECT COUNT(*) FROM `user`');
	if ((int) $userCountStatement->fetchColumn() === 0) {
		$conn->exec("INSERT INTO `user` (`fullName`, `username`, `password`, `status`) VALUES ('admin', 'admin', '21232f297a57a5a743894a0e4a801fc3', 'Active')");
	}
} catch (PDOException $e) {
	$errorMessage = $e->getMessage();
	echo $errorMessage;
	exit();
}
