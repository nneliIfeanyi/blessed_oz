<?php
session_start();

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');

ensureUserRoleColumn($conn);
bootstrapFirstSuperAdmin($conn);
ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];
$activeStoreName = isset($_SESSION['activeStoreName']) ? (string) $_SESSION['activeStoreName'] : 'Main Store';

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
        `storeID` int(11) NOT NULL DEFAULT '1',
        `storeName` varchar(120) DEFAULT NULL,
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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
        `storeID` int(11) NOT NULL DEFAULT '1',
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

    $archiveColumnMigrations = [
        ['archive_batches', 'storeID', "ALTER TABLE `archive_batches` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['archive_batches', 'storeName', "ALTER TABLE `archive_batches` ADD COLUMN `storeName` varchar(120) DEFAULT NULL AFTER `storeID`"],
        ['purchase_archive', 'storeID', "ALTER TABLE `purchase_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['purchase_headers_archive', 'storeID', "ALTER TABLE `purchase_headers_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['purchase_items_archive', 'storeID', "ALTER TABLE `purchase_items_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['sale_archive', 'storeID', "ALTER TABLE `sale_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['sale_headers_archive', 'storeID', "ALTER TABLE `sale_headers_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['sale_items_archive', 'storeID', "ALTER TABLE `sale_items_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['customer_ledger_archive', 'storeID', "ALTER TABLE `customer_ledger_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
        ['customer_payments_archive', 'storeID', "ALTER TABLE `customer_payments_archive` ADD COLUMN `storeID` int(11) NOT NULL DEFAULT '1' AFTER `batchID`"],
    ];
    foreach ($archiveColumnMigrations as $migration) {
        $columnCheckStatement = $conn->query("SHOW COLUMNS FROM `{$migration[0]}` LIKE '{$migration[1]}'");
        if ($columnCheckStatement->rowCount() === 0) {
            $conn->exec($migration[2]);
        }
    }

    $countSql = [
        'purchaseRows' => 'SELECT COUNT(*) FROM purchase WHERE storeID = :storeID',
        'purchaseHeaderRows' => 'SELECT COUNT(*) FROM purchase_headers WHERE storeID = :storeID',
        'purchaseItemRows' => 'SELECT COUNT(*) FROM purchase_items WHERE storeID = :storeID',
        'saleRows' => 'SELECT COUNT(*) FROM sale WHERE storeID = :storeID',
        'saleHeaderRows' => 'SELECT COUNT(*) FROM sale_headers WHERE storeID = :storeID',
        'saleItemRows' => 'SELECT COUNT(*) FROM sale_items WHERE storeID = :storeID',
        'ledgerRows' => 'SELECT COUNT(*) FROM customer_ledger WHERE storeID = :storeID',
        'paymentRows' => 'SELECT COUNT(*) FROM customer_payments WHERE storeID = :storeID',
    ];
    $rowCounts = [];
    foreach ($countSql as $key => $sql) {
        if (!$clearCreditData && ($key === 'ledgerRows' || $key === 'paymentRows')) {
            $rowCounts[$key] = 0;
            continue;
        }
        $countStatement = $conn->prepare($sql);
        $countStatement->execute(['storeID' => $activeStoreID]);
        $rowCounts[$key] = (int) $countStatement->fetchColumn();
    }

    $totalRows = array_sum($rowCounts);
    if ($totalRows === 0) {
        header('Location: ../../settings.php?archiveStatus=empty');
        exit();
    }

    $conn->beginTransaction();

    $insertBatchSql = 'INSERT INTO archive_batches(storeID, storeName, archivedByUserID, archivedByUsername, note, purchaseRows, purchaseHeaderRows, purchaseItemRows, saleRows, saleHeaderRows, saleItemRows, ledgerRows, paymentRows)
        VALUES(:storeID, :storeName, :archivedByUserID, :archivedByUsername, :note, :purchaseRows, :purchaseHeaderRows, :purchaseItemRows, :saleRows, :saleHeaderRows, :saleItemRows, :ledgerRows, :paymentRows)';
    $insertBatchStatement = $conn->prepare($insertBatchSql);
    $insertBatchStatement->execute([
        'storeID' => $activeStoreID,
        'storeName' => $activeStoreName,
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

    $conn->exec('INSERT INTO purchase_archive(batchID, storeID, purchaseID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference) SELECT ' . $batchID . ', storeID, purchaseID, itemNumber, purchaseDate, itemName, unitPrice, quantity, vendorName, vendorID, transactionReference FROM purchase WHERE storeID = ' . $activeStoreID);
    $conn->exec('INSERT INTO purchase_headers_archive(batchID, storeID, id, transactionReference, vendorName, purchaseDate, createdAt) SELECT ' . $batchID . ', storeID, id, transactionReference, vendorName, purchaseDate, createdAt FROM purchase_headers WHERE storeID = ' . $activeStoreID);
    $conn->exec('INSERT INTO purchase_items_archive(batchID, storeID, purchaseItemID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt) SELECT ' . $batchID . ', storeID, purchaseItemID, transactionReference, itemNumber, itemName, quantity, unitPrice, lineTotal, createdAt FROM purchase_items WHERE storeID = ' . $activeStoreID);
    $conn->exec('INSERT INTO sale_archive(batchID, storeID, saleID, itemNumber, customerID, customerName, itemName, saleDate, discount, quantity, unitPrice, reason) SELECT ' . $batchID . ', storeID, saleID, itemNumber, customerID, customerName, itemName, saleDate, discount, quantity, unitPrice, reason FROM sale WHERE storeID = ' . $activeStoreID);
    $conn->exec('INSERT INTO sale_headers_archive(batchID, storeID, id, saleReference, customerID, customerName, saleDate, amountPaid, createdAt) SELECT ' . $batchID . ', storeID, id, saleReference, customerID, customerName, saleDate, amountPaid, createdAt FROM sale_headers WHERE storeID = ' . $activeStoreID);
    $conn->exec('INSERT INTO sale_items_archive(batchID, storeID, saleItemID, saleReference, itemNumber, itemName, discount, reason, quantity, unitPrice, lineTotal, createdAt) SELECT ' . $batchID . ', storeID, saleItemID, saleReference, itemNumber, itemName, discount, reason, quantity, unitPrice, lineTotal, createdAt FROM sale_items WHERE storeID = ' . $activeStoreID);

    if ($clearCreditData) {
        $conn->exec('INSERT INTO customer_ledger_archive(batchID, storeID, ledgerID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note) SELECT ' . $batchID . ', storeID, ledgerID, customerID, saleID, entryType, amount, balanceAfter, entryDate, note FROM customer_ledger WHERE storeID = ' . $activeStoreID);
        $conn->exec('INSERT INTO customer_payments_archive(batchID, storeID, paymentID, customerID, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber, saleReference) SELECT ' . $batchID . ', storeID, paymentID, customerID, saleID, amount, paymentDate, paymentMethod, referenceNumber, note, receiptNumber, saleReference FROM customer_payments WHERE storeID = ' . $activeStoreID);
    }

    $conn->exec('DELETE FROM purchase_items WHERE storeID = ' . $activeStoreID);
    $conn->exec('DELETE FROM purchase_headers WHERE storeID = ' . $activeStoreID);
    $conn->exec('DELETE FROM purchase WHERE storeID = ' . $activeStoreID);
    $conn->exec('DELETE FROM sale_items WHERE storeID = ' . $activeStoreID);
    $conn->exec('DELETE FROM sale_headers WHERE storeID = ' . $activeStoreID);
    $conn->exec('DELETE FROM sale WHERE storeID = ' . $activeStoreID);

    if ($clearCreditData) {
        $conn->exec('DELETE FROM customer_ledger WHERE storeID = ' . $activeStoreID);
        $conn->exec('DELETE FROM customer_payments WHERE storeID = ' . $activeStoreID);
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
