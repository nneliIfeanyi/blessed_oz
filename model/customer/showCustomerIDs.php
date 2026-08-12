<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

// Check if the POST request is received and if so, execute the script
if (isset($_POST['textBoxValue'])) {
	$output = '';
	$customerIDString = '%' . htmlentities($_POST['textBoxValue']) . '%';

	// Construct the SQL query to get the customer ID
	$sql = 'SELECT customerID FROM customer WHERE customerID LIKE ? AND storeID = ?';
	$stmt = $conn->prepare($sql);
	$stmt->execute([$customerIDString, $activeStoreID]);

	// If we receive any results from the above query, then display them in a list
	if ($stmt->rowCount() > 0) {

		// Given customer ID is available in DB. Hence create the dropdown list
		$output = '<ul class="list-unstyled suggestionsList" id="customerDetailsCustomerIDSuggestionsList">';
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$output .= '<li>' . $row['customerID'] . '</li>';
		}
		$output .= '</ul>';
	} else {
		$output = '';
	}
	$stmt->closeCursor();
	echo $output;
}
