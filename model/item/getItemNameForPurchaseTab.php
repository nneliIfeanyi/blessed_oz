<?php
$activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
$itemDetailsSql = 'SELECT itemName FROM item WHERE storeID = :storeID';
$itemDetailsStatement = $conn->prepare($itemDetailsSql);
$itemDetailsStatement->execute(['storeID' => $activeStoreID]);

if ($itemDetailsStatement->rowCount() > 0) {
	while ($row = $itemDetailsStatement->fetch(PDO::FETCH_ASSOC)) {
		echo '<option>' . $row['itemName'] . '</option>';
	}
}
$itemDetailsStatement->closeCursor();
