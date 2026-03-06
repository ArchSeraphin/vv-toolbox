# VV ToolBox — Documentation de développement

> Outil interne de création de ressources numériques : QR Codes, Signatures mail, Cartes de visite.
> Stack : PHP 8.1+ / MySQL 8 / HTML+CSS+JS vanilla (pas de framework frontend).

---

## Sommaire

1. [Stack & prérequis](#1-stack--prérequis)
2. [Structure du projet](#2-structure-du-projet)
3. [Installation locale](#3-installation-locale)
4. [Déploiement Plesk](#4-déploiement-plesk)
5. [Base de données](#5-base-de-données)
6. [Système d'authentification](#6-système-dauthentification)
7. [Layout partagé — règles de design](#7-layout-partagé--règles-de-design)
8. [Outils existants](#8-outils-existants)
9. [Ajouter un nouvel outil](#9-ajouter-un-nouvel-outil)
10. [API interne](#10-api-interne)
11. [Conventions de code](#11-conventions-de-code)
12. [Roadmap & idées](#12-roadmap--idées)

---

## 1. Stack & prérequis

| Composant    | Version min | Notes |
|--------------|-------------|-------|
| PHP          | 8.1         | `json_encode`, `PDO`, `password_hash` |
| MySQL        | 8.0         | Ou MariaDB 10.6+ |
| Apache       | 2.4         | `mod_rewrite` activé |
| SSL          | requis      | Session cookie `secure` forcé |
| Composer     | —           | Non utilisé pour l'instant |
| Node / npm   | —           | Non utilisé pour l'instant |

**Dépendances CDN (aucune installation locale) :**
- [Font Awesome 6.5](https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css) — icônes
- [Google Fonts](https://fonts.googleapis.com) — Geist + Instrument Serif
- [QR Server API](https://api.qrserver.com/v1/create-qr-code/) — génération QR côté client

---

## 2. Structure du projet

```
/httpdocs/                          ← racine web Plesk
│
├── .gitignore
├── README.md                       ← ce fichier
│
├── index.php                       ← redirige vers /dashboard.php ou /login.php
├── login.php                       ← page de connexion
├── logout.php                      ← destruction session
├── dashboard.php                   ← hub principal (stats + activité récente)
├── profile.php                     ← profil utilisateur (changement mdp, username)
│
├── config/
│   └── db.php                      ← ⚠️ NON VERSIONNÉ — identifiants DB + constantes app
│
├── auth/
│   └── session.php                 ← toute la logique auth (voir §6)
│
├── api/
│   ├── auth.php                    ← handler POST login/logout (JSON)
│   └── share.php                   ← handler POST partage entre utilisateurs (JSON)
│
├── admin/
│   └── users.php                   ← gestion membres (admin only)
│
├── tools/
│   ├── qr.php                      ← générateur QR Code
│   ├── signature.php               ← générateur signature mail
│   └── vcard.php                   ← générateur carte de visite numérique
│
├── assets/
│   ├── layout.css                  ← ⭐ CSS partagé — largeurs fixes, nav, composants
│   └── layout.js                   ← JS partagé — nav rétractable, thème, toast (optionnel)
│
├── install/
│   └── setup.sql                   ← schéma complet + seed admin
│
├── r.php                           ← redirecteur QR avec tracking (scan_count++)
└── c.php                           ← utilitaire interne
```

---

## 3. Installation locale

### Option A — PHP built-in server (rapide)

```bash
# Cloner le repo
git clone <repo-url> vv-toolbox
cd vv-toolbox

# Créer la config locale
cp config/db.php.example config/db.php
# Éditer config/db.php avec vos identifiants locaux

# Créer la base
mysql -u root -p < install/setup.sql

# Lancer le serveur
php -S localhost:8080 -t .
# → http://localhost:8080/login.php
```

> **Note :** en local, le cookie `secure` peut bloquer la session. Commenter temporairement
> la ligne `ini_set('session.cookie_secure', '1');` dans `auth/session.php`.

### Option B — Laragon / XAMPP / Herd

Pointer le vhost vers le dossier racine du projet. Importer `install/setup.sql` via phpMyAdmin.

### Compte admin par défaut

| Email | Mot de passe |
|-------|-------------|
| `admin@vv-toolbox.fr` | `ChangeMe2024!` |

---

## 4. Déploiement Plesk

```bash
# 1. Uploader les fichiers
rsync -avz --exclude='.git' --exclude='config/db.php' . user@serveur:/httpdocs/

# 2. Configurer sur le serveur
cp /httpdocs/config/db.php.example /httpdocs/config/db.php
nano /httpdocs/config/db.php   # remplir les vraies valeurs

# 3. Importer le schéma (une seule fois, ou les migrations séparément)
mysql -u vv_toolbox_user -p vv_toolbox < /httpdocs/install/setup.sql

# 4. Permissions
chmod 600 /httpdocs/config/db.php
chmod 755 /httpdocs/tools/ /httpdocs/api/ /httpdocs/admin/
chmod 644 /httpdocs/**/*.php
```

**Après déploiement initial :**
- Changer le mot de passe admin
- Supprimer (ou protéger par `.htaccess`) le dossier `install/`
- Vérifier que `config/db.php` renvoie 403 si appelé directement

---

## 5. Base de données

### Tables

| Table | Description |
|-------|-------------|
| `users` | Comptes utilisateurs (admin / member) |
| `user_sessions` | Sessions server-side (optionnel) |
| `login_attempts` | Rate limiting (IP + email) |
| `qr_codes` | QR Codes — `options_json` stocke toutes les options visuelles |
| `email_signatures` | Signatures mail — `data_json` stocke tous les champs |
| `vcards` | Cartes de visite — `data_json` + `slug` unique (URL publique) |
| `shares` | Partage d'un projet entre utilisateurs |

### Table `shares` (ajoutée en v2)

```sql
CREATE TABLE shares (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  resource_type ENUM('qr','sig','vc') NOT NULL,
  resource_id   INT UNSIGNED    NOT NULL,
  owner_id      INT UNSIGNED    NOT NULL,   -- propriétaire
  shared_with   INT UNSIGNED    NOT NULL,   -- invité
  permission    ENUM('view','edit') NOT NULL DEFAULT 'edit',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share (resource_type, resource_id, shared_with)
);
```

### Migrations

Il n'y a pas encore de système de migration automatique. Pour mettre à jour une base existante :
1. Écrire le `ALTER TABLE` / `CREATE TABLE` dans un fichier `install/migration_vX.sql`
2. L'exécuter manuellement sur le serveur via phpMyAdmin ou CLI
3. Committer le fichier dans le repo

### Schéma JSON des outils

Les données métier sont stockées en JSON dans une colonne. Structure de référence :

**`email_signatures.data_json`**
```json
{
  "firstName": "", "lastName": "", "jobTitle": "", "company": "",
  "phone": "", "mobile": "", "email": "", "website": "", "address": "",
  "photo": "data:image/...",  "photoSize": 64,
  "logo":  "data:image/...",  "logoSize":  36,
  "instagram": "", "facebook": "", "linkedin": "", "twitter": "",
  "googleReview": "",
  "extras": [{ "label": "Mon site", "url": "https://..." }],
  "layout":      "modern",       // modern | classic | minimal | bold | compact | divided
  "fontId":      "helvetica",    // helvetica | georgia | trebuchet | verdana | courier
  "accentColor": "#0ea5e9",
  "textColor":   "#1a1a2e",
  "mutedColor":  "#6b7280",
  "fontSize":    "14",
  "divider":     true
}
```

**`qr_codes.options_json`**
```json
{
  "color": "#000000",
  "bgColor": "#ffffff",
  "size": 300,
  "format": "png"
}
```

**`vcards.data_json`**
```json
{
  "firstName": "", "lastName": "", "company": "",
  "imgType": "logo",             // logo | photo
  "logo": "data:image/...",      "logoSize": 80,
  "photo": "data:image/...",     "photoShape": "circle",
  "photoBorder": true,
  "ctaLabel": "", "ctaUrl": "",
  "phone": "", "email": "", "address": "",
  "instagram": "", "facebook": "", "linkedin": "", "twitter": "", "tiktok": "", "youtube": "",
  "extras": [{ "label": "", "url": "" }],
  "bgColor": "#CC0000",  "cardBg": "#FFFFFF",
  "accent": "#CC0000",   "textColor": "#1A1A2E",
  "fontN": "Syne",       "fontC": "'Syne',sans-serif"
}
```

---

## 6. Système d'authentification

Tout est dans `auth/session.php`. Inclure en tête de chaque page protégée :

```php
require_once __DIR__ . '/../auth/session.php';
requireLogin();        // redirige vers /login.php si non connecté
checkSessionExpiry();  // déconnecte si session expirée (8h par défaut)
```

### Fonctions disponibles

```php
isLoggedIn(): bool
isAdmin(): bool
requireLogin(): void       // redirige si non connecté
requireAdmin(): void       // redirige + 403 si pas admin

currentUser(): array       // ['id', 'username', 'email', 'role']

getCsrfToken(): string     // token CSRF de la session courante
verifyCsrfToken(string): bool
csrfField(): string        // <input type="hidden" name="csrf_token" ...>
```

### Pattern POST AJAX (tous les outils)

Chaque outil gère ses propres actions POST en haut du fichier, avant le HTML :

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok'=>false,'error'=>'Token invalide']); exit;
    }
    $action = $_POST['action'] ?? '';
    // ... switch sur $action
    exit;
}
```

Le token CSRF est injecté dans chaque page via :
```php
$csrf = getCsrfToken();
// puis en JS :
var CSRF = <?= json_encode($csrf) ?>;
```

---

## 7. Layout partagé — règles de design

### Fichier de référence : `assets/layout.css`

**Ce fichier définit les variables de mise en page à respecter sur TOUS les outils.**
Ne pas redéfinir ces valeurs localement (ou seulement si vraiment nécessaire).

### Variables de largeur — FIXES

```css
--nav-w:      240px;   /* nav latérale étendue */
--nav-w-mini:  56px;   /* nav rétractée (icônes seules) */
--sb-w:       272px;   /* sidebar liste (gauche) */
--pv-w:       360px;   /* panneau aperçu (droite) */
--topbar-h:    58px;   /* barre du haut */
```

> ⚠️ **Situation actuelle :** `qr.php` et `vcard.php` utilisent encore leurs propres valeurs
> (`--sb-w:268px`, `--pv-w:340px` / `370px`) définies dans leur `:root` inline.
> À aligner sur 272px / 360px lors de la prochaine passe de refacto.

### Palette de couleurs des outils

```css
--qr:   #4f6ef7;   /* bleu — QR Code */
--sig:  #0ea5e9;   /* cyan — Signature mail */
--vc:   #8b5cf6;   /* violet — Carte de visite */
```

### Thème clair/sombre

Géré via `data-theme="dark|light"` sur `<html>`. Persisté dans `localStorage('vv_theme')`.
Toujours initialiser en haut de `<script>` (avant le premier rendu) :

```js
(function(){
  var s = localStorage.getItem('vv_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', s);
})();
```

### Nav rétractable

Persistée dans `localStorage('vv_nav')` (`'mini'` ou `'full'`).
La classe `body.nav-mini` active le mode icônes.

```js
// Pattern à copier dans chaque page
(function(){
  var m = localStorage.getItem('vv_nav') === 'mini';
  if (m) document.body.classList.add('nav-mini');
  function ap(){
    var i = document.getElementById('navToggleIco');
    var l = document.getElementById('navToggleLbl');
    if (i) i.className = document.body.classList.contains('nav-mini') ? 'fa fa-chevron-right' : 'fa fa-chevron-left';
    if (l) l.textContent = document.body.classList.contains('nav-mini') ? '' : 'Réduire';
  }
  ap();
  window.toggleNav = function(){
    m = !m;
    document.body.classList.toggle('nav-mini', m);
    localStorage.setItem('vv_nav', m ? 'mini' : 'full');
    ap();
  };
})();
```

### Structure HTML d'une page outil (template)

```html
<div class="topbar">
  <div class="tb-logo">       <!-- largeur = --nav-w, se rétracte avec nav-mini -->
    <div class="tb-logo-icon"><i class="fa fa-toolbox"></i></div>
    <div class="tb-logo-text">
      <div class="tb-logo-name">VV ToolBox</div>
      <div class="tb-logo-sub">Espace de travail</div>
    </div>
  </div>
  <div class="tb-center"><!-- breadcrumb ou search --></div>
  <div class="tb-right"><!-- boutons topbar --></div>
</div>

<div class="layout">

  <nav class="nav">
    <div class="nav-body">
      <!-- nav-section > nav-label + nav-item x N -->
      <!-- chaque nav-item doit avoir :
           - <span class="nav-item-label"> pour le texte (caché en mini)
           - <span class="nav-tip">Tooltip</span> pour le tooltip mini
      -->
    </div>
    <div class="nav-footer">
      <button class="nav-toggle" onclick="toggleNav()">
        <i class="fa fa-chevron-left" id="navToggleIco"></i>
        <span class="nav-item-label nav-toggle-label" id="navToggleLbl">Réduire</span>
      </button>
    </div>
  </nav>

  <div class="sb">                        <!-- largeur = --sb-w -->
    <div class="sbh"><!-- header sidebar --></div>
    <div class="sb-list" id="SBL"><!-- liste des items --></div>
  </div>

  <div class="ed" id="ED">               <!-- flex:1, scrollable -->
    <!-- action-bar sticky + ed-content -->
  </div>

  <div class="pv">                        <!-- largeur = --pv-w -->
    <!-- panneau aperçu -->
  </div>

</div>
```

### Action bar sticky (état "non sauvegardé")

```html
<div class="action-bar" id="actionBar">
  <button class="btn-save" id="btnSave" onclick="save()">
    <span class="unsaved-dot"></span>
    <i class="fa fa-floppy-disk"></i>
    <span id="btnSaveLbl">Sauvegarder</span>
  </button>
  <span class="unsaved-label">Modifications non sauvegardées</span>
</div>
```

```js
function markUnsaved(){
  document.getElementById('actionBar')?.classList.add('unsaved');
  document.getElementById('btnSave')?.classList.add('unsaved');
}
function markSaved(){
  document.getElementById('actionBar')?.classList.remove('unsaved');
  document.getElementById('btnSave')?.classList.remove('unsaved');
  var l = document.getElementById('btnSaveLbl');
  if(l){ l.textContent = 'Sauvegardé ✓'; setTimeout(()=>{ l.textContent='Sauvegarder'; }, 2200); }
}
```

---

## 8. Outils existants

### QR Code (`tools/qr.php`)

- Génération via API externe `api.qrserver.com`
- Données stockées dans `qr_codes` (`slug` unique pour URL de redirection)
- Redirection publique via `r.php?s={slug}` qui incrémente `scan_count`
- Options : couleur, taille, format

### Signature Mail (`tools/signature.php`)

- Rendu 100% HTML/CSS inline (compatible clients mail)
- **6 agencements** (layout) : `modern`, `classic`, `minimal`, `bold`, `compact`, `divided`
- **5 polices** (fontId) : `helvetica`, `georgia`, `trebuchet`, `verdana`, `courier`
- Agencement et police sont **indépendants** l'un de l'autre
- Le logo apparaît **toujours en premier** dans le rendu HTML
- Taille photo (slider 32–120px) et taille logo (slider 20–80px) séparés
- Bouton "Copier" : utilise `ClipboardItem` avec `text/html` pour coller directement dans Outlook/Gmail

### Carte de visite (`tools/vcard.php`)

- Génère un fichier HTML autonome téléchargeable
- URL publique : `/{slug}` (via `r.php` ou routing direct)
- Aperçu dans un mockup iPhone
- Export HTML standalone (tout inline, pas de dépendances)

### Partage entre utilisateurs (`api/share.php`)

Endpoint POST, 4 actions :

| action | paramètres | description |
|--------|-----------|-------------|
| `list` | `rtype`, `rid` | Liste les partages d'une ressource |
| `add` | `rtype`, `rid`, `email`, `permission` | Invite par email |
| `remove` | `rtype`, `rid`, `share_id` | Retire un accès |
| `mine` | — | Ressources partagées avec moi |

`rtype` : `qr` / `sig` / `vc`

---

## 9. Ajouter un nouvel outil

### Checklist

1. **Créer `tools/mon-outil.php`** en suivant la structure template (§7)
2. **Créer la table SQL** dans `install/setup.sql` + un fichier `install/migration_vX.sql`
3. **Ajouter dans la nav** de toutes les pages existantes :
   - `dashboard.php`
   - `tools/qr.php`
   - `tools/signature.php`
   - `tools/vcard.php`
4. **Ajouter dans `dashboard.php`** :
   - La constante `$typeInfo` (icône, label, couleur, url)
   - La requête stats dans `$stats`
   - L'appel `recentItems()` pour l'activité récente
5. **Respecter les largeurs** (§7 — ne pas toucher `--sb-w`, `--pv-w`, `--nav-w`)
6. **Copier le pattern nav rétractable** (JS toggleNav + HTML nav-body/nav-footer)

### Snippet PHP de base (haut de fichier)

```php
<?php
require_once __DIR__ . '/../auth/session.php';
requireLogin();
checkSessionExpiry();

$user  = currentUser();
$db    = getDB();
$uid   = $user['id'];
$isAdm = isAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok'=>false,'error'=>'Token invalide']); exit;
    }
    // ... actions
    exit;
}

$csrf = getCsrfToken();
?>
```

---

## 10. API interne

### `api/auth.php`

| action | méthode | description |
|--------|---------|-------------|
| `login` | POST | `email` + `password` → session |
| `logout` | POST | Destruction session |

### `api/share.php`

Voir §8 — Partage entre utilisateurs.

### Pattern de réponse JSON

```json
{ "ok": true,  "id": 42 }
{ "ok": false, "error": "Message d'erreur lisible" }
```

---

## 11. Conventions de code

### PHP

- `require_once` avec chemin absolu via `__DIR__`
- PDO uniquement — pas de `mysqli`
- Toujours `htmlspecialchars()` sur les sorties HTML
- Toujours `verifyCsrfToken()` en premier dans les POST
- Pas de framework, pas de templating — PHP inline dans le HTML

### JavaScript

- Vanilla JS (ES5 compatible) — pas de `const`/`let` dans les fonctions critiques pour compatibilité max
- Pas de jQuery, pas de framework
- Les données PHP sont injectées via `json_encode` :
  ```js
  var CSRF   = <?= json_encode($csrf) ?>;
  var myData = <?= json_encode($arrayFromDB) ?>;
  ```
- Debounce sur les previews : `clearTimeout(timer); timer = setTimeout(fn, 180)`

### CSS

- Variables CSS dans `:root` de chaque page (couleur accent, etc.)
- Toujours utiliser les variables de `layout.css` pour les dimensions
- Dark mode via `[data-theme="light"]` sur `<html>` (jamais `prefers-color-scheme`)
- Pas de `!important` sauf cas exceptionnel documenté

### Nommage

- Classes CSS : BEM-light (`sb-item`, `sb-item-name`, `action-bar`)
- IDs JS : court et descriptif (`ED`, `SBL`, `actionBar`, `btnSave`)
- Variables PHP : camelCase (`$sigList`, `$isAdm`, `$typeInfo`)

---

## 12. Roadmap & idées

### En cours / à finir
- [ ] Aligner `--sb-w` et `--pv-w` dans `qr.php` et `vcard.php` sur les valeurs de `layout.css` (272px / 360px)
- [ ] Implémenter la lecture des ressources partagées (`api/share.php` action `mine`) dans le dashboard
- [ ] Protéger `api/share.php` pour les accès `view` vs `edit` côté outils

### Idées futures
- [ ] **Outil : Bannière email** (image d'en-tête HTML pour mails)
- [ ] **Outil : Mini landing page** (page one-pager simple)
- [ ] **Export PDF** des cartes de visite
- [ ] **Statistiques QR** : graphique des scans par jour
- [ ] **Dossiers / tags** pour organiser les créations
- [ ] **Notifications** quand un projet partagé est modifié
- [ ] **Historique de versions** des signatures (JSON diff)
- [ ] Migration vers Composer + autoloading PSR-4 si le projet grossit

---

## Variables d'environnement (`config/db.php`)

```php
define('DB_HOST',              'localhost');
define('DB_NAME',              'vv_toolbox');
define('DB_USER',              '');           // à remplir
define('DB_PASS',              '');           // à remplir
define('DB_CHARSET',           'utf8mb4');
define('APP_SECRET',           '');           // openssl rand -hex 32
define('APP_URL',              'https://...');
define('SESSION_LIFETIME',     28800);        // 8h en secondes
define('LOGIN_MAX_ATTEMPTS',   5);
define('LOGIN_WINDOW_MINUTES', 15);
```

Générer `APP_SECRET` :
```bash
openssl rand -hex 32
```

---

*Dernière mise à jour : v2 — Nav rétractable, partage utilisateurs, layout unifié, signature refactorée*
