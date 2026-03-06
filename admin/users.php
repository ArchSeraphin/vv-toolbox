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
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#080809;--surface:#0f0f11;--s2:#17171a;--s3:#1e1e23;--border:#26262d;--border2:#32323c;--accent:#4f6ef7;--text:#eeeef2;--muted:#7a7a8f;--dim:#3a3a48;--success:#34d399;--error:#f87171;--nav-w:240px;--topbar-h:58px}
[data-theme="light"]{--bg:#f1f1f5;--surface:#fff;--s2:#f7f7fb;--s3:#eeeeF5;--border:#e0e0ea;--border2:#d0d0de;--text:#0d0d14;--muted:#6b6b80;--dim:#b8b8cc}
html,body{height:100%}
body{font-family:'Geist',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;font-size:14px;line-height:1.5;overflow:hidden}

/* Topbar */
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px 0 0;flex-shrink:0;z-index:50}
.tb-logo{width:var(--nav-w);display:flex;align-items:center;gap:11px;padding:0 20px;border-right:1px solid var(--border);height:100%;flex-shrink:0;text-decoration:none;color:var(--text)}
.tb-logo-icon{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#7c5cfc);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;flex-shrink:0}
.tb-logo-name{font-family:'Instrument Serif',serif;font-size:18px;letter-spacing:-.2px}
.tb-logo-sub{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.tb-center{flex:1;padding:0 24px}
.tb-breadcrumb{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:8px}
.tb-breadcrumb a{color:var(--muted);text-decoration:none}.tb-breadcrumb a:hover{color:var(--text)}
.tb-breadcrumb .sep{color:var(--dim)}
.tb-right{display:flex;align-items:center;gap:8px}
.tb-btn{width:36px;height:36px;border-radius:9px;background:transparent;border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.tb-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--s2)}

/* Layout */
.layout{display:flex;flex:1;overflow:hidden}
.nav{width:var(--nav-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;overflow-y:auto;padding:16px 10px}
.nav::-webkit-scrollbar{width:0}
.nav-section{margin-bottom:24px}
.nav-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--dim);padding:0 10px;margin-bottom:6px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:9px;color:var(--muted);text-decoration:none;transition:all .12s;font-size:13px;font-weight:500;border:1px solid transparent}
.nav-item:hover{background:var(--s2);color:var(--text)}
.nav-item.active{background:rgba(79,110,247,.1);border-color:rgba(79,110,247,.2);color:var(--accent)}
.nav-item i{width:16px;text-align:center;font-size:14px;flex-shrink:0}
.nav-badge{margin-left:auto;font-size:10px;font-weight:600;padding:1px 7px;border-radius:20px;background:var(--s3);color:var(--muted)}
.nav-sep{height:1px;background:var(--border);margin:8px 4px}

/* Main */
.main{flex:1;overflow-y:auto;padding:28px 32px 40px;display:flex;flex-direction:column;gap:24px}
.main::-webkit-scrollbar{width:6px}.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.ph-title{font-family:'Instrument Serif',serif;font-size:26px;letter-spacing:-.4px}
.ph-sub{font-size:13px;color:var(--muted);margin-top:4px}

.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;border:none;font-family:'Geist',sans-serif;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .15s}
.btn-primary{background:linear-gradient(135deg,var(--accent),#6a5af9);color:#fff;box-shadow:0 2px 12px rgba(79,110,247,.3)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,110,247,.4)}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--s2);color:var(--text)}
.btn-danger{background:transparent;color:var(--error);border:1px solid rgba(248,113,113,.3)}
.btn-danger:hover{background:rgba(248,113,113,.08)}
.btn-sm{padding:6px 12px;font-size:12px}

/* Alert */
.alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:13px}
.alert.success{background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);color:var(--success)}
.alert.error{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:var(--error)}

/* Table */
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden}
.tw-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border)}
.tw-title{font-size:14px;font-weight:600}
table{width:100%;border-collapse:collapse}
thead th{padding:11px 16px;text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--dim);background:var(--s2);border-bottom:1px solid var(--border)}
tbody tr{border-bottom:1px solid var(--border);transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:var(--s2)}
tbody td{padding:13px 16px;font-size:13px}

.role-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.role-admin{background:rgba(79,110,247,.12);color:var(--accent)}
.role-member{background:rgba(14,165,233,.1);color:#0ea5e9}
.status-dot{display:inline-flex;align-items:center;gap:6px;font-size:12px}
.dot{width:6px;height:6px;border-radius:50%}
.dot-active{background:var(--success)}.dot-inactive{background:var(--error)}

.action-btns{display:flex;gap:6px;flex-wrap:wrap}
.me-badge{font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(79,110,247,.12);color:var(--accent);font-weight:600;margin-left:4px}

/* Modal */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:1000;display:none;align-items:center;justify-content:center}
.ov.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;width:420px;max-width:90vw;box-shadow:0 24px 80px rgba(0,0,0,.6);animation:mUp .25s cubic-bezier(.16,1,.3,1) both}
@keyframes mUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
.modal h2{font-family:'Instrument Serif',serif;font-size:20px;margin-bottom:6px}
.modal-desc{font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.5}
.fld{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
.fld label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.fld input,.fld select{font-family:'Geist',sans-serif;font-size:13px;padding:10px 12px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
.fld input:focus,.fld select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,110,247,.12)}
.fld select option{background:var(--s2)}
.mf{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}
</style>
</head>
<body>
<div class="topbar">
  <a class="tb-logo" href="/dashboard.php">
    <div class="tb-logo-icon"><i class="fa fa-toolbox"></i></div>
    <div><div class="tb-logo-name">VV ToolBox</div><div class="tb-logo-sub">Espace de travail</div></div>
  </a>
  <div class="tb-center">
    <div class="tb-breadcrumb">
      <a href="/dashboard.php">Dashboard</a>
      <span class="sep"><i class="fa fa-chevron-right" style="font-size:10px"></i></span>
      <span>Membres</span>
    </div>
  </div>
  <div class="tb-right">
    <button class="tb-btn" id="themeBtn" aria-label="Thème"><i class="fa fa-sun" id="themeIco"></i></button>
  </div>
</div>

<div class="layout">
  <nav class="nav">
    <div class="nav-section">
      <div class="nav-label">Navigation</div>
      <a class="nav-item" href="/dashboard.php"><i class="fa fa-house"></i> Dashboard</a>
    </div>
    <div class="nav-section">
      <div class="nav-label">Outils</div>
      <a class="nav-item" href="/tools/qr.php"><i class="fa fa-qrcode"></i> QR Code</a>
      <a class="nav-item" href="/tools/signature.php"><i class="fa fa-envelope"></i> Signature mail</a>
      <a class="nav-item" href="/tools/vcard.php"><i class="fa fa-id-card"></i> Carte de visite</a>
    </div>
    <div class="nav-sep"></div>
    <div class="nav-section">
      <div class="nav-label">Administration</div>
      <a class="nav-item active" href="/admin/users.php"><i class="fa fa-users"></i> Membres <span class="nav-badge"><?= count($members) ?></span></a>
      <a class="nav-item" href="/admin/settings.php"><i class="fa fa-gear"></i> Paramètres</a>
    </div>
    <div class="nav-sep"></div>
    <a class="nav-item" href="/logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Déconnexion</a>
  </nav>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="ph-title">Gestion des membres</h1>
        <p class="ph-sub"><?= count($members) ?> compte<?= count($members) > 1 ? 's' : '' ?> enregistré<?= count($members) > 1 ? 's' : '' ?></p>
      </div>
      <button class="btn btn-primary" onclick="document.getElementById('modalCreate').classList.add('open')">
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
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalCreate').classList.remove('open')">Annuler</button>
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
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalReset').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-key"></i> Réinitialiser</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){var s=localStorage.getItem('vv_theme')||'dark';document.documentElement.setAttribute('data-theme',s);document.getElementById('themeIco').className=s==='light'?'fa fa-moon':'fa fa-sun'})();
document.getElementById('themeBtn').addEventListener('click',function(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);document.getElementById('themeIco').className=n==='light'?'fa fa-moon':'fa fa-sun';localStorage.setItem('vv_theme',n)});

// Close overlays on bg click
document.querySelectorAll('.ov').forEach(function(el){el.addEventListener('click',function(e){if(e.target===el)el.classList.remove('open')})});

function openReset(id, name) {
  document.getElementById('resetTargetId').value = id;
  document.getElementById('resetDesc').textContent = 'Nouveau mot de passe pour ' + name;
  document.getElementById('modalReset').classList.add('open');
}
</script>
</body>
</html>
