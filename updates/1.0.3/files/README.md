# VIDEW

Source-available PHP video platform starter with subscriptions, creator workflows, and an admin suite.

VIDEW is built for teams that want to launch a branded video product without starting from a framework-heavy codebase. It combines a public catalog, gated Premium access, creator applications, a creator studio, Stripe billing, flexible media storage, and a multi-screen admin area in a stack that still works on shared hosting and small VPS deployments.

Commercial use is **not included by default**. This repository is distributed under a source-available non-commercial license. If you want to use VIDEW in a paid product, a client project, a hosted service, or any revenue-generating environment, contact `gadevs2020@gmail.com` for authorization. See [LICENSE](LICENSE).

## Why Videw

- Launch faster with a production-oriented PHP/MySQL starter instead of wiring subscriptions, uploads, and account flows from scratch.
- Run on lightweight hosting: shared hosting, cPanel-style environments, and small VPS setups are all supported.
- Mix free and Premium content in one product with clear gating rules.
- Support creators with public channel pages, creator applications, a studio, analytics, and profile assets.
- Manage content, billing, legal text, footer links, and public copy from the admin area.
- Keep the frontend lightweight with `gUI` while still shipping a modern catalog and player experience.

## What This Project Is

VIDEW is a source-available starter for:

- subscription video platforms
- membership-based content libraries
- creator-led video sites
- private catalog products with free and paid access tiers
- internal or client-evaluated product prototypes that need real account, billing, and admin workflows

It is intentionally flexible. You can run it as a general video platform, or enable optional controls such as an age gate when your use case requires them.

## Who This Is For

- developers who want a plain PHP codebase instead of a framework-heavy stack
- founders validating a niche video membership product
- agencies building an internal evaluation, proof of concept, or non-commercial prototype
- teams that need admin workflows, billing, and media storage options already wired together

## When This Is Not a Fit

VIDEW is probably not the right choice if you need:

- a fully managed SaaS platform with zero server administration
- enterprise-grade transcoding, DRM, or multi-region media infrastructure out of the box
- a permissive OSI-approved open-source license
- a React / Laravel / Symfony codebase
- commercial deployment rights without separate authorization

## Key Features

### Public product

- public homepage, browse flow, player page, support page, legal pages, and account pages
- free vs Premium video access rules
- optional age gate and cookie notice
- configurable footer, legal page copy, and public text from the admin panel

### Accounts and security

- email/password registration and sign-in
- password reset flow
- TOTP-based MFA with backup codes
- session hardening, CSRF protection, and rate limiting on sensitive flows

### Billing

- Stripe Hosted Checkout for new Premium subscriptions
- Stripe Billing Portal for self-service plan management
- webhook-based subscription sync back into the local account model

### Media and storage

- local file uploads
- Wasabi object storage
- external direct video URLs
- external embed support for supported providers
- private Wasabi playback support with signed URLs/proxying where applicable

### Admin suite

- content publishing and editing
- moderation queue
- user management
- creator request review
- billing and storage configuration
- legal/footer/site settings
- public copy editing
- audit activity log

### Creator workflows

- "Become creator" request flow
- creator approval path in admin
- creator studio with publish, manage, analytics, and profile screens
- public creator channel pages with avatar, banner, bio, and channel slug


## Use Cases

- premium content library with monthly subscriptions
- creator platform with staff moderation before publish
- gated training or private media portal
- branded prototype for a future subscription product
- non-commercial internal product exploration that still needs real billing and access logic

## Tech Stack

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.4+
- JavaScript + CSS
- [`gUI`](https://github.com/gadevsbr/gUI) for frontend runtime
- Stripe for subscriptions
- Wasabi or local disk for storage

## Project Structure

- `index.php`: public homepage
- `browse.php`: browse and filtering experience
- `watch.php`: player page
- `premium.php`: plan and upgrade page
- `account.php`: account, security, membership, creator entry
- `studio.php`: creator studio
- `channel.php`: public creator profile page
- `admin.php`: admin suite
- `install.php`: web installer
- `webhooks/stripe.php`: Stripe webhook endpoint
- `media.php`: gated media delivery

## Quick Start

### Option 1: Web installer

1. Upload the repository to your server.
2. Open `https://your-domain.example/install.php`.
3. Enter the app URL and database credentials.
4. Choose whether to import the optional demo catalog.
5. Finish the installer.
6. Register the first account on `register.php`. That account becomes the initial admin.
7. Delete `install.php` from the server after setup.

The installer:

- writes `.env`
- imports `db/schema.sql`
- can optionally import `db/seed-demo.sql` when that file is present
- prepares `storage/`
- locks itself after success

Database note:

- `db/schema.sql` always represents the full schema for the current project version on a fresh install
- versioned upgrade SQL files are only for existing installations that are already running an older release
- when upgrade scripts are needed, they live under `updates/<version>/sql/`

### Option 2: Manual install

1. Copy `.env.example` to `.env`.
2. Fill in the environment values for your server.
3. Create the database defined in `VIDEW_DB_DATABASE`.
4. Import `db/schema.sql`.
5. Optionally import `db/seed-demo.sql` if you include a demo seed file in your deployment.
6. Open `register.php` and create the first account.

For existing installs upgrading from an older release:

- do not re-import `db/schema.sql` over a live installation
- apply only the relevant upgrade SQL files for your target release
- upgrade scripts, when present, are organized under `updates/<version>/sql/`

## Installation Options

### Shared hosting

- use the web installer if possible
- keep the committed `assets/vendor/gui` runtime so production does not need `node_modules`
- make sure `storage/` is writable
- delete `install.php` after setup

### VPS / self-managed server

- use either the installer or manual install
- configure your web server to block access to sensitive files and folders
- set up HTTPS before enabling production cookies and webhooks
- use `deploy/nginx.conf.example` or `deploy/web.config` if you are not on Apache

More deployment guidance is in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Versioning and upgrade workflow notes live in [docs/RELEASE_PROCESS.md](docs/RELEASE_PROCESS.md). Backup and restore guidance lives in [docs/BACKUP_RESTORE.md](docs/BACKUP_RESTORE.md).

## Environment Configuration

All secrets and environment-specific values belong in `.env`.

Important groups:

- app identity and base URL
- database credentials
- session and security settings
- Stripe keys and webhook secret
- Wasabi credentials
- footer, legal, and public copy values

Examples are included in `.env.example`.

Important production variables:

- `VIDEW_BASE_URL`
- `VIDEW_TRUSTED_HOSTS`
- `VIDEW_SESSION_SECURE_COOKIE=1`
- `VIDEW_FORCE_HTTPS=1`
- `VIDEW_SECURITY_HSTS_ENABLED=1`

Enable `VIDEW_TRUST_PROXY_HEADERS=1` only if you control the reverse proxy in front of PHP.

## Admin Suite Overview

The admin area is organized by job, not by generic settings buckets.

Main screens:

- `overview`
- `storage`
- `billing`
- `publish`
- `library`
- `moderation`
- `creator_requests`
- `users`
- `settings`
- `copy`
- `legal`
- `activity`

What the admin suite covers:

- video publishing and editing
- moderation and review
- user roles and suspensions
- creator approvals
- Stripe and Wasabi configuration
- footer and legal page management
- public copy editing
- audit activity visibility

## Creator Experience

VIDEW includes a complete creator path:

1. a regular user account applies through "Become creator"
2. admin reviews the request
3. approved users get creator role access
4. creator manages uploads, analytics, and public profile from the studio

The studio currently includes:

- overview
- publish
- manage videos
- analytics
- profile

## Billing Overview

Billing is implemented with Stripe and focuses on a simple recurring Premium model.

Current flow:

- Hosted Checkout for new subscriptions
- Billing Portal for plan management
- webhook sync for subscription state
- free vs Premium gating enforced in the app

Required webhook endpoint:

- `https://your-domain.example/webhooks/stripe.php`

Minimum useful events:

- `checkout.session.completed`
- `invoice.paid`
- `invoice.payment_failed`
- `customer.subscription.updated`
- `customer.subscription.deleted`

## Storage Overview

VIDEW supports multiple media sources:

- local uploads under `storage/uploads`
- Wasabi object storage
- external direct video URLs
- external embed URLs for supported providers

This makes it possible to start simple with local uploads and move to object storage later without rewriting the public product.

## Security Notes

- delete `install.php` after installation
- keep `.env`, uploaded media, and runtime files private
- use the provided Apache, Nginx, or IIS blocking rules for sensitive files
- review `VIDEW_PUBLIC_HEAD_SCRIPTS` carefully before pasting third-party snippets
- password reset links are only publicly exposed when `VIDEW_DEBUG_EXPOSE_RESET_LINKS=1`
- Premium local media is access-checked server-side through `media.php`

See [SECURITY.md](SECURITY.md) for the reporting process and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for production reminders.

## Frontend Runtime

The frontend runtime is built with `gUI`.

- GitHub: `https://github.com/gadevsbr/gUI`
- npm: `https://www.npmjs.com/package/@bragamateus/gui`

The repository keeps a vendored runtime copy in `assets/vendor/gui` so production servers do not need `node_modules`.

If you update the dependency locally, run:

```bash
npm install
npm run sync:gui
```

## Development Checks

Typical checks after changes:

```bash
php -l install.php
php -l admin.php
php -l index.php
npm run check:js
```

## License and Commercial Usage

VIDEW is **source-available**, not OSI-approved open source.

License:

- [VIDEW Source Available Non-Commercial License 1.0](LICENSE)

Commercial use requires prior authorization. That includes paid products, hosted services, monetized sites, client work, agency delivery, or other revenue-generating usage.

Commercial licensing contact:

- `gadevs2020@gmail.com`

## Contributing

Contributions are welcome within the limits of the project license.

- read [CONTRIBUTING.md](CONTRIBUTING.md)
- report security issues privately through [SECURITY.md](SECURITY.md)
- open issues for bugs, docs improvements, and focused feature discussions

## Roadmap

See [ROADMAP.md](ROADMAP.md) for near-term priorities, mid-term work, and long-term direction.

## Support and Contact

- licensing and commercial inquiries: `gadevs2020@gmail.com`
- security reports: `gadevs2020@gmail.com`
- project feedback: GitHub issues and discussions

## Feedback, Issues, and Stars

If VIDEW is useful to you:

- star the repository
- open an issue when you hit friction or find a bug
- share feedback on setup, hosting compatibility, billing, or creator workflows

That feedback is especially useful while the project is becoming a more polished public starter.
