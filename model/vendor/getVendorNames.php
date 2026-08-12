<?php
$activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
$vendorNamesSql = 'SELECT * FROM vendor WHERE storeID = :storeID';
$vendorNamesStatement = $conn->prepare($vendorNamesSql);
$vendorNamesStatement->execute(['storeID' => $activeStoreID]);

if ($vendorNamesStatement->rowCount() > 0) {
	while ($row = $vendorNamesStatement->fetch(PDO::FETCH_ASSOC)) {
		echo '<option value="' . $row['fullName'] . '">' . $row['fullName'] . '</option>';
	}
}
$vendorNamesStatement->closeCursor();
