# KKKT CMS

Church management system built with Laravel 13, PHP 8.3, MySQL, Blade, Tailwind CSS, and Alpine.js.

## Requirements

- PHP 8.3 or later with `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `phar`, `session`, `tokenizer`, and `xml`
- MySQL 8.0 or later
- Composer 2
- Apache with `mod_rewrite` and `.htaccess` enabled
- HTTPS for the production domain

Node.js is only needed when changing frontend source files. The compiled Vite assets are committed in `public/build`, so Node.js is not required on the hosting server.

## Shared hosting deployment

Use a hosting account that provides SSH or a terminal, PHP 8.3, Composer, MySQL, and a scheduled cron job. Confirm those features with the host before deploying.

### 1. Keep application code outside the public web root

The safest setup is to set the domain's document root to the repository's `public` directory. For example:

```text
/home/CPANEL_USER/kanisa/public
```

Clone or upload the repository outside `public_html`, for example to `/home/CPANEL_USER/kanisa`. Never put `.env`, `vendor`, `storage`, or the application source directly in the public web root.

If the host requires `public_html`, copy the **contents** of `kanisa/public` into `public_html`. Then edit `public_html/index.php` so all three Laravel paths point to the application directory outside the web root:

```php
if (file_exists($maintenance = __DIR__.'/../kanisa/storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../kanisa/vendor/autoload.php';
$app = require_once __DIR__.'/../kanisa/bootstrap/app.php';
```

These example paths assume `kanisa` and `public_html` are sibling directories. Adjust them if your hosting account uses a different location. Keep the copied `.htaccess` file, and do not overwrite the customized `index.php` during updates.

In this `public_html` arrangement, also point `public_html/storage` to the application's `storage/app/public` directory. For example, if symbolic links are allowed:

```sh
ln -s ../kanisa/storage/app/public /home/CPANEL_USER/public_html/storage
```

If the host blocks symbolic links, ask the provider for a supported public-storage setup before enabling file uploads.

### 2. Configure production environment

Copy `.env.example` to `.env` in the private application directory. Set real values in the server's `.env`; never commit or upload a local development `.env`.

At minimum, configure:

```dotenv
APP_NAME="KKKT CMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Use the database host and credentials supplied by your provider. Configure SMTP settings if the application will send email. Keep `APP_DEBUG=false` and serve the site only over HTTPS.

For the first installation only, also set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and a unique `INITIAL_ADMIN_PASSWORD` of at least 12 characters. These values are required by the production-safe seeders; the application no longer creates a default administrator account.

### 3. Install dependencies and initialize the database

From the repository directory, run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

`db:seed` creates the initial administrator, roles, permissions, and default system settings. Change the administrator password after the first login. Once seeding succeeds, remove the three `INITIAL_ADMIN_*` values from `.env` so the initial password is not kept on the server, then cache production configuration:

```sh
php artisan optimize:clear
php artisan optimize
```

For the recommended document root (`kanisa/public`), run `php artisan storage:link` so public uploads can be served.

Back up the production database before running migrations during an update. For subsequent releases, update the code, run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, run `php artisan migrate --force`, and refresh the caches. Do not run `key:generate` again on an existing installation; changing `APP_KEY` can make encrypted data unreadable. Do not rerun the initial seeders during routine updates.

The PHP process must be able to write to `storage` and `bootstrap/cache`. Set ownership and permissions according to your hosting provider; do not make the entire project world-writable.

### 4. Process queued SMS

Finance SMS notifications use the database queue. Configure a cron job to process queued jobs once per minute. Replace the PHP binary and project paths with the values from your host:

```cron
* * * * * cd /home/CPANEL_USER/kanisa && /usr/local/bin/php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Without a working queue worker, queued SMS messages will remain pending. Only enable live SwalaSMS after saving the API key and sender ID and testing the connection from **System Settings**.

### 5. Updating frontend assets

The compiled assets are checked in so deployments do not depend on Node.js being installed on shared hosting. When frontend source changes, build and commit the updated assets from a development machine:

```sh
npm ci
npm run build
git add public/build
```

### 6. Verify the deployment

- Visit `/up` and confirm the application health endpoint responds.
- Sign in with the initial administrator and change the password.
- Confirm HTTPS, member pages, file uploads, and database writes work.
- Check Laravel logs in `storage/logs` and the hosting provider's PHP error log.
- Confirm the scheduled queue command runs before enabling live SMS.

## Local development

Install dependencies, create `.env` from `.env.example`, generate an application key, configure a local database, then run migrations and the development server:

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

For a fresh local database, set `INITIAL_ADMIN_NAME`, `INITIAL_ADMIN_EMAIL`, and `INITIAL_ADMIN_PASSWORD` in `.env`, then run `php artisan db:seed` to create the first administrator and system roles.

Run the test suite with:

```sh
php artisan test
```
