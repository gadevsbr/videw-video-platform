<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">COPY</span>
            <h2>Public text editor</h2>
        </div>
        <p>Edit the visible public copy used across the homepage, browse, plans, support, watch, account, auth, and age-gate flows.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" class="admin-form-shell">
            <input type="hidden" name="action" value="save_copy_settings">
            <?= csrf_input('admin'); ?>
            <div class="copy-editor-controls" data-copy-editor>
                <label class="copy-editor-controls__label">
                    <span>Section</span>
                    <select data-copy-section-selector>
                        <?php foreach ($copySectionTabs as $tab): ?>
                            <option value="<?= e($tab['id']); ?>"><?= e($tab['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="copy-editor-controls__summary" data-copy-section-summary>
                    <strong><?= e($copySectionTabs[0]['title'] ?? 'Copy'); ?></strong>
                    <p><?= e($copySectionTabs[0]['description'] ?? 'Edit the text used across public pages.'); ?></p>
                </div>
            </div>
            <?php foreach ($copySections as $index => $section): ?>
                <?php $panelId = 'copy-section-' . ($index + 1); ?>
                <section class="admin-form-section copy-editor-panel" data-copy-section-panel="<?= e($panelId); ?>"<?= $index === 0 ? '' : ' hidden'; ?>>
                    <div class="admin-form-section__header">
                        <h3><?= e((string) $section['title']); ?></h3>
                        <p><?= e((string) $section['description']); ?></p>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <?php foreach ($section['fields'] as $field): ?>
                            <?php $formKey = str_replace('.', '__', (string) $field['key']); ?>
                            <label>
                                <span><?= e((string) $field['label']); ?></span>
                                <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                                    <textarea name="copy[<?= e($formKey); ?>]" rows="<?= e((string) ($field['rows'] ?? 3)); ?>"><?= e($copySettings[(string) $field['key']] ?? ''); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="copy[<?= e($formKey); ?>]" value="<?= e($copySettings[(string) $field['key']] ?? ''); ?>">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <?php if ($copyExtraFields !== []): ?>
                <section class="admin-form-section copy-editor-panel" data-copy-section-panel="copy-section-extra" hidden>
                    <div class="admin-form-section__header">
                        <h3>Additional copy keys</h3>
                        <p>These keys are generated automatically from the text system so every remaining public text stays editable too.</p>
                    </div>
                    <div class="admin-fields admin-fields--two">
                        <?php foreach ($copyExtraFields as $field): ?>
                            <?php $formKey = str_replace('.', '__', (string) $field['key']); ?>
                            <label>
                                <span><?= e((string) $field['label']); ?></span>
                                <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                                    <textarea name="copy[<?= e($formKey); ?>]" rows="<?= e((string) ($field['rows'] ?? 4)); ?>"><?= e($copySettings[(string) $field['key']] ?? ''); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="copy[<?= e($formKey); ?>]" value="<?= e($copySettings[(string) $field['key']] ?? ''); ?>">
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <button class="button" type="submit">Save public text</button>
        </form>
        <div class="admin-sidebar-stack">
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">SCOPE</span>
                <p>Public-facing product copy only. Footer, legal pages, cookie notice, billing plans, and branding use dedicated controllers.</p>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">STORAGE</span>
                <p>Values are serialized into the <code>.env</code> file. No database migrations required for copy changes.</p>
                <div class="stat-pill success">Portable</div>
            </article>
        </div>
    </div>
</section>
