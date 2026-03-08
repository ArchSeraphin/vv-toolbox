<?php
/**
 * VV ToolBox — Carte de visite v2 (typographie & couleurs enrichies)
 */
require_once __DIR__ . '/../auth/session.php';
requireLogin(); checkSessionExpiry();
$user=$user=currentUser();$db=getDB();$uid=$user['id'];$isAdm=isAdmin();

if($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    if(!verifyCsrfToken($_POST['csrf_token']??'')){echo json_encode(['ok'=>false,'error'=>'Token invalide']);exit;}
    $action=$_POST['action']??'';
    if($action==='save'){
        $name=trim($_POST['name']??'');$slug=trim($_POST['slug']??'');$data=$_POST['data']??'{}';$id=(int)($_POST['id']??0);
        if(!$name||!$slug){echo json_encode(['ok'=>false,'error'=>'Nom et slug requis']);exit;}
        if(!preg_match('/^[a-z0-9\-]+$/',$slug)){echo json_encode(['ok'=>false,'error'=>'Slug invalide']);exit;}
        try{
            if($id){$db->prepare('UPDATE vcards SET name=?,slug=?,data_json=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$name,$slug,$data,$id,$uid]);echo json_encode(['ok'=>true,'id'=>$id]);}
            else{$db->prepare('INSERT INTO vcards (user_id,name,slug,data_json) VALUES (?,?,?,?)')->execute([$uid,$name,$slug,$data]);echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);}
        }catch(PDOException $e){echo json_encode(['ok'=>false,'error'=>'Slug déjà utilisé']);}exit;
    }
    if($action==='delete'){$id=(int)($_POST['id']??0);$db->prepare('DELETE FROM vcards WHERE id=? AND user_id=?')->execute([$id,$uid]);echo json_encode(['ok'=>true]);exit;}
    echo json_encode(['ok'=>false,'error'=>'Action inconnue']);exit;
}
$sql=$isAdm
    ?'SELECT *, NULL AS share_permission, NULL AS shared_by FROM vcards ORDER BY updated_at DESC'
    :'(SELECT *, NULL AS share_permission, NULL AS shared_by FROM vcards WHERE user_id=?)
      UNION ALL
      (SELECT v.*, s.permission AS share_permission, u_o.username AS shared_by
       FROM vcards v
       JOIN shares s ON s.resource_type=\'vc\' AND s.resource_id=v.id AND s.shared_with=?
       JOIN users u_o ON u_o.id=v.user_id)
      ORDER BY updated_at DESC';
$st=$db->prepare($sql);if(!$isAdm)$st->execute([$uid,$uid]);else $st->execute();
$vcList=$st->fetchAll();
$csrf=getCsrfToken();

// ── LAYOUT CONFIG ──────────────────────────────────────────
$navActive  = 'vc';
$navVc      = count($vcList);
$breadcrumb = [['Carte de visite', null]];
$tbActions  = '<button class="btn btn-primary btn-sm" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouvelle carte</button>';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carte de visite — VV ToolBox</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/layout.css">
<style>
/* ── PAGE vCard : surcharges spécifiques ─── */
:root { --accent: #8b5cf6; }

.img-toggle{display:flex;border:1px solid var(--border);border-radius:9px;overflow:hidden;margin-bottom:8px}
.img-toggle-btn{flex:1;padding:8px 0;font-size:12px;font-weight:600;font-family:'Geist',sans-serif;border:none;background:transparent;color:var(--muted);cursor:pointer;transition:all .15s;text-align:center}
.img-toggle-btn.act{background:var(--accent);color:#fff}

.shape-opts{display:flex;gap:6px}
.shape-opt{width:36px;height:36px;border:2px solid var(--border);background:var(--s2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.shape-opt:hover{border-color:var(--accent)}.shape-opt.act{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 10%,transparent)}
.shape-opt.circle-shape{border-radius:50%}.shape-opt.rounded-shape{border-radius:8px}.shape-opt.square-shape{border-radius:2px}
.shape-opt i{font-size:14px;color:var(--muted)}.shape-opt.act i{color:var(--accent)}

.pair-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.pair-card{border:2px solid var(--border);border-radius:10px;padding:10px 12px;cursor:pointer;transition:all .15s;background:var(--s2)}
.pair-card:hover{border-color:color-mix(in srgb,var(--accent) 50%,transparent)}
.pair-card.act{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 8%,transparent)}
.pair-name-preview{font-size:15px;font-weight:700;margin-bottom:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pair-sub-preview{font-size:11px;margin-bottom:6px;opacity:.7;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pair-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted)}

.del-zone{display:flex;justify-content:flex-end;padding:4px 20px 20px}

.pv{width:var(--pv-w);background:var(--surface);border-left:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:20px 14px;flex-shrink:0;overflow-y:auto;min-height:0}
.pv::-webkit-scrollbar{width:3px}.pv::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.pv-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--dim);align-self:flex-start;margin-bottom:16px}
.iphone{width:270px;background:#1c1c1e;border-radius:42px;padding:12px 10px;box-shadow:0 0 0 1.5px #3a3a3c,0 0 0 3px #111,0 24px 60px rgba(0,0,0,.7);position:relative;flex-shrink:0}
.ibtn{position:absolute;background:#2c2c2e;border-radius:2px}
.ibtn.pw{right:-3px;top:100px;width:3px;height:60px}.ibtn.mt{left:-3px;top:52px;width:3px;height:24px}
.ibtn.v1{left:-3px;top:86px;width:3px;height:42px}.ibtn.v2{left:-3px;top:138px;width:3px;height:42px}
.notch{width:80px;height:22px;background:#1c1c1e;border-radius:0 0 14px 14px;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;gap:5px;position:relative;z-index:2}
.npill{width:32px;height:4px;background:#2c2c2e;border-radius:4px}.ndot{width:8px;height:8px;background:#2c2c2e;border-radius:50%;border:1.5px solid #3a3a3c}
.screen{border-radius:28px;overflow:hidden;height:500px;background:#f0f0f0}
.screen iframe{width:100%;height:100%;border:none;display:block}
.pvb{margin-top:12px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap}

.sb-item-slug{font-size:11px;color:var(--dim);font-family:monospace}
</style>
</head>
<body>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="layout">

  <?php require __DIR__ . '/../includes/nav.php'; ?>

  <div class="sb">
    <div class="sbh">
      <span class="sbt">Mes cartes</span>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="openNewModal()"><i class="fa fa-plus"></i></button>
    </div>
    <div class="sb-list" id="SBL">
      <?php if(empty($vcList)):?>
        <div class="sb-empty"><i class="fa fa-id-card"></i>Aucune carte.<br>Créez-en une.</div>
      <?php else:foreach($vcList as $vc):
        $d=json_decode($vc['data_json'],true)?:[];
        $isOwner=($vc['share_permission']===null);
      ?>
        <div class="sb-item" id="sbi-<?=$vc['id']?>" onclick="selVC(<?=$vc['id']?>)">
          <div class="sb-item-name"><?=htmlspecialchars($vc['name'])?></div>
          <div class="sb-item-slug">
            <?php if(!$isOwner):?><span class="shared-badge"><i class="fa fa-share-nodes"></i> <?=htmlspecialchars($vc['shared_by'])?></span>
            <?php else:?>/<?=htmlspecialchars($vc['slug'])?><?php endif;?>
          </div>
          <div class="sb-item-dot" style="background:<?=htmlspecialchars($d['accent']??'#8b5cf6')?>"></div>
          <div class="sb-item-actions">
            <?php if($isOwner):?>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openShare(<?=$vc['id']?>)" title="Partager"><i class="fa fa-share-nodes" style="color:var(--accent)"></i></button>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delVC(<?=$vc['id']?>)" title="Supprimer"><i class="fa fa-trash" style="color:var(--error)"></i></button>
            <?php endif;?>
          </div>
        </div>
      <?php endforeach;endif;?>
    </div>
  </div>

  <div class="ed" id="ED">
    <div class="ed-empty">
      <i class="fa fa-id-card-clip"></i>
      <h2>Carte de visite</h2>
      <p>Créez votre carte de visite numérique personnalisée.</p>
      <button class="btn btn-primary" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouvelle carte</button>
    </div>
  </div>

  <div class="pv">
    <div class="pv-lbl">Aperçu live</div>
    <div class="iphone">
      <div class="ibtn pw"></div><div class="ibtn mt"></div><div class="ibtn v1"></div><div class="ibtn v2"></div>
      <div class="notch"><div class="npill"></div><div class="ndot"></div></div>
      <div class="screen"><iframe id="PF" srcdoc="<html><body style='display:flex;align-items:center;justify-content:center;height:100%;background:#f5f5f5;color:#aaa;font-family:sans-serif;font-size:12px;text-align:center;margin:0'>Sélectionnez<br>une carte</body></html>"></iframe></div>
    </div>
    <div class="pvb">
      <button class="btn btn-ghost btn-sm" onclick="openFull()"><i class="fa fa-external-link"></i> Plein écran</button>
      <button class="btn btn-ghost btn-sm" onclick="expActive()"><i class="fa fa-download"></i> Exporter</button>
    </div>
  </div>
</div>

<div class="ov" id="MN">
  <div class="modal">
    <h2>Nouvelle carte</h2>
    <p class="modal-desc">Nom interne et identifiant URL unique.</p>
    <div class="fld"><label>Nom interne</label><input type="text" id="nName" placeholder="Paul Dupont" oninput="autoSlugVC()"></div>
    <div class="fld" style="margin-top:10px"><label>Identifiant URL</label>
      <input type="text" id="nSlug" placeholder="paul-dupont" oninput="cleanSlugVC(this)">
      <div class="hint"><?=htmlspecialchars(APP_URL)?>/c/<em id="slugHint">...</em></div>
    </div>
    <div class="mf">
      <button class="btn btn-ghost" onclick="closeOv('MN')">Annuler</button>
      <button class="btn btn-primary" onclick="createVC()"><i class="fa fa-plus"></i> Créer</button>
    </div>
  </div>
</div>

<!-- Modal: Partage vCard -->
<div class="ov" id="MS">
  <div class="modal" style="width:500px">
    <h2><i class="fa fa-share-nodes" style="color:var(--accent);margin-right:8px"></i>Partager</h2>
    <p class="modal-desc">Invitez un autre utilisateur à accéder à cette carte de visite.</p>
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

<script src="/assets/layout.js"></script>
<script>
var CSRF    = <?=json_encode($csrf)?>;
var APP_URL = <?=json_encode(APP_URL)?>;
var vcData  = <?=json_encode(array_map(function($v){return['id'=>(int)$v['id'],'name'=>$v['name'],'slug'=>$v['slug'],'data'=>json_decode($v['data_json'],true)?:[],
  'share_permission'=>$v['share_permission'],'shared_by'=>$v['shared_by']];}, $vcList))?>;
var shareRid = 0;

var cur=null, ptmr=null;

function esc(s){return(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

/* ── FONT PAIRINGS ─────────────────────────────────────── */
/* Each pairing: display font for name/title, body font for rest */
var PAIRS=[
  {id:'syne-dm',    label:'Moderne',   nameF:'Syne',           nameC:"'Syne',sans-serif",          bodyF:'DM+Sans',      bodyC:"'DM Sans',sans-serif",         nameW:'800'},
  {id:'playfair-lato', label:'Classique', nameF:'Playfair+Display', nameC:"'Playfair Display',serif",   bodyF:'Lato',         bodyC:"'Lato',sans-serif",            nameW:'700'},
  {id:'fraunces-inter', label:'Éditorial', nameF:'Fraunces',      nameC:"'Fraunces',serif",            bodyF:'Inter',        bodyC:"'Inter',sans-serif",           nameW:'700'},
  {id:'space-geist', label:'Technique', nameF:'Space+Grotesk',  nameC:"'Space Grotesk',sans-serif",  bodyF:'Geist',        bodyC:"'Geist',sans-serif",           nameW:'700'},
  {id:'cormorant-source', label:'Luxe',  nameF:'Cormorant+Garamond', nameC:"'Cormorant Garamond',serif", bodyF:'Source+Sans+3', bodyC:"'Source Sans 3',sans-serif",  nameW:'600'},
  {id:'bebas-open',  label:'Impact',    nameF:'Bebas+Neue',     nameC:"'Bebas Neue',cursive",        bodyF:'Open+Sans',    bodyC:"'Open Sans',sans-serif",       nameW:'400'},
];

function loadPairFonts(p){
  [p.nameF,p.bodyF].forEach(function(f){
    var id='GF_'+f;
    if(!document.getElementById(id)){
      var l=document.createElement('link');l.id=id;l.rel='stylesheet';
      l.href='https://fonts.googleapis.com/css2?family='+f+':wght@400;600;700;800&display=swap';
      document.head.appendChild(l);
    }
  });
}

function defData(){
  return{
    firstName:'',lastName:'',company:'',jobTitle:'',
    imgType:'logo',logo:'',logoSize:80,photo:'',photoShape:'circle',photoBorder:true,
    ctaLabel:'',ctaUrl:'',phone:'',email:'',address:'',
    instagram:'',facebook:'',linkedin:'',twitter:'',tiktok:'',youtube:'',
    addToContacts:false,extras:[],
    /* Colors */
    bgColor:'#1a1a2e',cardBg:'#16213e',accent:'#e94560',
    nameColor:'#ffffff',titleColor:'#e94560',textColor:'#cccccc',
    /* Typography */
    pairId:'syne-dm',
    /* Card layout */
    cardRadius:24,
    showDivider:true
  };
}

function getPair(id){return PAIRS.find(function(p){return p.id===id})||PAIRS[0]}

/* ── SELECT ─────────────────────────────────────────────── */
function selVC(id){
  var v=vcData.find(function(x){return x.id===id});if(!v)return;
  cur=JSON.parse(JSON.stringify(v));
  document.querySelectorAll('.sb-item').forEach(function(el){el.classList.remove('active')});
  var si=document.getElementById('sbi-'+id);if(si)si.classList.add('active');
  rED();updPV();
}

/* ── EDITOR ─────────────────────────────────────────────── */
function rED(){
  var v=cur;if(!v)return;
  var d=v.data;
  var pair=getPair(d.pairId);
  PAIRS.forEach(loadPairFonts);

  var pairCards=PAIRS.map(function(p){
    loadPairFonts(p);
    return '<div class="pair-card'+(d.pairId===p.id?' act':'')+'" data-pair="'+p.id+'" onclick="setPair(\''+p.id+'\')" style="font-family:'+p.bodyC+'">'+
      '<div class="pair-name-preview" style="font-family:'+p.nameC+';font-weight:'+p.nameW+'">Jean Dupont</div>'+
      '<div class="pair-sub-preview">Directeur Commercial</div>'+
      '<div class="pair-label">'+p.label+'</div>'+
    '</div>';
  }).join('');

  var extH=d.extras&&d.extras.length?d.extras.map(function(b,i){return xRow(i,b)}).join(''):
    '<div style="font-size:12px;color:var(--dim);text-align:center;padding:4px">Aucun bouton secondaire</div>';

  document.getElementById('ED').innerHTML=
    '<div class="action-bar" id="actionBar">'+
      '<button class="btn btn-save" id="btnSave" onclick="saveVC()">'+
        '<span class="unsaved-dot"></span><i class="fa fa-floppy-disk"></i> <span id="btnSaveLbl">Sauvegarder</span>'+
      '</button>'+
      '<button class="btn btn-ghost btn-sm" onclick="expActive()"><i class="fa fa-download"></i> HTML</button>'+
      '<span class="unsaved-label" id="unsavedLbl">Modifications non sauvegardées</span>'+
      '<div style="flex:1"></div>'+
      '<button class="btn btn-danger btn-sm btn-icon" onclick="delVC('+(v.id||0)+')" title="Supprimer"><i class="fa fa-trash"></i></button>'+
    '</div>'+
    '<div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">'+

    mkSec('fa-user','Identité',true,
      '<div class="r2">'+mkF('Prénom','<input type="text" value="'+esc(d.firstName||'')+'" placeholder="Jean" oninput="upd(\'firstName\',this.value)">')+mkF('Nom','<input type="text" value="'+esc(d.lastName||'')+'" placeholder="Dupont" oninput="upd(\'lastName\',this.value)">')+'</div>'+
      '<div class="r2">'+mkF('Poste','<input type="text" value="'+esc(d.jobTitle||'')+'" placeholder="Directeur Commercial" oninput="upd(\'jobTitle\',this.value)">')+mkF('Société','<input type="text" value="'+esc(d.company||'')+'" placeholder="Acme Corp" oninput="upd(\'company\',this.value)">')+'</div>'+
      mkF('Slug / URL publique','<input type="text" value="'+esc(v.slug)+'" oninput="cur.slug=this.value.toLowerCase().replace(/[^a-z0-9-]/g,\'\')">'+
        '<div class="hint">'+esc(APP_URL)+'/c/<em>'+esc(v.slug)+'</em></div>')
    )+

    mkSec('fa-image','Image',false,
      '<div class="img-toggle">'+
        '<button class="img-toggle-btn'+(d.imgType!=='photo'?' act':'')+'" onclick="setImgType(\'logo\')"><i class="fa fa-image" style="margin-right:5px"></i>Logo</button>'+
        '<button class="img-toggle-btn'+(d.imgType==='photo'?' act':'')+'" onclick="setImgType(\'photo\')"><i class="fa fa-user-circle" style="margin-right:5px"></i>Photo</button>'+
      '</div>'+
      '<div id="PNL_logo" style="display:'+(d.imgType!=='photo'?'flex':'none')+';flex-direction:column;gap:10px">'+
        '<div class="ldrop" onclick="document.getElementById(\'LF_logo\').click()">'+
          (d.logo?'<img src="'+d.logo+'" alt="logo">':'<i class="fa fa-cloud-arrow-up" style="font-size:22px;color:var(--dim);display:block;margin-bottom:6px"></i>')+
          '<div class="ldrop-t">'+(d.logo?'Changer':'<strong>Cliquer</strong> pour uploader')+'</div>'+
          '<input type="file" id="LF_logo" accept="image/*" style="display:none" onchange="upImg(event,\'logo\')">'+
        '</div>'+
        (d.logo?'<button class="btn btn-danger btn-sm" onclick="upd(\'logo\',\'\');rED();updPV()"><i class="fa fa-trash"></i> Supprimer</button>':'')+
        mkF('Taille','<div class="sl-row"><input type="range" min="40" max="160" value="'+(d.logoSize||80)+'" oninput="upd(\'logoSize\',+this.value);document.getElementById(\'lszv\').textContent=this.value+\'px\';schPV()"><span class="sl-val" id="lszv">'+(d.logoSize||80)+'px</span></div>')+
      '</div>'+
      '<div id="PNL_photo" style="display:'+(d.imgType==='photo'?'flex':'none')+';flex-direction:column;gap:10px">'+
        '<div class="ldrop" onclick="document.getElementById(\'LF_photo\').click()">'+
          (d.photo?'<img src="'+d.photo+'" alt="photo" style="border-radius:50%;object-fit:cover;width:52px;height:52px">':'<i class="fa fa-user" style="font-size:22px;color:var(--dim);display:block;margin-bottom:6px"></i>')+
          '<div class="ldrop-t">'+(d.photo?'Changer':'<strong>Cliquer</strong> pour uploader')+'</div>'+
          '<input type="file" id="LF_photo" accept="image/*" style="display:none" onchange="upImg(event,\'photo\')">'+
        '</div>'+
        (d.photo?'<button class="btn btn-danger btn-sm" onclick="upd(\'photo\',\'\');rED();updPV()"><i class="fa fa-trash"></i> Supprimer</button>':'')+
        mkF('Forme',
          '<div class="shape-opts">'+
            '<div class="shape-opt circle-shape'+(d.photoShape==='circle'?' act':'')+'" onclick="setPhotoShape(\'circle\')" title="Cercle"><i class="fa fa-circle"></i></div>'+
            '<div class="shape-opt rounded-shape'+(d.photoShape==='rounded'?' act':'')+'" onclick="setPhotoShape(\'rounded\')" title="Arrondi"><i class="fa fa-square"></i></div>'+
            '<div class="shape-opt square-shape'+(d.photoShape==='square'?' act':'')+'" onclick="setPhotoShape(\'square\')" title="Carré"><i class="fa fa-stop"></i></div>'+
          '</div>'
        )+
        '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px"><input type="checkbox" '+(d.photoBorder?'checked':'')+' onchange="upd(\'photoBorder\',this.checked);schPV()" style="width:auto;accent-color:var(--accent)"> Bordure accent</label>'+
      '</div>'
    )+

    mkSec('fa-link','Bouton principal (CTA)',true,
      mkF('Texte','<input type="text" value="'+esc(d.ctaLabel||'')+'" placeholder="Visiter notre site" oninput="upd(\'ctaLabel\',this.value)">')+
      mkF('URL','<input type="url" value="'+esc(d.ctaUrl||'')+'" placeholder="https://..." oninput="upd(\'ctaUrl\',this.value)">')
    )+

    mkSec('fa-address-book','Coordonnées',true,
      mkF('Téléphone','<input type="tel" value="'+esc(d.phone||'')+'" placeholder="06 00 00 00 00" oninput="upd(\'phone\',this.value)">')+
      mkF('Email','<input type="email" value="'+esc(d.email||'')+'" placeholder="contact@domaine.fr" oninput="upd(\'email\',this.value)">')+
      mkF('Adresse','<textarea placeholder="7 Rue Basse, 02820 Sainte-Croix" oninput="upd(\'address\',this.value)">'+esc(d.address||'')+'</textarea>')
    )+

    mkSec('fa-brands fa-instagram','Réseaux sociaux',false,
      sRow('fa-brands fa-instagram','Instagram','instagram',d.instagram)+
      sRow('fa-brands fa-facebook','Facebook','facebook',d.facebook)+
      sRow('fa-brands fa-linkedin','LinkedIn','linkedin',d.linkedin)+
      sRow('fa-brands fa-x-twitter','X / Twitter','twitter',d.twitter)+
      sRow('fa-brands fa-tiktok','TikTok','tiktok',d.tiktok)+
      sRow('fa-brands fa-youtube','YouTube','youtube',d.youtube)
    )+

    mkSec('fa-grip','Boutons secondaires',false,
      '<div id="XC">'+extH+'</div>'+
      '<button class="btn btn-ghost btn-sm" style="margin-top:6px" onclick="addX()"><i class="fa fa-plus"></i> Ajouter</button>'
    )+

    mkSec('fa-address-card','Ajouter aux contacts',false,
      '<label style="display:flex;align-items:flex-start;gap:10px;background:var(--s2);padding:12px;border-radius:9px;border:1px solid var(--border);cursor:pointer">'+
        '<input type="checkbox" '+(d.addToContacts?'checked':'')+' onchange="upd(\'addToContacts\',this.checked);schPV()" style="width:auto;accent-color:var(--accent);margin-top:2px;flex-shrink:0">'+
        '<div><span style="font-size:13px;font-weight:500;display:block">Afficher le bouton « Ajouter aux contacts »</span>'+
        '<span style="font-size:11px;color:var(--muted);line-height:1.5;display:block;margin-top:2px">Génère un fichier .vcf téléchargeable directement sur mobile.</span></div>'+
      '</label>'
    )+

    mkSec('fa-palette','Typographie',true,
      '<div class="pair-grid" id="pairGrid">'+pairCards+'</div>'
    )+

    mkSec('fa-droplet','Couleurs',true,
      '<div class="r2">'+mkCF('Fond page','bgColor',d.bgColor||'#1a1a2e')+mkCF('Fond carte','cardBg',d.cardBg||'#16213e')+'</div>'+
      '<div class="r2">'+mkCF('Accent','accent',d.accent||'#e94560')+mkCF('Couleur nom','nameColor',d.nameColor||'#ffffff')+'</div>'+
      '<div class="r2">'+mkCF('Poste / Société','titleColor',d.titleColor||'#e94560')+mkCF('Texte contact','textColor',d.textColor||'#cccccc')+'</div>'+
      '<div style="margin-top:4px">'+
        mkF('Arrondi de la carte',
          '<div class="sl-row"><input type="range" min="0" max="40" value="'+(d.cardRadius!==undefined?d.cardRadius:24)+'" oninput="upd(\'cardRadius\',+this.value);document.getElementById(\'cradv\').textContent=this.value+\'px\';schPV()"><span class="sl-val" id="cradv">'+(d.cardRadius!==undefined?d.cardRadius:24)+'px</span></div>'
        )+
      '</div>'+
      '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">'+
        '<input type="checkbox" '+(d.showDivider!==false?'checked':'')+' onchange="upd(\'showDivider\',this.checked);schPV()" style="width:auto;accent-color:var(--accent)">'+
        'Afficher le séparateur'+
      '</label>'
    )+

    '<div class="del-zone"><button class="btn btn-danger btn-sm" onclick="delVC('+(v.id||0)+')"><i class="fa fa-trash"></i> Supprimer cette carte</button></div>'+
    '</div>'; /* close content wrapper */
}


/* ── HTML BUILDERS ─────────────────────────────────────── */
function mkSec(ico,lbl,open,body){
  var id='VS_'+lbl.replace(/\W/g,'_');
  return '<div class="sec"><div class="sec-h '+(open?'open':'')+'" onclick="togSec(\''+id+'\')" id="h_'+id+'"><i class="fa '+ico+' sec-ico"></i><span class="sec-lbl">'+lbl+'</span><i class="fa fa-chevron-down sec-arr"></i></div><div class="sec-b" id="b_'+id+'" style="'+(open?'':'display:none')+'">'+body+'</div></div>';
}
function togSec(id){var h=document.getElementById('h_'+id),b=document.getElementById('b_'+id);if(!h||!b)return;var o=h.classList.toggle('open');b.style.display=o?'':'none'}
function mkF(lbl,inp){return '<div class="fld"><label>'+lbl+'</label>'+inp+'</div>'}
function mkCF(lbl,key,val){
  return '<div class="fld"><label>'+lbl+'</label><div class="col-row">'+
    '<div class="col-swatch" id="sw_'+key+'" style="background:'+val+'"><input type="color" value="'+val+'" oninput="updColor(\''+key+'\',this.value)"></div>'+
    '<input type="text" class="col-hex" id="hx_'+key+'" value="'+val+'" maxlength="7" onchange="updColorHx(\''+key+'\',this.value)">'+
  '</div></div>';
}
function sRow(ico,ph,key,val){return '<div class="srow"><i class="'+ico+'"></i><input type="url" value="'+esc(val||'')+'" placeholder="'+ph+'" oninput="upd(\''+key+'\',this.value)"></div>'}
function xRow(i,b){return '<div class="xrow"><input type="text" value="'+esc(b.label)+'" placeholder="Texte" style="flex:1" oninput="updX('+i+',\'label\',this.value)"><span class="sp">|</span><input type="url" value="'+esc(b.url||'')+'" placeholder="https://..." style="flex:2" oninput="updX('+i+',\'url\',this.value)"><button class="xdel" onclick="rmX('+i+')"><i class="fa fa-xmark"></i></button></div>'}

/* ── UPDATE ─────────────────────────────────────────────── */
function upd(k,v){if(!cur)return;cur.data[k]=v;markUnsaved();schPV()}
function updColor(k,v){upd(k,v);var sw=document.getElementById('sw_'+k),hx=document.getElementById('hx_'+k);if(sw)sw.style.background=v;if(hx)hx.value=v}
function updColorHx(k,v){if(!/^#[0-9a-fA-F]{6}$/.test(v))return;updColor(k,v)}
function setPair(id){
  if(!cur)return;cur.data.pairId=id;
  document.querySelectorAll('.pair-card').forEach(function(el){el.classList.toggle('act',el.dataset.pair===id)});
  schPV();
}
function setImgType(t){upd('imgType',t);var pl=document.getElementById('PNL_logo'),pp=document.getElementById('PNL_photo');if(pl)pl.style.display=t==='logo'?'flex':'none';if(pp)pp.style.display=t==='photo'?'flex':'none';document.querySelectorAll('.img-toggle-btn').forEach(function(b,i){b.classList.toggle('act',i===(t==='logo'?0:1))});schPV()}
function setPhotoShape(s){upd('photoShape',s);document.querySelectorAll('.shape-opt').forEach(function(el){el.classList.toggle('act',el.getAttribute('onclick')==="setPhotoShape('"+s+"')")});schPV()}
function addX(){if(!cur)return;if(!cur.data.extras)cur.data.extras=[];cur.data.extras.push({label:'',url:''});reXC();schPV()}
function rmX(i){if(!cur)return;cur.data.extras.splice(i,1);reXC();schPV()}
function updX(i,k,v){if(!cur)return;cur.data.extras[i][k]=v;schPV()}
function reXC(){var el=document.getElementById('XC');if(!el)return;var d=cur.data;if(!d.extras||!d.extras.length){el.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:4px">Aucun bouton secondaire</div>';return;}el.innerHTML=d.extras.map(function(b,i){return xRow(i,b)}).join('')}
function upImg(ev,key){var f=ev.target.files[0];if(!f)return;var r=new FileReader();r.onload=function(e){upd(key,e.target.result);rED();updPV()};r.readAsDataURL(f)}

/* ── PREVIEW ─────────────────────────────────────────────── */
function schPV(){clearTimeout(ptmr);ptmr=setTimeout(updPV,160)}
function updPV(){var v=cur;if(!v)return;document.getElementById('PF').srcdoc=bldHTML(v)}
function openFull(){var v=cur;if(!v)return;var w=window.open('','_blank');w.document.write(bldHTML(v));w.document.close()}

/* ── BUILD CARD HTML ─────────────────────────────────────── */
function bldHTML(v){
  var c=v.data;
  var pair=getPair(c.pairId);
  var fn=[c.firstName,c.lastName].filter(Boolean).join(' ');

  /* Font import for both display + body font */
  var fi="@import url('https://fonts.googleapis.com/css2?family="+pair.nameF+":wght@400;600;700;800&family="+pair.bodyF+":wght@300;400;500;600&display=swap');";

  /* Colours */
  var ac      = c.accent      || '#e94560';
  var bgCol   = c.bgColor     || '#1a1a2e';
  var cardBg  = c.cardBg      || '#16213e';
  var nameCol = c.nameColor   || '#ffffff';
  var titCol  = c.titleColor  || ac;
  var txtCol  = c.textColor   || '#cccccc';
  var radius  = (c.cardRadius!==undefined?c.cardRadius:24)+'px';

  /* Image block */
  var imgSrc=c.imgType==='photo'?c.photo:c.logo;
  var imgHtml='';
  if(imgSrc){
    if(c.imgType==='photo'){
      var pr=c.photoShape==='circle'?'50%':c.photoShape==='rounded'?'16px':'4px';
      var pb=c.photoBorder?'border:3px solid '+ac+';':'';
      imgHtml='<div class="iw"><img src="'+imgSrc+'" style="border-radius:'+pr+';'+pb+'width:96px;height:96px;object-fit:cover;display:block" alt="photo"></div>';
    } else {
      var sz=(c.logoSize||80)+'px';
      imgHtml='<div class="iw"><img src="'+imgSrc+'" style="width:'+sz+';height:'+sz+';object-fit:contain;display:block" alt="logo"></div>';
    }
  }

  /* Socials */
  var soc=[
    {i:'fa-brands fa-instagram',u:c.instagram},{i:'fa-brands fa-facebook',u:c.facebook},
    {i:'fa-brands fa-linkedin',u:c.linkedin},{i:'fa-brands fa-x-twitter',u:c.twitter},
    {i:'fa-brands fa-tiktok',u:c.tiktok},{i:'fa-brands fa-youtube',u:c.youtube}
  ].filter(function(s){return s.u&&s.u.trim()});
  var sh=soc.length?'<div class="so">'+soc.map(function(s){return'<a href="'+s.u+'" target="_blank" rel="noopener"><i class="'+s.i+'"></i></a>';}).join('')+'</div>':'';

  /* Extras */
  var ex=(c.extras||[]).filter(function(b){return b.label});
  var xh=ex.length?'<div class="xe">'+ex.map(function(b){return'<a href="'+(b.url||'#')+'" class="bsec" target="_blank" rel="noopener">'+b.label+'</a>';}).join('')+'</div>':'';

  /* Contacts */
  var ct='';
  if(c.phone)ct+='<div class="ci"><span class="cl">Téléphone</span><a href="tel:'+c.phone.replace(/\s/g,'')+'" class="cv">'+c.phone+'</a></div>';
  if(c.email)ct+='<div class="ci"><span class="cl">Email</span><a href="mailto:'+c.email+'" class="cv">'+c.email+'</a></div>';
  if(c.address)ct+='<div class="ci"><span class="cl">Adresse</span><a href="https://maps.google.com/?q='+encodeURIComponent(c.address)+'" target="_blank" class="cv addr">'+c.address.replace(/\n/g,'<br>')+'</a></div>';

  /* vCard button */
  var vcfBtn='';
  if(c.addToContacts){
    var vcfLines=['BEGIN:VCARD','VERSION:3.0'];
    if(fn)vcfLines.push('FN:'+fn);
    if(c.lastName||c.firstName)vcfLines.push('N:'+(c.lastName||'')+';'+(c.firstName||'')+';;;');
    if(c.company)vcfLines.push('ORG:'+c.company);
    if(c.phone)vcfLines.push('TEL;TYPE=CELL:'+c.phone.replace(/\s/g,''));
    if(c.email)vcfLines.push('EMAIL:'+c.email);
    if(c.address)vcfLines.push('ADR:;;'+c.address+';;;;');
    vcfLines.push('URL:'+APP_URL+'/c/'+v.slug);
    vcfLines.push('END:VCARD');
    vcfBtn='<a href="data:text/vcard;charset=utf-8,'+encodeURIComponent(vcfLines.join('\n'))+'" download="'+(fn||'contact')+'.vcf" class="bsec" style="margin-bottom:8px">📇 Ajouter aux contacts</a>';
  }

  /* Subtitle line (poste · société) */
  var subtitle=[c.jobTitle,c.company].filter(Boolean).join(' · ');

  return'<!DOCTYPE html>\n<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1"><title>'+(fn||c.company||v.name)+'</title>\n'+
    '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">\n'+
    '<style>\n'+fi+'\n'+
    '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}\n'+
    'body{font-family:'+pair.bodyC+';background:'+bgCol+';min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px 16px 48px}\n'+
    '.card{background:'+cardBg+';border-radius:'+radius+';width:100%;max-width:400px;padding:40px 28px 32px;display:flex;flex-direction:column;align-items:center;box-shadow:0 24px 80px rgba(0,0,0,.35)}\n'+
    '.iw{margin-bottom:20px;display:flex;justify-content:center}\n'+
    '.nm{font-family:'+pair.nameC+';font-size:26px;font-weight:'+pair.nameW+';color:'+nameCol+';text-align:center;line-height:1.2;margin-bottom:'+(subtitle?'4px':'20px')+'}\n'+
    '.jt{font-size:12px;color:'+titCol+';text-align:center;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:20px}\n'+
    '.bm{display:block;width:100%;padding:14px 20px;background:'+ac+';color:#fff;text-align:center;border-radius:50px;font-family:inherit;font-size:13px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:24px;transition:opacity .15s,transform .15s}\n'+
    '.bm:hover{opacity:.85;transform:translateY(-1px)}\n'+
    (c.showDivider!==false?'.dv{width:60px;height:1px;background:'+ac+'55;margin:0 auto 24px}\n':'')+
    '.cts{width:100%;display:flex;flex-direction:column;gap:18px;margin-bottom:28px}\n'+
    '.ci{text-align:center}\n'+
    '.cl{display:block;font-size:10px;font-weight:700;letter-spacing:1.5px;color:'+ac+'99;margin-bottom:3px;text-transform:uppercase}\n'+
    '.cv{display:block;font-size:16px;font-weight:600;color:'+txtCol+';text-decoration:none;line-height:1.4;transition:color .15s}\n'+
    '.cv:hover{color:'+ac+'}\n'+
    '.addr{font-size:14px;font-weight:400}\n'+
    '.so{display:flex;gap:20px;justify-content:center;margin-bottom:28px}\n'+
    '.so a{font-size:22px;color:'+txtCol+';text-decoration:none;transition:color .15s,transform .15s}\n'+
    '.so a:hover{color:'+ac+';transform:scale(1.15)}\n'+
    '.xe{width:100%;display:flex;flex-direction:column;gap:10px;margin-bottom:10px}\n'+
    '.bsec{display:block;width:100%;padding:12px 20px;background:transparent;border:2px solid '+ac+';color:'+ac+';text-align:center;border-radius:50px;font-family:inherit;font-size:13px;font-weight:600;text-decoration:none;text-transform:uppercase;letter-spacing:1px;transition:all .15s}\n'+
    '.bsec:hover{background:'+ac+'20;transform:translateY(-1px)}\n'+
    '</style></head><body><div class="card">\n'+
    imgHtml+'\n'+
    (fn?'<div class="nm">'+fn+'</div>\n':'')+
    (subtitle?'<div class="jt">'+subtitle+'</div>\n':'')+
    (c.ctaLabel?'<a href="'+(c.ctaUrl||'#')+'" class="bm" target="_blank">'+c.ctaLabel+'</a>\n':'')+
    (c.showDivider!==false?'<div class="dv"></div>\n':'')+
    (ct?'<div class="cts">'+ct+'</div>\n':'')+
    sh+'\n'+xh+'\n'+vcfBtn+
    '</div></body></html>';
}

/* ── SAVE ────────────────────────────────────────────────── */
function saveVC(){
  var v=cur;if(!v)return;
  var fd=new FormData();
  fd.append('csrf_token',CSRF);fd.append('action','save');
  fd.append('id',v.id||0);fd.append('name',v.name);fd.append('slug',v.slug);
  fd.append('data',JSON.stringify(v.data));
  fetch('/tools/vcard.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){v.id=d.id;markSaved();toast('Carte sauvegardée !','success');rebuildSBI(v)}
      else toast(d.error||'Erreur','error');
    });
}

function rebuildSBI(v){
  var h='<div class="sb-item active" id="sbi-'+v.id+'" onclick="selVC('+v.id+')">'+
    '<div class="sb-item-name">'+esc(v.name)+'</div>'+
    '<div class="sb-item-slug">/'+esc(v.slug)+'</div>'+
    '<div class="sb-item-dot" style="background:'+(v.data.accent||'#8b5cf6')+'"></div>'+
    '<div class="sb-item-actions"><button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delVC('+v.id+')"><i class="fa fa-trash" style="color:var(--error)"></i></button></div>'+
  '</div>';
  var ex=document.getElementById('sbi-'+v.id);
  if(ex)ex.outerHTML=h; else{var l=document.getElementById('SBL');l.innerHTML=h+l.innerHTML.replace(/<div class="sb-empty">[\s\S]*?<\/div>/,'')}
  document.querySelectorAll('.sb-item').forEach(function(el){el.classList.remove('active')});
  var si=document.getElementById('sbi-'+v.id);if(si)si.classList.add('active');
  var idx=vcData.findIndex(function(x){return x.id===v.id});
  if(idx>=0)vcData[idx]=JSON.parse(JSON.stringify(v));else vcData.unshift(JSON.parse(JSON.stringify(v)));
}

/* ── DELETE ─────────────────────────────────────────────── */
function delVC(id){
  if(!confirm('Supprimer cette carte ?'))return;
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','delete');fd.append('id',id);
  fetch('/tools/vcard.php',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){
        var el=document.getElementById('sbi-'+id);if(el)el.remove();
        vcData=vcData.filter(function(x){return x.id!==id});
        if(cur&&cur.id===id){
          cur=null;
          document.getElementById('ED').innerHTML='<div class="ed-empty"><i class="fa fa-id-card-clip"></i><h2>Carte de visite</h2><p>Sélectionnez ou créez une carte.</p><button class="btn btn-primary" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouvelle</button></div>';
          document.getElementById('PF').srcdoc="<html><body style='display:flex;align-items:center;justify-content:center;height:100%;background:#f5f5f5;color:#aaa;font-family:sans-serif;font-size:12px;text-align:center;margin:0'>Sélectionnez<br>une carte</body></html>";
        }
        if(!vcData.length)document.getElementById('SBL').innerHTML='<div class="sb-empty"><i class="fa fa-id-card"></i>Aucune carte.</div>';
        toast('Carte supprimée.','info');
      }
    });
}

/* ── EXPORT ─────────────────────────────────────────────── */
function expActive(){
  var v=cur;if(!v){toast('Sélectionnez une carte.','error');return}
  var bl=new Blob([bldHTML(v)],{type:'text/html;charset=utf-8'});
  var a=document.createElement('a');a.href=URL.createObjectURL(bl);a.download=v.slug+'.html';a.click();
  toast('"'+v.slug+'.html" téléchargé !','success');
}

/* ── MODAL ──────────────────────────────────────────────── */
function openNewModal(){document.getElementById('nName').value='';document.getElementById('nSlug').value='';document.getElementById('slugHint').textContent='...';openOv('MN');setTimeout(function(){document.getElementById('nName').focus()},80)}
function autoSlugVC(){var v=document.getElementById('nName').value;var s=v.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('nSlug').value=s;document.getElementById('slugHint').textContent=s||'...'}
function cleanSlugVC(el){el.value=el.value.toLowerCase().replace(/[^a-z0-9-]/g,'');document.getElementById('slugHint').textContent=el.value||'...'}
function createVC(){
  var name=document.getElementById('nName').value.trim(),slug=document.getElementById('nSlug').value.trim();
  if(!name||!slug){toast('Remplissez tous les champs.','error');return}
  cur={id:0,name:name,slug:slug,data:defData()};
  closeOv('MN');rED();updPV();toast('Carte créée. Personnalisez et sauvegardez.','info');
}

// ── SHARE ─────────────────────────────────────────────────────
function openShare(id){
  shareRid=id;
  var sl=document.getElementById('shareList');
  sl.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:10px">Chargement…</div>';
  openOv('MS');
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','list');fd.append('rtype','vc');fd.append('rid',id);
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
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','add');fd.append('rtype','vc');fd.append('rid',shareRid);fd.append('email',email);fd.append('permission',perm);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){document.getElementById('shareEmail').value='';openShare(shareRid);toast('Partagé avec '+d.user.username,'success');}
    else toast(d.error||'Erreur','error');
  });
}
function removeShare(shareId){
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','remove');fd.append('rtype','vc');fd.append('rid',shareRid);fd.append('share_id',shareId);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){openShare(shareRid);toast('Accès retiré.','info');}
  });
}

</script>
</body>
</html>
