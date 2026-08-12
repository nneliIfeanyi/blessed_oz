<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$settingsFile = '../../inc/config/site_settings.json';
$settings = [
	'enableProductDescription' => true
];
if (file_exists($settingsFile)) {
	$json = file_get_contents($settingsFile);
	if ($json !== false) {
		$decoded = json_decode($json, true);
		if (is_array($decoded)) {
			$settings = array_merge($settings, $decoded);
		}
	}
}

// Check if the POST query is received
if (isset($_POST['itemNumber'])) {

	$itemNumber = htmlentities($_POST['itemNumber']);
	$itemName = htmlentities($_POST['itemDetailsItemName']);
	$unitAsSold = isset($_POST['itemDetailsUnitAsSold']) ? trim(htmlentities($_POST['itemDetailsUnitAsSold'])) : 'pcs';
	$discount = htmlentities($_POST['itemDetailsDiscount']);
	$itemDetailsQuantity = isset($_POST['itemDetailsQuantity']) ? htmlentities($_POST['itemDetailsQuantity']) : '';
	$itemDetailsUnitPrice = htmlentities($_POST['itemDetailsUnitPrice']);
	$status = htmlentities($_POST['itemDetailsStatus']);
	$description = !empty($settings['enableProductDescription']) ? htmlentities($_POST['itemDetailsDescription']) : '';

	$initialStock = 0;
	$newStock = 0;

	// Check if mandatory fields are not empty
	if (!empty($itemNumber) && !empty($itemName) && isset($itemDetailsUnitPrice)) {

		// Sanitize item number
		$itemNumber = filter_var($itemNumber, FILTER_SANITIZE_STRING);
		if ($unitAsSold === '') {
			$unitAsSold = 'pcs';
		}

		if ($itemDetailsQuantity !== '' && $itemDetailsQuantity !== null) {
			if (filter_var($itemDetailsQuantity, FILTER_VALIDATE_INT) === false && filter_var($itemDetailsQuantity, FILTER_VALIDATE_INT) !== 0) {
				$errorAlert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid number for initial stock</div>';
				$data = ['alertMessage' => $errorAlert];
				echo json_encode($data);
				exit();
			}
		}

		// Validate unit price. It has to be a number or floating point value
		if (filter_var($itemDetailsUnitPrice, FILTER_VALIDATE_FLOAT) === 0.0 || filter_var($itemDetailsUnitPrice, FILTER_VALIDATE_FLOAT)) {
			// Valid unit price
		} else {
			// Unit price is not a valid number
			$errorAlert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid number for unit price</div>';
			$data = ['alertMessage' => $errorAlert];
			echo json_encode($data);
			exit();
		}

		// Validate discount only if it's provided
		if (!empty($discount)) {
			if (filter_var($discount, FILTER_VALIDATE_FLOAT) === false) {
				// Discount is not a valid floating point number
				$errorAlert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid discount amount</div>';
				$data = ['alertMessage' => $errorAlert];
				echo json_encode($data);
				exit();
			}
		}

		// Keep the existing stock unchanged when updating item details
		$stockSelectSql = 'SELECT stock FROM item WHERE itemNumber = :itemNumber';
		$stockSelectStatement = $conn->prepare($stockSelectSql);
		$stockSelectStatement->execute(['itemNumber' => $itemNumber]);
		if ($stockSelectStatement->rowCount() > 0) {
			$row = $stockSelectStatement->fetch(PDO::FETCH_ASSOC);
			$initialStock = $row['stock'];
			$newStock = $initialStock;
		} else {
			// Item is not in DB. Therefore, stop the update and quit
			$errorAlert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Item Number does not exist in DB. Therefore, update not possible.</div>';
			$data = ['alertMessage' => $errorAlert];
			echo json_encode($data);
			exit();
		}

		// Construct the UPDATE query
		$updateItemDetailsSql = 'UPDATE item SET itemName = :itemName, unitAsSold = :unitAsSold, discount = :discount, stock = :stock, unitPrice = :unitPrice, status = :status, description = :description WHERE itemNumber = :itemNumber';
		$updateItemDetailsStatement = $conn->prepare($updateItemDetailsSql);
		$updateItemDetailsStatement->execute(['itemName' => $itemName, 'unitAsSold' => $unitAsSold, 'discount' => $discount, 'stock' => $newStock, 'unitPrice' => $itemDetailsUnitPrice, 'status' => $status, 'description' => $description, 'itemNumber' => $itemNumber]);

		// UPDATE item name in sale table
		$updateItemInSaleTableSql = 'UPDATE sale SET itemName = :itemName WHERE itemNumber = :itemNumber';
		$updateItemInSaleTableSstatement = $conn->prepare($updateItemInSaleTableSql);
		$updateItemInSaleTableSstatement->execute(['itemName' => $itemName, 'itemNumber' => $itemNumber]);

		// UPDATE item name in purchase table
		$updateItemInPurchaseTableSql = 'UPDATE purchase SET itemName = :itemName WHERE itemNumber = :itemNumber';
		$updateItemInPurchaseTableSstatement = $conn->prepare($updateItemInPurchaseTableSql);
		$updateItemInPurchaseTableSstatement->execute(['itemName' => $itemName, 'itemNumber' => $itemNumber]);

		$successAlert = '<div class="alert alert-warning"><button type="button" class="close" data-dismiss="alert">&times;</button>Item details updated. Stock was not changed.</div>';
		$data = ['alertMessage' => $successAlert, 'newStock' => $newStock];
		echo json_encode($data);
		exit();
	} else {
		// One or more mandatory fields are empty. Therefore, display the error message
		$errorAlert = '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter all fields marked with a (*)</div>';
		$data = ['alertMessage' => $errorAlert];
		echo json_encode($data);
		exit();
	}
}
