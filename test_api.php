<?php
/**
 * Testovací skript pro API endpointy
 */

// Nastavení
$baseUrl = 'http://localhost:8007/api';
$testData = [];

echo "<h1>Test API endpointů</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;}h2{color:#333;border-bottom:1px solid #ccc;padding-bottom:5px;}pre{background:#f5f5f5;padding:10px;border-radius:4px;overflow-x:auto;}.success{color:green;}.error{color:red;}.info{color:blue;}</style>";

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers),
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_COOKIEJAR => 'cookies.txt',
        CURLOPT_COOKIEFILE => 'cookies.txt'
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'data' => json_decode($body, true)
    ];
}

function printResult($title, $response) {
    echo "<h2>$title</h2>";
    echo "<p class='info'>HTTP Status: {$response['status']}</p>";
    
    if ($response['status'] >= 200 && $response['status'] < 300) {
        echo "<p class='success'>✓ Úspěšný</p>";
    } else {
        echo "<p class='error'>✗ Chyba</p>";
    }
    
    echo "<h3>Odpověd:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}

// 1. Test přihlášení
$loginResponse = makeRequest($baseUrl . '/auth/login', 'POST', [
    'username' => 'admin',
    'password' => 'milostboha'
]);
printResult('1. Přihlášení (admin)', $loginResponse);

if ($loginResponse['status'] === 200) {
    // 2. Test stavu přihlášení
    $statusResponse = makeRequest($baseUrl . '/auth/status');
    printResult('2. Stav přihlášení', $statusResponse);
    
    // 3. Test získání vazeb
    $vazbyResponse = makeRequest($baseUrl . '/vazby');
    printResult('3. Získání vazeb', $vazbyResponse);
    
    // 4. Test přidání vazby
    $newVazba = [
        'nazev' => 'Test Vazba ' . date('H:i:s'),
        'url' => 'https://test-' . time() . '.com',
        'popis' => 'Testovací vazba vytvořená API testem',
        'kategorie' => 'Test'
    ];
    
    $createResponse = makeRequest($baseUrl . '/vazby', 'POST', $newVazba);
    printResult('4. Přidání vazby', $createResponse);
    
    $vazbaId = null;
    if ($createResponse['status'] === 200 && isset($createResponse['data']['vazba']['id'])) {
        $vazbaId = $createResponse['data']['vazba']['id'];
        
        // 5. Test úpravy vazby
        $updateData = [
            'popis' => 'Aktualizovaný popis - ' . date('H:i:s')
        ];
        
        $updateResponse = makeRequest($baseUrl . '/vazby/' . $vazbaId, 'PUT', $updateData);
        printResult('5. Úprava vazby', $updateResponse);
        
        // 6. Test smazání vazby
        $deleteResponse = makeRequest($baseUrl . '/vazby/' . $vazbaId, 'DELETE');
        printResult('6. Smazání vazby', $deleteResponse);
    }
    
    // 7. Test získání uživatelů
    $usersResponse = makeRequest($baseUrl . '/users');
    printResult('7. Získání uživatelů', $usersResponse);
    
    // 8. Test vytvoření uživatele
    $newUser = [
        'username' => 'testuser' . time(),
        'password' => 'testpassword',
        'email' => 'test' . time() . '@example.com',
        'role' => 'editor'
    ];
    
    $createUserResponse = makeRequest($baseUrl . '/users', 'POST', $newUser);
    printResult('8. Vytvoření uživatele', $createUserResponse);
    
    $userId = null;
    if ($createUserResponse['status'] === 200 && isset($createUserResponse['data']['user']['id'])) {
        $userId = $createUserResponse['data']['user']['id'];
        
        // 9. Test úpravy uživatele
        $updateUserData = [
            'id' => $userId,
            'role' => 'viewer'
        ];
        
        $updateUserResponse = makeRequest($baseUrl . '/users', 'PUT', $updateUserData);
        printResult('9. Úprava uživatele', $updateUserResponse);
        
        // 10. Test smazání uživatele
        $deleteUserResponse = makeRequest($baseUrl . '/users/' . $userId, 'DELETE');
        printResult('10. Smazání uživatele', $deleteUserResponse);
    }
    
    // 11. Test změny hesla
    $changePasswordResponse = makeRequest($baseUrl . '/auth/change-password', 'POST', [
        'current_password' => 'admin',
        'new_password' => 'admin'  // Znět nastavíme stejné heslo
    ]);
    printResult('11. Změna hesla', $changePasswordResponse);
    
    // 12. Test odhlášení
    $logoutResponse = makeRequest($baseUrl . '/auth/logout');
    printResult('12. Odhlášení', $logoutResponse);
    
} else {
    echo "<p class='error'>Přihlášení selhalo, další testy nebudou provedeny.</p>";
}

echo "<h2>Test dokončen</h2>";
echo "<p><a href='index.html'>Zpět na aplikaci</a></p>";

// Smaž soubor s cookies
if (file_exists('cookies.txt')) {
    unlink('cookies.txt');
}
?>