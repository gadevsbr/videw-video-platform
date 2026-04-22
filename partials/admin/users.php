<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">USERS</span>
            <h2>Accounts and roles</h2>
        </div>
        <p>Manage role assignment and account status.</p>
    </div>
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Total users</span>
            <strong><?= e((string) $userStats['users']); ?></strong>
            <div class="stat-pill muted">Registered</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Admins</span>
            <strong><?= e((string) $userStats['admins']); ?></strong>
            <div class="stat-pill danger">Staff</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Creators</span>
            <strong><?= e((string) $userStats['creators']); ?></strong>
            <div class="stat-pill accent">Partners</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Suspended</span>
            <strong><?= e((string) $userStats['suspended']); ?></strong>
            <div class="stat-pill warning">Review req.</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Security</span>
            <strong><?= e((string) $userStats['mfa_enabled']); ?></strong>
            <div class="stat-pill success">2FA Active</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Revenue</span>
            <strong><?= e((string) ($userStats['premium'] ?? 0)); ?></strong>
            <div class="stat-pill accent">Premium</div>
        </article>
    </div>
    <form method="get" class="admin-toolbar">
        <input type="hidden" name="screen" value="users">
        <label>
            <span>Search</span>
            <input type="search" name="user_search" value="<?= e($userSearch); ?>" placeholder="Name or email">
        </label>
        <button class="button" type="submit">Filter</button>
        <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_json', 'users_page' => null])); ?>">Export JSON</a>
        <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_csv', 'users_page' => null])); ?>">Export CSV</a>
    </form>
    <?php if ($users === []): ?>
        <div class="notice-card">
            <strong>No users found</strong>
            <p>Try a different search or create a new account from the public register page.</p>
        </div>
    <?php else: ?>
        <div class="admin-worklist">
            <?php foreach ($users as $managedUser): ?>
                <article class="admin-user-row">
                    <div class="admin-user-row__main">
                        <div class="admin-user-row__meta">
                            <span class="stat-pill <?= (string) $managedUser['role'] === 'admin' ? 'danger' : ((string) $managedUser['role'] === 'creator' ? 'accent' : 'muted'); ?>"><?= e((string) $managedUser['role']); ?></span>
                            <span class="stat-pill <?= (string) ($managedUser['status'] ?? 'active') === 'active' ? 'success' : 'warning'; ?>"><?= e(user_status_label((string) ($managedUser['status'] ?? 'active'))); ?></span>
                            <span class="stat-pill <?= (string) ($managedUser['account_tier'] ?? 'free') === 'premium' ? 'accent' : 'muted'; ?>"><?= e(account_tier_label((string) ($managedUser['account_tier'] ?? 'free'))); ?></span>
                            <?php if ((int) ($managedUser['mfa_enabled'] ?? 0) === 1): ?>
                                <span class="stat-pill success">2FA</span>
                            <?php endif; ?>
                        </div>
                        <h3><?= e((string) $managedUser['display_name']); ?></h3>
                        <p><?= e((string) $managedUser['email']); ?></p>
                        <?php if (!empty($managedUser['stripe_subscription_status'])): ?>
                            <p class="form-note">Membership status: <?= e(subscription_status_label((string) $managedUser['stripe_subscription_status'])); ?></p>
                        <?php endif; ?>
                        <p class="form-note">Joined <?= e(format_datetime((string) ($managedUser['created_at'] ?? null))); ?><?php if (!empty($managedUser['last_login_at'])): ?> / Last login <?= e(format_datetime((string) $managedUser['last_login_at'])); ?><?php endif; ?></p>
                    </div>
                    <form method="post" class="admin-user-row__form">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= e((string) $managedUser['id']); ?>">
                        <?= csrf_input('admin'); ?>
                        <label>
                            <span>Role</span>
                            <select name="role">
                                <?php foreach (['member' => 'Member', 'creator' => 'Creator', 'admin' => 'Admin'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) $managedUser['role'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Status</span>
                            <select name="status">
                                <?php foreach (['active' => 'Active', 'suspended' => 'Suspended'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) ($managedUser['status'] ?? 'active') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button" type="submit">Save user</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (($usersPagination['total_pages'] ?? 1) > 1): ?>
            <nav class="pagination">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $usersPagination['total_pages']; $pageNumber++): ?>
                    <a class="<?= (int) $usersPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['users_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
