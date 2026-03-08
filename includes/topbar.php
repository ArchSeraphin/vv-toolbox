<?php
/**
 * VV ToolBox — Topbar partagée
 *
 * Variables attendues (définies avant le require) :
 *   $user       array  currentUser()
 *   $isAdm      bool   isAdmin()
 *   $breadcrumb array  [] = affiche la recherche (dashboard)
 *                      [['Label', '/url'|null], ...] = breadcrumb (null = page courante)
 *   $tbActions  string HTML optionnel — boutons supplémentaires (avant thème + avatar)
 */
$tbActions = $tbActions ?? '';
?>
<div class="topbar">

  <a class="tb-logo" href="/dashboard.php">
    <div class="tb-logo-icon"><i class="fa fa-toolbox"></i></div>
    <div class="tb-logo-text">
      <div class="tb-logo-name">VV ToolBox</div>
      <div class="tb-logo-sub">Espace de travail</div>
    </div>
  </a>

  <div class="tb-center">
    <?php if (empty($breadcrumb)): ?>
    <div class="tb-search">
      <i class="fa fa-magnifying-glass"></i>
      <input type="text" placeholder="Rechercher une création…" id="searchInput" autocomplete="off">
    </div>
    <?php else: ?>
    <div class="tb-bc">
      <a href="/dashboard.php">Dashboard</a>
      <?php foreach ($breadcrumb as $crumb): ?>
      <span class="sep"><i class="fa fa-chevron-right"></i></span>
      <?php if (!empty($crumb[1])): ?>
      <a href="<?= htmlspecialchars($crumb[1]) ?>"><?= htmlspecialchars($crumb[0]) ?></a>
      <?php else: ?>
      <span style="color:var(--text)"><?= htmlspecialchars($crumb[0]) ?></span>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="tb-right">
    <?php if ($tbActions) echo $tbActions; ?>
    <button class="tb-btn" onclick="toggleTheme()" aria-label="Changer le thème">
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
        <a class="av-item" href="/admin/users.php" role="menuitem">
          <i class="fa fa-users"></i> Gestion membres
        </a>
        <?php endif; ?>
        <a class="av-item" href="/profile.php" role="menuitem">
          <i class="fa fa-user-pen"></i> Mon profil
        </a>
        <div class="av-sep"></div>
        <a class="av-item danger" href="/logout.php" role="menuitem">
          <i class="fa fa-arrow-right-from-bracket"></i> Déconnexion
        </a>
      </div>
    </div>
  </div>

</div>
