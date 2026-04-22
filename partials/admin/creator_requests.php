<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">CREATORS</span>
            <h2>Creator request queue</h2>
        </div>
        <p>Approve or reject requests before creator studio access is unlocked.</p>
    </div>
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Pending</span>
            <strong><?= e((string) ($creatorRequestStats['pending'] ?? 0)); ?></strong>
            <div class="stat-pill warning">Review req.</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Approved</span>
            <strong><?= e((string) ($creatorRequestStats['approved'] ?? 0)); ?></strong>
            <div class="stat-pill success">Partners</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Rejected</span>
            <strong><?= e((string) ($creatorRequestStats['rejected'] ?? 0)); ?></strong>
            <div class="stat-pill danger">Denied</div>
        </article>
    </div>
    <div class="admin-screen-nav">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label): ?>
            <a class="<?= $creatorRequestStatus === $value ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['creator_request_status' => $value, 'creator_requests_page' => 1])); ?>"><?= e($label); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($creatorRequests === []): ?>
        <div class="notice-card">
            <strong>No creator requests in this queue</strong>
            <p>Switch the filter or wait for a new creator application.</p>
        </div>
    <?php else: ?>
        <div class="admin-worklist">
            <?php foreach ($creatorRequests as $request): ?>
                <article class="admin-workrow admin-workrow--simple">
                    <div class="admin-workrow__main">
                        <div class="admin-workrow__header">
                            <div class="meta-row">
                                <span class="stat-pill <?= (string) ($request['status'] ?? 'pending') === 'approved' ? 'success' : ((string) ($request['status'] ?? 'pending') === 'rejected' ? 'danger' : 'warning'); ?>"><?= e((string) ($request['status'] ?? 'pending')); ?></span>
                                <span class="stat-pill muted"><?= e((string) ($request['created_label'] ?? '')); ?></span>
                            </div>
                            <h3><?= e((string) ($request['requested_display_name'] ?? '')); ?></h3>
                        </div>
                        <p class="admin-workrow__summary"><?= e((string) ($request['user_display_name'] ?? 'Member')); ?> / <?= e((string) ($request['user_email'] ?? '')); ?></p>
                        <div class="admin-workrow__meta">
                            <span class="form-note">Channel link: <?= e((string) ($request['requested_slug'] ?? '')); ?></span>
                        </div>
                        <?php if (!empty($request['requested_bio'])): ?>
                            <p class="form-note"><?= e((string) $request['requested_bio']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($request['review_notes'])): ?>
                            <p class="form-note"><strong>Review notes:</strong> <?= e((string) $request['review_notes']); ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="admin-workrow__form">
                        <input type="hidden" name="action" value="review_creator_application">
                        <input type="hidden" name="application_id" value="<?= e((string) $request['id']); ?>">
                        <?= csrf_input('admin'); ?>
                        <label>
                            <span>Decision</span>
                            <select name="review_status">
                                <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'pending' => 'Keep pending'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) $request['status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Notes</span>
                            <textarea name="review_notes" rows="3"><?= e((string) ($request['review_notes'] ?? '')); ?></textarea>
                        </label>
                        <button class="button" type="submit">Save review</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (($creatorRequestsPagination['total_pages'] ?? 1) > 1): ?>
            <nav class="pagination">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $creatorRequestsPagination['total_pages']; $pageNumber++): ?>
                    <a class="<?= (int) $creatorRequestsPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['creator_requests_page' => $pageNumber])); ?>"><?= e((string) $pageNumber); ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
