# Application Admin

Laravel 12 administration application configured with Filament v4, MySQL, database-backed queue/cache/session storage, and common production development tooling.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
```

Set the MySQL values in `.env` before running migrations. The local scaffold uses `pos_admin`, `root`, and an empty password for WAMP.

## Admin Users

The default seeded administrator is:

- Email: `admin@example.com`
- Password: `password`

Create additional admins with:

```bash
php artisan make:filament-user
```

## Queues

The queue driver is `database`.

```bash
php artisan queue:work --queue=default --tries=3
php artisan queue:restart
```

## Development

```bash
php artisan serve
vendor/bin/pint
php artisan ide-helper:generate
php artisan telescope:prune
```

Debugbar and Telescope are intended for local use only. Keep `APP_DEBUG=false`, `DEBUGBAR_ENABLED=false`, and `TELESCOPE_ENABLED=false` in production.

## Accounting Engine

This POS admin uses double-entry accounting. Operational records such as sales invoices, purchase invoices, bank transactions, VAT returns, parties, and stock movements are input records only. The final accounting position is determined by `journal_lines`.

Every posted sales invoice, purchase invoice, and bank transaction creates a `journal_entries` row plus balanced `journal_lines`. Reports and VAT calculations must read journal lines instead of invoice totals. The posting services live in:

```bash
app/Services/Accounting
app/Services/Inventory
```

Default UK nominal ledgers are seeded, including Sales `4000`, Purchases `5000`, Stock `1000`, Bank `1200`, Trade Debtors `1100`, Trade Creditors `2100`, VAT Control `2200`, VAT Output `2201`, VAT Input `2202`, and Retained Earnings `3000`.

Run database setup with:

```bash
php artisan migrate --seed
```

For a fresh local rebuild:

```bash
php artisan migrate:fresh --seed
```

## Deployment Notes

Run these commands during deployment after dependencies are installed:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize
```

Serve the application from `public/`, configure a real mailer for password resets, and run a process supervisor for `php artisan queue:work`.
