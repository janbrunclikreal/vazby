<?php
require_once 'config.php';
require_once 'functions.php';

/**
 * Instalační skript pro vytvoření databáze a tabulek
 */

echo "<h1>Instalace aplikace pro správu vazeb</h1>";

try {
    // Připojení k MySQL serveru bez specifikované databáze
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "<p>✓ Připojení k MySQL serveru úspěšné</p>";
    
    // Vytvoření databáze
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✓ Databáze '" . DB_NAME . "' vytvořena</p>";
    
    // Výběr databáze
    $pdo->exec("USE " . DB_NAME);
    
    // Vytvoření tabulky app_users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('viewer', 'editor', 'admin') DEFAULT 'viewer',
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Tabulka 'app_users' vytvořena</p>";
    
    // Vytvoření tabulky vazby
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vazby (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nazev VARCHAR(255) NOT NULL UNIQUE,
            url VARCHAR(500) NOT NULL UNIQUE,
            popis TEXT,
            kategorie VARCHAR(100),
            schvaleno BOOLEAN DEFAULT FALSE,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES app_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Tabulka 'vazby' vytvořena</p>";
    
    // Vytvoření tabulky audit_log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p>✓ Tabulka 'audit_log' vytvořena</p>";
    
    // Kontrola existence admin uživatele
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        // Vytvoření výchozího admin uživatele
        $adminPassword = hashPassword('admin');
        $stmt = $pdo->prepare("
            INSERT INTO app_users (username, password, email, role) 
            VALUES ('admin', ?, 'admin@example.com', 'admin')
        ");
        $stmt->execute([$adminPassword]);
        echo "<p>✓ Výchozí admin účet vytvořen (username: admin, password: admin)</p>";
    } else {
        echo "<p>ℹ Admin účet již existuje</p>";
    }
    
    // Vložení ukázkových dat
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vazby");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $sampleData = [
            ['Google', 'https://www.google.com', 'Vyhledávač Google', 'Vyhledávače'],
            ['GitHub', 'https://github.com', 'Platforma pro správu kódu', 'Vývoj'],
            ['Stack Overflow', 'https://stackoverflow.com', 'Komunita programátorů', 'Vývoj']
        ];
        
        $adminId = $pdo->lastInsertId() ?: 1;
        
        foreach ($sampleData as $data) {
            $stmt = $pdo->prepare("
                INSERT INTO vazby (nazev, url, popis, kategorie, schvaleno, created_by) 
                VALUES (?, ?, ?, ?, TRUE, ?)
            ");
            $stmt->execute(array_merge($data, [$adminId]));
        }
        echo "<p>✓ Ukázková data vložena</p>";
    }
    
    echo "<h2 style='color: green;'>Instalace dokončena úspěšně!</h2>";
    echo "<p><strong>Přihlašovací údaje:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <code>admin</code></li>";
    echo "<li>Password: <code>admin</code></li>";
    echo "</ul>";
    echo "<p><a href='index.html'>Přejít na aplikaci</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Chyba: " . $e->getMessage() . "</p>";
    exit;
}
?>