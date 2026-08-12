<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

// Execute the script if the POST request is submitted
if (isset($_POST['customerID'])) {

	$customerID = htmlentities($_POST['customerID']);

	$customerDetailsSql = 'SELECT * FROM customer WHERE customerID = :customerID AND storeID = :storeID';
	$customerDetailsStatement = $conn->prepare($customerDetailsSql);
	$customerDetailsStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);

	// If data is found for the given item number, return it as a json object
	if ($customerDetailsStatement->rowCount() > 0) {
		$row = $customerDetailsStatement->fetch(PDO::FETCH_ASSOC);
		echo json_encode($row);
	}
	$customerDetailsStatement->closeCursor();
}
