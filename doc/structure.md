# Project Structure (Novocib)

This document explains **how the repository is organized**, what each major directory is for, and which files are the main entrypoints.

## Quick orientation (where to start)

If you are new to the codebase, these are the best starting points:

- `index.php` — front controller (bootstrap + dispatch)
- `routes.php` — URL → PHP file mapping
- `redirects.php` — legacy URL redirects (runs before routing)
- `.htaccess` — Apache rewrite rules, headers, caching, and access restrictions
- `app/templates/` — layout shells used by public pages
- `app/views/` — the public website pages
- `app/internal/admin/` — the internal admin interface

## High-level request flow (mental model)

1. **Apache** receives a request and rewrites most paths to `index.php` (see `.htaccess`).
2. `index.php` loads `redirects.php` first; if a legacy URL matches, it sends a `Location:` redirect and exits.
3. `index.php` normalizes the request path and looks it up in `routes.php`.
4. The matched route target is included (often a file under `app/views/`, sometimes a controller/logic script).
5. When a route is missing, `app/views/404.php` renders the 404 page and logs it.

## Repository tree (annotated)

> Note: some folders (e.g. `Design/`, `FA/`) are mostly asset sources. The runtime application code is primarily under `app/`.

```
.
├─ index.php                 # Front controller router (entrypoint)
├─ .htaccess                 # Apache rewrite + headers + caching + access rules
├─ routes.php                # Route map: '/path' => 'app/.../*.php'
├─ redirects.php             # Legacy redirects map
├─ robots.txt                # Search-engine directives
├─ sitemap.xml(.gz)          # Sitemap assets
├─ README.md                 # Operational overview & setup
├─ structure.md              # (this file) repository architecture guide
│
├─ app/                       # Main application code
├─ config/                    # Runtime configuration (DB + crypto)
├─ sql/                       # Per-table schema scripts
├─ db/                        # Combined schema scripts (and legacy variants)
├─ logs/                      # Log files (PHP error log, message log, etc.)
├─ tests/                     # URL test scripts + route lists
│
├─ Design/                    # Design sources / exports (not runtime)
├─ FA/                        # Font Awesome assets (served locally)
├─ server/                    # Alternate/duplicate config helpers (server-side)
├─ Mails.txt, products.md     # Notes / docs / content artifacts
└─ *.7z                       # Archived assets
```

## `app/` directory (main application)

`app/` contains the public site, the internal tools, and the backend logic.

### `app/views/` — public website pages

**Purpose:** Each PHP file is effectively a page. Most pages:

- set a page title (`$title`)
- include a base template (`app/templates/base.php` or `app/templates/new_base.php`)
- assemble HTML with components (banner/nav/footer/etc.)

Notable files:

- `app/views/home.php` — home page
- `app/views/search.php` — public search page
- `app/views/404.php` — 404 page + 404 logging
- `app/views/contact-us.php`, `app/views/inquiry.php` — form pages
- `app/views/secure/*` — “secure” customer pages

Subfolders like `active-purified-enzymes/`, `freshness-assay-kits/`, etc. group related pages.

### `app/templates/` — layout shells

**Purpose:** shared HTML layout and page scaffolding.

- `app/templates/base.php`
  - Defines `addContent()` and `render()` functions
  - Pages explicitly call `render()` when they’re done
- `app/templates/new_base.php`
  - Uses output buffering and `register_shutdown_function()` to auto-render
  - Pages don’t need to call `render()` manually

Both templates:

- include the navigation (`Nav::bar()`)
- include CSS/JS (Bootstrap, main styles, app JS)
- include a footer (`Footer::gen()`)

### `app/components/` — reusable UI building blocks

**Purpose:** small PHP classes that generate common HTML.

Examples:

- `Nav.php` — navigation markup
- `Footer.php` — footer markup
- `Banner.php`, `Searchbar.php`, etc. — content components

The code uses `app/components/autoloader.php` to autoload component classes by filename.

### `app/css/`, `app/js/`, `app/img/`, `app/static/` — public assets

**Purpose:** frontend assets referenced by templates and views.

- Bootstrap is included from `app/css/bootstrap.min.css` and `app/js/bootstrap.bundle.min.js`.
- Site-specific JS is in `app/js/` (e.g. `app.js`, `search-bar.js`).

### `app/logic/` — backend scripts and helpers

**Purpose:** “actions” (form handlers), utility scripts, and DB operations.

Common patterns:

- Many scripts only accept POST and redirect otherwise.
- Some scripts have direct-access protection (redirecting to `/404`).

Notable files:

- `send.php` — handles general contact/request forms
- `send_inquiry.php` — handles inquiry forms (with product fields)
- `spam_detector.php` — flags gibberish spam; spam is stored but not emailed
- `db_operations.php` — many DB helper functions (search index, users, logs)
- `log404.php` — writes 404 requests to DB (skips static file extensions)
- `env-loader.php` — loads `.env` into environment variables (optional)

### `app/db/` — DB connection helper

**Purpose:** central MySQL connection code.

- `app/db/connect.php` chooses between `config/db_config_local.php` (localhost) and `config/db_config` (non-local).
- Returns a PDO connection configured for exceptions.

### `app/models/` — data objects

**Purpose:** simple PHP classes used as typed containers.

Examples:

- `Product`
- `Customer`
- `Card`

### `app/repository/` — persistence layer

**Purpose:** classes that read/write models to MySQL.

- `ProductRepository.php` — CRUD for `products`
- `Message_repository.php` — inserts and fetches from `contact_messages`
- `CustomerRepository.php` — stores customers (encrypted fields)
- `CardRepository.php` — stores card data into `data` (encrypted blobs)

### `app/security/` — encryption

**Purpose:** application-level encryption helpers.

- `Encryption.php` — symmetric encryption for some customer fields
- `SecureEncrypt.php` — PBKDF2-derived key + IV-per-value encryption for card data

Important operational note:

- Secrets/keys live under `config/` in this repo. Treat them as sensitive and don’t publish them as-is.

### `app/controllers/` — controller-style endpoints

**Purpose:** endpoint scripts used by routes for actions that don’t live in `app/logic/`.

Notable:

- `SecureLogin.php` — validates customer credentials and starts a session
- `SaveCardInfo.php` — stores card data + updates customer record
- `SecureInfoController.php` — secure info endpoint (internal/secure use)

### `app/internal/` — internal tools

This area is separate from the public pages.

#### `app/internal/admin/` — admin interface

**Purpose:** operational back office (products, messages, search entries, logs).

- Login: `app/internal/admin/login.php` posts to `app/internal/admin/controllers/login.php`
- Session helpers: `app/internal/admin/session/*`
- Uses DB helpers from `app/logic/db_operations.php`

Common sections inside admin:

- product management (`products`)
- message management (`contact_messages`)
- search index management (`articles`)
- visitor searches (`searches`)
- 404 request logs (`request404`)

#### `app/internal/share/` — simple password-gated page

**Purpose:** lightweight “share a file” page protected by a hardcoded password.

Operational note:

- If this is internet-facing, consider removing it or moving the password to configuration.

## `config/` directory

**Purpose:** configuration functions loaded by code.

- `config/db_config_local.php` — local DB connection values
- `config/db_config` — non-local DB connection values
- `config/data` and `config/sec` — crypto settings

Because these files contain sensitive values, treat them as secrets.

## `sql/` and `db/` directories

**Purpose:** database schema scripts.

- `sql/` contains per-table schema scripts (e.g. users, products, contact_messages, etc.).
- `db/1 structure.sql` contains a combined schema that matches the app’s tables.

## `tests/` directory

**Purpose:** operational checks.

- `tests/test-urls.ps1` — PowerShell script to validate a list of URLs against a base URL and output CSV results.
- `tests/local-urls.txt` and `tests/routes.txt` — URL lists.

## `FA/` and `Design/`

**Purpose:** assets.

- `FA/` appears to contain locally served Font Awesome assets.
- `Design/` contains design sources/exports and is not required to run the backend.

---

## Suggested conventions (optional, but helpful)

If you continue evolving the project, these conventions will keep it tidy:

- Keep **public pages** in `app/views/` and keep **form handlers** in `app/logic/` (or controllers if you prefer that pattern).
- Prefer a single DB entrypoint (`app/db/connect.php`) to avoid drift.
- Keep redirects centralized in `redirects.php`.
- Avoid hardcoding secrets in repo; load them from `.env` or server secrets.
