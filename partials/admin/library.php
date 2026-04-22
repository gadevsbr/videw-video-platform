<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">LIBRARY</span>
            <h2>Manage videos</h2>
        </div>
        <p>Search, edit, feature, moderate, and delete videos.</p>
    </div>
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Total videos</span>
            <strong><?= e((string) $adminStats['total']); ?></strong>
            <div class="stat-pill muted">Library size</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Approved</span>
            <strong><?= e((string) $adminStats['approved']); ?></strong>
            <div class="stat-pill success">Live</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Draft</span>
            <strong><?= e((string) $adminStats['draft']); ?></strong>
            <div class="stat-pill <?= (int) $adminStats['draft'] > 0 ? 'warning' : 'muted'; ?>">Queue</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Flagged</span>
            <strong><?= e((string) $adminStats['flagged']); ?></strong>
            <div class="stat-pill <?= (int) $adminStats['flagged'] > 0 ? 'danger' : 'muted'; ?>">Moderate</div>
        </article>
    </div>

    <form method="get" class="admin-toolbar">
        <input type="hidden" name="screen" value="library">
        <label>
            <span>Search</span>
            <input type="search" name="library_search" value="<?= e($librarySearch); ?>" placeholder="Title, creator, category">
        </label>
        <label>
            <span>Status</span>
            <select name="library_status">
                <option value="">All</option>
                <option value="draft" <?= $libraryStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                <option value="approved" <?= $libraryStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="flagged" <?= $libraryStatus === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            </select>
        </label>
        <label>
            <span>Source</span>
            <select name="library_source_type">
                <option value="">All</option>
                <option value="upload" <?= $librarySource === 'upload' ? 'selected' : ''; ?>>Upload</option>
                <option value="external_file" <?= $librarySource === 'external_file' ? 'selected' : ''; ?>>External file</option>
                <option value="embed" <?= $librarySource === 'embed' ? 'selected' : ''; ?>>Embed</option>
            </select>
        </label>
        <label>
            <span>Storage</span>
            <select name="library_storage">
                <option value="">All</option>
                <option value="local" <?= $libraryStorage === 'local' ? 'selected' : ''; ?>>Local</option>
                <option value="wasabi" <?= $libraryStorage === 'wasabi' ? 'selected' : ''; ?>>Wasabi</option>
                <option value="external" <?= $libraryStorage === 'external' ? 'selected' : ''; ?>>External</option>
            </select>
        </label>
        <button class="button" type="submit">Filter</button>
        <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_json', 'library_page' => null])); ?>">Export JSON</a>
        <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_csv', 'library_page' => null])); ?>">Export CSV</a>
    </form>
    <form method="post" id="library-bulk-form" class="admin-toolbar admin-toolbar--bulk">
        <input type="hidden" name="action" value="bulk_library_action">
        <?= csrf_input('admin'); ?>
        <label>
            <span>Bulk action</span>
            <select name="bulk_action">
                <option value="approve">Approve selected</option>
                <option value="draft">Move to draft</option>
                <option value="flagged">Flag selected</option>
                <option value="feature">Feature selected</option>
                <option value="unfeature">Unfeature selected</option>
                <option value="delete">Delete selected</option>
            </select>
        </label>
        <button class="button" type="submit">Run bulk action</button>
        <p class="form-note">Select videos below to apply the action.</p>
    </form>

    <?php if ($editingVideo): ?>
        <div class="admin-screen-grid">
            <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
                <input type="hidden" name="action" value="update_video">
                <input type="hidden" name="video_id" value="<?= e((string) $editingVideo['id']); ?>">
                <?= csrf_input('admin'); ?>
                <section class="admin-form-section">
                    <div class="admin-form-section__header">
                        <h3>Edit video</h3>
                        <p>Update metadata, source and moderation for this item.</p>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <label>
                            <span>Title</span>
                            <input type="text" name="title" value="<?= e((string) $editingVideo['title']); ?>" required>
                        </label>
                        <label>
                            <span>Creator</span>
                            <input type="text" name="creator_name" value="<?= e((string) $editingVideo['creator_name']); ?>" required>
                        </label>
                        <label>
                            <span>Category</span>
                            <input type="text" name="category" value="<?= e((string) $editingVideo['category']); ?>" required>
                        </label>
                        <label>
                            <span>Length (minutes)</span>
                            <input type="number" min="0" name="duration_minutes" value="<?= e((string) $editingVideo['duration_minutes']); ?>">
                        </label>
                        <label>
                            <span>Access</span>
                            <select name="access_level">
                                <?php foreach (['free' => 'Free', 'premium' => 'Premium'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) $editingVideo['access_level'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Source</span>
                            <select name="source_mode" data-media-switch="video">
                                <option value="" <?= !isset($_POST['source_mode']) ? 'selected' : ''; ?>>Keep current video</option>
                                <option value="file" <?= (string) ($_POST['source_mode'] ?? '') === 'file' ? 'selected' : ''; ?>>Upload a new file</option>
                                <option value="url" <?= (string) ($_POST['source_mode'] ?? '') === 'url' ? 'selected' : ''; ?>>Use a video URL</option>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span>Description</span>
                        <textarea name="synopsis" rows="4" required><?= e((string) $editingVideo['synopsis']); ?></textarea>
                    </label>
                </section>
                <section class="admin-form-section">
                    <div class="admin-form-section__header">
                        <h3>State</h3>
                        <p>Control moderation and homepage placement.</p>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <label>
                            <span>Moderation status</span>
                            <select name="moderation_status">
                                <?php foreach (['draft' => 'Draft', 'approved' => 'Approved', 'flagged' => 'Flagged'] as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) $editingVideo['moderation_status'] === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Moderation reason</span>
                            <select name="moderation_reason">
                                <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                    <option value="<?= e($value); ?>" <?= (string) ($editingVideo['moderation_reason'] ?? '') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="is_featured" value="1" <?= (int) $editingVideo['is_featured'] === 1 ? 'checked' : ''; ?>>
                            <span>Show on home</span>
                        </label>
                    </div>
                    <label>
                        <span>Moderation notes</span>
                        <textarea name="moderation_notes" rows="4"><?= e((string) $editingVideo['moderation_notes']); ?></textarea>
                    </label>
                </section>
                <section class="admin-form-section">
                    <div class="admin-form-section__header">
                        <h3>Replace media</h3>
                        <p>Choose what you want to replace. If you keep the current option selected, nothing changes.</p>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <label>
                            <span>Poster source</span>
                            <select name="poster_source_mode" data-media-switch="poster">
                                <option value="" <?= !isset($_POST['poster_source_mode']) ? 'selected' : ''; ?>>Keep current poster</option>
                                <option value="upload" <?= (string) ($_POST['poster_source_mode'] ?? '') === 'upload' ? 'selected' : ''; ?>>Upload a new image</option>
                                <option value="url" <?= (string) ($_POST['poster_source_mode'] ?? '') === 'url' ? 'selected' : ''; ?>>Use a poster URL</option>
                            </select>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="remove_poster" value="1">
                            <span>Remove current poster and use the fallback art</span>
                        </label>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <div class="admin-conditional-field" data-media-group="video" data-media-mode="url" style="<?= (string) ($_POST['source_mode'] ?? '') === 'url' ? '' : 'display:none;'; ?>">
                            <label>
                                <span>Video URL</span>
                                <input type="url" name="external_url" value="<?= e((string) ($_POST['external_url'] ?? ($editingVideo['original_source_url'] ?? ''))); ?>" placeholder="https://...">
                            </label>
                        </div>
                        <div class="admin-conditional-field" data-media-group="video" data-media-mode="file" style="<?= (string) ($_POST['source_mode'] ?? '') === 'file' ? '' : 'display:none;'; ?>">
                            <label>
                                <span>Video file</span>
                                <input type="file" name="video_file" accept="video/*">
                            </label>
                        </div>
                        <div class="admin-conditional-field" data-media-group="poster" data-media-mode="upload" style="<?= (string) ($_POST['poster_source_mode'] ?? '') === 'upload' ? '' : 'display:none;'; ?>">
                            <label>
                                <span>Poster image</span>
                                <input type="file" name="poster_file" accept="image/*">
                            </label>
                        </div>
                        <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="<?= (string) ($_POST['poster_source_mode'] ?? '') === 'url' ? '' : 'display:none;'; ?>">
                            <label>
                                <span>Poster URL</span>
                                <input type="url" name="poster_external_url" value="<?= e((string) ($_POST['poster_external_url'] ?? (empty($editingVideo['poster_path']) ? ($editingVideo['stored_poster_url'] ?? '') : ''))); ?>" placeholder="https://...">
                            </label>
                        </div>
                    </div>
                </section>
                <button class="button" type="submit">Save changes</button>
            </form>
            <div class="admin-sidebar-stack">
                <article class="compliance-card">
                    <h3>Editing</h3>
                    <p><strong>Source:</strong> <?= e((string) $editingVideo['source_type']); ?></p>
                    <p><strong>Storage:</strong> <?= e((string) $editingVideo['storage_provider']); ?></p>
                    <p><strong>Status:</strong> <?= e((string) $editingVideo['moderation_label']); ?></p>
                    <p><strong>Reason:</strong> <?= e((string) ($editingVideo['moderation_reason_label'] ?? moderation_reason_label(''))); ?></p>
                    <p><strong>Published:</strong> <?= e((string) $editingVideo['published_label']); ?></p>
                </article>
                <article class="compliance-card">
                    <h3>Moderation history</h3>
                    <?php if ($editingVideoHistory !== []): ?>
                        <div class="creator-analytics-list">
                            <?php foreach ($editingVideoHistory as $historyItem): ?>
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
                    <?php else: ?>
                        <p class="form-note">Moderation history will appear here after the item is reviewed or updated.</p>
                    <?php endif; ?>
                </article>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($libraryVideos === []): ?>
        <div class="notice-card">
            <strong>No videos found</strong>
            <p>Adjust the filters or publish a new item.</p>
        </div>
    <?php else: ?>
        <div class="admin-library-grid">
            <?php foreach ($libraryVideos as $video): ?>
                <article class="admin-library-card">
                    <a class="admin-library-card__media" href="<?= e(base_url('watch.php?slug=' . urlencode((string) $video['slug']))); ?>">
                        <img src="<?= e((string) ($video['resolved_listing_poster_url'] ?? $video['resolved_poster_url'])); ?>" alt="<?= e($video['title']); ?>" style="object-position: <?= e(poster_object_position($video)); ?>;">
                        <div class="admin-library-card__overlay">
                            <div class="admin-library-card__badges">
                                <span class="stat-pill muted"><?= e((string) $video['storage_provider']); ?></span>
                                <span class="stat-pill muted"><?= e((string) $video['source_type']); ?></span>
                            </div>
                            <span class="admin-library-card__duration"><?= e((string) $video['duration_label']); ?></span>
                        </div>
                    </a>
                    <div class="admin-library-card__body">
                        <div class="admin-library-card__header">
                            <label class="bulk-select admin-library-card__select">
                                <input type="checkbox" name="video_ids[]" value="<?= e((string) $video['id']); ?>" form="library-bulk-form">
                                <span>Select</span>
                            </label>
                            <a class="text-link" href="<?= e(base_url('admin.php?screen=library&edit=' . urlencode((string) $video['id']))); ?>">Edit</a>
                        </div>
                        <div class="admin-library-card__identity">
                            <h3><?= e($video['title']); ?></h3>
                            <div class="admin-library-card__meta">
                                <span class="stat-pill <?= (string) $video['moderation_status'] === 'approved' ? 'success' : ((string) $video['moderation_status'] === 'flagged' ? 'danger' : 'warning'); ?>"><?= e((string) $video['moderation_label']); ?></span>
                                <span class="stat-pill <?= (string) $video['access_level'] === 'premium' ? 'accent' : 'muted'; ?>"><?= e((string) $video['access_label']); ?></span>
                                <?php if ((int) ($video['is_featured'] ?? 0) === 1): ?>
                                    <span class="stat-pill accent">Featured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="admin-library-card__summary"><?= e($video['synopsis']); ?></p>
                        <div class="admin-library-card__stats">
                            <span><strong>Creator</strong> <?= e($video['creator_name']); ?></span>
                            <span><strong>Category</strong> <?= e((string) $video['category']); ?></span>
                            <span><strong>Published</strong> <?= e((string) ($video['published_label'] ?? 'No date')); ?></span>
                        </div>
                    </div>
                    <div class="admin-library-card__aside">
                        <div class="admin-library-card__actions">
                            <form method="post">
                                <input type="hidden" name="action" value="toggle_featured">
                                <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                <input type="hidden" name="next_value" value="<?= (int) $video['is_featured'] === 1 ? '0' : '1'; ?>">
                                <?= csrf_input('admin'); ?>
                                <button class="button button--ghost" type="submit"><?= (int) $video['is_featured'] === 1 ? 'Unfeature' : 'Feature'; ?></button>
                            </form>
                            <a class="button button--ghost" href="<?= e(base_url('admin.php?screen=moderation')); ?>">Moderate</a>
                            <form method="post">
                                <input type="hidden" name="action" value="delete_video">
                                <input type="hidden" name="video_id" value="<?= e((string) $video['id']); ?>">
                                <?= csrf_input('admin'); ?>
                                <button class="button button--ghost" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (($libraryPagination['total_pages'] ?? 1) > 1): ?>
            <nav class="pagination">
                <?php for ($pageNumber = 1; $pageNumber <= (int) $libraryPagination['total_pages']; $pageNumber++): ?>
                    <a class="<?= (int) $libraryPagination['page'] === $pageNumber ? 'chip chip--active' : 'chip'; ?>" href="<?= e($queryUrl(['library_page' => $pageNumber, 'edit' => null])); ?>"><?= e((string) $pageNumber); ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
