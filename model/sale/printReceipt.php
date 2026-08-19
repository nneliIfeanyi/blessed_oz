<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$settingsFile = '../../inc/config/site_settings.json';
$receiptSettings = getStoreSettings($conn, $activeStoreID, $settingsFile);
$receiptBrandName = isset($receiptSettings['siteName']) && trim($receiptSettings['siteName']) !== ''
    ? $receiptSettings['siteName']
    : 'Inventory System';
$receiptBusinessPhone = isset($receiptSettings['businessPhone']) ? trim((string) $receiptSettings['businessPhone']) : '';
$receiptBusinessAddress = isset($receiptSettings['businessAddress']) ? trim((string) $receiptSettings['businessAddress']) : '';
$receiptIssuer = isset($_SESSION['fullName']) && trim((string) $_SESSION['fullName']) !== ''
    ? $_SESSION['fullName']
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'System User');

$transactionReference = isset($_GET['saleReference']) ? trim(htmlentities($_GET['saleReference'])) : '';

if ($transactionReference !== '') {
    $headerStatement = $conn->prepare('SELECT * FROM sale_headers WHERE saleReference = :saleReference AND storeID = :storeID');
    $headerStatement->execute(['saleReference' => $transactionReference, 'storeID' => $activeStoreID]);
    if ($headerStatement->rowCount() < 1) {
        header('Location: ../../index.php');
        exit();
    }
    $headerRow = $headerStatement->fetch(PDO::FETCH_ASSOC);
    $itemStatement = $conn->prepare('SELECT * FROM sale_items WHERE saleReference = :saleReference AND storeID = :storeID ORDER BY saleItemID ASC');
    $itemStatement->execute(['saleReference' => $transactionReference, 'storeID' => $activeStoreID]);
    $items = $itemStatement->fetchAll(PDO::FETCH_ASSOC);
    $customerName = isset($headerRow['customerName']) ? $headerRow['customerName'] : 'Customer';
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += (float) $item['lineTotal'];
    }
    $amountPaid = isset($headerRow['amountPaid']) ? (float) $headerRow['amountPaid'] : 0;
    $amountBalance = max(0, $totalAmount - $amountPaid);

    echo '<!DOCTYPE html><html><head><title>Receipt</title><style>@page{size:148mm 210mm;margin:8mm;} *{box-sizing:border-box;} body{font-family:Arial,sans-serif;font-size:11px;padding:0;margin:0;width:148mm;} .box{padding:6mm;} .small{font-size:10px;color:#666;margin:0 0 4px;} .header-row{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px;} .brand-col{flex:1 1 55%;} .meta-col{flex:1 1 45%;text-align:right;font-size:10px;} .meta-col p{margin:0 0 2px;} h3{margin:0 0 2px;font-size:14px;} table{width:100%;border-collapse:collapse;margin-top:4px;font-size:10px;} th,td{border-bottom:1px solid #ddd;padding:4px 0;text-align:left;vertical-align:top;} .right{text-align:right;} .totals p{margin:3px 0;font-size:10px;} hr{margin:6px 0;border:none;border-top:1px solid #bbb;} @media print{body{width:148mm;} @page{size:148mm 210mm;margin:8mm;}}</style></head><body onload="window.print()"><div class="box"><div class="header-row"><div class="brand-col"><h3>' . htmlspecialchars($receiptBrandName) . '</h3><p class="small">' . htmlspecialchars($receiptBusinessPhone) . '</p><p class="small">' . nl2br(htmlspecialchars($receiptBusinessAddress)) . '</p><p class="small">Sales Receipt</p></div><div class="meta-col"><p><strong>Transaction ID:</strong> ' . htmlspecialchars($transactionReference) . '</p><p><strong>Customer:</strong> ' . htmlspecialchars($customerName) . '</p><p><strong>Date:</strong> ' . htmlspecialchars($headerRow['saleDate']) . '</p></div></div><hr><table><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Amount</th></tr></thead><tbody>';
    foreach ($items as $item) {
        echo '<tr><td>' . htmlspecialchars($item['itemName']) . '</td><td class="right">' . htmlspecialchars($item['quantity']) . '</td><td class="right">' . htmlspecialchars(number_format((float) $item['unitPrice'], 2)) . '</td><td class="right">' . htmlspecialchars(number_format((float) $item['lineTotal'], 2)) . '</td></tr>';
    }
    echo '</tbody></table><hr><div class="totals"><p class="right"><strong>Total Amount:</strong> ' . htmlspecialchars(number_format($totalAmount, 2)) . '</p><p class="right"><strong>Amount Paid:</strong> ' . htmlspecialchars(number_format($amountPaid, 2)) . '</p><p class="right"><strong>Balance:</strong> ' . htmlspecialchars(number_format($amountBalance, 2)) . '</p></div><p class="small">Issued by: ' . htmlspecialchars($receiptIssuer) . '</p><p class="small">Thank you for patronage.</p></div></body></html>';
    exit();
}

header('Location: ../../index.php');
exit();
