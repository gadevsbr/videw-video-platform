# Security Policy

## Supported Versions

Security fixes are applied to the current `main` branch.

## Reporting a Vulnerability

Please do not report security issues in public GitHub issues or discussions.

If you discover a vulnerability:

1. Prepare a short report with the affected file or flow.
2. Include reproduction steps, impact, and any proof-of-concept details that are necessary.
3. Send the report privately to `gadevs2020@gmail.com`, or another private channel controlled by the maintainer.

When a report is confirmed, the goal is to:

- acknowledge it quickly
- reproduce it
- prepare a fix on a private branch if needed
- publish the fix with a short advisory after the patch is available

## Scope

Please report issues related to:

- authentication and session handling
- access control and premium gating
- file upload and media delivery
- admin panel authorization
- SQL injection, XSS, CSRF, SSRF, or secret exposure
- Stripe, Wasabi, and `.env` handling
