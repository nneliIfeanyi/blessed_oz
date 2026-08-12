<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$uPrice = 0;
$qty = 0;
$totalPrice = 0;

$purchaseDetailsSearchSql = 'SELECT p.*, COALESCE(i.unitAsSold, "pcs") AS unitAsSold FROM purchase p LEFT JOIN item i ON i.itemNumber = p.itemNumber AND i.storeID = p.storeID WHERE p.storeID = :storeID';
$purchaseDetailsSearchStatement = $conn->prepare($purchaseDetailsSearchSql);
$purchaseDetailsSearchStatement->execute(['storeID' => $activeStoreID]);

$output = '<table id="purchaseDetailsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Purchase ID</th>
						<th>Item Number</th>
						<th>Purchase Date</th>
						<th>Item Name</th>
						<th>Quantity</th>
						<th>Unit</th>
						<th>Unit Price</th>
						<th>Vendor Name</th>
						<th>Vendor ID</th>
						<th>Total Price</th>
					</tr>
				</thead>
				<tbody>';

// Create table rows from the selected data
while ($row = $purchaseDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
	$uPrice = $row['unitPrice'];
	$qty = $row['quantity'];
	$totalPrice = $uPrice * $qty;

	$output .= '<tr>' .
		'<td>' . $row['purchaseID'] . '</td>' .
		'<td>' . $row['itemNumber'] . '</td>' .
		'<td>' . $row['purchaseDate'] . '</td>' .
		'<td>' . $row['itemName'] . '</td>' .
		'<td>' . $row['quantity'] . '</td>' .
		'<td>' . htmlspecialchars($row['unitAsSold'] ?? 'pcs') . '</td>' .
		'<td>' . $row['unitPrice'] . '</td>' .
		'<td>' . $row['vendorName'] . '</td>' .
		'<td>' . $row['vendorID'] . '</td>' .
		'<td>' . $totalPrice . '</td>' .
		'</tr>';
}

$purchaseDetailsSearchStatement->closeCursor();

$output .= '</tbody>
					<tfoot>
						<tr>
							<th>Purchase ID</th>
							<th>Item Number</th>
							<th>Purchase Date</th>
							<th>Item Name</th>
							<th>Quantity</th>
							<th>Unit</th>
							<th>Unit Price</th>
							<th>Vendor Name</th>
							<th>Vendor ID</th>
							<th>Total Price</th>
						</tr>
					</tfoot>
				</table>';
echo $output;
