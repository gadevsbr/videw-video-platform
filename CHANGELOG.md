# Changelog

All notable changes to this project will be documented in this file.

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
