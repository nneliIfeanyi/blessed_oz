<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

if (isset($_POST['customerDetailsCustomerID'])) {

	$customerDetailsCustomerID = htmlentities($_POST['customerDetailsCustomerID']);

	// Check if mandatory fields are not empty
	if (!empty($customerDetailsCustomerID)) {

		// Sanitize customerID
		$customerDetailsCustomerID = filter_var($customerDetailsCustomerID, FILTER_SANITIZE_STRING);

		// Check if the customer is in the database
		$customerSql = 'SELECT customerID FROM customer WHERE customerID=:customerID AND storeID = :storeID';
		$customerStatement = $conn->prepare($customerSql);
		$customerStatement->execute(['customerID' => $customerDetailsCustomerID, 'storeID' => $activeStoreID]);

		if ($customerStatement->rowCount() > 0) {

			// Customer exists in DB. Hence start the DELETE process
			$deleteCustomerSql = 'DELETE FROM customer WHERE customerID=:customerID AND storeID = :storeID';
			$deleteCustomerStatement = $conn->prepare($deleteCustomerSql);
			$deleteCustomerStatement->execute(['customerID' => $customerDetailsCustomerID, 'storeID' => $activeStoreID]);

			echo '<div class="alert alert-success"><button type="button" class="close" data-dismiss="alert">&times;</button>Customer deleted.</div>';
			exit();
		} else {
			// Customer does not exist, therefore, tell the user that he can't delete that customer 
			echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Customer does not exist in DB. Therefore, can\'t delete.</div>';
			exit();
		}
	} else {
		// CustomerID is empty. Therefore, display the error message
		echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Please enter the CustomerID</div>';
		exit();
	}
}
