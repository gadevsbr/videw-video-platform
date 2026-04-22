<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">BILLING</span>
            <h2>Premium plan and payments</h2>
        </div>
        <p>Set the Premium offer, connect payments, and control member access.</p>
    </div>
    <div class="admin-screen-grid">
        <form method="post" class="admin-form-shell">
            <input type="hidden" name="action" value="save_billing_settings">
            <?= csrf_input('admin'); ?>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Payment connection</h3>
                    <p>Add your payment keys here. Leave secret fields empty if you want to keep the current values.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Secret key</span>
                        <input type="password" name="stripe_secret_key" value="" placeholder="<?= trim((string) $billingSettings['stripe_secret_key']) !== '' ? 'Leave blank to keep current secret key' : 'sk_live_...'; ?>" autocomplete="new-password">
                    </label>
                    <label>
                        <span>Public key</span>
                        <input type="text" name="stripe_publishable_key" value="<?= e($billingSettings['stripe_publishable_key']); ?>" placeholder="pk_live_...">
                    </label>
                    <label>
                        <span>Event signing secret</span>
                        <input type="password" name="stripe_webhook_secret" value="" placeholder="<?= trim((string) $billingSettings['stripe_webhook_secret']) !== '' ? 'Leave blank to keep the current signing secret' : 'whsec_...'; ?>" autocomplete="new-password">
                    </label>
                    <label>
                        <span>Premium price ID</span>
                        <input type="text" name="premium_price_id" value="<?= e($billingSettings['premium_price_id']); ?>" placeholder="price_...">
                    </label>
                </div>
            </section>

            <section class="admin-form-section">
                <div class="admin-form-section__header">
                    <h3>Public plan copy</h3>
                    <p>This text appears on the public premium page and the account screen.</p>
                </div>
                <div class="admin-fields admin-fields--two">
                    <label>
                        <span>Plan name</span>
                        <input type="text" name="premium_plan_name" value="<?= e($billingSettings['premium_plan_name']); ?>" placeholder="Premium">
                    </label>
                    <label>
                        <span>Price label</span>
                        <input type="text" name="premium_price_label" value="<?= e($billingSettings['premium_price_label']); ?>" placeholder="$9.99 / month">
                    </label>
                    <label>
                        <span>Plan copy</span>
                        <textarea name="premium_plan_copy" rows="5" placeholder="Unlock the full catalog with an active subscription."><?= e($billingSettings['premium_plan_copy']); ?></textarea>
                    </label>
                    <div class="compliance-card glass">
                        <span class="eyebrow eyebrow--small">DISTRIBUTION SPLIT</span>
                        <div class="stack stack--small">
                            <p><strong>Free catalog:</strong> <span class="stat-pill success"><?= e((string) $freeVideoCount); ?></span></p>
                            <p><strong>Premium catalog:</strong> <span class="stat-pill accent"><?= e((string) $premiumVideoCount); ?></span></p>
                            <p><strong>Active members:</strong> <span class="stat-pill accent"><?= e((string) ($userStats['premium'] ?? 0)); ?></span></p>
                        </div>
                    </div>
                </div>
            </section>

            <button class="button" type="submit">Save payment settings</button>
        </form>

        <div class="admin-sidebar-stack">
            <article class="admin-guide">
                <div class="admin-guide__header">
                    <span class="eyebrow">PREMIUM FLOW</span>
                    <h3>How Premium works</h3>
                    <p>This is the path your members follow from free access to Premium.</p>
                </div>
                <div class="admin-steps">
                    <article class="admin-step">
                        <strong>1. Create the plan</strong>
                        <p>Create the recurring Premium plan in Stripe, then paste the price ID here.</p>
                    </article>
                    <article class="admin-step">
                        <strong>2. Save your keys</strong>
                        <p>Add your payment keys and plan copy on this screen.</p>
                    </article>
                    <article class="admin-step">
                        <strong>3. Connect automatic updates</strong>
                        <p>Add the event URL below in Stripe so successful payments, renewals, and cancellations update member access automatically.</p>
                    </article>
                    <article class="admin-step">
                        <strong>4. Gate playback</strong>
                        <p>Videos marked Premium will play only for signed-in members with an active Premium plan.</p>
                    </article>
                </div>
            </article>

            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">CONNECTION STATUS</span>
                <div class="stack stack--small">
                    <p><strong>Payments:</strong> <span class="stat-pill <?= $billingConfigured ? 'success' : 'danger'; ?>"><?= $billingConfigured ? 'Configured' : 'Missing keys'; ?></span></p>
                    <p><strong>Webhooks:</strong> <span class="stat-pill <?= $webhookConfigured ? 'success' : 'danger'; ?>"><?= $webhookConfigured ? 'Ready' : 'Pending'; ?></span></p>
                    <p><a class="text-link" href="<?= e(base_url('premium.php')); ?>" target="_blank" rel="noreferrer">Open public premium page</a></p>
                </div>
            </article>

            <article class="compliance-card">
                <h3>Webhook diagnostics</h3>
                <p><strong>Latest status:</strong> <?= e($latestWebhookStatus !== '' ? ucfirst($latestWebhookStatus) : 'No events yet'); ?></p>
                <p><strong>Latest event:</strong> <?= e($latestWebhookType !== '' ? $latestWebhookType : 'Not available'); ?></p>
                <p><strong>Updated:</strong> <?= e($latestWebhookUpdatedAt); ?></p>
                <p><strong>Processed:</strong> <?= e((string) ($webhookSnapshot['processed'] ?? 0)); ?> / <strong>Failed:</strong> <?= e((string) ($webhookSnapshot['failed'] ?? 0)); ?></p>
                <p><strong>Duplicates ignored:</strong> <?= e((string) ($webhookSnapshot['duplicates'] ?? 0)); ?></p>
                <?php if ($latestWebhookError !== ''): ?>
                    <p class="form-note"><?= e($latestWebhookError); ?></p>
                <?php endif; ?>
            </article>

            <article class="compliance-card">
                <h3>Failed webhook queue</h3>
                <?php if ($failedWebhookEvents !== []): ?>
                    <?php foreach ($failedWebhookEvents as $event): ?>
                        <div class="admin-overview-activity__item">
                            <strong><?= e((string) (($event['type'] ?? '') !== '' ? $event['type'] : ($event['event_id'] ?? 'Webhook event'))); ?></strong>
                            <span>
                                <?= e((string) ($event['event_id'] ?? '')); ?>
                                <?php if ((string) ($event['updated_at'] ?? '') !== ''): ?>
                                    | Updated <?= e(format_datetime((string) $event['updated_at'], 'Not available')); ?>
                                <?php endif; ?>
                            </span>
                            <span>
                                Failures <?= e((string) ((int) ($event['failure_count'] ?? 0))); ?>
                                | Retries <?= e((string) ((int) ($event['retry_count'] ?? 0))); ?>
                            </span>
                            <?php if ((string) ($event['last_error'] ?? '') !== ''): ?>
                                <span><?= e((string) $event['last_error']); ?></span>
                            <?php endif; ?>
                            <form method="post">
                                <input type="hidden" name="action" value="retry_billing_webhook">
                                <input type="hidden" name="event_id" value="<?= e((string) ($event['event_id'] ?? '')); ?>">
                                <?= csrf_input('admin'); ?>
                                <button class="button button--ghost" type="submit">Retry event</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No failed webhook events are waiting for manual retry.</p>
                <?php endif; ?>
            </article>

            <article class="compliance-card">
                <h3>Recent webhook history</h3>
                <?php if ($recentWebhookEvents !== []): ?>
                    <?php foreach ($recentWebhookEvents as $event): ?>
                        <div class="admin-overview-activity__item">
                            <strong><?= e((string) (($event['type'] ?? '') !== '' ? $event['type'] : ($event['event_id'] ?? 'Webhook event'))); ?></strong>
                            <span>
                                Status <?= e(ucfirst((string) ($event['status'] ?? 'unknown'))); ?>
                                <?php if ((string) ($event['updated_at'] ?? '') !== ''): ?>
                                    | Updated <?= e(format_datetime((string) $event['updated_at'], 'Not available')); ?>
                                <?php endif; ?>
                            </span>
                            <span>
                                Duplicates <?= e((string) ((int) ($event['duplicate_count'] ?? 0))); ?>
                                | Retries <?= e((string) ((int) ($event['retry_count'] ?? 0))); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No webhook history has been recorded yet.</p>
                <?php endif; ?>
            </article>

            <article class="compliance-card">
                <h3>Automatic updates URL</h3>
                <p>Add this URL in Stripe so payments and renewals update accounts automatically:</p>
                <code><?= e($webhookUrl); ?></code>
                <p class="form-note">Use your real site URL here.</p>
            </article>
        </div>
    </div>
</section>
