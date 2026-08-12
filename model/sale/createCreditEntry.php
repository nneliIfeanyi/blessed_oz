<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

if (isset($_POST['customerID']) && isset($_POST['saleID']) && isset($_POST['amount'])) {
    $customerID = htmlentities($_POST['customerID']);
    $saleID = htmlentities($_POST['saleID']);
    $amount = (float) htmlentities($_POST['amount']);
    $entryDate = isset($_POST['entryDate']) ? htmlentities($_POST['entryDate']) : date('Y-m-d');
    $note = isset($_POST['note']) ? htmlentities($_POST['note']) : 'Sale on credit';

    try {
        $customerStatement = $conn->prepare('SELECT customerID FROM customer WHERE customerID = :customerID');
        $customerStatement->execute(['customerID' => $customerID]);
        if ($customerStatement->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Customer does not exist.']);
            exit();
        }

        $ledgerBalanceSql = 'SELECT COALESCE(balanceAfter, 0) AS balanceAfter FROM customer_ledger WHERE customerID = :customerID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1';
        $ledgerBalanceStatement = $conn->prepare($ledgerBalanceSql);
        $ledgerBalanceStatement->execute(['customerID' => $customerID]);
        $lastBalance = $ledgerBalanceStatement->fetch(PDO::FETCH_ASSOC);
        $currentBalance = isset($lastBalance['balanceAfter']) ? (float) $lastBalance['balanceAfter'] : 0;
        $newBalance = $currentBalance + $amount;

        $ledgerInsertSql = 'INSERT INTO customer_ledger(customerID, saleID, entryType, amount, balanceAfter, entryDate, note) VALUES(:customerID, :saleID, :entryType, :amount, :balanceAfter, :entryDate, :note)';
        $ledgerInsertStatement = $conn->prepare($ledgerInsertSql);
        $ledgerInsertStatement->execute(['customerID' => $customerID, 'saleID' => $saleID, 'entryType' => 'Sale', 'amount' => $amount, 'balanceAfter' => $newBalance, 'entryDate' => $entryDate, 'note' => $note]);

        echo json_encode(['success' => true, 'message' => 'Credit entry created.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Credit tables are not available yet. Please import the SQL definitions first.']);
    }
}
