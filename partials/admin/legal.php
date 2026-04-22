<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">LEGAL</span>
            <h2>Public legal pages and footer content</h2>
        </div>
        <p>Edit the pages and footer links visitors see across the site.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" class="admin-form-shell">
            <input type="hidden" name="action" value="save_legal_settings">
            <?= csrf_input('admin'); ?>
            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Footer</h3>
                    <p>Control the public footer text and the link groups shown across the site.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Footer tagline</span>
                        <textarea name="footer_tagline" rows="4"><?= e($legalSettings['footer_tagline']); ?></textarea>
                    </label>
                    <label>
                        <span>Support copy</span>
                        <textarea name="footer_support_copy" rows="4"><?= e($legalSettings['footer_support_copy']); ?></textarea>
                    </label>
                    <label>
                        <span>Useful links title</span>
                        <input type="text" name="footer_useful_title" value="<?= e($legalSettings['footer_useful_title']); ?>">
                    </label>
                    <label>
                        <span>Legal links title</span>
                        <input type="text" name="footer_legal_title" value="<?= e($legalSettings['footer_legal_title']); ?>">
                    </label>
                    <label>
                        <span>Support title</span>
                        <input type="text" name="footer_support_title" value="<?= e($legalSettings['footer_support_title']); ?>">
                    </label>
                    <div class="admin-empty-slot"></div>
                    <label>
                        <span>Useful link 1 label</span>
                        <input type="text" name="footer_useful_link_1_label" value="<?= e($legalSettings['footer_useful_link_1_label']); ?>">
                    </label>
                    <label>
                        <span>Useful link 1 URL</span>
                        <input type="text" name="footer_useful_link_1_url" value="<?= e($legalSettings['footer_useful_link_1_url']); ?>">
                    </label>
                    <label>
                        <span>Useful link 2 label</span>
                        <input type="text" name="footer_useful_link_2_label" value="<?= e($legalSettings['footer_useful_link_2_label']); ?>">
                    </label>
                    <label>
                        <span>Useful link 2 URL</span>
                        <input type="text" name="footer_useful_link_2_url" value="<?= e($legalSettings['footer_useful_link_2_url']); ?>">
                    </label>
                    <label>
                        <span>Useful link 3 label</span>
                        <input type="text" name="footer_useful_link_3_label" value="<?= e($legalSettings['footer_useful_link_3_label']); ?>">
                    </label>
                    <label>
                        <span>Useful link 3 URL</span>
                        <input type="text" name="footer_useful_link_3_url" value="<?= e($legalSettings['footer_useful_link_3_url']); ?>">
                    </label>
                    <label>
                        <span>Legal link 1 label</span>
                        <input type="text" name="footer_legal_link_1_label" value="<?= e($legalSettings['footer_legal_link_1_label']); ?>">
                    </label>
                    <label>
                        <span>Legal link 1 URL</span>
                        <input type="text" name="footer_legal_link_1_url" value="<?= e($legalSettings['footer_legal_link_1_url']); ?>">
                    </label>
                    <label>
                        <span>Legal link 2 label</span>
                        <input type="text" name="footer_legal_link_2_label" value="<?= e($legalSettings['footer_legal_link_2_label']); ?>">
                    </label>
                    <label>
                        <span>Legal link 2 URL</span>
                        <input type="text" name="footer_legal_link_2_url" value="<?= e($legalSettings['footer_legal_link_2_url']); ?>">
                    </label>
                    <label>
                        <span>Legal link 3 label</span>
                        <input type="text" name="footer_legal_link_3_label" value="<?= e($legalSettings['footer_legal_link_3_label']); ?>">
                    </label>
                    <label>
                        <span>Legal link 3 URL</span>
                        <input type="text" name="footer_legal_link_3_url" value="<?= e($legalSettings['footer_legal_link_3_url']); ?>">
                    </label>
                    <label>
                        <span>Legal link 4 label</span>
                        <input type="text" name="footer_legal_link_4_label" value="<?= e($legalSettings['footer_legal_link_4_label']); ?>">
                    </label>
                    <label>
                        <span>Legal link 4 URL</span>
                        <input type="text" name="footer_legal_link_4_url" value="<?= e($legalSettings['footer_legal_link_4_url']); ?>">
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Rules page</h3>
                    <p>Keep the rules page clear and easy to understand for visitors.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Nav label</span>
                        <input type="text" name="rules_nav_label" value="<?= e($legalSettings['rules_nav_label']); ?>">
                    </label>
                    <label>
                        <span>Kicker</span>
                        <input type="text" name="rules_kicker" value="<?= e($legalSettings['rules_kicker']); ?>">
                    </label>
                    <label>
                        <span>Page title</span>
                        <input type="text" name="rules_title" value="<?= e($legalSettings['rules_title']); ?>">
                    </label>
                    <label>
                        <span>Page intro</span>
                        <textarea name="rules_intro" rows="4"><?= e($legalSettings['rules_intro']); ?></textarea>
                    </label>
                    <label>
                        <span>Card 1 title</span>
                        <input type="text" name="rules_card_1_title" value="<?= e($legalSettings['rules_card_1_title']); ?>">
                    </label>
                    <label>
                        <span>Card 1 text</span>
                        <textarea name="rules_card_1_text" rows="3"><?= e($legalSettings['rules_card_1_text']); ?></textarea>
                    </label>
                    <label>
                        <span>Card 2 title</span>
                        <input type="text" name="rules_card_2_title" value="<?= e($legalSettings['rules_card_2_title']); ?>">
                    </label>
                    <label>
                        <span>Card 2 text</span>
                        <textarea name="rules_card_2_text" rows="3"><?= e($legalSettings['rules_card_2_text']); ?></textarea>
                    </label>
                    <label>
                        <span>Card 3 title</span>
                        <input type="text" name="rules_card_3_title" value="<?= e($legalSettings['rules_card_3_title']); ?>">
                    </label>
                    <label>
                        <span>Card 3 text</span>
                        <textarea name="rules_card_3_text" rows="3"><?= e($legalSettings['rules_card_3_text']); ?></textarea>
                    </label>
                    <label>
                        <span>Card 4 title</span>
                        <input type="text" name="rules_card_4_title" value="<?= e($legalSettings['rules_card_4_title']); ?>">
                    </label>
                    <label>
                        <span>Card 4 text</span>
                        <textarea name="rules_card_4_text" rows="3"><?= e($legalSettings['rules_card_4_text']); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Terms page</h3>
                    <p>Write this in plain language for visitors.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Kicker</span>
                        <input type="text" name="terms_kicker" value="<?= e($legalSettings['terms_kicker']); ?>">
                    </label>
                    <label>
                        <span>Page title</span>
                        <input type="text" name="terms_title" value="<?= e($legalSettings['terms_title']); ?>">
                    </label>
                    <label>
                        <span>Page intro</span>
                        <textarea name="terms_intro" rows="4"><?= e($legalSettings['terms_intro']); ?></textarea>
                    </label>
                    <label>
                        <span>Content</span>
                        <textarea name="terms_content" rows="10"><?= e($legalSettings['terms_content']); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Privacy page</h3>
                    <p>Explain privacy in simple language visitors can understand.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Kicker</span>
                        <input type="text" name="privacy_kicker" value="<?= e($legalSettings['privacy_kicker']); ?>">
                    </label>
                    <label>
                        <span>Page title</span>
                        <input type="text" name="privacy_title" value="<?= e($legalSettings['privacy_title']); ?>">
                    </label>
                    <label>
                        <span>Page intro</span>
                        <textarea name="privacy_intro" rows="4"><?= e($legalSettings['privacy_intro']); ?></textarea>
                    </label>
                    <label>
                        <span>Content</span>
                        <textarea name="privacy_content" rows="10"><?= e($legalSettings['privacy_content']); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Cookies page and banner</h3>
                    <p>Control both the cookie page and the banner shown on public pages.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Kicker</span>
                        <input type="text" name="cookies_kicker" value="<?= e($legalSettings['cookies_kicker']); ?>">
                    </label>
                    <label>
                        <span>Page title</span>
                        <input type="text" name="cookies_title" value="<?= e($legalSettings['cookies_title']); ?>">
                    </label>
                    <label>
                        <span>Page intro</span>
                        <textarea name="cookies_intro" rows="4"><?= e($legalSettings['cookies_intro']); ?></textarea>
                    </label>
                    <label>
                        <span>Content</span>
                        <textarea name="cookies_content" rows="10"><?= e($legalSettings['cookies_content']); ?></textarea>
                    </label>
                    <label class="checkbox-line">
                        <input type="checkbox" name="cookie_notice_enabled" value="1" <?= $legalSettings['cookie_notice_enabled'] === '1' ? 'checked' : ''; ?>>
                        <span>Show the cookie notice banner on public pages.</span>
                    </label>
                    <div class="admin-empty-slot"></div>
                    <label>
                        <span>Notice title</span>
                        <input type="text" name="cookie_notice_title" value="<?= e($legalSettings['cookie_notice_title']); ?>">
                    </label>
                    <label>
                        <span>Notice text</span>
                        <textarea name="cookie_notice_text" rows="4"><?= e($legalSettings['cookie_notice_text']); ?></textarea>
                    </label>
                    <label>
                        <span>Accept button label</span>
                        <input type="text" name="cookie_notice_accept_label" value="<?= e($legalSettings['cookie_notice_accept_label']); ?>">
                    </label>
                    <label>
                        <span>Policy link label</span>
                        <input type="text" name="cookie_notice_link_label" value="<?= e($legalSettings['cookie_notice_link_label']); ?>">
                    </label>
                    <label>
                        <span>Policy link URL</span>
                        <input type="text" name="cookie_notice_link_url" value="<?= e($legalSettings['cookie_notice_link_url']); ?>">
                    </label>
                </div>
            </section>

            <button class="button" type="submit">Save legal pages and footer</button>
        </form>
        <div class="admin-sidebar-stack">
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">DISTRIBUTION</span>
                <p>Footer copy, policies, and cookie notice assets are served globally via the site configuration payload.</p>
                <div class="stat-pill success">Internal</div>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">PUBLIC PREVIEWS</span>
                <div class="stack stack--small">
                    <p><a class="text-link" href="<?= e(base_url('rules.php')); ?>" target="_blank" rel="noreferrer">Open rules page</a></p>
                    <p><a class="text-link" href="<?= e(base_url('terms.php')); ?>" target="_blank" rel="noreferrer">Open terms page</a></p>
                    <p><a class="text-link" href="<?= e(base_url('privacy.php')); ?>" target="_blank" rel="noreferrer">Open privacy page</a></p>
                    <p><a class="text-link" href="<?= e(base_url('cookies.php')); ?>" target="_blank" rel="noreferrer">Open cookie page</a></p>
                </div>
            </article>
        </div>
    </div>
</section>
