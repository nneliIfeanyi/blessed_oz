<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

header('Content-Type: application/json');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$summary = [
    'currentStockValue' => 0,
    'totalSales' => 0,
    'totalPurchases' => 0,
    'totalCredits' => 0,
    'movements' => []
];

try {
    $dashboardStockValueStatement = $conn->prepare('SELECT COALESCE(SUM(stock * unitPrice), 0) FROM item WHERE storeID = :storeID');
    $dashboardStockValueStatement->execute(['storeID' => $activeStoreID]);
    $summary['currentStockValue'] = (float) $dashboardStockValueStatement->fetchColumn();

    $dashboardSalesStatement = $conn->prepare('SELECT COALESCE(SUM(lineTotal), 0) FROM sale_items WHERE storeID = :storeID');
    $dashboardSalesStatement->execute(['storeID' => $activeStoreID]);
    $summary['totalSales'] = (float) $dashboardSalesStatement->fetchColumn();

    $dashboardPurchasesStatement = $conn->prepare('SELECT COALESCE(SUM(quantity * unitPrice), 0) FROM purchase WHERE storeID = :storeID');
    $dashboardPurchasesStatement->execute(['storeID' => $activeStoreID]);
    $summary['totalPurchases'] = (float) $dashboardPurchasesStatement->fetchColumn();

    $dashboardCreditsStatement = $conn->prepare('SELECT COALESCE(SUM(outstandingBalance), 0) FROM (
        SELECT ROUND(GREATEST(COALESCE(SUM(si.lineTotal), 0) - COALESCE(sh.amountPaid, 0), 0), 2) AS outstandingBalance
        FROM sale_headers sh
        LEFT JOIN sale_items si ON si.saleReference = sh.saleReference AND si.storeID = sh.storeID
        WHERE sh.storeID = :storeID
        GROUP BY sh.id, sh.amountPaid
    ) AS creditTotals');
    $dashboardCreditsStatement->execute(['storeID' => $activeStoreID]);
    $summary['totalCredits'] = (float) $dashboardCreditsStatement->fetchColumn();

    $dashboardMovementSql = 'SELECT movementDate, itemNumber, itemName, quantity, referenceName, reason, direction FROM (
        SELECT purchaseDate AS movementDate, itemNumber, itemName, quantity, vendorName AS referenceName, "" AS reason, "In" AS direction, purchaseID AS movementSequence FROM purchase WHERE storeID = :purchaseStoreID
        UNION ALL
        SELECT sh.saleDate AS movementDate, si.itemNumber, si.itemName, si.quantity, COALESCE(sh.customerName, "") AS referenceName, COALESCE(si.reason, "Sales") AS reason, "Out" AS direction, si.saleItemID AS movementSequence
        FROM sale_items si
        LEFT JOIN sale_headers sh ON sh.saleReference = si.saleReference
        WHERE si.storeID = :saleItemsStoreID
        UNION ALL
        SELECT saleDate AS movementDate, itemNumber, itemName, quantity, customerName AS referenceName, COALESCE(reason, "Sales") AS reason, "Out" AS direction, saleID AS movementSequence FROM sale WHERE storeID = :saleStoreID
    ) AS movementLog
    ORDER BY movementDate DESC, movementSequence DESC';
    $dashboardMovementStatement = $conn->prepare($dashboardMovementSql);
    $dashboardMovementStatement->execute(['purchaseStoreID' => $activeStoreID, 'saleItemsStoreID' => $activeStoreID, 'saleStoreID' => $activeStoreID]);
    $summary['movements'] = $dashboardMovementStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $summary['error'] = 'Dashboard data is unavailable right now.';
}

echo json_encode($summary);
