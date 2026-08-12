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
	$searchString = '%' . trim(htmlentities($_POST['textBoxValue'])) . '%';

	// Search suggestions by customer name first, with ID as fallback
	$sql = 'SELECT customerID, fullName FROM customer WHERE storeID = ? AND (fullName LIKE ? OR customerID LIKE ?) ORDER BY fullName ASC LIMIT 15';
	$stmt = $conn->prepare($sql);
	$stmt->execute([$activeStoreID, $searchString, $searchString]);

	// If we receive any results from the above query, then display them in a list
	if ($stmt->rowCount() > 0) {

		// Given customer is available in DB. Hence create the dropdown list
		$output = '<ul class="list-unstyled suggestionsList" id="saleDetailsCustomerIDSuggestionsList">';
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$output .= '<li data-customer-id="' . htmlspecialchars((string)$row['customerID']) . '">' . htmlspecialchars((string)$row['fullName']) . ' (ID: ' . htmlspecialchars((string)$row['customerID']) . ')</li>';
		}
		$output .= '</ul>';
	} else {
		$output = '';
	}
	$stmt->closeCursor();
	echo $output;
}
