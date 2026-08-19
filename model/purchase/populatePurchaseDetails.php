<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];
header('Content-Type: application/json');

if (!userCanManageUsers()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Only super admin can load transactions for editing.']);
	exit();
}

// Execute the script if the POST request is submitted
if (isset($_POST['purchaseDetailsPurchaseID'])) {

	$purchaseID = trim(htmlentities($_POST['purchaseDetailsPurchaseID']));

	$purchaseDetailsSql = 'SELECT transactionReference, vendorName, purchaseDate FROM purchase_headers WHERE transactionReference = :purchaseID AND storeID = :storeID LIMIT 1';
	$purchaseDetailsStatement = $conn->prepare($purchaseDetailsSql);
	$purchaseDetailsStatement->execute(['purchaseID' => $purchaseID, 'storeID' => $activeStoreID]);

	if ($purchaseDetailsStatement->rowCount() > 0) {
		$row = $purchaseDetailsStatement->fetch(PDO::FETCH_ASSOC);
		$itemStatement = $conn->prepare('SELECT itemNumber, itemName, quantity, unitPrice FROM purchase_items WHERE transactionReference = :purchaseID AND storeID = :storeID ORDER BY purchaseItemID ASC');
		$itemStatement->execute(['purchaseID' => $purchaseID, 'storeID' => $activeStoreID]);
		$row['items'] = $itemStatement->fetchAll(PDO::FETCH_ASSOC);
		$row['success'] = true;
		echo json_encode($row);
	} else {
		echo json_encode(['success' => false, 'message' => 'Transaction ID does not exist.']);
	}
	$purchaseDetailsStatement->closeCursor();
}
