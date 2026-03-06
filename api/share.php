<?php
/**
 * VV ToolBox — API Partage
 * POST /api/share.php
 * Actions : list, add, remove
 */
require_once __DIR__ . '/../auth/session.php';
requireLogin();
checkSessionExpiry();

header('Content-Type: application/json');

$user = currentUser();
$db   = getDB();
$uid  = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'Méthode non autorisée']); exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok'=>false,'error'=>'Token invalide']); exit;
}

$action  = $_POST['action'] ?? '';
$rtype   = $_POST['rtype']  ?? '';   // qr | sig | vc
$rid     = (int)($_POST['rid'] ?? 0);

// Validate resource type
$validTypes = ['qr','sig','vc'];
$tableMap   = ['qr'=>'qr_codes','sig'=>'email_signatures','vc'=>'vcards'];

if ($rid && $rtype && !in_array($rtype, $validTypes)) {
    echo json_encode(['ok'=>false,'error'=>'Type invalide']); exit;
}

// Verify ownership (or shared access) before any action
function verifyOwner(PDO $db, string $rtype, int $rid, int $uid): bool {
    $tables = ['qr'=>'qr_codes','sig'=>'email_signatures','vc'=>'vcards'];
    $t = $tables[$rtype] ?? null;
    if (!$t) return false;
    $s = $db->prepare("SELECT id FROM $t WHERE id=? AND user_id=?");
    $s->execute([$rid, $uid]);
    return (bool)$s->fetch();
}

// ── LIST shares for a resource ──────────────────────────
if ($action === 'list') {
    if (!verifyOwner($db, $rtype, $rid, $uid)) {
        echo json_encode(['ok'=>false,'error'=>'Accès refusé']); exit;
    }
    $rows = $db->prepare(
        'SELECT s.id, s.permission, u.id AS user_id, u.username, u.email
         FROM shares s
         JOIN users u ON u.id = s.shared_with
         WHERE s.resource_type=? AND s.resource_id=?'
    );
    $rows->execute([$rtype, $rid]);
    echo json_encode(['ok'=>true,'shares'=>$rows->fetchAll()]); exit;
}

// ── LIST resources shared WITH me ───────────────────────
if ($action === 'mine') {
    $rows = $db->prepare(
        "SELECT s.resource_type, s.resource_id, s.permission,
                u.username AS owner_name
         FROM shares s
         JOIN users u ON u.id = s.owner_id
         WHERE s.shared_with = ?"
    );
    $rows->execute([$uid]);
    echo json_encode(['ok'=>true,'shared'=>$rows->fetchAll()]); exit;
}

// ── ADD share ───────────────────────────────────────────
if ($action === 'add') {
    if (!verifyOwner($db, $rtype, $rid, $uid)) {
        echo json_encode(['ok'=>false,'error'=>'Accès refusé']); exit;
    }

    $targetEmail = trim($_POST['email'] ?? '');
    $perm        = $_POST['permission'] ?? 'edit';
    if (!in_array($perm, ['view','edit'])) $perm = 'edit';

    if (!$targetEmail) {
        echo json_encode(['ok'=>false,'error'=>'Email requis']); exit;
    }

    // Find target user
    $tu = $db->prepare('SELECT id, username FROM users WHERE email=? AND is_active=1');
    $tu->execute([$targetEmail]);
    $target = $tu->fetch();

    if (!$target) {
        echo json_encode(['ok'=>false,'error'=>"Aucun utilisateur avec l'email « $targetEmail »"]); exit;
    }
    if ($target['id'] === $uid) {
        echo json_encode(['ok'=>false,'error'=>'Vous ne pouvez pas partager avec vous-même']); exit;
    }

    // Insert or update
    try {
        $db->prepare(
            'INSERT INTO shares (resource_type, resource_id, owner_id, shared_with, permission)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE permission=VALUES(permission)'
        )->execute([$rtype, $rid, $uid, $target['id'], $perm]);
        echo json_encode(['ok'=>true,'user'=>['id'=>$target['id'],'username'=>$target['username'],'email'=>$targetEmail,'permission'=>$perm]]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'error'=>'Erreur base de données']);
    }
    exit;
}

// ── REMOVE share ────────────────────────────────────────
if ($action === 'remove') {
    if (!verifyOwner($db, $rtype, $rid, $uid)) {
        echo json_encode(['ok'=>false,'error'=>'Accès refusé']); exit;
    }
    $shareId = (int)($_POST['share_id'] ?? 0);
    $db->prepare('DELETE FROM shares WHERE id=? AND resource_type=? AND resource_id=? AND owner_id=?')
       ->execute([$shareId, $rtype, $rid, $uid]);
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Action inconnue']);
