<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$sql = "SELECT MAX(productID) FROM item WHERE storeID = :storeID";
$stmt = $conn->prepare($sql);
$stmt->execute(['storeID' => $activeStoreID]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo isset($row['MAX(productID)']) ? $row['MAX(productID)'] : 0;
$stmt->closeCursor();
