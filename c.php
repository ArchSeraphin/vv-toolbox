<?php
/**
 * VV ToolBox — Page publique carte de visite
 * URL : /c/{slug}
 * Accessible sans authentification
 */

require_once __DIR__ . '/config/db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$slug = '';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (preg_match('#^/c/([a-z0-9\-]+)$#', $uri, $m)) {
    $slug = $m[1];
} elseif (isset($_GET['s'])) {
    $slug = trim($_GET['s']);
}

if (!$slug || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
    http_response_code(404); die('Carte introuvable.');
}

try {
    $db = getDB();
    $st = $db->prepare('SELECT * FROM vcards WHERE slug = ? AND is_published = 1 LIMIT 1');
    $st->execute([$slug]);
    $vc = $st->fetch();
    if (!$vc) { http_response_code(404); die('Cette carte n\'existe pas ou n\'est plus publiée.'); }

    $data = json_decode($vc['data_json'], true) ?: [];
    $fn   = trim(($data['firstName']??'').' '.($data['lastName']??''));
    $title = $fn ?: ($data['company'] ?? $vc['name']);

} catch (Throwable $e) {
    error_log('[VV-ToolBox] VCard error: ' . $e->getMessage());
    http_response_code(500); die('Erreur.');
}

// Reuse the same HTML builder logic (PHP version of bldHTML)
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$c = $data;
$fn2 = implode(' ', array_filter([$c['firstName']??'', $c['lastName']??'']));
$fi  = isset($c['fontN']) && $c['fontN']
    ? "@import url('https://fonts.googleapis.com/css2?family=".urlencode($c['fontN']).":wght@400;600;700;800&display=swap');"
    : '';

$imgSize = (int)($c['logoSize'] ?? 80);
$usePhoto = ($c['imgType'] ?? '') === 'photo';
$imgSrc   = $usePhoto ? ($c['photo'] ?? '') : ($c['logo'] ?? '');
$imgHtml  = '';
if ($imgSrc) {
    if ($usePhoto) {
        $shape = $c['photoShape'] ?? 'circle';
        $pr = $shape === 'circle' ? '50%' : ($shape === 'rounded' ? '16px' : '4px');
        $pb = ($c['photoBorder'] ?? true) ? 'border:3px solid '.e($c['accent']??'#000').';' : '';
        $imgHtml = '<div class="iw"><img src="'.e($imgSrc).'" style="border-radius:'.$pr.';'.$pb.'width:96px;height:96px;object-fit:cover;display:block" alt="photo"></div>';
    } else {
        $imgHtml = '<div class="iw"><img src="'.e($imgSrc).'" style="width:'.$imgSize.'px;height:'.$imgSize.'px;object-fit:contain;display:block" alt="logo"></div>';
    }
}

$ac  = e($c['accent'] ?? '#000000');
$tc  = e($c['textColor'] ?? '#1a1a2e');
$bg  = e($c['bgColor'] ?? '#f0f0f0');
$cbg = e($c['cardBg'] ?? '#ffffff');
$fc  = e($c['fontC'] ?? 'sans-serif');

// Socials
$socials = [
    ['fa-brands fa-instagram', $c['instagram']??''],
    ['fa-brands fa-facebook',  $c['facebook']??''],
    ['fa-brands fa-linkedin',  $c['linkedin']??''],
    ['fa-brands fa-x-twitter', $c['twitter']??''],
    ['fa-brands fa-tiktok',    $c['tiktok']??''],
    ['fa-brands fa-youtube',   $c['youtube']??''],
];
$sh = '';
$hasSoc = false;
foreach ($socials as [$ico, $url]) {
    if ($url) { $sh .= '<a href="'.e($url).'" target="_blank" rel="noopener"><i class="'.$ico.'"></i></a>'; $hasSoc=true; }
}
if ($hasSoc) $sh = '<div class="so">'.$sh.'</div>';

$extras = array_filter($c['extras']??[], fn($b)=>!empty($b['label']));
$xh = '';
if ($extras) {
    $xh = '<div class="xe">';
    foreach ($extras as $b) $xh .= '<a href="'.e($b['url']??'#').'" class="bsec" target="_blank" rel="noopener">'.e($b['label']).'</a>';
    $xh .= '</div>';
}

$ct = '';
if (!empty($c['phone']))   $ct .= '<div class="ci"><span class="cl">Téléphone</span><a href="tel:'.e(preg_replace('/\s/','',$c['phone'])).'" class="cv">'.e($c['phone']).'</a></div>';
if (!empty($c['email']))   $ct .= '<div class="ci"><span class="cl">Email</span><a href="mailto:'.e($c['email']).'" class="cv">'.e($c['email']).'</a></div>';
if (!empty($c['address'])) $ct .= '<div class="ci"><span class="cl">Adresse</span><a href="https://maps.google.com/?q='.urlencode($c['address']).'" target="_blank" class="cv addr">'.nl2br(e($c['address'])).'</a></div>';

// vCard download button
$vcfBtn = '';
if (!empty($c['addToContacts'])) {
    $vcf  = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
    if ($fn2) $vcf .= "FN:$fn2\r\n";
    $vcf .= "N:".($c['lastName']??'').';'.($c['firstName']??'').";;;\r\n";
    if (!empty($c['company']))  $vcf .= "ORG:".$c['company']."\r\n";
    if (!empty($c['phone']))    $vcf .= "TEL;TYPE=CELL:".preg_replace('/\s/','',$c['phone'])."\r\n";
    if (!empty($c['email']))    $vcf .= "EMAIL:".$c['email']."\r\n";
    if (!empty($c['address']))  $vcf .= "ADR:;;".$c['address'].";;;;\r\n";
    $vcf .= "URL:".APP_URL."/c/".$vc['slug']."\r\n";
    $vcf .= "END:VCARD\r\n";
    $vcfData = 'data:text/vcard;charset=utf-8,'.rawurlencode($vcf);
    $vcfName = e($fn2 ?: 'contact');
    $vcfBtn  = '<a href="'.$vcfData.'" download="'.$vcfName.'.vcf" class="bsec" style="margin-bottom:8px">📇 Ajouter aux contacts</a>';
}

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="description" content="<?= e($title) ?><?= !empty($c['company']) ? ' — '.e($c['company']) : '' ?>">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
<?= $fi ?>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:<?= $fc ?>;background:<?= $bg ?>;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:32px 16px 48px}
.card{background:<?= $cbg ?>;border-radius:24px;width:100%;max-width:400px;padding:40px 28px 32px;display:flex;flex-direction:column;align-items:center;box-shadow:0 24px 80px rgba(0,0,0,.25)}
.iw{margin-bottom:20px;display:flex;justify-content:center}
.nm{font-size:26px;font-weight:800;color:<?= $tc ?>;text-align:center;line-height:1.2;margin-bottom:<?= !empty($c['company'])?'4px':'20px' ?>}
.cp{font-size:12px;color:<?= $ac ?>;text-align:center;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:20px}
.bm{display:block;width:100%;padding:14px 20px;background:<?= $ac ?>;color:#fff;text-align:center;border-radius:50px;font-family:inherit;font-size:13px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:24px;transition:opacity .15s,transform .15s}
.bm:hover{opacity:.85;transform:translateY(-1px)}
.dv{width:60px;height:1px;background:<?= $ac ?>44;margin:0 auto 24px}
.cts{width:100%;display:flex;flex-direction:column;gap:18px;margin-bottom:28px}
.ci{text-align:center}
.cl{display:block;font-size:10px;font-weight:700;letter-spacing:1.5px;color:#aaa;margin-bottom:3px;text-transform:uppercase}
.cv{display:block;font-size:16px;font-weight:600;color:<?= $tc ?>;text-decoration:none;line-height:1.4;transition:color .15s}
.cv:hover{color:<?= $ac ?>}
.addr{font-size:14px;font-weight:400}
.so{display:flex;gap:20px;justify-content:center;margin-bottom:28px}
.so a{font-size:22px;color:<?= $tc ?>;text-decoration:none;transition:color .15s,transform .15s}
.so a:hover{color:<?= $ac ?>;transform:scale(1.15)}
.xe{width:100%;display:flex;flex-direction:column;gap:10px;margin-bottom:10px}
.bsec{display:block;width:100%;padding:12px 20px;background:transparent;border:2px solid <?= $ac ?>;color:<?= $ac ?>;text-align:center;border-radius:50px;font-family:inherit;font-size:13px;font-weight:600;text-decoration:none;text-transform:uppercase;letter-spacing:1px;transition:all .15s}
.bsec:hover{background:<?= $ac ?>18;transform:translateY(-1px)}
</style>
</head>
<body>
<div class="card">
  <?= $imgHtml ?>
  <?= $fn2 ? '<div class="nm">'.e($fn2).'</div>' : '' ?>
  <?= !empty($c['company']) ? '<div class="cp">'.e($c['company']).'</div>' : '' ?>
  <?= (!empty($c['ctaLabel'])) ? '<a href="'.e($c['ctaUrl']??'#').'" class="bm" target="_blank">'.e($c['ctaLabel']).'</a>' : '' ?>
  <div class="dv"></div>
  <?= $ct ? '<div class="cts">'.$ct.'</div>' : '' ?>
  <?= $sh ?>
  <?= $xh ?>
  <?= $vcfBtn ?>
</div>
</body>
</html>
