<?php
require_once 'config.php';

/**
 * Získání připojení k databázi
 */
function getDbConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Chyba připojení k databázi']);
        exit;
    }
}

/**
 * Sanitizace vstupních dat
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Kontrola role uživatele
 */
function checkUserRole($requiredRoles = []) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Nepřihlášený uživatel']);
        exit;
    }
    
    if (!empty($requiredRoles) && !in_array($_SESSION['role'], $requiredRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Nedostatečná oprávnění']);
        exit;
    }
    
    return true;
}

/**
 * Záznam akce do audit logu
 */
function logAction($action, $details = '', $user_id = null) {
    try {
        $pdo = getDbConnection();
        $user_id = $user_id ?? $_SESSION['user_id'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, details, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$user_id, $action, $details]);
    } catch (Exception $e) {
        error_log("Logging failed: " . $e->getMessage());
    }
}

/**
 * Získání uživatele podle ID
 */
function getUserById($user_id) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Ověření hesla
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Hashování hesla
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Kontrola jedinečnosti vazby
 */
function isVazbaUnique($nazev, $url, $exclude_id = null) {
    $pdo = getDbConnection();
    
    $sql = "SELECT COUNT(*) FROM vazby WHERE (nazev = ? OR url = ?)";
    $params = [$nazev, $url];
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

/**
 * Odeslání JSON odpovědi
 */
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Parsování JSON vstupů
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}
?>