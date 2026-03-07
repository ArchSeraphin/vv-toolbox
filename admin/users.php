<?php
/**
 * VV ToolBox — Admin : Gestion des membres
 */
require_once __DIR__ . '/../auth/session.php';
requireAdmin();
checkSessionExpiry();

$user = currentUser();
$db   = getDB();

$msg = '';
$msgType = '';

// ── ACTIONS POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Token invalide.'; $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $uname = trim($_POST['username'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password'] ?? '';
            $role  = in_array($_POST['role'], ['admin','member']) ? $_POST['role'] : 'member';

            if (!$uname || !$email || !$pass) {
                $msg = 'Champs obligatoires manquants.'; $msgType = 'error';
            } elseif (strlen($pass) < 8) {
                $msg = 'Le mot de passe doit faire au moins 8 caractères.'; $msgType = 'error';
            } else {
                try {
                    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)')
                       ->execute([$uname, $email, $hash, $role]);
                    $msg = "Membre \"$uname\" créé avec succès."; $msgType = 'success';
                } catch (PDOException $e) {
                    $msg = 'Email ou nom d\'utilisateur déjà utilisé.'; $msgType = 'error';
                }
            }
        }

        if ($action === 'toggle') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            if ($tid === (int)$user['id']) {
                $msg = 'Vous ne pouvez pas désactiver votre propre compte.'; $msgType = 'error';
            } else {
                $db->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$tid]);
                $msg = 'Statut mis à jour.'; $msgType = 'success';
            }
        }

        if ($action === 'delete') {
            $tid = (int) ($_POST['target_id'] ?? 0);
            if ($tid === (int)$user['id']) {
                $msg = 'Vous ne pouvez pas supprimer votre propre compte.'; $msgType = 'error';
            } else {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$tid]);
                $msg = 'Compte supprimé.'; $msgType = 'success';
            }
        }

        if ($action === 'reset_password') {
            $tid  = (int) ($_POST['target_id'] ?? 0);
            $pass = $_POST['new_password'] ?? '';
            if (strlen($pass) < 8) {
                $msg = 'Mot de passe trop court (8 car. min).'; $msgType = 'error';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $tid]);
                $msg = 'Mot de passe réinitialisé.'; $msgType = 'success';
            }
        }
    }
}

$members = $db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$csrf = getCsrfToken();

function navCount(PDO $db, string $t, bool $adm, int $uid): int {
    $s=$db->prepare($adm?"SELECT COUNT(*) FROM $t":"SELECT COUNT(*) FROM $t WHERE user_id=?");
    $adm?$s->execute():$s->execute([$uid]); return (int)$s->fetchColumn();
}
$uid   = (int)$user['id'];
$navQr  = navCount($db,'qr_codes',true,$uid);
$navSig = navCount($db,'email_signatures',true,$uid);
$navVc  = navCount($db,'vcards',true,$uid);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Membres — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/layout.css">
<style>
.main { flex:1;overflow-y:auto;padding:28px 32px 40px;display:flex;flex-direction:column;gap:24px; }
.main::-webkit-scrollbar { width:6px; }
.main::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
.page-header { display:flex;align-items:flex-start;justify-content:space-between;gap:16px; }
.ph-title { font-family:'Instrument Serif',serif;font-size:26px;letter-spacing:-.4px; }
.ph-sub { font-size:13px;color:var(--muted);margin-top:4px; }
.alert { display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:13px; }
.alert.success { background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);color:var(--success); }
.alert.error { background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:var(--error); }
.table-wrap { background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden; }
.tw-header { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border); }
.tw-title { font-size:14px;font-weight:600; }
table { width:100%;border-collapse:collapse; }
thead th { padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--dim);background:var(--s2);border-bottom:1px solid var(--border); }
tbody tr { border-bottom:1px solid var(--border);transition:background .1s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--s2); }
tbody td { padding:13px 16px;font-size:13px; }
.role-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px; }
.role-admin { background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent); }
.role-member { background:rgba(14,165,233,.1);color:#0ea5e9; }
.status-dot { display:inline-flex;align-items:center;gap:6px;font-size:12px; }
.dot { width:6px;height:6px;border-radius:50%; }
.dot-active { background:var(--success); }
.dot-inactive { background:var(--error); }
.action-btns { display:flex;gap:6px;flex-wrap:wrap; }
.me-badge { font-size:10px;padding:1px 6px;border-radius:4px;background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent);font-weight:600;margin-left:4px; }
</style>
</head>
<body>

<div class="topbar">
  <a class="tb-logo" href="/dashboard.php">
    <div class="tb-logo-icon"><i class="fa fa-toolbox"></i></div>
    <div class="tb-logo-text">
      <div class="tb-logo-name">VV ToolBox</div>
      <div class="tb-logo-sub">Espace de travail</div>
    </div>
  </a>
  <div class="tb-center">
    <div class="tb-bc">
      <a href="/dashboard.php">Dashboard</a>
      <span class="sep"><i class="fa fa-chevron-right"></i></span>
      <span style="color:var(--text)">Membres</span>
    </div>
  </div>
  <div class="tb-right">
    <button class="tb-btn" onclick="toggleTheme()" aria-label="Thème"><i class="fa fa-sun" id="themeIco"></i></button>
  </div>
</div>

<div class="layout">
  <nav class="nav">
    <div class="nav-body">
      <div class="nav-section">
        <div class="nav-label">Navigation</div>
        <a class="nav-item" href="/dashboard.php"><i class="fa fa-house"></i><span class="nav-item-label"> Dashboard</span><span class="nav-tip">Dashboard</span></a>
      </div>
      <div class="nav-section">
        <div class="nav-label">Outils</div>
        <a class="nav-item" href="/tools/qr.php"><i class="fa fa-qrcode"></i><span class="nav-item-label"> QR Code</span><span class="nav-badge"><?=$navQr?></span><span class="nav-tip">QR Code</span></a>
        <a class="nav-item" href="/tools/signature.php"><i class="fa fa-envelope"></i><span class="nav-item-label"> Signature mail</span><span class="nav-badge"><?=$navSig?></span><span class="nav-tip">Signature mail</span></a>
        <a class="nav-item" href="/tools/vcard.php"><i class="fa fa-id-card"></i><span class="nav-item-label"> Carte de visite</span><span class="nav-badge"><?=$navVc?></span><span class="nav-tip">Carte de visite</span></a>
      </div>
      <div class="nav-sep"></div>
      <div class="nav-section">
        <div class="nav-label">Administration</div>
        <a class="nav-item active" href="/admin/users.php"><i class="fa fa-users"></i><span class="nav-item-label"> Membres</span><span class="nav-badge"><?= count($members) ?></span><span class="nav-tip">Membres</span></a>
      </div>
      <div class="nav-sep"></div>
      <a class="nav-item" href="/profile.php"><i class="fa fa-user-pen"></i><span class="nav-item-label"> Mon profil</span><span class="nav-tip">Mon profil</span></a>
      <a class="nav-item" href="/logout.php"><i class="fa fa-arrow-right-from-bracket"></i><span class="nav-item-label"> Déconnexion</span><span class="nav-tip">Déconnexion</span></a>
    </div>
    <div class="nav-footer">
      <button class="nav-toggle" onclick="toggleNav()">
        <i class="fa fa-chevron-left" id="navToggleIco"></i>
        <span class="nav-toggle-label" id="navToggleLbl">Réduire</span>
      </button>
    </div>
  </nav>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="ph-title">Gestion des membres</h1>
        <p class="ph-sub"><?= count($members) ?> compte<?= count($members) > 1 ? 's' : '' ?> enregistré<?= count($members) > 1 ? 's' : '' ?></p>
      </div>
      <button class="btn btn-primary" onclick="openOv('modalCreate')">
        <i class="fa fa-user-plus"></i> Ajouter un membre
      </button>
    </div>

    <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>">
      <i class="fa <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
      <div class="tw-header">
        <div class="tw-title">Tous les membres</div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Membre</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th>Inscrit</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m):
            $isMe = ($m['id'] == $user['id']);
          ?>
          <tr>
            <td>
              <div style="font-weight:500">
                <?= htmlspecialchars($m['username']) ?>
                <?php if ($isMe): ?><span class="me-badge">Vous</span><?php endif; ?>
              </div>
              <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($m['email']) ?></div>
            </td>
            <td><span class="role-badge role-<?= $m['role'] ?>"><?= $m['role'] === 'admin' ? 'Admin' : 'Membre' ?></span></td>
            <td>
              <span class="status-dot">
                <span class="dot <?= $m['is_active'] ? 'dot-active' : 'dot-inactive' ?>"></span>
                <?= $m['is_active'] ? 'Actif' : 'Inactif' ?>
              </span>
            </td>
            <td style="color:var(--muted);font-size:12px"><?= $m['last_login'] ? date('d/m/Y H:i', strtotime($m['last_login'])) : '—' ?></td>
            <td style="color:var(--muted);font-size:12px"><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
            <td>
              <?php if (!$isMe): ?>
              <div class="action-btns">
                <form method="post" style="display:inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="<?= $m['is_active'] ? 'Désactiver' : 'Activer' ?>">
                    <i class="fa <?= $m['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                  </button>
                </form>
                <button class="btn btn-ghost btn-sm" onclick="openReset(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['username'])) ?>')" title="Réinitialiser mdp">
                  <i class="fa fa-key"></i>
                </button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce compte définitivement ?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="target_id" value="<?= $m['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">
                    <i class="fa fa-trash"></i>
                  </button>
                </form>
              </div>
              <?php else: ?>
              <span style="font-size:12px;color:var(--dim)">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<!-- Modal: Créer membre -->
<div class="ov" id="modalCreate">
  <div class="modal">
    <h2>Ajouter un membre</h2>
    <p class="modal-desc">Créez un nouveau compte avec accès aux outils.</p>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="fld"><label>Nom d'utilisateur</label><input type="text" name="username" placeholder="jdupont" required></div>
      <div class="fld"><label>Email</label><input type="email" name="email" placeholder="j.dupont@agence.fr" required></div>
      <div class="fld"><label>Mot de passe</label><input type="password" name="password" placeholder="8 caractères minimum" required minlength="8"></div>
      <div class="fld"><label>Rôle</label>
        <select name="role">
          <option value="member">Membre</option>
          <option value="admin">Administrateur</option>
        </select>
      </div>
      <div class="mf">
        <button type="button" class="btn btn-ghost" onclick="closeOv('modalCreate')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-user-plus"></i> Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Reset mdp -->
<div class="ov" id="modalReset">
  <div class="modal">
    <h2>Réinitialiser le mot de passe</h2>
    <p class="modal-desc" id="resetDesc">Définir un nouveau mot de passe.</p>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="target_id" id="resetTargetId">
      <div class="fld"><label>Nouveau mot de passe</label><input type="password" name="new_password" placeholder="8 caractères minimum" required minlength="8"></div>
      <div class="mf">
        <button type="button" class="btn btn-ghost" onclick="closeOv('modalReset')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Réinitialiser</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/layout.js"></script>
<script>
function openReset(id, name) {
  document.getElementById('resetTargetId').value = id;
  document.getElementById('resetDesc').textContent = 'Nouveau mot de passe pour ' + name;
  openOv('modalReset');
}
</script>
</body>
</html>
