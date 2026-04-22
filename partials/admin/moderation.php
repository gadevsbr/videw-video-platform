<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">MODERATION</span>
            <h2>Moderation queue</h2>
        </div>
        <p>Review video state and leave internal notes.</p>
    </div>
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Draft</span>
            <strong><?= e((string) $adminStats['draft']); ?></strong>
            <div class="stat-pill warning">Pending</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Approved</span>
            <strong><?= e((string) $adminStats['approved']); ?></strong>
            <div class="stat-pill success">Live</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Flagged</span>
            <strong><?= e((string) $adminStats['flagged']); ?></strong>
            <div class="stat-pill danger">Action req.</div>
        </article>
    </div>
    <div class="admin-screen-nav">
        <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
            <a class="<?= $moderationStatusFilter === $value ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['moderation_status' => $value, 'moderation_page' => 1])); ?>"><?= e($label); ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="admin-toolbar">
        <input type="hidden" name="screen" value="moderation">
        <input type="hidden" name="moderation_status" value="<?= e($moderationStatusFilter); ?>">
        <label>
            <span>Reason</span>
            <select name="moderation_reason">
                <?php foreach ($moderationReasonOptions as $value => $label): ?>
                    <option value="<?= e($value); ?>" <?= $moderationReasonFilter === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button" type="submit">Filter queue</button>
        <a class="button button--ghost" href="<?= e($queryUrl(['moderation_reason' => null, 'moderation_page' => 1])); ?>">Clear reason filter</a>
    </form>
    <form method="post" id="moderation-bulk-form" class="admin-toolbar admin-toolbar--bulk">
        <input type="hidden" name="action" value="bulk_moderation_action">
        <?= csrf_input('admin'); ?>
        <label>
            <span>Bulk action</span>
            <select name="bulk_action">
                <option value="approve">Approve selected</option>
                <option value="draft">Move to draft</option>
                <option value="flagged">Flag selected</option>
                <option value="delete">Delete selected</option>
            </select>
        </label>
        <label>
            <span>Reason</span>
            <select name="bulk_reason">
                <?php foreach ($moderationReasonOptions as $value => $label): ?>
                    <option value="<?= e($value); ?>"><?= e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Notes</span>
            <input type="text" name="bulk_notes" placeholder="Optional moderation note">
        </label>
        <button class="button" type="submit">Run moderation action</button>
    </form>
    <?php if ($moderationVideos === []): ?>
        <div class="notice-card">
            <strong>No items in this queue</strong>
            <p>Pick another status filter or publish a new draft.</p>
        </div>
    <?php else: ?>
        <div class="admin-worklist">
            <?php foreach ($moderationVideos as $video): ?>
                <article class="admin-workrow">
                    <label class="bulk-select">
                        <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="moderation-bulk-form">
                        <span>Select</span>
                    </label>
                    <div class="admin-workrow__thumb">
                        <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                    </div>
                    <div class="admin-workrow__main">
                        <div class="admin-workrow__header">
                            <div class="meta-row">
                                <span class="stat-pill muted"><?= e($video['category']); ?></span>
                                <span class="stat-pill <?= (string) $video['moderation_status'] === 'approved' ? 'success' : ((string) $video['moderation_status'] === 'flagged' ? 'danger' : 'warning'); ?>"><?= e((string) $video['moderation_label']); ?></span>
                                <?php if ((string) ($video['moderation_reason'] ?? '') !== ''): ?>
                                    <span class="stat-pill warning"><?= e((string) $video['moderation_reason_label']); ?></span>
                                <?php endif; ?>
                                <span class="stat-pill <?= (string) $video['access_level'] === 'premium' ? 'accent' : 'muted'; ?>"><?= e((string) $video['access_label']); ?></span>
                            </div>
                            <h3><?= e($video['title']); ?></h3>
                        </div>
                        <p class="admin-workrow__summary"><?= e($video['creator_name']); ?> / <?= e($video['duration_label']); ?> / <?= e((string) $video['storage_provider']); ?></p>
                        <div class="admin-workrow__meta">
                            <span class="form-note">Source: <?= e((string) $video['source_type']); ?></span>
                            <span class="form-note">Published: <?= e((string) ($video['published_label'] ?? 'No date')); ?></span>
                        </div>
                    </div>
                    <form method="post" class="admin-workrow__form">
                        <input type="hidden" name="action" value="moderate_video">
                        <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                        <?= csrf_input('admin'); ?>
                        <label>
                            <span>Status</span>
                            <select name="moderation_status">
                                <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) $video['moderation_status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Reason</span>
                            <select name="moderation_reason">
                                <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) ($video['moderation_reason'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Notes</span>
                            <textarea name="moderation_notes" rows="3"><?= e((string) $video['moderation_notes']); ?></textarea>
                        </label>
                        <?php $videoHistory = $moderationHistoryByVideo[(int) ($video['id'] ?? 0)] ?? []; ?>
                        <?php if ($videoHistory !== []): ?>
                            <div class="creator-analytics-list">
                                <?php foreach ($videoHistory as $historyItem): ?>
                                    <?php $historyMeta = json_decode((string) ($historyItem['metadata_json'] ?? ''), true); ?>
                                    <div class="creator-analytics-list__row">
                                        <div>
                                            <strong><?= e((string) ($historyItem['summary'] ?? 'History')); ?></strong>
                                            <p><?= e((string) (($historyItem['actor_name'] ?? '') !== '' ? $historyItem['actor_name'] : ($historyItem['actor_email'] ?? 'System'))); ?></p>
                                        </div>
                                        <div class="creator-analytics-list__stats">
                                            <?php if (is_array($historyMeta) && !empty($historyMeta['status'])): ?>
                                                <span><?= e(moderation_label((string) $historyMeta['status'])); ?></span>
                                            <?php endif; ?>
                                            <?php if (is_array($historyMeta) && array_key_exists('reason', $historyMeta)): ?>
                                                <span><?= e(moderation_reason_label((string) ($historyMeta['reason'] ?? ''))); ?></span>
                                            <?php endif; ?>
                                            <span><?= e(format_datetime((string) ($historyItem['created_at'] ?? null))); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <button class="button" type="submit">Save moderation</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (($moderationPagination['total_pages'] ?? 1) > 1): ?>
            <nav class="pagination">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $moderationPagination['total_pages']; $pageNumber++): ?>
                    <a class="<?= (int) $moderationPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['moderation_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
