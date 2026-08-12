<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

// Execute the script if the POST request is submitted
if (isset($_POST['saleDetailsSaleID'])) {

	$saleReference = htmlentities($_POST['saleDetailsSaleID']);

	$saleDetailsSql = 'SELECT sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, sh.amountPaid, si.itemNumber, si.itemName, si.discount, si.quantity, si.unitPrice, si.reason
			FROM sale_headers sh
			LEFT JOIN sale_items si ON sh.saleReference = si.saleReference
			WHERE sh.saleReference = :saleReference
			ORDER BY si.saleItemID ASC
			LIMIT 1';
	$saleDetailsStatement = $conn->prepare($saleDetailsSql);
	$saleDetailsStatement->execute(['saleReference' => $saleReference]);

	// If data is found for the given transaction ID, return it as a json object
	if ($saleDetailsStatement->rowCount() > 0) {
		$row = $saleDetailsStatement->fetch(PDO::FETCH_ASSOC);
		echo json_encode($row);
	}
	$saleDetailsStatement->closeCursor();
}
