<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$sql = 'SELECT saleReference FROM sale_headers WHERE storeID = :storeID ORDER BY id DESC LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->execute(['storeID' => $activeStoreID]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo isset($row['saleReference']) ? $row['saleReference'] : '';
$stmt->closeCursor();
