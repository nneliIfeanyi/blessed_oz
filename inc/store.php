<?php
function ensureActiveStoreSession(PDO $conn)
{
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `stores` (
            `storeID` int(11) NOT NULL AUTO_INCREMENT,
            `storeName` varchar(120) NOT NULL,
            `storeCode` varchar(40) DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'Active',
            `createdOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`storeID`),
            UNIQUE KEY `storeCode` (`storeCode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1");

        $conn->exec("INSERT INTO `stores` (`storeID`, `storeName`, `storeCode`, `status`) VALUES (1, 'Main Store', 'MAIN', 'Active') ON DUPLICATE KEY UPDATE `storeName` = VALUES(`storeName`), `status` = VALUES(`status`)");
    } catch (PDOException $e) {
        if (!isset($_SESSION['activeStoreID']) || (int) $_SESSION['activeStoreID'] <= 0) {
            $_SESSION['activeStoreID'] = 1;
            $_SESSION['activeStoreName'] = 'Main Store';
        }
        return;
    }

    if (!isset($_SESSION['activeStoreID']) || (int) $_SESSION['activeStoreID'] <= 0) {
        $_SESSION['activeStoreID'] = 1;
    }

    $activeStoreStatement = $conn->prepare('SELECT storeID, storeName FROM stores WHERE storeID = :storeID AND status = :status LIMIT 1');
    $activeStoreStatement->execute(['storeID' => (int) $_SESSION['activeStoreID'], 'status' => 'Active']);
    if ($activeStoreStatement->rowCount() < 1) {
        $_SESSION['activeStoreID'] = 1;
        $activeStoreStatement->execute(['storeID' => 1, 'status' => 'Active']);
    }

    $activeStore = $activeStoreStatement->fetch(PDO::FETCH_ASSOC);
    $_SESSION['activeStoreName'] = $activeStore && isset($activeStore['storeName']) ? $activeStore['storeName'] : 'Main Store';
}

function getActiveStores(PDO $conn)
{
    $stores = [];
    try {
        $storesStatement = $conn->prepare('SELECT storeID, storeName FROM stores WHERE status = :status ORDER BY storeName ASC');
        $storesStatement->execute(['status' => 'Active']);
        $stores = $storesStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [['storeID' => 1, 'storeName' => 'Main Store']];
    }

    if (!is_array($stores) || count($stores) === 0) {
        $stores = [['storeID' => 1, 'storeName' => 'Main Store']];
    }

    return $stores;
}

function setActiveStoreByID(PDO $conn, $storeID)
{
    $storeID = (int) $storeID;
    if ($storeID <= 0) {
        return false;
    }

    $storeStatement = $conn->prepare('SELECT storeID, storeName FROM stores WHERE storeID = :storeID AND status = :status LIMIT 1');
    $storeStatement->execute(['storeID' => $storeID, 'status' => 'Active']);
    if ($storeStatement->rowCount() < 1) {
        return false;
    }

    $store = $storeStatement->fetch(PDO::FETCH_ASSOC);
    $_SESSION['activeStoreID'] = (int) $store['storeID'];
    $_SESSION['activeStoreName'] = $store['storeName'];

    return true;
}

function ensureStoreSettingsTable(PDO $conn)
{
    $conn->exec("CREATE TABLE IF NOT EXISTS `store_settings` (
        `storeID` int(11) NOT NULL,
        `siteName` varchar(255) NOT NULL DEFAULT 'Inventory System',
        `lowStockThreshold` int(11) NOT NULL DEFAULT '5',
        `enableProductDescription` tinyint(1) NOT NULL DEFAULT '1',
        `enableProductImage` tinyint(1) NOT NULL DEFAULT '1',
        `updatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`storeID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1");
}

function getDefaultStoreSettings()
{
    return [
        'siteName' => 'Inventory System',
        'lowStockThreshold' => 5,
        'enableProductDescription' => true,
        'enableProductImage' => true
    ];
}

function getLegacySettingsFromJson($settingsFile)
{
    $legacySettings = [];
    if (!is_string($settingsFile) || $settingsFile === '' || !file_exists($settingsFile)) {
        return $legacySettings;
    }

    $json = file_get_contents($settingsFile);
    if ($json === false) {
        return $legacySettings;
    }

    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $legacySettings = $decoded;
    }

    return $legacySettings;
}

function getStoreSettings(PDO $conn, $storeID, $legacySettingsFile = '')
{
    $storeID = (int) $storeID;
    if ($storeID <= 0) {
        $storeID = 1;
    }

    $settings = getDefaultStoreSettings();
    $legacySettings = getLegacySettingsFromJson($legacySettingsFile);
    if (!empty($legacySettings)) {
        $settings = array_merge($settings, $legacySettings);
    }

    try {
        ensureStoreSettingsTable($conn);
        $statement = $conn->prepare('SELECT siteName, lowStockThreshold, enableProductDescription, enableProductImage FROM store_settings WHERE storeID = :storeID LIMIT 1');
        $statement->execute(['storeID' => $storeID]);
        if ($statement->rowCount() > 0) {
            $dbSettings = $statement->fetch(PDO::FETCH_ASSOC);
            if ($dbSettings) {
                $settings['siteName'] = isset($dbSettings['siteName']) && trim((string) $dbSettings['siteName']) !== '' ? $dbSettings['siteName'] : $settings['siteName'];
                $settings['lowStockThreshold'] = isset($dbSettings['lowStockThreshold']) ? max(0, (int) $dbSettings['lowStockThreshold']) : (int) $settings['lowStockThreshold'];
                $settings['enableProductDescription'] = !empty($dbSettings['enableProductDescription']);
                $settings['enableProductImage'] = !empty($dbSettings['enableProductImage']);
            }
        }
    } catch (PDOException $e) {
        // Fall back to legacy JSON/default settings when DB settings table is unavailable.
    }

    return $settings;
}

function saveStoreSettings(PDO $conn, $storeID, array $settings)
{
    $storeID = (int) $storeID;
    if ($storeID <= 0) {
        $storeID = 1;
    }

    ensureStoreSettingsTable($conn);

    $siteName = isset($settings['siteName']) && trim((string) $settings['siteName']) !== '' ? trim((string) $settings['siteName']) : 'Inventory System';
    $lowStockThreshold = isset($settings['lowStockThreshold']) ? max(0, (int) $settings['lowStockThreshold']) : 5;
    $enableProductDescription = !empty($settings['enableProductDescription']) ? 1 : 0;
    $enableProductImage = !empty($settings['enableProductImage']) ? 1 : 0;

    $statement = $conn->prepare('INSERT INTO store_settings(storeID, siteName, lowStockThreshold, enableProductDescription, enableProductImage) VALUES(:storeID, :siteName, :lowStockThreshold, :enableProductDescription, :enableProductImage)
        ON DUPLICATE KEY UPDATE siteName = VALUES(siteName), lowStockThreshold = VALUES(lowStockThreshold), enableProductDescription = VALUES(enableProductDescription), enableProductImage = VALUES(enableProductImage)');
    $statement->execute([
        'storeID' => $storeID,
        'siteName' => $siteName,
        'lowStockThreshold' => $lowStockThreshold,
        'enableProductDescription' => $enableProductDescription,
        'enableProductImage' => $enableProductImage,
    ]);
}
