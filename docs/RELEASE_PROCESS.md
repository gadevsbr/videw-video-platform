# Release Process

This project uses a simple versioning workflow so code version, database version, and upgrade files do not drift apart.

## Core Rules

- `db/schema.sql` always represents the full clean schema for the current version.
- `db/schema.sql` is for fresh installs only.
- Existing installs must use upgrade SQL files only.
- Upgrade SQL files live under `updates/<version>/sql/`.
- Every release must also maintain a version package under `updates/<version>/`.
- The version package should keep the created and modified files for that release under `updates/<version>/files/`.
- If a release removes or relocates files, record that in a manifest inside `updates/<version>/`.
- Every shipped version must update the documented release notes in `CHANGELOG.md`.
- Do not publish anything to GitHub until explicitly requested.

## Version Sources

Keep these aligned for every release:

- `CHANGELOG.md`
- `package.json`
- `.env.example` via `VIDEW_APP_VERSION`
- any version-specific SQL under `updates/<version>/sql/`
- the packaged release snapshot under `updates/<version>/`

## Database Versioning

The app tracks applied schema versions in the `schema_migrations` table.

For fresh installs:

- import `db/schema.sql`
- the schema seeds the baseline migration row for the current version

For upgrades:

- back up the database first
- apply the SQL files inside each required `updates/<version>/sql/` folder in order
- do not re-import `db/schema.sql` over an existing installation

## Release Checklist

1. Choose the target version using semantic versioning.
2. Update `CHANGELOG.md` with the final release section and date.
3. Update `package.json`.
4. Update `.env.example` `VIDEW_APP_VERSION`.
5. If the database changed:
   - update `db/schema.sql` to the new full current schema
   - create or update the corresponding files under `updates/<version>/sql/`
6. Refresh `updates/<version>/` so it contains the release package files plus a manifest.
7. Verify the admin version indicators still show the expected code version and DB version behavior.
8. Verify a fresh install works with `db/schema.sql`.
9. Verify an upgrade path works with the incremental SQL files.
10. Keep all changes local until publishing is explicitly requested.

## Release Package Layout

Use this structure for each shipped version:

- `updates/<version>/MANIFEST.md`
- `updates/<version>/sql/<upgrade files>`
- `updates/<version>/files/<relative project paths>`

The manifest should list:

- modified files
- newly created files
- deleted or relocated files

## SQL File Naming

Use deterministic ordering inside version folders, for example:

- `updates/1.0.3/sql/001-schema-version-tracking.sql`
- `updates/1.0.3/sql/002-some-other-upgrade.sql`

This keeps multi-file upgrades predictable and easier to review.
