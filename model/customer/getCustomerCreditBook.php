<?php
require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');

if (isset($_POST['customerID'])) {
    $customerID = htmlentities($_POST['customerID']);

    try {
        $customerSql = 'SELECT customerID, fullName FROM customer WHERE customerID = :customerID';
        $customerStatement = $conn->prepare($customerSql);
        $customerStatement->execute(['customerID' => $customerID]);

        if ($customerStatement->rowCount() < 1) {
            echo json_encode(['success' => false, 'message' => 'Customer does not exist.']);
            exit();
        }

        $customerRow = $customerStatement->fetch(PDO::FETCH_ASSOC);
        $ledgerRows = array();
        $balance = 0;

        $ledgerSql = 'SELECT * FROM customer_ledger WHERE customerID = :customerID ORDER BY entryDate DESC, ledgerID DESC';
        $ledgerStatement = $conn->prepare($ledgerSql);
        $ledgerStatement->execute(['customerID' => $customerID]);
        while ($row = $ledgerStatement->fetch(PDO::FETCH_ASSOC)) {
            $ledgerRows[] = $row;
        }

        if (!empty($ledgerRows)) {
            $latestLedgerRow = $ledgerRows[0];
            $balance = isset($latestLedgerRow['balanceAfter']) && $latestLedgerRow['balanceAfter'] !== '' ? (float) $latestLedgerRow['balanceAfter'] : 0;
        }

        echo json_encode([
            'success' => true,
            'customerName' => $customerRow['fullName'],
            'outstandingBalance' => $balance,
            'ledger' => $ledgerRows
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Credit book tables are not available yet. Please import the SQL definitions first.']);
    }
}
