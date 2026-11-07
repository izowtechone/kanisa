# KKKT CMS - PWA and Deployment Guide / Mwongozo wa PWA na Usambazaji

This guide covers installing KKKT CMS as an app and preparing a shared-hosting deployment (Asura Hosting, DirectAdmin).
Hosting facts marked **CONFIRM** must be verified with Asura Hosting support before go-live. Nothing in this guide has been deployed.

Mwongozo huu unaeleza jinsi ya kusakinisha KKKT CMS kama programu na kuandaa usambazaji kwenye shared hosting.

---

## Part 1 - Installing the app

KKKT CMS is a server-based system. The installed app is the same website in its own window. It needs internet and never stores member, finance or user data for offline use.

### 1. Windows (Chrome or Edge)
1. Open `https://your-domain` and sign in (HTTPS is required).
2. Chrome: click the install icon at the right of the address bar, or menu -> **Cast, save and share -> Install page as app**.
   Edge: menu -> **Apps -> Install this site as an app**.
3. Confirm the name **KKKT CMS**. The app opens in its own window and appears in the Start menu.
4. Alternatively use the **Install app** button at the bottom of the sidebar (shown only when the browser allows installation; hidden once installed).

Kiswahili: fungua tovuti kwa HTTPS, bonyeza alama ya Install kwenye upau wa anwani au kitufe cha **Sakinisha programu** kwenye menyu ya pembeni.

### 2. Android (Chrome)
1. Open the site in Chrome and sign in.
2. Tap **Install app** in the sidebar, or menu (three dots) -> **Install app / Add to Home Screen**.
3. Confirm. The KKKT CMS icon appears on the home screen and launches in standalone mode.

### 3. iPhone / iPad (Safari) - limitations
iOS never shows an automatic install prompt. In Safari tap **Share -> Add to Home Screen**. The sidebar button only shows these instructions. Service workers on iOS are limited, so the offline notice may appear as a normal Safari error. Other iOS browsers cannot install web apps on older iOS versions.

### 4. Updating the installed app
Updates are automatic. Static files are versioned by Vite, and the service worker is revalidated on every visit (`Cache-Control: no-cache` in `public/.htaccess`). After a deployment, close and reopen the app once. To force every device to drop old static caches, increase `CACHE_VERSION` in `public/sw.js`.

### 5. Offline behaviour
- Page loads always go to the server. If the connection fails, `public/offline.html` explains (English and Kiswahili) that internet is required.
- Only these are cached: `/build/*` (CSS/JS), `/icons/*` and the offline page.
- Never cached: HTML pages, login, JSON/AJAX lookups, uploads, receipts, exports, member, finance or user data. POST/PUT/PATCH/DELETE are never intercepted, so Laravel sessions and CSRF are untouched.
- After logout nothing sensitive remains in Cache Storage because it was never stored.
- Registration happens only on HTTPS or `localhost`.

---

## Part 2 - Asura Hosting preparation

### 6. Requirements to CONFIRM with Asura
| Item | Needed |
| --- | --- |
| PHP | 8.3 or newer selectable in DirectAdmin (Laravel 13) |
| Extensions | mbstring, openssl, pdo_mysql, fileinfo, intl, ctype, json, tokenizer, xml, curl, gd |
| MySQL | 8.0+ / MariaDB 10.6+ |
| SSH or Terminal | Strongly preferred (artisan, symlink). Otherwise use the no-SSH alternatives below |
| Cron jobs | Needed for SMS queue (every minute) |
| Free SSL | Let's Encrypt in DirectAdmin |
| Upload limits | `upload_max_filesize` and `post_max_size` of at least 10M for member imports |
| Symlinks | Needed for `storage:link`; else use the alternative below |

Do not assume Node.js, long-running workers or Composer on the server. The package is built locally and already contains the compiled assets and production `vendor/`.

### 7. Domain and HTTPS
1. Point the domain's DNS to Asura's nameservers or A record.
2. DirectAdmin -> **SSL Certificates** -> Let's Encrypt for the domain and `www`.
3. Enable **Force SSL with https redirect**. Set `APP_URL=https://your-domain` and `SESSION_SECURE_COOKIE=true`.

### 8. Create the MySQL database
DirectAdmin -> **MySQL Management -> Create new database**. Note the full database name, user and password (they carry the account prefix, e.g. `acct_kkkt`). Use a strong generated password. Never reuse it elsewhere.

### 9. Upload securely - recommended layout
Only `public/` may be web-visible. Keep everything else above the web root:

```
/home/USER/kkkt/                     <- app: app, bootstrap, config, vendor, storage, .env  (NOT web accessible)
/home/USER/domains/DOMAIN/public_html/   <- contents of kkkt/public only
```
Steps:
1. Build the package locally: `powershell -ExecutionPolicy Bypass -File docs\build-deploy-package.ps1` (after `npm run build`). It contains no `.env`, no `.git`, no dev packages, no logs.
2. DirectAdmin **File Manager**: upload the zip to `/home/USER/`, extract into `/home/USER/kkkt/`.
3. Move the contents of `/home/USER/kkkt/public/` into `public_html/` (keep `.htaccess`, `index.php`, `build/`, `icons/`, `sw.js`, `manifest.webmanifest`, `offline.html`, `favicon.*`).
4. Edit `public_html/index.php` and point to the app folder:
   ```php
   require '/home/USER/kkkt/vendor/autoload.php';
   $app = require_once '/home/USER/kkkt/bootstrap/app.php';
   ```
   Also change the maintenance line to `file_exists($maintenance = '/home/USER/kkkt/storage/framework/maintenance.php')`.
5. Check in a browser that `https://DOMAIN/.env`, `/vendor/` and `/storage/` return 403/404.

### 10. Production environment
1. Copy `.env.production.example` to `/home/USER/kkkt/.env` and fill DB values. Keep `APP_DEBUG=false`.
2. Generate the key once: `cd /home/USER/kkkt && php artisan key:generate` (no SSH: generate locally with `php artisan key:generate --show` and paste it). **Never regenerate the key after data exists** - encrypted SMS credentials and sessions would become unreadable.
3. Set permissions: `chmod -R 775 storage bootstrap/cache`.
4. SMS credentials are entered in Settings by the Super Admin and stored encrypted. Do not put them in `.env`.

### 11. Run migrations safely
1. **Back up first**: DirectAdmin -> **Create/Restore Backups** or export via phpMyAdmin. Keep the file outside `public_html`.
2. Review pending migrations: `php artisan migrate:status`.
3. Apply: `php artisan migrate --force`. This includes the additive migration `2026_11_04_171119_add_super_admin_audit_and_invitation_fields`, required for user invitation tracking. Never run `migrate:fresh`, `db:wipe` or `migrate:rollback` on production data.
4. First install only: create the Super Admin through the project's existing seeding/setup process.
   No SSH: import the schema through phpMyAdmin from a locally generated SQL dump of an empty, migrated database.

### 12. Storage link
Uploaded logos live in `storage/app/public`.
- With SSH: `ln -s /home/USER/kkkt/storage/app/public /home/USER/domains/DOMAIN/public_html/storage`
- Without symlink support: ask Asura to enable it, or copy uploads into `public_html/storage/` after each change (not recommended).

### 13. Optimize
```
cd /home/USER/kkkt
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
After changing `.env` run `php artisan config:clear` then `config:cache` again.

### 14. Queue and SMS (no worker on shared hosting)
SMS confirmations, invitations and campaigns are queued jobs. Add a DirectAdmin **Cron Job** every minute:
```
* * * * * /usr/local/bin/php /home/USER/kkkt/artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```
(CONFIRM the PHP binary path with Asura, e.g. `php83`.) Without cron, SMS stays queued and is not sent. Alternative: `QUEUE_CONNECTION=sync` sends during the request and can slow forms or time out on slow gateways.

---

## Part 3 - Verify, troubleshoot, roll back

### 15. Verify the production app
- `https://DOMAIN/up` returns OK; login works over HTTPS; sidebar, finance and members load.
- DevTools -> Application: manifest has no errors, service worker is active, Cache Storage holds only `kkkt-static-*` with `/build` and `/icons` files.
- Switch off the network: reloading shows the offline page. Log out: no HTML in Cache Storage.
- An error page must not show stack traces (`APP_DEBUG=false`).
- Send a test SMS only from the Settings screen once the gateway is configured.

### 16. Common errors
| Symptom | Cause / fix |
| --- | --- |
| 500, blank page | Check `storage/logs/laravel.log`; wrong paths in `index.php`; `storage` or `bootstrap/cache` not writable |
| "No application encryption key" | `APP_KEY` missing in `.env`, then `config:clear` |
| 419 Page Expired | `SESSION_SECURE_COOKIE=true` while on HTTP, or wrong `APP_URL`/`SESSION_DOMAIN` |
| Missing CSS/JS | `public/build` not uploaded, or stale config cache |
| Column not found (e.g. `financial_sms_logs.user_id`) | Pending migration: `php artisan migrate --force` after backup |
| Logo not shown | Storage link missing |
| SMS not sent | Cron not running, or gateway not configured in Settings |
| App not installable | Not HTTPS, manifest or `sw.js` blocked, or icons missing |
| Old design after deploy | Run `config:cache`/`view:cache`; reopen the installed app; raise `CACHE_VERSION` |

### 17. Roll back
1. Keep the previous release: rename `/home/USER/kkkt` to `/home/USER/kkkt_prev` before extracting a new one and copy `.env` across.
2. Enable maintenance: `php artisan down`.
3. If a migration failed or data is wrong, restore the database backup taken in step 11. Do not edit tables by hand.
4. Restore the previous folder name, `php artisan config:clear`, then `php artisan up`.
5. Raise `CACHE_VERSION` only if static assets were mismatched.

---

## Checklist before go-live / Orodha ya mwisho
- [ ] PHP 8.3+, extensions and MySQL confirmed with Asura
- [ ] SSL active and forced
- [ ] `.env` created on the server only, debug off, key generated once
- [ ] Database backup taken, migrations applied
- [ ] Only `public/` contents in `public_html`; `.env`/`vendor`/`storage` not reachable
- [ ] Storage link and cron queue worker working
- [ ] Install test on Windows and Android
- [ ] Owner approval received before going live
