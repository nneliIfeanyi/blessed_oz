<?php
session_start();
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

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

        if ($paymentAmount <= 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Please enter a valid payment amount.']);
            exit();
        }

        $saleReferenceColumnCheck = $conn->query("SHOW COLUMNS FROM customer_payments LIKE 'saleReference'");
        if ($saleReferenceColumnCheck->rowCount() === 0) {
            $conn->exec("ALTER TABLE customer_payments ADD COLUMN saleReference VARCHAR(50) DEFAULT NULL");
        }

        $customerStatement = $conn->prepare('SELECT customerID FROM customer WHERE customerID = :customerID AND storeID = :storeID');
        $customerStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
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

            $transactionCheckSql = 'SELECT customerID FROM sale_headers WHERE saleReference = :saleReference AND storeID = :storeID';
            $transactionCheckStatement = $conn->prepare($transactionCheckSql);
            $transactionCheckStatement->execute(['saleReference' => $transactionID, 'storeID' => $activeStoreID]);
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

        $ledgerBalanceSql = 'SELECT COALESCE(balanceAfter, 0) AS balanceAfter FROM customer_ledger WHERE customerID = :customerID AND storeID = :storeID ORDER BY entryDate DESC, ledgerID DESC LIMIT 1';
        $ledgerBalanceStatement = $conn->prepare($ledgerBalanceSql);
        $ledgerBalanceStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
        $lastBalance = $ledgerBalanceStatement->fetch(PDO::FETCH_ASSOC);
        $currentBalance = isset($lastBalance['balanceAfter']) ? (float) $lastBalance['balanceAfter'] : 0;

        if ($paymentAmount - $currentBalance > 0.009) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Payment amount cannot be greater than the current outstanding balance.']);
            exit();
        }

        $newBalance = max(0, $currentBalance - $paymentAmount);

        $paymentNote = $note;
        if ($transactionID !== '') {
            $paymentNote = trim($paymentNote . ' [Txn: ' . $transactionID . ']');
        }

        $paymentInsertSql = 'INSERT INTO customer_payments(storeID, customerID, saleReference, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber) VALUES(:storeID, :customerID, :saleReference, :amount, :paymentDate, :paymentMethod, :referenceNumber, :note, :receiptNumber)';
        $paymentInsertStatement = $conn->prepare($paymentInsertSql);
        $paymentInsertStatement->execute(['storeID' => $activeStoreID, 'customerID' => $customerID, 'saleReference' => ($transactionID !== '' ? $transactionID : null), 'amount' => $paymentAmount, 'paymentDate' => $paymentDate, 'paymentMethod' => $paymentMethod, 'referenceNumber' => $referenceNumber, 'note' => $paymentNote, 'receiptNumber' => $receiptNumber]);

        if ($transactionID !== '') {
            $updateTransactionPaidSql = 'UPDATE sale_headers SET amountPaid = amountPaid + :paymentAmount WHERE saleReference = :saleReference AND storeID = :storeID';
            $updateTransactionPaidStatement = $conn->prepare($updateTransactionPaidSql);
            $updateTransactionPaidStatement->execute(['paymentAmount' => $paymentAmount, 'saleReference' => $transactionID, 'storeID' => $activeStoreID]);
        } else {
            $outstandingSql = 'SELECT sh.saleReference, ROUND(COALESCE(SUM(si.lineTotal), 0) - COALESCE(sh.amountPaid, 0), 2) AS balance FROM sale_headers sh LEFT JOIN sale_items si ON si.saleReference = sh.saleReference AND si.storeID = sh.storeID WHERE sh.customerID = :customerID AND sh.storeID = :storeID GROUP BY sh.id, sh.saleReference, sh.saleDate, sh.amountPaid HAVING balance > 0 ORDER BY sh.saleDate ASC, sh.id ASC';
            $outstandingStatement = $conn->prepare($outstandingSql);
            $outstandingStatement->execute(['customerID' => $customerID, 'storeID' => $activeStoreID]);
            $outstandingRows = $outstandingStatement->fetchAll(PDO::FETCH_ASSOC);

            if (count($outstandingRows) < 1) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'No outstanding transactions found for this customer.']);
                exit();
            }

            $remainingPayment = round($paymentAmount, 2);
            $allocatedReferences = [];

            foreach ($outstandingRows as $outstandingRow) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $transactionBalance = round((float) $outstandingRow['balance'], 2);
                if ($transactionBalance <= 0) {
                    continue;
                }

                $allocationAmount = round(min($remainingPayment, $transactionBalance), 2);
                if ($allocationAmount <= 0) {
                    continue;
                }

                $updateTransactionPaidSql = 'UPDATE sale_headers SET amountPaid = amountPaid + :paymentAmount WHERE saleReference = :saleReference AND storeID = :storeID';
                $updateTransactionPaidStatement = $conn->prepare($updateTransactionPaidSql);
                $updateTransactionPaidStatement->execute([
                    'paymentAmount' => $allocationAmount,
                    'saleReference' => $outstandingRow['saleReference'],
                    'storeID' => $activeStoreID
                ]);

                $allocatedReferences[] = $outstandingRow['saleReference'];
                $remainingPayment = round($remainingPayment - $allocationAmount, 2);
            }

            if ($remainingPayment > 0.009) {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Unable to fully allocate this payment to outstanding transactions. Please use Transaction ID for this payment.']);
                exit();
            }

            if (!empty($allocatedReferences)) {
                $paymentNote = trim($paymentNote . ' [Auto-applied: ' . implode(', ', array_unique($allocatedReferences)) . ']');
            }
        }

        $ledgerInsertSql = 'INSERT INTO customer_ledger(storeID, customerID, entryType, amount, balanceAfter, entryDate, note) VALUES(:storeID, :customerID, :entryType, :amount, :balanceAfter, :entryDate, :note)';
        $ledgerInsertStatement = $conn->prepare($ledgerInsertSql);
        $ledgerInsertStatement->execute(['storeID' => $activeStoreID, 'customerID' => $customerID, 'entryType' => 'Payment', 'amount' => -$paymentAmount, 'balanceAfter' => $newBalance, 'entryDate' => $paymentDate, 'note' => $paymentNote]);

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Payment tables are not available yet. Please import the SQL definitions first.']);
    }
}
