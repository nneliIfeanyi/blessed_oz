<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

if (isset($_POST['purchaseItems']) || isset($_POST['purchaseDetailsItemNumber'])) {
	$purchaseDetailsPurchaseDate = isset($_POST['purchaseDetailsPurchaseDate']) ? trim(htmlentities($_POST['purchaseDetailsPurchaseDate'])) : date('Y-m-d');
	$purchaseDetailsVendorName = isset($_POST['purchaseDetailsVendorName']) ? trim(htmlentities($_POST['purchaseDetailsVendorName'])) : '';
	$purchaseItems = [];

	if (isset($_POST['purchaseItems'])) {
		$purchaseItems = json_decode($_POST['purchaseItems'], true);
	}

	if (!is_array($purchaseItems) || empty($purchaseItems)) {
		if (isset($_POST['purchaseDetailsItemNumber'])) {
			$purchaseItems[] = [
				'itemNumber' => trim(htmlentities($_POST['purchaseDetailsItemNumber'])),
				'itemName' => trim(htmlentities($_POST['purchaseDetailsItemName'])),
				'quantity' => trim(htmlentities($_POST['purchaseDetailsQuantity'])),
				'unitPrice' => trim(htmlentities($_POST['purchaseDetailsUnitPrice']))
			];
		}
	}

	if (empty($purchaseItems)) {
		echo json_encode(['success' => false, 'message' => 'Please add at least one item before saving the purchase.']);
		exit();
	}

	if ($purchaseDetailsVendorName == '') {
		echo json_encode(['success' => false, 'message' => 'Please select a vendor.']);
		exit();
	}

	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS purchase_headers (
			id INT(11) NOT NULL AUTO_INCREMENT,
			storeID INT(11) NOT NULL DEFAULT '1',
			transactionReference VARCHAR(50) NOT NULL,
			vendorName VARCHAR(255) DEFAULT NULL,
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

		$columnCheck = $conn->query("SHOW COLUMNS FROM purchase LIKE 'transactionReference'");
		if ($columnCheck->rowCount() === 0) {
			$conn->exec("ALTER TABLE purchase ADD COLUMN transactionReference VARCHAR(255) DEFAULT NULL");
		}

		$purchaseHeadersStoreColumn = $conn->query("SHOW COLUMNS FROM purchase_headers LIKE 'storeID'");
		if ($purchaseHeadersStoreColumn->rowCount() === 0) {
			$conn->exec("ALTER TABLE purchase_headers ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER id");
		}
		$purchaseItemsStoreColumn = $conn->query("SHOW COLUMNS FROM purchase_items LIKE 'storeID'");
		if ($purchaseItemsStoreColumn->rowCount() === 0) {
			$conn->exec("ALTER TABLE purchase_items ADD COLUMN storeID INT(11) NOT NULL DEFAULT '1' AFTER purchaseItemID");
		}

		$vendorIDsql = 'SELECT vendorID FROM vendor WHERE fullName = :fullName AND storeID = :storeID';
		$vendorIDStatement = $conn->prepare($vendorIDsql);
		$vendorIDStatement->execute(['fullName' => $purchaseDetailsVendorName, 'storeID' => $activeStoreID]);
		$vendorRow = $vendorIDStatement->fetch(PDO::FETCH_ASSOC);
		if (!$vendorRow) {
			echo json_encode(['success' => false, 'message' => 'Vendor does not exist.']);
			exit();
		}
		$vendorID = $vendorRow['vendorID'];

		$conn->beginTransaction();
		$transactionReference = '';
		$transactionReferenceCheck = $conn->prepare('SELECT COUNT(*) FROM (SELECT saleReference AS transactionReference FROM sale_headers WHERE saleReference = :transactionReference UNION ALL SELECT transactionReference FROM purchase_headers WHERE transactionReference = :transactionReference) AS existingTransactions');
		for ($attempt = 0; $attempt < 20; $attempt++) {
			$candidateReference = 'TXN-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
			$transactionReferenceCheck->execute(['transactionReference' => $candidateReference]);
			if ((int) $transactionReferenceCheck->fetchColumn() === 0) {
				$transactionReference = $candidateReference;
				break;
			}
		}
		if ($transactionReference === '') {
			throw new Exception('Unable to generate a unique transaction ID. Please try again.');
		}

		$insertHeaderSql = 'INSERT INTO purchase_headers(storeID, transactionReference, vendorName, purchaseDate, createdAt) VALUES(:storeID, :transactionReference, :vendorName, :purchaseDate, NOW())';
		$insertHeaderStatement = $conn->prepare($insertHeaderSql);
		$insertHeaderStatement->execute(['storeID' => $activeStoreID, 'transactionReference' => $transactionReference, 'vendorName' => $purchaseDetailsVendorName, 'purchaseDate' => $purchaseDetailsPurchaseDate]);

		foreach ($purchaseItems as $itemData) {
			$itemNumber = isset($itemData['itemNumber']) ? trim((string) $itemData['itemNumber']) : '';
			$itemName = isset($itemData['itemName']) ? trim((string) $itemData['itemName']) : '';
			$quantity = isset($itemData['quantity']) ? trim((string) $itemData['quantity']) : '';
			$unitPrice = isset($itemData['unitPrice']) ? trim((string) $itemData['unitPrice']) : '';

			if ($itemNumber == '') {
				throw new Exception('Please enter an item number for each row.');
			}
			if ($quantity === '' || filter_var($quantity, FILTER_VALIDATE_INT) === false) {
				throw new Exception('Please enter a valid quantity for each row.');
			}
			if ($unitPrice === '' || filter_var($unitPrice, FILTER_VALIDATE_FLOAT) === false) {
				throw new Exception('Please enter a valid unit price for each row.');
			}

			$stockSql = 'SELECT stock FROM item WHERE itemNumber=:itemNumber AND storeID = :storeID';
			$stockStatement = $conn->prepare($stockSql);
			$stockStatement->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
			if ($stockStatement->rowCount() < 1) {
				throw new Exception('Item does not exist in DB.');
			}
			$stockRow = $stockStatement->fetch(PDO::FETCH_ASSOC);
			$initialStock = (int) $stockRow['stock'];
			$newStock = $initialStock + (int) $quantity;

			$updateStockSql = 'UPDATE item SET stock = :stock WHERE itemNumber = :itemNumber AND storeID = :storeID';
			$updateStockStatement = $conn->prepare($updateStockSql);
			$updateStockStatement->execute(['stock' => $newStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);

			$lineTotal = round(((float) $unitPrice * (int) $quantity), 2);
			$insertPurchaseSql = 'INSERT INTO purchase(storeID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference) VALUES(:storeID, :itemNumber, :purchaseDate, :itemName, :unitPrice, :quantity, :vendorName, :vendorID, :transactionReference)';
			$insertPurchaseStatement = $conn->prepare($insertPurchaseSql);
			$insertPurchaseStatement->execute(['storeID' => $activeStoreID, 'itemNumber' => $itemNumber, 'purchaseDate' => $purchaseDetailsPurchaseDate, 'itemName' => $itemName, 'unitPrice' => (float) $unitPrice, 'quantity' => (int) $quantity, 'vendorName' => $purchaseDetailsVendorName, 'vendorID' => $vendorID, 'transactionReference' => $transactionReference]);

			$insertPurchaseItemSql = 'INSERT INTO purchase_items(storeID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt) VALUES(:storeID, :transactionReference, :itemNumber, :itemName, :quantity, :unitPrice, :lineTotal, NOW())';
			$insertPurchaseItemStatement = $conn->prepare($insertPurchaseItemSql);
			$insertPurchaseItemStatement->execute(['storeID' => $activeStoreID, 'transactionReference' => $transactionReference, 'itemNumber' => $itemNumber, 'itemName' => $itemName, 'quantity' => (int) $quantity, 'unitPrice' => (float) $unitPrice, 'lineTotal' => $lineTotal]);
		}

		$conn->commit();
		echo json_encode(['success' => true, 'message' => 'Purchase transaction saved successfully.', 'transactionReference' => $transactionReference]);
	} catch (Exception $e) {
		$conn->rollBack();
		echo json_encode(['success' => false, 'message' => $e->getMessage()]);
	}
}
