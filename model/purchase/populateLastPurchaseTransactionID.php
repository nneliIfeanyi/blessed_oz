<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$sql = 'SELECT transactionReference FROM purchase_headers ORDER BY id DESC LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo isset($row['transactionReference']) ? $row['transactionReference'] : '';
$stmt->closeCursor();
