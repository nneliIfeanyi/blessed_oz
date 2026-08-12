<?php
session_start();
if (!isset($_SESSION['loggedIn'])) {
    header('Location: login.php');
    exit();
}

require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/auth.php');

ensureUserRoleColumn($conn);
bootstrapFirstSuperAdmin($conn);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit();
}

require_once('inc/header.html');

$settingsFile = 'inc/config/site_settings.json';
$settings = [
    'siteName' => 'Inventory System',
    'lowStockThreshold' => 5,
    'enableProductDescription' => true,
    'enableProductImage' => true
];
if (file_exists($settingsFile)) {
    $json = file_get_contents($settingsFile);
    if ($json !== false) {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $settings = array_merge($settings, $decoded);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings['siteName'] = isset($_POST['siteName']) ? trim($_POST['siteName']) : $settings['siteName'];
    $settings['lowStockThreshold'] = isset($_POST['lowStockThreshold']) ? max(0, (int) $_POST['lowStockThreshold']) : $settings['lowStockThreshold'];
    $settings['enableProductDescription'] = isset($_POST['enableProductDescription']) ? (bool) $_POST['enableProductDescription'] : false;
    $settings['enableProductImage'] = isset($_POST['enableProductImage']) ? (bool) $_POST['enableProductImage'] : false;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    $savedMessage = 'Settings saved.';
}

$archiveStatus = isset($_GET['archiveStatus']) ? trim((string) $_GET['archiveStatus']) : '';
$archiveBatch = isset($_GET['archiveBatch']) ? trim((string) $_GET['archiveBatch']) : '';
$archiveError = isset($_GET['archiveError']) ? trim((string) $_GET['archiveError']) : '';
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
    <div class="container" style="margin-top:100px;">
        <div class="card">
            <div class="card-header">Site Settings</div>
            <div class="card-body">
                <?php if (!empty($savedMessage)) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($savedMessage) . '</div>';
                } ?>
                <?php
                if ($archiveStatus === 'success') {
                    echo '<div class="alert alert-success">Archive completed successfully. Batch ID: ' . htmlspecialchars($archiveBatch) . '.</div>';
                } elseif ($archiveStatus === 'empty') {
                    echo '<div class="alert alert-info">No sales or purchase records were found to archive.</div>';
                } elseif ($archiveStatus === 'error') {
                    $errorText = $archiveError !== '' ? ' Details: ' . htmlspecialchars($archiveError) : '';
                    echo '<div class="alert alert-danger">Archive failed. Please try again and check your database permissions.' . $errorText . '</div>';
                }
                ?>
                <form method="post" action="settings.php">
                    <div class="form-group">
                        <label for="siteName">Site Name</label>
                        <input type="text" class="form-control" id="siteName" name="siteName" value="<?php echo htmlspecialchars($settings['siteName']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lowStockThreshold">Low Stock Threshold</label>
                        <input type="number" class="form-control" id="lowStockThreshold" name="lowStockThreshold" value="<?php echo (int) $settings['lowStockThreshold']; ?>">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="enableProductDescription" name="enableProductDescription" value="1" <?php echo !empty($settings['enableProductDescription']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enableProductDescription">Enable product description field</label>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="enableProductImage" name="enableProductImage" value="1" <?php echo !empty($settings['enableProductImage']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enableProductImage">Enable product image upload</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <a href="login.php?action=register" class="btn btn-success">Add User</a>
                    <a href="login.php?action=resetPassword" class="btn btn-warning">Reset Password</a>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </form>

                <hr>
                <h5>Archive and Reset Transactions</h5>
                <p class="text-muted mb-2">Use this to archive and clear current sales and purchase records for a fresh cycle. This action is restricted to super admin.</p>
                <form method="post" action="model/admin/archiveTransactions.php" onsubmit="return confirm('This will archive and clear sales and purchase records. Continue?');">
                    <div class="form-group">
                        <label for="archiveNote">Archive Note (optional)</label>
                        <input type="text" class="form-control" id="archiveNote" name="archiveNote" placeholder="Example: Period ended 2026-08-09">
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="clearCreditData" name="clearCreditData" value="1">
                        <label class="form-check-label" for="clearCreditData">Also archive and clear customer credit ledger/payments</label>
                    </div>
                    <button type="submit" class="btn btn-danger">Archive and Clear Sales/Purchase</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>