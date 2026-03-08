<?php
/**
 * VV ToolBox — Nav latérale partagée
 *
 * Variables attendues (définies avant le require) :
 *   $user        array  currentUser()
 *   $isAdm       bool   isAdmin()
 *   $db          PDO    getDB()
 *   $navActive   string 'dashboard' | 'qr' | 'sig' | 'vc' | 'profile' | 'admin-users'
 *
 * Variables optionnelles (calculées automatiquement si absentes) :
 *   $navQr          int  nb QR codes
 *   $navSig         int  nb signatures
 *   $navVc          int  nb cartes
 *   $navMemberCount int  nb membres (admin only)
 */

if (!function_exists('_navCount')) {
    function _navCount(PDO $db, string $table, bool $isAdm, int $uid): int {
        $sql = $isAdm
            ? "SELECT COUNT(*) FROM $table"
            : "SELECT COUNT(*) FROM $table WHERE user_id=?";
        $s = $db->prepare($sql);
        $isAdm ? $s->execute() : $s->execute([$uid]);
        return (int)$s->fetchColumn();
    }
}

$isAdm = $isAdm ?? isAdmin();
$_uid = (int)$user['id'];
if (!isset($navQr))  $navQr  = _navCount($db, 'qr_codes',        $isAdm, $_uid);
if (!isset($navSig)) $navSig = _navCount($db, 'email_signatures', $isAdm, $_uid);
if (!isset($navVc))  $navVc  = _navCount($db, 'vcards',          $isAdm, $_uid);
if ($isAdm && !isset($navMemberCount))
    $navMemberCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
else
    $navMemberCount = $navMemberCount ?? 0;

$_a = $navActive ?? '';

function _navCls(string $key, string $active, string $extra = ''): string {
    $cls = 'nav-item' . ($extra ? " $extra" : '');
    return $cls . ($key === $active ? ' active' : '');
}
?>
<nav class="nav" aria-label="Navigation principale">
  <div class="nav-body">

    <div class="nav-section">
      <div class="nav-label">Navigation</div>
      <a class="<?= _navCls('dashboard', $_a) ?>" href="/dashboard.php">
        <i class="fa fa-house"></i>
        <span class="nav-item-label"> Dashboard</span>
        <span class="nav-tip">Dashboard</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Outils</div>
      <a class="<?= _navCls('qr', $_a, 'tool-qr') ?>" href="/tools/qr.php">
        <i class="fa fa-qrcode"></i>
        <span class="nav-item-label"> QR Code</span>
        <?php if ($navQr > 0): ?><span class="nav-badge"><?= $navQr ?></span><?php endif; ?>
        <span class="nav-tip">QR Code</span>
      </a>
      <a class="<?= _navCls('sig', $_a, 'tool-sig') ?>" href="/tools/signature.php">
        <i class="fa fa-envelope"></i>
        <span class="nav-item-label"> Signature mail</span>
        <?php if ($navSig > 0): ?><span class="nav-badge"><?= $navSig ?></span><?php endif; ?>
        <span class="nav-tip">Signature mail</span>
      </a>
      <a class="<?= _navCls('vc', $_a, 'tool-vc') ?>" href="/tools/vcard.php">
        <i class="fa fa-id-card"></i>
        <span class="nav-item-label"> Carte de visite</span>
        <?php if ($navVc > 0): ?><span class="nav-badge"><?= $navVc ?></span><?php endif; ?>
        <span class="nav-tip">Carte de visite</span>
      </a>
    </div>

    <?php if ($isAdm): ?>
    <div class="nav-sep"></div>
    <div class="nav-section">
      <div class="nav-label">Administration</div>
      <a class="<?= _navCls('admin-users', $_a) ?>" href="/admin/users.php">
        <i class="fa fa-users"></i>
        <span class="nav-item-label"> Membres</span>
        <?php if ($navMemberCount > 0): ?><span class="nav-badge"><?= $navMemberCount ?></span><?php endif; ?>
        <span class="nav-tip">Membres</span>
      </a>
    </div>
    <?php endif; ?>

    <div class="nav-sep"></div>
    <a class="<?= _navCls('profile', $_a) ?>" href="/profile.php">
      <i class="fa fa-user-pen"></i>
      <span class="nav-item-label"> Mon profil</span>
      <span class="nav-tip">Mon profil</span>
    </a>
    <a class="nav-item" href="/logout.php">
      <i class="fa fa-arrow-right-from-bracket"></i>
      <span class="nav-item-label"> Déconnexion</span>
      <span class="nav-tip">Déconnexion</span>
    </a>

  </div><!-- nav-body -->

  <div class="nav-footer">
    <button class="nav-toggle" onclick="toggleNav()">
      <i class="fa fa-chevron-left" id="navToggleIco"></i>
      <span class="nav-toggle-label" id="navToggleLbl">Réduire</span>
    </button>
  </div>
</nav>
