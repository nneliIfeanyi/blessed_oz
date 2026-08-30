<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

function getAvailableColumns($conn, $table)
{
	$columns = [];
	try {
		$statement = $conn->query("SHOW COLUMNS FROM `$table`");
		while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$columns[] = $row['Field'];
		}
	} catch (PDOException $e) {
		$columns = [];
	}
	return $columns;
}

if (isset($_POST['customerDetailsCustomerFullName'])) {
	$fullName = trim(htmlentities($_POST['customerDetailsCustomerFullName']));
	$email = trim(htmlentities($_POST['customerDetailsCustomerEmail'] ?? ''));
	$mobile = trim(htmlentities($_POST['customerDetailsCustomerMobile']));
	$address = trim(htmlentities($_POST['customerDetailsCustomerAddress']));
	$address2 = trim(htmlentities($_POST['customerDetailsCustomerAddress2'] ?? ''));
	$city = trim(htmlentities($_POST['customerDetailsCustomerCity'] ?? ''));
	$district = trim(htmlentities($_POST['customerDetailsCustomerDistrict'] ?? ''));
	$status = trim(htmlentities($_POST['customerDetailsStatus'] ?? ''));

	if ($fullName == '' || $mobile == '' || $address == '') {
		echo 'Please enter all fields marked with a (*)';
		exit();
	}

	if (filter_var($mobile, FILTER_VALIDATE_INT) === false) {
		echo 'Please enter a valid phone number';
		exit();
	}

	// if (!empty($phone2) && filter_var($phone2, FILTER_VALIDATE_INT) === false) {
	// 	echo 'Please enter a valid mobile number 2';
	// 	exit();
	// }

	if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
		echo 'Please enter a valid email';
		exit();
	}

	try {
		$availableColumns = getAvailableColumns($conn, 'customer');
		$insertColumns = ['fullName', 'email', 'mobile', 'address', 'status'];
		$insertData = ['fullName' => $fullName, 'email' => $email, 'mobile' => $mobile, 'address' => $address, 'status' => $status];

		if (in_array('storeID', $availableColumns)) {
			$insertColumns[] = 'storeID';
			$insertData['storeID'] = $activeStoreID;
		}

		// if (in_array('phone2', $availableColumns)) {
		// 	$insertColumns[] = 'phone2';
		// 	$insertData['phone2'] = $phone2;
		// }
		if (in_array('address2', $availableColumns)) {
			$insertColumns[] = 'address2';
			$insertData['address2'] = $address2;
		}
		if (in_array('city', $availableColumns)) {
			$insertColumns[] = 'city';
			$insertData['city'] = $city;
		}
		if (in_array('district', $availableColumns)) {
			$insertColumns[] = 'district';
			$insertData['district'] = $district;
		}

		$placeholders = array_map(function ($column) {
			return ':' . $column;
		}, $insertColumns);
		$sql = 'INSERT INTO customer(' . implode(', ', $insertColumns) . ') VALUES(' . implode(', ', $placeholders) . ')';
		$stmt = $conn->prepare($sql);
		$stmt->execute($insertData);
		echo 'Customer added successfully.';
	} catch (PDOException $e) {
		echo 'Unable to add customer right now.';
	}
}
