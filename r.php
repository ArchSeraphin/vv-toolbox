<?php
/**
 * VV ToolBox — Redirecteur QR Code
 * URL : /r/{slug}
 * Incrémente le compteur de scans et redirige vers l'URL cible
 */

require_once __DIR__ . '/config/db.php';

// Sécurité basique : header minimal
header('X-Content-Type-Options: nosniff');

$slug = trim($_GET['s'] ?? '');

// Support URL rewrite : /r/slug → r.php?s=slug
// OU /r.php?s=slug direct
if (!$slug) {
    // Essayer de parser depuis REQUEST_URI : /r/slug
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#^/r/([a-z0-9\-]+)$#', $uri, $m)) {
        $slug = $m[1];
    }
}

if (!$slug || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(404);
    die('QR Code introuvable.');
}

try {
    $db = getDB();
    $st = $db->prepare('SELECT id, target_url FROM qr_codes WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    $qr = $st->fetch();

    if (!$qr) {
        http_response_code(404);
        die('Ce QR Code est introuvable.');
    }

    // Incrémenter le compteur de scans (fire & forget)
    $db->prepare('UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?')
       ->execute([$qr['id']]);

    // Redirection 302 (non-cachée pour tracking)
    header('Location: ' . $qr['target_url'], true, 302);
    exit;

} catch (Throwable $e) {
    error_log('[VV-ToolBox] Redirect error: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur de redirection.');
}
