<?php
/**
 * VV ToolBox — Page de connexion
 */
require_once __DIR__ . '/auth/session.php';

// Déjà connecté → dashboard
if (isLoggedIn()) {
    checkSessionExpiry();
    header('Location: /dashboard.php');
    exit;
}

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VV ToolBox — Connexion</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ── RESET & BASE ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #080809;
  --surface:   #0f0f11;
  --s2:        #17171a;
  --s3:        #1e1e23;
  --border:    #26262d;
  --border2:   #32323c;
  --accent:    #4f6ef7;
  --accent-h:  #6a85ff;
  --accent-g:  rgba(79,110,247,.12);
  --text:      #eeeef2;
  --muted:     #7a7a8f;
  --dim:       #3a3a48;
  --success:   #34d399;
  --error:     #f87171;
  --r:         14px;
}
[data-theme="light"] {
  --bg:        #f4f4f7;
  --surface:   #ffffff;
  --s2:        #f8f8fb;
  --s3:        #f0f0f5;
  --border:    #e2e2ec;
  --border2:   #d0d0de;
  --accent-g:  rgba(79,110,247,.08);
  --text:      #0d0d14;
  --muted:     #6b6b80;
  --dim:       #c0c0d0;
}

body {
  font-family: 'Geist', system-ui, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}

/* ── BACKGROUND AMBIANCE ────────────────────────────────── */
.bg-fx {
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  overflow: hidden;
}
.bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: .18;
  animation: drift 18s ease-in-out infinite alternate;
}
.bg-orb-1 {
  width: 600px; height: 600px;
  background: radial-gradient(circle, #4f6ef7, transparent 70%);
  top: -200px; left: -150px;
  animation-delay: 0s;
}
.bg-orb-2 {
  width: 400px; height: 400px;
  background: radial-gradient(circle, #7c3aed, transparent 70%);
  bottom: -100px; right: -100px;
  animation-delay: -7s;
}
.bg-orb-3 {
  width: 300px; height: 300px;
  background: radial-gradient(circle, #0ea5e9, transparent 70%);
  top: 40%; left: 60%;
  animation-delay: -12s;
}
.bg-grid {
  position: absolute; inset: 0;
  background-image:
    linear-gradient(var(--border) 1px, transparent 1px),
    linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 48px 48px;
  opacity: .35;
  mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
}
@keyframes drift {
  from { transform: translate(0, 0) scale(1); }
  to   { transform: translate(40px, 30px) scale(1.08); }
}

/* ── CARD ───────────────────────────────────────────────── */
.card {
  position: relative; z-index: 1;
  width: 100%; max-width: 420px;
  padding: 44px 40px 40px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  box-shadow:
    0 0 0 1px var(--border),
    0 32px 64px rgba(0,0,0,.4),
    inset 0 1px 0 rgba(255,255,255,.04);
  animation: fadeUp .5s cubic-bezier(.16,1,.3,1) both;
}
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── LOGO / HEADER ──────────────────────────────────────── */
.logo-wrap {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 32px;
}
.logo-icon {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, var(--accent), #7c5cfc);
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: #fff;
  box-shadow: 0 4px 20px rgba(79,110,247,.35);
  flex-shrink: 0;
}
.logo-text { line-height: 1.1; }
.logo-name {
  font-family: 'Instrument Serif', Georgia, serif;
  font-size: 22px; letter-spacing: -.3px;
  color: var(--text);
}
.logo-sub {
  font-size: 11px; font-weight: 400;
  color: var(--muted); letter-spacing: .5px;
  text-transform: uppercase;
}

.login-title {
  font-size: 24px; font-weight: 600; letter-spacing: -.4px;
  color: var(--text); margin-bottom: 6px;
}
.login-desc {
  font-size: 13px; color: var(--muted); margin-bottom: 28px;
  line-height: 1.5;
}

/* ── FORM ───────────────────────────────────────────────── */
.field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.field label { font-size: 12px; font-weight: 500; color: var(--muted); letter-spacing: .3px; }

.input-wrap { position: relative; }
.input-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: var(--dim); font-size: 13px; pointer-events: none;
  transition: color .2s;
}
.input-wrap:focus-within .input-icon { color: var(--accent); }

input[type="email"],
input[type="password"],
input[type="text"] {
  width: 100%;
  padding: 12px 14px 12px 40px;
  background: var(--s2);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-family: 'Geist', sans-serif;
  font-size: 14px;
  outline: none;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
input:focus {
  border-color: var(--accent);
  background: var(--s3);
  box-shadow: 0 0 0 3px rgba(79,110,247,.15);
}
input::placeholder { color: var(--dim); }

/* Eye toggle */
.eye-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--dim); font-size: 13px; padding: 4px;
  transition: color .15s;
}
.eye-btn:hover { color: var(--muted); }

/* ── SUBMIT ─────────────────────────────────────────────── */
.btn-login {
  width: 100%; padding: 13px;
  background: linear-gradient(135deg, var(--accent), #6a5af9);
  color: #fff; border: none; border-radius: 10px;
  font-family: 'Geist', sans-serif; font-size: 14px; font-weight: 600;
  cursor: pointer; letter-spacing: .2px;
  transition: all .2s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 8px;
  position: relative; overflow: hidden;
}
.btn-login::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, transparent, rgba(255,255,255,.1));
  opacity: 0; transition: opacity .2s;
}
.btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(79,110,247,.4); }
.btn-login:hover::before { opacity: 1; }
.btn-login:active { transform: translateY(0); }
.btn-login:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* Spinner */
.spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.btn-login.loading .spinner { display: block; }
.btn-login.loading .btn-txt { display: none; }

/* ── ALERT ──────────────────────────────────────────────── */
.alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px; border-radius: 9px;
  font-size: 13px; line-height: 1.5;
  margin-bottom: 16px;
  animation: fadeUp .3s cubic-bezier(.16,1,.3,1) both;
  display: none;
}
.alert.error  { background: rgba(248,113,113,.08); border: 1px solid rgba(248,113,113,.25); color: var(--error); }
.alert.success{ background: rgba(52,211,153,.08);  border: 1px solid rgba(52,211,153,.25);  color: var(--success); }
.alert i { margin-top: 1px; flex-shrink: 0; }
.alert.show { display: flex; }

/* ── DIVIDER ────────────────────────────────────────────── */
.divider {
  display: flex; align-items: center; gap: 12px;
  margin: 20px 0 0;
  color: var(--dim); font-size: 11px; letter-spacing: .5px;
}
.divider::before, .divider::after {
  content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── THEME TOGGLE ───────────────────────────────────────── */
.theme-btn {
  position: fixed; top: 20px; right: 20px; z-index: 100;
  width: 38px; height: 38px; border-radius: 50%;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--muted); cursor: pointer; font-size: 14px;
  display: flex; align-items: center; justify-content: center;
  transition: all .2s; box-shadow: 0 2px 8px rgba(0,0,0,.2);
}
.theme-btn:hover { border-color: var(--accent); color: var(--accent); }

/* ── FOOTER ─────────────────────────────────────────────── */
.card-footer {
  margin-top: 24px; padding-top: 20px;
  border-top: 1px solid var(--border);
  text-align: center;
  font-size: 11px; color: var(--dim);
}

/* ── RESPONSIVE ─────────────────────────────────────────── */
@media (max-width: 480px) {
  .card { margin: 16px; padding: 32px 24px 28px; }
}
</style>
</head>
<body>

<!-- Background FX -->
<div class="bg-fx" aria-hidden="true">
  <div class="bg-grid"></div>
  <div class="bg-orb bg-orb-1"></div>
  <div class="bg-orb bg-orb-2"></div>
  <div class="bg-orb bg-orb-3"></div>
</div>

<!-- Theme toggle -->
<button class="theme-btn" id="themeBtn" title="Changer le thème" aria-label="Changer le thème">
  <i class="fa fa-sun" id="themeIco"></i>
</button>

<!-- Login card -->
<div class="card" role="main">

  <div class="logo-wrap">
    <div class="logo-icon" aria-hidden="true"><i class="fa fa-toolbox"></i></div>
    <div class="logo-text">
      <div class="logo-name">VV ToolBox</div>
      <div class="logo-sub">Espace de travail</div>
    </div>
  </div>

  <h1 class="login-title">Connexion</h1>
  <p class="login-desc">Accédez à votre espace pour créer et gérer vos outils de communication.</p>

  <!-- Alert zone -->
  <div class="alert error" id="alertErr" role="alert" aria-live="polite">
    <i class="fa fa-circle-exclamation"></i>
    <span id="alertMsg">Erreur</span>
  </div>

  <!-- Form -->
  <form id="loginForm" novalidate autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="login">

    <div class="field">
      <label for="email">Adresse email</label>
      <div class="input-wrap">
        <i class="fa fa-envelope input-icon"></i>
        <input type="email" id="email" name="email"
               placeholder="vous@exemple.fr"
               autocomplete="email" required
               autofocus>
      </div>
    </div>

    <div class="field">
      <label for="password">Mot de passe</label>
      <div class="input-wrap">
        <i class="fa fa-lock input-icon"></i>
        <input type="password" id="password" name="password"
               placeholder="••••••••"
               autocomplete="current-password" required>
        <button type="button" class="eye-btn" id="eyeBtn" aria-label="Voir le mot de passe">
          <i class="fa fa-eye" id="eyeIco"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-login" id="loginBtn">
      <span class="btn-txt"><i class="fa fa-arrow-right-to-bracket"></i> Se connecter</span>
      <span class="spinner" aria-hidden="true"></span>
    </button>
  </form>

  <div class="divider">Accès sécurisé — Session chiffrée</div>

  <div class="card-footer">
    &copy; <?= date('Y') ?> VV ToolBox &mdash; Usage interne uniquement
  </div>

</div>

<script>
// ── THEME ─────────────────────────────────────────────────
(function() {
  var saved = localStorage.getItem('vv_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  document.getElementById('themeIco').className = saved === 'light' ? 'fa fa-moon' : 'fa fa-sun';
})();

document.getElementById('themeBtn').addEventListener('click', function() {
  var cur = document.documentElement.getAttribute('data-theme');
  var next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  document.getElementById('themeIco').className = next === 'light' ? 'fa fa-moon' : 'fa fa-sun';
  localStorage.setItem('vv_theme', next);
});

// ── PASSWORD REVEAL ───────────────────────────────────────
document.getElementById('eyeBtn').addEventListener('click', function() {
  var inp = document.getElementById('password');
  var ico = document.getElementById('eyeIco');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa fa-eye-slash';
    this.setAttribute('aria-label', 'Masquer le mot de passe');
  } else {
    inp.type = 'password';
    ico.className = 'fa fa-eye';
    this.setAttribute('aria-label', 'Voir le mot de passe');
  }
});

// ── FORM SUBMIT ───────────────────────────────────────────
document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();

  var btn   = document.getElementById('loginBtn');
  var alert = document.getElementById('alertErr');
  var email = document.getElementById('email').value.trim();
  var pass  = document.getElementById('password').value;

  // Validation basique
  if (!email || !pass) {
    showError('Veuillez remplir tous les champs.');
    return;
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showError('Format d\'email invalide.');
    return;
  }

  // UI loading
  btn.classList.add('loading');
  btn.disabled = true;
  alert.classList.remove('show');

  var fd = new FormData(this);

  fetch('/api/auth.php', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.ok) {
      btn.innerHTML = '<i class="fa fa-check" style="color:#34d399"></i>';
      setTimeout(function() {
        window.location.href = '/dashboard.php';
      }, 400);
    } else {
      showError(data.error || 'Erreur de connexion.');
      btn.classList.remove('loading');
      btn.disabled = false;
    }
  })
  .catch(function() {
    showError('Erreur réseau. Veuillez réessayer.');
    btn.classList.remove('loading');
    btn.disabled = false;
  });
});

function showError(msg) {
  var el = document.getElementById('alertErr');
  document.getElementById('alertMsg').textContent = msg;
  el.classList.add('show');
  // Shake animation
  el.style.animation = 'none';
  el.offsetHeight;
  el.style.animation = '';
}

// Cacher l'alerte dès qu'on retape
document.getElementById('email').addEventListener('input', hideAlert);
document.getElementById('password').addEventListener('input', hideAlert);
function hideAlert() {
  document.getElementById('alertErr').classList.remove('show');
}
</script>
</body>
</html>
