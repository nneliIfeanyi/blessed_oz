<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

header('Content-Type: application/json');

if (!isset($_POST['customerID']) || !isset($_POST['saleReference']) || !isset($_POST['paymentAmount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing payment details.']);
    exit();
}

$customerID = trim(htmlentities($_POST['customerID']));
$saleReference = trim(htmlentities($_POST['saleReference']));
$paymentAmount = (float) $_POST['paymentAmount'];
$paymentDate = date('Y-m-d');

if (filter_var($customerID, FILTER_VALIDATE_INT) === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer selected.']);
    exit();
}

if ($saleReference === '') {
    echo json_encode(['success' => false, 'message' => 'Transaction ID is required.']);
    exit();
}

if ($paymentAmount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid payment amount.']);
    exit();
}

try {
    $conn->beginTransaction();

    $transactionSql = 'SELECT sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, ROUND(COALESCE(SUM(si.lineTotal), 0), 2) AS amountDue, ROUND(COALESCE(sh.amountPaid, 0), 2) AS amountPaid FROM sale_headers sh LEFT JOIN sale_items si ON si.saleReference = sh.saleReference AND si.storeID = sh.storeID WHERE sh.saleReference = :saleReference AND sh.storeID = :storeID GROUP BY sh.id, sh.saleReference, sh.customerID, sh.customerName, sh.saleDate, sh.amountPaid LIMIT 1';
    $transactionStatement = $conn->prepare($transactionSql);
    $transactionStatement->execute(['saleReference' => $saleReference, 'storeID' => $activeStoreID]);

    if ($transactionStatement->rowCount() < 1) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Transaction ID does not exist.']);
        exit();
    }

    $transaction = $transactionStatement->fetch(PDO::FETCH_ASSOC);
    if ((string) $transaction['customerID'] !== (string) $customerID) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Transaction is not linked to this customer.']);
        exit();
    }

    $amountDue = round((float) $transaction['amountDue'], 2);
    $amountPaid = round((float) $transaction['amountPaid'], 2);
    $currentBalance = round($amountDue - $amountPaid, 2);

    if ($currentBalance <= 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'This transaction has already been fully paid.']);
        exit();
    }

    if ($paymentAmount - $currentBalance > 0.009) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Payment amount cannot be greater than the outstanding balance.']);
        exit();
    }

    $newAmountPaid = round($amountPaid + $paymentAmount, 2);
    $newTransactionBalance = round(max(0, $amountDue - $newAmountPaid), 2);

    $updateHeaderSql = 'UPDATE sale_headers SET amountPaid = :amountPaid WHERE saleReference = :saleReference AND storeID = :storeID';
    $updateHeaderStatement = $conn->prepare($updateHeaderSql);
    $updateHeaderStatement->execute(['amountPaid' => $newAmountPaid, 'saleReference' => $saleReference, 'storeID' => $activeStoreID]);

    $paymentPayload = [
        'storeID' => $activeStoreID,
        'customerID' => $customerID,
        'saleID' => null,
        'amount' => $paymentAmount,
        'paymentDate' => $paymentDate,
        'paymentMethod' => 'Cash',
        'referenceNumber' => '',
        'note' => 'Credit payment for ' . $saleReference,
        'receiptNumber' => 'RC-' . $customerID . '-' . time()
    ];

    $saleReferenceColumnCheck = $conn->query("SHOW COLUMNS FROM customer_payments LIKE 'saleReference'");
    if ($saleReferenceColumnCheck->rowCount() > 0) {
        $paymentInsertSql = 'INSERT INTO customer_payments(storeID, customerID, saleReference, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber) VALUES(:storeID, :customerID, :saleReference, :saleID, :amount, :paymentDate, :paymentMethod, :referenceNumber, :note, :receiptNumber)';
        $paymentPayload['saleReference'] = $saleReference;
    } else {
        $paymentInsertSql = 'INSERT INTO customer_payments(storeID, customerID, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber) VALUES(:storeID, :customerID, :saleID, :amount, :paymentDate, :paymentMethod, :referenceNumber, :note, :receiptNumber)';
    }

    $paymentInsertStatement = $conn->prepare($paymentInsertSql);
    $paymentInsertStatement->execute($paymentPayload);

    $ledgerBalanceSql = 'SELECT COALESCE(balanceAfter, 0) AS balanceAfter FROM customer_ledger WHERE customerID = :customerID AND storeID = :storeID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1';
    $ledgerBalanceStatement = $conn->prepare($ledgerBalanceSql);
    $ledgerBalanceStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
    $lastBalance = $ledgerBalanceStatement->fetch(PDO::FETCH_ASSOC);
    $customerBalance = isset($lastBalance['balanceAfter']) ? (float) $lastBalance['balanceAfter'] : 0;
    $newCustomerBalance = round(max(0, $customerBalance - $paymentAmount), 2);

    $ledgerInsertSql = 'INSERT INTO customer_ledger(storeID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note) VALUES(:storeID, :customerID, :saleID, :entryType, :amount, :balanceAfter, :entryDate, :note)';
    $ledgerInsertStatement = $conn->prepare($ledgerInsertSql);
    $ledgerInsertStatement->execute([
        'storeID' => $activeStoreID,
        'customerID' => $customerID,
        'saleID' => null,
        'entryType' => 'Payment',
        'amount' => -$paymentAmount,
        'balanceAfter' => $newCustomerBalance,
        'entryDate' => $paymentDate,
        'note' => 'Credit payment for ' . $saleReference
    ]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $newTransactionBalance <= 0 ? 'Payment recorded and creditor cleared successfully.' : 'Payment recorded successfully.',
        'amountDue' => $amountDue,
        'amountPaid' => $newAmountPaid,
        'balance' => $newTransactionBalance,
        'removeRow' => $newTransactionBalance <= 0
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode(['success' => false, 'message' => 'Unable to update creditor payment right now.']);
}
