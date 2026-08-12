<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$sql = "SELECT MAX(purchaseID) FROM purchase";
$stmt = $conn->prepare($sql);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo isset($row['MAX(purchaseID)']) && $row['MAX(purchaseID)'] !== null ? $row['MAX(purchaseID)'] : '';
