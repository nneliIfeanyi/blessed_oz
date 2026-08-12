<?php
session_start();

if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== '1') {
    header('Location: ../../login.php');
    exit();
}

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);

if (isset($_POST['storeID'])) {
    setActiveStoreByID($conn, $_POST['storeID']);
}

$redirectPath = '/inventory-system/index.php';
if (isset($_POST['returnUrl'])) {
    $candidate = trim((string) $_POST['returnUrl']);
    if ($candidate !== '' && strpos($candidate, '/inventory-system/') === 0) {
        $redirectPath = $candidate;
    }
}

header('Location: ' . $redirectPath);
exit();
