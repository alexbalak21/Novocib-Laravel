
# Novocib Website (PHP)

This repository is a PHP + Apache (mod_rewrite) website with a small “front controller” router, a view/template system, MySQL-backed features (search, products, messages, admin), and a “secure” flow for storing payment details in an encrypted form.

## Table of contents

- [How requests are handled](#how-requests-are-handled)
- [Project structure](#project-structure)
- [Local development](#local-development)
- [Configuration](#configuration)
- [Database](#database)
- [Routing](#routing)
- [Forms & email](#forms--email)
- [Internal admin](#internal-admin)
- [Secure flow (payment info)](#secure-flow-payment-info)
- [Testing utilities](#testing-utilities)
- [Deployment notes](#deployment-notes)
- [Known gotchas](#known-gotchas)

## How requests are handled

High level flow:

1. Apache rewrites most requests to `index.php` (see `.htaccess`).
2. `index.php` loads legacy redirects (`redirects.php`) first.
3. `index.php` resolves the normalized request path and looks it up in `routes.php`.
4. The matched route target is `include`’d (view/controller/logic script).
5. If nothing matches, `app/views/404.php` is loaded.

Key files:

- `index.php` — request bootstrap + route dispatch.
- `routes.php` — route map (`/path` → PHP file).
- `redirects.php` — legacy URL redirects (`/OldPage.html` → `/new-path`).
- `.htaccess` — rewrite rules + caching + headers.

## Project structure

Most of the application lives under `app/`:

- `app/views/` — public site pages.
	- Many pages include a template and then add content via components.
	- `app/views/404.php` logs 404s.
- `app/templates/` — shared layout shells.
	- `base.php`: manual `addContent()` + `render()` flow.
	- `new_base.php`: output-buffer auto-render at shutdown.
- `app/components/` — small HTML generators (header, nav, footer, banners, cards, etc.).
	- `app/components/autoloader.php` registers a simple autoloader for `app/components/*.php`.
- `app/js/`, `app/css/`, `app/img/`, `app/static/` — client assets.
- `app/db/` — DB connection helper.
- `app/logic/` — form handlers and DB helpers.
- `app/models/` — lightweight data objects.
- `app/repository/` — persistence layer (products, messages, customers, cards).
- `app/security/` — encryption helpers.
- `app/internal/` — internal tools:
	- `app/internal/admin/` admin UI (products, messages, search index, users, etc.).
	- `app/internal/share/` simple password-gated download page.

Other notable folders:

- `config/` — runtime configuration (DB config and encryption parameters).
- `sql/` and `db/` — schema + seed SQL.
- `tests/` — URL testing scripts and URL lists.

## Local development

### Prerequisites

- PHP 8.0+ (code uses `str_starts_with` / `str_ends_with`).
- Apache with `mod_rewrite` enabled (recommended for accurate local routing).
- MySQL / MariaDB.

### Option A (recommended): Apache (XAMPP/WAMP/MAMP)

1. Point your virtual host / document root to the repository root (the folder containing `index.php`).
2. Ensure Apache allows `.htaccess` overrides (`AllowOverride All`) and `mod_rewrite` is enabled.
3. Create your local DB config in `config/db_config_local.php`.
4. Import schema (see [Database](#database)).
5. Browse the site (example: `http://localhost/`).

### Option B: PHP built-in server (router mode)

This won’t use `.htaccess`, but you can still route through `index.php`:

```bash
php -S localhost:3000 index.php
```

Notes:

- Scripts use `$_SERVER['DOCUMENT_ROOT']` heavily. With the built-in server, it typically points at the current working directory (start the server from the repo root).

## Configuration

### Database configuration

The main DB connection helper is `app/db/connect.php`:

- If running on localhost (`SERVER_NAME` is `localhost` / `127.0.0.1` or `REMOTE_ADDR` is loopback), it loads `config/db_config_local.php`.
- Otherwise it loads `config/db_config`.

Both config files define a `get_config()` function that returns:

```php
[
	'host' => '...',
	'username' => '...',
	'password' => '...',
	'database' => '...',
]
```

### Encryption configuration

There are two encryption helpers:

- `app/security/Encryption.php` uses `config/data` (`get_data()` returns method + key + IV).
- `app/security/SecureEncrypt.php` uses `config/sec` (`get_sec()` returns PBKDF2 settings + base key).

Important:

- These config files currently contain secret material in-repo. Treat them as secrets and rotate/move them to your secret store if this project is deployed.

### Optional `.env`

`app/logic/env-loader.php` loads a `.env` file from the web root (`DOCUMENT_ROOT/.env`) into `putenv()` / `$_ENV` / `$_SERVER`.

At the moment, most configuration is read from `config/*` files rather than environment variables, but the loader exists if you want to migrate.

## Database

Schemas are provided in:

- `sql/*.sql` (individual tables)
- `db/1 structure.sql` (combined schema)

Common tables used by the app:

- `articles` — search index used by `/search`.
- `searches` — visitor search queries logged by `logSearch()`.
- `products` — products shown/managed in internal admin.
- `contact_messages` — contact/inquiry messages stored from public forms.
- `users` — internal admin authentication.
- `customers` + `data` — “secure” customer + encrypted card data.
- `request404` — 404 request logging.

### Importing schema

Pick one approach:

1) Import combined schema:

```bash
mysql -u root -p YOUR_DB_NAME < "db/1 structure.sql"
```

2) Or import per-table from `sql/`.

You’ll also find seed/data scripts (for example `*_data.sql`) depending on environment.

## Routing

Routes are defined in `routes.php` as a PHP array.

Example:

```php
return [
	'/search' => 'app/views/search.php',
	'/send'   => 'app/logic/send.php',
];
```

To add a new page:

1. Create a view in `app/views/...`.
2. Add a route mapping in `routes.php`.
3. In your view, include one of the templates:
	 - `app/templates/base.php` (manual `addContent()`/`render()`), or
	 - `app/templates/new_base.php` (auto-render on shutdown).

### Redirects

Legacy redirects are centralized in `redirects.php` and run before route handling.

If you need a new “old URL → new URL” mapping, add it there (rather than trying to redirect via `routes.php`).

## Forms & email

Public form handlers:

- `app/logic/send.php` — handles “contact/request” type submissions.
- `app/logic/send_inquiry.php` — handles inquiry submissions (supports product fields like `ref`, `price`, etc.).

Shared behavior:

- Requires POST.
- Sanitizes inputs.
- Runs spam detection (`app/logic/spam_detector.php`), which flags “gibberish” and stores it without emailing.
- Persists messages via `app/repository/Message_repository.php` into `contact_messages`.
- Sends email via PHP `mail()` (server must be configured with a working mail transport).
- Writes operational errors to `logs/message.log`.

## Internal admin

Admin UI is under:

- `app/internal/admin/`

It uses:

- Login controller: `app/internal/admin/controllers/login.php`
- Session helpers: `app/internal/admin/session/*`
- DB helpers: `app/logic/db_operations.php`

Authentication:

- Admin credentials are stored in the `users` table.
- Passwords are hashed (see `create_user()` in `app/logic/db_operations.php`).
- Session lifetime is ~30 minutes (see `check_id()` and session timestamps).

Admin features (high level):

- Manage products (`products` table)
- Read/delete messages (`contact_messages` table)
- Manage search index entries (`articles` table)
- View/delete 404 logs (`request404` table)
- View visitor searches (`searches` table)

## Secure flow (payment info)

There is a “secure” customer flow with routes in `routes.php`:

- `/secure/login` (view)
- `/secure/login-c` (controller)
- `/secure/transfer` (view)
- `/secure/store` (controller)
- `/secure/success` (view)

Implementation pieces:

- `app/controllers/SecureLogin.php` validates a customer password and starts a session.
- `app/controllers/SaveCardInfo.php` stores encrypted card info into `data` and updates `customers` with the card reference + key.
- Encryption is handled by `app/security/SecureEncrypt.php` and the `CardRepository`.

Security note:

- This repository stores payment card-like data (encrypted) in a database. Encryption alone is not equivalent to PCI compliance. If this is used in production, review the entire flow (transport security, key management, retention policies, access controls, logging, backups) with appropriate security/compliance guidance.

## Testing utilities

There is a PowerShell URL tester:

- `tests/test-urls.ps1`

Example:

```powershell
cd tests
./test-urls.ps1 -UrlFile local-urls.txt
```

It will write `url_test_results.csv` with response status and timing.

## Deployment notes

Apache is expected.

- Ensure `mod_rewrite` is enabled.
- Ensure the vhost allows `.htaccess` overrides.
- Confirm the `logs/` folder is writable by the PHP process (PHP errors go to `logs/php_error.log`).
- Configure outbound email (PHP `mail()` needs a working MTA/SMTP bridge).

## Known gotchas

- Redirects belong in `redirects.php`. Some routes in `routes.php` look like redirects (e.g. `'/contact' => '/contact-us'`), but the front controller includes files; it does not perform `Location:` redirects for route targets.
- There are two different `connect_db()` implementations:
	- `app/db/connect.php` (used by repositories and most DB code; supports local-vs-prod config)
	- `app/logic/connect_db.php` (older helper; always reads `config/db_config`)
	Prefer `app/db/connect.php` for consistency.
- Secrets are currently committed under `config/` (DB passwords and encryption material). If you plan to deploy this publicly, rotate these values and move them out of the repository.

