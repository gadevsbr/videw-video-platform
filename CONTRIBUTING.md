# Contributing

Thanks for your interest in improving `VIDEW`.

VIDEW is distributed under a **source-available non-commercial license**. By contributing, you agree that your contribution may be distributed under the same license.

## Before You Open a Pull Request

- use GitHub issues for bugs, regressions, documentation improvements, and feature discussions
- do **not** report security vulnerabilities in public issues; use [SECURITY.md](SECURITY.md)
- keep changes focused and reviewable
- avoid bundling unrelated refactors with product or bug-fix work

## Local Setup

1. Copy `.env.example` to `.env`.
2. Fill in the local database and URL values.
3. Import `db/schema.sql`, or use `install.php` for a local installer-driven setup.
4. Optionally import `db/seed-demo.sql` if your local checkout includes a demo seed file.
5. Run `npm install` only if you need to maintain or refresh the vendored `gUI` runtime.

## Contribution Priorities

Contributions are especially useful in these areas:

- documentation clarity
- deployment compatibility
- admin and creator workflow polish
- billing and storage robustness
- UI/UX consistency
- security hardening

## Pull Request Checklist

- keep the change set small enough to review comfortably
- update documentation when setup, deployment, runtime behavior, or public positioning changes
- do not commit secrets, `.env`, uploaded media, archives, or local test artifacts
- if you update `@bragamateus/gui`, run `npm run sync:gui` and commit the refreshed files in `assets/vendor/gui`
- run relevant validation before opening the PR:
  - `php -l` for changed PHP files
  - `npm run check:js`

## Style Notes

- follow the existing PHP style already used in the repository
- prefer straightforward server-side flows over framework-heavy abstractions
- keep shared-hosting compatibility in mind when adding dependencies or build requirements
- avoid introducing documentation claims for features that do not exist in the codebase
