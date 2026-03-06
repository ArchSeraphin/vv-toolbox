<?php
/**
 * VV ToolBox — Handler login/logout (POST uniquement)
 */

require_once __DIR__ . '/../auth/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ----------------------------------------------------------
//  LOGIN
// ----------------------------------------------------------
if ($action === 'login') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token invalide.']);
        exit;
    }

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Champs obligatoires manquants.']);
        exit;
    }

    $result = attemptLogin($email, $password);
    echo json_encode($result);
    exit;
}

// ----------------------------------------------------------
//  LOGOUT
// ----------------------------------------------------------
if ($action === 'logout') {
    logout(); // redirige vers /login.php
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Action inconnue.']);
