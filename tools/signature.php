<?php
/**
 * VV ToolBox — Générateur de Signature Mail v3
 * Styles séparés des polices, taille photo/logo, logo toujours en haut
 */
require_once __DIR__ . '/../auth/session.php';
requireLogin();
checkSessionExpiry();

$user  = currentUser();
$db    = getDB();
$uid   = $user['id'];
$isAdm = isAdmin();

// ── ACTIONS POST (AJAX) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok'=>false,'error'=>'Token invalide']); exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $data = $_POST['data'] ?? '{}';
        $id   = (int)($_POST['id'] ?? 0);
        if (!$name) { echo json_encode(['ok'=>false,'error'=>'Nom requis']); exit; }
        try {
            if ($id) {
                $db->prepare('UPDATE email_signatures SET name=?,data_json=?,updated_at=NOW() WHERE id=? AND user_id=?')
                   ->execute([$name, $data, $id, $uid]);
                echo json_encode(['ok'=>true,'id'=>$id]);
            } else {
                $db->prepare('INSERT INTO email_signatures (user_id,name,data_json) VALUES (?,?,?)')
                   ->execute([$uid, $name, $data]);
                echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
            }
        } catch (PDOException $e) { echo json_encode(['ok'=>false,'error'=>'Erreur DB']); }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM email_signatures WHERE id=? AND user_id=?')->execute([$id, $uid]);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Action inconnue']); exit;
}

// ── GET : liste (propres + partagés avec moi) ────────────────
$sql = $isAdm
    ? 'SELECT *, NULL AS share_permission, NULL AS shared_by FROM email_signatures ORDER BY updated_at DESC'
    : '(SELECT *, NULL AS share_permission, NULL AS shared_by FROM email_signatures WHERE user_id=?)
       UNION ALL
       (SELECT e.*, s.permission AS share_permission, u_o.username AS shared_by
        FROM email_signatures e
        JOIN shares s ON s.resource_type=\'sig\' AND s.resource_id=e.id AND s.shared_with=?
        JOIN users u_o ON u_o.id=e.user_id)
       ORDER BY updated_at DESC';
$st = $db->prepare($sql);
if (!$isAdm) $st->execute([$uid, $uid]); else $st->execute();
$sigList = $st->fetchAll();
$csrf = getCsrfToken();

// ── LAYOUT CONFIG ──────────────────────────────────────────
$navActive  = 'sig';
$navSig     = count($sigList);
$breadcrumb = [['Signature mail', null]];
$tbActions  = '<button class="btn btn-primary btn-sm" onclick="openOv(\'MN\')"><i class="fa fa-plus"></i> Nouvelle Signature</button>';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Signature Mail — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/layout.css">
<style>
:root { --accent: #0ea5e9; }

/* ── PREVIEW PANEL ─── */
.pv {
  width: var(--pv-w);
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex; flex-direction: column;
  flex-shrink: 0; min-height: 0;
}
.pv-header { padding: 13px 16px 11px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.pv-label  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--dim); }
.pv-tabs   { display: flex; gap: 4px; }
.pv-tab    { padding: 5px 10px; border-radius: 7px; font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--muted); font-family: 'Geist',sans-serif; transition: all .15s; }
.pv-tab.act { background: var(--s2); color: var(--text); border-color: var(--border2); }
.pv-preview { flex: 1; min-height: 0; overflow-y: auto; padding: 20px; background: var(--bg); }
.pv-preview::-webkit-scrollbar { width: 3px; }
.pv-preview::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
.sig-frame { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
.pv-copy { padding: 12px 16px; border-top: 1px solid var(--border); flex-shrink: 0; }
.copy-info { font-size: 11px; color: var(--muted); margin-bottom: 8px; line-height: 1.5; }

/* ── STYLE LAYOUT CARDS ─── */
.layout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.layout-card {
  border: 2px solid var(--border);
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: all .15s;
  background: var(--s2);
  position: relative;
}
.layout-card:hover { border-color: var(--accent); }
.layout-card.act   { border-color: var(--accent); background: rgba(14,165,233,.08); }
.layout-card-preview {
  height: 52px; background: var(--s3);
  border-radius: 6px; margin-bottom: 7px;
  overflow: hidden; position: relative;
  display: flex; align-items: center;
  padding: 6px 8px; gap: 6px;
}
.lcp-logo { width: 28px; height: 28px; background: var(--accent); opacity: .5; border-radius: 4px; flex-shrink: 0; }
.lcp-lines { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.lcp-line { height: 4px; background: var(--border2); border-radius: 2px; }
.lcp-line.bold { background: var(--muted); width: 60%; }
.lcp-line.short { width: 40%; }
.layout-card-name { font-size: 11px; font-weight: 600; color: var(--muted); text-align: center; }
.layout-card .act-check { position: absolute; top: 6px; right: 6px; width: 16px; height: 16px; background: var(--accent); border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 9px; color: #fff; }
.layout-card.act .act-check { display: flex; }

/* ── FONT GRID ─── */
.font-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.font-card {
  padding: 8px 14px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--s2);
  cursor: pointer;
  font-size: 14px;
  transition: all .15s;
  color: var(--text);
}
.font-card:hover { border-color: rgba(14,165,233,.5); }
.font-card.act   { border-color: var(--accent); background: rgba(14,165,233,.1); color: var(--accent); }

/* share-user-* styles are in layout.css */

#htmlExport { position:absolute;left:-9999px;top:-9999px;opacity:0; }
</style>
</head>
<body>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="layout">

  <?php require __DIR__ . '/../includes/nav.php'; ?>

  <!-- SIDEBAR -->
  <div class="sb">
    <div class="sbh">
      <span class="sbt">Mes signatures</span>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="openOv('MN')"><i class="fa fa-plus"></i></button>
    </div>
    <div class="sb-list" id="SBL">
      <?php if (empty($sigList)): ?>
        <div class="sb-empty"><i class="fa fa-envelope"></i>Aucune signature.<br>Créez-en une.</div>
      <?php else: foreach ($sigList as $s):
        $d = json_decode($s['data_json'], true) ?: [];
        $isOwner = ($s['share_permission'] === null);
      ?>
        <div class="sb-item" id="sbi-<?= $s['id'] ?>" onclick="selSig(<?= $s['id'] ?>)">
          <div class="sb-item-name"><?= htmlspecialchars($s['name']) ?></div>
          <div class="sb-item-sub">
            <?php if (!$isOwner): ?>
              <span class="shared-badge"><i class="fa fa-share-nodes"></i> <?= htmlspecialchars($s['shared_by']) ?></span>
            <?php else: ?>
              <?= htmlspecialchars(trim(($d['firstName']??'').' '.($d['lastName']??''))) ?: 'Sans nom' ?>
            <?php endif; ?>
          </div>
          <div class="sb-item-actions">
            <?php if ($isOwner): ?>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openShare(<?= $s['id'] ?>)" title="Partager"><i class="fa fa-share-nodes" style="color:var(--accent)"></i></button>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delSig(<?= $s['id'] ?>)" title="Supprimer"><i class="fa fa-trash" style="color:var(--error)"></i></button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- EDITOR -->
  <div class="ed" id="ED">
    <div class="ed-empty">
      <i class="fa fa-envelope-open-text"></i>
      <h2>Signature Mail</h2>
      <p>Créez des signatures HTML compatibles avec tous les clients mail.</p>
      <button class="btn btn-primary" onclick="openOv('MN')"><i class="fa fa-plus"></i> Nouvelle signature</button>
    </div>
  </div>

  <!-- PREVIEW -->
  <div class="pv">
    <div class="pv-header">
      <span class="pv-label">Aperçu</span>
      <div class="pv-tabs">
        <button class="pv-tab act" onclick="setPvTab('visual',this)">Visuel</button>
        <button class="pv-tab" onclick="setPvTab('html',this)">HTML</button>
      </div>
    </div>
    <div class="pv-preview" id="pvPreview">
      <div style="color:var(--dim);text-align:center;padding:40px 20px;font-size:13px">
        <i class="fa fa-envelope" style="font-size:32px;display:block;margin-bottom:10px;opacity:.2"></i>
        La signature apparaît ici
      </div>
    </div>
    <div class="pv-copy">
      <div class="copy-info">Copiez le HTML et collez-le dans les paramètres de signature de votre client mail (Outlook, Gmail, Apple Mail…)</div>
      <button class="btn btn-success" style="width:100%" onclick="copySig()"><i class="fa fa-copy"></i> Copier la signature</button>
    </div>
  </div>
</div>

<!-- Modal: Nouvelle signature -->
<div class="ov" id="MN">
  <div class="modal">
    <h2>Nouvelle signature</h2>
    <p class="modal-desc">Donnez un nom interne à cette signature.</p>
    <div class="fld"><label>Nom interne</label><input type="text" id="nName" placeholder="Ex: Signature principale" autofocus></div>
    <div class="mf">
      <button class="btn btn-ghost" onclick="closeOv('MN')">Annuler</button>
      <button class="btn btn-primary" onclick="createSig()"><i class="fa fa-plus"></i> Créer</button>
    </div>
  </div>
</div>

<!-- Modal: Partage -->
<div class="ov" id="MS">
  <div class="modal" style="width:500px">
    <h2><i class="fa fa-share-nodes" style="color:var(--accent);margin-right:8px"></i>Partager</h2>
    <p class="modal-desc">Invitez un autre utilisateur à accéder à cette signature.</p>
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <input type="email" id="shareEmail" placeholder="email@utilisateur.fr" style="flex:1;font-family:'Geist',sans-serif;font-size:13px;padding:9px 11px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);outline:none">
      <select id="sharePerm" style="font-family:'Geist',sans-serif;font-size:13px;padding:9px 11px;background:var(--s2);border:1px solid var(--border);border-radius:8px;color:var(--text);outline:none">
        <option value="edit">Peut modifier</option>
        <option value="view">Lecture seule</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="doShare()"><i class="fa fa-plus"></i></button>
    </div>
    <div id="shareList" style="min-height:40px"></div>
    <div class="mf"><button class="btn btn-ghost" onclick="closeOv('MS')">Fermer</button></div>
  </div>
</div>

<div class="toast" id="T"><i></i><span id="TM"></span></div>
<textarea id="htmlExport"></textarea>

<script src="/assets/layout.js"></script>
<script>
var CSRF = <?=json_encode($csrf)?>;
var sigData = <?=json_encode(array_map(function($s){
  return ['id'=>(int)$s['id'],'name'=>$s['name'],'data'=>json_decode($s['data_json'],true)?:[],
          'share_permission'=>$s['share_permission'],'shared_by'=>$s['shared_by']];
},$sigList))?>;

var currentSig = null;
var debTimer = null;
var pvTab = 'visual';
var shareRid = 0;

// ── LAYOUT OPTIONS ──────────────────────────────────────────
var LAYOUTS = [
  { id:'modern',   label:'Moderne',    desc:'Logo · Texte · Coordonnées' },
  { id:'classic',  label:'Classique',  desc:'Logo · Titre · Texte vertical' },
  { id:'minimal',  label:'Minimal',    desc:'Logo · Une ligne' },
  { id:'bold',     label:'Impactant',  desc:'Logo · Fond coloré' },
  { id:'compact',  label:'Compact',    desc:'Logo · Tout en ligne' },
  { id:'divided',  label:'Séparé',     desc:'Logo gauche | Contenu droite' }
];

// ── FONT OPTIONS ────────────────────────────────────────────
var FONTS = [
  { id:'helvetica', label:'Helvetica',  stack:"'Helvetica Neue',Helvetica,Arial,sans-serif" },
  { id:'georgia',   label:'Georgia',    stack:"Georgia,'Times New Roman',serif" },
  { id:'trebuchet', label:'Trebuchet',  stack:"'Trebuchet MS',Tahoma,sans-serif" },
  { id:'verdana',   label:'Verdana',    stack:"Verdana,Geneva,sans-serif" },
  { id:'courier',   label:'Courier',    stack:"'Courier New',Courier,monospace" }
];

function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

// ── SELECT ───────────────────────────────────────────────────
function selSig(id){
  var s=sigData.find(function(x){return x.id===id}); if(!s) return;
  currentSig=JSON.parse(JSON.stringify(s));
  document.querySelectorAll('.sb-item').forEach(function(el){el.classList.remove('active')});
  var si=document.getElementById('sbi-'+id); if(si) si.classList.add('active');
  renderEditor();
  schPV();
}

// ── DEFAULT DATA ─────────────────────────────────────────────
function defData(){
  return{
    firstName:'',lastName:'',jobTitle:'',company:'',
    phone:'',mobile:'',email:'',website:'',address:'',
    photo:'',photoSize:64,
    logo:'',logoSize:36,
    instagram:'',facebook:'',linkedin:'',twitter:'',
    googleReview:'',
    extras:[],
    layout:'modern',
    fontId:'helvetica',
    accentColor:'#0ea5e9',textColor:'#1a1a2e',mutedColor:'#6b7280',
    fontSize:'14',
    divider:true
  };
}

// ── EDITOR ───────────────────────────────────────────────────
function renderEditor(){
  var s=currentSig; if(!s) return;
  var d=s.data;
  var ed=document.getElementById('ED');

  // Layout cards
  var layoutCards = LAYOUTS.map(function(l){
    var act = (d.layout||'modern')===l.id;
    return '<div class="layout-card'+(act?' act':'')+'" onclick="setLayout(\''+l.id+'\')" title="'+l.desc+'">'+
      '<div class="act-check"><i class="fa fa-check"></i></div>'+
      layoutPreviewSVG(l.id)+
      '<div class="layout-card-name">'+l.label+'</div>'+
    '</div>';
  }).join('');

  // Font cards
  var fontCards = FONTS.map(function(f){
    var act = (d.fontId||'helvetica')===f.id;
    return '<div class="font-card'+(act?' act':'')+'" style="font-family:'+f.stack+'" onclick="setFont(\''+f.id+'\')">'+f.label+'</div>';
  }).join('');

  var extH = d.extras&&d.extras.length ? d.extras.map(function(b,i){return xRow(i,b)}).join('') :
    '<div style="font-size:12px;color:var(--dim);text-align:center;padding:4px">Aucun bouton</div>';

  ed.innerHTML=
    '<div class="action-bar" id="actionBar">'+
      '<button class="btn-save" id="btnSave" onclick="saveSig()">'+
        '<span class="unsaved-dot"></span><i class="fa fa-floppy-disk"></i> <span id="btnSaveLbl">Sauvegarder</span>'+
      '</button>'+
      '<button class="btn btn-success btn-sm" onclick="copySig()"><i class="fa fa-copy"></i> Copier</button>'+
      (s.share_permission===null?'<button class="btn btn-ghost btn-sm" onclick="openShare('+s.id+')"><i class="fa fa-share-nodes"></i> Partager</button>':'')+
      '<span class="unsaved-label" id="unsavedLbl">Modifications non sauvegardées</span>'+
      '<div style="flex:1"></div>'+
      '<button class="btn btn-danger btn-sm btn-icon" onclick="delSig('+s.id+')" title="Supprimer"><i class="fa fa-trash"></i></button>'+
    '</div>'+

    '<div class="ed-content">'+

    mkSec('fa-user','Identité',true,
      '<div class="r2">'+
        mkFld('Prénom','<input type="text" value="'+esc(d.firstName||'')+'" placeholder="Jean" oninput="upd(\'firstName\',this.value)">')+
        mkFld('Nom','<input type="text" value="'+esc(d.lastName||'')+'" placeholder="Dupont" oninput="upd(\'lastName\',this.value)">')+
      '</div>'+
      '<div class="r2">'+
        mkFld('Poste','<input type="text" value="'+esc(d.jobTitle||'')+'" placeholder="Directeur Commercial" oninput="upd(\'jobTitle\',this.value)">')+
        mkFld('Entreprise','<input type="text" value="'+esc(d.company||'')+'" placeholder="Acme Corp" oninput="upd(\'company\',this.value)">')+
      '</div>'
    )+

    mkSec('fa-image','Photo & Logo',false,
      '<div class="r2">'+
        // PHOTO
        '<div>'+
          '<div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Photo</div>'+
          '<div class="ldrop" onclick="document.getElementById(\'LFp\').click()">'+
            (d.photo?'<img src="'+d.photo+'" alt="photo" style="width:52px;height:52px;border-radius:50%;object-fit:cover">':
              '<i class="fa fa-user" style="font-size:22px;color:var(--dim);display:block;margin-bottom:4px"></i>')+
            '<div class="ldrop-t"><strong>'+(d.photo?'Changer':'Upload')+'</strong></div>'+
            '<input type="file" id="LFp" accept="image/*" style="display:none" onchange="upImg(event,\'photo\')">'+
          '</div>'+
          (d.photo?'<button class="btn btn-danger btn-sm" style="margin-top:6px;width:100%" onclick="rmImg(\'photo\')"><i class="fa fa-trash"></i> Suppr.</button>':'')+
        '</div>'+
        // LOGO
        '<div>'+
          '<div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Logo</div>'+
          '<div class="ldrop" onclick="document.getElementById(\'LFl\').click()">'+
            (d.logo?'<img src="'+d.logo+'" alt="logo" style="width:52px;height:52px;object-fit:contain">':
              '<i class="fa fa-image" style="font-size:22px;color:var(--dim);display:block;margin-bottom:4px"></i>')+
            '<div class="ldrop-t"><strong>'+(d.logo?'Changer':'Upload')+'</strong></div>'+
            '<input type="file" id="LFl" accept="image/*" style="display:none" onchange="upImg(event,\'logo\')">'+
          '</div>'+
          (d.logo?'<button class="btn btn-danger btn-sm" style="margin-top:6px;width:100%" onclick="rmImg(\'logo\')"><i class="fa fa-trash"></i> Suppr.</button>':'')+
        '</div>'+
      '</div>'+
      // TAILLES
      mkFld('Taille photo (px)',
        '<div class="sl-row"><input type="range" min="32" max="120" value="'+(d.photoSize||64)+'" oninput="updN(\'photoSize\',+this.value);document.getElementById(\'pszv\').textContent=this.value+\'px\'"><span class="sl-val" id="pszv">'+(d.photoSize||64)+'px</span></div>')+
      mkFld('Taille logo (px)',
        '<div class="sl-row"><input type="range" min="20" max="80" value="'+(d.logoSize||36)+'" oninput="updN(\'logoSize\',+this.value);document.getElementById(\'lszv\').textContent=this.value+\'px\'"><span class="sl-val" id="lszv">'+(d.logoSize||36)+'px</span></div>')
    )+

    mkSec('fa-address-book','Coordonnées',true,
      '<div class="r2">'+
        mkFld('Téléphone','<div class="srow"><i class="fa fa-phone"></i><input type="tel" value="'+esc(d.phone||'')+'" placeholder="04 00 00 00 00" oninput="upd(\'phone\',this.value)"></div>')+
        mkFld('Mobile','<div class="srow"><i class="fa fa-mobile-screen"></i><input type="tel" value="'+esc(d.mobile||'')+'" placeholder="06 00 00 00 00" oninput="upd(\'mobile\',this.value)"></div>')+
      '</div>'+
      mkFld('Email','<div class="srow"><i class="fa fa-envelope"></i><input type="email" value="'+esc(d.email||'')+'" placeholder="contact@acme.fr" oninput="upd(\'email\',this.value)"></div>')+
      mkFld('Site web','<div class="srow"><i class="fa fa-globe"></i><input type="url" value="'+esc(d.website||'')+'" placeholder="https://acme.fr" oninput="upd(\'website\',this.value)"></div>')+
      mkFld('Adresse','<div class="srow"><i class="fa fa-location-dot"></i><input type="text" value="'+esc(d.address||'')+'" placeholder="7 Rue Basse, 75001 Paris" oninput="upd(\'address\',this.value)"></div>')
    )+

    mkSec('fa-share-nodes','Réseaux sociaux',false,
      '<div class="srow"><i class="fa-brands fa-linkedin"></i><input type="url" value="'+esc(d.linkedin||'')+'" placeholder="LinkedIn" oninput="upd(\'linkedin\',this.value)"></div>'+
      '<div class="srow"><i class="fa-brands fa-instagram"></i><input type="url" value="'+esc(d.instagram||'')+'" placeholder="Instagram" oninput="upd(\'instagram\',this.value)"></div>'+
      '<div class="srow"><i class="fa-brands fa-facebook"></i><input type="url" value="'+esc(d.facebook||'')+'" placeholder="Facebook" oninput="upd(\'facebook\',this.value)"></div>'+
      '<div class="srow" style="margin-bottom:0"><i class="fa-brands fa-x-twitter"></i><input type="url" value="'+esc(d.twitter||'')+'" placeholder="X / Twitter" oninput="upd(\'twitter\',this.value)"></div>'
    )+

    mkSec('fa-star','Avis Google',false,
      mkFld('URL Google My Business',
        '<div class="srow"><i class="fa-brands fa-google" style="color:#4285f4"></i>'+
        '<input type="url" value="'+esc(d.googleReview||'')+'" placeholder="https://g.page/r/..." oninput="upd(\'googleReview\',this.value)"></div>'+
        '<div style="font-size:11px;color:var(--dim);margin-top:4px">Un bouton ⭐ Laisser un avis sera ajouté</div>'
      )
    )+

    mkSec('fa-grip','Boutons secondaires',false,
      '<div id="XC">'+extH+'</div>'+
      '<button class="btn btn-ghost btn-sm" style="margin-top:6px" onclick="addExtra()"><i class="fa fa-plus"></i> Ajouter un bouton</button>'
    )+

    mkSec('fa-layer-group','Agencement',true,
      '<div style="font-size:11px;color:var(--dim);margin-bottom:10px">Choisissez la structure visuelle de la signature — le logo apparaît toujours en premier.</div>'+
      '<div class="layout-grid">'+layoutCards+'</div>'
    )+

    mkSec('fa-font','Typographie',false,
      '<div style="font-size:11px;color:var(--dim);margin-bottom:10px">Police d\'écriture du contenu (indépendante de l\'agencement).</div>'+
      '<div class="font-grid" id="FG">'+fontCards+'</div>'+
      mkFld('Taille de police',
        '<select onchange="upd(\'fontSize\',this.value)">'+
          ['12','13','14','15','16'].map(function(v){
            return '<option value="'+v+'"'+(d.fontSize==v?' selected':'')+'>'+v+'px</option>';
          }).join('')+
        '</select>'
      )
    )+

    mkSec('fa-palette','Couleurs',true,
      '<div class="r3">'+
        mkColFld('Accent','accentColor',d.accentColor||'#0ea5e9')+
        mkColFld('Texte','textColor',d.textColor||'#1a1a2e')+
        mkColFld('Discret','mutedColor',d.mutedColor||'#6b7280')+
      '</div>'+
      '<div style="display:flex;align-items:center;gap:8px;margin-top:4px">'+
        '<input type="checkbox" id="cbDiv" '+(d.divider!==false?'checked':'')+' onchange="upd(\'divider\',this.checked)" style="width:auto;accent-color:var(--accent)">'+
        '<label for="cbDiv" style="font-size:13px;color:var(--text)">Afficher un séparateur</label>'+
      '</div>'
    )+

    '<div style="display:flex;justify-content:flex-end;padding:4px 0">'+
      '<button class="btn btn-danger btn-sm" onclick="delSig('+s.id+')"><i class="fa fa-trash"></i> Supprimer</button>'+
    '</div>'+
    '</div>'; // ed-content
}

// Layout card preview mini-SVG (représentation schématique)
function layoutPreviewSVG(id){
  var logos = { h:'<div class="lcp-logo" style="border-radius:50%"></div>' };
  var lines = '<div class="lcp-lines"><div class="lcp-line bold"></div><div class="lcp-line"></div><div class="lcp-line short"></div></div>';
  var p = '<div class="layout-card-preview">';
  if(id==='modern')   p += logos.h + lines;
  else if(id==='classic') p += '<div style="width:3px;background:var(--accent);opacity:.6;border-radius:2px;height:40px;flex-shrink:0"></div><div style="margin-left:6px">' + logos.h + '</div>' + lines;
  else if(id==='minimal') p += logos.h + '<div style="flex:1;height:4px;background:var(--muted);border-radius:2px;opacity:.5;align-self:center"></div>';
  else if(id==='bold')    p += '<div style="background:var(--accent);opacity:.3;position:absolute;inset:0;border-radius:6px"></div>' + logos.h + lines;
  else if(id==='compact') p += logos.h + '<div style="flex:1;border-left:2px solid var(--accent);opacity:.6;padding-left:6px">' + lines + '</div>';
  else if(id==='divided') p += logos.h + '<div style="width:1px;background:var(--border2);height:40px;flex-shrink:0;margin:0 6px"></div>' + lines;
  return p + '</div>';
}

function mkSec(ico,lbl,open,content){
  var sid='SS'+lbl.replace(/\W/g,'_');
  return '<div class="sec">'+
    '<div class="sec-h '+(open?'open':'')+'" onclick="togSec(\''+sid+'\')" id="h_'+sid+'">'+
      '<i class="fa '+ico+' sec-ico"></i><span class="sec-lbl">'+lbl+'</span><i class="fa fa-chevron-down sec-arr"></i>'+
    '</div>'+
    '<div class="sec-b" id="b_'+sid+'" style="'+(open?'':'display:none')+'">'+content+'</div>'+
  '</div>';
}
function togSec(sid){var h=document.getElementById('h_'+sid),b=document.getElementById('b_'+sid);if(!h||!b)return;var o=h.classList.toggle('open');b.style.display=o?'':'none'}
function mkFld(lbl,inp){return '<div class="fld"><label>'+lbl+'</label>'+inp+'</div>'}
function mkColFld(lbl,key,val){
  return '<div class="fld"><label>'+lbl+'</label>'+
    '<div class="col-row">'+
      '<div class="col-swatch" id="sw_'+key+'" style="background:'+val+'"><input type="color" value="'+val+'" oninput="updColor(\''+key+'\',this.value)"></div>'+
      '<input type="text" class="col-hex" id="hx_'+key+'" value="'+val+'" maxlength="7" onchange="updColorHx(\''+key+'\',this.value)">'+
    '</div></div>';
}
function xRow(i,b){
  return '<div class="xrow"><input type="text" value="'+esc(b.label)+'" placeholder="Texte" style="flex:1" oninput="updX('+i+',\'label\',this.value)"><span class="sp">|</span><input type="url" value="'+esc(b.url||'')+'" placeholder="https://..." style="flex:2" oninput="updX('+i+',\'url\',this.value)"><button class="xdel" onclick="rmX('+i+')"><i class="fa fa-xmark"></i></button></div>'
}

// ── DATA HELPERS ─────────────────────────────────────────────
function upd(k,v){if(!currentSig)return;currentSig.data[k]=v;markUnsaved();schPV()}
function updN(k,v){if(!currentSig)return;currentSig.data[k]=v;markUnsaved();schPV()}
function updColor(k,v){upd(k,v);var sw=document.getElementById('sw_'+k),hx=document.getElementById('hx_'+k);if(sw)sw.style.background=v;if(hx)hx.value=v;}
function updColorHx(k,v){if(!/^#[0-9a-fA-F]{6}$/.test(v))return;upd(k,v);var sw=document.getElementById('sw_'+k);if(sw)sw.style.background=v;}
function setLayout(l){upd('layout',l);document.querySelectorAll('.layout-card').forEach(function(el){el.classList.toggle('act',el.getAttribute('onclick').includes("'"+l+"'"));})}
function setFont(f){upd('fontId',f);document.querySelectorAll('.font-card').forEach(function(el){el.classList.toggle('act',el.getAttribute('onclick').includes("'"+f+"'"));})}
function addExtra(){if(!currentSig)return;if(!currentSig.data.extras)currentSig.data.extras=[];currentSig.data.extras.push({label:'',url:''});reXC();schPV()}
function rmX(i){if(!currentSig)return;currentSig.data.extras.splice(i,1);reXC();schPV()}
function updX(i,k,v){if(!currentSig)return;currentSig.data.extras[i][k]=v;schPV()}
function reXC(){var el=document.getElementById('XC');if(!el)return;var d=currentSig.data;if(!d.extras||!d.extras.length){el.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:4px">Aucun bouton</div>';return;}el.innerHTML=d.extras.map(function(b,i){return xRow(i,b)}).join('')}
function upImg(ev,key){var f=ev.target.files[0];if(!f)return;var r=new FileReader();r.onload=function(e){upd(key,e.target.result);renderEditor();schPV()};r.readAsDataURL(f)}
function rmImg(key){upd(key,'');renderEditor();schPV()}

// ── PREVIEW ──────────────────────────────────────────────────
function schPV(){clearTimeout(debTimer);debTimer=setTimeout(renderPreview,200)}
function setPvTab(tab,btn){pvTab=tab;document.querySelectorAll('.pv-tab').forEach(function(el){el.classList.remove('act')});btn.classList.add('act');renderPreview()}
function renderPreview(){
  var pv=document.getElementById('pvPreview'); if(!currentSig) return;
  var html=buildSigHTML(currentSig.data);
  if(pvTab==='html'){
    pv.innerHTML='<pre style="font-size:10px;color:var(--muted);line-height:1.5;white-space:pre-wrap;word-break:break-all;background:var(--s2);padding:12px;border-radius:8px;border:1px solid var(--border)">'+esc(html)+'</pre>';
  } else {
    pv.innerHTML='<div class="sig-frame">'+html+'</div>';
  }
}

// ── BUILD HTML ───────────────────────────────────────────────
function buildSigHTML(d){
  var ac   = d.accentColor  || '#0ea5e9';
  var tc   = d.textColor    || '#1a1a2e';
  var mc   = d.mutedColor   || '#6b7280';
  var fs   = parseInt(d.fontSize||'14');
  var font = FONTS.find(function(f){return f.id===(d.fontId||'helvetica')}) || FONTS[0];
  var fs2  = font.stack;
  var layout = d.layout || 'modern';
  var fn   = [d.firstName,d.lastName].filter(Boolean).join(' ');
  var pSz  = d.photoSize || 64;
  var lSz  = d.logoSize  || 36;

  var socials = [];
  if(d.linkedin)  socials.push({url:d.linkedin, color:'#0a66c2', letter:'in'});
  if(d.instagram) socials.push({url:d.instagram,color:'#e1306c', letter:'ig'});
  if(d.facebook)  socials.push({url:d.facebook, color:'#1877f2', letter:'fb'});
  if(d.twitter)   socials.push({url:d.twitter,  color:'#000000', letter:'tw'});

  var extras = (d.extras||[]).filter(function(b){return b.label&&b.url});
  if(d.googleReview) extras.unshift({label:'⭐ Laisser un avis', url:d.googleReview});

  // Logo block (toujours en premier dans le rendu)
  var logoBlock = d.logo
    ? '<div style="margin-bottom:10px"><img src="'+d.logo+'" height="'+lSz+'" style="max-height:'+lSz+'px;max-width:160px;display:block" alt="logo"></div>'
    : '';

  var sep = (d.divider!==false) ? '<div style="height:2px;width:40px;background:'+ac+';margin-bottom:8px"></div>' : '';
  var nameBlock = fn ? '<div style="font-size:'+(fs+2)+'px;font-weight:700;color:'+tc+';margin-bottom:1px">'+esc(fn)+'</div>' : '';
  var subParts  = [d.jobTitle,d.company].filter(Boolean);
  var subBlock  = subParts.length ? '<div style="font-size:'+(fs-1)+'px;color:'+ac+';font-weight:600;margin-bottom:8px">'+esc(subParts.join(' · '))+'</div>' : '';
  var photoBlock = d.photo
    ? '<td valign="top" style="padding-right:14px;width:'+(pSz+8)+'px"><img src="'+d.photo+'" width="'+pSz+'" height="'+pSz+'" style="border-radius:50%;display:block;width:'+pSz+'px;height:'+pSz+'px;object-fit:cover" alt="'+esc(fn)+'"></td>'
    : '';

  var socRow = socials.length
    ? '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:10px"><tr>'+
        socials.map(function(s){return'<td style="padding-right:8px"><a href="'+s.url+'" target="_blank" style="display:inline-block;width:26px;height:26px;background:'+s.color+';color:#fff;text-align:center;line-height:26px;border-radius:4px;font-size:11px;font-weight:700;text-decoration:none;font-family:Arial">'+s.letter+'</a></td>'}).join('')+
      '</tr></table>' : '';
  var extBlock = extras.length
    ? '<div>'+extras.map(function(b){return '<a href="'+esc(b.url)+'" target="_blank" style="display:inline-block;padding:7px 14px;background:'+ac+';color:#fff;text-decoration:none;border-radius:4px;font-size:'+(fs-2)+'px;font-weight:600;font-family:Arial;margin-right:6px;margin-top:8px">'+esc(b.label)+'</a>'}).join('')+'</div>' : '';

  var ctLines = '';
  if(d.phone)   ctLines+='<div style="font-size:'+(fs-1)+'px;margin-bottom:3px"><a href="tel:'+esc(d.phone.replace(/\s/g,''))+'" style="color:'+tc+';text-decoration:none">'+esc(d.phone)+'</a></div>';
  if(d.mobile)  ctLines+='<div style="font-size:'+(fs-1)+'px;margin-bottom:3px"><a href="tel:'+esc(d.mobile.replace(/\s/g,''))+'" style="color:'+tc+';text-decoration:none">'+esc(d.mobile)+'</a></div>';
  if(d.email)   ctLines+='<div style="font-size:'+(fs-1)+'px;margin-bottom:3px"><a href="mailto:'+esc(d.email)+'" style="color:'+ac+';text-decoration:none">'+esc(d.email)+'</a></div>';
  if(d.website) ctLines+='<div style="font-size:'+(fs-1)+'px;margin-bottom:3px"><a href="'+esc(d.website)+'" style="color:'+ac+';text-decoration:none">'+esc(d.website.replace(/^https?:\/\//,''))+'</a></div>';
  if(d.address) ctLines+='<div style="font-size:'+(fs-2)+'px;color:'+mc+'">'+esc(d.address)+'</div>';

  var out = '';

  // ── MODERN : logo · photo+info horizontale ────────────────
  if(layout==='modern'){
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:'+fs2+';font-size:'+fs+'px;color:'+tc+';line-height:1.5;max-width:520px">';
    out+='<tr><td colspan="2">'+logoBlock+'</td></tr>';
    out+='<tr>'+photoBlock+'<td valign="top">'+nameBlock+subBlock+sep+ctLines+'</td></tr>';
    if(socials.length||extras.length) out+='<tr><td colspan="'+(d.photo?'2':'1')+'">'+socRow+extBlock+'</td></tr>';
    out+='</table>';
  }
  // ── CLASSIC : logo · barre gauche · titre italique ─────────
  else if(layout==='classic'){
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:Georgia,serif;font-size:'+fs+'px;color:'+tc+';line-height:1.5;max-width:520px;border-left:3px solid '+ac+';padding-left:14px">';
    out+='<tr><td>'+logoBlock;
    if(fn) out+='<div style="font-size:'+(fs+4)+'px;font-weight:700;font-style:italic;margin-bottom:2px">'+esc(fn)+'</div>';
    if(d.jobTitle) out+='<div style="font-size:'+(fs-1)+'px;font-weight:600;color:'+ac+';letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px">'+esc(d.jobTitle)+'</div>';
    if(d.company)  out+='<div style="font-size:'+fs+'px;font-weight:700;margin-bottom:8px">'+esc(d.company)+'</div>';
    if(d.divider!==false) out+='<div style="height:1px;background:#e5e7eb;margin-bottom:8px"></div>';
    out+=ctLines+socRow+extBlock+'</td></tr></table>';
  }
  // ── MINIMAL : logo · tout sur une ligne ──────────────────
  else if(layout==='minimal'){
    var parts=[];
    if(fn) parts.push('<strong>'+esc(fn)+'</strong>');
    if(d.jobTitle) parts.push('<span style="color:'+mc+'">'+esc(d.jobTitle)+'</span>');
    if(d.company) parts.push(esc(d.company));
    var contacts=[];
    if(d.phone)   contacts.push('<a href="tel:'+esc(d.phone.replace(/\s/g,''))+'" style="color:'+mc+';text-decoration:none">'+esc(d.phone)+'</a>');
    if(d.email)   contacts.push('<a href="mailto:'+esc(d.email)+'" style="color:'+ac+';text-decoration:none">'+esc(d.email)+'</a>');
    if(d.website) contacts.push('<a href="'+esc(d.website)+'" style="color:'+ac+';text-decoration:none">'+esc(d.website.replace(/^https?:\/\//,''))+'</a>');
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:'+fs2+';font-size:'+fs+'px;color:'+tc+';max-width:480px">';
    out+='<tr><td>'+logoBlock;
    out+='<div style="margin-bottom:4px">'+parts.join(' <span style="color:#d1d5db">|</span> ')+'</div>';
    if(d.divider!==false) out+='<div style="height:1px;background:#e5e7eb;margin-bottom:4px"></div>';
    out+=contacts.length?'<div>'+contacts.join(' <span style="color:#d1d5db">·</span> ')+'</div>':'';
    out+=socRow+extBlock+'</td></tr></table>';
  }
  // ── BOLD : logo · fond coloré ────────────────────────────
  else if(layout==='bold'){
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:'+fs2+';font-size:'+fs+'px;color:#fff;max-width:540px;background:'+ac+';border-radius:8px">';
    out+='<tr><td style="padding:16px 20px">';
    out+=d.logo?'<div style="margin-bottom:10px"><img src="'+d.logo+'" height="'+lSz+'" style="max-height:'+lSz+'px;opacity:.9;display:block" alt="logo"></div>':'';
    if(d.photo) out+='<img src="'+d.photo+'" width="'+pSz+'" height="'+pSz+'" style="border-radius:50%;float:right;margin-left:12px;width:'+pSz+'px;height:'+pSz+'px;object-fit:cover;border:2px solid rgba(255,255,255,.4)" alt="">';
    if(fn) out+='<div style="font-size:'+(fs+4)+'px;font-weight:800;margin-bottom:2px">'+esc(fn)+'</div>';
    if(d.jobTitle||d.company) out+='<div style="font-size:'+(fs-1)+'px;opacity:.85;margin-bottom:10px">'+esc([d.jobTitle,d.company].filter(Boolean).join(' · '))+'</div>';
    if(d.phone)   out+='<div style="font-size:'+(fs-1)+'px;opacity:.9;margin-bottom:2px">📞 '+esc(d.phone)+'</div>';
    if(d.email)   out+='<div style="font-size:'+(fs-1)+'px;opacity:.9;margin-bottom:2px"><a href="mailto:'+esc(d.email)+'" style="color:#fff;text-decoration:none">✉ '+esc(d.email)+'</a></div>';
    if(d.website) out+='<div style="font-size:'+(fs-1)+'px;opacity:.9">🌐 '+esc(d.website.replace(/^https?:\/\//,''))+'</div>';
    out+='</td></tr>';
    if(extras.length) out+='<tr><td style="padding:8px 20px 16px;border-top:1px solid rgba(255,255,255,.2)">'+extBlock+'</td></tr>';
    out+='</table>';
  }
  // ── COMPACT : logo · tout compact avec barre gauche ──────
  else if(layout==='compact'){
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:'+fs2+';font-size:'+(fs-1)+'px;color:'+tc+';line-height:1.4;max-width:460px">';
    out+='<tr><td colspan="2" style="padding-bottom:6px">'+logoBlock+'</td></tr>';
    out+='<tr>';
    if(d.photo) out+='<td valign="middle" style="padding-right:10px;width:'+(pSz*0.7+8)+'px"><img src="'+d.photo+'" width="'+(pSz*0.7)+'" height="'+(pSz*0.7)+'" style="border-radius:4px;display:block;width:'+(pSz*0.7)+'px;height:'+(pSz*0.7)+'px;object-fit:cover" alt=""></td>';
    out+='<td valign="middle" style="border-left:2px solid '+ac+';padding-left:10px">';
    if(fn) out+='<strong>'+esc(fn)+'</strong>';
    var s2b=[d.jobTitle,d.company].filter(Boolean).join(', ');
    if(s2b) out+=' <span style="color:'+mc+'">— '+esc(s2b)+'</span>';
    out+='<br>';
    var cpb=[];
    if(d.phone)   cpb.push('<a href="tel:'+esc(d.phone.replace(/\s/g,''))+'" style="color:'+tc+';text-decoration:none">'+esc(d.phone)+'</a>');
    if(d.email)   cpb.push('<a href="mailto:'+esc(d.email)+'" style="color:'+ac+';text-decoration:none">'+esc(d.email)+'</a>');
    if(d.website) cpb.push('<a href="'+esc(d.website)+'" style="color:'+ac+';text-decoration:none">'+esc(d.website.replace(/^https?:\/\//,''))+'</a>');
    out+=cpb.join(' <span style="color:#d1d5db">·</span> ');
    out+=socRow+'</td></tr></table>';
  }
  // ── DIVIDED : logo gauche | contenu droite ───────────────
  else if(layout==='divided'){
    out='<table cellpadding="0" cellspacing="0" border="0" style="font-family:'+fs2+';font-size:'+fs+'px;color:'+tc+';line-height:1.5;max-width:520px">';
    out+='<tr>';
    out+='<td valign="top" style="padding-right:18px;width:130px;text-align:center;border-right:1px solid #e5e7eb">';
    if(d.logo)  out+='<img src="'+d.logo+'" height="'+lSz+'" style="max-height:'+lSz+'px;max-width:120px;display:block;margin:0 auto 8px" alt="logo">';
    if(d.photo) out+='<img src="'+d.photo+'" width="'+pSz+'" height="'+pSz+'" style="border-radius:50%;display:block;margin:0 auto;width:'+pSz+'px;height:'+pSz+'px;object-fit:cover" alt="">';
    out+='</td>';
    out+='<td valign="top" style="padding-left:18px">'+nameBlock+subBlock+sep+ctLines+socRow+extBlock+'</td>';
    out+='</tr></table>';
    return out; // logo already handled
  }

  return out;
}

// ── COPY ─────────────────────────────────────────────────────
function copySig(){
  if(!currentSig){toast('Sélectionnez une signature.','error');return}
  var html=buildSigHTML(currentSig.data);
  if(navigator.clipboard&&window.ClipboardItem){
    var blob=new Blob([html],{type:'text/html'});
    navigator.clipboard.write([new ClipboardItem({'text/html':blob})]).then(function(){toast('Signature copiée dans le presse-papier !','success')}).catch(function(){fallbackCopy(html)});
  } else { fallbackCopy(html); }
}
function fallbackCopy(html){var ta=document.getElementById('htmlExport');ta.value=html;ta.select();try{document.execCommand('copy');toast('HTML copié !','success')}catch(e){toast('Ouvrez l\'onglet HTML et copiez manuellement.','info')}}

// ── SAVE ─────────────────────────────────────────────────────
function saveSig(){
  var s=currentSig; if(!s) return;
  var fd=new FormData();
  fd.append('csrf_token',CSRF);fd.append('action','save');
  fd.append('id',s.id||0);fd.append('name',s.name);fd.append('data',JSON.stringify(s.data));
  fetch('/tools/signature.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){s.id=d.id;markSaved();toast('Signature sauvegardée !','success');refreshSBI(s)}
      else toast(d.error||'Erreur','error');
    });
}
function refreshSBI(s){
  var d=s.data;
  var isOwner=!s.share_permission;
  var subHtml=s.shared_by
    ?'<span class="shared-badge"><i class="fa fa-share-nodes"></i> '+esc(s.shared_by)+'</span>'
    :esc(trim(((d.firstName||'')+' '+(d.lastName||''))));
  var actHtml=isOwner
    ?'<button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openShare('+s.id+')" title="Partager"><i class="fa fa-share-nodes" style="color:var(--accent)"></i></button>'+
     '<button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delSig('+s.id+')" title="Supprimer"><i class="fa fa-trash" style="color:var(--error)"></i></button>'
    :'';
  var html='<div class="sb-item'+(currentSig&&currentSig.id===s.id?' active':'')+'\" id="sbi-'+s.id+'" onclick="selSig('+s.id+')">'+
    '<div class="sb-item-name">'+esc(s.name)+'</div>'+
    '<div class="sb-item-sub">'+subHtml+'</div>'+
    '<div class="sb-item-actions">'+actHtml+'</div>'+
  '</div>';
  var ex=document.getElementById('sbi-'+s.id);
  if(ex) ex.outerHTML=html;
  else {
    var sbl=document.getElementById('SBL');
    var empty=sbl.querySelector('.sb-empty');
    if(empty) empty.remove();
    sbl.insertAdjacentHTML('afterbegin',html);
  }
  var idx=sigData.findIndex(function(x){return x.id===s.id});
  if(idx>=0) sigData[idx]=JSON.parse(JSON.stringify(s)); else sigData.unshift(JSON.parse(JSON.stringify(s)));
}
function trim(s){return(s||'').trim()}

// ── DELETE ────────────────────────────────────────────────────
function delSig(id){
  if(!confirm('Supprimer cette signature ?')) return;
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','delete');fd.append('id',id);
  fetch('/tools/signature.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){
        var el=document.getElementById('sbi-'+id);if(el)el.remove();
        sigData=sigData.filter(function(x){return x.id!==id});
        if(currentSig&&currentSig.id===id){
          currentSig=null;
          document.getElementById('ED').innerHTML='<div class="ed-empty"><i class="fa fa-envelope-open-text"></i><h2>Signature Mail</h2><p>Créez ou sélectionnez une signature.</p><button class="btn btn-primary" onclick="openOv(\'MN\')"><i class="fa fa-plus"></i> Nouvelle</button></div>';
          document.getElementById('pvPreview').innerHTML='<div style="color:var(--dim);text-align:center;padding:40px 20px;font-size:13px"><i class="fa fa-envelope" style="font-size:32px;display:block;margin-bottom:10px;opacity:.2"></i>La signature apparaît ici</div>';
        }
        if(!sigData.length) document.getElementById('SBL').innerHTML='<div class="sb-empty"><i class="fa fa-envelope"></i>Aucune signature.</div>';
        toast('Signature supprimée.','info');
      }
    });
}

// ── CREATE ────────────────────────────────────────────────────
function createSig(){
  var nm=document.getElementById('nName').value.trim();
  if(!nm){toast('Nom requis','error');return}
  var s={id:0,name:nm,data:defData()};
  sigData.unshift(s);
  closeOv('MN');
  currentSig=JSON.parse(JSON.stringify(s));
  renderEditor();schPV();
  markUnsaved();
  toast('Signature créée — sauvegardez !','info');
  document.getElementById('nName').value='';
}

// ── SHARE ─────────────────────────────────────────────────────
function openShare(id){
  shareRid=id;
  var sl=document.getElementById('shareList');
  sl.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:10px">Chargement…</div>';
  openOv('MS');
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','list');fd.append('rtype','sig');fd.append('rid',id);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok) renderShareList(d.shares);
    else sl.innerHTML='<div style="font-size:12px;color:var(--error)">'+esc(d.error||'Erreur')+'</div>';
  });
}
function renderShareList(shares){
  var el=document.getElementById('shareList');
  if(!shares||!shares.length){el.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:10px">Aucun partage actif</div>';return;}
  el.innerHTML=shares.map(function(s){
    return '<div class="share-user-row">'+
      '<div class="share-user-av">'+esc((s.username||'?').charAt(0).toUpperCase())+'</div>'+
      '<div class="share-user-info"><div class="share-user-name">'+esc(s.username)+'</div><div class="share-user-email">'+esc(s.email)+'</div></div>'+
      '<span class="share-perm">'+(s.permission==='edit'?'Peut modifier':'Lecture seule')+'</span>'+
      '<button class="btn btn-danger btn-icon btn-sm" onclick="removeShare('+s.id+')" title="Retirer"><i class="fa fa-xmark"></i></button>'+
    '</div>';
  }).join('');
}
function doShare(){
  var email=document.getElementById('shareEmail').value.trim();
  var perm=document.getElementById('sharePerm').value;
  if(!email){toast('Email requis','error');return}
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','add');fd.append('rtype','sig');fd.append('rid',shareRid);fd.append('email',email);fd.append('permission',perm);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){document.getElementById('shareEmail').value='';openShare(shareRid);toast('Partagé avec '+d.user.username+'','success');}
    else toast(d.error||'Erreur','error');
  });
}
function removeShare(shareId){
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','remove');fd.append('rtype','sig');fd.append('rid',shareRid);fd.append('share_id',shareId);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){openShare(shareRid);toast('Accès retiré.','info');}
  });
}
</script>
</body>
</html>
