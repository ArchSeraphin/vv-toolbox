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
                $db->prepare('INSERT INTO qr_codes (user_id,name,slug,target_url,options_json,is_active) VALUES (?,?,?,?,?,1)')->execute([$uid,$name,$slug,$url,$opts]);
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
    ? 'SELECT q.*,u.username, NULL AS share_permission, NULL AS shared_by FROM qr_codes q JOIN users u ON u.id=q.user_id ORDER BY q.updated_at DESC'
    : '(SELECT q.*,u.username, NULL AS share_permission, NULL AS shared_by FROM qr_codes q JOIN users u ON u.id=q.user_id WHERE q.user_id=?)
       UNION ALL
       (SELECT q.*,u.username, s.permission AS share_permission, u.username AS shared_by
        FROM qr_codes q JOIN users u ON u.id=q.user_id
        JOIN shares s ON s.resource_type=\'qr\' AND s.resource_id=q.id AND s.shared_with=?)
       ORDER BY updated_at DESC';
$st = $db->prepare($sql);
if (!$isAdm) $st->execute([$uid, $uid]); else $st->execute();
$qrList = $st->fetchAll();
$csrf = getCsrfToken();

// ── LAYOUT CONFIG ──────────────────────────────────────────
$navActive  = 'qr';
$navQr      = count($qrList); // évite une requête supplémentaire
$breadcrumb = [['QR Code', null]];
$tbActions  = '<button class="btn btn-primary btn-sm" onclick="openNewModal()"><i class="fa fa-plus"></i> Nouveau QR</button>';
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
<link rel="stylesheet" href="/assets/layout.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
/* ── PAGE QR : surcharges spécifiques ─── */
.checker{background-image:repeating-conic-gradient(#bbb 0% 25%,#fff 0% 50%);background-size:8px 8px}
[data-theme="dark"] .checker{background-image:repeating-conic-gradient(#444 0% 25%,#222 0% 50%)}

.dot-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.dot-opt{padding:9px 6px 8px;border-radius:9px;border:1px solid var(--border);background:var(--s2);cursor:pointer;font-size:11px;font-weight:600;color:var(--muted);transition:all .15s;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px}
.dot-opt:hover{border-color:color-mix(in srgb,var(--accent) 50%,transparent)}
.dot-opt.act{border-color:var(--accent);background:color-mix(in srgb,var(--accent) 10%,transparent);color:var(--accent)}
.dot-preview{width:38px;height:38px;border-radius:4px}


.pv{width:var(--pv-w);background:var(--surface);border-left:1px solid var(--border);display:flex;flex-direction:column;align-items:center;padding:20px 16px;flex-shrink:0;overflow-y:auto;gap:0;min-height:0}
.pv::-webkit-scrollbar{width:3px}.pv::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.pv-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--dim);align-self:flex-start;margin-bottom:16px}
.qr-box{background:var(--s2);border:1px solid var(--border);border-radius:16px;padding:22px;display:flex;flex-direction:column;align-items:center;gap:14px;width:100%}
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
</style>
</head>
<body>

<?php require __DIR__ . '/../includes/topbar.php'; ?>

<div class="layout">

  <?php require __DIR__ . '/../includes/nav.php'; ?>

  <div class="sb">
    <div class="sbh">
      <span class="sbt">Mes QR Codes</span>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="openNewModal()"><i class="fa fa-plus"></i></button>
    </div>
    <div class="sb-list" id="SBL">
      <?php if(empty($qrList)):?>
        <div class="sb-empty"><i class="fa fa-qrcode"></i>Aucun QR code.<br>Créez-en un.</div>
      <?php else:foreach($qrList as $qr):
        $opts=json_decode($qr['options_json']??'{}',true)?:[];
        $isOwner=($qr['share_permission']===null);
      ?>
        <div class="sb-item" id="sbi-<?=$qr['id']?>" onclick="selQR(<?=$qr['id']?>)">
          <div class="sb-item-name"><?=htmlspecialchars($qr['name'])?></div>
          <div class="sb-item-sub">
            <?php if(!$isOwner):?><span class="shared-badge"><i class="fa fa-share-nodes"></i> <?=htmlspecialchars($qr['shared_by'])?></span>
            <?php else:?><?=htmlspecialchars(parse_url($qr['target_url'],PHP_URL_HOST)?:'')?><?php endif;?>
          </div>
          <div class="sb-item-dot" style="background:<?=htmlspecialchars($opts['fgColor']??'#4f6ef7')?>"></div>
          <div class="sb-item-actions">
            <?php if($isOwner):?>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();openShare(<?=$qr['id']?>)" title="Partager"><i class="fa fa-share-nodes" style="color:var(--accent)"></i></button>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation();delQR(<?=$qr['id']?>)" title="Supprimer"><i class="fa fa-trash" style="color:var(--error)"></i></button>
            <?php endif;?>
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

<!-- Modal: Partage QR -->
<div class="ov" id="MS">
  <div class="modal" style="width:500px">
    <h2><i class="fa fa-share-nodes" style="color:var(--accent);margin-right:8px"></i>Partager</h2>
    <p class="modal-desc">Invitez un autre utilisateur à accéder à ce QR Code.</p>
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
var qrData  = <?=json_encode(array_map(function($q){
  $o=json_decode($q['options_json']??'{}',true)?:[];
  return['id'=>(int)$q['id'],'name'=>$q['name'],'slug'=>$q['slug'],
    'target_url'=>$q['target_url'],'scan_count'=>(int)$q['scan_count'],'opts'=>$o,
    'share_permission'=>$q['share_permission'],'shared_by'=>$q['shared_by']];
},$qrList))?>;
var shareRid = 0;

var cur=null, deb=null;

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
    var cx=ex+ew/2, cy=ey+ew/2;

    /* clear area */
    if(trans) ctx.clearRect(ex,ey,ew,ew);
    else{ctx.fillStyle=bg;ctx.fillRect(ex,ey,ew,ew);}

    ctx.fillStyle=fg;
    if(eye==='dot'){
      /* outer circle */
      ctx.beginPath();ctx.arc(cx,cy,ew/2,0,Math.PI*2);ctx.fill();
      /* inner white circle */
      if(trans){
        ctx.save();ctx.globalCompositeOperation='destination-out';
        ctx.beginPath();ctx.arc(cx,cy,ew/2-cellSz,0,Math.PI*2);ctx.fill();
        ctx.restore();
      } else {
        ctx.fillStyle=bg;ctx.beginPath();ctx.arc(cx,cy,ew/2-cellSz,0,Math.PI*2);ctx.fill();
      }
      /* inner dot circle */
      ctx.fillStyle=fg;ctx.beginPath();ctx.arc(cx,cy,1.5*cellSz,0,Math.PI*2);ctx.fill();
    } else {
      var rad=eye==='rounded'?ew*0.25:0;
      var irad=eye==='rounded'?cellSz*0.5:0;
      var drad=eye==='rounded'?cellSz*0.4:0;
      /* outer */
      if(rad>0) roundRect(ctx,ex,ey,ew,ew,rad);
      else ctx.fillRect(ex,ey,ew,ew);
      /* inner white */
      if(trans) ctx.clearRect(ex+cellSz,ey+cellSz,5*cellSz,5*cellSz);
      else{ctx.fillStyle=bg;if(irad>0) roundRect(ctx,ex+cellSz,ey+cellSz,5*cellSz,5*cellSz,irad); else ctx.fillRect(ex+cellSz,ey+cellSz,5*cellSz,5*cellSz);}
      /* inner dot */
      ctx.fillStyle=fg;
      if(drad>0) roundRect(ctx,ex+2*cellSz,ey+2*cellSz,3*cellSz,3*cellSz,drad);
      else ctx.fillRect(ex+2*cellSz,ey+2*cellSz,3*cellSz,3*cellSz);
    }
  }

  drawEye(0,0); drawEye(0,N-7); drawEye(N-7,0);

  /* logo overlay — aspect ratio préservé (dimensions lues à l'upload) */
  if(opts.logoData){
    var img=new Image();
    img.onload=function(){
      var lp=(opts.logoSize||30)/100;
      var maxSz=SIZE*lp;
      /* Utilise les dimensions stockées à l'upload; fallback sur naturalWidth */
      var nw=opts.logoW||img.naturalWidth||img.width||1;
      var nh=opts.logoH||img.naturalHeight||img.height||1;
      var ratio=nw/nh;
      var lw=ratio>=1?maxSz:maxSz*ratio;
      var lh=ratio>=1?maxSz/ratio:maxSz;
      var lx=(SIZE-lw)/2,ly=(SIZE-lh)/2;
      var pad=5;
      if(trans) ctx.clearRect(lx-pad,ly-pad,lw+pad*2,lh+pad*2);
      else{ctx.fillStyle=bg;ctx.fillRect(lx-pad,ly-pad,lw+pad*2,lh+pad*2);}
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
var EYE_STYLES=[
  {k:'square',l:'Carré'},
  {k:'rounded',l:'Arrondi'},
  {k:'dot',l:'Cercle'},
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
      (q.share_permission===null?'<button class="btn btn-ghost btn-sm" onclick="openShare('+q.id+')"><i class="fa fa-share-nodes"></i> Partager</button>':'')+
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
      '<div class="dot-grid" id="eyeGrid">'+
        EYE_STYLES.map(function(e){
          return '<div class="dot-opt'+(eye===e.k?' act':'')+'" data-eye="'+e.k+'" onclick="setEye(\''+e.k+'\')">'+
            '<canvas class="dot-preview" id="ep_'+e.k+'"></canvas>'+e.l+'</div>';
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
  setTimeout(function(){drawPreviews();drawEyePreviews();},40);
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

/* Draw tiny eye-style previews */
function drawEyePreviews(){
  var fg=(cur&&cur.opts&&cur.opts.fgColor)||'#4f6ef7';
  var bg='#ffffff';
  EYE_STYLES.forEach(function(e){
    var c=document.getElementById('ep_'+e.k);if(!c)return;
    c.width=38;c.height=38;
    var ctx=c.getContext('2d');
    var sz=28,ox=5,oy=5; // eye area 28×28px at offset 5,5
    var cell=sz/7;
    var cx=ox+sz/2,cy=oy+sz/2;
    ctx.clearRect(0,0,38,38);
    ctx.fillStyle=fg;
    if(e.k==='dot'){
      ctx.beginPath();ctx.arc(cx,cy,sz/2,0,Math.PI*2);ctx.fill();
      ctx.fillStyle=bg;ctx.beginPath();ctx.arc(cx,cy,sz/2-cell,0,Math.PI*2);ctx.fill();
      ctx.fillStyle=fg;ctx.beginPath();ctx.arc(cx,cy,1.5*cell,0,Math.PI*2);ctx.fill();
    } else {
      var r=e.k==='rounded'?sz*0.25:0;
      var ir=e.k==='rounded'?cell*0.5:0;
      var dr=e.k==='rounded'?cell*0.4:0;
      /* outer */
      if(r>0){ctx.beginPath();drawRR(ctx,ox,oy,sz,sz,r);ctx.fill();}else ctx.fillRect(ox,oy,sz,sz);
      /* inner white */
      ctx.fillStyle=bg;
      if(ir>0){ctx.beginPath();drawRR(ctx,ox+cell,oy+cell,5*cell,5*cell,ir);ctx.fill();}else ctx.fillRect(ox+cell,oy+cell,5*cell,5*cell);
      /* inner dot */
      ctx.fillStyle=fg;
      if(dr>0){ctx.beginPath();drawRR(ctx,ox+2*cell,oy+2*cell,3*cell,3*cell,dr);ctx.fill();}else ctx.fillRect(ox+2*cell,oy+2*cell,3*cell,3*cell);
    }
  });
  function drawRR(ctx,x,y,w,h,r){
    r=Math.min(r,w/2,h/2);
    ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.arcTo(x+w,y,x+w,y+r,r);
    ctx.lineTo(x+w,y+h-r);ctx.arcTo(x+w,y+h,x+w-r,y+h,r);
    ctx.lineTo(x+r,y+h);ctx.arcTo(x,y+h,x,y+h-r,r);
    ctx.lineTo(x,y+r);ctx.arcTo(x,y,x+r,y,r);
    ctx.closePath();
  }
}

/* ── UPDATE HELPERS ─────────────────────────────────────── */
function upd(k,v){if(!cur)return;cur[k]=v;markUnsaved()}
function setOpt(k,v){if(!cur)return;if(!cur.opts)cur.opts={};cur.opts[k]=v;markUnsaved()}
function updColor(k,v){
  setOpt(k,v);
  var sw=document.getElementById('sw_'+k),hx=document.getElementById('hx_'+k);
  if(sw){sw.style.background=v;sw.classList.remove('checker')}
  if(hx)hx.value=v;
  drawPreviews();drawEyePreviews();schedQR();
}
function updColorHx(k,v){if(!/^#[0-9a-fA-F]{6}$/.test(v))return;updColor(k,v)}
function setDot(s){
  setOpt('dotStyle',s);schedQR();
  document.querySelectorAll('.dot-opt').forEach(function(el){el.classList.toggle('act',el.dataset.dot===s)});
}
function setEye(s){
  setOpt('eyeStyle',s);schedQR();
  document.querySelectorAll('#eyeGrid .dot-opt').forEach(function(el){el.classList.toggle('act',el.dataset.eye===s)});
}
function upLogo(ev){
  var f=ev.target.files[0];if(!f)return;
  var r=new FileReader();
  r.onload=function(e){
    var dataUrl=e.target.result;
    var tmp=new Image();
    tmp.onload=function(){
      setOpt('logoData',dataUrl);
      setOpt('logoW',tmp.naturalWidth||tmp.width||0);
      setOpt('logoH',tmp.naturalHeight||tmp.height||0);
      renderEditor();schedQR();
    };
    tmp.src=dataUrl;
  };
  r.readAsDataURL(f);
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
function openNewModal(){document.getElementById('nName').value='';document.getElementById('nSlug').value='';document.getElementById('slugHint').textContent='...';openOv('MN');setTimeout(function(){document.getElementById('nName').focus()},80)}
function autoSlug(){var v=document.getElementById('nName').value;var s=v.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');document.getElementById('nSlug').value=s;document.getElementById('slugHint').textContent=s||'...'}
function cleanSlug(el){el.value=el.value.toLowerCase().replace(/[^a-z0-9-]/g,'');document.getElementById('slugHint').textContent=el.value||'...'}
function createQR(){
  var name=document.getElementById('nName').value.trim(),slug=document.getElementById('nSlug').value.trim();
  if(!name||!slug){toast('Remplissez tous les champs.','error');return}
  cur={id:0,name:name,slug:slug,target_url:'',scan_count:0,
    opts:{fgColor:'#000000',bgColor:'#ffffff',transparentBg:false,dotStyle:'square',eyeStyle:'square',margin:4,logoSize:30}};
  closeOv('MN');renderEditor();schedQR();toast('Renseignez l\'URL puis sauvegardez.','info');
}

// ── SHARE ─────────────────────────────────────────────────────
function openShare(id){
  shareRid=id;
  var sl=document.getElementById('shareList');
  sl.innerHTML='<div style="font-size:12px;color:var(--dim);text-align:center;padding:10px">Chargement…</div>';
  openOv('MS');
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','list');fd.append('rtype','qr');fd.append('rid',id);
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
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','add');fd.append('rtype','qr');fd.append('rid',shareRid);fd.append('email',email);fd.append('permission',perm);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){document.getElementById('shareEmail').value='';openShare(shareRid);toast('Partagé avec '+d.user.username,'success');}
    else toast(d.error||'Erreur','error');
  });
}
function removeShare(shareId){
  var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('action','remove');fd.append('rtype','qr');fd.append('rid',shareRid);fd.append('share_id',shareId);
  fetch('/api/share.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){
    if(d.ok){openShare(shareRid);toast('Accès retiré.','info');}
  });
}

</script>
</body>
</html>
