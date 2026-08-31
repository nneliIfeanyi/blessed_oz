<?php
session_start();

if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== '1') {
    header('Location: ../../login.php');
    exit();
}

require_once('../../inc/config/constants.php');
require_once('../../inc/config/db.php');
require_once('../../inc/auth.php');
require_once('../../inc/store.php');

ensureActiveStoreSession($conn);
if (function_exists('loadUserSubscriptionSession')) {
    loadUserSubscriptionSession($conn);
}

// Multi-store switching is a Pro feature — free users stay on their current / main store
if (isset($_POST['storeID'])) {
    $requestedStoreID = (int) $_POST['storeID'];
    if (isProActive()) {
        setActiveStoreByID($conn, $requestedStoreID);
    } else {
        // Quietly ignore non-main switches for free plan
        if ($requestedStoreID === 1) {
            setActiveStoreByID($conn, 1);
        }
    }
}

// Derive the application base path from ROOT_URL so the redirect works on any sub-directory or live host.
$parsedBase = parse_url(ROOT_URL, PHP_URL_PATH);
$basePath = rtrim($parsedBase !== false && $parsedBase !== null ? $parsedBase : '/inventory-system', '/');

$redirectPath = $basePath . '/index.php';
if (isset($_POST['returnUrl'])) {
    $candidate = trim((string) $_POST['returnUrl']);
    // Accept the return URL only if it starts with the application base path.
    if ($candidate !== '' && strpos($candidate, $basePath . '/') === 0) {
        $redirectPath = $candidate;
    }
}

header('Location: ' . $redirectPath);
exit();
