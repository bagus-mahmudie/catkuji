# Catkuji

Catkuji is a Laravel and Vue application for kuji event catalogs, prize draws, inventory, wallets, payments, and shipping.

**Status:** Local development; a public demo is not yet available. The instructions below describe local setup. They are not a claim that every feature or external integration has been tested on every operating system.

## Features

- Kuji events, prize catalogs, draws, tokens, and user inventory
- Wallet balances, transaction records, and Midtrans payments
- Shipment requests and Biteship integration
- Email/password registration, email OTP verification, and optional social login
- Administration of products, categories, users, banners, payments, and orders

## Technology and requirements

| Component | Requirement |
| --- | --- |
| Backend | PHP 8.2 or newer within the PHP 8 series, Composer 2, Laravel 12 |
| Frontend | Vue 3, Inertia.js 2, Tailwind CSS, Vite 7 |
| Node.js | Node 22.12+ or 24.x with npm; the locked Vite version requires `^20.19.0 || >=22.12.0` |
| Database | A local MySQL server and a new, empty database |
| PHP extensions | Ctype, cURL, DOM/XML, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO MySQL, Session, Tokenizer; ZIP for dependency extraction and GD for image/PDF work |
| Tests | PDO SQLite and SQLite support for the in-memory database configured in `phpunit.xml` |

Use `composer check-platform-reqs` after installation to check the installed dependencies against your PHP environment. MySQL is the application setup described here; the SQLite test configuration is not a substitute for testing MySQL-specific behavior.

XAMPP can provide PHP and MySQL on Windows. Start MySQL in the XAMPP Control Panel. Apache is not required when using `php artisan serve`.

Check your terminal tools before starting:

```sh
php -v
php -m
composer --version
node --version
npm --version
```

On Windows, add the PHP directory (for example, `C:\xampp\php`) to your user `PATH` and reopen the terminal if `php` is not recognized. CLI PHP and Apache PHP can use different configuration files; `php --ini` shows the CLI configuration.

## 1. Download the source

Clone the repository:

```sh
git clone https://github.com/bagus-mahmudie/catkuji.git
cd catkuji
```

Alternatively, use **Code → Download ZIP**, extract it, and open a terminal in the extracted directory containing `artisan`, `composer.json`, and `package.json`. Git is optional for the ZIP workflow. Run all subsequent commands from this directory unless stated otherwise.

## 2. Create your local configuration

Copy the template once, before editing it. Do not overwrite an existing `.env` containing your configuration.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

macOS/Linux:

```sh
cp .env.example .env
```

Create an empty database named `catkuji` through phpMyAdmin, or run this SQL in your MySQL client:

```sql
CREATE DATABASE catkuji CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edit these values in `.env` to match your own local MySQL account:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=catkuji
DB_USERNAME=your_local_mysql_user
DB_PASSWORD="your_local_mysql_password"

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
MIDTRANS_IS_PRODUCTION=false
```

Replace the example database username and password. If your local account has no password, use `DB_PASSWORD=`. A dedicated account with access only to this development database is sufficient. Keep the remaining settings from `.env.example` unless you need to change them.

With file sessions/cache and the synchronous queue, Redis and a queue worker are not needed for initial local setup. Leave optional API credentials empty until configuring those features. Store secrets only in `.env`, which is ignored by Git.

## 3. Install dependencies and initialize Laravel

```sh
composer install
composer check-platform-reqs
npm ci
php artisan key:generate
php artisan migrate
php artisan storage:link
npm run build
```

- `composer install` and `npm ci` use the committed dependency lock files. Internet access is required on the first installation.
- `key:generate` creates your own application encryption key. Generate it for a new installation; do not regenerate it routinely on an existing installation.
- `migrate` creates the database tables. Check that `.env` points to the empty local database first.
- `storage:link` exposes public uploads through `public/storage`.
- `npm run build` creates the frontend bundle under `public/build`.

These explicit steps let you configure the database before migrations run. The `composer setup` shortcut also installs dependencies and runs migrations, so it should not be used as a replacement for configuring `.env` first.

## 4. Optional sample catalog

On a disposable local development database, run:

```sh
php artisan db:seed
```

`DatabaseSeeder` runs `UserSeeder` and `HomepageCatalogSeeder`. It creates sample accounts and catalog data. Without seeding, the database starts without sample catalog content.

**There is no default login password.** The sample account passwords are randomly generated and are not printed. Register your own account using the steps below. `AdminUserSeeder` is a separate seeder and is not called by `DatabaseSeeder`; it also generates a random password.

Running `UserSeeder` again replaces passwords and account fields for its matching sample email addresses. Catalog seeding also writes sample records. Use seeders only on a local database whose sample data you are willing to change, not on a database containing real users or business records.

## 5. Start the application

After building the frontend:

```sh
php artisan serve --host=localhost --port=8000
```

Open [http://localhost:8000](http://localhost:8000). Keep the terminal running; press `Ctrl+C` to stop the server. Use the same hostname consistently for URLs, sessions, and OAuth callbacks.

For frontend development, keep the Laravel server running and open a second terminal in the project directory:

```sh
npm run dev
```

Continue opening the application at port **8000**. The Vite URL is the asset development server. Stop Vite and run `npm run build` when you want to use built assets again. If Vite was interrupted and the app still requests its old address, remove the stale generated `public/hot` file after confirming Vite has stopped.

## 6. Register and verify a local account

1. Open the registration form from the homepage, or visit `/register` to open it.
2. Enter your own email and password, confirm the password, and accept the terms. If supplying a phone number, use a valid Indonesian number.
3. With the default `MAIL_MAILER=log`, no email is delivered. Find the most recent verification email and its six-digit OTP in `storage/logs/laravel.log`.
4. Enter the OTP in the verification form in the same browser session. If it expires, request a new code.

Read the local mail log with PowerShell:

```powershell
Get-Content storage/logs/laravel.log -Tail 100
```

Or on macOS/Linux:

```sh
tail -n 100 storage/logs/laravel.log
```

Password-reset emails are also written to this log while the log mailer is selected. Keep the log private because it contains verification codes and reset links.

### Give your own local account administrator access

After registering and verifying your own account, open a second terminal and run:

```sh
php artisan tinker
```

Enter the following PHP in Tinker, replacing the email with the account you just registered:

```php
$user = App\Models\User::where('email', 'your-local-email@example.test')->firstOrFail();
$user->forceFill(['role' => 'super_admin', 'status' => 'active'])->save();
exit
```

This promotes only that account and keeps its chosen password. Use this step only in your own local installation. Open `/admin` with the verified account, or sign out and use `/admin/login`. `super_admin` includes administrator management access; use `admin` instead if you only need ordinary administrator access.

## Optional integrations

The homepage, local registration, and catalog administration can be explored without external credentials. Payment processing, shipping API requests, and social login require their respective services. They are not simulated automatically when keys are empty; a new wallet also starts without funds, so paid draws require additional setup.

### Email delivery

To deliver real email, set `MAIL_MAILER=smtp` and fill `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` in `.env`. Set `MAIL_SCHEME` according to your SMTP provider and `config/mail.php`. The current SMTP configuration reads `MAIL_SCHEME`, not the legacy `MAIL_ENCRYPTION` setting. Use a mail testing inbox for development if appropriate.

### Midtrans payments

Set your own sandbox `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, and, where needed, `MIDTRANS_MERCHANT_ID`. Keep `MIDTRANS_IS_PRODUCTION=false`. Configuration is in `config/midtrans.php`.

The notification endpoint is `POST /payments/midtrans/callback`. External services cannot call your computer's `localhost`; notification testing requires a reachable test URL, such as a development tunnel, configured in the provider dashboard. Do not use real payment credentials for a sample installation.

### Biteship shipping

In `.env`, configure `BITESHIP_API_KEY`, `BITESHIP_BASE_URL`, `BITESHIP_COURIERS`, and your own shipping-origin values: `BITESHIP_ORIGIN_CONTACT_NAME`, `BITESHIP_ORIGIN_CONTACT_PHONE`, `BITESHIP_ORIGIN_CONTACT_EMAIL`, `BITESHIP_ORIGIN_ADDRESS`, `BITESHIP_ORIGIN_POSTAL_CODE`, and optionally `BITESHIP_ORIGIN_NOTE`. See `config/services.php` for defaults.

Set `BITESHIP_WEBHOOK_SECRET` for `POST /shipping/biteship/webhook`. The current endpoint expects the matching `X-Biteship-Webhook-Secret` HTTP header. Confirm that your provider or test webhook setup sends this header; an absent or mismatched secret is rejected. A reachable test URL is needed for external delivery notifications.

### Google and Facebook login

Email/password login does not require OAuth. To enable Google login, set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback`.

Facebook configuration is supported by `config/services.php`; add `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, and `FACEBOOK_REDIRECT_URI=http://localhost:8000/auth/facebook/callback` to `.env` if using it. Register the exact callback URL with the provider. If the provider requires HTTPS, use your reachable HTTPS test URL consistently instead.

After changing integration configuration, run:

```sh
php artisan config:clear
```

Restart any long-running workers or development processes that use the changed configuration.

## Check your installation

- Confirm the homepage loads with styles and scripts.
- If seeded, confirm catalog entries are visible; otherwise create catalog content through your local admin account.
- Register a user and complete OTP verification.
- Confirm the promoted account can open `/admin`.
- Test external integrations separately after configuring their sandbox/test credentials.

For automated checks:

```sh
php artisan config:clear
php artisan test
npm run build
```

`phpunit.xml` selects an in-memory SQLite database. Ensure PDO SQLite is enabled and no shell-level database variables override the test configuration. Never point tests at a database with data you need to keep. Passing local tests does not verify live payment or shipping services.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| `php`, `composer`, or `npm` is not recognized | Install the required tool, add it to `PATH`, and reopen the terminal. In PowerShell, `npm.cmd` can be used if execution policy blocks `npm.ps1`. |
| Composer reports a missing extension | Run `php --ini` and `php -m`; enable the extension in the CLI PHP configuration and rerun `composer install`. Do not bypass platform checks. |
| `vendor/autoload.php` is missing | Run `composer install` from the directory containing `composer.json`. |
| `bootstrap/cache` or `storage` is not writable | Ensure the directories exist and grant your local PHP process access using normal filesystem permissions. Check security software or protected-folder restrictions; avoid granting everyone full access. |
| `Unknown database`, connection refused, or access denied | Start MySQL, create the database, verify `DB_*` values and permissions, then run `php artisan config:clear`. |
| `No application encryption key` | Run `php artisan key:generate` on the new installation. |
| Missing table | Verify the intended local database and run `php artisan migrate`. Do not use `migrate:fresh` on data you need to keep; it drops tables. |
| Vite manifest missing or page has no styles | Run `npm ci` and `npm run build`, or keep `npm run dev` running. Check Node compatibility and stale `public/hot` as described above. |
| `EPERM` during frontend build | Check file access, file locks, antivirus/protected folders, and whether the terminal can read the project directory. |
| Uploads return 404 | Run `php artisan storage:link` and check that the uploaded file exists. On Windows, symlink creation may require Developer Mode or an appropriately elevated terminal. |
| OTP email never arrives | With `MAIL_MAILER=log`, read `storage/logs/laravel.log`; SMTP delivery requires separate configuration. |
| Admin access returns 403 | Check that your own account is verified, active, and has the required `admin` or `super_admin` role. |
| Payment/shipping errors with a fresh `.env` | Configure the corresponding service credentials; these features need external APIs. |
| Port 8000 is already in use | Use another port, such as `php artisan serve --host=localhost --port=8001`, and update `APP_URL` and OAuth callback settings to match. |
| HTTP 419 or session problems | Use one hostname consistently, keep `SESSION_DOMAIN=null` locally, ensure session storage is writable, and clear site cookies after configuration changes. |

## Project structure

- `app/` — models, controllers, actions, services, and application logic
- `config/` — application and integration configuration
- `database/migrations/` — database schema changes
- `database/seeders/` — optional sample data
- `resources/` — Vue components, page views, and frontend source
- `routes/web.php`, `routes/auth.php`, `routes/admin.php` — application routes
- `public/` — web document root and generated frontend assets
- `storage/` — runtime files, logs, and uploads
- `tests/` — automated application tests

## Repository notes

Dependencies, private `.env` files, production databases, and user uploads are not included. Use your own local accounts and credentials. Keep `APP_DEBUG=true` and these local setup settings confined to development; public hosting requires separate deployment configuration and a web root pointing to `public/`.

Brand names and third-party assets remain subject to their respective owners' rights. This repository does not grant a license for reuse.
