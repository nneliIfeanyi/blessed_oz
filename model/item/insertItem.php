<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$initialStock = 0;
$baseImageFolder = '../../data/item_images/';
$itemImageFolder = '';
$settingsFile = '../../inc/config/site_settings.json';
$settings = [
	'enableProductDescription' => true,
	'enableProductImage' => true
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

if (isset($_POST['itemDetailsItemNumber'])) {

	$itemNumber = htmlentities($_POST['itemDetailsItemNumber']);
	$itemName = htmlentities($_POST['itemDetailsItemName']);
	$unitAsSold = isset($_POST['itemDetailsUnitAsSold']) ? trim(htmlentities($_POST['itemDetailsUnitAsSold'])) : 'pcs';
	$discount = htmlentities($_POST['itemDetailsDiscount']);
	$quantity = htmlentities($_POST['itemDetailsQuantity']);
	if ($quantity === '' || $quantity === null) {
		$quantity = 0;
	}
	if ($unitAsSold === '') {
		$unitAsSold = 'pcs';
	}
	$unitPrice = htmlentities($_POST['itemDetailsUnitPrice']);
	$status = htmlentities($_POST['itemDetailsStatus']);
	$description = !empty($settings['enableProductDescription']) ? htmlentities($_POST['itemDetailsDescription']) : '';

	// Check if mandatory fields are not empty
	if (!empty($itemNumber) && !empty($itemName) && isset($unitPrice)) {

		// Sanitize item number
		$itemNumber = filter_var($itemNumber, FILTER_SANITIZE_STRING);

		// Validate item quantity. It has to be a number
		if (filter_var($quantity, FILTER_VALIDATE_INT) === 0 || filter_var($quantity, FILTER_VALIDATE_INT)) {
			// Valid quantity
		} else {
			// Quantity is not a valid number
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid number for quantity</div>';
			exit();
		}

		// Validate unit price. It has to be a number or floating point value
		if (filter_var($unitPrice, FILTER_VALIDATE_FLOAT) === 0.0 || filter_var($unitPrice, FILTER_VALIDATE_FLOAT)) {
			// Valid float (unit price)
		} else {
			// Unit price is not a valid number
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid number for unit price</div>';
			exit();
		}

		// Validate discount only if it's provided
		if (!empty($discount)) {
			if (filter_var($discount, FILTER_VALIDATE_FLOAT) === false) {
				// Discount is not a valid floating point number
				echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter a valid discount amount</div>';
				exit();
			}
		}

		// Create image folder for uploading images
		$itemImageFolder = $baseImageFolder . $itemNumber;
		if (is_dir($itemImageFolder)) {
			// Folder already exist. Hence, do nothing
		} else {
			// Folder does not exist, Hence, create it
			mkdir($itemImageFolder);
		}

		// Calculate the stock values
		$stockSql = 'SELECT stock FROM item WHERE itemNumber=:itemNumber AND storeID = :storeID';
		$stockStatement = $conn->prepare($stockSql);
		$stockStatement->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
		if ($stockStatement->rowCount() > 0) {
			//$row = $stockStatement->fetch(PDO::FETCH_ASSOC);
			//$quantity = $quantity + $row['stock'];
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Item already exists in DB. Please click the <strong>Update</strong> button to update the details. Or use a different Item Number.</div>';
			exit();
		} else {
			// Item does not exist, therefore, you can add it to DB as a new item
			// Start the insert process
			$insertItemSql = 'INSERT INTO item(storeID, itemNumber, itemName, unitAsSold, discount, stock, unitPrice, status, description) VALUES(:storeID, :itemNumber, :itemName, :unitAsSold, :discount, :stock, :unitPrice, :status, :description)';
			$insertItemStatement = $conn->prepare($insertItemSql);
			$insertItemStatement->execute(['storeID' => $activeStoreID, 'itemNumber' => $itemNumber, 'itemName' => $itemName, 'unitAsSold' => $unitAsSold, 'discount' => $discount, 'stock' => $quantity, 'unitPrice' => $unitPrice, 'status' => $status, 'description' => $description]);
			echo '<div class="alert alert-success"><button type="button" class="close" data-dismiss="alert">&times;</button>Item added to database.</div>';
			exit();
		}
	} else {
		// One or more mandatory fields are empty. Therefore, display a the error message
		echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter all fields marked with a (*)</div>';
		exit();
	}
}
