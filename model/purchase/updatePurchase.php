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
    echo json_encode(['success' => false, 'message' => 'Only super admin can update purchase transactions.']);
    exit();
}

$transactionReference = isset($_POST['purchaseDetailsPurchaseID']) ? trim((string) $_POST['purchaseDetailsPurchaseID']) : '';
$purchaseDate = isset($_POST['purchaseDetailsPurchaseDate']) ? trim((string) $_POST['purchaseDetailsPurchaseDate']) : '';
$vendorName = isset($_POST['purchaseDetailsVendorName']) ? trim((string) $_POST['purchaseDetailsVendorName']) : '';
$items = isset($_POST['purchaseItems']) ? json_decode($_POST['purchaseItems'], true) : null;

if ($transactionReference === '' || $purchaseDate === '' || $vendorName === '' || !is_array($items) || count($items) === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID, vendor, date, and at least one item are required.']);
    exit();
}

try {
    $conn->beginTransaction();
    $headerStatement = $conn->prepare('SELECT id FROM purchase_headers WHERE transactionReference = :transactionReference AND storeID = :storeID FOR UPDATE');
    $headerStatement->execute(['transactionReference' => $transactionReference, 'storeID' => $activeStoreID]);
    if (!$headerStatement->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Transaction ID does not exist.');
    }
    $vendorStatement = $conn->prepare('SELECT vendorID FROM vendor WHERE fullName = :vendorName AND storeID = :storeID');
    $vendorStatement->execute(['vendorName' => $vendorName, 'storeID' => $activeStoreID]);
    $vendor = $vendorStatement->fetch(PDO::FETCH_ASSOC);
    if (!$vendor) {
        throw new Exception('Vendor does not exist.');
    }

    $oldStatement = $conn->prepare('SELECT itemNumber, quantity FROM purchase_items WHERE transactionReference = :transactionReference AND storeID = :storeID FOR UPDATE');
    $oldStatement->execute(['transactionReference' => $transactionReference, 'storeID' => $activeStoreID]);
    $oldQuantities = [];
    foreach ($oldStatement->fetchAll(PDO::FETCH_ASSOC) as $oldItem) {
        $oldQuantities[$oldItem['itemNumber']] = ($oldQuantities[$oldItem['itemNumber']] ?? 0) + (int) $oldItem['quantity'];
    }

    $newItems = [];
    $newQuantities = [];
    foreach ($items as $item) {
        $itemNumber = isset($item['itemNumber']) ? trim((string) $item['itemNumber']) : '';
        $quantity = isset($item['quantity']) ? filter_var($item['quantity'], FILTER_VALIDATE_INT) : false;
        $unitPrice = isset($item['unitPrice']) ? filter_var($item['unitPrice'], FILTER_VALIDATE_FLOAT) : false;
        if ($itemNumber === '' || $quantity === false || $quantity <= 0 || $unitPrice === false || $unitPrice < 0) {
            throw new Exception('Each purchase item needs a valid item number, positive quantity, and price.');
        }
        $newQuantities[$itemNumber] = ($newQuantities[$itemNumber] ?? 0) + (int) $quantity;
        $newItems[] = ['itemNumber' => $itemNumber, 'quantity' => (int) $quantity, 'unitPrice' => (float) $unitPrice, 'lineTotal' => round((float) $unitPrice * (int) $quantity, 2)];
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
        $revisedStock = (int) $inventoryItem['stock'] - ($oldQuantities[$itemNumber] ?? 0) + ($newQuantities[$itemNumber] ?? 0);
        if ($revisedStock < 0) {
            throw new Exception('Updating this purchase would make stock negative for item ' . $itemNumber . '.');
        }
        $updateStock->execute(['stock' => $revisedStock, 'itemNumber' => $itemNumber, 'storeID' => $activeStoreID]);
    }

    $conn->prepare('UPDATE purchase_headers SET vendorName = :vendorName, purchaseDate = :purchaseDate WHERE transactionReference = :transactionReference AND storeID = :storeID')->execute(['vendorName' => $vendorName, 'purchaseDate' => $purchaseDate, 'transactionReference' => $transactionReference, 'storeID' => $activeStoreID]);
    $conn->prepare('DELETE FROM purchase_items WHERE transactionReference = :transactionReference AND storeID = :storeID')->execute(['transactionReference' => $transactionReference, 'storeID' => $activeStoreID]);
    $conn->prepare('DELETE FROM purchase WHERE transactionReference = :transactionReference AND storeID = :storeID')->execute(['transactionReference' => $transactionReference, 'storeID' => $activeStoreID]);
    $itemNameStatement = $conn->prepare('SELECT itemName FROM item WHERE itemNumber = :itemNumber AND storeID = :storeID');
    $insertItem = $conn->prepare('INSERT INTO purchase_items(storeID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt) VALUES(:storeID, :transactionReference, :itemNumber, :itemName, :quantity, :unitPrice, :lineTotal, NOW())');
    $insertPurchase = $conn->prepare('INSERT INTO purchase(storeID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference) VALUES(:storeID, :itemNumber, :purchaseDate, :itemName, :unitPrice, :quantity, :vendorName, :vendorID, :transactionReference)');
    foreach ($newItems as $item) {
        $itemNameStatement->execute(['itemNumber' => $item['itemNumber'], 'storeID' => $activeStoreID]);
        $item['itemName'] = $itemNameStatement->fetchColumn();
        $insertItem->execute(array_merge(['storeID' => $activeStoreID, 'transactionReference' => $transactionReference], $item));
        $insertPurchase->execute(array_merge(['storeID' => $activeStoreID, 'purchaseDate' => $purchaseDate, 'vendorName' => $vendorName, 'vendorID' => $vendor['vendorID'], 'transactionReference' => $transactionReference], $item));
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Purchase transaction updated successfully.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
