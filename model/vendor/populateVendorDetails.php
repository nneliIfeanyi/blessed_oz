<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

// Execute the script if the POST request is submitted
if (isset($_POST['vendorDetailsVendorID'])) {

	$vendorID = htmlentities($_POST['vendorDetailsVendorID']);

	$vendorDetailsSql = 'SELECT * FROM vendor WHERE vendorID = :vendorID AND storeID = :storeID';
	$vendorDetailsStatement = $conn->prepare($vendorDetailsSql);
	$vendorDetailsStatement->execute(['vendorID' => $vendorID, 'storeID' => $activeStoreID]);

	// If data is found for the given vendorID, return it as a json object
	if ($vendorDetailsStatement->rowCount() > 0) {
		$row = $vendorDetailsStatement->fetch(PDO::FETCH_ASSOC);
		echo json_encode($row);
	}
	$vendorDetailsStatement->closeCursor();
}
