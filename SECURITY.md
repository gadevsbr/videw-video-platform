# Security Policy

## Supported Versions

Security fixes are currently applied to the active `main` branch.

## Reporting a Vulnerability

Please do not report security issues in public GitHub issues or discussions.

If you discover a vulnerability:

1. Prepare a short report with the affected file or flow.
2. Include reproduction steps, impact, and any proof-of-concept details that are necessary.
3. Send the report privately to `gadevs2020@gmail.com`, or another private channel controlled by the maintainer.

When a report is confirmed, the goal is to:

- acknowledge it quickly
- reproduce it
- prepare a fix on a private branch when appropriate
- publish the fix with a short advisory after the patch is available

## Scope

Please report issues related to:

- authentication and session handling
- access control and premium gating
- file upload and media delivery
- admin panel authorization
- SQL injection, XSS, CSRF, SSRF, or secret exposure
- Stripe, Wasabi, and `.env` handling

## Helpful Report Format

Including the following makes triage much faster:

- affected file or route
- affected user role or permission level
- reproduction steps
- expected vs actual behavior
- impact
- any screenshots, logs, or proof-of-concept material that are needed to reproduce safely
