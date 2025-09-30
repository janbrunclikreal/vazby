<?php
require_once 'config.php';
require_once 'functions.php';

// Získání HTTP metody a cesty
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Routing
try {
    if ($pathParts[0] === 'api') {
        switch ($pathParts[1]) {
            case 'auth':
                handleAuth($method, $pathParts[2] ?? '');
                break;
            case 'vazby':
                handleVazby($method, $pathParts[2] ?? null);
                break;
            case 'users':
                handleUsers($method, $pathParts[2] ?? null);
                break;
            default:
                sendJsonResponse(['error' => 'Neznámý endpoint'], 404);
        }
    } else {
        sendJsonResponse(['error' => 'API endpoint not found'], 404);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendJsonResponse(['error' => 'Interní chyba serveru'], 500);
}

/**
 * Zpracování autentizačních endpointů
 */
function handleAuth($method, $action) {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                sendJsonResponse(['error' => 'Povolen pouze POST'], 405);
            }
            handleLogin();
            break;
            
        case 'logout':
            if ($method !== 'GET') {
                sendJsonResponse(['error' => 'Povolen pouze GET'], 405);
            }
            handleLogout();
            break;
            
        case 'status':
            if ($method !== 'GET') {
                sendJsonResponse(['error' => 'Povolen pouze GET'], 405);
            }
            handleAuthStatus();
            break;
            
        case 'change-password':
            if ($method !== 'POST') {
                sendJsonResponse(['error' => 'Povolen pouze POST'], 405);
            }
            handleChangePassword();
            break;
            
        default:
            sendJsonResponse(['error' => 'Neznámý auth endpoint'], 404);
    }
}

/**
 * Zpracování endpointů pro vazby
 */
function handleVazby($method, $id) {
    switch ($method) {
        case 'GET':
            handleGetVazby();
            break;
            
        case 'POST':
            checkUserRole(['editor', 'admin']);
            handleCreateVazba();
            break;
            
        case 'PUT':
            checkUserRole(['editor', 'admin']);
            handleUpdateVazba($id);
            break;
            
        case 'DELETE':
            checkUserRole(['admin']);
            if (!$id) {
                sendJsonResponse(['error' => 'ID vazby je povinné'], 400);
            }
            handleDeleteVazba($id);
            break;
            
        default:
            sendJsonResponse(['error' => 'Nepodporovaná metoda'], 405);
    }
}

/**
 * Zpracování endpointů pro uživatele
 */
function handleUsers($method, $id) {
    checkUserRole(['admin']);
    
    switch ($method) {
        case 'GET':
            handleGetUsers();
            break;
            
        case 'POST':
            handleCreateUser();
            break;
            
        case 'PUT':
            handleUpdateUser();
            break;
            
        case 'DELETE':
            if (!$id) {
                sendJsonResponse(['error' => 'ID uživatele je povinné'], 400);
            }
            handleDeleteUser($id);
            break;
            
        default:
            sendJsonResponse(['error' => 'Nepodporovaná metoda'], 405);
    }
}

// AUTH HANDLERS

function handleLogin() {
    $input = getJsonInput();
    
    if (!isset($input['username']) || !isset($input['password'])) {
        sendJsonResponse(['error' => 'Username a password jsou povinné'], 400);
    }
    
    $username = sanitizeInput($input['username']);
    $password = $input['password'];
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM app_users WHERE username = ? AND active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && verifyPassword($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        logAction('LOGIN', "Uživatel $username se přihlásil");
        
        sendJsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    } else {
        logAction('LOGIN_FAILED', "Nepodařené přihlášení pro $username", null);
        sendJsonResponse(['error' => 'Neplatné přihlašovací údaje'], 401);
    }
}

function handleLogout() {
    if (isset($_SESSION['username'])) {
        logAction('LOGOUT', "Uživatel {$_SESSION['username']} se odhlásil");
    }
    
    session_destroy();
    sendJsonResponse(['success' => true]);
}

function handleAuthStatus() {
    if (isset($_SESSION['user_id'])) {
        $user = getUserById($_SESSION['user_id']);
        if ($user && $user['active']) {
            sendJsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);
        }
    }
    
    sendJsonResponse(['authenticated' => false]);
}

function handleChangePassword() {
    checkUserRole();
    
    $input = getJsonInput();
    if (!isset($input['current_password']) || !isset($input['new_password'])) {
        sendJsonResponse(['error' => 'Stavní a nové heslo jsou povinné'], 400);
    }
    
    $user = getUserById($_SESSION['user_id']);
    if (!verifyPassword($input['current_password'], $user['password'])) {
        sendJsonResponse(['error' => 'Neplatné stavní heslo'], 400);
    }
    
    $newPasswordHash = hashPassword($input['new_password']);
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE app_users SET password = ? WHERE id = ?");
    $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
    
    logAction('PASSWORD_CHANGE', "Uživatel změnil heslo");
    sendJsonResponse(['success' => true]);
}

// VAZBY HANDLERS

function handleGetVazby() {
    $pdo = getDbConnection();
    
    // Hosté vidí pouze schválené vazby, editor/admin vidí všechny
    if (!isset($_SESSION['user_id'])) {
        $sql = "SELECT v.*, u.username as created_by_username FROM vazby v 
                LEFT JOIN app_users u ON v.created_by = u.id 
                WHERE v.schvaleno = TRUE ORDER BY v.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        $role = $_SESSION['role'];
        if ($role === 'viewer') {
            $sql = "SELECT v.*, u.username as created_by_username FROM vazby v 
                    LEFT JOIN app_users u ON v.created_by = u.id 
                    WHERE v.schvaleno = TRUE ORDER BY v.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "SELECT v.*, u.username as created_by_username FROM vazby v 
                    LEFT JOIN app_users u ON v.created_by = u.id 
                    ORDER BY v.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
    }
    
    $vazby = $stmt->fetchAll();
    sendJsonResponse(['vazby' => $vazby]);
}

function handleCreateVazba() {
    $input = getJsonInput();
    
    $required = ['nazev', 'url'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            sendJsonResponse(['error' => "Pole '$field' je povinné"], 400);
        }
    }
    
    $nazev = sanitizeInput($input['nazev']);
    $url = sanitizeInput($input['url']);
    $popis = sanitizeInput($input['popis'] ?? '');
    $kategorie = sanitizeInput($input['kategorie'] ?? '');
    
    if (!isVazbaUnique($nazev, $url)) {
        sendJsonResponse(['error' => 'Vazba s tímto názvem nebo URL již existuje'], 400);
    }
    
    // Admin automaticky schvaluje
    $schvaleno = $_SESSION['role'] === 'admin';
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        INSERT INTO vazby (nazev, url, popis, kategorie, schvaleno, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$nazev, $url, $popis, $kategorie, $schvaleno, $_SESSION['user_id']]);
    $vazbaId = $pdo->lastInsertId();
    
    logAction('CREATE_VAZBA', "Vytvořena vazba: $nazev");
    
    // Vrátít vytvořenou vazbu
    $stmt = $pdo->prepare("
        SELECT v.*, u.username as created_by_username FROM vazby v 
        LEFT JOIN app_users u ON v.created_by = u.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$vazbaId]);
    $vazba = $stmt->fetch();
    
    sendJsonResponse(['success' => true, 'vazba' => $vazba]);
}

function handleUpdateVazba($id) {
    $input = getJsonInput();
    
    if (!$id && isset($input['id'])) {
        $id = $input['id'];
    }
    
    if (!$id) {
        sendJsonResponse(['error' => 'ID vazby je povinné'], 400);
    }
    
    $pdo = getDbConnection();
    
    // Kontrola existence vazby
    $stmt = $pdo->prepare("SELECT * FROM vazby WHERE id = ?");
    $stmt->execute([$id]);
    $existingVazba = $stmt->fetch();
    
    if (!$existingVazba) {
        sendJsonResponse(['error' => 'Vazba nenalezena'], 404);
    }
    
    // Příprava dat pro aktualizaci
    $updates = [];
    $params = [];
    
    if (isset($input['nazev'])) {
        $nazev = sanitizeInput($input['nazev']);
        if (!isVazbaUnique($nazev, $existingVazba['url'], $id)) {
            sendJsonResponse(['error' => 'Vazba s tímto názvem již existuje'], 400);
        }
        $updates[] = 'nazev = ?';
        $params[] = $nazev;
    }
    
    if (isset($input['url'])) {
        $url = sanitizeInput($input['url']);
        if (!isVazbaUnique($existingVazba['nazev'], $url, $id)) {
            sendJsonResponse(['error' => 'Vazba s tímto URL již existuje'], 400);
        }
        $updates[] = 'url = ?';
        $params[] = $url;
    }
    
    if (isset($input['popis'])) {
        $updates[] = 'popis = ?';
        $params[] = sanitizeInput($input['popis']);
    }
    
    if (isset($input['kategorie'])) {
        $updates[] = 'kategorie = ?';
        $params[] = sanitizeInput($input['kategorie']);
    }
    
    // Pouze admin může schválit
    if (isset($input['schvaleno']) && $_SESSION['role'] === 'admin') {
        $updates[] = 'schvaleno = ?';
        $params[] = (bool)$input['schvaleno'];
    }
    
    if (empty($updates)) {
        sendJsonResponse(['error' => 'Žádná data k aktualizaci'], 400);
    }
    
    $params[] = $id;
    
    $sql = "UPDATE vazby SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    logAction('UPDATE_VAZBA', "Aktualizována vazba ID: $id");
    
    // Vrátít aktualizovanou vazbu
    $stmt = $pdo->prepare("
        SELECT v.*, u.username as created_by_username FROM vazby v 
        LEFT JOIN app_users u ON v.created_by = u.id 
        WHERE v.id = ?
    ");
    $stmt->execute([$id]);
    $vazba = $stmt->fetch();
    
    sendJsonResponse(['success' => true, 'vazba' => $vazba]);
}

function handleDeleteVazba($id) {
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("SELECT nazev FROM vazby WHERE id = ?");
    $stmt->execute([$id]);
    $vazba = $stmt->fetch();
    
    if (!$vazba) {
        sendJsonResponse(['error' => 'Vazba nenalezena'], 404);
    }
    
    $stmt = $pdo->prepare("DELETE FROM vazby WHERE id = ?");
    $stmt->execute([$id]);
    
    logAction('DELETE_VAZBA', "Smazána vazba: {$vazba['nazev']}");
    
    sendJsonResponse(['success' => true]);
}

// USERS HANDLERS

function handleGetUsers() {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, active, created_at, updated_at 
        FROM app_users ORDER BY created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    sendJsonResponse(['users' => $users]);
}

function handleCreateUser() {
    $input = getJsonInput();
    
    $required = ['username', 'password', 'email', 'role'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            sendJsonResponse(['error' => "Pole '$field' je povinné"], 400);
        }
    }
    
    $username = sanitizeInput($input['username']);
    $email = sanitizeInput($input['email']);
    $role = sanitizeInput($input['role']);
    $password = $input['password'];
    
    if (!in_array($role, ['viewer', 'editor', 'admin'])) {
        sendJsonResponse(['error' => 'Neplatná role'], 400);
    }
    
    $pdo = getDbConnection();
    
    // Kontrola jedinečnosti
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        sendJsonResponse(['error' => 'Uživatel s tímto jménem nebo emailem již existuje'], 400);
    }
    
    $passwordHash = hashPassword($password);
    
    $stmt = $pdo->prepare("
        INSERT INTO app_users (username, password, email, role) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$username, $passwordHash, $email, $role]);
    $userId = $pdo->lastInsertId();
    
    logAction('CREATE_USER', "Vytvořen uživatel: $username");
    
    // Vrátít vytvořeného uživatele
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, active, created_at, updated_at 
        FROM app_users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    sendJsonResponse(['success' => true, 'user' => $user]);
}

function handleUpdateUser() {
    $input = getJsonInput();
    
    if (!isset($input['id'])) {
        sendJsonResponse(['error' => 'ID uživatele je povinné'], 400);
    }
    
    $id = (int)$input['id'];
    
    $pdo = getDbConnection();
    
    // Kontrola existence uživatele
    $stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = ?");
    $stmt->execute([$id]);
    $existingUser = $stmt->fetch();
    
    if (!$existingUser) {
        sendJsonResponse(['error' => 'Uživatel nenalezen'], 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($input['username'])) {
        $username = sanitizeInput($input['username']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);
        if ($stmt->fetchColumn() > 0) {
            sendJsonResponse(['error' => 'Uživatelské jméno již existuje'], 400);
        }
        $updates[] = 'username = ?';
        $params[] = $username;
    }
    
    if (isset($input['email'])) {
        $email = sanitizeInput($input['email']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetchColumn() > 0) {
            sendJsonResponse(['error' => 'Email již existuje'], 400);
        }
        $updates[] = 'email = ?';
        $params[] = $email;
    }
    
    if (isset($input['role'])) {
        $role = sanitizeInput($input['role']);
        if (!in_array($role, ['viewer', 'editor', 'admin'])) {
            sendJsonResponse(['error' => 'Neplatná role'], 400);
        }
        $updates[] = 'role = ?';
        $params[] = $role;
    }
    
    if (isset($input['active'])) {
        $updates[] = 'active = ?';
        $params[] = (bool)$input['active'];
    }
    
    if (isset($input['password']) && !empty($input['password'])) {
        $updates[] = 'password = ?';
        $params[] = hashPassword($input['password']);
    }
    
    if (empty($updates)) {
        sendJsonResponse(['error' => 'Žádná data k aktualizaci'], 400);
    }
    
    $params[] = $id;
    
    $sql = "UPDATE app_users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    logAction('UPDATE_USER', "Aktualizován uživatel ID: $id");
    
    // Vrátít aktualizovaného uživatele
    $stmt = $pdo->prepare("
        SELECT id, username, email, role, active, created_at, updated_at 
        FROM app_users WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    sendJsonResponse(['success' => true, 'user' => $user]);
}

function handleDeleteUser($id) {
    // Admin nemůže smazat sebe
    if ((int)$id === $_SESSION['user_id']) {
        sendJsonResponse(['error' => 'Nemůžete smazat sebe'], 400);
    }
    
    $pdo = getDbConnection();
    
    $stmt = $pdo->prepare("SELECT username FROM app_users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJsonResponse(['error' => 'Uživatel nenalezen'], 404);
    }
    
    $stmt = $pdo->prepare("DELETE FROM app_users WHERE id = ?");
    $stmt->execute([$id]);
    
    logAction('DELETE_USER', "Smazán uživatel: {$user['username']}");
    
    sendJsonResponse(['success' => true]);
}
?>