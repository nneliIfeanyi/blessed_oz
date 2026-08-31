<?php
/**
 * Export items, customers, and vendors for the active store (offline catalog).
 * Used while online to seed localStorage for offline search/autofill.
 */
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== '1') {
	http_response_code(401);
	echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
	exit();
}

try {
	ensureActiveStoreSession($conn);
} catch (Exception $e) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid store session.']);
	exit();
}

$activeStoreID = (int) $_SESSION['activeStoreID'];

$items = [];
$customers = [];
$vendors = [];

try {
	// Include zero-stock items (sale UI filters available > 0 offline)
	$itemSql = 'SELECT itemNumber, itemName, unitPrice, discount, stock, unitAsSold, status
		FROM item
		WHERE storeID = :storeID
		ORDER BY itemNumber ASC';
	$itemStmt = $conn->prepare($itemSql);
	$itemStmt->execute(['storeID' => $activeStoreID]);
	while ($row = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
		$status = isset($row['status']) ? trim((string) $row['status']) : 'Active';
		if ($status !== '' && strcasecmp($status, 'Active') !== 0) {
			continue;
		}
		$items[] = [
			'itemNumber' => (string) $row['itemNumber'],
			'itemName' => (string) $row['itemName'],
			'unitPrice' => isset($row['unitPrice']) ? (float) $row['unitPrice'] : 0,
			'discount' => isset($row['discount']) ? (float) $row['discount'] : 0,
			'stock' => isset($row['stock']) ? (int) $row['stock'] : 0,
			'unitAsSold' => isset($row['unitAsSold']) && $row['unitAsSold'] !== '' ? (string) $row['unitAsSold'] : 'pcs',
		];
	}
	$itemStmt->closeCursor();
} catch (PDOException $e) {
	error_log('exportOfflineCatalog items: ' . $e->getMessage());
}

try {
	$custSql = 'SELECT customerID, fullName, status FROM customer WHERE storeID = :storeID ORDER BY fullName ASC';
	$custStmt = $conn->prepare($custSql);
	$custStmt->execute(['storeID' => $activeStoreID]);
	while ($row = $custStmt->fetch(PDO::FETCH_ASSOC)) {
		$status = isset($row['status']) ? trim((string) $row['status']) : 'Active';
		if ($status !== '' && strcasecmp($status, 'Active') !== 0) {
			continue;
		}
		$customers[] = [
			'customerID' => (int) $row['customerID'],
			'fullName' => (string) $row['fullName'],
		];
	}
	$custStmt->closeCursor();
} catch (PDOException $e) {
	error_log('exportOfflineCatalog customers: ' . $e->getMessage());
}

try {
	$vendSql = 'SELECT vendorID, fullName, status FROM vendor WHERE storeID = :storeID ORDER BY fullName ASC';
	$vendStmt = $conn->prepare($vendSql);
	$vendStmt->execute(['storeID' => $activeStoreID]);
	while ($row = $vendStmt->fetch(PDO::FETCH_ASSOC)) {
		$status = isset($row['status']) ? trim((string) $row['status']) : 'Active';
		if ($status !== '' && strcasecmp($status, 'Active') !== 0) {
			continue;
		}
		$vendors[] = [
			'vendorID' => (int) $row['vendorID'],
			'fullName' => (string) $row['fullName'],
		];
	}
	$vendStmt->closeCursor();
} catch (PDOException $e) {
	error_log('exportOfflineCatalog vendors: ' . $e->getMessage());
}

echo json_encode([
	'success' => true,
	'storeID' => $activeStoreID,
	'exportedAt' => date('c'),
	'items' => $items,
	'customers' => $customers,
	'vendors' => $vendors,
	'counts' => [
		'items' => count($items),
		'customers' => count($customers),
		'vendors' => count($vendors),
	],
]);
