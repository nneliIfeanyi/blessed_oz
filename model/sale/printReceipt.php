<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

$settingsFile = '../../inc/config/site_settings.json';
$receiptSettings = [
    'siteName' => 'Inventory System'
];
if (file_exists($settingsFile)) {
    $settingsJson = file_get_contents($settingsFile);
    if ($settingsJson !== false) {
        $settingsDecoded = json_decode($settingsJson, true);
        if (is_array($settingsDecoded)) {
            $receiptSettings = array_merge($receiptSettings, $settingsDecoded);
        }
    }
}
$receiptBrandName = isset($receiptSettings['siteName']) && trim($receiptSettings['siteName']) !== ''
    ? $receiptSettings['siteName']
    : 'Inventory System';

$transactionReference = isset($_GET['saleReference']) ? trim(htmlentities($_GET['saleReference'])) : '';

if ($transactionReference !== '') {
    $headerStatement = $conn->prepare('SELECT * FROM sale_headers WHERE saleReference = :saleReference');
    $headerStatement->execute(['saleReference' => $transactionReference]);
    if ($headerStatement->rowCount() < 1) {
        header('Location: ../../index.php');
        exit();
    }
    $headerRow = $headerStatement->fetch(PDO::FETCH_ASSOC);
    $itemStatement = $conn->prepare('SELECT * FROM sale_items WHERE saleReference = :saleReference ORDER BY saleItemID ASC');
    $itemStatement->execute(['saleReference' => $transactionReference]);
    $items = $itemStatement->fetchAll(PDO::FETCH_ASSOC);
    $customerName = isset($headerRow['customerName']) ? $headerRow['customerName'] : 'Customer';
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += (float) $item['lineTotal'];
    }
    $amountPaid = isset($headerRow['amountPaid']) ? (float) $headerRow['amountPaid'] : 0;
    $amountBalance = max(0, $totalAmount - $amountPaid);

    echo '<!DOCTYPE html><html><head><title>Receipt</title><style>body{font-family:Arial,sans-serif;padding:24px;} .box{border:1px solid #ddd;padding:16px;max-width:680px;} .small{font-size:12px;color:#666;margin:0 0 6px;} .header-row{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:8px;} .brand-col{flex:1 1 50%;} .meta-col{flex:1 1 50%;text-align:right;} .meta-col p{margin:0 0 3px;} table{width:100%;border-collapse:collapse;margin-top:6px;} th,td{border-bottom:1px solid #ddd;padding:6px 0;text-align:left;vertical-align:top;} .right{text-align:right;} .totals p{margin:4px 0;} @media (max-width:560px){.header-row{display:block;} .meta-col{text-align:left;margin-top:8px;}}</style></head><body onload="window.print()"><div class="box"><div class="header-row"><div class="brand-col"><h3 style="margin:0 0 4px;">' . htmlspecialchars($receiptBrandName) . '</h3><p class="small">Sales Receipt</p></div><div class="meta-col"><p><strong>Transaction ID:</strong> ' . htmlspecialchars($transactionReference) . '</p><p><strong>Customer:</strong> ' . htmlspecialchars($customerName) . '</p><p><strong>Date:</strong> ' . htmlspecialchars($headerRow['saleDate']) . '</p></div></div><hr><table><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Amount</th></tr></thead><tbody>';
    foreach ($items as $item) {
        echo '<tr><td>' . htmlspecialchars($item['itemName']) . '</td><td class="right">' . htmlspecialchars($item['quantity']) . '</td><td class="right">' . htmlspecialchars(number_format((float) $item['lineTotal'], 2)) . '</td></tr>';
    }
    echo '</tbody></table><hr><div class="totals"><p class="right"><strong>Total Amount:</strong> ' . htmlspecialchars(number_format($totalAmount, 2)) . '</p><p class="right"><strong>Amount Paid:</strong> ' . htmlspecialchars(number_format($amountPaid, 2)) . '</p><p class="right"><strong>Balance:</strong> ' . htmlspecialchars(number_format($amountBalance, 2)) . '</p></div><p class="small">Thank you for patronage.</p></div></body></html>';
    exit();
}

header('Location: ../../index.php');
exit();
