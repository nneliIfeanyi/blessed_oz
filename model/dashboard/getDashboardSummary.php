<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

header('Content-Type: application/json');

$summary = [
    'currentStockValue' => 0,
    'totalSales' => 0,
    'totalPurchases' => 0,
    'totalCredits' => 0,
    'movements' => []
];

try {
    $dashboardStockValueStatement = $conn->query('SELECT COALESCE(SUM(stock * unitPrice), 0) FROM item');
    $summary['currentStockValue'] = (float) $dashboardStockValueStatement->fetchColumn();

    $dashboardSalesStatement = $conn->query('SELECT COALESCE(SUM(lineTotal), 0) FROM sale_items');
    $summary['totalSales'] = (float) $dashboardSalesStatement->fetchColumn();

    $dashboardPurchasesStatement = $conn->query('SELECT COALESCE(SUM(quantity * unitPrice), 0) FROM purchase');
    $summary['totalPurchases'] = (float) $dashboardPurchasesStatement->fetchColumn();

    $dashboardCreditsStatement = $conn->query('SELECT COALESCE(SUM(balanceAfter), 0) FROM (SELECT customerID, balanceAfter FROM customer_ledger WHERE ledgerID IN (SELECT MAX(ledgerID) FROM customer_ledger GROUP BY customerID)) AS latestBalances');
    $summary['totalCredits'] = (float) $dashboardCreditsStatement->fetchColumn();

    $dashboardMovementSql = 'SELECT movementDate, itemNumber, itemName, quantity, referenceName, reason, direction FROM (
        SELECT purchaseDate AS movementDate, itemNumber, itemName, quantity, vendorName AS referenceName, "" AS reason, "In" AS direction, purchaseID AS movementSequence FROM purchase
        UNION ALL
        SELECT sh.saleDate AS movementDate, si.itemNumber, si.itemName, si.quantity, COALESCE(sh.customerName, "") AS referenceName, COALESCE(si.reason, "Sales") AS reason, "Out" AS direction, si.saleItemID AS movementSequence
        FROM sale_items si
        LEFT JOIN sale_headers sh ON sh.saleReference = si.saleReference
        UNION ALL
        SELECT saleDate AS movementDate, itemNumber, itemName, quantity, customerName AS referenceName, COALESCE(reason, "Sales") AS reason, "Out" AS direction, saleID AS movementSequence FROM sale
    ) AS movementLog
    ORDER BY movementDate DESC, movementSequence DESC';
    $dashboardMovementStatement = $conn->query($dashboardMovementSql);
    $summary['movements'] = $dashboardMovementStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $summary['error'] = 'Dashboard data is unavailable right now.';
}

echo json_encode($summary);
