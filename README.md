# VV ToolBox — Guide de déploiement Plesk

## Prérequis
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Apache avec mod_rewrite activé
- Certificat SSL (Let's Encrypt via Plesk)

---

## Structure des fichiers

```
/httpdocs/
├── .htaccess               ← Sécurité + redirections
├── index.php               ← Point d'entrée (redirect)
├── login.php               ← Page de connexion
├── logout.php
├── dashboard.php           ← Hub principal
├── config/
│   └── db.php              ← ⚠️  Config DB (ne pas versionner)
├── auth/
│   └── session.php         ← Gestion sessions
├── api/
│   └── auth.php            ← Handler login/logout
├── admin/
│   └── users.php           ← Gestion membres (admin)
├── tools/                  ← (Phase 2)
│   ├── qr.php
│   ├── signature.php
│   └── vcard.php
└── install/
    └── setup.sql           ← Schéma base de données
```

---

## Installation étape par étape

### 1. Base de données (Plesk > MySQL)
```sql
-- Créer la base
CREATE DATABASE vv_toolbox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié (ne pas utiliser root)
CREATE USER 'vv_toolbox_user'@'localhost' IDENTIFIED BY 'MotDePasseTresSecurise!';
GRANT ALL PRIVILEGES ON vv_toolbox.* TO 'vv_toolbox_user'@'localhost';
FLUSH PRIVILEGES;
```

Puis importer `install/setup.sql` via phpMyAdmin.

### 2. Configuration
Éditer `config/db.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vv_toolbox');
define('DB_USER', 'vv_toolbox_user');
define('DB_PASS', 'MotDePasseTresSecurise!');
define('APP_SECRET', 'GENERER_ICI'); // openssl rand -hex 32
define('APP_URL', 'https://votre-domaine.fr');
```

### 3. Upload sur Plesk
- Uploader tous les fichiers dans `/httpdocs/`
- Permissions :
  ```
  chmod 755 /httpdocs/
  chmod 644 /httpdocs/*.php
  chmod 600 /httpdocs/config/db.php   ← accès restreint
  chmod 755 /httpdocs/admin/
  ```

### 4. Première connexion
- URL : `https://votre-domaine.fr/login.php`
- Email : `admin@vv-toolbox.fr`
- Mot de passe : `ChangeMe2024!`
- **⚠️ Changer immédiatement le mot de passe admin**

---

## Sécurité post-installation

1. **Supprimer le dossier `install/`** après import SQL
2. **Changer le mot de passe admin** via Admin > Membres
3. **Activer HSTS** dans `.htaccess` (décommenter la ligne)
4. Configurer la sauvegarde automatique MySQL dans Plesk
5. Vérifier que `config/db.php` n'est pas accessible publiquement

---

## Comptes par défaut

| Rôle  | Email                  | Mot de passe     |
|-------|------------------------|------------------|
| Admin | admin@vv-toolbox.fr    | ChangeMe2024!    |

**Générer une clé APP_SECRET :**
```bash
openssl rand -hex 32
```

---

## Phase 2 — Outils (à venir)
- `/tools/qr.php` — Générateur QR Code
- `/tools/signature.php` — Générateur de signature mail
- `/tools/vcard.php` — Générateur de carte de visite
- `/r.php` — Redirecteur QR (tracking)
