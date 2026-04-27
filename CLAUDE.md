# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**MRKSolutions** — a supply chain / inventory management portal built on the [Adianti Framework](https://www.adianti.com.br/) (PHP, v7.6). It handles inventory, purchasing, finance, production, sales, stock balance, and internal communications for a multi-tenant (multi-unit) organization.

## Development Commands

```bash
# Install PHP dependencies
composer install

# Run development server (PHP built-in)
php -S localhost:8000

# Run CLI commands (server-side tasks, reports, etc.)
php cmd.php "class=ClassName&method=methodName&param=value"
```

There is no build step — this is pure PHP with static CSS/JS assets.

**No test or linting infrastructure** exists in the project (no phpunit.xml, phpstan.neon, .phpcs.xml, or tests/ directory).

## Architecture

### Entry Points

| File | Purpose |
|------|---------|
| `index.php` | Main web app — loads session, theme template, XML menu, then routes to engine |
| `engine.php` | AJAX/action handler — checks permissions, instantiates controller, calls method |
| `rest.php` | REST API v0 — only allows classes extending `AdiantiRecordService` |
| `api/v1/index.php` | Modern REST API (45+ controllers, JWT auth, CORS-enabled) |
| `cmd.php` | CLI runner — PHP CLI access only |

### Request Flow

Requests arrive as query-string parameters: `?class=ClassName&method=methodName&id=123`

1. `index.php` renders the shell (theme, menu parsed from `menu.xml`)
2. Page content loaded via AJAX through `engine.php`
3. `engine.php` checks `SystemPermission::checkPermission()`, instantiates the class, calls the method
4. The controller renders form/list/report HTML back to the browser

### Two Controller Patterns

**Pattern 1 — Modern (iframe wrapper):** Extends `MRKIframePage` (`app/lib/MRKIframePage.class.php`). Used for newer features backed by external HTML/JS apps in `/external/`. Passes auth tokens via URL, handles iframe embedding and resize.

```php
class BalancoManual extends MRKIframePage {
    protected function getFrontendUrl(): string {
        return 'external/balancoManual.html';
    }
}
```

**Pattern 2 — Traditional (Adianti forms):** Extends `TPage`. Uses Adianti widgets (`TEntry`, `TCombo`, `TDataGrid`, etc.) to build forms and lists entirely in PHP.

### Model Pattern (Active Record ORM)

Models extend `TRecord` and live in `app/model/`:

```php
class Estoque extends TRecord {
    const TABLENAME = 'estoque';
    const PRIMARYKEY = 'id';
    const IDPOLICY = 'max'; // or 'serial'

    public function __construct($id = NULL, $callObjectLoad = TRUE) {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('insumo_id');
        parent::addAttribute('quantity_available');
    }
}
```

### API Layer (`api/v1/`)

Modern REST API with JWT-based auth. Controllers in `api/v1/controllers/` follow a pattern of receiving JSON, querying models (or raw SQL), and returning JSON. Public endpoints bypass token checks; all others require a valid bearer token.

### Databases

The app uses **SQLite** databases (in `app/database/` and `api/v1/database/`):

| Database | Purpose |
|----------|---------|
| `permission.db` | RBAC — users, groups, roles, permissions, units |
| `log.db` | Audit trail — change log, SQL log, access log, request log |
| `communication.db` | Internal messaging and notifications |
| `unit_*.db` | Per-tenant business data (multi-unit architecture) |

Connection names are declared in `app/config/*.ini` files and referenced by string name throughout the app.

### Navigation / Routing

The menu is XML-driven (`menu.xml`, `menu-public.xml`). Menu entries declare the controller class name; the framework handles instantiation. Adding a new page requires: creating the controller class + adding a menu entry (or linking from another controller).

### Key Directories

```
app/control/        # Controllers (143 files) — admin/, log/, communication/, public/
app/model/          # Active Record models (221 files)
app/service/        # Business logic — auth/, log/, system/, cli/
app/lib/            # Custom base classes, widgets, validators, utilities
app/config/         # application.ini + database connection INIs
app/templates/      # Theme HTML shells (theme3–theme6)
api/v1/controllers/ # Modern REST API controllers
external/           # Standalone HTML/JS frontend apps (wrapped via MRKIframePage)
```

### Configuration

`app/config/application.ini` controls timezone (`America/Sao_Paulo`), language (`pt`), active theme, multi-unit/multi-database flags, public class declarations, and optional LDAP auth. Copy `.env.example` to `.env` for AWS SQS and RabbitMQ credentials (not committed).

### Key Dependencies (composer.json)

`phpmailer/phpmailer`, `dompdf/dompdf`, `firebase/php-jwt`, `bacon/bacon-qr-code`, `picqer/php-barcode-generator`, `linfo/linfo`, plus Adianti plugins for PDF/spreadsheet export.
