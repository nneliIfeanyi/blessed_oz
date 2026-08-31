<?php
function ensureUserRoleColumn(PDO $conn)
{
    $roleColumnCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'role'");
    if ($roleColumnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE `user` ADD COLUMN `role` varchar(20) NOT NULL DEFAULT 'admin' AFTER `password`");
    }

    $conn->exec("UPDATE `user` SET role = 'admin' WHERE role IS NULL OR role = '' OR role NOT IN ('admin', 'super_admin')");
}

function ensureSubscriptionColumns(PDO $conn)
{
    $userColumns = [];
    try {
        $userColumnStatement = $conn->query('SHOW COLUMNS FROM `user`');
        while ($row = $userColumnStatement->fetch(PDO::FETCH_ASSOC)) {
            $userColumns[] = $row['Field'];
        }
    } catch (PDOException $e) {
        $userColumns = [];
    }

    foreach (['subscription_plan', 'subscription_cycle', 'subscription_expires_at'] as $column) {
        if (!in_array($column, $userColumns, true)) {
            $conn->exec("ALTER TABLE `user` ADD COLUMN `{$column}` " . (
                $column === 'subscription_expires_at' ? 'datetime DEFAULT NULL' : 'varchar(20) DEFAULT NULL'
            ) . " AFTER `status`");
        }
    }

    $storeColumns = [];
    try {
        $storeColumnStatement = $conn->query('SHOW COLUMNS FROM `stores`');
        while ($row = $storeColumnStatement->fetch(PDO::FETCH_ASSOC)) {
            $storeColumns[] = $row['Field'];
        }
    } catch (PDOException $e) {
        $storeColumns = [];
    }

    foreach (['subscription_plan', 'subscription_cycle', 'subscription_expires_at'] as $column) {
        if (!in_array($column, $storeColumns, true)) {
            $conn->exec("ALTER TABLE `stores` ADD COLUMN `{$column}` " . (
                $column === 'subscription_expires_at' ? 'datetime DEFAULT NULL' : 'varchar(20) DEFAULT NULL'
            ) . " AFTER `status`");
        }
    }
}

function isPaidSubscriptionPlan($plan)
{
    $plan = strtolower(trim((string) $plan));
    return in_array($plan, ['pro', 'business'], true);
}

/**
 * Load subscription from DB into session. Call on login AND on each authenticated page
 * so admin/manual plan changes take effect without forcing logout.
 */
function loadUserSubscriptionSession(PDO $conn, $userID = null)
{
    $userID = (int) ($userID ?? ($_SESSION['userID'] ?? 0));
    $profile = [
        'plan' => 'free',
        'cycle' => null,
        'expires_at' => null,
        'is_pro_active' => false,
    ];

    if ($userID > 0) {
        try {
            $userColumnCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'subscription_plan'");
            if ($userColumnCheck && $userColumnCheck->rowCount() > 0) {
                $userSubscriptionStatement = $conn->prepare(
                    'SELECT subscription_plan, subscription_cycle, subscription_expires_at FROM `user` WHERE userID = :userID LIMIT 1'
                );
                $userSubscriptionStatement->execute(['userID' => $userID]);
                $userSubscription = $userSubscriptionStatement->fetch(PDO::FETCH_ASSOC);
                if (is_array($userSubscription)) {
                    $rawPlan = isset($userSubscription['subscription_plan']) ? trim((string) $userSubscription['subscription_plan']) : '';
                    $profile['plan'] = $rawPlan !== '' ? strtolower($rawPlan) : 'free';
                    $profile['cycle'] = !empty($userSubscription['subscription_cycle'])
                        ? $userSubscription['subscription_cycle']
                        : null;
                    $rawExpiry = isset($userSubscription['subscription_expires_at'])
                        ? trim((string) $userSubscription['subscription_expires_at'])
                        : '';
                    $profile['expires_at'] = ($rawExpiry !== '' && $rawExpiry !== '0000-00-00 00:00:00')
                        ? $rawExpiry
                        : null;
                }
            }
        } catch (PDOException $e) {
            // keep free defaults
        }
    }

    // Fallback: store-level plan if user is still free
    if ($profile['plan'] === 'free') {
        $activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
        try {
            $storeColumnCheck = $conn->query("SHOW COLUMNS FROM `stores` LIKE 'subscription_plan'");
            if ($storeColumnCheck && $storeColumnCheck->rowCount() > 0) {
                $storeSubscriptionStatement = $conn->prepare(
                    'SELECT subscription_plan, subscription_cycle, subscription_expires_at FROM `stores` WHERE storeID = :storeID LIMIT 1'
                );
                $storeSubscriptionStatement->execute(['storeID' => $activeStoreID]);
                $storeSubscription = $storeSubscriptionStatement->fetch(PDO::FETCH_ASSOC);
                if (is_array($storeSubscription)) {
                    $rawPlan = isset($storeSubscription['subscription_plan']) ? trim((string) $storeSubscription['subscription_plan']) : '';
                    if ($rawPlan !== '') {
                        $profile['plan'] = strtolower($rawPlan);
                        $profile['cycle'] = !empty($storeSubscription['subscription_cycle'])
                            ? $storeSubscription['subscription_cycle']
                            : null;
                        $rawExpiry = isset($storeSubscription['subscription_expires_at'])
                            ? trim((string) $storeSubscription['subscription_expires_at'])
                            : '';
                        $profile['expires_at'] = ($rawExpiry !== '' && $rawExpiry !== '0000-00-00 00:00:00')
                            ? $rawExpiry
                            : null;
                    }
                }
            }
        } catch (PDOException $e) {
            // keep current profile
        }
    }

    $isPaidPlan = isPaidSubscriptionPlan($profile['plan']);

    if ($isPaidPlan && $profile['expires_at'] !== null) {
        try {
            // Accept both "Y-m-d H:i:s" and HTML datetime-local "Y-m-d\TH:i"
            $expiresRaw = str_replace('T', ' ', (string) $profile['expires_at']);
            $expiresAt = new DateTimeImmutable($expiresRaw);
            $now = new DateTimeImmutable('now');
            $profile['is_pro_active'] = ($expiresAt > $now);
        } catch (Exception $e) {
            // Unparseable expiry: treat paid plan without valid expiry as active
            $profile['is_pro_active'] = true;
        }
    } else {
        // No expiry set → paid plan stays active until admin clears it
        $profile['is_pro_active'] = $isPaidPlan;
    }

    $_SESSION['subscription_plan'] = $profile['plan'];
    $_SESSION['subscription_cycle'] = $profile['cycle'];
    $_SESSION['subscription_expires_at'] = $profile['expires_at'];
    $_SESSION['isProActive'] = $profile['is_pro_active'] ? true : false;

    return $profile;
}

function isProActive()
{
    return !empty($_SESSION['isProActive']) && $_SESSION['isProActive'] === true;
}

/**
 * Re-read Pro status from DB when $conn is available (keeps gating in sync after admin updates).
 */
function refreshProStatusFromDb(PDO $conn = null)
{
    if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== '1') {
        return false;
    }
    if ($conn === null) {
        return isProActive();
    }
    loadUserSubscriptionSession($conn);
    return isProActive();
}

function bootstrapFirstSuperAdmin(PDO $conn)
{
    $superAdminCount = (int) $conn->query("SELECT COUNT(*) FROM `user` WHERE role = 'super_admin'")->fetchColumn();
    if ($superAdminCount > 0) {
        return;
    }

    $firstUserStatement = $conn->query("SELECT userID FROM `user` ORDER BY userID ASC LIMIT 1");
    $firstUser = $firstUserStatement->fetch(PDO::FETCH_ASSOC);
    if ($firstUser && isset($firstUser['userID'])) {
        $promoteStatement = $conn->prepare("UPDATE `user` SET role = 'super_admin' WHERE userID = :userID");
        $promoteStatement->execute(['userID' => (int) $firstUser['userID']]);
    }
}

function userCanManageUsers()
{
    return isset($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === '1' && isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

function enforceSuperAdminOrDie()
{
    if (!userCanManageUsers()) {
        http_response_code(403);
        echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>Only super admin can perform this action.</div>';
        exit();
    }
}
