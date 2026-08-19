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

if (isset($_POST['startDate'])) {
	$startDate = htmlentities($_POST['startDate']);
	$endDate = htmlentities($_POST['endDate']);

	$purchaseFilteredReportSql = 'SELECT p.*, COALESCE(i.unitAsSold, "pcs") AS unitAsSold FROM purchase p LEFT JOIN item i ON i.itemNumber = p.itemNumber AND i.storeID = p.storeID WHERE p.purchaseDate BETWEEN :startDate AND :endDate AND p.storeID = :storeID';
	$purchaseFilteredReportStatement = $conn->prepare($purchaseFilteredReportSql);
	$purchaseFilteredReportStatement->execute(['startDate' => $startDate, 'endDate' => $endDate, 'storeID' => $activeStoreID]);

	$output = '<table id="purchaseFilteredReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
					<thead>
						<tr>
							<th>Transaction ID</th>
							<th>Item Number</th>
							<th>Purchase Date</th>
							<th>Item Name</th>
							<th>Quantity</th>
							<th>Unit</th>
							<th>Vendor Name</th>
							<th>Vendor ID</th>
							<th>Unit Price</th>
							<th>Total Price</th>
						</tr>
					</thead>
					<tbody>';

	// Create table rows from the selected data
	while ($row = $purchaseFilteredReportStatement->fetch(PDO::FETCH_ASSOC)) {
		$uPrice = $row['unitPrice'];
		$qty = $row['quantity'];
		$totalPrice = $uPrice * $qty;

		$output .= '<tr>' .
			'<td>' . (!empty($row['transactionReference']) ? '<button type="button" class="transaction-id-copy" data-transaction-id="' . htmlspecialchars($row['transactionReference']) . '" title="Copy transaction ID">' . htmlspecialchars($row['transactionReference']) . '</button>' : htmlspecialchars($row['purchaseID'])) . '</td>' .
			'<td>' . $row['itemNumber'] . '</td>' .
			'<td>' . $row['purchaseDate'] . '</td>' .
			'<td>' . $row['itemName'] . '</td>' .
			'<td>' . $row['quantity'] . '</td>' .
			'<td>' . htmlspecialchars($row['unitAsSold'] ?? 'pcs') . '</td>' .
			'<td>' . $row['vendorName'] . '</td>' .
			'<td>' . $row['vendorID'] . '</td>' .
			'<td>' . $row['unitPrice'] . '</td>' .
			'<td>' . $totalPrice . '</td>' .
			'</tr>';
	}

	$purchaseFilteredReportStatement->closeCursor();

	$output .= '</tbody>
						<tfoot>
							<tr>
								<th>Total</th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
							</tr>
						</tfoot>
					</table>';
	echo $output;
}
