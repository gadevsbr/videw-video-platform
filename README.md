# VIDEW

Source-available video platform starter built with `PHP`, `MySQL`, `CSS`, `JavaScript`, and `gUI`.

`VIDEW` is designed to be flexible: you can run it as a general video platform or enable optional access controls such as an `age gate` when your project requires it.

Commercial use is not included by default. If you want to use `VIDEW` in a paid product, customer project, hosted service, monetized site, or other commercial environment, you need prior authorization from the project maintainer. Contact: `gadevs2020@gmail.com`. See [LICENSE](LICENSE).

`VIDEW` includes:

- free vs premium catalog gating
- Stripe subscriptions with Hosted Checkout and Billing Portal
- local uploads, Wasabi uploads, and external embeds
- account registration, password reset, and TOTP MFA
- optional age gate, legal pages, cookie notice, and editable footer content
- a multi-screen admin suite for publishing, moderation, billing, and settings
- a lightweight web installer for shared hosting and small VPS deployments

## Frontend Runtime

The frontend was built with `gUI`.

- GitHub: `https://github.com/gadevsbr/gUI`
- npm: `https://www.npmjs.com/package/@bragamateus/gui`

The repository keeps a vendored runtime copy in `assets/vendor/gui` so production servers do not need `node_modules`.
If you update the dependency locally, run `npm run sync:gui` to refresh the committed runtime files.

## Repository Layout

- `index.php`: public home and catalog
- `watch.php`: video detail and player page
- `premium.php`: public plans page
- `login.php`, `register.php`, `account.php`: account flow
- `forgot-password.php`, `reset-password.php`, `mfa-challenge.php`: security flows
- `admin.php`: admin suite
- `rules.php`, `terms.php`, `privacy.php`, `cookies.php`: public legal pages
- `install.php`: web installer
- `api/videos.php`, `api/session.php`: public JSON endpoints
- `webhooks/stripe.php`: Stripe webhook endpoint
- `assets/js/app.js`: public frontend app
- `assets/vendor/gui`: committed `gUI` runtime for hosting without npm
- `db/schema.sql`: clean install schema
- `db/seed-demo.sql`: optional demo catalog seed
- `scripts/sync-gui-runtime.mjs`: refresh the vendored `gUI` runtime from npm

## Requirements

- PHP `8.1+`
- MySQL `5.7+` or MariaDB `10.4+`
- PHP extensions:
  - `pdo_mysql`
  - `curl`
  - `mbstring`
  - `json`
  - `fileinfo`
- Apache, Nginx, IIS, or any PHP-compatible shared hosting / VPS

## Quick Start

### Option 1: Web Installer

1. Upload the project to your server.
2. Open `https://your-domain.example/install.php`.
3. Fill in the site URL and MySQL credentials.
4. Choose whether to import the optional demo catalog.
5. Finish the install.
6. Register the first account on `register.php` to become the initial admin.

The installer writes `.env`, imports `db/schema.sql`, optionally imports `db/seed-demo.sql`, prepares `storage/`, and locks itself after success.

### Option 2: Manual Install

1. Copy `.env.example` to `.env`.
2. Fill in environment-specific values.
3. Create the database configured in `VIDEW_DB_DATABASE`.
4. Import `db/schema.sql`.
5. Optionally import `db/seed-demo.sql`.
6. Open `register.php` and create the first account. That account becomes `admin`.

## Environment

All secrets and deployment-specific values belong in `.env`.

Examples:

- database credentials
- base URL and support email
- Stripe keys and webhook secret
- Wasabi credentials
- footer and legal page content
- cookie notice text

The admin suite can write app, storage, billing, and legal settings back into `.env`.

## Admin Suite

The admin panel is organized by job:

- `overview`
- `storage`
- `billing`
- `publish`
- `library`
- `moderation`
- `users`
- `settings`
- `copy`
- `legal`
- `activity`

It includes:

- video create/edit/delete and bulk actions
- moderation workflow
- user role and suspension management
- Stripe and Wasabi configuration
- public text editing for homepage, browse, plans, support, watch, account, auth, and age-gate copy
- audit log filtering and CSV export
- footer, terms, privacy, cookies, and notice editing

## Billing

`VIDEW` uses Stripe Hosted Checkout for new Premium subscriptions and Stripe Billing Portal for self-service account management.

Setup flow:

1. Open `Admin > Billing`.
2. Save the Stripe secret key, publishable key, webhook signing secret, and recurring `price_...` ID.
3. Create a webhook for `https://your-domain.example/webhooks/stripe.php`.
4. Subscribe at minimum to:
   - `checkout.session.completed`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`

## Storage

The project supports:

- local uploads under `storage/uploads`
- Wasabi object storage
- external direct video URLs
- external embed URLs for supported providers

The active storage driver and Wasabi credentials can be managed from `Admin > Storage`.

## Security Notes

- `.env` and `storage/runtime` are ignored by Git and should stay private.
- The repository includes an Apache `.htaccess` for blocking sensitive files. If you deploy with `nginx` or `IIS`, add equivalent rules at the server level.
- The installer locks itself after a successful run. Remove the lock file manually only if you intentionally need to reinstall.
- Premium local media is routed through `media.php` and access-checked server-side.
- Password reset links are not shown publicly unless `VIDEW_DEBUG_EXPOSE_RESET_LINKS=1`.

## Frontend Development

You only need `npm` for local dependency maintenance.

Commands:

```bash
npm install
npm run check:js
npm run sync:gui
```

Production hosting does not need `npm install` as long as `assets/vendor/gui` is present.

## Licensing

- License: [VIDEW Source Available Non-Commercial License 1.0](LICENSE)
- Commercial licensing contact: `gadevs2020@gmail.com`
- Contribution guide: [CONTRIBUTING.md](CONTRIBUTING.md)
- Security reporting: [SECURITY.md](SECURITY.md)

Because commercial use is restricted, `VIDEW` is source-available rather than OSI open source.

The repository is prepared to stay public:

- `.env`, uploaded media, runtime cache, archives, and `node_modules` stay ignored
- `.env.example` contains placeholder values only
- the runtime asset required by the frontend is committed in `assets/vendor/gui`

## Validation

Typical checks after changes:

```bash
php -l install.php
php -l admin.php
php -l index.php
npm run check:js
```
