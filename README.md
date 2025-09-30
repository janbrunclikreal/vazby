# Správa vazeb - Fullstack PHP aplikace

Kompletní webová aplikace pro správu uživatelských vazeb s PHP backendem, interaktivním frontendem a databází.

## Vlastnosti

### Backend (PHP)
- RESTful API s kompletními endpointy
- Systém role (viewer, editor, admin)
- Session-based autentizace
- Kontrola jedinečnosti vazeb
- Audit log všech akcí
- Bezpečnostní validace

### Frontend (HTML/CSS/JavaScript)
- Moderní, responzivní design
- Inline editace vazeb
- Real-time aktualizace
- Intuitivní uživatelské rozhraní
- Toast notifikace
- Modal dialogy

### Databáze (MySQL/MariaDB)
- Tabulka `app_users` - uživatelé s rolemi
- Tabulka `vazby` - vazby s možností schválení
- Tabulka `audit_log` - logování akcí

## Instalace

### Předpoklady
- PHP 7.4+
- MySQL/MariaDB
- Webový server (Apache/Nginx/Lighttpd)
- Moduly: PDO, PDO_MySQL

### Kroky instalace

1. **Nahrajte soubory** na webový server
2. **Upravte konfiguraci** v `config.php`:
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USER', 'vaše_db_jméno');
   define('DB_PASS', 'vaše_db_heslo');
   define('DB_NAME', 'vazby_app');
   ```

3. **Spusťte instalaci** navštívením `install.php` v prohlížeči
4. **Přihlaste se** s výchozími údaji:
   - **Username:** admin
   - **Password:** admin

## Struktura souborů

```
vazby-app/
├── config.php          # Konfigurace databáze
├── functions.php       # Pomocné funkce
├── api.php             # Hlavní API endpoint
├── install.php         # Instalační skript
├── test_api.php        # Testovací skript
├── index.html          # Hlavní stránka
├── style.css           # CSS styly
├── app.js              # JavaScript logika
└── .htaccess           # Apache konfigurace
```

## API Endpointy

### Autentizace
- `POST /api/auth/login` - Přihlášení
- `GET /api/auth/logout` - Odhlášení
- `GET /api/auth/status` - Stav přihlášení
- `POST /api/auth/change-password` - Změna hesla

### Vazby
- `GET /api/vazby` - Seznam vazeb
- `POST /api/vazby` - Přidání vazby
- `PUT /api/vazby/{id}` - Úprava vazby
- `DELETE /api/vazby/{id}` - Smazání vazby

### Uživatelé
- `GET /api/users` - Seznam uživatelů
- `POST /api/users` - Vytvoření uživatele
- `PUT /api/users` - Úprava uživatele
- `DELETE /api/users/{id}` - Smazání uživatele

## Role a oprávnění

### Viewer (Prohlížeč)
- Zobrazení schválených vazeb
- Změna vlastního hesla

### Editor
- Všechna oprávnění viewera
- Přidávání nových vazeb
- Úprava vazeb
- Zobrazení všech vazeb (i neschválených)

### Admin
- Všechna oprávnění editora
- Schvalování vazeb
- Smazání vazeb
- Správa uživatelů
- Automatické schválení přidávaných vazeb

## Testování

Pro testování API funkcionalit navštivte `test_api.php` v prohlížeči. Skript automaticky:
- Otestuje všechny klíčové endpointy
- Ověří CRUD operace
- Zkontroluje systém oprávnění

## Bezpečnost

- Hashovaná hesla (PHP password_hash)
- Sanitizované vstupy
- SQL injection ochrana (prepared statements)
- XSS ochrana
- Session management
- Role-based access control

## Kompatibilita

- **Webové servery:** Apache, Nginx, Lighttpd
- **PHP:** 7.4+
- **Databáze:** MySQL 5.7+, MariaDB 10.2+
- **Prohlížeče:** Chrome, Firefox, Safari, Edge

## Autor

MiniMax Agent - Fullstack aplikace pro správu vazeb