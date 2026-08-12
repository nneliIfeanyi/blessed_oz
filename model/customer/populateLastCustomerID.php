<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$sql = "SELECT MAX(customerID) FROM customer WHERE storeID = :storeID";
$stmt = $conn->prepare($sql);
$stmt->execute(['storeID' => $activeStoreID]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo isset($row['MAX(customerID)']) ? $row['MAX(customerID)'] : 0;
$stmt->closeCursor();
