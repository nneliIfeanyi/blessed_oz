<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');

header('Content-Type: application/json');

// Re-read subscription from DB so admin-granted Pro works without re-login
if (isset($_SESSION['userID']) && function_exists('loadUserSubscriptionSession')) {
	try {
		loadUserSubscriptionSession($conn, (int) $_SESSION['userID']);
	} catch (Exception $e) {
		// fall through to isProActive check
	}
}

// Verify user is logged in and Pro-active
if (!isset($_SESSION['userID']) || !isProActive()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Unauthorized. Pro subscription required.']);
	exit();
}

// Verify store session
try {
	ensureActiveStoreSession($conn);
} catch (Exception $e) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid store session.']);
	exit();
}

$activeStoreID = (int) $_SESSION['activeStoreID'];
$userID = (int) $_SESSION['userID'];

// Read and parse JSON payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!isset($data['transactions']) || !is_array($data['transactions'])) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
	exit();
}

$transactions = $data['transactions'];
$syncedIds = [];
$failedTransactions = [];

try {
	// Ensure sync_log exists (never drop — preserves history across sync runs)
	$conn->exec("CREATE TABLE IF NOT EXISTS sync_log (
		syncID INT(11) NOT NULL AUTO_INCREMENT,
		storeID INT(11) NOT NULL DEFAULT '1',
		userID INT(11) NOT NULL,
		clientReferenceId VARCHAR(255) NOT NULL UNIQUE,
		transactionType VARCHAR(50) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'pending',
		responseReference VARCHAR(255),
		createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (syncID),
		KEY status (status)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1");

	// Process each transaction
	foreach ($transactions as $transaction) {
		$clientRefId = isset($transaction['clientReferenceId']) ? trim((string) $transaction['clientReferenceId']) : '';
		$transactionType = isset($transaction['type']) ? trim((string) $transaction['type']) : '';
		$payload = isset($transaction['payload']) ? $transaction['payload'] : null;

		// Validate
		if ($clientRefId === '' || !$transactionType || !is_array($payload)) {
			$failedTransactions[] = [
				'clientReferenceId' => $clientRefId,
				'error' => 'Invalid transaction format'
			];
			continue;
		}

		// Check idempotency: is this already synced?
		$idempotencyCheck = $conn->prepare('SELECT syncID, responseReference FROM sync_log WHERE clientReferenceId = :clientRefId LIMIT 1');
		$idempotencyCheck->execute(['clientRefId' => $clientRefId]);

		if ($idempotencyCheck->rowCount() > 0) {
			// Already synced, mark as done
			$existingSync = $idempotencyCheck->fetch(PDO::FETCH_ASSOC);
			if ($existingSync['responseReference'] !== null && $existingSync['responseReference'] !== '') {
				$syncedIds[] = $transaction['id'];
				continue;
			}
		}

		// Process the transaction based on type
		try {
			$responseReference = null;

			if ($transactionType === 'sale') {
				$responseReference = processSaleTransaction($conn, $activeStoreID, $payload);
			} elseif ($transactionType === 'purchase') {
				$responseReference = processPurchaseTransaction($conn, $activeStoreID, $payload);
			} else {
				throw new Exception('Unknown transaction type: ' . $transactionType);
			}

			// Log success
			$logStmt = $conn->prepare('INSERT INTO sync_log (storeID, userID, clientReferenceId, transactionType, status, responseReference) VALUES (:storeID, :userID, :clientRefId, :type, :status, :responseRef)');
			$logStmt->execute([
				'storeID' => $activeStoreID,
				'userID' => $userID,
				'clientRefId' => $clientRefId,
				'type' => $transactionType,
				'status' => 'synced',
				'responseRef' => $responseReference
			]);

			$syncedIds[] = $transaction['id'];
		} catch (Exception $e) {
			// Log failure
			$logStmt = $conn->prepare('INSERT INTO sync_log (storeID, userID, clientReferenceId, transactionType, status, responseReference) VALUES (:storeID, :userID, :clientRefId, :type, :status, :responseRef)');
			$logStmt->execute([
				'storeID' => $activeStoreID,
				'userID' => $userID,
				'clientRefId' => $clientRefId,
				'type' => $transactionType,
				'status' => 'failed',
				'responseRef' => $e->getMessage()
			]);

			$failedTransactions[] = [
				'clientReferenceId' => $clientRefId,
				'error' => $e->getMessage()
			];
		}
	}

	echo json_encode([
		'success' => true,
		'synced_ids' => $syncedIds,
		'failed_count' => count($failedTransactions),
		'failed_transactions' => $failedTransactions
	]);
} catch (Exception $e) {
	http_response_code(500);
	error_log('Sync error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
	echo json_encode(['success' => false, 'message' => 'Sync error: ' . $e->getMessage()]);
}

function processSaleTransaction(PDO $conn, $activeStoreID, $payload)
{
	if (!isset($payload['customerID']) || !isset($payload['items'])) {
		throw new Exception('Invalid sale payload: missing customerID or items');
	}

	$customerID = (int) $payload['customerID'];
	$customerName = isset($payload['customerName']) ? trim((string) $payload['customerName']) : '';
	$saleDate = isset($payload['saleDate']) ? trim((string) $payload['saleDate']) : date('Y-m-d');
	$amountPaid = isset($payload['amountPaid']) ? (float) $payload['amountPaid'] : 0;
	$saleItems = $payload['items'];

	if (!is_array($saleItems) || empty($saleItems)) {
		throw new Exception('No sale items provided');
	}

	// Verify customer exists
	$customerCheck = $conn->prepare('SELECT fullName FROM customer WHERE customerID = :customerID AND storeID = :storeID');
	$customerCheck->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
	if ($customerCheck->rowCount() < 1) {
		throw new Exception('Customer does not exist');
	}
	$customerRow = $customerCheck->fetch(PDO::FETCH_ASSOC);
	$customerName = $customerName !== '' ? $customerName : $customerRow['fullName'];

	// Generate unique sale reference
	$saleReference = '';
	$transactionReferenceCheck = $conn->prepare('SELECT COUNT(*) FROM (SELECT saleReference AS transactionReference FROM sale_headers WHERE saleReference = :transactionReference UNION ALL SELECT transactionReference FROM purchase_headers WHERE transactionReference = :transactionReference) AS existingTransactions');
	for ($attempt = 0; $attempt < 20; $attempt++) {
		$candidateReference = 'SYNC-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
		$transactionReferenceCheck->execute(['transactionReference' => $candidateReference]);
		if ((int) $transactionReferenceCheck->fetchColumn() === 0) {
			$saleReference = $candidateReference;
			break;
		}
	}
	if ($saleReference === '') {
		throw new Exception('Unable to generate transaction ID');
	}

	// Ensure tables exist BEFORE transaction
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

		// Legacy `sale` table (some helpers still query it)
		$conn->exec("CREATE TABLE IF NOT EXISTS sale (
			saleID INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			itemNumber VARCHAR(255) NOT NULL,
			customerID INT(11) NOT NULL,
			customerName VARCHAR(255) NOT NULL,
			itemName VARCHAR(255) NOT NULL,
			saleDate DATE NOT NULL,
			saleReference VARCHAR(255) DEFAULT NULL,
			discount FLOAT NOT NULL DEFAULT '0',
			quantity INT(11) NOT NULL DEFAULT '0',
			unitPrice FLOAT NOT NULL DEFAULT '0',
			reason VARCHAR(255) NOT NULL DEFAULT 'Sales',
			PRIMARY KEY (saleID)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$saleRefCol = $conn->query("SHOW COLUMNS FROM sale LIKE 'saleReference'");
		if ($saleRefCol && $saleRefCol->rowCount() === 0) {
			$conn->exec("ALTER TABLE sale ADD COLUMN saleReference VARCHAR(255) DEFAULT NULL");
		}
		$saleStoreCol = $conn->query("SHOW COLUMNS FROM sale LIKE 'storeID'");
		if ($saleStoreCol && $saleStoreCol->rowCount() === 0) {
			$conn->exec("ALTER TABLE sale ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER saleID");
		}
	} catch (PDOException $e) {
		// Table creation errors; allow to continue
	}

	$conn->beginTransaction();

	try {
		// Insert sale header
		$insertHeaderSql = 'INSERT INTO sale_headers(storeID, saleReference, customerID, customerName, saleDate, amountPaid, createdAt) VALUES(:storeID, :saleReference, :customerID, :customerName, :saleDate, :amountPaid, NOW())';
		$insertHeaderStatement = $conn->prepare($insertHeaderSql);
		$insertHeaderStatement->execute([
			'storeID' => $activeStoreID,
			'saleReference' => $saleReference,
			'customerID' => $customerID,
			'customerName' => $customerName,
			'saleDate' => $saleDate,
			'amountPaid' => $amountPaid
		]);

		$totalSaleAmount = 0;

		// Insert sale items
		foreach ($saleItems as $itemData) {
			$itemNumber = isset($itemData['itemNumber']) ? trim((string) $itemData['itemNumber']) : '';
			$itemName = isset($itemData['itemName']) ? trim((string) $itemData['itemName']) : '';
			$discount = isset($itemData['discount']) ? (float) $itemData['discount'] : 0;
			$quantity = isset($itemData['quantity']) ? (int) $itemData['quantity'] : 0;
			$unitPrice = isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0;
			$reason = isset($itemData['reason']) ? trim((string) $itemData['reason']) : 'Sales';

			if ($itemNumber === '' || $quantity <= 0 || $unitPrice < 0) {
				throw new Exception('Invalid item data');
			}

			// Verify stock exists
			$stockCheck = $conn->prepare('SELECT stock FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID');
			$stockCheck->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
			if ($stockCheck->rowCount() < 1) {
				throw new Exception('Item ' . $itemNumber . ' does not exist');
			}
			$stockRow = $stockCheck->fetch(PDO::FETCH_ASSOC);
			$currentStock = (int) $stockRow['stock'];

			if ($currentStock < $quantity) {
				throw new Exception('Insufficient stock for item ' . $itemNumber);
			}

			// Update stock
			$newStock = $currentStock - $quantity;
			$stockUpdateSql = 'UPDATE item SET stock = :stock WHERE itemNumber = :itemNumber AND storeID = :storeID';
			$stockUpdateStatement = $conn->prepare($stockUpdateSql);
			$stockUpdateStatement->execute(['stock' => $newStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);

			// Calculate line total
			$lineTotal = round((($unitPrice * ((100 - $discount) / 100)) * $quantity), 2);
			$totalSaleAmount += $lineTotal;

			// Insert sale item (normalized — used by sale details/reports UI)
			$insertItemSql = 'INSERT INTO sale_items(storeID, saleReference, itemNumber, itemName, discount, quantity, unitPrice, reason, lineTotal, createdAt) VALUES(:storeID, :saleReference, :itemNumber, :itemName, :discount, :quantity, :unitPrice, :reason, :lineTotal, NOW())';
			$insertItemStatement = $conn->prepare($insertItemSql);
			$insertItemStatement->execute([
				'storeID' => $activeStoreID,
				'saleReference' => $saleReference,
				'itemNumber' => $itemNumber,
				'itemName' => $itemName,
				'discount' => $discount,
				'quantity' => $quantity,
				'unitPrice' => $unitPrice,
				'reason' => $reason,
				'lineTotal' => $lineTotal
			]);

			// Legacy `sale` table for helpers that still query it
			$insertSaleSql = 'INSERT INTO sale(storeID, itemNumber, customerID, customerName, itemName, saleDate, saleReference, discount, quantity, unitPrice, reason) VALUES(:storeID, :itemNumber, :customerID, :customerName, :itemName, :saleDate, :saleReference, :discount, :quantity, :unitPrice, :reason)';
			$insertSaleStatement = $conn->prepare($insertSaleSql);
			$insertSaleStatement->execute([
				'storeID' => $activeStoreID,
				'itemNumber' => $itemNumber,
				'customerID' => $customerID,
				'customerName' => $customerName,
				'itemName' => $itemName,
				'saleDate' => $saleDate,
				'saleReference' => $saleReference,
				'discount' => $discount,
				'quantity' => $quantity,
				'unitPrice' => $unitPrice,
				'reason' => $reason
			]);
		}

		// Handle pending balance for credit
		$pendingBalance = round(max(0, $totalSaleAmount - $amountPaid), 2);
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
				$ledgerInsertStatement->execute([
					'storeID' => $activeStoreID,
					'customerID' => $customerID,
					'saleID' => null,
					'entryType' => 'Sale',
					'amount' => $pendingBalance,
					'balanceAfter' => $newLedgerBalance,
					'entryDate' => $saleDate,
					'note' => 'Offline sale - ' . $saleReference
				]);
			} catch (PDOException $e) {
				// Credit tables may not exist yet; allow sale to proceed
			}
		}

		$conn->commit();
		return $saleReference;
	} catch (Exception $e) {
		$conn->rollBack();
		throw $e;
	}
}

function processPurchaseTransaction(PDO $conn, $activeStoreID, $payload)
{
	if (!isset($payload['vendorName']) || !isset($payload['items'])) {
		throw new Exception('Invalid purchase payload: missing vendorName or items');
	}

	$vendorName = trim((string) $payload['vendorName']);
	$purchaseDate = isset($payload['purchaseDate']) ? trim((string) $payload['purchaseDate']) : date('Y-m-d');
	$purchaseItems = $payload['items'];

	if ($vendorName === '' || !is_array($purchaseItems) || empty($purchaseItems)) {
		throw new Exception('Invalid purchase data');
	}

	// Resolve vendor (same as online insertPurchase) — required for legacy `purchase` table
	$vendorIDsql = 'SELECT vendorID FROM vendor WHERE fullName = :fullName AND storeID = :storeID';
	$vendorIDStatement = $conn->prepare($vendorIDsql);
	$vendorIDStatement->execute(['fullName' => $vendorName, 'storeID' => $activeStoreID]);
	$vendorRow = $vendorIDStatement->fetch(PDO::FETCH_ASSOC);
	if (!$vendorRow) {
		throw new Exception('Vendor does not exist: ' . $vendorName);
	}
	$vendorID = (int) $vendorRow['vendorID'];

	// Generate unique purchase reference
	$purchaseReference = '';
	$transactionReferenceCheck = $conn->prepare('SELECT COUNT(*) FROM (SELECT transactionReference FROM purchase_headers WHERE transactionReference = :transactionReference UNION ALL SELECT saleReference FROM sale_headers WHERE saleReference = :transactionReference) AS existingTransactions');
	for ($attempt = 0; $attempt < 20; $attempt++) {
		$candidateReference = 'SYNC-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
		$transactionReferenceCheck->execute(['transactionReference' => $candidateReference]);
		if ((int) $transactionReferenceCheck->fetchColumn() === 0) {
			$purchaseReference = $candidateReference;
			break;
		}
	}
	if ($purchaseReference === '') {
		throw new Exception('Unable to generate transaction ID');
	}

	// Ensure tables exist BEFORE transaction (headers/items + legacy purchase used by UI lists)
	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS purchase_headers (
			id INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			transactionReference VARCHAR(50) NOT NULL,
			vendorName VARCHAR(255) NOT NULL,
			purchaseDate DATE NOT NULL,
			createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY transactionReference (transactionReference)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$conn->exec("CREATE TABLE IF NOT EXISTS purchase_items (
			purchaseItemID INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			transactionReference VARCHAR(50) NOT NULL,
			itemNumber VARCHAR(255) NOT NULL,
			itemName VARCHAR(255) NOT NULL,
			quantity INT(11) NOT NULL DEFAULT '0',
			unitPrice FLOAT NOT NULL DEFAULT '0',
			lineTotal FLOAT NOT NULL DEFAULT '0',
			createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (purchaseItemID),
			KEY transactionReference (transactionReference)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$conn->exec("CREATE TABLE IF NOT EXISTS purchase (
			purchaseID INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			itemNumber VARCHAR(255) NOT NULL,
			purchaseDate DATE NOT NULL,
			itemName VARCHAR(255) NOT NULL,
			unitPrice FLOAT NOT NULL DEFAULT '0',
			quantity INT(11) NOT NULL DEFAULT '0',
			vendorName VARCHAR(255) NOT NULL DEFAULT '',
			vendorID INT(11) NOT NULL DEFAULT '0',
			transactionReference VARCHAR(255) DEFAULT NULL,
			PRIMARY KEY (purchaseID)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

		$columnCheck = $conn->query("SHOW COLUMNS FROM purchase LIKE 'transactionReference'");
		if ($columnCheck && $columnCheck->rowCount() === 0) {
			$conn->exec("ALTER TABLE purchase ADD COLUMN transactionReference VARCHAR(255) DEFAULT NULL");
		}
		$storeCol = $conn->query("SHOW COLUMNS FROM purchase LIKE 'storeID'");
		if ($storeCol && $storeCol->rowCount() === 0) {
			$conn->exec("ALTER TABLE purchase ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER purchaseID");
		}
	} catch (PDOException $e) {
		// Table creation errors; allow to continue
	}

	$conn->beginTransaction();

	try {
		// Insert purchase header
		$insertHeaderSql = 'INSERT INTO purchase_headers(storeID, transactionReference, vendorName, purchaseDate, createdAt) VALUES(:storeID, :transactionReference, :vendorName, :purchaseDate, NOW())';
		$insertHeaderStatement = $conn->prepare($insertHeaderSql);
		$insertHeaderStatement->execute([
			'storeID' => $activeStoreID,
			'transactionReference' => $purchaseReference,
			'vendorName' => $vendorName,
			'purchaseDate' => $purchaseDate
		]);

		// Insert purchase items + legacy purchase rows (UI/reports read FROM purchase)
		foreach ($purchaseItems as $itemData) {
			$itemNumber = isset($itemData['itemNumber']) ? trim((string) $itemData['itemNumber']) : '';
			$itemName = isset($itemData['itemName']) ? trim((string) $itemData['itemName']) : '';
			$quantity = isset($itemData['quantity']) ? (int) $itemData['quantity'] : 0;
			$unitPrice = isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0;

			if ($itemNumber === '' || $quantity <= 0 || $unitPrice < 0) {
				throw new Exception('Invalid purchase item data');
			}

			// Verify item exists
			$itemCheck = $conn->prepare('SELECT stock FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID');
			$itemCheck->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
			if ($itemCheck->rowCount() < 1) {
				throw new Exception('Item ' . $itemNumber . ' does not exist');
			}
			$itemRow = $itemCheck->fetch(PDO::FETCH_ASSOC);
			$currentStock = (int) $itemRow['stock'];

			// Update stock
			$newStock = $currentStock + $quantity;
			$stockUpdateSql = 'UPDATE item SET stock = :stock WHERE itemNumber = :itemNumber AND storeID = :storeID';
			$stockUpdateStatement = $conn->prepare($stockUpdateSql);
			$stockUpdateStatement->execute(['stock' => $newStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);

			// Calculate line total
			$lineTotal = round(($unitPrice * $quantity), 2);

			// purchase_items (normalized)
			$insertItemSql = 'INSERT INTO purchase_items(storeID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt) VALUES(:storeID, :transactionReference, :itemNumber, :itemName, :quantity, :unitPrice, :lineTotal, NOW())';
			$insertItemStatement = $conn->prepare($insertItemSql);
			$insertItemStatement->execute([
				'storeID' => $activeStoreID,
				'transactionReference' => $purchaseReference,
				'itemNumber' => $itemNumber,
				'itemName' => $itemName,
				'quantity' => $quantity,
				'unitPrice' => $unitPrice,
				'lineTotal' => $lineTotal
			]);

			// Legacy `purchase` table — what purchase details/reports query
			$insertPurchaseSql = 'INSERT INTO purchase(storeID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference) VALUES(:storeID, :itemNumber, :purchaseDate, :itemName, :unitPrice, :quantity, :vendorName, :vendorID, :transactionReference)';
			$insertPurchaseStatement = $conn->prepare($insertPurchaseSql);
			$insertPurchaseStatement->execute([
				'storeID' => $activeStoreID,
				'itemNumber' => $itemNumber,
				'purchaseDate' => $purchaseDate,
				'itemName' => $itemName,
				'unitPrice' => $unitPrice,
				'quantity' => $quantity,
				'vendorName' => $vendorName,
				'vendorID' => $vendorID,
				'transactionReference' => $purchaseReference
			]);
		}

		$conn->commit();
		return $purchaseReference;
	} catch (Exception $e) {
		$conn->rollBack();
		throw $e;
	}
}
?>
