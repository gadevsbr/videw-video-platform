<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">ADS</span>
            <h2>Sponsored placements</h2>
        </div>
        <p>Manage image, script, and text ads across the public site. Premium members never see these placements.</p>
    </div>
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Total slots</span>
            <strong><?= e((string) ($adStats['slots'] ?? count($adSlots))); ?></strong>
            <div class="stat-pill muted">Inventory</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Configured</span>
            <strong><?= e((string) ($adStats['configured'] ?? 0)); ?></strong>
            <div class="stat-pill accent">Assigned</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Active</span>
            <strong><?= e((string) ($adStats['active'] ?? 0)); ?></strong>
            <div class="stat-pill success">Live</div>
        </article>
    </div>
    <div class="copy-editor-controls" data-ad-slot-browser>
        <label class="copy-editor-controls__label">
            <span>Ad slot</span>
            <select data-ad-slot-selector>
                <?php foreach ($adSlots as $slotKey => $slotDefinition): ?>
                    <option value="<?= e($slotKey); ?>" <?= $activeAdSlot === $slotKey ? 'selected' : ''; ?>><?= e((string) $slotDefinition['title']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="copy-editor-controls__summary" data-ad-slot-summary>
            <strong><?= e((string) (($activeAdSlot !== '' && isset($adSlots[$activeAdSlot]['title'])) ? $adSlots[$activeAdSlot]['title'] : 'Ad slot')); ?></strong>
            <p><?= e((string) (($activeAdSlot !== '' && isset($adSlots[$activeAdSlot]['description'])) ? $adSlots[$activeAdSlot]['description'] : 'Manage one ad slot at a time.')); ?></p>
        </div>
    </div>
    <div class="admin-ads-grid">
        <?php foreach ($adSlots as $slotKey => $slotDefinition): ?>
            <?php
            $slotAd = $adsBySlot[$slotKey] ?? null;
            $slotType = (string) ($slotAd['ad_type'] ?? 'placeholder');
            $slotTypes = is_array($slotDefinition['types'] ?? null) ? $slotDefinition['types'] : ['placeholder', 'image', 'script', 'text'];
            $slotPreviewUrl = is_array($slotAd)
                ? resolve_ad_media_asset(
                    $slotKey,
                    isset($slotAd['image_url']) ? (string) $slotAd['image_url'] : null,
                    isset($slotAd['image_path']) ? (string) $slotAd['image_path'] : null,
                    isset($slotAd['image_storage_provider']) ? (string) $slotAd['image_storage_provider'] : null
                )
                : '';
            $slotPreviewVideoUrl = is_array($slotAd)
                ? resolve_ad_video_asset(
                    $slotKey,
                    isset($slotAd['video_url']) ? (string) $slotAd['video_url'] : null,
                    isset($slotAd['video_path']) ? (string) $slotAd['video_path'] : null,
                    isset($slotAd['video_storage_provider']) ? (string) $slotAd['video_storage_provider'] : null
                )
                : '';
            ?>
            <article class="admin-ad-card" data-ad-slot-panel="<?= e($slotKey); ?>" data-ad-slot-title="<?= e((string) $slotDefinition['title']); ?>" data-ad-slot-description="<?= e((string) $slotDefinition['description'] . ' ' . $slotDefinition['placeholder_text']); ?>"<?= $slotKey === $activeAdSlot ? '' : ' hidden'; ?> style="<?= $slotKey === $activeAdSlot ? '' : 'display:none;'; ?>">
                <div class="admin-ad-card__preview">
                    <?php if ($slotType === 'image' && $slotPreviewUrl !== ''): ?>
                        <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--admin-preview">
                            <span class="ad-slot__eyebrow">Sponsored</span>
                            <div class="ad-slot__media">
                                <img src="<?= e($slotPreviewUrl); ?>" alt="<?= e((string) ($slotAd['title'] ?? $slotDefinition['title'])); ?>">
                            </div>
                        </div>
                    <?php elseif ($slotType === 'text' && is_array($slotAd) && ((string) ($slotAd['title'] ?? '') !== '' || (string) ($slotAd['body_text'] ?? '') !== '')): ?>
                        <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--text ad-slot--admin-preview">
                            <span class="ad-slot__eyebrow">Sponsored</span>
                            <strong><?= e((string) ($slotAd['title'] ?? $slotDefinition['placeholder_title'])); ?></strong>
                            <p><?= e((string) ($slotAd['body_text'] ?? 'Text ad preview')); ?></p>
                        </div>
                    <?php elseif ($slotType === 'script' && is_array($slotAd) && trim((string) ($slotAd['script_code'] ?? '')) !== ''): ?>
                        <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--placeholder ad-slot--admin-preview">
                            <span class="ad-slot__eyebrow">Script ad</span>
                            <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                            <p><?= e(strlen((string) $slotAd['script_code']) . ' characters saved. Script ads run only on public pages.'); ?></p>
                        </div>
                    <?php elseif ($slotType === 'video' && is_array($slotAd) && $slotPreviewVideoUrl !== ''): ?>
                        <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--video ad-slot--admin-preview">
                            <span class="ad-slot__eyebrow">Video pre-roll</span>
                            <div class="ad-slot__media">
                                <video src="<?= e($slotPreviewVideoUrl); ?>" muted playsinline preload="metadata"></video>
                            </div>
                            <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                            <p><?= e('Skip after ' . max(0, (int) ($slotAd['skip_after_seconds'] ?? 5)) . 's'); ?></p>
                        </div>
                    <?php elseif ($slotType === 'vast' && is_array($slotAd) && trim((string) ($slotAd['vast_tag_url'] ?? '')) !== ''): ?>
                        <div class="ad-slot ad-slot--<?= e((string) $slotDefinition['shape']); ?> ad-slot--placeholder ad-slot--admin-preview">
                            <span class="ad-slot__eyebrow">VAST pre-roll</span>
                            <strong><?= e((string) (($slotAd['title'] ?? '') !== '' ? $slotAd['title'] : $slotDefinition['title'])); ?></strong>
                            <p><?= e('VAST tag saved. Skip after ' . max(0, (int) ($slotAd['skip_after_seconds'] ?? 5)) . 's unless the tag defines its own offset.'); ?></p>
                        </div>
                    <?php else: ?>
                        <?= render_public_ad_slot($slotKey, 'ad-slot--admin-preview'); ?>
                    <?php endif; ?>
                </div>
                <form method="post" enctype="multipart/form-data" class="admin-form-shell admin-ad-card__form" data-ad-editor>
                    <input type="hidden" name="action" value="save_ad_slot">
                    <input type="hidden" name="slot_key" value="<?= e($slotKey); ?>">
                    <input type="hidden" name="return_screen" value="ads">
                    <?= csrf_input('admin'); ?>
                    <section class="admin-form-section">
                        <div class="admin-form-section__header">
                            <h3><?= e((string) $slotDefinition['title']); ?></h3>
                            <p><?= e((string) $slotDefinition['description']); ?> <?= e((string) $slotDefinition['placeholder_text']); ?></p>
                        </div>
                        <div class="admin-fields admin-fields--two">
                            <label class="checkbox-line">
                                <input type="checkbox" name="is_active" value="1" <?= !empty($slotAd['is_active']) ? 'checked' : ''; ?>>
                                <span>Show this ad slot on public pages</span>
                            </label>
                            <label>
                                <span>Ad type</span>
                                <select name="ad_type" data-ad-type-selector>
                                    <?php foreach ($slotTypes as $supportedType): ?>
                                        <?php
                                        $typeLabels = [
                                            'placeholder' => 'Placeholder only',
                                            'image' => 'Image ad',
                                            'script' => 'Script embed',
                                            'text' => 'Text ad',
                                            'video' => 'Video pre-roll',
                                            'vast' => 'VAST pre-roll',
                                        ];
                                        ?>
                                        <option value="<?= e((string) $supportedType); ?>" <?= $slotType === $supportedType ? 'selected' : ''; ?>><?= e($typeLabels[(string) $supportedType] ?? ucfirst((string) $supportedType)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Headline / label</span>
                                <input type="text" name="title" value="<?= e((string) ($slotAd['title'] ?? '')); ?>" placeholder="Optional ad title">
                            </label>
                            <label>
                                <span>Click URL</span>
                                <input type="text" name="click_url" value="<?= e((string) ($slotAd['click_url'] ?? '')); ?>" placeholder="https://partner.example">
                            </label>
                        </div>
                    </section>

                    <section class="admin-form-section" data-ad-type-group="image"<?= $slotType === 'image' ? '' : ' hidden'; ?>>
                        <div class="admin-form-section__header">
                            <h3>Image ad</h3>
                            <p>Upload the creative here. The click URL above will be used when visitors click the image.</p>
                        </div>
                        <div class="admin-fields admin-fields--two">
                            <label>
                                <span>Image upload</span>
                                <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            </label>
                            <label class="checkbox-line">
                                <input type="checkbox" name="remove_image" value="1">
                                <span>Remove the current uploaded image</span>
                            </label>
                        </div>
                    </section>

                    <section class="admin-form-section" data-ad-type-group="video"<?= $slotType === 'video' ? '' : ' hidden'; ?>>
                        <div class="admin-form-section__header">
                            <h3>Video pre-roll</h3>
                            <p>Use an uploaded ad video or a direct HTTPS video file URL. This slot plays before the main video for non-Premium viewers.</p>
                        </div>
                        <div class="admin-fields admin-fields--two">
                            <label>
                                <span>Direct video URL</span>
                                <input type="text" name="video_url" value="<?= e((string) ($slotAd['video_url'] ?? '')); ?>" placeholder="https://cdn.example.com/preroll.mp4">
                            </label>
                            <label>
                                <span>Skip after (seconds)</span>
                                <input type="number" name="skip_after_seconds" min="0" max="30" step="1" value="<?= e((string) ($slotAd['skip_after_seconds'] ?? 5)); ?>">
                            </label>
                            <label>
                                <span>Video upload</span>
                                <input type="file" name="video_file" accept=".mp4,.m4v,.webm,.mov,video/mp4,video/webm,video/quicktime">
                            </label>
                            <label class="checkbox-line">
                                <input type="checkbox" name="remove_video" value="1">
                                <span>Remove the current uploaded video</span>
                            </label>
                        </div>
                    </section>

                    <section class="admin-form-section" data-ad-type-group="vast"<?= $slotType === 'vast' ? '' : ' hidden'; ?>>
                        <div class="admin-form-section__header">
                            <h3>VAST pre-roll</h3>
                            <p>Paste a public HTTPS VAST tag URL. The player resolves the tag on the backend and plays the first supported media file before the video starts.</p>
                        </div>
                        <div class="admin-fields admin-fields--two">
                            <label>
                                <span>VAST tag URL</span>
                                <input type="text" name="vast_tag_url" value="<?= e((string) ($slotAd['vast_tag_url'] ?? '')); ?>" placeholder="https://ads.example.com/vast.xml">
                            </label>
                            <label>
                                <span>Fallback skip after (seconds)</span>
                                <input type="number" name="skip_after_seconds" min="0" max="30" step="1" value="<?= e((string) ($slotAd['skip_after_seconds'] ?? 5)); ?>">
                            </label>
                        </div>
                    </section>

                    <section class="admin-form-section" data-ad-type-group="script"<?= $slotType === 'script' ? '' : ' hidden'; ?>>
                        <div class="admin-form-section__header">
                            <h3>Script ad</h3>
                            <p>Paste the ad network code exactly as required. Premium members still will not see this slot.</p>
                        </div>
                        <label>
                            <span>Script code</span>
                            <textarea name="script_code" rows="8" placeholder="<script async src=&quot;...&quot;></script><?= PHP_EOL; ?><script>/* ad code */</script>"><?= e((string) ($slotAd['script_code'] ?? '')); ?></textarea>
                        </label>
                    </section>

                    <section class="admin-form-section" data-ad-type-group="text"<?= $slotType === 'text' ? '' : ' hidden'; ?>>
                        <div class="admin-form-section__header">
                            <h3>Text ad</h3>
                            <p>Use this for internal promos, affiliate callouts, or sponsor text blocks.</p>
                        </div>
                        <label>
                            <span>Text content</span>
                            <textarea name="body_text" rows="6" placeholder="Simple sponsored message or promo copy."><?= e((string) ($slotAd['body_text'] ?? '')); ?></textarea>
                        </label>
                    </section>

                    <button class="button" type="submit">Save ad slot</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="admin-sidebar-stack">
        <article class="compliance-card glass">
            <span class="eyebrow eyebrow--small">VISIBILITY RULE</span>
            <p>Placements are automatically suppressed for signed-in Premium members. Admins retain visibility for QA purposes.</p>
            <div class="stat-pill accent">Gated</div>
        </article>
        <article class="compliance-card glass">
            <span class="eyebrow eyebrow--small">PRE-ROLL ENGINE</span>
            <p>Pre-roll slots trigger exclusively on watch flows. Uploads utilize a hardware-accelerated overlay; embeds delay iframe hydration.</p>
        </article>
        <article class="compliance-card glass">
            <span class="eyebrow eyebrow--small">SCRIPT SECURITY</span>
            <p>Script ads require appropriate Content Security Policy (CSP) headers in production to function correctly with external networks.</p>
            <div class="stat-pill warning">CSP Alert</div>
        </article>
    </div>
</section>
