<?php
/**
 * VV ToolBox — Générateur de QR Code v2
 */
require_once __DIR__ . '/../auth/session.php';
requireLogin();
checkSessionExpiry();

$user  = currentUser();
$db    = getDB();
$uid   = $user['id'];
$isAdm = isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'Token invalide']); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $url  = trim($_POST['target_url'] ?? '');
        $opts = $_POST['options'] ?? '{}';
        $id   = (int)($_POST['id'] ?? 0);
        if (!$name||!$slug||!$url) { echo json_encode(['ok'=>false,'error'=>'Champs obligatoires']); exit; }
        if (!filter_var($url, FILTER_VALIDATE_URL)) { echo json_encode(['ok'=>false,'error'=>'URL invalide']); exit; }
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) { echo json_encode(['ok'=>false,'error'=>'Slug invalide']); exit; }
        try {
            if ($id) {
                $db->prepare('UPDATE qr_codes SET name=?,slug=?,target_url=?,options_json=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$name,$slug,$url,$opts,$id,$uid]);
                echo json_encode(['ok'=>true,'id'=>$id]);
            } else {
                $db->prepare('INSERT INTO qr_codes (user_id,name,slug,target_url,options_json) VALUES (?,?,?,?,?)')->execute([$uid,$name,$slug,$url,$opts]);
                echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
            }
        } catch (PDOException $e) { echo json_encode(['ok'=>false,'error'=>'Slug déjà utilisé']); }
        exit;
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM qr_codes WHERE id=? AND user_id=?')->execute([$id,$uid]);
        echo json_encode(['ok'=>true]); exit;
    }
    echo json_encode(['ok'=>false,'error'=>'Action inconnue']); exit;
}

$sql = $isAdm
    ? 'SELECT q.*,u.username FROM qr_codes q JOIN users u ON u.id=q.user_id ORDER BY q.updated_at DESC'
    : 'SELECT q.*,u.username FROM qr_codes q JOIN users u ON u.id=q.user_id WHERE q.user_id=? ORDER BY q.updated_at DESC';
$st = $db->prepare($sql);
if (!$isAdm) $st->execute([$uid]); else $st->execute();
$qrList = $st->fetchAll();
$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#080809;--surface:#0f0f11;--s2:#17171a;--s3:#1e1e23;
  --border:#26262d;--accent:#4f6ef7;
  --text:#eeeef2;--muted:#7a7a8f;--dim:#3a3a48;
  --success:#34d399;--error:#f87171;
  --nav-w:240px;--topbar-h:58px;--sb-w:272px;--pv-w:360px;
}
[data-theme="light"]{--bg:#f1f1f5;--surface:#fff;--s2:#f7f7fb;--s3:#eeeef5;--border:#e0e0ea;--text:#0d0d14;--muted:#6b6b80;--dim:#b8b8cc}

/* Reset + base */
html,body{height:100%;overflow:hidden}
body{font-family:'Geist',system-ui,sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;font-size:14px}

/* TOPBAR */
.topbar{height:var(--topbar-h);background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 20px 0 0;flex-shrink:0;z-index:50}
.tb-logo{width:var(--nav-w);display:flex;align-items:center;gap:11px;padding:0 20px;border-right:1px solid var(--border);height:100%;flex-shrink:0;text-decoration:none;color:var(--text)}
.tb-logo-icon{width:34px;height:34px;background:linear-gradient(135deg,#4f6ef7,#7c5cfc);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;color:#fff;flex-shrink:0}
.tb-logo-name{font-family:'Instrument Serif',serif;font-size:18px;letter-spacing:-.2px}
.tb-logo-sub{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.tb-center{flex:1;padding:0 20px}
.tb-bc{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:8px}
.tb-bc a{color:var(--muted);text-decoration:none}.tb-bc a:hover{color:var(--text)}
.tb-right{display:flex;align-items:center;gap:8px}
.tb-btn{width:36px;height:36px;border-radius:9px;background:transparent;border:1px solid var(--border);color:var(--muted);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.tb-btn:hover{border-color:var(--accent);color:var(--accent);background:var(--s2)}

/* ── LAYOUT FIX : min-height:0 sur tous les flex-children ── */
.layout{display:flex;flex:1;min-height:0}

/* NAV rétractable */
.nav{width:var(--nav-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;overflow:hidden;padding:14px 8px 56px;transition:width .22s ease;position:relative;min-height:0}
.nav-body{flex:1;overflow-y:auto;overflow-x:hidden}.nav-body::-webkit-scrollbar{width:0}
.nav-footer{position:absolute;bottom:0;left:0;right:0;padding:10px 8px;border-top:1px solid var(--border);background:var(--surface)}
.nav-toggle{display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;border-radius:9px;border:none;background:transparent;color:var(--muted);cursor:pointer;font-size:13px;font-weight:500;font-family:'Geist',sans-serif;transition:all .15s;white-space:nowrap;overflow:hidden}
.nav-toggle:hover{background:var(--s2);color:var(--text)}
.nav-toggle i{width:18px;text-align:center;flex-shrink:0}
.nav-section{margin-bottom:18px}
.nav-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--dim);padding:0 8px;margin-bottom:4px;white-space:nowrap;overflow:hidden;opacity:1;height:20px;transition:opacity .15s,height .15s,margin .15s}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:9px;color:var(--muted);text-decoration:none;transition:all .12s;font-size:13px;font-weight:500;border:1px solid transparent;white-space:nowrap;overflow:hidden;position:relative}
.nav-item:hover{background:var(--s2);color:var(--text)}
.nav-item.active{background:rgba(79,110,247,.1);border-color:rgba(79,110,247,.2);color:var(--accent)}
.nav-item i{width:18px;text-align:center;font-size:14px;flex-shrink:0}
.nav-item-label{flex:1;transition:opacity .15s;overflow:hidden}
.nav-sep{height:1px;background:var(--border);margin:6px 4px}
.nav-badge{margin-left:auto;font-size:10px;font-weight:600;padding:1px 7px;border-radius:20px;background:var(--s3);color:var(--muted);flex-shrink:0;transition:opacity .15s}
.nav-tip{position:absolute;left:62px;top:50%;transform:translateY(-50%);background:var(--s3);border:1px solid var(--border);color:var(--text);font-size:12px;padding:4px 10px;border-radius:7px;white-space:nowrap;pointer-events:none;box-shadow:0 4px 16px rgba(0,0,0,.4);opacity:0;transition:opacity .1s;z-index:999}
body.nav-mini .nav-item:hover .nav-tip{opacity:1}
/* mini state */
body.nav-mini .nav{width:56px}
body.nav-mini .tb-logo{width:56px}
body.nav-mini .tb-logo-text{opacity:0;max-width:0;overflow:hidden}
body.nav-mini .nav-label{opacity:0;height:0;margin:0}
body.nav-mini .nav-item-label{opacity:0;max-width:0}
body.nav-mini .nav-badge{opacity:0;max-width:0}
body.nav-mini .nav-item{justify-content:center;padding:10px}
body.nav-mini .nav-toggle-label{opacity:0;max-width:0;overflow:hidden}

/* SIDEBAR */
.sb{width:var(--sb-w);background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0;min-height:0}
.sbh{padding:14px 14px 12px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.sbt{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted)}
/* KEY: flex:1 + min-height:0 + overflow-y:auto pour scroll */
.sb-list{flex:1;min-height:0;overflow-y:auto;padding:8px}
.sb-list::-webkit-scrollbar{width:3px}.sb-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.sb-item{padding:10px 12px;border-radius:9px;border:1px solid var(--border);background:var(--s2);cursor:pointer;transition:all .15s;margin-bottom:5px;position:relative}
.sb-item:hover{border-color:var(--accent)}.sb-item.active{border-color:var(--accent);background:rgba(79,110,247,.08)}
.sb-item-name{font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;padding-right:30px}
.sb-item-sub{font-size:11px;color:var(--dim);font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-item-dot{width:8px;height:8px;border-radius:50%;position:absolute;right:10px;top:50%;transform:translateY(-50%)}
.sb-item-actions{position:absolute;right:6px;top:50%;transform:translateY(-50%);display:none;gap:2px}
.sb-item:hover .sb-item-actions{display:flex}.sb-item:hover .sb-item-dot{display:none}
.sb-empty{padding:40px 16px;text-align:center;color:var(--dim);font-size:12px;line-height:1.8}
.sb-empty i{font-size:26px;margin-bottom:8px;display:block;opacity:.3}

/* EDITOR — KEY: flex:1, min-width:0, overflow-y:auto */
.ed{flex:1;min-width:0;min-height:0;overflow-y:auto;padding:0 0 24px;display:flex;flex-direction:column;gap:0}
.ed::-webkit-scrollbar{width:5px}.ed::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.ed-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--dim);text-align:center}
.ed-empty i{font-size:44px;opacity:.15}
.ed-empty h2{font-family:'Instrument Serif',serif;font-size:20px;color:var(--muted)}
.ed-empty p{font-size:13px;max-width:260px;line-height:1.6}

/* SECTIONS */
.sec{background:var(--surface);border:1px solid var(--border);border-radius:13px;overflow:hidden;flex-shrink:0}
.sec-h{display:flex;align-items:center;gap:10px;padding:13px 16px;cursor:pointer;user-select:none;transition:background .1s}
.sec-h:hover{background:rgba(255,255,255,.02)}[data-theme="light"] .sec-h:hover{background:rgba(0,0,0,.02)}
.sec-h.open{border-bottom:1px solid var(--border)}
.sec-ico{color:var(--accent);font-size:13px;width:16px;text-align:center;flex-shrink:0}
.sec-lbl{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);flex:1}
.sec-arr{font-size:11px;color:var(--dim);transition:transform .2s}
.sec-h.open .sec-arr{transform:rotate(180deg)}
.sec-b{padding:16px;display:flex;flex-direction:column;gap:12px}

/* FIELDS */
.fld{display:flex;flex-direction:column;gap:5px}
.fld label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.fld input,.fld select{font-family:'Geist',sans-serif;font-size:13px;padding:9px 11px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;width:100%}
.fld input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,110,247,.12)}
.r2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.hint{font-size:11px;color:var(--dim);font-family:monospace;padding:5px 9px;background:var(--s2);border-radius:6px;border:1px solid var(--border);margin-top:4px}
.hint em{color:var(--accent);font-style:normal}

/* COLOR */
.col-row{display:flex;align-items:center;gap:8px}
.col-swatch{width:34px;height:34px;border-radius:8px;border:2px solid var(--border);flex-shrink:0;position:relative;overflow:hidden;cursor:pointer}
.col-swatch input[type=color]{position:absolute;inset:-6px;width:calc(100% + 12px);height:calc(100% + 12px);opacity:0;cursor:pointer;border:none;padding:0}
.checker{background-image:repeating-conic-gradient(#bbb 0% 25%,#fff 0% 50%);background-size:8px 8px}
[data-theme="dark"] .checker{background-image:repeating-conic-gradient(#444 0% 25%,#222 0% 50%)}
.col-hex{font-family:monospace;font-size:13px;padding:8px 10px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);flex:1;outline:none}
.col-hex:focus{border-color:var(--accent)}

/* SLIDER */
.sl-row{display:flex;align-items:center;gap:10px}
.sl-row input[type=range]{flex:1;-webkit-appearance:none;height:4px;border-radius:4px;background:var(--border);outline:none;cursor:pointer}
.sl-row input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:var(--accent);cursor:pointer}
.sl-val{font-size:12px;color:var(--muted);min-width:36px;text-align:right;font-family:monospace}

/* DOT STYLE GRID */
.dot-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.dot-opt{padding:9px 6px 8px;border-radius:9px;border:1px solid var(--border);background:var(--s2);cursor:pointer;font-size:11px;font-weight:600;color:var(--muted);transition:all .15s;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px}
.dot-opt:hover{border-color:rgba(79,110,247,.5)}
.dot-opt.act{border-color:var(--accent);background:rgba(79,110,247,.1);color:var(--accent)}
.dot-preview{width:38px;height:38px;border-radius:4px}

/* Eye style row */
.eye-row{display:flex;gap:8px}
.eye-opt{flex:1;padding:8px;border-radius:9px;border:1px solid var(--border);background:var(--s2);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);transition:all .15s;text-align:center}
.eye-opt:hover{border-color:rgba(79,110,247,.5)}.eye-opt.act{border-color:var(--accent);background:rgba(79,110,247,.1);color:var(--accent)}

/* LOGO DROP */
.ldrop{border:2px dashed var(--border);border-radius:10px;padding:14px;text-align:center;cursor:pointer;transition:all .15s}
.ldrop:hover{border-color:var(--accent);background:rgba(79,110,247,.04)}
.ldrop img{width:48px;height:48px;object-fit:contain;border-radius:6px;margin:0 auto 6px;display:block}
.ldrop-t{font-size:12px;color:var(--muted)}.ldrop-t strong{color:var(--accent)}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;border:none;font-family:'Geist',sans-serif;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,var(--accent),#6a5af9);color:#fff;box-shadow:0 2px 12px rgba(79,110,247,.3)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,110,247,.4)}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{background:var(--s2);color:var(--text)}
.btn-danger{background:transparent;color:var(--error);border:1px solid rgba(248,113,113,.3)}
.btn-danger:hover{background:rgba(248,113,113,.08)}
.btn-sm{padding:6px 12px;font-size:12px}.btn-icon{padding:7px 9px}

/* ── STICKY ACTION BAR ── */
.action-bar{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:8px;padding:10px 20px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0;transition:box-shadow .2s}
.action-bar.unsaved{box-shadow:0 1px 0 var(--border),0 4px 20px rgba(0,0,0,.15)}
.btn-save{background:linear-gradient(135deg,var(--accent),#6a5af9);color:#fff;box-shadow:0 2px 12px rgba(79,110,247,.3);transition:all .2s}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(79,110,247,.4)}
.btn-save.unsaved{background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 2px 12px rgba(245,158,11,.4);animation:pulse-save 2s infinite}
.btn-save.unsaved:hover{box-shadow:0 6px 20px rgba(245,158,11,.5)}
@keyframes pulse-save{0%,100%{box-shadow:0 2px 12px rgba(245,158,11,.4)}50%{box-shadow:0 2px 20px rgba(245,158,11,.7)}}
.unsaved-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#fff;margin-right:2px;opacity:0;transition:opacity .2s}
.btn-save.unsaved .unsaved-dot{opacity:1}
.unsaved-label{font-size:11px;color:#f59e0b;font-weight:500;margin-left:2px;opacity:0;transition:opacity .2s;white-space:nowrap}
.action-bar.unsaved .unsaved-label{opacity:1}

/* PREVIEW PANEL */
.pv{width:var(--pv-w);background:var(--surface);border-left:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:20px 16px;flex-shrink:0;overflow-y:auto;gap:0;min-height:0}
.pv::-webkit-scrollbar{width:3px}.pv::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.pv-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--dim);align-self:flex-start;margin-bottom:16px}
.qr-box{background:var(--s2);border:1px solid var(--border);border-radius:16px;padding:22px;display:flex;flex-direction:column;align-items:center;gap:14px;width:100%}
/* Checker background for transparent QR preview */
.qr-canvas-wrap{border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center;background-image:repeating-conic-gradient(#ccc 0% 25%,#fff 0% 50%);background-size:12px 12px;min-width:220px;min-height:220px}
[data-theme="dark"] .qr-canvas-wrap{background-image:repeating-conic-gradient(#333 0% 25%,#1a1a1a 0% 50%)}
#qrCanvas{display:block;max-width:100%;border-radius:6px}
.qr-placeholder{color:var(--dim);font-size:12px;text-align:center;padding:20px;display:flex;flex-direction:column;align-items:center;justify-content:center;width:220px;height:220px}
.qr-placeholder i{font-size:40px;display:block;margin-bottom:8px;opacity:.2}
.qr-name{font-size:13px;font-weight:600;text-align:center}
.qr-url{font-size:10px;color:var(--muted);font-family:monospace;text-align:center;word-break:break-all}
.pv-actions{margin-top:12px;display:flex;flex-direction:column;gap:8px;width:100%}
.pv-stats{margin-top:12px;background:var(--s2);border:1px solid var(--border);border-radius:10px;padding:14px;width:100%}
.stat-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0}
.stat-row:not(:last-child){border-bottom:1px solid var(--border)}
.stat-lbl{color:var(--muted)}.stat-val{font-weight:600;font-family:monospace}

/* TOAST / MODAL */
.toast{position:fixed;bottom:20px;right:20px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:11px 16px;font-size:13px;color:var(--text);box-shadow:0 8px 32px rgba(0,0,0,.5);z-index:9999;transform:translateY(60px);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);display:flex;align-items:center;gap:8px}
.toast.show{transform:translateY(0);opacity:1}
.toast.success i{color:var(--success)}.toast.error i{color:var(--error)}.toast.info i{color:var(--accent)}
.ov{position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(6px);z-index:1000;display:none;align-items:center;justify-content:center}
.ov.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;width:440px;max-width:90vw;box-shadow:0 24px 80px rgba(0,0,0,.6);animation:mUp .25s cubic-bezier(.16,1,.3,1) both}
@keyframes mUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.modal h2{font-family:'Instrument Serif',serif;font-size:20px;margin-bottom:6px}
.modal-desc{font-size:13px;color:var(--muted);margin-bottom:20px}
.mf{display:flex;justify-content:flex-end;gap:8px;margin-top:20px}
</style>
</head>
<body>

<div class="topbar">
  <a class="tb-logo" href="/dashboard.php">
    <div class="tb-logo-icon"><i class="fa fa-toolbox"></i></div>
    <div class="tb-logo-text"><div class="tb-logo-name">VV ToolBox</div><div class="tb-logo-sub">Espace de travail</div></div>
  </a>
  <div class="tb-center">
    <div class="tb-bc">
      <a href="/dashboard.php">Dashboard</a>
      <span style="color:var(--dim);font-size:10px"><i class="fa fa-chevron-right"></i></span>
      <span>QR Code</span>
    </div>
  </div>
  <div class="tb-right">
    <button class="tb-btn" id="themeBtn"><i class="fa fa-sun" id="themeIco"></i></button>
    <button class="btn btn-primary btn-sm" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouveau QR</button>
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
        <a class="nav-item active" href="/tools/qr.php"><i class="fa fa-qrcode"></i><span class="nav-item-label"> QR Code</span><span class="nav-badge"><?=count($qrList)?></span><span class="nav-tip">QR Code</span></a>
        <a class="nav-item" href="/tools/signature.php"><i class="fa fa-envelope"></i><span class="nav-item-label"> Signature mail</span><span class="nav-tip">Signature mail</span></a>
        <a class="nav-item" href="/tools/vcard.php"><i class="fa fa-id-card"></i><span class="nav-item-label"> Carte de visite</span><span class="nav-tip">Carte de visite</span></a>
      </div>
      <div class="nav-sep"></div>
      <?php if($isAdm):?><a class="nav-item" href="/admin/users.php"><i class="fa fa-users"></i><span class="nav-item-label"> Membres</span><span class="nav-tip">Membres</span></a><?php endif;?>
      <a class="nav-item" href="/profile.php"><i class="fa fa-user-pen"></i><span class="nav-item-label"> Mon profil</span><span class="nav-tip">Mon profil</span></a>
      <a class="nav-item" href="/logout.php"><i class="fa fa-arrow-right-from-bracket"></i><span class="nav-item-label"> Déconnexion</span><span class="nav-tip">Déconnexion</span></a>
    </div>
    <div class="nav-footer">
      <button class="nav-toggle" onclick="toggleNav()">
        <i class="fa fa-chevron-left" id="navToggleIco"></i>
        <span class="nav-item-label nav-toggle-label" id="navToggleLbl">Réduire</span>
      </button>
    </div>
  </nav>

  <div class="sb">
    <div class="sbh">
      <span class="sbt">Mes QR Codes</span>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="openNewModal()"><i class="fa fa-plus"></i></button>
    </div>
    <div class="sb-list" id="SBL">
      <?php if(empty($qrList)):?>
        <div class="sb-empty"><i class="fa fa-qrcode"></i>Aucun QR code.<br>Créez-en un.</div>
      <?php else:foreach($qrList as $qr): $opts=json_decode($qr['options_json']??'{}',true)?:[];?>
        <div class="sb-item" id="sbi-<?=$qr['id']?>" onclick="selQR(<?=$qr['id']?>)">
          <div class="sb-item-name"><?=htmlspecialchars($qr['name'])?></div>
          <div class="sb-item-sub"><?=htmlspecialchars(parse_url($qr['target_url'],PHP_URL_HOST)?:'')?></div>
          <div class="sb-item-dot" style="background:<?=htmlspecialchars($opts['fgColor']??'#4f6ef7')?>"></div>
          <div class="sb-item-actions">
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delQR(<?=$qr['id']?>)"><i class="fa fa-trash" style="color:var(--error)"></i></button>
          </div>
        </div>
      <?php endforeach;endif;?>
    </div>
  </div>

  <div class="ed" id="ED">
    <div class="ed-empty">
      <i class="fa fa-qrcode"></i>
      <h2>Générateur QR</h2>
      <p>QR codes personnalisés avec redirection trackée.</p>
      <button class="btn btn-primary" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouveau QR Code</button>
    </div>
  </div>

  <div class="pv">
    <div class="pv-lbl">Aperçu live</div>
    <div class="qr-box">
      <div class="qr-canvas-wrap" id="qrWrap">
        <div class="qr-placeholder"><i class="fa fa-qrcode"></i>QR apparaît ici</div>
      </div>
      <div id="qrName" class="qr-name" style="display:none"></div>
      <div id="qrUrl"  class="qr-url"  style="display:none"></div>
    </div>
    <div class="pv-actions" id="pvActions" style="display:none">
      <button class="btn btn-primary" onclick="dlQR('png')"><i class="fa fa-download"></i> Télécharger PNG</button>
      <button class="btn btn-ghost"   onclick="dlQR('svg')"><i class="fa fa-file-code"></i> SVG</button>
    </div>
    <div class="pv-stats" id="pvStats" style="display:none">
      <div class="stat-row"><span class="stat-lbl">Scans</span><span class="stat-val" id="pvScans">0</span></div>
      <div class="stat-row"><span class="stat-lbl">Lien court</span><span class="stat-val" id="pvLink" style="font-size:10px;color:var(--accent)"></span></div>
    </div>
  </div>
</div>

<div class="ov" id="MN">
  <div class="modal">
    <h2>Nouveau QR Code</h2>
    <p class="modal-desc">Nom interne et slug URL unique.</p>
    <div class="fld"><label>Nom interne</label><input type="text" id="nName" placeholder="Flyer été 2025" oninput="autoSlug()"></div>
    <div class="fld" style="margin-top:10px"><label>Slug URL</label>
      <input type="text" id="nSlug" placeholder="flyer-ete-2025" oninput="cleanSlug(this)">
      <div class="hint"><?=htmlspecialchars(APP_URL)?>/r/<em id="slugHint">...</em></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost" onclick="closeOv('MN')">Annuler</button>
      <button class="btn btn-primary" onclick="createQR()"><i class="fa fa-plus"></i> Créer</button>
    </div>
  </div>
</div>

<div class="toast" id="T"><i></i><span id="TM"></span></div>

<script>
var CSRF    = <?=json_encode($csrf)?>;
var APP_URL = <?=json_encode(APP_URL)?>;
var qrData  = <?=json_encode(array_map(function($q){
  $o=json_decode($q['options_json']??'{}',true)?:[];
  return['id'=>(int)$q['id'],'name'=>$q['name'],'slug'=>$q['slug'],
    'target_url'=>$q['target_url'],'scan_count'=>(int)$q['scan_count'],'opts'=>$o];
},$qrList))?>;

var cur=null, deb=null, tTmr=null;

/* ── THEME ─────────────────────────────────────────────── */
(function(){var s=localStorage.getItem('vv_theme')||'dark';document.documentElement.setAttribute('data-theme',s);document.getElementById('themeIco').className=s==='light'?'fa fa-moon':'fa fa-sun'})();
document.getElementById('themeBtn').onclick=function(){var c=document.documentElement.getAttribute('data-theme'),n=c==='dark'?'light':'dark';document.documentElement.setAttribute('data-theme',n);document.getElementById('themeIco').className=n==='light'?'fa fa-moon':'fa fa-sun';localStorage.setItem('vv_theme',n)};
(function(){var m=localStorage.getItem('vv_nav')==='mini';if(m)document.body.classList.add('nav-mini');function ap(){var i=document.getElementById('navToggleIco'),l=document.getElementById('navToggleLbl');if(i)i.className=document.body.classList.contains('nav-mini')?'fa fa-chevron-right':'fa fa-chevron-left';if(l)l.textContent=document.body.classList.contains('nav-mini')?'':'Réduire';}ap();window.toggleNav=function(){m=!m;document.body.classList.toggle('nav-mini',m);localStorage.setItem('vv_nav',m?'mini':'full');ap();};})();

function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

/* ── QR MATRIX EXTRACTION via qrcode.js ────────────────── */
function getMatrix(text){
  var d=document.createElement('div');d.style.cssText='position:fixed;left:-9999px;top:-9999px';
  document.body.appendChild(d);
  var qr=new QRCode(d,{text:text,width:200,height:200,correctLevel:QRCode.CorrectLevel.H});
  var N=0,mods=[];
  if(qr._oQRCode){
    N=qr._oQRCode.moduleCount;
    for(var r=0;r<N;r++){mods[r]=[];for(var c=0;c<N;c++)mods[r][c]=qr._oQRCode.isDark(r,c);}
  }
  document.body.removeChild(d);
  return{N:N,mods:mods};
}

/* ── DRAW STYLED QR on canvas ───────────────────────────── */
function drawQR(text,opts,canvas){
  var mx=getMatrix(text);
  if(!mx.N){return;}
  var N=mx.N, mods=mx.mods;
  var SIZE=280, margin=parseInt(opts.margin)||2;
  var cellSz=(SIZE-margin*2)/N;
  canvas.width=SIZE; canvas.height=SIZE;
  var ctx=canvas.getContext('2d');

  var fg  = opts.fgColor||'#000000';
  var bg  = opts.bgColor||'#ffffff';
  var trans=!!opts.transparentBg;
  var dot = opts.dotStyle||'square';
  var eye = opts.eyeStyle||'square';

  /* background */
  if(trans){ ctx.clearRect(0,0,SIZE,SIZE); }
  else{ ctx.fillStyle=bg; ctx.fillRect(0,0,SIZE,SIZE); }

  /* eye zones (7×7 modules at corners) */
  function isInEye(r,c){
    return(r<7&&c<7)||(r<7&&c>=N-7)||(r>=N-7&&c<7);
  }

  /* draw one cell with chosen dot style */
  function drawDot(ctx,x,y,sz,style){
    var p=sz*0.08, dx=x+p, dy=y+p, dw=sz-2*p;
    ctx.fillStyle=fg;
    switch(style){
      case 'dots':
        ctx.beginPath();ctx.arc(dx+dw/2,dy+dw/2,dw/2,0,Math.PI*2);ctx.fill(); break;
      case 'rounded':
        roundRect(ctx,dx,dy,dw,dw,dw*0.3); break;
      case 'extra-rounded':
        roundRect(ctx,dx,dy,dw,dw,dw*0.5); break;
      case 'classy':
        ctx.beginPath();
        ctx.moveTo(dx+dw/2,dy); ctx.lineTo(dx+dw,dy+dw/2);
        ctx.lineTo(dx+dw/2,dy+dw); ctx.lineTo(dx,dy+dw/2);
        ctx.closePath();ctx.fill(); break;
      default: /* square */
        ctx.fillRect(dx,dy,dw,dw);
    }
  }

  function roundRect(ctx,x,y,w,h,r){
    r=Math.min(r,w/2,h/2);
    ctx.beginPath();
    ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.arcTo(x+w,y,x+w,y+r,r);
    ctx.lineTo(x+w,y+h-r);ctx.arcTo(x+w,y+h,x+w-r,y+h,r);
    ctx.lineTo(x+r,y+h);ctx.arcTo(x,y+h,x,y+h-r,r);
    ctx.lineTo(x,y+r);ctx.arcTo(x,y,x+r,y,r);
    ctx.closePath();ctx.fill();
  }

  /* draw data modules */
  for(var r=0;r<N;r++){
    for(var c=0;c<N;c++){
      if(!mods[r][c]||isInEye(r,c)) continue;
      var x=margin+c*cellSz, y=margin+r*cellSz;
      drawDot(ctx,x,y,cellSz,dot);
    }
  }

  /* draw eye (finder pattern) at corner offset (r0,c0) */
  function drawEye(r0,c0){
    var ex=margin+c0*cellSz, ey=margin+r0*cellSz;
    var ew=7*cellSz;
    var rad=eye==='rounded'?ew*0.15:0;

    /* clear area */
    if(trans) ctx.clearRect(ex,ey,ew,ew);
    else{ctx.fillStyle=bg;ctx.fillRect(ex,ey,ew,ew);}

    /* outer ring */
    ctx.fillStyle=fg;
    if(rad>0) roundRect(ctx,ex,ey,ew,ew,rad);
    else ctx.fillRect(ex,ey,ew,ew);

    /* inner white square */
    var irad=eye==='rounded'?cellSz*0.3:0;
    if(trans) ctx.clearRect(ex+cellSz,ey+cellSz,5*cellSz,5*cellSz);
    else{ctx.fillStyle=bg; if(irad>0) roundRect(ctx,ex+cellSz,ey+cellSz,5*cellSz,5*cellSz,irad); else ctx.fillRect(ex+cellSz,ey+cellSz,5*cellSz,5*cellSz);}

    /* inner dot 3×3 */
    ctx.fillStyle=fg;
    var drad=eye==='rounded'?cellSz*0.25:0;
    if(drad>0) roundRect(ctx,ex+2*cellSz,ey+2*cellSz,3*cellSz,3*cellSz,drad);
    else ctx.fillRect(ex+2*cellSz,ey+2*cellSz,3*cellSz,3*cellSz);
  }

  drawEye(0,0); drawEye(0,N-7); drawEye(N-7,0);

  /* logo overlay */
  if(opts.logoData){
    var img=new Image();
    img.onload=function(){
      var lp=(opts.logoSize||30)/100;
      var lw=SIZE*lp,lh=SIZE*lp,lx=(SIZE-lw)/2,ly=(SIZE-lh)/2;
      if(trans) ctx.clearRect(lx-4,ly-4,lw+8,lh+8);
      else{ctx.fillStyle=bg;ctx.fillRect(lx-4,ly-4,lw+8,lh+8);}
      ctx.drawImage(img,lx,ly,lw,lh);
    };
    img.src=opts.logoData;
  }
}

/* ── PREVIEW ────────────────────────────────────────────── */
function schedQR(){clearTimeout(deb);deb=setTimeout(renderPreview,250)}

function renderPreview(){
  var q=cur;
  var wrap=document.getElementById('qrWrap');
  var pvA=document.getElementById('pvActions'),pvS=document.getElementById('pvStats');
  if(!q||!q.target_url){
    wrap.innerHTML='<div class="qr-placeholder"><i class="fa fa-qrcode"></i>Entrez une URL</div>';
    pvA.style.display='none';pvS.style.display='none';return;
  }
  var opts=q.opts||{};
  var link=APP_URL+'/r/'+(q.slug||'preview');

  var canvas=document.getElementById('qrCanvas');
  if(!canvas){
    canvas=document.createElement('canvas');canvas.id='qrCanvas';
    canvas.style.cssText='display:block;max-width:100%;border-radius:6px';
    wrap.innerHTML='';wrap.appendChild(canvas);
  }
  try{ drawQR(link,opts,canvas); } catch(e){ console.error(e); }

  var nm=document.getElementById('qrName'),ur=document.getElementById('qrUrl');
  nm.textContent=q.name;nm.style.display='';
  ur.textContent=link;ur.style.display='';
  pvA.style.display='';pvS.style.display='';
  document.getElementById('pvScans').textContent=q.scan_count||0;
  document.getElementById('pvLink').textContent=link.replace(/^https?:\/\//,'');
}

/* ── DOWNLOAD ───────────────────────────────────────────── */
function dlQR(fmt){
  var c=document.getElementById('qrCanvas');if(!c){toast('Générez un QR d\'abord.','error');return}
  var fn=(cur?cur.slug:'qr')+'-qr';
  if(fmt==='png'){
    var a=document.createElement('a');a.download=fn+'.png';a.href=c.toDataURL('image/png');a.click();
    toast('PNG téléchargé !','success');
  } else {
    var w=c.width,h=c.height,ctx=c.getContext('2d'),id=ctx.getImageData(0,0,w,h);
    var fg=(cur&&cur.opts&&cur.opts.fgColor)||'#000000';
    var trans=!!(cur&&cur.opts&&cur.opts.transparentBg);
    var bgCol=trans?'none':((cur&&cur.opts&&cur.opts.bgColor)||'#ffffff');
    var rects='';
    for(var y=0;y<h;y++)for(var x=0;x<w;x++){
      var i=(y*w+x)*4;
      if(id.data[i+3]>128&&id.data[i]<100) rects+='<rect x="'+x+'" y="'+y+'" width="1" height="1" fill="'+fg+'"/>';
    }
    var svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+w+' '+h+'" width="400" height="400"><rect width="100%" height="100%" fill="'+bgCol+'"/>'+rects+'</svg>';
    var bl=new Blob([svg],{type:'image/svg+xml'});
    var a=document.createElement('a');a.download=fn+'.svg';a.href=URL.createObjectURL(bl);a.click();
    toast('SVG téléchargé !','success');
  }
}

/* ── EDITOR ─────────────────────────────────────────────── */
var DOT_STYLES=[
  {k:'square',l:'Carré'},
  {k:'dots',l:'Points'},
  {k:'rounded',l:'Arrondi'},
  {k:'extra-rounded',l:'Bulles'},
  {k:'classy',l:'Diamant'},
];

function renderEditor(){
  var q=cur;if(!q)return;
  var o=q.opts||{};
  var fg=o.fgColor||'#000000', bg=o.bgColor||'#ffffff';
  var trans=!!o.transparentBg, dot=o.dotStyle||'square', eye=o.eyeStyle||'square';
  var logo=o.logoData||'', lsz=o.logoSize||30, margin=o.margin!==undefined?o.margin:2;

  var dotBtns=DOT_STYLES.map(function(d){
    return '<div class="dot-opt'+(dot===d.k?' act':'')+'" data-dot="'+d.k+'" onclick="setDot(\''+d.k+'\')">'+
      '<canvas class="dot-preview" id="dp_'+d.k+'"></canvas>'+d.l+'</div>';
  }).join('');

  document.getElementById('ED').innerHTML=
    '<div class="action-bar" id="actionBar">'+
      '<button class="btn btn-save" id="btnSave" onclick="saveQR()">'+
        '<span class="unsaved-dot"></span><i class="fa fa-floppy-disk"></i> <span id="btnSaveLbl">Sauvegarder</span>'+
      '</button>'+
      '<button class="btn btn-ghost btn-sm" onclick="dlQR(\'png\')"><i class="fa fa-download"></i> PNG</button>'+
      '<span class="unsaved-label" id="unsavedLbl">Modifications non sauvegardées</span>'+
      '<div style="flex:1"></div>'+
      '<button class="btn btn-danger btn-sm btn-icon" onclick="delQR('+q.id+')" title="Supprimer"><i class="fa fa-trash"></i></button>'+
    '</div>'+
    '<div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">'+

    mkSec('fa-qrcode','Informations',true,
      mkF('Nom interne','<input type="text" value="'+esc(q.name)+'" oninput="upd(\'name\',this.value)">')+
      mkF('URL de destination','<input type="url" value="'+esc(q.target_url)+'" placeholder="https://..." oninput="upd(\'target_url\',this.value);schedQR()">')+
      mkF('Slug','<input type="text" value="'+esc(q.slug)+'" oninput="upd(\'slug\',this.value)">'+
        '<div class="hint">'+esc(APP_URL)+'/r/<em>'+esc(q.slug)+'</em></div>')
    )+

    mkSec('fa-palette','Couleurs',true,
      '<div class="r2">'+
        mkColorF('Couleur QR','fgColor',fg,false)+
        mkColorF('Fond','bgColor',bg,trans)+
      '</div>'+
      '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;margin-top:4px">'+
        '<input type="checkbox" id="cbTrans" '+(trans?'checked':'')+' onchange="setOpt(\'transparentBg\',this.checked);toggleBgField(this.checked);schedQR()" style="width:auto;accent-color:var(--accent)">'+
        'Fond transparent (PNG / SVG seulement)'+
      '</label>'
    )+

    mkSec('fa-shapes','Style des modules',true,
      '<div class="dot-grid" id="dotGrid">'+dotBtns+'</div>'+
      '<div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:10px 0 6px">Coins (yeux)</div>'+
      '<div class="eye-row">'+
        ['square','rounded'].map(function(e){
          var lbl=e==='square'?'Carré':'Arrondi';
          return '<div class="eye-opt'+(eye===e?' act':'')+'" onclick="setEye(\''+e+'\')">'+lbl+'</div>';
        }).join('')+
      '</div>'
    )+

    mkSec('fa-image','Logo central',false,
      '<div class="ldrop" onclick="document.getElementById(\'LF\').click()">'+
        (logo?'<img src="'+logo+'" alt="logo">':'<i class="fa fa-cloud-arrow-up" style="font-size:22px;color:var(--dim);display:block;margin-bottom:6px"></i>')+
        '<div class="ldrop-t">'+(logo?'Cliquer pour changer':'<strong>Cliquer</strong> pour uploader<br><small style="color:var(--dim)">PNG transparent recommandé</small>')+'</div>'+
        '<input type="file" id="LF" accept="image/*" style="display:none" onchange="upLogo(event)">'+
      '</div>'+
      (logo?'<button class="btn btn-danger btn-sm" onclick="setOpt(\'logoData\',\'\');renderEditor();schedQR()"><i class="fa fa-trash"></i> Supprimer le logo</button>':'')+
      mkF('Taille du logo',
        '<div class="sl-row"><input type="range" min="10" max="45" value="'+lsz+'" oninput="setOpt(\'logoSize\',+this.value);document.getElementById(\'lszv\').textContent=this.value+\'%\';schedQR()"><span class="sl-val" id="lszv">'+lsz+'%</span></div>')
    )+

    mkSec('fa-border-all','Marge',false,
      mkF('Espace autour du QR',
        '<div class="sl-row"><input type="range" min="0" max="10" value="'+margin+'" oninput="setOpt(\'margin\',+this.value);document.getElementById(\'mgv\').textContent=this.value;schedQR()"><span class="sl-val" id="mgv">'+margin+'</span></div>')
    )+'</div>'; /* close content wrapper */

  toggleBgField(trans);
  setTimeout(drawPreviews,40);
}

/* ── UNSAVED STATE ────────────────────────────────────────── */
function markUnsaved(){
  var bar=document.getElementById('actionBar'),btn=document.getElementById('btnSave');
  if(bar)bar.classList.add('unsaved');
  if(btn)btn.classList.add('unsaved');
}
function markSaved(){
  var bar=document.getElementById('actionBar'),btn=document.getElementById('btnSave'),lbl=document.getElementById('btnSaveLbl');
  if(bar)bar.classList.remove('unsaved');
  if(btn)btn.classList.remove('unsaved','saving');
  if(lbl){lbl.textContent='Sauvegardé !';setTimeout(function(){if(document.getElementById('btnSaveLbl'))document.getElementById('btnSaveLbl').textContent='Sauvegarder';},2000);}
}

function mkSec(ico,lbl,open,body){
  var id='S_'+lbl.replace(/\W/g,'');
  return '<div class="sec">'+
    '<div class="sec-h '+(open?'open':'')+'" onclick="togSec(\''+id+'\')" id="h_'+id+'">'+
      '<i class="fa '+ico+' sec-ico"></i><span class="sec-lbl">'+lbl+'</span><i class="fa fa-chevron-down sec-arr"></i>'+
    '</div>'+
    '<div class="sec-b" id="b_'+id+'" style="'+(open?'':'display:none')+'">'+body+'</div>'+
  '</div>';
}
function togSec(id){var h=document.getElementById('h_'+id),b=document.getElementById('b_'+id);if(!h||!b)return;var o=h.classList.toggle('open');b.style.display=o?'':'none'}
function mkF(lbl,inp){return '<div class="fld"><label>'+lbl+'</label>'+inp+'</div>'}
function mkColorF(lbl,key,val,trans){
  return '<div class="fld" id="cf_'+key+'"><label>'+lbl+'</label><div class="col-row">'+
    '<div class="col-swatch'+(trans?' checker':'')+'" id="sw_'+key+'" style="background:'+(trans?'':val)+'">'+
      '<input type="color" value="'+val+'" oninput="updColor(\''+key+'\',this.value)">'+
    '</div>'+
    '<input type="text" class="col-hex" id="hx_'+key+'" value="'+val+'" maxlength="7" onchange="updColorHx(\''+key+'\',this.value)">'+
  '</div></div>';
}

/* Toggle bg field opacity when transparent */
function toggleBgField(on){
  var el=document.getElementById('cf_bgColor');
  if(el) el.style.opacity=on?'0.35':'1';
}

/* Draw tiny dot-style previews */
function drawPreviews(){
  var fg=(cur&&cur.opts&&cur.opts.fgColor)||'#4f6ef7';
  DOT_STYLES.forEach(function(d){
    var c=document.getElementById('dp_'+d.k);if(!c)return;
    c.width=38;c.height=38;
    var ctx=c.getContext('2d');ctx.fillStyle=fg;
    [[1,1],[1,2],[2,1],[2,2],[1,3],[3,1],[3,2],[3,3],[2,3]].forEach(function(p){
      var sz=9,x=p[0]*sz,y=p[1]*sz;
      switch(d.k){
        case 'dots': ctx.beginPath();ctx.arc(x+sz/2,y+sz/2,sz/2-1,0,2*Math.PI);ctx.fill();break;
        case 'rounded': ctx.beginPath();ctx.roundRect(x+1,y+1,sz-2,sz-2,[2.5]);ctx.fill();break;
        case 'extra-rounded': ctx.beginPath();ctx.roundRect(x+1,y+1,sz-2,sz-2,[4]);ctx.fill();break;
        case 'classy':
          var hw=sz/2;ctx.beginPath();
          ctx.moveTo(x+hw,y);ctx.lineTo(x+sz,y+hw);ctx.lineTo(x+hw,y+sz);ctx.lineTo(x,y+hw);
          ctx.closePath();ctx.fill();break;
        default: ctx.fillRect(x+1,y+1,sz-2,sz-2);
      }
    });
  });
}

/* ── UPDATE HELPERS ─────────────────────────────────────── */
function upd(k,v){if(!cur)return;cur[k]=v;markUnsaved()}
function setOpt(k,v){if(!cur)return;if(!cur.opts)cur.opts={};cur.opts[k]=v;markUnsaved()}
function updColor(k,v){
  setOpt(k,v);
  var sw=document.getElementById('sw_'+k),hx=document.getElementById('hx_'+k);
  if(sw){sw.style.background=v;sw.classList.remove('checker')}
  if(hx)hx.value=v;
  drawPreviews();schedQR();
}
function updColorHx(k,v){if(!/^#[0-9a-fA-F]{6}$/.test(v))return;updColor(k,v)}
function setDot(s){
  setOpt('dotStyle',s);schedQR();
  document.querySelectorAll('.dot-opt').forEach(function(el){el.classList.toggle('act',el.dataset.dot===s)});
}
function setEye(s){
  setOpt('eyeStyle',s);schedQR();
  document.querySelectorAll('.eye-opt').forEach(function(el,i){el.classList.toggle('act',(i===0&&s==='square')||(i===1&&s==='rounded'))});
}
function upLogo(ev){
  var f=ev.target.files[0];if(!f)return;
  var r=new FileReader();r.onload=function(e){setOpt('logoData',e.target.result);renderEditor();schedQR()};r.readAsDataURL(f);
}

/* ── SELECT ─────────────────────────────────────────────── */
function selQR(id){
  var q=qrData.find(function(x){return x.id===id});if(!q)return;
  cur=JSON.parse(JSON.stringify(q));
  document.querySelectorAll('.sb-item').forEach(function(el){el.classList.remove('active')});
  var si=document.getElementById('sbi-'+id);if(si)si.classList.add('active');
  renderEditor();schedQR();
}

/* ── SAVE ───────────────────────────────────────────────── */
function saveQR(){
  var q=cur;if(!q)return;
  var fd=new FormData();
  fd.append('csrf_token',CSRF);fd.append('action','save');
  fd.append('id',q.id||0);fd.append('name',q.name||'');
  fd.append('slug',q.slug||'');fd.append('target_url',q.target_url||'');
  fd.append('options',JSON.stringify(q.opts||{}));
  fetch('/tools/qr.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){q.id=d.id;markSaved();toast('QR sauvegardé !','success');rebuildSBI(q)}
      else toast(d.error||'Erreur','error');
    });
}
function rebuildSBI(q){
  var opts=q.opts||{};
  var h='<div class="sb-item active" id="sbi-'+q.id+'" onclick="selQR('+q.id+')">'+
    '<div class="sb-item-name">'+esc(q.name)+'</div>'+
    '<div class="sb-item-sub">'+(q.target_url?esc(q.target_url.replace(/^https?:\/\//,'').split('/')[0]):'')+'</div>'+
    '<div class="sb-item-dot" style="background:'+(opts.fgColor||'#4f6ef7')+'"></div>'+
    '<div class="sb-item-actions"><button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delQR('+q.id+')"><i class="fa fa-trash" style="color:var(--error)"></i></button></div>'+
  '</div>';
  var ex=document.getElementById('sbi-'+q.id);
  if(ex)ex.outerHTML=h;
  else{var l=document.getElementById('SBL');l.innerHTML=h+l.innerHTML.replace(/<div class="sb-empty">[\s\S]*?<\/div>/,'')}
  document.querySelectorAll('.sb-item').forEach(function(el){el.classList.remove('active')});
  var si=document.getElementById('sbi-'+q.id);if(si)si.classList.add('active');
  var idx=qrData.findIndex(function(x){return x.id===q.id});
  if(idx>=0)qrData[idx]=JSON.parse(JSON.stringify(q));else qrData.unshift(JSON.parse(JSON.stringify(q)));
}

/* ── DELETE ─────────────────────────────────────────────── */
function delQR(id){
  if(!confirm('Supprimer ce QR Code ?'))return;
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','delete');fd.append('id',id);
  fetch('/tools/qr.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){
        var el=document.getElementById('sbi-'+id);if(el)el.remove();
        qrData=qrData.filter(function(x){return x.id!==id});
        if(cur&&cur.id===id){
          cur=null;
          document.getElementById('ED').innerHTML='<div class="ed-empty"><i class="fa fa-qrcode"></i><h2>Générateur QR</h2><p>Sélectionnez ou créez un QR.</p><button class="btn btn-primary" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouveau</button></div>';
          document.getElementById('qrWrap').innerHTML='<div class="qr-placeholder"><i class="fa fa-qrcode"></i>QR apparaît ici</div>';
          ['pvActions','pvStats'].forEach(function(x){document.getElementById(x).style.display='none'});
          ['qrName','qrUrl'].forEach(function(x){document.getElementById(x).style.display='none'});
        }
        if(!qrData.length)document.getElementById('SBL').innerHTML='<div class="sb-empty"><i class="fa fa-qrcode"></i>Aucun QR.</div>';
        toast('QR supprimé.','info');
      }
    });
}

/* ── MODAL / SLUG ───────────────────────────────────────── */
function openNewModal(){document.getElementById('nName').value='';document.getElementById('nSlug').value='';document.getElementById('slugHint').textContent='...';document.getElementById('MN').classList.add('open');setTimeout(function(){document.getElementById('nName').focus()},80)}
function closeOv(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.ov').forEach(function(el){el.addEventListener('click',function(e){if(e.target===el)el.classList.remove('open')})});
function autoSlug(){var v=document.getElementById('nName').value;var s=v.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('nSlug').value=s;document.getElementById('slugHint').textContent=s||'...'}
function cleanSlug(el){el.value=el.value.toLowerCase().replace(/[^a-z0-9-]/g,'');document.getElementById('slugHint').textContent=el.value||'...'}
function createQR(){
  var name=document.getElementById('nName').value.trim(),slug=document.getElementById('nSlug').value.trim();
  if(!name||!slug){toast('Remplissez tous les champs.','error');return}
  cur={id:0,name:name,slug:slug,target_url:'',scan_count:0,
    opts:{fgColor:'#000000',bgColor:'#ffffff',transparentBg:false,dotStyle:'square',eyeStyle:'square',margin:4,logoSize:30}};
  closeOv('MN');renderEditor();schedQR();toast('Renseignez l\'URL puis sauvegardez.','info');
}

/* ── TOAST ──────────────────────────────────────────────── */
function toast(m,t){
  var el=document.getElementById('T'),ic={success:'fa-check-circle',error:'fa-circle-exclamation',info:'fa-circle-info'};
  el.className='toast '+(t||'success');
  el.querySelector('i').className='fa '+ic[t||'success'];
  document.getElementById('TM').textContent=m;
  el.classList.add('show');clearTimeout(tTmr);tTmr=setTimeout(function(){el.classList.remove('show')},3200);
}
</script>
</body>
</html>
