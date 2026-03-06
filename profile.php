<?php
/**
 * VV ToolBox — Mon profil (changement mot de passe)
 */
require_once __DIR__ . '/auth/session.php';
requireLogin();
checkSessionExpiry();

$user = currentUser();
$db   = getDB();
$msg  = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Token invalide.'; $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            // Récupérer le hash actuel
            $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $row->execute([$user['id']]);
            $row = $row->fetch();

            if (!$row || !password_verify($current, $row['password_hash'])) {
                $msg = 'Mot de passe actuel incorrect.'; $msgType = 'error';
            } elseif (strlen($new) < 8) {
                $msg = 'Le nouveau mot de passe doit faire au moins 8 caractères.'; $msgType = 'error';
            } elseif ($new !== $confirm) {
                $msg = 'Les mots de passe ne correspondent pas.'; $msgType = 'error';
            } else {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
                $msg = 'Mot de passe changé avec succès !'; $msgType = 'success';
            }
        }

        if ($action === 'update_profile') {
            $username = trim($_POST['username'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            if (!$username || !$email) {
                $msg = 'Champs obligatoires.'; $msgType = 'error';
            } else {
                try {
                    $db->prepare('UPDATE users SET username=?, email=? WHERE id=?')
                       ->execute([$username, $email, $user['id']]);
                    // Mettre à jour la session
                    $_SESSION['user_username'] = $username;
                    $_SESSION['user_email']    = $email;
                    $user = currentUser();
                    $msg = 'Profil mis à jour.'; $msgType = 'success';
                } catch (PDOException $e) {
                    $msg = 'Email ou nom déjà utilisé.'; $msgType = 'error';
                }
            }
        }
    }
}

$csrf = getCsrfToken();
// Données fraîches
$me = $db->prepare('SELECT * FROM users WHERE id = ?');
$me->execute([$user['id']]);
$me = $me->fetch();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon profil — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#080809;--surface:#0f0f11;--s2:#17171a;--s3:#1e1e23;--border:#26262d;--accent:#4f6ef7;--text:#eeeef2;--muted:#7a7a8f;--dim:#3a3a48;--success:#34d399;--error:#f87171;--nav-w:240px;--topbar-h:58px}
[data-theme="light"]{--bg:#f1f1f5;--surface:#fff;--s2:#f7f7fb;--s3:#eeeef5;--border:#e0e0ea;--text:#0d0d14;--muted:#6b6b80;--dim:#b8b8cc}
html,body{height:100%}
body{font-family:'Geist',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;font-size:14px;overflow:hidden}

.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px 0 0;flex-shrink:0}
.tb-logo{width:var(--nav-w);display:flex;align-items:center;gap:11px;padding:0 20px;border-right:1px solid var(--border);height:100%;flex-shrink:0;text-decoration:none;color:var(--text)}
.tb-logo-icon{width:34px;height:34px;background:linear-gradient(135deg,var(--accent),#7c5cfc);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;flex-shrink:0}
.tb-logo-name{font-family:'Instrument Serif',serif;font-size:18px;letter-spacing:-.2px}
.tb-logo-sub{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.tb-center{flex:1;padding:0 24px}
.tb-breadcrumb{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:8px}
.tb-breadcrumb a{color:var(--muted);text-decoration:none}.tb-breadcrumb a:hover{color:var(--text)}
.tb-right{display:flex;align-items:center;gap:8px}
.tb-btn{width:36px;height:36px;border-radius:9px;background:transparent;border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.tb-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--s2)}

.layout{display:flex;flex:1;overflow:hidden}
.nav{width:var(--nav-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;padding:16px 10px;overflow-y:auto}
.nav-section{margin-bottom:20px}.nav-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--dim);padding:0 10px;margin-bottom:6px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:9px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;border:1px solid transparent;transition:all .12s}
.nav-item:hover{background:var(--s2);color:var(--text)}
.nav-item.active{background:rgba(79,110,247,.1);border-color:rgba(79,110,247,.2);color:var(--accent)}
.nav-item i{width:16px;text-align:center;font-size:13px;flex-shrink:0}
.nav-sep{height:1px;background:var(--border);margin:8px 4px}

.main{flex:1;overflow-y:auto;padding:32px;display:flex;flex-direction:column;gap:20px;align-items:flex-start}
.main::-webkit-scrollbar{width:6px}.main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.page-title{font-family:'Instrument Serif',serif;font-size:26px;letter-spacing:-.4px;margin-bottom:4px}
.page-sub{font-size:13px;color:var(--muted)}

.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;width:100%;max-width:520px}
.card-title{font-size:14px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.card-title i{color:var(--accent);font-size:13px}
.card-desc{font-size:12px;color:var(--muted);margin-bottom:20px;line-height:1.5}

.fld{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.fld label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.fld input{font-family:'Geist',sans-serif;font-size:13px;padding:10px 12px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
.fld input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,110,247,.12)}

.input-wrap{position:relative}
.eye-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--dim);font-size:13px;padding:4px;transition:color .15s}
.eye-btn:hover{color:var(--muted)}

.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9px;border:none;font-family:'Geist',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s}
.btn-primary{background:linear-gradient(135deg,var(--accent),#6a5af9);color:#fff;box-shadow:0 2px 12px rgba(79,110,247,.3)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,110,247,.4)}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--s2);color:var(--text)}

.alert{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:16px}
.alert.success{background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);color:var(--success)}
.alert.error{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:var(--error)}

.info-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--muted)}
.info-value{font-weight:500}
.role-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.role-admin{background:rgba(79,110,247,.12);color:var(--accent)}
.role-member{background:rgba(14,165,233,.1);color:#0ea5e9}

.strength-bar{height:4px;border-radius:4px;background:var(--border);margin-top:6px;overflow:hidden}
.strength-fill{height:100%;border-radius:4px;transition:width .3s,background .3s;width:0}
.strength-label{font-size:11px;color:var(--dim);margin-top:4px}
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
      <span style="color:var(--dim);font-size:10px"><i class="fa fa-chevron-right"></i></span>
      <span style="color:var(--text)">Mon profil</span>
    </div>
  </div>
  <div class="tb-right">
    <button class="tb-btn" id="themeBtn"><i class="fa fa-sun" id="themeIco"></i></button>
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
    <?php if (isAdmin()): ?>
    <a class="nav-item" href="/admin/users.php"><i class="fa fa-users"></i> Membres</a>
    <?php endif; ?>
    <a class="nav-item active" href="/profile.php"><i class="fa fa-user-pen"></i> Mon profil</a>
    <div class="nav-sep"></div>
    <a class="nav-item" href="/logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Déconnexion</a>
  </nav>

  <main class="main">
    <div>
      <div class="page-title">Mon profil</div>
      <div class="page-sub">Gérez vos informations et votre mot de passe.</div>
    </div>

    <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>" style="max-width:520px;width:100%">
      <i class="fa <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Infos compte -->
    <div class="card">
      <div class="card-title"><i class="fa fa-circle-info"></i> Informations du compte</div>
      <div class="card-desc">Vos informations de connexion actuelles.</div>
      <div class="info-row"><span class="info-label">Identifiant</span><span class="info-value"><?= htmlspecialchars($me['username']) ?></span></div>
      <div class="info-row"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($me['email']) ?></span></div>
      <div class="info-row"><span class="info-label">Rôle</span><span class="role-badge role-<?= $me['role'] ?>"><?= $me['role'] === 'admin' ? 'Administrateur' : 'Membre' ?></span></div>
      <div class="info-row"><span class="info-label">Dernière connexion</span><span class="info-value" style="color:var(--muted);font-size:12px"><?= $me['last_login'] ? date('d/m/Y à H:i', strtotime($me['last_login'])) : '—' ?></span></div>
      <div class="info-row"><span class="info-label">Membre depuis</span><span class="info-value" style="color:var(--muted);font-size:12px"><?= date('d/m/Y', strtotime($me['created_at'])) ?></span></div>
    </div>

    <!-- Modifier infos -->
    <div class="card">
      <div class="card-title"><i class="fa fa-user-pen"></i> Modifier mes informations</div>
      <div class="card-desc">Modifiez votre nom d'utilisateur ou votre adresse email.</div>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="fld"><label>Nom d'utilisateur</label><input type="text" name="username" value="<?= htmlspecialchars($me['username']) ?>" required></div>
        <div class="fld"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>" required></div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Enregistrer</button>
      </form>
    </div>

    <!-- Changer mot de passe -->
    <div class="card">
      <div class="card-title"><i class="fa fa-lock"></i> Changer mon mot de passe</div>
      <div class="card-desc">Choisissez un mot de passe fort d'au moins 8 caractères.</div>
      <form method="post" id="pwdForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="fld">
          <label>Mot de passe actuel</label>
          <div class="input-wrap">
            <input type="password" name="current_password" id="curPwd" required>
            <button type="button" class="eye-btn" onclick="togglePwd('curPwd',this)"><i class="fa fa-eye"></i></button>
          </div>
        </div>
        <div class="fld">
          <label>Nouveau mot de passe</label>
          <div class="input-wrap">
            <input type="password" name="new_password" id="newPwd" required minlength="8" oninput="checkStrength(this.value)">
            <button type="button" class="eye-btn" onclick="togglePwd('newPwd',this)"><i class="fa fa-eye"></i></button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
          <div class="strength-label" id="strengthLabel"></div>
        </div>
        <div class="fld">
          <label>Confirmer le nouveau mot de passe</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="cnfPwd" required minlength="8">
            <button type="button" class="eye-btn" onclick="togglePwd('cnfPwd',this)"><i class="fa fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa fa-lock"></i> Changer le mot de passe</button>
      </form>
    </div>

  </main>
</div>

<script>
(function(){var s=localStorage.getItem('vv_theme')||'dark';document.documentElement.setAttribute('data-theme',s);document.getElementById('themeIco').className=s==='light'?'fa fa-moon':'fa fa-sun'})();
document.getElementById('themeBtn').addEventListener('click',function(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);document.getElementById('themeIco').className=n==='light'?'fa fa-moon':'fa fa-sun';localStorage.setItem('vv_theme',n)});

function togglePwd(id, btn) {
  var inp = document.getElementById(id);
  var ico = btn.querySelector('i');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  ico.className = inp.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

function checkStrength(val) {
  var fill = document.getElementById('strengthFill');
  var lbl  = document.getElementById('strengthLabel');
  var score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^a-zA-Z0-9]/.test(val)) score++;
  var levels = [
    {w:'0%',c:'transparent',t:''},
    {w:'25%',c:'#f87171',t:'Très faible'},
    {w:'50%',c:'#fbbf24',t:'Faible'},
    {w:'75%',c:'#60a5fa',t:'Correct'},
    {w:'90%',c:'#34d399',t:'Fort'},
    {w:'100%',c:'#34d399',t:'Très fort'},
  ];
  var l = levels[Math.min(score, 5)];
  fill.style.width = l.w; fill.style.background = l.c;
  lbl.textContent = l.t;
  lbl.style.color = l.c;
}

document.getElementById('pwdForm').addEventListener('submit', function(e) {
  var np = document.getElementById('newPwd').value;
  var cp = document.getElementById('cnfPwd').value;
  if (np !== cp) {
    e.preventDefault();
    alert('Les mots de passe ne correspondent pas.');
  }
});
</script>
</body>
</html>
