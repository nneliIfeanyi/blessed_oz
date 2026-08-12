<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    header('Location: login.php');
    exit();
}

require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/store.php');
require_once('inc/header.html');

ensureActiveStoreSession($conn);
$activeStoreID = (int) $_SESSION['activeStoreID'];

$settingsFile = 'inc/config/site_settings.json';
$settings = getStoreSettings($conn, $activeStoreID, $settingsFile);

$lowStockThreshold = max(0, (int) $settings['lowStockThreshold']);
$lowStockItems = [];
$pageError = '';

try {
    $lowStockItemsSql = 'SELECT itemNumber, itemName, stock, unitAsSold, unitPrice, status FROM item WHERE stock <= :threshold AND storeID = :storeID ORDER BY stock ASC, itemName ASC';
    $lowStockItemsStatement = $conn->prepare($lowStockItemsSql);
    $lowStockItemsStatement->execute(['threshold' => $lowStockThreshold, 'storeID' => $activeStoreID]);
    $lowStockItems = $lowStockItemsStatement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pageError = 'Low stock data is unavailable right now.';
}
?>

<body>
    <?php require 'inc/navigation.php'; ?>

    <div class="container" style="margin-top:100px;">
        <div class="card card-outline-secondary my-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Low Stock Alerts</span>
                <div>
                    <a href="index.php" class="btn btn-sm btn-outline-primary">Back to Dashboard</a>
                    <a href="settings.php" class="btn btn-sm btn-outline-secondary">Edit Threshold</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($pageError != '') {
                    echo '<div class="alert alert-warning">' . htmlspecialchars($pageError) . '</div>';
                } ?>
                <p class="mb-3">Showing items with stock less than or equal to threshold: <strong><?php echo (int) $lowStockThreshold; ?></strong></p>

                <div class="table-responsive">
                    <table id="lowStockAlertsTable" class="table table-sm table-striped table-bordered table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Item Number</th>
                                <th>Item Name</th>
                                <th>Stock</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lowStockItems) > 0) {
                                foreach ($lowStockItems as $item) {
                                    echo '<tr>' .
                                        '<td>' . htmlspecialchars($item['itemNumber']) . '</td>' .
                                        '<td>' . htmlspecialchars($item['itemName']) . '</td>' .
                                        '<td>' . htmlspecialchars($item['stock']) . '</td>' .
                                        '<td>' . htmlspecialchars($item['unitAsSold'] ?? 'pcs') . '</td>' .
                                        '<td>' . htmlspecialchars(number_format((float) $item['unitPrice'], 2)) . '</td>' .
                                        '<td>' . htmlspecialchars($item['status']) . '</td>' .
                                        '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-muted">No low stock items at current threshold.</td></tr>';
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php require 'inc/footer.php'; ?>
</body>

</html>