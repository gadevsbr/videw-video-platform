# Changelog

All notable changes to this project will be documented in this file.

## [1.0.1] - 2026-03-31

### Added
- Added configurable production security settings for trusted hosts, proxy header trust, forced HTTPS, HSTS, and CSP in `.env.example` and `config/app.php`.
- Added deploy examples for `nginx` and `IIS` in `deploy/nginx.conf.example` and `deploy/web.config` to help block sensitive files outside Apache environments.
- Added a CSP nonce flow for the public and admin bootstrap scripts so inline page bootstrapping can stay compatible with a stricter `Content-Security-Policy`.
- Added CSRF protection to `api/session.php` and exposed the corresponding frontend token through the page bootstrap payload.

### Changed
- Stopped deriving public absolute URLs from arbitrary request hosts and now prefer the configured base URL plus trusted-host validation.
- Updated the bootstrap layer to emit `Content-Security-Policy`, optional `Strict-Transport-Security`, and safer HTTPS-aware cookie handling.
- Updated the installer to seed safer production defaults for trusted hosts, HTTPS, HSTS, and CSP when the detected base URL uses `https`.
- Updated the admin wording around public head scripts to warn that arbitrary snippets run with full access to the public frontend.
- Updated the README security guidance to cover host validation, reverse proxies, non-Apache blocking rules, CSP, and deleting `install.php` after installation.

### Fixed
- Fixed public error leakage in `webhooks/stripe.php` by replacing raw exception messages with generic webhook responses while keeping details in the server log.
- Fixed installer-side error exposure by removing raw database exception details from the public setup flow.
- Fixed the session API so age-gate state changes now require a valid CSRF token instead of allowing unauthenticated state mutation within the active session.

## [1.0.0] - 2026-03-31

### Added
- Added a full creator suite with application flow, moderation-ready approval path, creator studio, creator analytics, public channel pages, and creator profile controls for avatar, banner, bio, and slug.
- Added creator-focused backend support with dedicated repositories and services for creator applications, channel analytics, and creator publishing workflows.
- Added poster framing controls in the creator studio so publishers can choose which part of the poster stays visible in featured banners and video cards.
- Added support for poster focus persistence in the video data model, along with the `db/upgrade-20260330-poster-framing.sql` migration for existing installs.
- Added a section selector to the admin `Copy` screen so copy can be managed one section at a time instead of through one long page.
- Added a `.user.ini` baseline for higher upload limits on lightweight shared-hosting environments.

### Changed
- Reworked the public front-end around the new shell and navigation model, including homepage, browse, support, Premium, account, legal, and watch/player pages.
- Rebuilt the video player page to better match the new public product identity, with clearer playback hierarchy, cleaner metadata placement, and stronger creator/channel linking.
- Refined the admin workspace to better match the new front-end identity, including updated navigation, denser operational layouts, better styling for form controls, and cleaner section organization.
- Updated the account area to surface creator status, creator application state, and direct links into the creator studio and public channel experience.
- Updated the schema and repositories to support creator-owned videos, creator profile fields, and poster focus coordinates.
- Expanded the public copy system so new navigation labels and creator-related labels can still be managed centrally.

### Improved
- Improved homepage media sizing so featured posters, latest-upload cards, and supporting thumbnails keep more predictable proportions.
- Improved creator publishing UX with poster recommendations for both uploads and external URLs, plus live preview framing for featured and card crops.
- Improved responsive behavior across the public shell and admin shell, including header height handling, sidebar scrolling, category strips, support page layout, and browse/home stat blocks.
- Improved admin usability for the `Copy` screen by reducing cognitive load and making large text sets easier to manage.
- Improved file input, select, textarea, and moderation form styling so admin and studio screens are more visually consistent.

### Fixed
- Fixed public header overlap issues on certain resolutions by syncing the shell layout to the real header height.
- Fixed cropped vertical video playback by changing the player media treatment so portrait videos are contained instead of being cut off.
- Fixed oversized homepage and browse side-stat cards that were stretching vertically due to grid layout behavior.
- Fixed malformed homepage feed markup that could distort the latest-upload cards and make thumbnails appear too large.
- Fixed the support hero layout by simplifying the top area and removing low-value side cards that caused layout breakage.
- Fixed the admin `Copy` workflow so section navigation now lands on the intended content instead of forcing operators through the full page.
- Fixed upload error feedback so oversize uploads no longer fall through as a misleading expired-security-token message.

### Removed
- Removed the legacy `db/seed-demo.sql` file from the current release set.

## [0.2.4] - 2026-03-30

### Added
- Added a dedicated admin-editable message layer for public auth, MFA, password reset, and Premium billing feedback inside the `Copy` screen.
- Added an explicit licensing and security contact email (`gadevs2020@gmail.com`) to the public project documentation.

### Changed
- Moved the remaining public-facing auth and billing success/error messages into the centralized copy system so they can be managed from one place.
- Replaced the previous MIT license with the `VIDEW Source Available Non-Commercial License 1.0`.
- Updated the README, package metadata, and contribution/security docs to position `VIDEW` as source-available rather than OSI open source.

## [0.2.3] - 2026-03-30

### Added
- Added a dedicated `Copy` screen in `admin.php` so public-facing text can be edited from one place.
- Added a centralized public copy system in `config/copy.php` with environment-backed overrides through `VIDEW_COPY_OVERRIDES_B64`.
- Added support for editing homepage, browse, plans, support, watch, account, auth, and age-gate copy without code changes.

### Changed
- Updated major public pages and shared templates to read text from the centralized copy layer.
- Updated the project defaults in `.env.example`, `config/app.php`, `install.php`, and `package.json` to position `VIDEW` as a general video platform with optional age gate support.
- Updated the README to reflect the broader positioning and document the new admin copy screen.

### Fixed
- Fixed the admin `copy` route so `admin.php?screen=copy` no longer falls back to the overview screen.

## [0.2.2] - 2026-03-30

### Added
- Added an admin-controlled `18+ entry notice` toggle in `admin.php` so the public age gate can be enabled or disabled without code changes.
- Added support for the `VIDEW_AGE_GATE_ENABLED` environment variable in `config/app.php`, `src/Support/helpers.php`, and `.env.example`.

### Changed
- Updated the public brand lockup so the yellow `Brand title` badge only appears when a value is actually configured in the admin settings.
- Updated the public bootstrap payload and `assets/js/app.js` so the age gate modal only mounts when the admin setting is enabled.
- Updated `watch.php` so the on-page `18+ notice` only appears when the age gate is enabled.

## [0.2.1] - 2026-03-29

### Added
- Added a `Public head scripts` setting in `admin.php` for analytics, AdSense, verification tags, pixels, and other monetization or tracking snippets.
- Added support for the `VIDEW_PUBLIC_HEAD_SCRIPTS` environment variable in `config/app.php` and `.env.example`.

### Changed
- Updated public templates to render configurable head markup through `public_head_markup()` in `src/Support/helpers.php`.
- Kept the head-script injection limited to public-facing pages, excluding admin and installer screens.

## [0.2.0] - 2026-03-29

### Added
- Added a dedicated `browse.php` page for full catalog search, filtering, and sorting.
- Added a dedicated `support.php` page for account help, billing guidance, and legal contact paths.
- Added a shared public header in `partials/public-header.php` to keep navigation consistent across public pages.
- Added a first product UI/UX roadmap in `UI-UX-ROADMAP.md` to track the restructuring work.

### Changed
- Simplified the homepage in `index.php` so it now acts as a lighter landing page instead of combining landing and full browse behavior in the same screen.
- Reworked the public experience in `premium.php`, `account.php`, `watch.php`, and `partials/legal-page.php` to improve hierarchy, reduce visual overload, and make user actions clearer.
- Reorganized the admin experience in `admin.php` with a sidebar-based shell, task-first page header, grouped navigation, and denser management layouts.
- Updated the shared UI system in `assets/css/app.css` to support lighter page intros, CTA bands, support cards, admin worklists, and the new admin shell.
- Updated `assets/js/app.js` so the catalog app mounts on the dedicated browse page instead of the homepage.
- Refined public and admin copy to sound more user-facing and less technical across authentication, billing, publishing, and account flows.

### Improved
- Improved moderation, users, and activity views to feel more operational and easier to scan.
- Improved account hierarchy with a clearer membership summary, security emphasis, and direct help paths.
- Improved playback page structure by moving key title and metadata details closer to the player.
- Improved footer and useful-link defaults through environment-driven public navigation settings.

### Fixed
- Fixed mixed public navigation patterns across templates by standardizing the main public nav structure.
- Fixed browse links that still pointed to `index.php#catalog` after the browse experience was split into its own page.
- Fixed the public catalog mounting behavior so `gUI` powers the dedicated browse surface as intended.
