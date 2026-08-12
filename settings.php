<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    header('Location: login.php');
    exit();
}

require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/auth.php');
require_once('inc/store.php');

ensureUserRoleColumn($conn);
bootstrapFirstSuperAdmin($conn);
ensureActiveStoreSession($conn);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit();
}

$settingsFile = 'inc/config/site_settings.json';
$savedMessage = '';
$errorMessage = '';

try {
    ensureStoreSettingsTable($conn);
} catch (PDOException $e) {
    $errorMessage = 'Unable to initialize store settings table. Please check database permissions.';
}

$archiveStatus = isset($_GET['archiveStatus']) ? trim((string) $_GET['archiveStatus']) : '';
$archiveBatch = isset($_GET['archiveBatch']) ? trim((string) $_GET['archiveBatch']) : '';
$archiveError = isset($_GET['archiveError']) ? trim((string) $_GET['archiveError']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = isset($_POST['actionType']) ? trim((string) $_POST['actionType']) : '';

    try {
        if ($actionType === 'saveStoreSettings') {
            $storeIDToSave = isset($_POST['settingsStoreID']) ? (int) $_POST['settingsStoreID'] : 1;
            $settingsPayload = [
                'siteName' => isset($_POST['siteName']) ? trim((string) $_POST['siteName']) : 'Inventory System',
                'lowStockThreshold' => isset($_POST['lowStockThreshold']) ? max(0, (int) $_POST['lowStockThreshold']) : 5,
                'enableProductDescription' => isset($_POST['enableProductDescription']) && $_POST['enableProductDescription'] === '1',
                'enableProductImage' => isset($_POST['enableProductImage']) && $_POST['enableProductImage'] === '1'
            ];
            saveStoreSettings($conn, $storeIDToSave, $settingsPayload);
            $savedMessage = 'Store settings saved successfully.';
        } elseif ($actionType === 'createStore') {
            $newStoreName = isset($_POST['newStoreName']) ? trim((string) $_POST['newStoreName']) : '';
            $newStoreCode = isset($_POST['newStoreCode']) ? strtoupper(trim((string) $_POST['newStoreCode'])) : '';

            if ($newStoreName === '') {
                throw new Exception('Store name is required to create a branch.');
            }

            $insertStoreSql = 'INSERT INTO stores(storeName, storeCode, status) VALUES(:storeName, :storeCode, :status)';
            $insertStoreStatement = $conn->prepare($insertStoreSql);
            $insertStoreStatement->execute([
                'storeName' => $newStoreName,
                'storeCode' => $newStoreCode !== '' ? $newStoreCode : null,
                'status' => 'Active'
            ]);

            $newStoreID = (int) $conn->lastInsertId();
            saveStoreSettings($conn, $newStoreID, getStoreSettings($conn, 1, $settingsFile));
            $savedMessage = 'New branch created successfully.';
        } elseif ($actionType === 'updateStore') {
            $editStoreID = isset($_POST['editStoreID']) ? (int) $_POST['editStoreID'] : 0;
            $editStoreName = isset($_POST['editStoreName']) ? trim((string) $_POST['editStoreName']) : '';
            $editStoreCode = isset($_POST['editStoreCode']) ? strtoupper(trim((string) $_POST['editStoreCode'])) : '';
            $editStoreStatus = isset($_POST['editStoreStatus']) ? trim((string) $_POST['editStoreStatus']) : 'Active';
            $editStoreStatus = $editStoreStatus === 'Inactive' ? 'Inactive' : 'Active';

            if ($editStoreID <= 0 || $editStoreName === '') {
                throw new Exception('Invalid branch update payload.');
            }

            if ($editStoreID === 1) {
                $editStoreStatus = 'Active';
            }

            $updateStoreSql = 'UPDATE stores SET storeName = :storeName, storeCode = :storeCode, status = :status WHERE storeID = :storeID';
            $updateStoreStatement = $conn->prepare($updateStoreSql);
            $updateStoreStatement->execute([
                'storeName' => $editStoreName,
                'storeCode' => $editStoreCode !== '' ? $editStoreCode : null,
                'status' => $editStoreStatus,
                'storeID' => $editStoreID
            ]);

            if ($editStoreID === (int) $_SESSION['activeStoreID']) {
                ensureActiveStoreSession($conn);
            }

            $savedMessage = 'Branch updated successfully.';
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    } catch (PDOException $e) {
        $errorMessage = 'Unable to save changes. Please check if store code is unique and try again.';
    }
}

$stores = [];
$storesStatement = $conn->prepare('SELECT storeID, storeName, storeCode, status FROM stores ORDER BY storeID ASC');
$storesStatement->execute();
while ($storeRow = $storesStatement->fetch(PDO::FETCH_ASSOC)) {
    $stores[] = $storeRow;
}
if (count($stores) === 0) {
    $stores[] = ['storeID' => 1, 'storeName' => 'Main Store', 'storeCode' => 'MAIN', 'status' => 'Active'];
}

$selectedStoreID = isset($_GET['storeID']) ? (int) $_GET['storeID'] : (int) $_SESSION['activeStoreID'];
if ($selectedStoreID <= 0) {
    $selectedStoreID = (int) $_SESSION['activeStoreID'];
}

$selectedStore = null;
foreach ($stores as $store) {
    if ((int) $store['storeID'] === $selectedStoreID) {
        $selectedStore = $store;
        break;
    }
}
if (!$selectedStore) {
    $selectedStore = $stores[0];
    $selectedStoreID = (int) $selectedStore['storeID'];
}

$settings = getStoreSettings($conn, $selectedStoreID, $settingsFile);

require_once('inc/header.html');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Settings</title>
    <link rel="stylesheet" href="assets/css/shop-styles.css">
</head>

<body>
    <?php require 'inc/navigation.php'; ?>
    <div class="container" style="margin-top:100px; margin-bottom: 40px;">
        <div class="card mb-4">
            <div class="card-header">Store Settings</div>
            <div class="card-body">
                <?php if ($savedMessage !== '') {
                    echo '<div class="alert alert-success">' . htmlspecialchars($savedMessage) . '</div>';
                } ?>
                <?php if ($errorMessage !== '') {
                    echo '<div class="alert alert-danger">' . htmlspecialchars($errorMessage) . '</div>';
                } ?>
                <?php
                if ($archiveStatus === 'success') {
                    echo '<div class="alert alert-success">Archive completed successfully for the active store. Batch ID: ' . htmlspecialchars($archiveBatch) . '.</div>';
                } elseif ($archiveStatus === 'empty') {
                    echo '<div class="alert alert-info">No sales or purchase records were found to archive for the active store.</div>';
                } elseif ($archiveStatus === 'error') {
                    $errorText = $archiveError !== '' ? ' Details: ' . htmlspecialchars($archiveError) : '';
                    echo '<div class="alert alert-danger">Archive failed. Please try again and check your database permissions.' . $errorText . '</div>';
                }
                ?>

                <form method="get" action="settings.php" class="form-inline mb-3">
                    <label for="storeID" class="mr-2">Manage store settings for:</label>
                    <select id="storeID" name="storeID" class="form-control mr-2" onchange="this.form.submit()">
                        <?php foreach ($stores as $store) {
                            $storeID = (int) $store['storeID'];
                            $selected = $storeID === $selectedStoreID ? 'selected' : '';
                            $statusBadge = $store['status'] === 'Inactive' ? ' (Inactive)' : '';
                            echo '<option value="' . $storeID . '" ' . $selected . '>' . htmlspecialchars($store['storeName'] . $statusBadge) . '</option>';
                        } ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-secondary">Load</button></noscript>
                </form>

                <form method="post" action="settings.php?storeID=<?php echo (int) $selectedStoreID; ?>">
                    <input type="hidden" name="actionType" value="saveStoreSettings">
                    <input type="hidden" name="settingsStoreID" value="<?php echo (int) $selectedStoreID; ?>">
                    <div class="form-group">
                        <label for="siteName">Site Name (selected store)</label>
                        <input type="text" class="form-control" id="siteName" name="siteName" value="<?php echo htmlspecialchars($settings['siteName']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lowStockThreshold">Low Stock Threshold</label>
                        <input type="number" class="form-control" id="lowStockThreshold" name="lowStockThreshold" min="0" value="<?php echo (int) $settings['lowStockThreshold']; ?>">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="enableProductDescription" name="enableProductDescription" value="1" <?php echo !empty($settings['enableProductDescription']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enableProductDescription">Enable product description field</label>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="enableProductImage" name="enableProductImage" value="1" <?php echo !empty($settings['enableProductImage']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enableProductImage">Enable product image upload</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Store Settings</button>
                    <a href="login.php?action=register" class="btn btn-success">Add User</a>
                    <a href="login.php?action=resetPassword" class="btn btn-warning">Reset Password</a>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Branch Management</div>
            <div class="card-body">
                <h6>Create New Branch</h6>
                <form method="post" action="settings.php?storeID=<?php echo (int) $selectedStoreID; ?>" class="mb-4">
                    <input type="hidden" name="actionType" value="createStore">
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label for="newStoreName">Branch Name</label>
                            <input type="text" class="form-control" id="newStoreName" name="newStoreName" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="newStoreCode">Branch Code</label>
                            <input type="text" class="form-control" id="newStoreCode" name="newStoreCode" maxlength="40" placeholder="Optional">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Create Branch</button>
                </form>

                <h6>Existing Branches</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Store ID</th>
                                <th>Store Name</th>
                                <th>Store Code</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stores as $store) {
                                $storeID = (int) $store['storeID'];
                                $isMainStore = $storeID === 1;
                                echo '<tr><form method="post" action="settings.php?storeID=' . (int) $selectedStoreID . '">';
                                echo '<td>' . $storeID . '<input type="hidden" name="actionType" value="updateStore"><input type="hidden" name="editStoreID" value="' . $storeID . '"></td>';
                                echo '<td><input type="text" class="form-control form-control-sm" name="editStoreName" value="' . htmlspecialchars($store['storeName']) . '" required></td>';
                                echo '<td><input type="text" class="form-control form-control-sm" name="editStoreCode" value="' . htmlspecialchars((string) $store['storeCode']) . '"></td>';
                                echo '<td><select name="editStoreStatus" class="form-control form-control-sm" ' . ($isMainStore ? 'disabled' : '') . '>';
                                $activeSelected = $store['status'] === 'Active' ? 'selected' : '';
                                $inactiveSelected = $store['status'] === 'Inactive' ? 'selected' : '';
                                echo '<option value="Active" ' . $activeSelected . '>Active</option>';
                                echo '<option value="Inactive" ' . $inactiveSelected . '>Inactive</option>';
                                echo '</select>';
                                if ($isMainStore) {
                                    echo '<input type="hidden" name="editStoreStatus" value="Active">';
                                }
                                echo '</td>';
                                echo '<td><button type="submit" class="btn btn-sm btn-outline-success">Update</button></td>';
                                echo '</form></tr>';
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Archive and Reset Transactions</div>
            <div class="card-body">
                <p class="text-muted mb-2">This archives and clears sales and purchase records for your currently active store only (<?php echo htmlspecialchars(isset($_SESSION['activeStoreName']) ? $_SESSION['activeStoreName'] : 'Main Store'); ?>).</p>
                <form method="post" action="model/admin/archiveTransactions.php" onsubmit="return confirm('This will archive and clear sales and purchase records for the active store. Continue?');">
                    <div class="form-group">
                        <label for="archiveNote">Archive Note (optional)</label>
                        <input type="text" class="form-control" id="archiveNote" name="archiveNote" placeholder="Example: Period ended 2026-08-09">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="clearCreditData" name="clearCreditData" value="1">
                        <label class="form-check-label" for="clearCreditData">Also archive and clear customer credit ledger/payments for this store</label>
                    </div>
                    <button type="submit" class="btn btn-danger">Archive and Clear Active Store Transactions</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>