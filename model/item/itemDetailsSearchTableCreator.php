<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$settingsFile = '../../inc/config/site_settings.json';
$settings = ['enableProductDescription' => true];
if (file_exists($settingsFile)) {
	$json = file_get_contents($settingsFile);
	if ($json !== false) {
		$decoded = json_decode($json, true);
		if (is_array($decoded)) {
			$settings = array_merge($settings, $decoded);
		}
	}
}

$itemDetailsSearchSql = 'SELECT * FROM item WHERE storeID = :storeID';
$itemDetailsSearchStatement = $conn->prepare($itemDetailsSearchSql);
$itemDetailsSearchStatement->execute(['storeID' => $activeStoreID]);

$descriptionHeader = !empty($settings['enableProductDescription']) ? '<th>Description</th>' : '';
$output = '<table id="itemDetailsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Product ID</th>
						<th>Item Number</th>
						<th>Item Name</th>
						<th>Unit</th>
						<th>Discount %</th>
						<th>Stock</th>
						<th>Unit Price</th>
						<th>Status</th>
						' . $descriptionHeader . '
					</tr>
				</thead>
				<tbody>';

// Create table rows from the selected data
while ($row = $itemDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {

	$output .= '<tr>' .
		'<td>' . $row['productID'] . '</td>' .
		'<td>' . $row['itemNumber'] . '</td>' .
		'<td><a href="#" class="itemDetailsHover" data-toggle="popover" id="' . $row['productID'] . '">' . $row['itemName'] . '</a></td>' .
		'<td>' . htmlspecialchars($row['unitAsSold'] ?? 'pcs') . '</td>' .
		'<td>' . $row['discount'] . '</td>' .
		'<td>' . $row['stock'] . '</td>' .
		'<td>' . $row['unitPrice'] . '</td>' .
		'<td>' . $row['status'] . '</td>' .
		(!empty($settings['enableProductDescription']) ? '<td>' . $row['description'] . '</td>' : '') .
		'</tr>';
}

$itemDetailsSearchStatement->closeCursor();

$descriptionFooter = !empty($settings['enableProductDescription']) ? '<th>Description</th>' : '';
$output .= '</tbody>
					<tfoot>
						<tr>
							<th>Product ID</th>
							<th>Item Number</th>
							<th>Item Name</th>
							<th>Unit</th>
							<th>Discount %</th>
							<th>Stock</th>
							<th>Unit Price</th>
							<th>Status</th>
							' . $descriptionFooter . '
						</tr>
					</tfoot>
				</table>';
echo $output;
