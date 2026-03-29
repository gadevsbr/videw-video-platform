# Admin Suite TODO

## Delivered

- [x] Expand database schema for admin operations
- [x] Add incremental SQL upgrade for existing installs
- [x] Add CSRF protection helpers and wire every admin form
- [x] Add audit log persistence and activity screen
- [x] Add admin video repository methods for listing, editing, moderation, feature toggle, and soft delete
- [x] Add publish/edit validation for moderation status
- [x] Add library management screen with search, filter, and actions
- [x] Add moderation queue screen
- [x] Expand user schema with status and last login
- [x] Add user repository methods for listing and updating admin-managed fields
- [x] Add users management screen with role and status controls
- [x] Add general app settings form in admin
- [x] Save app env-driven settings back into `.env`
- [x] Keep storage credentials and app branding under admin control
- [x] Protect the last active admin from being removed or suspended
- [x] Add bulk actions for library and moderation screens
- [x] Add pagination to large video, user, moderation, and activity lists
- [x] Add poster removal and file cleanup workflows
- [x] Add password reset and optional MFA
- [x] Add richer audit log filtering and CSV export
- [x] Update README with new admin capabilities
- [x] Run PHP lint and admin smoke tests

## Future Backlog

- [ ] Add email delivery for reset links and security notifications
- [ ] Add QR rendering for MFA setup
- [ ] Add restore flow for soft-deleted videos
- [ ] Add background cleanup of old reset tokens and orphaned media
