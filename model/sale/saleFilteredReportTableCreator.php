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

	$saleFilteredReportSql = 'SELECT sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, si.itemNumber, si.itemName, si.discount, si.quantity, si.unitPrice, si.reason, si.lineTotal FROM sale_headers sh LEFT JOIN sale_items si ON sh.saleReference = si.saleReference AND si.storeID = sh.storeID WHERE sh.saleDate BETWEEN :startDate AND :endDate AND sh.storeID = :storeID ORDER BY sh.saleDate DESC, sh.id DESC, si.saleItemID ASC';
	$saleFilteredReportStatement = $conn->prepare($saleFilteredReportSql);
	$saleFilteredReportStatement->execute(['startDate' => $startDate, 'endDate' => $endDate, 'storeID' => $activeStoreID]);

	$output = '<table id="saleFilteredReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
					<thead>
						<tr>
							<th>Transaction ID</th>
							<th>Item Number</th>
							<th>Customer ID</th>
							<th>Customer Name</th>
							<th>Item Name</th>
							<th>Sale Date</th>
							<th>Discount %</th>
							<th>Quantity</th>
							<th>Reason</th>
							<th>Unit Price</th>
							<th>Total Price</th>
						</tr>
					</thead>
					<tbody>';

	// Create table rows from the selected data
	while ($row = $saleFilteredReportStatement->fetch(PDO::FETCH_ASSOC)) {
		$uPrice = (float) $row['unitPrice'];
		$qty = (int) $row['quantity'];
		$discount = (float) (isset($row['discount']) ? $row['discount'] : 0);
		$totalPrice = (isset($row['lineTotal']) && $row['lineTotal'] !== '') ? (float) $row['lineTotal'] : ($uPrice * $qty * ((100 - $discount) / 100));

		$output .= '<tr>' .
			'<td>' . htmlspecialchars($row['saleReference']) . '</td>' .
			'<td>' . htmlspecialchars($row['itemNumber']) . '</td>' .
			'<td>' . htmlspecialchars($row['customerID']) . '</td>' .
			'<td>' . htmlspecialchars($row['customerName']) . '</td>' .
			'<td>' . htmlspecialchars($row['itemName']) . '</td>' .
			'<td>' . htmlspecialchars($row['saleDate']) . '</td>' .
			'<td>' . htmlspecialchars($row['discount']) . '</td>' .
			'<td>' . htmlspecialchars($row['quantity']) . '</td>' .
			'<td>' . htmlspecialchars(isset($row['reason']) ? $row['reason'] : 'Sales') . '</td>' .
			'<td>' . htmlspecialchars($row['unitPrice']) . '</td>' .
			'<td>' . htmlspecialchars($totalPrice) . '</td>' .
			'</tr>';
	}

	$saleFilteredReportStatement->closeCursor();

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
								<th></th>
							</tr>
						</tfoot>
					</table>';
	echo $output;
}
