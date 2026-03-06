<?php
/**
 * VV ToolBox — Point d'entrée
 * Redirige selon l'état de connexion
 */
require_once __DIR__ . '/auth/session.php';

if (isLoggedIn()) {
    checkSessionExpiry();
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;
