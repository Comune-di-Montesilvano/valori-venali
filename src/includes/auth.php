<?php
/**
 * Gestione autenticazione e sessioni
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Avvia sessione sicura
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Tenta il login — restituisce true se le credenziali sono valide
 */
function login(string $username, string $password): bool {
    $user = DB::queryOne(
        'SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1',
        [$username]
    );
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['logged_at'] = time();
        return true;
    }
    return false;
}

/**
 * Verifica se l'utente è autenticato
 */
function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

/**
 * Richiede autenticazione — redirect a login.php se non autenticato
 */
function requireAuth(): void {
    startSecureSession();
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Logout completo
 */
function logout(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Genera token CSRF e lo salva in sessione
 */
function csrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida il token CSRF — termina con 403 se non valido
 */
function verifyCsrf(): void {
    startSecureSession();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF non valido. <a href="javascript:history.back()">Torna indietro</a>');
    }
}

/**
 * Crea l'utente admin dalla variabile d'ambiente se non esiste ancora
 */
function seedAdmin(): void {
    $adminUser = getenv('APP_ADMIN_USER') ?: 'admin';
    $adminPass = getenv('APP_ADMIN_PASS') ?: 'changeme123';

    $user = DB::queryOne('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1', [$adminUser]);

    if (!$user) {
        // Crea l'utente se non esiste
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        DB::execute(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)',
            [$adminUser, $hash]
        );
    } else {
        // Sincronizza la password se cambiata nel .env
        if (!password_verify($adminPass, $user['password_hash'])) {
            $hash = password_hash($adminPass, PASSWORD_BCRYPT);
            DB::execute(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [$hash, $user['id']]
            );
        }
    }
}
