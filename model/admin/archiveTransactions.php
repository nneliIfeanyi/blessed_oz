<?php
session_start();

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');

ensureUserRoleColumn($conn);
bootstrapFirstSuperAdmin($conn);

if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== '1' || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../settings.php');
    exit();
}

$archiveNote = isset($_POST['archiveNote']) ? trim((string) $_POST['archiveNote']) : '';
$clearCreditData = isset($_POST['clearCreditData']) && $_POST['clearCreditData'] === '1';

try {

    if ($clearCreditData) {
        $paymentsSaleReferenceColumn = $conn->query("SHOW COLUMNS FROM `customer_payments` LIKE 'saleReference'");
        if ($paymentsSaleReferenceColumn->rowCount() === 0) {
            $conn->exec("ALTER TABLE `customer_payments` ADD COLUMN `saleReference` varchar(255) DEFAULT NULL");
        }
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS `archive_batches` (
        `batchID` int(11) NOT NULL AUTO_INCREMENT,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `archivedByUserID` int(11) DEFAULT NULL,
        `archivedByUsername` varchar(255) DEFAULT NULL,
        `note` varchar(255) DEFAULT NULL,
        `purchaseRows` int(11) NOT NULL DEFAULT '0',
        `purchaseHeaderRows` int(11) NOT NULL DEFAULT '0',
        `purchaseItemRows` int(11) NOT NULL DEFAULT '0',
        `saleRows` int(11) NOT NULL DEFAULT '0',
        `saleHeaderRows` int(11) NOT NULL DEFAULT '0',
        `saleItemRows` int(11) NOT NULL DEFAULT '0',
        `ledgerRows` int(11) NOT NULL DEFAULT '0',
        `paymentRows` int(11) NOT NULL DEFAULT '0',
        PRIMARY KEY (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `purchase_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `purchaseID` int(11) DEFAULT NULL,
        `itemNumber` varchar(255) DEFAULT NULL,
        `purchaseDate` date DEFAULT NULL,
        `itemName` varchar(255) DEFAULT NULL,
        `unitPrice` float NOT NULL DEFAULT '0',
        `quantity` int(11) NOT NULL DEFAULT '0',
        `vendorName` varchar(255) DEFAULT NULL,
        `vendorID` int(11) DEFAULT NULL,
        `transactionReference` varchar(255) DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `purchase_headers_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `id` int(11) DEFAULT NULL,
        `transactionReference` varchar(50) DEFAULT NULL,
        `vendorName` varchar(255) DEFAULT NULL,
        `purchaseDate` date DEFAULT NULL,
        `createdAt` timestamp NULL DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `purchase_items_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `purchaseItemID` int(11) DEFAULT NULL,
        `transactionReference` varchar(50) DEFAULT NULL,
        `itemNumber` varchar(255) DEFAULT NULL,
        `itemName` varchar(255) DEFAULT NULL,
        `quantity` int(11) NOT NULL DEFAULT '0',
        `unitPrice` float NOT NULL DEFAULT '0',
        `lineTotal` float NOT NULL DEFAULT '0',
        `createdAt` timestamp NULL DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `sale_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `saleID` int(11) DEFAULT NULL,
        `itemNumber` varchar(255) DEFAULT NULL,
        `customerID` int(11) DEFAULT NULL,
        `customerName` varchar(255) DEFAULT NULL,
        `itemName` varchar(255) DEFAULT NULL,
        `saleDate` date DEFAULT NULL,
        `discount` float NOT NULL DEFAULT '0',
        `quantity` int(11) NOT NULL DEFAULT '0',
        `unitPrice` float NOT NULL DEFAULT '0',
        `reason` varchar(255) DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `sale_headers_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `id` int(11) DEFAULT NULL,
        `saleReference` varchar(50) DEFAULT NULL,
        `customerID` int(11) DEFAULT NULL,
        `customerName` varchar(255) DEFAULT NULL,
        `saleDate` date DEFAULT NULL,
        `amountPaid` float NOT NULL DEFAULT '0',
        `createdAt` timestamp NULL DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `sale_items_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `saleItemID` int(11) DEFAULT NULL,
        `saleReference` varchar(50) DEFAULT NULL,
        `itemNumber` varchar(255) DEFAULT NULL,
        `itemName` varchar(255) DEFAULT NULL,
        `discount` float NOT NULL DEFAULT '0',
        `reason` varchar(255) DEFAULT NULL,
        `quantity` int(11) NOT NULL DEFAULT '0',
        `unitPrice` float NOT NULL DEFAULT '0',
        `lineTotal` float NOT NULL DEFAULT '0',
        `createdAt` timestamp NULL DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `customer_ledger_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `ledgerID` int(11) DEFAULT NULL,
        `customerID` int(11) DEFAULT NULL,
        `saleID` int(11) DEFAULT NULL,
        `entryType` varchar(255) DEFAULT NULL,
        `amount` float NOT NULL DEFAULT '0',
        `balanceAfter` float NOT NULL DEFAULT '0',
        `entryDate` date DEFAULT NULL,
        `note` text DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $conn->exec("CREATE TABLE IF NOT EXISTS `customer_payments_archive` (
        `archiveID` int(11) NOT NULL AUTO_INCREMENT,
        `batchID` int(11) NOT NULL,
        `paymentID` int(11) DEFAULT NULL,
        `customerID` int(11) DEFAULT NULL,
        `saleID` int(11) DEFAULT NULL,
        `amount` float NOT NULL DEFAULT '0',
        `paymentDate` date DEFAULT NULL,
        `paymentMethod` varchar(255) DEFAULT NULL,
        `referenceNumber` varchar(255) DEFAULT NULL,
        `note` text DEFAULT NULL,
        `receiptNumber` varchar(255) DEFAULT NULL,
        `saleReference` varchar(255) DEFAULT NULL,
        `archivedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`archiveID`),
        KEY `batchID` (`batchID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

    $rowCounts = [
        'purchaseRows' => (int) $conn->query('SELECT COUNT(*) FROM purchase')->fetchColumn(),
        'purchaseHeaderRows' => (int) $conn->query('SELECT COUNT(*) FROM purchase_headers')->fetchColumn(),
        'purchaseItemRows' => (int) $conn->query('SELECT COUNT(*) FROM purchase_items')->fetchColumn(),
        'saleRows' => (int) $conn->query('SELECT COUNT(*) FROM sale')->fetchColumn(),
        'saleHeaderRows' => (int) $conn->query('SELECT COUNT(*) FROM sale_headers')->fetchColumn(),
        'saleItemRows' => (int) $conn->query('SELECT COUNT(*) FROM sale_items')->fetchColumn(),
        'ledgerRows' => $clearCreditData ? (int) $conn->query('SELECT COUNT(*) FROM customer_ledger')->fetchColumn() : 0,
        'paymentRows' => $clearCreditData ? (int) $conn->query('SELECT COUNT(*) FROM customer_payments')->fetchColumn() : 0,
    ];

    $totalRows = array_sum($rowCounts);
    if ($totalRows === 0) {
        header('Location: ../../settings.php?archiveStatus=empty');
        exit();
    }

    $conn->beginTransaction();

    $insertBatchSql = 'INSERT INTO archive_batches(archivedByUserID, archivedByUsername, note, purchaseRows, purchaseHeaderRows, purchaseItemRows, saleRows, saleHeaderRows, saleItemRows, ledgerRows, paymentRows)
        VALUES(:archivedByUserID, :archivedByUsername, :note, :purchaseRows, :purchaseHeaderRows, :purchaseItemRows, :saleRows, :saleHeaderRows, :saleItemRows, :ledgerRows, :paymentRows)';
    $insertBatchStatement = $conn->prepare($insertBatchSql);
    $insertBatchStatement->execute([
        'archivedByUserID' => isset($_SESSION['userID']) ? (int) $_SESSION['userID'] : null,
        'archivedByUsername' => isset($_SESSION['username']) ? (string) $_SESSION['username'] : null,
        'note' => $archiveNote !== '' ? $archiveNote : null,
        'purchaseRows' => $rowCounts['purchaseRows'],
        'purchaseHeaderRows' => $rowCounts['purchaseHeaderRows'],
        'purchaseItemRows' => $rowCounts['purchaseItemRows'],
        'saleRows' => $rowCounts['saleRows'],
        'saleHeaderRows' => $rowCounts['saleHeaderRows'],
        'saleItemRows' => $rowCounts['saleItemRows'],
        'ledgerRows' => $rowCounts['ledgerRows'],
        'paymentRows' => $rowCounts['paymentRows'],
    ]);
    $batchID = (int) $conn->lastInsertId();

    $conn->exec('INSERT INTO purchase_archive(batchID, purchaseID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference) SELECT ' . $batchID . ', purchaseID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference FROM purchase');
    $conn->exec('INSERT INTO purchase_headers_archive(batchID, id, transactionReference, vendorName, purchaseDate, createdAt) SELECT ' . $batchID . ', id, transactionReference, vendorName, purchaseDate, createdAt FROM purchase_headers');
    $conn->exec('INSERT INTO purchase_items_archive(batchID, purchaseItemID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt) SELECT ' . $batchID . ', purchaseItemID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt FROM purchase_items');
    $conn->exec('INSERT INTO sale_archive(batchID, saleID, itemNumber, customerID, customerName, itemName, saleDate, discount, quantity, unitPrice, reason) SELECT ' . $batchID . ', saleID, itemNumber, customerID, customerName, itemName, saleDate, discount, quantity, unitPrice, reason FROM sale');
    $conn->exec('INSERT INTO sale_headers_archive(batchID, id, saleReference, customerID, customerName, saleDate, amountPaid, createdAt) SELECT ' . $batchID . ', id, saleReference, customerID, customerName, saleDate, amountPaid, createdAt FROM sale_headers');
    $conn->exec('INSERT INTO sale_items_archive(batchID, saleItemID, saleReference, itemNumber, itemName, discount, reason, quantity, unitPrice, lineTotal, createdAt) SELECT ' . $batchID . ', saleItemID, saleReference, itemNumber, itemName, discount, reason, quantity, unitPrice, lineTotal, createdAt FROM sale_items');

    if ($clearCreditData) {
        $conn->exec('INSERT INTO customer_ledger_archive(batchID, ledgerID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note) SELECT ' . $batchID . ', ledgerID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note FROM customer_ledger');
        $conn->exec('INSERT INTO customer_payments_archive(batchID, paymentID, customerID, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber, saleReference) SELECT ' . $batchID . ', paymentID, customerID, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber, saleReference FROM customer_payments');
    }

    $conn->exec('DELETE FROM purchase_items');
    $conn->exec('DELETE FROM purchase_headers');
    $conn->exec('DELETE FROM purchase');
    $conn->exec('DELETE FROM sale_items');
    $conn->exec('DELETE FROM sale_headers');
    $conn->exec('DELETE FROM sale');

    if ($clearCreditData) {
        $conn->exec('DELETE FROM customer_ledger');
        $conn->exec('DELETE FROM customer_payments');
    }

    $conn->commit();

    // Reset counters after commit because ALTER TABLE causes implicit commits in MySQL.
    $conn->exec('ALTER TABLE purchase AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE purchase_headers AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE purchase_items AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE sale AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE sale_headers AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE sale_items AUTO_INCREMENT = 1');

    if ($clearCreditData) {
        $conn->exec('ALTER TABLE customer_ledger AUTO_INCREMENT = 1');
        $conn->exec('ALTER TABLE customer_payments AUTO_INCREMENT = 1');
    }

    header('Location: ../../settings.php?archiveStatus=success&archiveBatch=' . $batchID);
    exit();
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $errorMessage = substr($e->getMessage(), 0, 400);
    header('Location: ../../settings.php?archiveStatus=error&archiveError=' . urlencode($errorMessage));
    exit();
}
