<?php
session_start();
require_once('inc/config/constants.php');
require_once('inc/config/db.php');
require_once('inc/auth.php');
require_once('inc/store.php');

// Redirect if not logged in
if (!isset($_SESSION['loggedIn'])) {
    header('Location: login.php');
    exit();
}

try {
    ensureActiveStoreSession($conn);
} catch (Exception $e) {
    // Continue with session defaults if store bootstrap fails
}

$activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
$userID = isset($_SESSION['userID']) ? (int) $_SESSION['userID'] : 0;

// Re-read plan from DB so admin/manual Pro grants apply without re-login
if (function_exists('loadUserSubscriptionSession')) {
    try {
        loadUserSubscriptionSession($conn, $userID);
    } catch (Exception $e) {
        // keep session defaults
    }
}
$isProActive = function_exists('isProActive') ? isProActive() : false;

// Ensure subscription columns exist (shared hosts often lack them until first login path runs)
if (function_exists('ensureSubscriptionColumns')) {
    try {
        ensureSubscriptionColumns($conn);
    } catch (Exception $e) {
        // non-fatal
    }
}

// Create sync_log if missing — never DROP on shared hosting
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS sync_log (
        syncID INT(11) NOT NULL AUTO_INCREMENT,
        storeID INT(11) NOT NULL DEFAULT '1',
        userID INT(11) NOT NULL,
        clientReferenceId VARCHAR(255) NOT NULL,
        transactionType VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        responseReference VARCHAR(255) DEFAULT NULL,
        createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (syncID),
        UNIQUE KEY clientReferenceId (clientReferenceId),
        KEY status (status),
        KEY storeID (storeID)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Table may already exist with a compatible schema — page must still render
    error_log('sync_dashboard sync_log ensure: ' . $e->getMessage());
}

// Get sync statistics (safe defaults if table/query fails)
$syncStats = [];
try {
    $syncStatsStmt = $conn->prepare(
        'SELECT status, COUNT(*) AS count, transactionType
         FROM sync_log
         WHERE storeID = :storeID
         GROUP BY status, transactionType'
    );
    $syncStatsStmt->execute(['storeID' => $activeStoreID]);
    $syncStats = $syncStatsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $syncStats = [];
}

// Get recent sync activities
$recentSync = [];
try {
    $recentSyncStmt = $conn->prepare(
        'SELECT syncID, clientReferenceId, transactionType, status, responseReference, createdAt
         FROM sync_log
         WHERE storeID = :storeID
         ORDER BY createdAt DESC
         LIMIT 50'
    );
    $recentSyncStmt->execute(['storeID' => $activeStoreID]);
    $recentSync = $recentSyncStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentSync = [];
}

// Calculate totals
$totalPending = 0;
$totalSynced = 0;
$totalFailed = 0;
$pendingByType = ['sale' => 0, 'purchase' => 0];
$syncedByType = ['sale' => 0, 'purchase' => 0];
$failedByType = ['sale' => 0, 'purchase' => 0];

foreach ($syncStats as $stat) {
    $type = isset($stat['transactionType']) ? (string) $stat['transactionType'] : '';
    $count = isset($stat['count']) ? (int) $stat['count'] : 0;
    $status = isset($stat['status']) ? (string) $stat['status'] : '';

    if ($status === 'pending') {
        $totalPending += $count;
        if (isset($pendingByType[$type])) {
            $pendingByType[$type] = $count;
        }
    } elseif ($status === 'synced') {
        $totalSynced += $count;
        if (isset($syncedByType[$type])) {
            $syncedByType[$type] = $count;
        }
    } elseif ($status === 'failed') {
        $totalFailed += $count;
        if (isset($failedByType[$type])) {
            $failedByType[$type] = $count;
        }
    }
}

// Subscription display — never fatal if columns/rows missing
$userSub = [
    'subscription_plan' => isset($_SESSION['subscription_plan']) ? $_SESSION['subscription_plan'] : 'free',
    'subscription_expires_at' => isset($_SESSION['subscription_expires_at']) ? $_SESSION['subscription_expires_at'] : null,
];
try {
    $colCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'subscription_plan'");
    if ($colCheck && $colCheck->rowCount() > 0 && $userID > 0) {
        $userSubStmt = $conn->prepare(
            'SELECT subscription_plan, subscription_expires_at FROM `user` WHERE userID = :userID LIMIT 1'
        );
        $userSubStmt->execute(['userID' => $userID]);
        $row = $userSubStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $userSub = $row;
        }
    }
} catch (PDOException $e) {
    // keep session defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sync Status Dashboard</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/shop-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .stat-card {
            border-left: 4px solid #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 4px;
            background: white;
        }
        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.synced { border-left-color: #28a745; }
        .stat-card.failed { border-left-color: #dc3545; }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-badge-pending { background-color: #ffc107; color: black; }
        .status-badge-synced { background-color: #28a745; }
        .status-badge-failed { background-color: #dc3545; }
        .sync-table {
            font-size: 0.9em;
        }
        .pro-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .free-badge {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85em;
        }
        .auto-sync-status {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .sync-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .sync-indicator.online { background-color: #28a745; }
        .sync-indicator.offline { background-color: #dc3545; }
        .sync-indicator.syncing { background-color: #ffc107; animation: pulse 1s infinite; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <?php require_once('inc/navigation.php'); ?>

    <div class="container-fluid my-5">
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>Sync Status Dashboard</h1>
                    <p class="mb-0">Monitor offline queue and sync activity</p>
                </div>
                <div class="col-md-4 text-right">
                    <?php if ($isProActive) { ?>
                        <span class="pro-badge">
                            ✓ Pro Active
                            <?php
                            $expiresAt = isset($userSub['subscription_expires_at']) ? $userSub['subscription_expires_at'] : null;
                            if (!empty($expiresAt)) {
                                echo '<br><small>Expires: ' . htmlspecialchars(date('M d, Y', strtotime($expiresAt))) . '</small>';
                            }
                            ?>
                        </span>
                    <?php } else { ?>
                        <span class="free-badge">
                            Free Plan
                            <br><small><a href="upgrade.php" style="color: white; text-decoration: underline;">Upgrade for offline sync</a></small>
                        </span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php if (!$isProActive) { ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <strong>Offline sync disabled!</strong> You're currently on a Free plan. 
                <a href="upgrade.php" class="alert-link">Upgrade to Pro</a> to enable offline transactions and automatic sync.
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php } ?>

        <!-- Auto-Sync Status -->
        <div class="auto-sync-status">
            <h6>
                <span class="sync-indicator online" id="onlineIndicator"></span>
                Connection Status: <strong id="connectionStatus">Checking...</strong>
            </h6>
            <small class="text-muted">
                Last sync attempt: <span id="lastSyncTime">Never</span>
            </small>
            <button class="btn btn-sm btn-primary float-right" id="manualSyncBtn" onclick="manualSync()" <?php echo !$isProActive ? 'disabled' : ''; ?>>
                Manual Sync
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card pending">
                    <div class="stat-label">Pending Sync</div>
                    <div class="stat-number" id="pendingCount"><?php echo $totalPending; ?></div>
                    <small class="text-muted">
                        Sales: <?php echo $pendingByType['sale']; ?> | 
                        Purchases: <?php echo $pendingByType['purchase']; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card synced">
                    <div class="stat-label">Successfully Synced</div>
                    <div class="stat-number" id="syncedCount"><?php echo $totalSynced; ?></div>
                    <small class="text-muted">
                        Sales: <?php echo $syncedByType['sale']; ?> | 
                        Purchases: <?php echo $syncedByType['purchase']; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card failed">
                    <div class="stat-label">Failed Sync</div>
                    <div class="stat-number" id="failedCount"><?php echo $totalFailed; ?></div>
                    <small class="text-muted">
                        Sales: <?php echo $failedByType['sale']; ?> | 
                        Purchases: <?php echo $failedByType['purchase']; ?>
                    </small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card" style="border-left-color: #6c757d;">
                    <div class="stat-label">Total Transactions</div>
                    <div class="stat-number"><?php echo $totalPending + $totalSynced + $totalFailed; ?></div>
                    <small class="text-muted">
                        All time activity
                    </small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sync Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Sync Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="syncChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Transaction Type Chart -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">By Transaction Type</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sync Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Sync Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm sync-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                        <th>Response</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody id="syncTableBody">
                                    <?php if (empty($recentSync)) { ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                No sync activity yet. <?php echo $isProActive ? 'Go offline and make a transaction to see it here.' : 'Upgrade to Pro to enable offline sync.'; ?>
                                            </td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($recentSync as $sync) { ?>
                                            <tr>
                                                <td>
                                                    <code style="font-size: 0.75em;"><?php echo substr($sync['clientReferenceId'], 0, 10); ?>...</code>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst($sync['transactionType']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge-<?php echo $sync['status']; ?>">
                                                        <?php echo ucfirst($sync['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <code style="font-size: 0.75em;">
                                                        <?php echo $sync['responseReference'] ? substr($sync['responseReference'], 0, 15) . (strlen($sync['responseReference']) > 15 ? '...' : '') : '-'; ?>
                                                    </code>
                                                </td>
                                                <td>
                                                    <?php if ($sync['status'] === 'failed' && $sync['responseReference']) { ?>
                                                        <button class="btn btn-xs btn-outline-danger" title="<?php echo htmlentities($sync['responseReference'], ENT_QUOTES, 'UTF-8'); ?>">Error</button>
                                                    <?php } else { ?>
                                                        <span class="text-muted">-</span>
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('M d, H:i', strtotime($sync['createdAt'])); ?></small>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offline Queue Details (for Pro users) -->
        <?php if ($isProActive) { ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Offline Queue (Local Storage)</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Your browser's offline queue is shown below. This data is stored locally and synced when online.</p>
                        <div id="offlineCatalogStatus" class="alert alert-secondary mb-3">
                            Offline catalog status loading…
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="refreshOfflineCatalogBtn">Refresh offline catalog</button>
                        <div id="offlineQueueDisplay" class="alert alert-info">
                            Loading queue status... (requires JavaScript enabled)
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize charts
        const syncCtx = document.getElementById('syncChart').getContext('2d');
        const typeCtx = document.getElementById('typeChart').getContext('2d');

        new Chart(syncCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Synced', 'Failed'],
                datasets: [{
                    data: [<?php echo $totalPending; ?>, <?php echo $totalSynced; ?>, <?php echo $totalFailed; ?>],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderColor: ['#fff', '#fff', '#fff'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: ['Sales', 'Purchases'],
                datasets: [
                    {
                        label: 'Pending',
                        data: [<?php echo $pendingByType['sale']; ?>, <?php echo $pendingByType['purchase']; ?>],
                        backgroundColor: '#ffc107'
                    },
                    {
                        label: 'Synced',
                        data: [<?php echo $syncedByType['sale']; ?>, <?php echo $syncedByType['purchase']; ?>],
                        backgroundColor: '#28a745'
                    },
                    {
                        label: 'Failed',
                        data: [<?php echo $failedByType['sale']; ?>, <?php echo $failedByType['purchase']; ?>],
                        backgroundColor: '#dc3545'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Connection status + last sync attempt (from localStorage via db-sync.js)
        function updateConnectionStatus() {
            const status = navigator.onLine ? 'Online' : 'Offline';
            const indicator = document.getElementById('onlineIndicator');
            if (indicator) {
                indicator.className = 'sync-indicator ' + (navigator.onLine ? 'online' : 'offline');
            }
            const statusEl = document.getElementById('connectionStatus');
            if (statusEl) {
                statusEl.textContent = status;
            }
            if (window.inventorySync && typeof window.inventorySync.setSyncStatusUI === 'function') {
                window.inventorySync.setSyncStatusUI();
            } else {
                const lastSyncEl = document.getElementById('lastSyncTime');
                if (lastSyncEl) {
                    const raw = localStorage.getItem('inventory_sync_last_attempt');
                    if (!raw) {
                        lastSyncEl.textContent = 'Never';
                    } else {
                        try {
                            lastSyncEl.textContent = new Date(raw).toLocaleString();
                        } catch (e) {
                            lastSyncEl.textContent = 'Never';
                        }
                    }
                }
            }
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        // Run after db-sync.js has loaded (footer scripts)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateConnectionStatus);
        } else {
            updateConnectionStatus();
        }
        // Secondary pass once inventorySync is definitely available
        setTimeout(updateConnectionStatus, 100);

        // Offline queue display
        function displayOfflineQueue() {
            if (window.inventorySync && typeof window.inventorySync.getOutbox === 'function') {
                const outbox = window.inventorySync.getOutbox();
                const pending = outbox.filter(function (item) { return !item.synced; });
                const display = document.getElementById('offlineQueueDisplay');
                if (!display) {
                    return;
                }

                if (pending.length === 0) {
                    display.innerHTML = '<strong>✓ Queue is empty</strong> - All transactions synced or none pending.';
                    display.className = 'alert alert-success';
                } else {
                    let html = '<strong>' + pending.length + ' transaction(s) pending:</strong><br><br>';
                    html += '<table class="table table-sm"><thead><tr><th>Type</th><th>Items</th><th>Created</th><th>Status</th></tr></thead><tbody>';

                    pending.forEach(function (item) {
                        const createdTime = new Date(item.createdAt).toLocaleString();
                        const itemCount = item.payload && item.payload.items ? item.payload.items.length : 0;
                        html += '<tr><td><span class="badge badge-info">' + item.type + '</span></td>';
                        html += '<td>' + itemCount + '</td>';
                        html += '<td><small>' + createdTime + '</small></td>';
                        html += '<td><span class="badge badge-warning">Pending</span></td></tr>';
                    });

                    html += '</tbody></table>';
                    display.innerHTML = html;
                    display.className = 'alert alert-warning';
                }
            }
        }

        displayOfflineQueue();
        setInterval(displayOfflineQueue, 2000);
        setInterval(updateConnectionStatus, 3000);

        function updateOfflineCatalogStatus() {
            var el = document.getElementById('offlineCatalogStatus');
            if (!el) {
                return;
            }
            if (window.offlineCatalog && typeof window.offlineCatalog.statusText === 'function') {
                el.textContent = window.offlineCatalog.statusText();
                el.className = window.offlineCatalog.hasCatalog()
                    ? 'alert alert-success mb-3'
                    : 'alert alert-warning mb-3';
            } else {
                el.textContent = 'Offline catalog module not loaded.';
            }
        }
        updateOfflineCatalogStatus();
        setInterval(updateOfflineCatalogStatus, 3000);
        var refreshCatBtn = document.getElementById('refreshOfflineCatalogBtn');
        if (refreshCatBtn) {
            refreshCatBtn.addEventListener('click', function () {
                if (!window.offlineCatalog || typeof window.offlineCatalog.refreshFromServer !== 'function') {
                    alert('Offline catalog module not loaded.');
                    return;
                }
                if (!navigator.onLine) {
                    alert('Connect to the internet to refresh the offline catalog.');
                    return;
                }
                refreshCatBtn.disabled = true;
                refreshCatBtn.textContent = 'Refreshing…';
                window.offlineCatalog.refreshFromServer().then(function (data) {
                    updateOfflineCatalogStatus();
                    alert(data && data.success
                        ? 'Offline catalog updated (' + (data.counts && data.counts.items ? data.counts.items : 0) + ' items).'
                        : 'Catalog refresh finished.');
                }).catch(function (err) {
                    alert('Catalog refresh failed: ' + (err.message || err));
                }).then(function () {
                    refreshCatBtn.disabled = false;
                    refreshCatBtn.textContent = 'Refresh offline catalog';
                });
            });
        }

        // Manual sync — clear feedback when queue empty (e.g. auto-sync already ran)
        function manualSync() {
            if (window.inventorySync && typeof window.inventorySync.syncPendingTransactions === 'function') {
                const btn = document.getElementById('manualSyncBtn');
                btn.disabled = true;
                btn.textContent = 'Syncing...';

                window.inventorySync.syncPendingTransactions().then(function (result) {
                    var message = 'No queued transactions. Everything is up to date.';
                    if (result && typeof result === 'object' && result.message) {
                        message = result.message;
                    } else if (result === false) {
                        message = 'Sync encountered an issue. Check console for details.';
                    } else if (result === true) {
                        message = 'Sync completed successfully. Everything is up to date.';
                    }
                    alert(message);
                    btn.disabled = false;
                    btn.textContent = 'Manual Sync';
                    displayOfflineQueue();
                    updateConnectionStatus();
                }).catch(function (err) {
                    alert('Sync encountered an issue. Check console for details.');
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = 'Manual Sync';
                    updateConnectionStatus();
                });
            } else {
                alert('Sync module is not loaded.');
            }
        }
        // Expose for inline onclick
        window.manualSync = manualSync;
    </script>

    <?php require_once('inc/footer.php'); ?>
</body>
</html>
