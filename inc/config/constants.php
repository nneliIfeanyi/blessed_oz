<?php

if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    // Local development environment
    define('ROOT_URL', 'http://localhost/inventory_system/');
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'leadstar_inventory');
} else {
    // Online production environment
    define('ROOT_URL', 'https://leadstar.com.ng/inventory/');
    define('DB_HOST', 'localhost');
    define('DB_USER', 'leadstar_inventory');
    define('DB_PASSWORD', 'Avalanche@25');
    define('DB_NAME', 'leadstar_inventory');
}

define('DSN', 'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME);

