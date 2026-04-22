<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">STORAGE</span>
            <h2>Upload driver and delivery</h2>
        </div>
        <p>Choose where uploaded files live and how protected playback is delivered.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" class="admin-form-shell">
            <input type="hidden" name="action" value="save_storage">
            <?= csrf_input('admin'); ?>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Upload driver</h3>
                    <p>Choose where new video and poster files will be saved.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Driver</span>
                        <select name="upload_driver">
                            <option value="local" <?= ($settings['upload_driver'] ?? 'local') === 'local' ? 'selected' : ''; ?>>This server</option>
                            <option value="wasabi" <?= ($settings['upload_driver'] ?? '') === 'wasabi' ? 'selected' : ''; ?>>Wasabi cloud storage</option>
                        </select>
                    </label>
                    <label>
                        <span>Folder prefix</span>
                        <input type="text" name="wasabi_path_prefix" value="<?= e((string) ($settings['wasabi_path_prefix'] ?? 'videw')); ?>" placeholder="videw">
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Wasabi connection</h3>
                    <p>Enter the bucket details used for Wasabi uploads.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Wasabi endpoint</span>
                        <input type="text" name="wasabi_endpoint" value="<?= e((string) ($settings['wasabi_endpoint'] ?? 'https://s3.wasabisys.com')); ?>" placeholder="https://s3.wasabisys.com">
                    </label>
                    <label>
                        <span>Region</span>
                        <input type="text" name="wasabi_region" value="<?= e((string) ($settings['wasabi_region'] ?? 'us-east-1')); ?>" placeholder="us-east-1">
                    </label>
                    <label>
                        <span>Bucket</span>
                        <input type="text" name="wasabi_bucket" value="<?= e((string) ($settings['wasabi_bucket'] ?? '')); ?>" placeholder="my-bucket">
                    </label>
                    <label>
                        <span>Public base URL</span>
                        <input type="text" name="wasabi_public_base_url" value="<?= e((string) ($settings['wasabi_public_base_url'] ?? '')); ?>" placeholder="https://s3.us-east-1.wasabisys.com/my-bucket">
                    </label>
                    <label>
                        <span>Access key</span>
                        <input type="text" name="wasabi_access_key" value="" placeholder="<?= trim((string) ($settings['wasabi_access_key'] ?? '')) !== '' ? 'Leave blank to keep current key' : 'Paste a new access key'; ?>" autocomplete="off">
                    </label>
                    <label>
                        <span>Secret key</span>
                        <input type="password" name="wasabi_secret_key" value="" placeholder="<?= trim((string) ($settings['wasabi_secret_key'] ?? '')) !== '' ? 'Leave blank to keep current secret' : 'Paste a new secret key'; ?>" autocomplete="new-password">
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Delivery rules</h3>
                    <p>Choose whether Wasabi files stay public or open through time-limited links.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label class="checkbox-line">
                        <input type="checkbox" name="wasabi_private_bucket" value="1" <?= ($settings['wasabi_private_bucket'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span>Keep the bucket private and use time-limited playback links</span>
                    </label>
                    <div class="admin-empty-slot"></div>
                    <label>
                        <span>Signed URL TTL (seconds)</span>
                        <input type="number" min="60" max="604800" name="wasabi_signed_url_ttl_seconds" value="<?= e((string) ($settings['wasabi_signed_url_ttl_seconds'] ?? '900')); ?>">
                    </label>
                    <label>
                        <span>Multipart threshold (MB)</span>
                        <input type="number" min="5" name="wasabi_multipart_threshold_mb" value="<?= e((string) ($settings['wasabi_multipart_threshold_mb'] ?? '64')); ?>">
                    </label>
                    <label>
                        <span>Part size (MB)</span>
                        <input type="number" min="5" name="wasabi_multipart_part_size_mb" value="<?= e((string) ($settings['wasabi_multipart_part_size_mb'] ?? '16')); ?>">
                    </label>
                </div>
            </section>

            <button class="button" type="submit">Save storage settings</button>
        </form>

        <div class="admin-sidebar-stack">
            <article class="admin-guide">
                <div class="admin-guide__header">
                    <span class="eyebrow">HOW IT WORKS</span>
                    <h3>Storage flow</h3>
                    <p>A quick guide to how uploads and playback work on the site.</p>
                </div>
                <div class="admin-steps">
                    <article class="admin-step">
                        <strong>This server</strong>
                        <p>Uploaded files stay on the same hosting account as your site.</p>
                    </article>
                    <article class="admin-step">
                        <strong>Wasabi</strong>
                        <p>Uploaded files are sent to your Wasabi bucket using the saved connection details.</p>
                    </article>
                    <article class="admin-step">
                        <strong>Private settings</strong>
                        <p>Connection details are saved privately for your site and do not appear on the public pages.</p>
                    </article>
                    <article class="admin-step">
                        <strong>Multipart</strong>
                        <p>Large files are uploaded in parts to make big transfers more reliable.</p>
                    </article>
                    <article class="admin-step">
                        <strong>Private playback</strong>
                        <p>When the bucket is private, videos and posters open through protected time-limited links.</p>
                    </article>
                    <article class="admin-step">
                        <strong>External link</strong>
                        <p>You can also publish supported video links without uploading a file.</p>
                    </article>
                </div>
            </article>

            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">STORAGE CLUSTER</span>
                <div class="stack stack--small">
                    <p><strong>Driver:</strong> <span class="stat-pill <?= $wasabiEnabled ? 'accent' : 'muted'; ?>"><?= $wasabiEnabled ? 'Wasabi S3' : 'Local Host'; ?></span></p>
                    <p><strong>Bucket:</strong> <span class="stat-pill muted"><?= e($wasabiBucket !== '' ? $wasabiBucket : 'No bucket'); ?></span></p>
                    <p><strong>Security:</strong> <span class="stat-pill <?= $privateDelivery ? 'success' : 'warning'; ?>"><?= $privateDelivery ? 'Signed' : 'Open'; ?></span></p>
                    <p><strong>Latency:</strong> <span class="stat-pill muted"><?= $wasabiEnabled ? 'External' : 'Near-zero'; ?></span></p>
                </div>
            </article>
        </div>
    </div>
</section>
