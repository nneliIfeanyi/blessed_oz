<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

if (isset($_POST['customerID']) && isset($_POST['paymentAmount'])) {
    $customerID = htmlentities($_POST['customerID']);
    $paymentAmount = (float) htmlentities($_POST['paymentAmount']);
    $paymentDate = isset($_POST['paymentDate']) ? htmlentities($_POST['paymentDate']) : date('Y-m-d');
    $paymentMethod = isset($_POST['paymentMethod']) ? htmlentities($_POST['paymentMethod']) : 'Cash';
    $referenceNumber = isset($_POST['referenceNumber']) ? htmlentities($_POST['referenceNumber']) : '';
    $note = isset($_POST['note']) ? htmlentities($_POST['note']) : '';
    $receiptNumber = isset($_POST['receiptNumber']) ? htmlentities($_POST['receiptNumber']) : '';
    $transactionID = isset($_POST['transactionID']) ? trim(htmlentities($_POST['transactionID'])) : '';

    try {
        $conn->beginTransaction();

        $saleReferenceColumnCheck = $conn->query("SHOW COLUMNS FROM customer_payments LIKE 'saleReference'");
        if ($saleReferenceColumnCheck->rowCount() === 0) {
            $conn->exec("ALTER TABLE customer_payments ADD COLUMN saleReference VARCHAR(50) DEFAULT NULL");
        }

        $customerStatement = $conn->prepare('SELECT customerID FROM customer WHERE customerID = :customerID');
        $customerStatement->execute(['customerID' => $customerID]);
        if ($customerStatement->rowCount() < 1) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Customer does not exist.']);
            exit();
        }

        if ($transactionID !== '') {
            if (strpos($transactionID, 'TXN-') !== 0) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Please enter a valid Transaction ID.']);
                exit();
            }

            $transactionCheckSql = 'SELECT customerID FROM sale_headers WHERE saleReference = :saleReference';
            $transactionCheckStatement = $conn->prepare($transactionCheckSql);
            $transactionCheckStatement->execute(['saleReference' => $transactionID]);
            if ($transactionCheckStatement->rowCount() < 1) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Transaction ID does not exist.']);
                exit();
            }

            $transactionRow = $transactionCheckStatement->fetch(PDO::FETCH_ASSOC);
            if ((string) $transactionRow['customerID'] !== (string) $customerID) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Transaction ID is not linked to this customer.']);
                exit();
            }
        }

        $ledgerBalanceSql = 'SELECT COALESCE(balanceAfter, 0) AS balanceAfter FROM customer_ledger WHERE customerID = :customerID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1';
        $ledgerBalanceStatement = $conn->prepare($ledgerBalanceSql);
        $ledgerBalanceStatement->execute(['customerID' => $customerID]);
        $lastBalance = $ledgerBalanceStatement->fetch(PDO::FETCH_ASSOC);
        $currentBalance = isset($lastBalance['balanceAfter']) ? (float) $lastBalance['balanceAfter'] : 0;
        $newBalance = max(0, $currentBalance - $paymentAmount);

        $paymentNote = $note;
        if ($transactionID !== '') {
            $paymentNote = trim($paymentNote . ' [Txn: ' . $transactionID . ']');
        }

        $paymentInsertSql = 'INSERT INTO customer_payments(customerID, saleReference, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber) VALUES(:customerID, :saleReference, :amount, :paymentDate, :paymentMethod, :referenceNumber, :note, :receiptNumber)';
        $paymentInsertStatement = $conn->prepare($paymentInsertSql);
        $paymentInsertStatement->execute(['customerID' => $customerID, 'saleReference' => ($transactionID !== '' ? $transactionID : null), 'amount' => $paymentAmount, 'paymentDate' => $paymentDate, 'paymentMethod' => $paymentMethod, 'referenceNumber' => $referenceNumber, 'note' => $paymentNote, 'receiptNumber' => $receiptNumber]);

        if ($transactionID !== '') {
            $updateTransactionPaidSql = 'UPDATE sale_headers SET amountPaid = amountPaid + :paymentAmount WHERE saleReference = :saleReference';
            $updateTransactionPaidStatement = $conn->prepare($updateTransactionPaidSql);
            $updateTransactionPaidStatement->execute(['paymentAmount' => $paymentAmount, 'saleReference' => $transactionID]);
        }

        $ledgerInsertSql = 'INSERT INTO customer_ledger(customerID, entryType, amount, balanceAfter, entryDate, note) VALUES(:customerID, :entryType, :amount, :balanceAfter, :entryDate, :note)';
        $ledgerInsertStatement = $conn->prepare($ledgerInsertSql);
        $ledgerInsertStatement->execute(['customerID' => $customerID, 'entryType' => 'Payment', 'amount' => -$paymentAmount, 'balanceAfter' => $newBalance, 'entryDate' => $paymentDate, 'note' => $paymentNote]);

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Payment tables are not available yet. Please import the SQL definitions first.']);
    }
}
