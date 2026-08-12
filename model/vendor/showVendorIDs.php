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
	$vendorIDString = '%' . htmlentities($_POST['textBoxValue']) . '%';

	// Construct the SQL query to get the vendor ID
	$sql = 'SELECT vendorID FROM vendor WHERE vendorID LIKE ? AND storeID = ?';
	$stmt = $conn->prepare($sql);
	$stmt->execute([$vendorIDString, $activeStoreID]);

	// If we receive any results from the above query, then display them in a list
	if ($stmt->rowCount() > 0) {

		// Given vendor ID is available in DB. Hence create the dropdown list
		$output = '<ul class="list-unstyled suggestionsList" id="vendorDetailsVendorIDSuggestionsList">';
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$output .= '<li>' . $row['vendorID'] . '</li>';
		}
		$output .= '</ul>';
	} else {
		$output = '';
	}
	$stmt->closeCursor();
	echo $output;
}
