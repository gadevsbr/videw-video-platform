<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">SETTINGS</span>
            <h2>General application settings</h2>
        </div>
        <p>Update the public name, support details, main site links, and optional head scripts.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" class="admin-form-shell">
            <input type="hidden" name="action" value="save_app_settings">
            <?= csrf_input('admin'); ?>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Branding</h3>
                    <p>Control the visible product name and short lockup. Leave Brand title empty to hide the yellow tag.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>App name</span>
                        <input type="text" name="app_name" value="<?= e($appSettings['app_name']); ?>">
                    </label>
                    <label>
                        <span>Description</span>
                        <input type="text" name="app_description" value="<?= e($appSettings['app_description']); ?>">
                    </label>
                    <label>
                        <span>Brand kicker</span>
                        <input type="text" name="brand_kicker" value="<?= e($appSettings['brand_kicker']); ?>">
                    </label>
                    <label>
                        <span>Brand title</span>
                        <input type="text" name="brand_title" value="<?= e($appSettings['brand_title']); ?>">
                    </label>
                </div>
            </section>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Entry notice</h3>
                    <p>Choose whether the 18+ entry warning should appear before visitors continue into the site.</p>
                </div>
                <label class="checkbox-line">
                    <input type="checkbox" name="age_gate_enabled" value="1" <?= !empty($appSettings['age_gate_enabled']) ? 'checked' : ''; ?>>
                    <span>Show the 18+ entry notice on public pages</span>
                </label>
                <p class="form-note">When this is off, the modal warning is hidden. When this is on, visitors will see the entry notice again until they confirm.</p>
            </section>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>URLs and support</h3>
                    <p>Keep the public URLs and contact points under admin control.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Base URL</span>
                        <input type="text" name="base_url" value="<?= e($appSettings['base_url']); ?>">
                    </label>
                    <label>
                        <span>Support email</span>
                        <input type="email" name="support_email" value="<?= e($appSettings['support_email']); ?>">
                    </label>
                    <label>
                        <span>Exit URL</span>
                        <input type="text" name="exit_url" value="<?= e($appSettings['exit_url']); ?>">
                    </label>
                    <label>
                        <span>Timezone</span>
                        <input type="text" name="timezone" value="<?= e($appSettings['timezone']); ?>">
                    </label>
                </div>
            </section>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Release monitoring</h3>
                    <p>Show update information in the admin overview by comparing this install against GitHub releases.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>GitHub repository</span>
                        <input type="text" name="github_repository" value="<?= e($appSettings['github_repository']); ?>" placeholder="owner/repository">
                    </label>
                    <label>
                        <span>Installed version</span>
                        <input type="text" name="current_version" value="<?= e($appSettings['current_version']); ?>" placeholder="1.0.2">
                    </label>
                </div>
                <p class="form-note">Leave the repository empty to disable release checks. Leave Installed version empty to fall back to the top version in <code>CHANGELOG.md</code>.</p>
            </section>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Public head scripts</h3>
                    <p>Paste analytics, monetization, verification, or tracking scripts that should load inside the <head> of public pages.</p>
                </div>
                <label>
                    <span>Scripts rendered on public pages</span>
                    <textarea name="public_head_scripts" rows="10" placeholder="<script async src=&quot;https://www.googletagmanager.com/gtag/js?id=...&quot;></script><?= PHP_EOL; ?><script>/* analytics or adsense code */</script>"><?= e($appSettings['public_head_scripts']); ?></textarea>
                </label>
                <p class="form-note">These scripts are injected as-is into public page heads. Admin and installer screens are excluded. Only paste trusted snippets because they run with full access to the public frontend.</p>
            </section>
            <button class="button" type="submit">Save site settings</button>
        </form>
        <div class="admin-sidebar-stack">
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">DISTRIBUTION</span>
                <p>System-wide branding and security parameters. Configured via the unified administrative payload.</p>
                <div class="stat-pill success">Internal</div>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">AGE VERIFICATION</span>
                <p><strong>Status:</strong> <span class="stat-pill <?= !empty($appSettings['age_gate_enabled']) ? 'warning' : 'muted'; ?>"><?= !empty($appSettings['age_gate_enabled']) ? 'Enabled' : 'Disabled'; ?></span></p>
                <p>Enforces a mandatory confirmation modal for non-authenticated sessions.</p>
            </article>
            <article class="compliance-card">
                <h3>Head script status</h3>
                <p><strong>Custom scripts:</strong> <?= trim($appSettings['public_head_scripts']) !== '' ? 'Enabled' : 'Disabled'; ?></p>
                <p>Useful for Google Analytics, AdSense, verification tags, pixels, and other monetization or measurement platforms.</p>
            </article>
            <article class="compliance-card">
                <h3>Release monitoring</h3>
                <p><strong>Status:</strong> <?= e($releaseStatusTitle); ?></p>
                <p><?= e($releaseStatusDetail); ?></p>
            </article>
            <article class="compliance-card">
                <h3>Backup export</h3>
                <p>Download a JSON snapshot of app, storage, billing, legal, copy, and ad-slot admin settings.</p>
                <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'backup'])); ?>">Download backup JSON</a>
                <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_json'])); ?>">Full catalog JSON</a>
                <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'catalog_csv'])); ?>">Full catalog CSV</a>
                <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_json'])); ?>">Full users JSON</a>
                <a class="button button--ghost" href="<?= e($queryUrl(['export' => 'users_csv'])); ?>">Full users CSV</a>
                <p class="form-note">User exports include account and creator profile fields, but do not include password hashes, MFA secrets, or backup codes.</p>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">SYSTEM RUNTIME</span>
                <div class="stack stack--small">
                    <p><strong>App:</strong> <span class="stat-pill accent"><?= e((string) (($databaseVersionStatus['code_version'] ?? '') !== '' ? $databaseVersionStatus['code_version'] : 'Not set')); ?></span></p>
                    <p><strong>Database:</strong> <span class="stat-pill success"><?= e((string) (($databaseVersionStatus['db_version'] ?? '') !== '' ? $databaseVersionStatus['db_version'] : 'Unknown')); ?></span></p>
                    <p><strong>Release:</strong> <span class="stat-pill muted"><?= e($releaseStatusTitle); ?></span></p>
                </div>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">MIGRATION LOG</span>
                <p><strong>ID:</strong> <?= e((string) (($databaseLatestMigration['filename'] ?? '') !== '' ? $databaseLatestMigration['filename'] : 'None')); ?></p>
                <p><strong>Applied:</strong> <?= e($databaseLatestMigrationAppliedAt); ?></p>
            </article>
        </div>
    </div>
</section>
