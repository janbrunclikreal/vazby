<?php
// Konfigurace databáze
define('DB_SERVER', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'vazby_app');

// Spuštění session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Nastavení časové zóny
date_default_timezone_set('Europe/Prague');

// Error reporting pro vývoj
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CORS hlavičky
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Odpovědět na OPTIONS požadavky
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>