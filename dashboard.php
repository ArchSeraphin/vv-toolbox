<?php
/**
 * VV ToolBox — Dashboard principal
 */
require_once __DIR__ . '/auth/session.php';
requireLogin();
checkSessionExpiry();

$user = currentUser();
$db   = getDB();

// ── STATS ──────────────────────────────────────────────────
$uid = $user['id'];
$isAdm = isAdmin();

// Pour admin : stats globales / pour member : stats personnelles
$where = $isAdm ? '' : 'WHERE user_id = :uid';
$bind  = $isAdm ? [] : [':uid' => $uid];

function countTable(PDO $db, string $table, bool $isAdm, int $uid): int {
    $sql = $isAdm
        ? "SELECT COUNT(*) FROM $table"
        : "SELECT COUNT(*) FROM $table WHERE user_id = :uid";
    $s = $db->prepare($sql);
    if (!$isAdm) $s->bindValue(':uid', $uid, PDO::PARAM_INT);
    $s->execute();
    return (int) $s->fetchColumn();
}

$stats = [
    'qr'  => countTable($db, 'qr_codes',         $isAdm, $uid),
    'sig' => countTable($db, 'email_signatures',  $isAdm, $uid),
    'vc'  => countTable($db, 'vcards',            $isAdm, $uid),
];
$stats['total'] = $stats['qr'] + $stats['sig'] + $stats['vc'];

// ── DERNIÈRES CRÉATIONS ────────────────────────────────────
function recentItems(PDO $db, string $table, string $type, bool $isAdm, int $uid, int $limit = 4): array {
    $sql = "SELECT t.id, t.name, t.created_at, t.updated_at, t.user_id, u.username AS author FROM $table t LEFT JOIN users u ON u.id = t.user_id";
    if (!$isAdm) $sql .= " WHERE t.user_id = :uid";
    $sql .= " ORDER BY t.updated_at DESC LIMIT :lim";
    $s = $db->prepare($sql);
    if (!$isAdm) $s->bindValue(':uid', $uid, PDO::PARAM_INT);
    $s->bindValue(':lim', $limit, PDO::PARAM_INT);
    $s->execute();
    $rows = $s->fetchAll();
    foreach ($rows as &$r) { $r['type'] = $type; }
    return $rows;
}

$recentQR  = recentItems($db, 'qr_codes',        'qr',  $isAdm, $uid);
$recentSig = recentItems($db, 'email_signatures', 'sig', $isAdm, $uid);
$recentVC  = recentItems($db, 'vcards',           'vc',  $isAdm, $uid);

// Fusionner et trier par updated_at
$allRecent = array_merge($recentQR, $recentSig, $recentVC);
usort($allRecent, fn($a,$b) => strcmp($b['updated_at'], $a['updated_at']));
$allRecent = array_slice($allRecent, 0, 8);

// ── MEMBRES (admin only) ───────────────────────────────────
$members = [];
if ($isAdm) {
    $members = $db->query(
        'SELECT id, username, email, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();
}

// ── HELPERS ───────────────────────────────────────────────
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'À l\'instant';
    if ($diff < 3600)   return round($diff/60) . ' min';
    if ($diff < 86400)  return round($diff/3600) . 'h';
    if ($diff < 604800) return round($diff/86400) . 'j';
    return date('d/m/Y', strtotime($dt));
}

$typeInfo = [
    'qr'  => ['icon'=>'fa-qrcode',    'label'=>'QR Code',    'color'=>'#4f6ef7', 'url'=>'/tools/qr.php'],
    'sig' => ['icon'=>'fa-envelope',  'label'=>'Signature',  'color'=>'#0ea5e9', 'url'=>'/tools/signature.php'],
    'vc'  => ['icon'=>'fa-id-card',   'label'=>'Carte',      'color'=>'#8b5cf6', 'url'=>'/tools/vcard.php'],
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/layout.css">
<style>
/* ── PAGE Dashboard : surcharges spécifiques ─── */
:root {
  --qr:       #4f6ef7;
  --sig:      #0ea5e9;
  --vc:       #8b5cf6;
  --warn:     #fbbf24;
  --danger:   #f87171;
}

/* Search in topbar */
.tb-center { padding: 0 24px; }
.tb-search {
  display: flex; align-items: center; gap: 9px;
  background: var(--s2); border: 1px solid var(--border);
  border-radius: 9px; padding: 8px 14px;
  width: 100%; max-width: 360px;
  transition: border-color .15s, box-shadow .15s;
}
.tb-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb,var(--accent) 10%,transparent); }
.tb-search i { color: var(--dim); font-size: 12px; flex-shrink: 0; }
.tb-search input { border: none; background: transparent; color: var(--text); font-family: 'Geist', sans-serif; font-size: 13px; outline: none; flex: 1; }
.tb-search input::placeholder { color: var(--dim); }

/* Avatar */
.avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #7c5cfc);
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 600; color: #fff;
  cursor: pointer; flex-shrink: 0;
  border: 2px solid transparent; transition: border-color .15s;
  position: relative;
}
.avatar:hover { border-color: var(--accent); }
.av-dropdown {
  position: absolute; top: calc(100% + 8px); right: 0;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 12px; padding: 6px; min-width: 200px;
  box-shadow: 0 16px 40px rgba(0,0,0,.4);
  display: none; z-index: 200;
  animation: fadeDown .2s cubic-bezier(.16,1,.3,1) both;
}
@keyframes fadeDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
.av-dropdown.open { display: block; }
.av-head { padding: 10px 12px 8px; border-bottom: 1px solid var(--border); margin-bottom: 4px; }
.av-name { font-weight: 600; font-size: 13px; }
.av-email { font-size: 11px; color: var(--muted); margin-top: 1px; }
.av-role { display: inline-flex; align-items: center; gap: 4px; margin-top: 5px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; padding: 2px 7px; border-radius: 20px; }
.av-role.admin  { background: color-mix(in srgb,var(--accent) 15%,transparent); color: var(--accent); }
.av-role.member { background: color-mix(in srgb,var(--sig) 12%,transparent); color: var(--sig); }
.av-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px; cursor: pointer; font-size: 13px; color: var(--muted); text-decoration: none; transition: all .12s; }
.av-item:hover { background: var(--s2); color: var(--text); }
.av-item.danger:hover { background: rgba(248,113,113,.08); color: var(--danger); }
.av-item i { width: 16px; text-align: center; font-size: 13px; }

/* Nav tool-specific active states */
.nav-item.tool-qr.active  { background: color-mix(in srgb,var(--qr) 10%,transparent);  border-color: color-mix(in srgb,var(--qr) 20%,transparent);  color: var(--qr); }
.nav-item.tool-sig.active { background: color-mix(in srgb,var(--sig) 10%,transparent); border-color: color-mix(in srgb,var(--sig) 20%,transparent); color: var(--sig); }
.nav-item.tool-vc.active  { background: color-mix(in srgb,var(--vc) 10%,transparent);  border-color: color-mix(in srgb,var(--vc) 20%,transparent);  color: var(--vc); }
.nav-item.active .nav-badge { background: color-mix(in srgb,var(--accent) 20%,transparent); color: var(--accent); }

/* Main content */
.main { flex: 1; overflow-y: auto; padding: 28px 32px 40px; display: flex; flex-direction: column; gap: 28px; }
.main::-webkit-scrollbar { width: 6px; }
.main::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

/* Page header */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.ph-title { font-family: 'Instrument Serif', Georgia, serif; font-size: 28px; letter-spacing: -.4px; line-height: 1.1; }
.ph-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }
.ph-actions { display: flex; gap: 8px; flex-shrink: 0; }

/* Stat cards */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; position: relative; overflow: hidden; transition: border-color .2s, transform .2s; cursor: default; }
.stat-card:hover { border-color: var(--border2); transform: translateY(-1px); }
.stat-card::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, var(--c, transparent) 0%, transparent 60%); opacity: .06; pointer-events: none; }
.stat-card.c-total { --c: #e2e2ff; }
.stat-card.c-qr    { --c: var(--qr); }
.stat-card.c-sig   { --c: var(--sig); }
.stat-card.c-vc    { --c: var(--vc); }
.stat-icon { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 14px; }
.stat-card.c-total .stat-icon { background: rgba(226,226,255,.1); color: #a5b4fc; }
.stat-card.c-qr    .stat-icon { background: color-mix(in srgb,var(--qr)  12%,transparent); color: var(--qr); }
.stat-card.c-sig   .stat-icon { background: color-mix(in srgb,var(--sig) 12%,transparent); color: var(--sig); }
.stat-card.c-vc    .stat-icon { background: color-mix(in srgb,var(--vc)  12%,transparent); color: var(--vc); }
.stat-value { font-size: 32px; font-weight: 700; line-height: 1; letter-spacing: -1px; margin-bottom: 4px; font-variant-numeric: tabular-nums; }
.stat-label { font-size: 12px; color: var(--muted); }

/* Tool cards */
.tools-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.tool-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 12px; transition: all .2s; position: relative; overflow: hidden; }
.tool-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--tool-c, var(--accent)); transform: scaleX(0); transform-origin: left; transition: transform .3s cubic-bezier(.16,1,.3,1); }
.tool-card:hover { border-color: var(--tool-c, var(--accent)); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,.2); }
.tool-card:hover::after { transform: scaleX(1); }
.tool-card.tc-qr  { --tool-c: var(--qr); }
.tool-card.tc-sig { --tool-c: var(--sig); }
.tool-card.tc-vc  { --tool-c: var(--vc); }
.tool-top { display: flex; align-items: flex-start; justify-content: space-between; }
.tool-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.tc-qr  .tool-icon { background: color-mix(in srgb,var(--qr)  12%,transparent); color: var(--qr); }
.tc-sig .tool-icon { background: color-mix(in srgb,var(--sig) 12%,transparent); color: var(--sig); }
.tc-vc  .tool-icon { background: color-mix(in srgb,var(--vc)  12%,transparent); color: var(--vc); }
.tool-arrow { width: 28px; height: 28px; border-radius: 7px; background: var(--s2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 11px; color: var(--dim); transition: all .2s; }
.tool-card:hover .tool-arrow { background: var(--tool-c, var(--accent)); border-color: transparent; color: #fff; }
.tool-name { font-size: 15px; font-weight: 600; letter-spacing: -.2px; }
.tool-desc { font-size: 12px; color: var(--muted); line-height: 1.5; }
.tool-count { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: var(--s2); color: var(--muted); align-self: flex-start; border: 1px solid var(--border); }

/* Recent table */
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.section-title { font-size: 14px; font-weight: 600; letter-spacing: -.2px; }
.section-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
.table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead th { padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--dim); background: var(--s2); border-bottom: 1px solid var(--border); }
tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--s2); }
tbody td { padding: 13px 16px; font-size: 13px; }
.type-badge { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.type-qr  { background: color-mix(in srgb,var(--qr)  12%,transparent); color: var(--qr); }
.type-sig { background: color-mix(in srgb,var(--sig) 12%,transparent); color: var(--sig); }
.type-vc  { background: color-mix(in srgb,var(--vc)  12%,transparent); color: var(--vc); }
.item-name { font-weight: 500; }
.item-time { color: var(--muted); }
.act-link { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 7px; font-size: 11px; font-weight: 500; background: var(--s3); border: 1px solid var(--border); color: var(--muted); text-decoration: none; transition: all .12s; }
.act-link:hover { border-color: var(--accent); color: var(--accent); background: color-mix(in srgb,var(--accent) 8%,transparent); }
.empty-state { padding: 40px; text-align: center; color: var(--dim); font-size: 13px; }
.empty-state i { font-size: 28px; margin-bottom: 10px; display: block; opacity: .4; }

/* Members table */
.role-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.role-admin  { background: color-mix(in srgb,var(--accent) 12%,transparent); color: var(--accent); }
.role-member { background: color-mix(in srgb,var(--sig)    10%,transparent); color: var(--sig); }
.status-dot { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; }
.dot { width: 6px; height: 6px; border-radius: 50%; }
.dot-active   { background: var(--success); }
.dot-inactive { background: var(--danger); }

/* Responsive */
@media (max-width: 1100px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .tools-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 800px) {
  .nav { display: none; }
  .main { padding: 20px 16px 32px; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .tools-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ── TOPBAR ── -->
<div class="topbar">
  <div class="tb-logo">
    <div class="tb-logo-icon" aria-hidden="true"><i class="fa fa-toolbox"></i></div>
    <div class="tb-logo-text">
      <div class="tb-logo-name">VV ToolBox</div>
      <div class="tb-logo-sub">Espace de travail</div>
    </div>
  </div>
  <div class="tb-center">
    <div class="tb-search">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" placeholder="Rechercher une création…" id="searchInput" autocomplete="off">
    </div>
  </div>
  <div class="tb-right">
    <button class="tb-btn" onclick="toggleTheme()" title="Thème" aria-label="Changer le thème">
      <i class="fa fa-sun" id="themeIco"></i>
    </button>
    <div class="avatar" id="avatarBtn" title="Mon compte" role="button" tabindex="0"
         aria-haspopup="true" aria-expanded="false">
      <?= strtoupper(mb_substr($user['username'], 0, 1)) ?>
      <div class="av-dropdown" id="avMenu" role="menu">
        <div class="av-head">
          <div class="av-name"><?= htmlspecialchars($user['username']) ?></div>
          <div class="av-email"><?= htmlspecialchars($user['email']) ?></div>
          <span class="av-role <?= $user['role'] ?>">
            <i class="fa <?= $isAdm ? 'fa-shield-halved' : 'fa-user' ?>"></i>
            <?= $user['role'] === 'admin' ? 'Administrateur' : 'Membre' ?>
          </span>
        </div>
        <?php if ($isAdm): ?>
        <a class="av-item" href="/admin/users.php"><i class="fa fa-users"></i> Gestion membres</a>
        <a class="av-item" href="/admin/settings.php"><i class="fa fa-gear"></i> Paramètres</a>
        <?php endif; ?>
        <a class="av-item" href="/profile.php"><i class="fa fa-user-pen"></i> Mon profil</a>
        <div style="height:1px;background:var(--border);margin:4px 0"></div>
        <a class="av-item danger" href="/logout.php"><i class="fa fa-arrow-right-from-bracket"></i> Déconnexion</a>
      </div>
    </div>
  </div>
</div>

<!-- ── LAYOUT ── -->
<div class="layout">

  <!-- Sidebar nav -->
  <nav class="nav" aria-label="Navigation principale">
    <div class="nav-body">
      <div class="nav-section">
        <div class="nav-label">Navigation</div>
        <a class="nav-item active" href="/dashboard.php" data-tip="Dashboard">
          <i class="fa fa-house"></i><span class="nav-item-label"> Dashboard</span>
          <span class="nav-tip">Dashboard</span>
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-label">Outils</div>
        <a class="nav-item tool-qr" href="/tools/qr.php" data-tip="QR Code">
          <i class="fa fa-qrcode"></i><span class="nav-item-label"> QR Code</span>
          <span class="nav-badge"><?= $stats['qr'] ?></span>
          <span class="nav-tip">QR Code</span>
        </a>
        <a class="nav-item tool-sig" href="/tools/signature.php" data-tip="Signature mail">
          <i class="fa fa-envelope"></i><span class="nav-item-label"> Signature mail</span>
          <span class="nav-badge"><?= $stats['sig'] ?></span>
          <span class="nav-tip">Signature mail</span>
        </a>
        <a class="nav-item tool-vc" href="/tools/vcard.php" data-tip="Carte de visite">
          <i class="fa fa-id-card"></i><span class="nav-item-label"> Carte de visite</span>
          <span class="nav-badge"><?= $stats['vc'] ?></span>
          <span class="nav-tip">Carte de visite</span>
        </a>
      </div>

      <?php if ($isAdm): ?>
      <div class="nav-sep"></div>
      <div class="nav-section">
        <div class="nav-label">Administration</div>
        <a class="nav-item" href="/admin/users.php" data-tip="Membres">
          <i class="fa fa-users"></i><span class="nav-item-label"> Membres</span>
          <span class="nav-badge"><?= count($members) ?></span>
          <span class="nav-tip">Membres</span>
        </a>
      </div>
      <?php endif; ?>

      <div class="nav-sep"></div>
      <a class="nav-item" href="/profile.php" data-tip="Mon profil">
        <i class="fa fa-user-pen"></i><span class="nav-item-label"> Mon profil</span>
        <span class="nav-tip">Mon profil</span>
      </a>
      <a class="nav-item" href="/logout.php" data-tip="Déconnexion">
        <i class="fa fa-arrow-right-from-bracket"></i><span class="nav-item-label"> Déconnexion</span>
        <span class="nav-tip">Déconnexion</span>
      </a>
    </div><!-- nav-body -->

    <div class="nav-footer">
      <button class="nav-toggle" onclick="toggleNav()">
        <i class="fa fa-chevron-left" id="navToggleIco"></i>
        <span class="nav-item-label nav-toggle-label" id="navToggleLbl">Réduire</span>
      </button>
    </div>
  </nav>

  <!-- Main -->
  <main class="main" id="mainContent">

    <!-- Page header -->
    <div class="page-header">
      <div>
        <h1 class="ph-title">Bonjour, <?= htmlspecialchars($user['username']) ?> 👋</h1>
        <p class="ph-sub"><?= $isAdm ? 'Vue administrateur — toutes les créations de l\'équipe.' : 'Vos créations et outils disponibles.' ?></p>
      </div>
      <div class="ph-actions">
        <button class="btn btn-ghost btn-sm" onclick="location.href='/tools/qr.php'">
          <i class="fa fa-plus"></i> Nouvelle création
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card c-total">
        <div class="stat-icon"><i class="fa fa-layer-group"></i></div>
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total créations</div>
      </div>
      <div class="stat-card c-qr" style="cursor:pointer" onclick="location.href='/tools/qr.php'">
        <div class="stat-icon"><i class="fa fa-qrcode"></i></div>
        <div class="stat-value"><?= $stats['qr'] ?></div>
        <div class="stat-label">QR Codes</div>
      </div>
      <div class="stat-card c-sig" style="cursor:pointer" onclick="location.href='/tools/signature.php'">
        <div class="stat-icon"><i class="fa fa-envelope"></i></div>
        <div class="stat-value"><?= $stats['sig'] ?></div>
        <div class="stat-label">Signatures mail</div>
      </div>
      <div class="stat-card c-vc" style="cursor:pointer" onclick="location.href='/tools/vcard.php'">
        <div class="stat-icon"><i class="fa fa-id-card"></i></div>
        <div class="stat-value"><?= $stats['vc'] ?></div>
        <div class="stat-label">Cartes de visite</div>
      </div>
    </div>

    <!-- Tools -->
    <div>
      <div class="section-header">
        <div>
          <div class="section-title">Outils disponibles</div>
          <div class="section-sub">Accédez directement à vos outils de création</div>
        </div>
      </div>
      <div class="tools-grid">

        <a class="tool-card tc-qr" href="/tools/qr.php">
          <div class="tool-top">
            <div class="tool-icon"><i class="fa fa-qrcode"></i></div>
            <div class="tool-arrow"><i class="fa fa-arrow-right"></i></div>
          </div>
          <div>
            <div class="tool-name">Générateur QR Code</div>
            <div class="tool-desc">Créez des QR codes personnalisés avec couleurs, logo et redirections trackées.</div>
          </div>
          <div class="tool-count"><i class="fa fa-layer-group"></i> <?= $stats['qr'] ?> créé<?= $stats['qr'] > 1 ? 's' : '' ?></div>
        </a>

        <a class="tool-card tc-sig" href="/tools/signature.php">
          <div class="tool-top">
            <div class="tool-icon"><i class="fa fa-envelope"></i></div>
            <div class="tool-arrow"><i class="fa fa-arrow-right"></i></div>
          </div>
          <div>
            <div class="tool-name">Signature Mail</div>
            <div class="tool-desc">Générez des signatures email professionnelles compatibles tous clients mail.</div>
          </div>
          <div class="tool-count"><i class="fa fa-layer-group"></i> <?= $stats['sig'] ?> créée<?= $stats['sig'] > 1 ? 's' : '' ?></div>
        </a>

        <a class="tool-card tc-vc" href="/tools/vcard.php">
          <div class="tool-top">
            <div class="tool-icon"><i class="fa fa-id-card"></i></div>
            <div class="tool-arrow"><i class="fa fa-arrow-right"></i></div>
          </div>
          <div>
            <div class="tool-name">Carte de visite</div>
            <div class="tool-desc">Créez votre carte de visite numérique partageable avec un lien unique.</div>
          </div>
          <div class="tool-count"><i class="fa fa-layer-group"></i> <?= $stats['vc'] ?> créée<?= $stats['vc'] > 1 ? 's' : '' ?></div>
        </a>

      </div>
    </div>

    <!-- Recent activity -->
    <div>
      <div class="section-header">
        <div>
          <div class="section-title">Activité récente</div>
          <div class="section-sub">Dernières créations et modifications</div>
        </div>
      </div>
      <div class="table-wrap">
        <?php if (empty($allRecent)): ?>
          <div class="empty-state">
            <i class="fa fa-clock-rotate-left"></i>
            Aucune création pour le moment.<br>
            <a href="/tools/qr.php" style="color:var(--accent);text-decoration:none;font-size:13px;margin-top:8px;display:inline-block">
              Commencer maintenant →
            </a>
          </div>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nom</th>
                <th>Type</th>
                <?php if ($isAdm): ?><th>Auteur</th><?php endif; ?>
                <th>Modifié</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="recentTbody">
              <?php foreach ($allRecent as $item):
                $ti = $typeInfo[$item['type']];
                $typeClass = 'type-' . $item['type'];
              ?>
              <tr data-name="<?= strtolower(htmlspecialchars($item['name'])) ?>">
                <td><span class="item-name"><?= htmlspecialchars($item['name']) ?></span></td>
                <td>
                  <span class="type-badge <?= $typeClass ?>">
                    <i class="fa <?= $ti['icon'] ?>"></i>
                    <?= $ti['label'] ?>
                  </span>
                </td>
                <?php if ($isAdm): ?>
                <td style="color:var(--muted)"><?= htmlspecialchars($item['author'] ?? '—') ?></td>
                <?php endif; ?>
                <td class="item-time"><?= timeAgo($item['updated_at']) ?></td>
                <td>
                  <a class="act-link" href="<?= $ti['url'] ?>?id=<?= $item['id'] ?>">
                    <i class="fa fa-pen-to-square"></i> Modifier
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Members (admin only) -->
    <?php if ($isAdm && !empty($members)): ?>
    <div>
      <div class="section-header">
        <div>
          <div class="section-title">Membres de l'équipe</div>
          <div class="section-sub"><?= count($members) ?> compte<?= count($members) > 1 ? 's' : '' ?> enregistré<?= count($members) > 1 ? 's' : '' ?></div>
        </div>
        <a class="btn btn-ghost btn-sm" href="/admin/users.php">
          <i class="fa fa-users-gear"></i> Gérer
        </a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Membre</th>
              <th>Rôle</th>
              <th>Statut</th>
              <th>Dernière connexion</th>
              <th>Inscrit le</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr>
              <td>
                <div style="font-weight:500"><?= htmlspecialchars($m['username']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($m['email']) ?></div>
              </td>
              <td><span class="role-badge role-<?= $m['role'] ?>"><?= $m['role'] === 'admin' ? 'Admin' : 'Membre' ?></span></td>
              <td>
                <span class="status-dot">
                  <span class="dot <?= $m['is_active'] ? 'dot-active' : 'dot-inactive' ?>"></span>
                  <?= $m['is_active'] ? 'Actif' : 'Inactif' ?>
                </span>
              </td>
              <td class="item-time"><?= $m['last_login'] ? timeAgo($m['last_login']) : '—' ?></td>
              <td class="item-time"><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<script src="/assets/layout.js"></script>
<script>
// ── AVATAR DROPDOWN ───────────────────────────────────────
var avBtn = document.getElementById('avatarBtn');
var avMenu = document.getElementById('avMenu');
avBtn.addEventListener('click', function(e) {
  e.stopPropagation();
  var open = avMenu.classList.toggle('open');
  avBtn.setAttribute('aria-expanded', open);
});
document.addEventListener('click', function() {
  avMenu.classList.remove('open');
  avBtn.setAttribute('aria-expanded', false);
});
avBtn.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); avBtn.click(); }
});

// ── SEARCH ────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function() {
  var q = this.value.toLowerCase().trim();
  var rows = document.querySelectorAll('#recentTbody tr');
  rows.forEach(function(r) {
    r.style.display = (!q || r.dataset.name.includes(q)) ? '' : 'none';
  });
});

// ── STAT COUNTER ANIMATION ────────────────────────────────
function animateCount(el, target) {
  var start = 0, dur = 800, step = 16;
  var inc = target / (dur / step);
  var timer = setInterval(function() {
    start += inc;
    if (start >= target) { start = target; clearInterval(timer); }
    el.textContent = Math.floor(start);
  }, step);
}
document.querySelectorAll('.stat-value').forEach(function(el) {
  var v = parseInt(el.textContent, 10);
  if (v > 0) { el.textContent = '0'; animateCount(el, v); }
});
</script>
</body>
</html>
