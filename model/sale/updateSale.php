<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];
header('Content-Type: application/json');

if (!userCanManageUsers()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only super admin can update sale transactions.']);
    exit();
}

$saleReference = isset($_POST['saleDetailsSaleID']) ? trim((string) $_POST['saleDetailsSaleID']) : '';
$customerID = isset($_POST['saleDetailsCustomerID']) ? (int) $_POST['saleDetailsCustomerID'] : 0;
$saleDate = isset($_POST['saleDetailsSaleDate']) ? trim((string) $_POST['saleDetailsSaleDate']) : '';
$amountPaid = isset($_POST['saleDetailsAmountPaid']) ? (float) $_POST['saleDetailsAmountPaid'] : 0;
$items = isset($_POST['saleItems']) ? json_decode($_POST['saleItems'], true) : null;

if ($saleReference === '' || $customerID <= 0 || $saleDate === '' || !is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID, customer, date, and at least one item are required.']);
    exit();
}

try {
    $conn->beginTransaction();
    $headerStatement = $conn->prepare('SELECT customerID, amountPaid FROM sale_headers WHERE saleReference = :saleReference AND storeID = :storeID FOR UPDATE');
    $headerStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
    $header = $headerStatement->fetch(PDO::FETCH_ASSOC);
    if (!$header) {
        throw new Exception('Transaction ID does not exist.');
    }
    if ((int) $header['customerID'] !== $customerID) {
        throw new Exception('Changing the customer on an existing sale is not supported because it can affect credit records.');
    }
    $laterPaymentsStatement = $conn->prepare('SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE saleReference = :saleReference AND storeID = :storeID');
    $laterPaymentsStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
    $laterPayments = round((float) $laterPaymentsStatement->fetchColumn(), 2);
    $originalInitialPayment = round(max(0, (float) $header['amountPaid'] - $laterPayments), 2);

    $oldStatement = $conn->prepare('SELECT itemNumber, quantity, lineTotal FROM sale_items WHERE saleReference = :saleReference AND storeID = :storeID FOR UPDATE');
    $oldStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
    $oldQuantities = [];
    $oldTotal = 0;
    foreach ($oldStatement->fetchAll(PDO::FETCH_ASSOC) as $oldItem) {
        $oldQuantities[$oldItem['itemNumber']] = ($oldQuantities[$oldItem['itemNumber']] ?? 0) + (int) $oldItem['quantity'];
        $oldTotal += (float) $oldItem['lineTotal'];
    }

    $newItems = [];
    $newQuantities = [];
    $total = 0;
    foreach ($items as $item) {
        $itemNumber = isset($item['itemNumber']) ? trim((string) $item['itemNumber']) : '';
        $quantity = isset($item['quantity']) ? filter_var($item['quantity'], FILTER_VALIDATE_INT) : false;
        $unitPrice = isset($item['unitPrice']) ? filter_var($item['unitPrice'], FILTER_VALIDATE_FLOAT) : false;
        $discount = isset($item['discount']) && $item['discount'] !== '' ? filter_var($item['discount'], FILTER_VALIDATE_FLOAT) : 0;
        if ($itemNumber === '' || $quantity === false || $quantity <= 0 || $unitPrice === false || $unitPrice < 0 || $discount === false || $discount < 0 || $discount > 100) {
            throw new Exception('Each sale item needs a valid item number, positive quantity, price, and discount.');
        }
        $newQuantities[$itemNumber] = ($newQuantities[$itemNumber] ?? 0) + (int) $quantity;
        $lineTotal = round((float) $unitPrice * ((100 - (float) $discount) / 100) * (int) $quantity, 2);
        $total += $lineTotal;
        $newItems[] = ['itemNumber' => $itemNumber, 'quantity' => (int) $quantity, 'unitPrice' => (float) $unitPrice, 'discount' => (float) $discount, 'reason' => isset($item['reason']) ? trim((string) $item['reason']) : 'Sales', 'lineTotal' => $lineTotal];
    }
    if ($amountPaid < 0 || $amountPaid + $laterPayments - $total > 0.009) {
        throw new Exception('Initial amount paid plus later payments cannot exceed the revised sale total.');
    }

    $allItemNumbers = array_unique(array_merge(array_keys($oldQuantities), array_keys($newQuantities)));
    $inventoryStatement = $conn->prepare('SELECT stock FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID FOR UPDATE');
    $updateStock = $conn->prepare('UPDATE item SET stock = :stock WHERE itemNumber = :itemNumber AND storeID = :storeID');
    foreach ($allItemNumbers as $itemNumber) {
        $inventoryStatement->execute(['itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
        $inventoryItem = $inventoryStatement->fetch(PDO::FETCH_ASSOC);
        if (!$inventoryItem) {
            throw new Exception('Item ' . $itemNumber . ' does not exist.');
        }
        $revisedStock = (int) $inventoryItem['stock'] + ($oldQuantities[$itemNumber] ?? 0) - ($newQuantities[$itemNumber] ?? 0);
        if ($revisedStock < 0) {
            throw new Exception('Not enough stock for item ' . $itemNumber . '.');
        }
        $updateStock->execute(['stock' => $revisedStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
    }

    $customerStatement = $conn->prepare('SELECT fullName FROM customer WHERE customerID = :customerID AND storeID = :storeID');
    $customerStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
    $customer = $customerStatement->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        throw new Exception('Customer does not exist.');
    }

    $newOutstanding = round($total - $amountPaid - $laterPayments, 2);
    $oldOutstanding = round($oldTotal - $originalInitialPayment - $laterPayments, 2);
    $creditAdjustment = round($newOutstanding - $oldOutstanding, 2);

    $conn->prepare('UPDATE sale_headers SET customerName = :customerName, saleDate = :saleDate, amountPaid = :amountPaid WHERE saleReference = :saleReference AND storeID = :storeID')->execute(['customerName' => $customer['fullName'], 'saleDate' => $saleDate, 'amountPaid' => $amountPaid + $laterPayments, 'saleReference' => $saleReference, 'storeID' => $activeStoreID]);
    $conn->prepare('DELETE FROM sale_items WHERE saleReference = :saleReference AND storeID = :storeID')->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);
    $itemNameStatement = $conn->prepare('SELECT itemName FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID');
    $insertItem = $conn->prepare('INSERT INTO sale_items(storeID, saleReference, itemNumber, itemName, discount, quantity, unitPrice, reason, lineTotal, createdAt) VALUES(:storeID, :saleReference, :itemNumber, :itemName, :discount, :quantity, :unitPrice, :reason, :lineTotal, NOW())');
    foreach ($newItems as $item) {
        $itemNameStatement->execute(['itemNumber' => $item['itemNumber'], 'storeID' => $activeStoreID]);
        $item['itemName'] = $itemNameStatement->fetchColumn();
        $insertItem->execute(array_merge(['storeID' => $activeStoreID, 'saleReference' => $saleReference], $item));
    }

    if (abs($creditAdjustment) > 0.009) {
        $ledgerBalanceStatement = $conn->prepare('SELECT COALESCE(balanceAfter, 0) FROM customer_ledger WHERE customerID = :customerID AND storeID = :storeID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1 FOR UPDATE');
        $ledgerBalanceStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
        $currentBalance = (float) $ledgerBalanceStatement->fetchColumn();
        $newBalance = round(max(0, $currentBalance + $creditAdjustment), 2);
        $ledgerInsertStatement = $conn->prepare('INSERT INTO customer_ledger(storeID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note) VALUES(:storeID, :customerID, :saleID, :entryType, :amount, :balanceAfter, :entryDate, :note)');
        $ledgerInsertStatement->execute([
            'storeID' => $activeStoreID,
            'customerID' => $customerID,
            'saleID' => null,
            'entryType' => 'Sale Adjustment',
            'amount' => $creditAdjustment,
            'balanceAfter' => $newBalance,
            'entryDate' => date('Y-m-d'),
            'note' => 'Initial payment or sale total adjusted for ' . $saleReference
        ]);
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Sale transaction updated successfully.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
