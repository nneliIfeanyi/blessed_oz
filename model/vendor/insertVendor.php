<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

if (isset($_POST['vendorDetailsStatus'])) {
	$fullName = trim(htmlentities($_POST['vendorDetailsVendorFullName']));
	$email = trim(htmlentities($_POST['vendorDetailsVendorEmail']));
	$mobile = trim(htmlentities($_POST['vendorDetailsVendorMobile']));
	$address = trim(htmlentities($_POST['vendorDetailsVendorAddress']));
	$district = trim(htmlentities($_POST['vendorDetailsVendorDistrict']));
	$status = trim(htmlentities($_POST['vendorDetailsStatus']));

	if ($fullName == '' || $mobile == '' || $address == '') {
		echo 'Please enter all fields marked with a (*)';
		exit();
	}

	if (filter_var($mobile, FILTER_VALIDATE_INT) === false) {
		echo 'Please enter a valid phone number.';
		exit();
	}

	if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
		echo 'Please enter a valid email.';
		exit();
	}
	// Check if the vendor already exists
	try {
		$checkVendorSql = 'SELECT * FROM vendor WHERE fullName = :fullName AND mobile = :mobile AND storeID = :storeID';
		$checkVendorStatement = $conn->prepare($checkVendorSql);
		$checkVendorStatement->execute(['fullName' => $fullName, 'mobile' => $mobile, 'storeID' => $activeStoreID]);

		if ($checkVendorStatement->rowCount() > 0) {
			echo 'A vendor with the same name and mobile number already exists.';
			exit();
		}
	} catch (PDOException $e) {
		echo 'Error checking for existing vendor: ' . $e->getMessage();
		exit();
	}

	try {
		$insertVendorSql = 'INSERT INTO vendor (storeID, fullName, email, mobile, address, district, status) VALUES (:storeID, :fullName, :email, :mobile, :address, :district, :status)';
		$insertVendorStatement = $conn->prepare($insertVendorSql);
		$insertVendorStatement->execute([
			'storeID' => $activeStoreID,
			'fullName' => $fullName,
			'email' => $email,
			'mobile' => $mobile,
			'address' => $address,
			'district' => $district,
			'status' => $status
		]);

		echo 'success';
	} catch (PDOException $e) {
		echo 'Error inserting vendor: ' . $e->getMessage();
	}
}
