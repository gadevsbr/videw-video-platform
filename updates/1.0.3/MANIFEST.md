# Update Package 1.0.3

Date: `2026-04-09`

This folder packages the project files changed for version `1.0.3`.

## Layout

- `sql/`: official upgrade SQL files for existing installs moving into `1.0.3`
- `files/`: current copies of the created and modified project files for this release
- `MANIFEST.md`: release-package index for `1.0.3`

## Modified Files

- `.env.example`
- `.gitignore`
- `CHANGELOG.md`
- `README.md`
- `admin.php`
- `config/app.php`
- `db/schema.sql`
- `docs/DEPLOYMENT.md`
- `install.php`
- `package.json`
- `src/Repositories/AuditLogRepository.php`
- `src/Repositories/UserRepository.php`
- `src/Repositories/VideoAnalyticsRepository.php`
- `src/Repositories/VideoRepository.php`
- `src/Services/AdminVideoService.php`
- `src/Services/BillingService.php`
- `src/Services/StripeApiClient.php`
- `src/Support/helpers.php`
- `webhooks/stripe.php`

## Created Files

- `updates/1.0.3/sql/001-schema-version-tracking.sql`
- `updates/1.0.3/sql/002-video-moderation-reasons.sql`
- `updates/1.0.3/sql/upgrade-20260402-ad-slots.sql`
- `updates/1.0.3/sql/upgrade-20260403-ad-preroll-vast.sql`
- `docs/BACKUP_RESTORE.md`
- `docs/RELEASE_PROCESS.md`
- `docs/ROADMAP_STATUS.md`
- `src/Services/AdminBackupService.php`
- `src/Services/AdminExportService.php`
- `src/Services/DatabaseVersionService.php`
- `src/Services/GitHubReleaseService.php`
- `src/Services/StripeWebhookEventStore.php`
- `storage/cache/.gitkeep`

## Deleted Or Relocated Files

- `db/1.0.3/001-schema-version-tracking.sql`
  Relocated into `updates/1.0.3/sql/001-schema-version-tracking.sql`
- `db/1.0.3/002-video-moderation-reasons.sql`
  Relocated into `updates/1.0.3/sql/002-video-moderation-reasons.sql`
- `db/1.0.3/upgrade-20260402-ad-slots.sql`
  Relocated into `updates/1.0.3/sql/upgrade-20260402-ad-slots.sql`
- `db/1.0.3/upgrade-20260403-ad-preroll-vast.sql`
  Relocated into `updates/1.0.3/sql/upgrade-20260403-ad-preroll-vast.sql`
- `db/upgrade-20260402-ad-slots.sql`
  Relocated into `updates/1.0.3/sql/upgrade-20260402-ad-slots.sql`
- `db/upgrade-20260403-ad-preroll-vast.sql`
  Relocated into `updates/1.0.3/sql/upgrade-20260403-ad-preroll-vast.sql`

## Notes

- `docs/ROADMAP_STATUS.md` is ignored in Git, but its current local copy is included in this package because it was part of the `1.0.3` work.
- This package reflects project files only. The local Codex skill update for versioning was kept outside the repository and is not mirrored here.
