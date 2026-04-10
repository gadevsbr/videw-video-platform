# Deployment Guide

This guide covers the practical deployment details for VIDEW on shared hosting, VPS environments, and reverse-proxy setups.

## Minimum Requirements

### PHP

- PHP `8.1+`
- required extensions:
  - `pdo_mysql`
  - `curl`
  - `mbstring`
  - `json`
  - `fileinfo`

### Database

- MySQL `5.7+`
- or MariaDB `10.4+`

## Installation Paths

You can deploy VIDEW in two ways:

1. use the web installer at `install.php`
2. perform a manual install with `.env` + `db/schema.sql`

The installer is useful for lightweight hosting, but it should be deleted after setup.

## Database Versioning

- `db/schema.sql` is always the clean full schema for the current project version.
- Use `db/schema.sql` only for fresh installs.
- If you are upgrading an existing installation, use only the upgrade SQL files for the versions you are moving through.
- Upgrade SQL files, when present, are organized under `updates/<version>/sql/`.

## Shared Hosting Notes

VIDEW is designed to stay compatible with simple PHP hosting.

Recommended checklist:

- upload the repository files
- make sure `storage/` is writable
- run the installer or import `db/schema.sql` manually
- set the correct values in `.env`
- delete `install.php` after setup
- confirm that `.env`, `db/`, `src/`, and other sensitive paths are blocked by the web server

Notes:

- the committed `assets/vendor/gui` runtime means production does not need `npm install`
- `.user.ini` can help with upload limits in some shared-hosting environments
- if the host ignores Apache `.htaccess`, you must apply equivalent rules yourself

## VPS Notes

On a VPS, the recommended flow is:

1. provision PHP and MySQL/MariaDB
2. configure HTTPS first
3. deploy the repository
4. create `.env`
5. import `db/schema.sql`
6. configure permissions for `storage/`
7. configure Stripe webhook delivery
8. delete `install.php` if it was used

If you are upgrading an existing VPS install instead of deploying fresh:

1. back up the database first
2. deploy the new application files
3. apply the relevant SQL upgrade files from `updates/<version>/sql/`
4. do not re-import `db/schema.sql` into the existing database

Use the deployment as an application, not as a writable document dump:

- keep secrets out of the web root when possible
- keep uploads writable, but keep source/config directories protected
- run PHP with errors logged, not exposed publicly

## Reverse Proxy and Trusted Host Notes

VIDEW now relies on explicit host configuration for safer absolute URL generation.

Important variables:

- `VIDEW_BASE_URL`
- `VIDEW_TRUSTED_HOSTS`
- `VIDEW_TRUST_PROXY_HEADERS`
- `VIDEW_FORCE_HTTPS`

Recommendations:

- always set `VIDEW_BASE_URL` to the real production URL
- set `VIDEW_TRUSTED_HOSTS` to your expected hostnames
- keep `VIDEW_TRUST_PROXY_HEADERS=0` unless you control the reverse proxy in front of PHP
- set `VIDEW_FORCE_HTTPS=1` in production once HTTPS is ready

Example:

```env
VIDEW_BASE_URL="https://video.example.com"
VIDEW_TRUSTED_HOSTS="video.example.com,www.video.example.com"
VIDEW_TRUST_PROXY_HEADERS="0"
VIDEW_FORCE_HTTPS="1"
```

## Web Server Protection

### Apache

The repository includes `.htaccess` rules for sensitive files and directories.

### Nginx

Use [deploy/nginx.conf.example](../deploy/nginx.conf.example) as a starting point and adapt it to your server block.

### IIS

Use [deploy/web.config](../deploy/web.config) as a starting point and adapt it to your hosting setup.

## Production Hardening Reminders

- delete `install.php` after installation
- keep `.env` private
- keep `storage/runtime` private
- keep `VIDEW_SESSION_SECURE_COOKIE=1`
- enable HSTS only after HTTPS is fully working
- review the default CSP before adding third-party scripts
- do not expose PHP warnings/errors publicly
- log Stripe webhook failures server-side

## Stripe Deployment Notes

VIDEW uses:

- Stripe Hosted Checkout
- Stripe Billing Portal
- webhook-based subscription sync

Before going live:

1. save the Stripe keys in `.env` or through the admin panel
2. set the Premium recurring `price_...` ID
3. configure the webhook endpoint:
   - `https://your-domain.example/webhooks/stripe.php`
4. subscribe at minimum to:
   - `checkout.session.completed`
   - `invoice.paid`
   - `invoice.payment_failed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`

## Wasabi and Storage Notes

VIDEW supports:

- local storage
- Wasabi object storage
- external direct URLs
- supported external embeds

For Wasabi:

- save endpoint, region, bucket, access key, and secret key
- decide whether the bucket is public or private
- review signed URL settings for private delivery
- verify upload sizes and PHP limits before large media imports

For local storage:

- confirm `storage/uploads` is writable
- confirm public URL mapping matches `VIDEW_LOCAL_STORAGE_BASE_URL`

## Upload Limits

Large uploads may require:

- `upload_max_filesize`
- `post_max_size`
- timeout adjustments at the PHP and web-server level

The included `.user.ini` is only a baseline. Your hosting provider may still require account-level configuration changes.

## Final Pre-Launch Checklist

- `.env` configured
- `VIDEW_BASE_URL` correct
- trusted hosts configured
- HTTPS working
- sensitive files blocked
- Stripe webhook reachable
- storage writable
- admin account created
- `install.php` removed
