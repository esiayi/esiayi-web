# ESIAYI

The web app and mobile-app API behind [esiayi.com](https://esiayi.com): a marketplace where customers book home services (cleaning, plumbing, electrical...) from providers, who manage their services, availability, bookings and payouts. Admins run the site from `/admin`.

Laravel 13 (PHP 8.3+), MySQL 8, Blade views with jQuery; JWT for the mobile-app API.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed      # tables, required settings rows and a first admin (see ADMIN_EMAIL / ADMIN_PASSWORD)
php artisan serve
```

Admin panel: `/admin/login`. Payment gateways, Pusher (live chat), email, analytics and social logins are all switched off until you configure them there.

Front-end assets are committed (`public/js/app.js`, `public/frontend`). To rebuild `app.js` after changing `resources/js`:

```sh
npm ci --legacy-peer-deps && npx mix --production   # Node 16
```

## Database schema

The original migrations came from the 2021 template and no longer matched the code; production was built from a SQL dump. `2026_11_18_000000_add_tables_and_columns_the_code_uses` adds what the code needs (reconstructed from the code, guarded so it never changes an existing column), which makes a fresh install work. It is a stopgap: once someone runs `php artisan schema:dump` against production, commit `database/schema/mysql-schema.sql` and new installs will load the real schema.

## Deploying

- The server needs **PHP 8.3 or newer** (Hostinger: hPanel > Advanced > PHP Configuration).
- Admin "language" edits are written to `lang/en/*.php` **on the server**, so the server copy can differ from git. Diff before deploying so live wording is not overwritten.
- After deploying: `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan view:clear`, `php artisan route:cache`.
- `php artisan esiayi:content-check` lists live data that needs attention (test accounts, test prices, outdated settings). It only reads.
