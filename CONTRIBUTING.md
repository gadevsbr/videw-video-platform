# Contributing

Thanks for considering a contribution to `VIDEW`.

## Before You Start

- Use the issue tracker for bugs, regressions, and feature discussions.
- Do not open public issues for security vulnerabilities. Use the process in [SECURITY.md](SECURITY.md).
- Keep pull requests focused. Small, reviewable changes are preferred over broad refactors.

## Local Setup

1. Copy `.env.example` to `.env`.
2. Fill in local values for database and URLs.
3. Run `npm install` if you want to refresh the vendored `gUI` runtime.
4. Import `db/schema.sql`, or run the web installer at `install.php`.
5. Optionally import `db/seed-demo.sql` if you want example catalog records.

## Pull Request Guidelines

- Target the smallest change set that solves the problem.
- Update `README.md` when setup, runtime behavior, or deployment steps change.
- Keep secrets, `.env`, uploaded media, and local archives out of the branch.
- If you update `@bragamateus/gui`, run `npm run sync:gui` and include the refreshed files in `assets/vendor/gui`.
- Run the relevant validation before opening a PR:
  - `php -l` for changed PHP files
  - `npm run check:js`

## Style Notes

- Follow the existing PHP style in the repository.
- Prefer simple server-side flows over framework-heavy abstractions.
- Keep shared-hosting compatibility in mind when introducing dependencies or build steps.
