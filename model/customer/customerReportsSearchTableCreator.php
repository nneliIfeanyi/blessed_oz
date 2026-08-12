<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$customerDetailsSearchSql = 'SELECT * FROM customer';
$customerDetailsSearchStatement = $conn->prepare($customerDetailsSearchSql);
$customerDetailsSearchStatement->execute();

$output = '<table id="customerReportsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
				<thead>
					<tr>
						<th>Customer ID</th>
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
while ($row = $customerDetailsSearchStatement->fetch(PDO::FETCH_ASSOC)) {
	$customerID = htmlspecialchars((string)($row['customerID'] ?? ''));
	$fullName = htmlspecialchars((string)($row['fullName'] ?? ''));
	$email = htmlspecialchars((string)($row['email'] ?? ''));
	$mobile = htmlspecialchars((string)($row['mobile'] ?? ''));
	$address = htmlspecialchars((string)($row['address'] ?? ''));
	$district = htmlspecialchars((string)($row['district'] ?? ''));
	$status = htmlspecialchars((string)($row['status'] ?? ''));

	$output .= '<tr>' .
		'<td>' . $customerID . '</td>' .
		'<td>' . $fullName . '</td>' .
		'<td>' . $email . '</td>' .
		'<td>' . $mobile . '</td>' .
		'<td>' . $address . '</td>' .
		'<td>' . $district . '</td>' .
		'<td>' . $status . '</td>' .
		'</tr>';
}

$customerDetailsSearchStatement->closeCursor();

$output .= '</tbody>
					<tfoot>
						<tr>
							<th>Customer ID</th>
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
