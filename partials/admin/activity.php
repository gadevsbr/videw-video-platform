<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">ACTIVITY</span>
            <h2>Recent admin actions</h2>
        </div>
        <p>Audit trail for storage, videos, users, and settings.</p>
    </div>
    <form method="get" class="admin-toolbar">
        <input type="hidden" name="screen" value="activity">
        <label>
            <span>Action</span>
            <input type="search" name="activity_action" value="<?= e($activityFilters['action']); ?>" placeholder="video.updated">
        </label>
        <label>
            <span>Target type</span>
            <select name="activity_target_type">
                <option value="">All</option>
                <option value="video" <?= $activityFilters['target_type'] === 'video' ? 'selected' : ''; ?>>Video</option>
                <option value="user" <?= $activityFilters['target_type'] === 'user' ? 'selected' : ''; ?>>User</option>
                <option value="settings" <?= $activityFilters['target_type'] === 'settings' ? 'selected' : ''; ?>>Settings</option>
            </select>
        </label>
        <label>
            <span>Actor</span>
            <input type="search" name="activity_actor" value="<?= e($activityFilters['actor']); ?>" placeholder="Admin name or email">
        </label>
        <label>
            <span>From</span>
            <input type="date" name="activity_from" value="<?= e($activityFilters['from']); ?>">
        </label>
        <label>
            <span>To</span>
            <input type="date" name="activity_to" value="<?= e($activityFilters['to']); ?>">
        </label>
        <button class="button" type="submit">Filter</button>
        <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'csv', 'activity_page' => null])); ?>">Export CSV</a>
    </form>
    <?php if ($activityItems === []): ?>
        <div class="notice-card">
            <strong>No activity recorded yet</strong>
            <p>Admin actions will start appearing here after the first changes are saved.</p>
        </div>
    <?php else: ?>
        <div class="admin-worklist">
            <?php foreach ($activityItems as $item): ?>
                <article class="admin-activity-row">
                    <div class="admin-activity-row__main">
                        <div class="admin-activity-row__meta">
                            <span class="stat-pill accent"><?= e((string) $item['action']); ?></span>
                            <span class="stat-pill muted"><?= e((string) $item['target_type']); ?><?php if (!empty($item['target_id'])): ?> #<?= e((string) $item['target_id']); ?><?php endif; ?></span>
                        </div>
                        <h3><?= e((string) $item['summary']); ?></h3>
                        <?php if (!empty($item['metadata_json'])): ?>
                            <p><?= e((string) $item['metadata_json']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="admin-activity-row__aside">
                        <strong><?= e((string) ($item['actor_name'] ?: $item['actor_email'] ?: 'System')); ?></strong>
                        <div><?= e(format_datetime((string) ($item['created_at'] ?? null))); ?></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (($activityPagination['total_pages'] ?? 1) > 1): ?>
            <nav class="pagination">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $activityPagination['total_pages']; $pageNumber++): ?>
                    <a class="<?= (int) $activityPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['activity_page' => $pageNumber, 'export' => null])); ?>"><?= e((string) $pageNumber); ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
