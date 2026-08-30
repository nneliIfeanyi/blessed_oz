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
        $userColumnCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'subscription_plan'");
        if ($userColumnCheck && $userColumnCheck->rowCount() > 0) {
            $userSubscriptionStatement = $conn->prepare('SELECT subscription_plan, subscription_cycle, subscription_expires_at FROM `user` WHERE userID = :userID LIMIT 1');
            $userSubscriptionStatement->execute(['userID' => $userID]);
            if ($userSubscriptionStatement->rowCount() > 0) {
                $userSubscription = $userSubscriptionStatement->fetch(PDO::FETCH_ASSOC);
                $profile['plan'] = isset($userSubscription['subscription_plan']) && $userSubscription['subscription_plan'] !== '' ? $userSubscription['subscription_plan'] : 'free';
                $profile['cycle'] = $userSubscription['subscription_cycle'] ?? null;
                $profile['expires_at'] = $userSubscription['subscription_expires_at'] ?? null;
            }
        }
    }

    if ($profile['plan'] === 'free') {
        $activeStoreID = isset($_SESSION['activeStoreID']) ? (int) $_SESSION['activeStoreID'] : 1;
        $storeColumnCheck = $conn->query("SHOW COLUMNS FROM `stores` LIKE 'subscription_plan'");
        if ($storeColumnCheck && $storeColumnCheck->rowCount() > 0) {
            $storeSubscriptionStatement = $conn->prepare('SELECT subscription_plan, subscription_cycle, subscription_expires_at FROM `stores` WHERE storeID = :storeID LIMIT 1');
            $storeSubscriptionStatement->execute(['storeID' => $activeStoreID]);
            if ($storeSubscriptionStatement->rowCount() > 0) {
                $storeSubscription = $storeSubscriptionStatement->fetch(PDO::FETCH_ASSOC);
                $profile['plan'] = isset($storeSubscription['subscription_plan']) && $storeSubscription['subscription_plan'] !== '' ? $storeSubscription['subscription_plan'] : 'free';
                $profile['cycle'] = $storeSubscription['subscription_cycle'] ?? null;
                $profile['expires_at'] = $storeSubscription['subscription_expires_at'] ?? null;
            }
        }
    }

    if (isset($profile['expires_at']) && $profile['expires_at'] !== null && $profile['expires_at'] !== '') {
        try {
            $expiresAt = new DateTimeImmutable((string) $profile['expires_at']);
            $now = new DateTimeImmutable('now');
            $profile['is_pro_active'] = ($profile['plan'] === 'pro' && $expiresAt > $now);
        } catch (Exception $e) {
            $profile['is_pro_active'] = false;
        }
    } else {
        $profile['is_pro_active'] = ($profile['plan'] === 'pro');
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
