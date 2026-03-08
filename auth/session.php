<?php
/**
 * VV ToolBox — Gestion sessions & sécurité
 */

require_once __DIR__ . '/../config/db.php';

// Configuration session sécurisée
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');      // HTTPS uniquement
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------
//  Headers de sécurité HTTP
// ----------------------------------------------------------
function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
         . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
         . "img-src 'self' data: blob:;");
}

// ----------------------------------------------------------
//  CSRF
// ----------------------------------------------------------
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}

// ----------------------------------------------------------
//  Authentification
// ----------------------------------------------------------
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        die('Accès refusé.');
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']       ?? null,
        'username' => $_SESSION['user_username'] ?? '',
        'email'    => $_SESSION['user_email']    ?? '',
        'role'     => $_SESSION['user_role']     ?? '',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

// ----------------------------------------------------------
//  Rate limiting login
// ----------------------------------------------------------
function isRateLimited(string $ip): bool {
    $db = getDB();
    $window = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND attempted_at > ? AND success = 0'
    );
    $stmt->execute([$ip, $window]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function recordLoginAttempt(string $ip, string $email, bool $success): void {
    $db = getDB();
    $db->prepare(
        'INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)'
    )->execute([$ip, $email, $success ? 1 : 0]);

    // Purge des entrées de plus de 30 jours (nettoyage opportuniste, ~5% des appels)
    if (random_int(1, 20) === 1) {
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
}

// ----------------------------------------------------------
//  Login / Logout
// ----------------------------------------------------------
function attemptLogin(string $email, string $password): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (isRateLimited($ip)) {
        return ['ok' => false, 'error' => 'Trop de tentatives. Réessayez dans ' . LOGIN_WINDOW_MINUTES . ' minutes.'];
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($ip, $email, false);
        return ['ok' => false, 'error' => 'Email ou mot de passe incorrect.'];
    }

    // Succès
    recordLoginAttempt($ip, $email, true);

    // Régénère l'ID de session (protection fixation)
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['login_time']    = time();

    // Màj last_login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
       ->execute([$user['id']]);

    return ['ok' => true, 'role' => $user['role']];
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

// Expiration automatique de session inactive
function checkSessionExpiry(): void {
    if (!empty($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
            logout();
        }
        // Glissement : met à jour le timestamp à chaque requête
        $_SESSION['login_time'] = time();
    }
}

setSecurityHeaders();
