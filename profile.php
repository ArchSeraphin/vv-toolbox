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
$me = $db->prepare('SELECT * FROM users WHERE id = ?');
$me->execute([$user['id']]);
$me = $me->fetch();

// ── LAYOUT CONFIG ──────────────────────────────────────────
$navActive  = 'profile';
$breadcrumb = [['Mon profil', null]];
$tbActions  = '';
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
<link rel="stylesheet" href="/assets/layout.css">
<style>
.main { flex:1;overflow-y:auto;padding:32px;display:flex;flex-direction:column;gap:20px;align-items:flex-start; }
.main::-webkit-scrollbar { width:6px; }
.main::-webkit-scrollbar-thumb { background:var(--border);border-radius:4px; }
.page-title { font-family:'Instrument Serif',serif;font-size:26px;letter-spacing:-.4px;margin-bottom:4px; }
.page-sub { font-size:13px;color:var(--muted); }
.card { background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;width:100%;max-width:520px; }
.card-title { font-size:14px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px; }
.card-title i { color:var(--accent);font-size:13px; }
.card-desc { font-size:12px;color:var(--muted);margin-bottom:20px;line-height:1.5; }
.info-row { display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px; }
.info-row:last-child { border-bottom:none; }
.info-label { color:var(--muted); }
.info-value { font-weight:500; }
.role-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px; }
.role-admin { background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent); }
.role-member { background:rgba(14,165,233,.1);color:#0ea5e9; }
.alert { display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:4px; }
.alert.success { background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.25);color:var(--success); }
.alert.error { background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);color:var(--error); }
.input-wrap { position:relative; }
.eye-btn { position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--dim);font-size:13px;padding:4px;transition:color .15s; }
.eye-btn:hover { color:var(--muted); }
.strength-bar { height:4px;border-radius:4px;background:var(--border);margin-top:6px;overflow:hidden; }
.strength-fill { height:100%;border-radius:4px;transition:width .3s,background .3s;width:0; }
.strength-label { font-size:11px;color:var(--dim);margin-top:4px; }
</style>
</head>
<body>

<?php require __DIR__ . '/includes/topbar.php'; ?>

<div class="layout">

  <?php require __DIR__ . '/includes/nav.php'; ?>

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
        <div class="fld" style="margin-bottom:18px"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($me['email']) ?>" required></div>
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
        <div class="fld" style="margin-bottom:18px">
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

<script src="/assets/layout.js"></script>
<script>
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
    {w:'0%',  c:'transparent', t:''},
    {w:'25%', c:'#f87171',     t:'Très faible'},
    {w:'50%', c:'#fbbf24',     t:'Faible'},
    {w:'75%', c:'#60a5fa',     t:'Correct'},
    {w:'90%', c:'#34d399',     t:'Fort'},
    {w:'100%',c:'#34d399',     t:'Très fort'},
  ];
  var l = levels[Math.min(score, 5)];
  fill.style.width = l.w; fill.style.background = l.c;
  lbl.textContent = l.t; lbl.style.color = l.c;
}

document.getElementById('pwdForm').addEventListener('submit', function(e) {
  var np = document.getElementById('newPwd').value;
  var cp = document.getElementById('cnfPwd').value;
  if (np !== cp) { e.preventDefault(); alert('Les mots de passe ne correspondent pas.'); }
});
</script>
</body>
</html>
