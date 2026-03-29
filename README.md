# VIDEW 18+

Adult-only video platform starter built with:

- `PHP`
- `MySQL`
- `CSS`
- `JavaScript`
- `gUI`

It includes local uploads, Wasabi storage, external embeds, age gating, account flow, 2FA, password reset, public legal pages, a cookie notice, and a multi-screen admin panel.
It also includes Stripe-based `Free` vs `Premium` access with Hosted Checkout, Billing Portal, and webhook-driven account sync.

## Frontend Runtime

This project frontend was built with `gUI`.

- GitHub: `https://github.com/gadevsbr/gUI`
- npm: `https://www.npmjs.com/package/@bragamateus/gui`
- The frontend uses the official `@bragamateus/gui` npm package

- Install dependencies with `npm install`
- The PHP templates inject an `importmap` that maps `@bragamateus/gui` to the installed package inside `node_modules`
- `assets/js/app.js` imports the framework from `@bragamateus/gui`

## Stack

- `index.php`: home page and catalog
- `watch.php`: video detail and player
- `rules.php`, `terms.php`, `privacy.php`, `cookies.php`: public legal pages
- `premium.php`: public pricing and upgrade page
- `login.php`, `register.php`, `account.php`: account flow
- `forgot-password.php`, `reset-password.php`, `mfa-challenge.php`: security flows
- `start-premium-checkout.php`, `manage-billing.php`: Stripe billing routes
- `webhooks/stripe.php`: Stripe webhook endpoint
- `admin.php`: admin suite
- `api/videos.php`: catalog JSON endpoint
- `api/session.php`: age gate session endpoint
- `assets/js/app.js`: frontend app powered by `@bragamateus/gui`
- `package.json`: frontend dependency manifest
- `db/schema.sql`: base schema
- `db/seed.sql`: example data
- `db/upgrade-20260328-embed-wasabi.sql`: embed + Wasabi upgrade
- `db/upgrade-20260328-admin-suite.sql`: admin suite upgrade
- `db/upgrade-20260328-backlog-features.sql`: backlog features upgrade
- `db/upgrade-20260329-stripe-premium.sql`: Stripe premium + account tier upgrade

## Admin Suite

The admin panel is split into one screen per job:

- `overview`: shortcuts and high-level stats
- `storage`: local vs Wasabi, signed URLs, multipart thresholds
- `billing`: save Stripe keys, webhook secret, premium price ID, and public plan copy into `.env`
- `publish`: create videos from upload or external URL
- `library`: search, filter, paginate, bulk edit, feature, and delete videos
- `moderation`: paginate, bulk review, and move videos between `draft`, `approved`, and `flagged`
- `users`: manage roles, account status, MFA visibility, and pagination
- `settings`: save app branding and public settings into `.env`
- `legal`: edit footer links, rules, terms, privacy, cookie page copy, and the cookie notice
- `activity`: filter, paginate, and export the audit trail

Security and operational basics included:

- CSRF protection on admin forms
- audit log persistence
- last active admin protection
- suspended-user login block
- file cleanup on delete/replacement
- password reset token flow
- optional TOTP-based MFA with backup codes

## Environment

1. Copy `.env.example` to `.env`.
2. Fill in all environment-specific values.
3. Optionally create `.env.local` for machine-specific overrides.
4. Run `npm install`.
5. Do not commit `.env` or `.env.local`.

Sensitive or deployment-specific values should stay in `.env`, including:

- app name and branding
- base URL and support email
- footer copy and footer links
- rules, terms, privacy, and cookie page content
- cookie notice text and link target
- exit URL and timezone
- session settings
- MySQL credentials
- local storage paths
- all `VIDEW_WASABI_*` values
- all `VIDEW_STRIPE_*` values

The admin panel can write storage settings and general app settings back into `.env`.
The legal screen can also write footer links, public policy pages, and cookie notice content back into `.env`.
The billing screen also writes Stripe credentials, the premium price ID, and pricing copy back into `.env`.

## Repository Notes

This repository is meant to stay public-friendly:

- source code, SQL files, `README.md`, `.env.example`, and npm manifests stay versioned
- live secrets, uploaded media, and `node_modules` stay out of version control through `.gitignore`

After cloning the project:

1. Copy `.env.example` to `.env`.
2. Fill in your environment-specific values.
3. Run `npm install`.
4. Import `db/schema.sql`.
5. Run any required upgrade SQL files for older installs.
6. Configure Wasabi and Stripe from the admin panel or directly in `.env`.

## Database Setup

1. Create a MySQL database matching `VIDEW_DB_DATABASE`.
2. Select that database in phpMyAdmin.
3. Import `db/schema.sql`.


## Stripe Setup

1. Open `Admin > Billing`.
2. Paste your Stripe secret key, publishable key, webhook signing secret, and recurring `price_...` ID.
3. Set the public plan name, copy, and price label.
4. Save the screen so the values are written into `.env`.
5. In Stripe Dashboard / Workbench, create a webhook pointing to `https://your-domain.example/webhooks/stripe.php`.
6. Subscribe at minimum to:
   - `checkout.session.completed`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`

The app uses Stripe Hosted Checkout for upgrades and Stripe Billing Portal for subscription self-service.

## Current Capabilities

- age gate with session lock
- account registration and login
- first registered user becomes `admin`
- local uploads or Wasabi uploads
- Wasabi private bucket delivery via signed URLs
- multipart upload support for large Wasabi uploads
- direct media URLs: `.mp4`, `.webm`, `.m3u8`
- embed conversion from supported providers
- clean listing posters and richer detail posters
- free vs premium playback gating
- Stripe Hosted Checkout upgrades
- Stripe Billing Portal management
- Stripe webhook sync for account tier updates
- moderation status workflow
- user role and suspension management
- dedicated rules, terms, privacy, and cookies pages
- editable global footer with useful and legal links
- editable cookie notice banner on public pages
- bulk actions and pagination in admin lists
- audit activity filtering and CSV export
- password reset by one-time token
- authenticator-app MFA with backup codes

## Notes

- If MySQL is offline, the public home page falls back to demo data.
- For very large uploads, also raise `upload_max_filesize` and `post_max_size` in your PHP runtime or hosting panel.
- Wasabi playback and upload should be tested with real bucket credentials before production use.
- For Premium assets on Wasabi, the app now routes playback through `media.php` so Premium videos are not exposed as plain public object URLs.
- In this starter, password reset links are shown on-screen because outbound email is not configured.

## Validation

The current project has been checked with:

- `php -l` on the touched PHP files
- smoke rendering for login, register, forgot password, reset password, MFA challenge, and account
- smoke rendering for the public `premium.php` page and the Stripe routes
- smoke rendering for all admin screens:
  - `overview`
  - `storage`
  - `billing`
  - `publish`
  - `library`
  - `moderation`
  - `users`
  - `settings`
  - `legal`
  - `activity`
