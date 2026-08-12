<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

// Execute the script if the POST request is submitted
if (isset($_POST['itemNumber'])) {

	$itemNumber = htmlentities($_POST['itemNumber']);

	$itemDetailsSql = 'SELECT * FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID';
	$itemDetailsStatement = $conn->prepare($itemDetailsSql);
	$itemDetailsStatement->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);

	// If data is found for the given item number, return it as a json object
	if ($itemDetailsStatement->rowCount() > 0) {
		$row = $itemDetailsStatement->fetch(PDO::FETCH_ASSOC);
		echo json_encode($row);
	}
	$itemDetailsStatement->closeCursor();
}
