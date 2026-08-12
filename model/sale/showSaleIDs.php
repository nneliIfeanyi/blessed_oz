<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

// Check if the POST request is received and if so, execute the script
if (isset($_POST['textBoxValue'])) {
	$output = '';
	$saleIDString = '%' . htmlentities($_POST['textBoxValue']) . '%';
	$customerID = isset($_POST['customerID']) ? trim(htmlentities($_POST['customerID'])) : '';
	$suggestionsListID = isset($_POST['suggestionsListID']) ? trim(htmlentities($_POST['suggestionsListID'])) : 'saleDetailsSaleIDSuggestionsList';
	if ($suggestionsListID === '') {
		$suggestionsListID = 'saleDetailsSaleIDSuggestionsList';
	}

	// Construct the SQL query to get the transaction ID
	$sql = 'SELECT saleReference FROM sale_headers WHERE saleReference LIKE ?';
	$params = [$saleIDString];
	if ($customerID !== '' && ctype_digit($customerID)) {
		$sql .= ' AND customerID = ?';
		$params[] = (int) $customerID;
	}
	$sql .= ' ORDER BY id DESC';
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);

	// If we receive any results from the above query, then display them in a list
	if ($stmt->rowCount() > 0) {

		// Given transaction ID is available in DB. Hence create the dropdown list
		$output = '<ul class="list-unstyled suggestionsList" id="' . htmlspecialchars($suggestionsListID) . '">';
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$output .= '<li>' . htmlspecialchars($row['saleReference']) . '</li>';
		}
		$output .= '</ul>';
	} else {
		$output = '';
	}
	$stmt->closeCursor();
	echo $output;
}
