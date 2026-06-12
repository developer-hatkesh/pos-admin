# Installation

## Requirements

- PHP 8.2 or newer
- Composer 2
- MySQL 8 compatible server
- Node.js only when frontend assets are changed

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000/admin`.

## Environment

Required database variables:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_admin
DB_USERNAME=root
DB_PASSWORD=
```

Production must use:

```dotenv
APP_ENV=production
APP_DEBUG=false
DEBUGBAR_ENABLED=false
TELESCOPE_ENABLED=false
```
