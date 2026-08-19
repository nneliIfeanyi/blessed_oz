<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

header('Content-Type: application/json');

if (isset($_POST['saleItems']) || isset($_POST['saleDetailsItemNumber'])) {
	$customerID = isset($_POST['saleDetailsCustomerID']) ? trim(htmlentities($_POST['saleDetailsCustomerID'])) : '';
	$customerName = isset($_POST['saleDetailsCustomerName']) ? trim(htmlentities($_POST['saleDetailsCustomerName'])) : '';
	$saleDate = isset($_POST['saleDetailsSaleDate']) ? trim(htmlentities($_POST['saleDetailsSaleDate'])) : date('Y-m-d');
	$amountPaid = isset($_POST['saleDetailsAmountPaid']) ? trim(htmlentities($_POST['saleDetailsAmountPaid'])) : '0';

	$saleItems = [];
	if (isset($_POST['saleItems'])) {
		$saleItems = json_decode($_POST['saleItems'], true);
	}

	if (!is_array($saleItems) || empty($saleItems)) {
		if (isset($_POST['saleDetailsItemNumber'])) {
			$saleItems[] = [
				'itemNumber' => trim(htmlentities($_POST['saleDetailsItemNumber'])),
				'itemName' => trim(htmlentities($_POST['saleDetailsItemName'])),
				'discount' => trim(htmlentities($_POST['saleDetailsDiscount'])),
				'quantity' => trim(htmlentities($_POST['saleDetailsQuantity'])),
				'unitPrice' => trim(htmlentities($_POST['saleDetailsUnitPrice'])),
				'reason' => isset($_POST['saleDetailsReason']) ? trim(htmlentities($_POST['saleDetailsReason'])) : 'Sales',
			];
		}
	}

	if (empty($saleItems)) {
		echo json_encode(['success' => false, 'message' => 'Please add at least one item before saving the stock out.']);
		exit();
	}

	if ($customerID == '') {
		echo json_encode(['success' => false, 'message' => 'Please enter a Customer ID.']);
		exit();
	}

	if (filter_var($customerID, FILTER_VALIDATE_INT) === false) {
		echo json_encode(['success' => false, 'message' => 'Please enter a valid Customer ID.']);
		exit();
	}

	if ($amountPaid !== '' && filter_var($amountPaid, FILTER_VALIDATE_FLOAT) === false) {
		echo json_encode(['success' => false, 'message' => 'Please enter a valid amount paid.']);
		exit();
	}

	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS sale_headers (
			id INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			saleReference VARCHAR(50) NOT NULL,
			customerID INT(11) NOT NULL,
			customerName VARCHAR(255) DEFAULT NULL,
			saleDate DATE NOT NULL,
			amountPaid FLOAT NOT NULL DEFAULT '0',
			createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY saleReference (saleReference)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$conn->exec("CREATE TABLE IF NOT EXISTS sale_items (
			saleItemID INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			saleReference VARCHAR(50) NOT NULL,
			itemNumber VARCHAR(255) NOT NULL,
			itemName VARCHAR(255) NOT NULL,
			discount FLOAT NOT NULL DEFAULT '0',
			quantity INT(11) NOT NULL DEFAULT '0',
			unitPrice FLOAT NOT NULL DEFAULT '0',
			reason VARCHAR(255) NOT NULL DEFAULT 'Sales',
			lineTotal FLOAT NOT NULL DEFAULT '0',
			createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (saleItemID),
			KEY saleReference (saleReference)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$columnCheck = $conn->query("SHOW COLUMNS FROM sale LIKE 'saleReference'");
		if ($columnCheck->rowCount() === 0) {
			$conn->exec("ALTER TABLE sale ADD COLUMN saleReference VARCHAR(255) DEFAULT NULL");
		}

		$saleHeadersStoreColumn = $conn->query("SHOW COLUMNS FROM sale_headers LIKE 'storeID'");
		if ($saleHeadersStoreColumn->rowCount() === 0) {
			$conn->exec("ALTER TABLE sale_headers ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER id");
		}
		$saleItemsStoreColumn = $conn->query("SHOW COLUMNS FROM sale_items LIKE 'storeID'");
		if ($saleItemsStoreColumn->rowCount() === 0) {
			$conn->exec("ALTER TABLE sale_items ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER saleItemID");
		}

		$customerSql = 'SELECT fullName FROM customer WHERE customerID = :customerID AND storeID = :storeID';
		$customerStatement = $conn->prepare($customerSql);
		$customerStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
		if ($customerStatement->rowCount() < 1) {
			echo json_encode(['success' => false, 'message' => 'Customer does not exist.']);
			exit();
		}
		$customerRow = $customerStatement->fetch(PDO::FETCH_ASSOC);
		$customerName = $customerName !== '' ? $customerName : $customerRow['fullName'];

		$conn->beginTransaction();
		$saleReference = '';
		$transactionReferenceCheck = $conn->prepare('SELECT COUNT(*) FROM (SELECT saleReference AS transactionReference FROM sale_headers WHERE saleReference = :transactionReference UNION ALL SELECT transactionReference FROM purchase_headers WHERE transactionReference = :transactionReference) AS existingTransactions');
		for ($attempt = 0; $attempt < 20; $attempt++) {
			$candidateReference = 'TXN-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
			$transactionReferenceCheck->execute(['transactionReference' => $candidateReference]);
			if ((int) $transactionReferenceCheck->fetchColumn() === 0) {
				$saleReference = $candidateReference;
				break;
			}
		}
		if ($saleReference === '') {
			throw new Exception('Unable to generate a unique transaction ID. Please try again.');
		}

		$ensureSaleItemsColumns = [
			['discount', "ALTER TABLE `sale_items` ADD COLUMN `discount` float NOT NULL DEFAULT '0'"],
			['reason', "ALTER TABLE `sale_items` ADD COLUMN `reason` varchar(255) NOT NULL DEFAULT 'Sales'"],
			['lineTotal', "ALTER TABLE `sale_items` ADD COLUMN `lineTotal` float NOT NULL DEFAULT '0'"],
		];
		foreach ($ensureSaleItemsColumns as $columnDefinition) {
			$columnCheck = $conn->query("SHOW COLUMNS FROM `sale_items` LIKE '{$columnDefinition[0]}'");
			if ($columnCheck->rowCount() === 0) {
				$conn->exec($columnDefinition[1]);
			}
		}
		$totalSaleAmount = 0;

		$insertHeaderSql = 'INSERT INTO sale_headers(storeID, saleReference, customerID, customerName, saleDate, amountPaid, createdAt) VALUES(:storeID, :saleReference, :customerID, :customerName, :saleDate, :amountPaid, NOW())';
		$insertHeaderStatement = $conn->prepare($insertHeaderSql);
		$insertHeaderStatement->execute(['storeID' => $activeStoreID, 'saleReference' => $saleReference, 'customerID' => $customerID, 'customerName' => $customerName, 'saleDate' => $saleDate, 'amountPaid' => (float) $amountPaid]);

		foreach ($saleItems as $itemData) {
			$itemNumber = isset($itemData['itemNumber']) ? trim((string) $itemData['itemNumber']) : '';
			$itemName = isset($itemData['itemName']) ? trim((string) $itemData['itemName']) : '';
			$discount = isset($itemData['discount']) ? trim((string) $itemData['discount']) : '0';
			$quantity = isset($itemData['quantity']) ? trim((string) $itemData['quantity']) : '0';
			$unitPrice = isset($itemData['unitPrice']) ? trim((string) $itemData['unitPrice']) : '0';
			$reason = isset($itemData['reason']) ? trim((string) $itemData['reason']) : 'Sales';

			if ($itemNumber == '') {
				throw new Exception('Please enter an item number for each row.');
			}
			if ($quantity === '' || filter_var($quantity, FILTER_VALIDATE_INT) === false) {
				throw new Exception('Please enter a valid quantity for each row.');
			}
			if ($unitPrice === '' || filter_var($unitPrice, FILTER_VALIDATE_FLOAT) === false) {
				throw new Exception('Please enter a valid unit price for each row.');
			}
			if ($discount !== '' && filter_var($discount, FILTER_VALIDATE_FLOAT) === false) {
				throw new Exception('Please enter a valid discount for each row.');
			}

			$stockSql = 'SELECT stock FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID';
			$stockStatement = $conn->prepare($stockSql);
			$stockStatement->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
			if ($stockStatement->rowCount() < 1) {
				throw new Exception('Item does not exist in DB.');
			}
			$stockRow = $stockStatement->fetch(PDO::FETCH_ASSOC);
			$currentStock = (int) $stockRow['stock'];
			$quantityInt = (int) $quantity;
			if ($currentStock <= 0 || $currentStock < $quantityInt) {
				throw new Exception('Not enough stock available for this sale.');
			}

			$newStock = $currentStock - $quantityInt;
			$stockUpdateSql = 'UPDATE item SET stock = :stock WHERE itemNumber = :itemNumber AND storeID = :storeID';
			$stockUpdateStatement = $conn->prepare($stockUpdateSql);
			$stockUpdateStatement->execute(['stock' => $newStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);

			$lineTotal = round(((float) $unitPrice * ((100 - (float) $discount) / 100) * $quantityInt), 2);
			$totalSaleAmount += $lineTotal;

			$insertSaleItemSql = 'INSERT INTO sale_items(storeID, saleReference, itemNumber, itemName, discount, quantity, unitPrice, reason, lineTotal, createdAt) VALUES(:storeID, :saleReference, :itemNumber, :itemName, :discount, :quantity, :unitPrice, :reason, :lineTotal, NOW())';
			$insertSaleItemStatement = $conn->prepare($insertSaleItemSql);
			$insertSaleItemStatement->execute(['storeID' => $activeStoreID, 'saleReference' => $saleReference, 'itemNumber' => $itemNumber, 'itemName' => $itemName, 'discount' => (float) $discount, 'quantity' => $quantityInt, 'unitPrice' => (float) $unitPrice, 'reason' => $reason, 'lineTotal' => $lineTotal]);
		}

		$pendingBalance = round(max(0, $totalSaleAmount - (float) $amountPaid), 2);
		if ($pendingBalance > 0) {
			try {
				$ledgerBalanceSql = 'SELECT COALESCE(balanceAfter, 0) AS balanceAfter FROM customer_ledger WHERE customerID = :customerID AND storeID = :storeID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1';
				$ledgerBalanceStatement = $conn->prepare($ledgerBalanceSql);
				$ledgerBalanceStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
				$lastBalance = $ledgerBalanceStatement->fetch(PDO::FETCH_ASSOC);
				$currentBalance = isset($lastBalance['balanceAfter']) ? (float) $lastBalance['balanceAfter'] : 0;
				$newLedgerBalance = round($currentBalance + $pendingBalance, 2);
				$ledgerInsertSql = 'INSERT INTO customer_ledger(storeID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note) VALUES(:storeID, :customerID, :saleID, :entryType, :amount, :balanceAfter, :entryDate, :note)';
				$ledgerInsertStatement = $conn->prepare($ledgerInsertSql);
				$ledgerInsertStatement->execute(['storeID' => $activeStoreID, 'customerID' => $customerID, 'saleID' => null, 'entryType' => 'Sale', 'amount' => $pendingBalance, 'balanceAfter' => $newLedgerBalance, 'entryDate' => $saleDate, 'note' => 'Sale on credit - ' . $saleReference]);
			} catch (PDOException $e) {
				// Credit tables may not be available yet; still allow the sale to be recorded.
			}
		}

		$conn->commit();
		echo json_encode(['success' => true, 'message' => 'Stock out saved successfully.', 'saleReference' => $saleReference]);
	} catch (Exception $e) {
		$conn->rollBack();
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
}
