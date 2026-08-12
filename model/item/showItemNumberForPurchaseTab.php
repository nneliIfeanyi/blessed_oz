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
	$itemNumberString = '%' . htmlentities($_POST['textBoxValue']) . '%';

	// Construct the SQL query to get the item name
	$sql = 'SELECT itemNumber FROM item WHERE itemNumber LIKE ? AND storeID = ?';
	$stmt = $conn->prepare($sql);
	$stmt->execute([$itemNumberString, $activeStoreID]);

	// If we receive any results from the above query, then display them in a list
	if ($stmt->rowCount() > 0) {
		$output = '<ul class="list-unstyled suggestionsList" id="purchaseDetailsItemNumberSuggestionsList">';
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$output .= '<li>' . $row['itemNumber'] . '</li>';
		}
		$output .= '</ul>';
	} else {
		$output = '';
	}
	$stmt->closeCursor();
	echo $output;
}
