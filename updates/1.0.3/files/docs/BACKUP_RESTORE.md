# Backup And Restore

This project now supports multiple export paths from the admin area, but restore should still be treated as an operator workflow, not as a blind one-click import.

## What To Export

Use the admin workspace to export:

- settings backup JSON
- full catalog JSON or CSV
- full users JSON or CSV
- filtered catalog and filtered users exports from their own screens
- activity CSV when you need audit history

## What A Full Backup Still Requires

Admin exports do not replace a full instance backup.

For a full recovery-ready backup, keep:

- the MySQL database dump
- the `storage/` directory
- the current `.env`
- any versioned SQL files under `updates/<version>/sql/` relevant to your install history
- the admin exports when you want human-readable snapshots for review or migration work

## Recommended Backup Flow

1. Export the admin backup JSON from `Settings`.
2. Export catalog and users from the admin area when you want portable review copies.
3. Create a database dump.
4. Back up the `storage/` directory.
5. Keep the backup labeled with the app version and date.

## Restore Guidance

For a clean rebuild:

1. Restore the codebase for the target version.
2. Restore `.env`.
3. Restore the database dump.
4. Restore `storage/`.
5. Check admin version indicators to confirm code version and DB version are aligned.

For selective migration or review:

- use the JSON and CSV exports to inspect settings, users, and catalog data
- do not overwrite a live database by re-importing `db/schema.sql`
- use versioned upgrade SQL from `updates/<version>/sql/` only for schema changes on existing installs

## Important Limits

- User exports do not include password hashes, MFA secrets, or backup codes.
- Settings backup JSON can include sensitive secrets and should be stored securely.
- CSV exports are best for review, migration prep, and audit-friendly sharing, not full-fidelity restore.
