<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$vendorDetailsSearchSql = 'SELECT * FROM vendor';
$vendorDetailsSearchStatement = $conn->prepare($vendorDetailsSearchSql);
$vendorDetailsSearchStatement->execute();

$output = '<table id="vendorReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Vendor ID</th>
						<th>Full Name</th>
						<th>Email</th>
						<th>Mobile</th>
						<th>Address</th>
						<th>District</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>';

// Create table rows from the selected data
while ($row = $vendorDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
	$vendorID = htmlspecialchars((string)($row['vendorID'] ?? ''));
	$fullName = htmlspecialchars((string)($row['fullName'] ?? ''));
	$email = htmlspecialchars((string)($row['email'] ?? ''));
	$mobile = htmlspecialchars((string)($row['mobile'] ?? ''));
	$address = htmlspecialchars((string)($row['address'] ?? ''));
	$district = htmlspecialchars((string)($row['district'] ?? ''));
	$status = htmlspecialchars((string)($row['status'] ?? ''));

	$output .= '<tr>' .
		'<td>' . $vendorID . '</td>' .
		'<td>' . $fullName . '</td>' .
		'<td>' . $email . '</td>' .
		'<td>' . $mobile . '</td>' .
		'<td>' . $address . '</td>' .
		'<td>' . $district . '</td>' .
		'<td>' . $status . '</td>' .
		'</tr>';
}

$vendorDetailsSearchStatement->closeCursor();

$output .= '</tbody>
					<tfoot>
						<tr>
							<th>Vendor ID</th>
							<th>Full Name</th>
							<th>Email</th>
							<th>Mobile</th>
							<th>Address</th>
							<th>District</th>
							<th>Status</th>
						</tr>
					</tfoot>
				</table>';
echo $output;
