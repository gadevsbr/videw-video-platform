<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">PUBLISH</span>
            <h2>New video form</h2>
        </div>
        <p>Use files or supported external sources.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" enctype="multipart/form-data" class="admin-form-shell" data-media-source-form>
            <input type="hidden" name="action" value="publish_video">
            <?= csrf_input('admin'); ?>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Basic details</h3>
                    <p>Start with the title, creator, category, and short description.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Title</span>
                        <input type="text" name="title" value="<?= e(old('title')); ?>" required>
                    </label>
                    <label>
                        <span>Creator</span>
                        <input type="text" name="creator_name" value="<?= e(old('creator_name')); ?>" required>
                    </label>
                    <label>
                        <span>Category</span>
                        <input type="text" name="category" value="<?= e(old('category')); ?>" required>
                    </label>
                    <label>
                        <span>Length (minutes)</span>
                        <input type="number" min="0" name="duration_minutes" value="<?= e(old('duration_minutes', '0')); ?>">
                    </label>
                </div>
                <label>
                    <span>Description</span>
                    <textarea name="synopsis" rows="5" required><?= e(old('synopsis')); ?></textarea>
                </label>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Visibility</h3>
                    <p>Choose who can watch the item and whether it should appear on the home page.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Access</span>
                        <select name="access_level">
                            <option value="free" <?= old('access_level', 'free') === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="premium" <?= old('access_level') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                        </select>
                    </label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_featured" value="1" <?= old('is_featured') === '1' ? 'checked' : ''; ?>>
                        <span>Show on home</span>
                    </label>
                    <label>
                        <span>Moderation status</span>
                        <select name="moderation_status">
                            <option value="draft" <?= old('moderation_status', 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="approved" <?= old('moderation_status') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="flagged" <?= old('moderation_status') === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                        </select>
                    </label>
                    <label>
                        <span>Moderation reason</span>
                        <select name="moderation_reason">
                            <?php foreach ($moderationReasonOptions as $value => $label): ?>
                                <option value="<?= e($value); ?>" <?= old('moderation_reason') === $value ? 'selected' : ''; ?>><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label>
                    <span>Moderation notes</span>
                    <textarea name="moderation_notes" rows="4" placeholder="Optional internal notes"><?= e(old('moderation_notes')); ?></textarea>
                </label>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Media source</h3>
                    <p>Start by choosing how you want to add the video and the poster.</p>
                    <p>Current server upload limits: video/poster file max <?= e(ini_size_label('upload_max_filesize')); ?>, full request max <?= e(ini_size_label('post_max_size')); ?>.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Video source</span>
                        <select name="source_mode" data-media-switch="video">
                            <option value="" <?= old('source_mode', '') === '' ? 'selected' : ''; ?>>Choose how to add the video</option>
                            <option value="file" <?= old('source_mode') === 'file' ? 'selected' : ''; ?>>Upload a file</option>
                            <option value="url" <?= old('source_mode') === 'url' ? 'selected' : ''; ?>>External URL</option>
                        </select>
                    </label>
                    <label>
                        <span>Poster source</span>
                        <select name="poster_source_mode" data-media-switch="poster">
                            <option value="" <?= old('poster_source_mode', '') === '' ? 'selected' : ''; ?>>Use fallback artwork</option>
                            <option value="upload" <?= old('poster_source_mode') === 'upload' ? 'selected' : ''; ?>>Upload an image</option>
                            <option value="url" <?= old('poster_source_mode') === 'url' ? 'selected' : ''; ?>>Poster URL</option>
                        </select>
                    </label>
                </div>
                <div class="admin-fields admin-fields--two">
                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="url" style="<?= old('source_mode') === 'url' ? '' : 'display:none;'; ?>">
                        <label>
                            <span>Video URL</span>
                            <input type="url" name="external_url" value="<?= e(old('external_url')); ?>" placeholder="https://...">
                        </label>
                    </div>
                    <div class="admin-conditional-field" data-media-group="video" data-media-mode="file" style="<?= old('source_mode') === 'file' ? '' : 'display:none;'; ?>">
                        <label>
                            <span>Video file</span>
                            <input type="file" name="video_file" accept="video/*">
                        </label>
                    </div>
                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="upload" style="<?= old('poster_source_mode') === 'upload' ? '' : 'display:none;'; ?>">
                        <label>
                            <span>Poster image</span>
                            <input type="file" name="poster_file" accept="image/*">
                        </label>
                    </div>
                    <div class="admin-conditional-field" data-media-group="poster" data-media-mode="url" style="<?= old('poster_source_mode') === 'url' ? '' : 'display:none;'; ?>">
                        <label>
                            <span>Poster URL</span>
                            <input type="url" name="poster_external_url" value="<?= e(old('poster_external_url')); ?>" placeholder="https://...">
                        </label>
                    </div>
                </div>
            </section>

            <button class="button" type="submit" <?= !$dbReady ? 'disabled' : ''; ?>>Publish video</button>
        </form>

        <div class="admin-sidebar-stack">
            <article class="admin-guide glass">
                <div class="admin-guide__header">
                    <span class="eyebrow eyebrow--small">CHECKLIST</span>
                    <h3>Before you publish</h3>
                    <p>Ensure the media source matches the target distribution goal.</p>
                </div>
                <div class="admin-steps">
                    <article class="admin-step">
                        <div class="stat-pill accent">Source</div>
                        <strong>File upload</strong>
                        <p>Stored on local server or Wasabi S3.</p>
                    </article>
                    <article class="admin-step">
                        <div class="stat-pill muted">External</div>
                        <strong>Direct URL</strong>
                        <p>Proxied or embedded via external host.</p>
                    </article>
                    <article class="admin-step">
                        <div class="stat-pill warning">Visual</div>
                        <strong>Poster</strong>
                        <p>Custom artwork is recommended for library density.</p>
                    </article>
                </div>
            </article>

            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">INFRASTRUCTURE</span>
                <div class="stack stack--small">
                    <p><strong>Storage:</strong> <span class="stat-pill success"><?= $wasabiEnabled ? 'Wasabi' : 'Local'; ?></span></p>
                    <p><strong>Playback:</strong> <span class="stat-pill accent"><?= $privateDelivery ? 'Signed' : 'Public'; ?></span></p>
                    <p><strong>DB Connection:</strong> <span class="stat-pill <?= $dbReady ? 'success' : 'danger'; ?>"><?= $dbReady ? 'Ready' : 'Pending'; ?></span></p>
                </div>
            </article>
        </div>
    </div>
</section>
