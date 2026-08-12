<?php
function ensureUserRoleColumn(PDO $conn)
{
    $roleColumnCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'role'");
    if ($roleColumnCheck->rowCount() === 0) {
        $conn->exec("ALTER TABLE `user` ADD COLUMN `role` varchar(20) NOT NULL DEFAULT 'admin' AFTER `password`");
    }

    $conn->exec("UPDATE `user` SET role = 'admin' WHERE role IS NULL OR role = '' OR role NOT IN ('admin', 'super_admin')");
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
