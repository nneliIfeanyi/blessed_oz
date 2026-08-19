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
if (isset($_POST['saleDetailsSaleID'])) {

	$saleReference = htmlentities($_POST['saleDetailsSaleID']);

	$saleDetailsSql = 'SELECT saleReference, customerID, customerName, saleDate, amountPaid FROM sale_headers WHERE saleReference = :saleReference AND storeID = :storeID LIMIT 1';
	$saleDetailsStatement = $conn->prepare($saleDetailsSql);
	$saleDetailsStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);

	if ($saleDetailsStatement->rowCount() > 0) {
		$row = $saleDetailsStatement->fetch(PDO::FETCH_ASSOC);
		$paymentStatement = $conn->prepare('SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE saleReference = :saleReference AND storeID = :storeID');
		$paymentStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
		$laterPayments = (float) $paymentStatement->fetchColumn();
		$row['amountPaid'] = max(0, round((float) $row['amountPaid'] - $laterPayments, 2));
		$itemStatement = $conn->prepare('SELECT itemNumber, itemName, discount, quantity, unitPrice, reason FROM sale_items WHERE saleReference = :saleReference AND storeID = :storeID ORDER BY saleItemID ASC');
		$itemStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
		$row['items'] = $itemStatement->fetchAll(PDO::FETCH_ASSOC);
		$row['success'] = true;
		echo json_encode($row);
	} else {
		echo json_encode(['success' => false, 'message' => 'Transaction ID does not exist.']);
	}
	$saleDetailsStatement->closeCursor();
}
