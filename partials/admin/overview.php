<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">OVERVIEW</span>
            <h2>Platform KPIs</h2>
        </div>
        <p>Start here for catalog, member, billing, and operations visibility.</p>
    </div>
    <div class="admin-kpi-grid">
        <article class="mini-stat admin-kpi-card">
            <span class="eyebrow eyebrow--small">Videos</span>
            <strong><?= e((string) $adminStats['total']); ?></strong>
            <div class="stat-pill success"><?= e((string) $adminStats['approved']); ?> live</div>
            <div class="sparkline-container">
                <svg viewBox="0 0 100 32"><path class="sparkline-path" d="M0 20 L10 15 L20 25 L30 10 L40 18 L50 12 L60 22 L70 8 L80 15 L90 25 L100 10"></path></svg>
            </div>
        </article>
        <article class="mini-stat admin-kpi-card">
            <span class="eyebrow eyebrow--small">Draft queue</span>
            <strong><?= e((string) $adminStats['draft']); ?></strong>
            <div class="stat-pill <?= (int) $adminStats['draft'] > 0 ? 'warning' : 'muted'; ?>">Waiting review</div>
            <div class="sparkline-container">
                <svg viewBox="0 0 100 32"><path class="sparkline-path" d="M0 25 L15 20 L30 22 L45 15 L60 18 L75 10 L90 12 L100 5"></path></svg>
            </div>
        </article>
        <article class="mini-stat admin-kpi-card">
            <span class="eyebrow eyebrow--small">Creators</span>
            <strong><?= e((string) $userStats['creators']); ?></strong>
            <div class="stat-pill <?= (int) $creatorRequestStats['pending'] > 0 ? 'accent' : 'muted'; ?>"><?= e((string) $creatorRequestStats['pending']); ?> pending</div>
            <div class="sparkline-container">
                <svg viewBox="0 0 100 32"><path class="sparkline-path" d="M0 15 L20 18 L40 12 L60 15 L80 10 L100 8"></path></svg>
            </div>
        </article>
        <article class="mini-stat admin-kpi-card">
            <span class="eyebrow eyebrow--small">Members</span>
            <strong><?= e((string) $userStats['users']); ?></strong>
            <div class="stat-pill accent"><?= e((string) ($userStats['premium'] ?? 0)); ?> premium</div>
            <div class="sparkline-container">
                <svg viewBox="0 0 100 32"><path class="sparkline-path" d="M0 28 L10 25 L20 22 L30 24 L40 20 L50 18 L60 15 L70 12 L80 10 L90 8 L100 5"></path></svg>
            </div>
        </article>
    </div>
</section>

<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">OPERATIONS</span>
            <h2>Health, billing, and pending work</h2>
        </div>
        <p>Use the dashboard to verify service readiness, revenue setup, and what still needs attention.</p>
    </div>
    <div class="admin-overview-shell">
        <div class="admin-overview-main">
            <article class="admin-overview-panel">
                <div class="admin-overview-panel__header">
                    <div>
                        <span class="eyebrow">HEALTH</span>
                        <h3>Platform status</h3>
                    </div>
                    <a class="text-link" href="<?= e($screenUrl('activity')); ?>">Open activity</a>
                </div>
                <div class="admin-health-grid">
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Internal API</span>
                        <strong><?= $internalApiHealthy ? 'Optimal' : 'Attention'; ?></strong>
                        <div class="stat-pill <?= $internalApiHealthy ? 'success' : 'danger'; ?>"><?= $internalApiHealthy ? 'Connected' : 'Offline'; ?></div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Storage</span>
                        <strong><?= e($wasabiEnabled ? 'Wasabi' : 'Local'); ?></strong>
                        <div class="stat-pill success">Available</div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Wasabi delivery</span>
                        <strong><?= $privateDelivery ? 'Signed' : 'Public'; ?></strong>
                        <div class="stat-pill <?= $wasabiBucket !== '' ? 'success' : 'warning'; ?>"><?= $wasabiBucket !== '' ? 'Ready' : 'Incomplete'; ?></div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Stripe</span>
                        <strong><?= $billingConfigured ? 'Ready' : 'Pending'; ?></strong>
                        <div class="stat-pill <?= $webhookConfigured ? 'success' : 'warning'; ?>"><?= $webhookConfigured ? 'Live' : 'Config req.'; ?></div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Database schema</span>
                        <strong><?= e($databaseSchemaLabel); ?></strong>
                        <div class="stat-pill <?= (string) ($databaseVersionStatus['type'] ?? '') === 'success' ? 'success' : 'warning'; ?>">Version <?= e((string) ($databaseVersionStatus['version'] ?? 'unknown')); ?></div>
                    </article>
                </div>
            </article>

            <article class="admin-overview-panel">
                <div class="admin-overview-panel__header">
                    <div>
                        <span class="eyebrow">BILLING</span>
                        <h3>Revenue setup snapshot</h3>
                    </div>
                    <a class="text-link" href="<?= e($screenUrl('billing')); ?>">Open billing</a>
                </div>
                <div class="admin-billing-grid">
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Plan</span>
                        <strong><?= e((string) $billingSettings['premium_plan_name']); ?></strong>
                        <div class="stat-pill accent"><?= e((string) $billingSettings['premium_price_label']); ?></div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Premium members</span>
                        <strong><?= e((string) ($userStats['premium'] ?? 0)); ?></strong>
                        <div class="stat-pill accent">Active</div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Webhook</span>
                        <strong><?= $webhookConfigured ? 'Connected' : 'Pending'; ?></strong>
                        <div class="stat-pill <?= $webhookConfigured ? 'success' : 'danger'; ?>"><?= $webhookConfigured ? 'Healthy' : 'Needs setup'; ?></div>
                    </article>
                    <article class="mini-stat admin-health-card">
                        <span class="eyebrow eyebrow--small">Premium catalog</span>
                        <strong><?= e((string) $premiumVideoCount); ?></strong>
                        <div class="stat-pill muted"><?= e((string) $freeVideoCount); ?> free titles</div>
                    </article>
                </div>
            </article>
        </div>

        <aside class="admin-overview-side">
            <article class="admin-overview-panel">
                <div class="admin-overview-panel__header">
                    <div>
                        <span class="eyebrow">PENDING</span>
                        <h3>Pending items</h3>
                    </div>
                </div>
                <div class="admin-overview-activity">
                    <a class="admin-overview-activity__item" href="<?= e($screenUrl('moderation')); ?>">
                        <strong>Review moderation queue</strong>
                        <span><?= e((string) $adminStats['draft']); ?> drafts currently need a decision.</span>
                    </a>
                    <a class="admin-overview-activity__item" href="<?= e($screenUrl('creator_requests')); ?>">
                        <strong>Review creator applications</strong>
                        <span><?= e((string) $creatorRequestStats['pending']); ?> creator requests are waiting.</span>
                    </a>
                    <a class="admin-overview-activity__item" href="<?= e($screenUrl('billing')); ?>">
                        <strong><?= $webhookConfigured ? 'Review billing setup' : 'Finish Stripe setup'; ?></strong>
                        <span><?= $webhookConfigured ? 'Checkout is configured. Review pricing and account health.' : 'Add the webhook and keys to finish paid access setup.'; ?></span>
                    </a>
                    <a class="admin-overview-activity__item" href="<?= e($screenUrl('ads')); ?>">
                        <strong><?= (int) ($adStats['active'] ?? 0) > 0 ? 'Review active ads' : 'Set up ad slots'; ?></strong>
                        <span><?= (int) ($adStats['active'] ?? 0) > 0 ? e((string) ($adStats['active'] ?? 0)) . ' ad slots are currently active.' : 'No ad slots are active yet.'; ?></span>
                    </a>
                    <a class="admin-overview-activity__item" href="<?= e($screenUrl('storage')); ?>">
                        <strong><?= $wasabiEnabled ? 'Check storage delivery' : 'Storage uses local uploads'; ?></strong>
                        <span><?= e($storageHealthLabel); ?></span>
                    </a>
                </div>
            </article>

            <article class="admin-overview-panel">
                <div class="admin-overview-panel__header">
                    <div>
                        <span class="eyebrow">UPDATES</span>
                        <h3>Release monitor</h3>
                    </div>
                    <a class="text-link" href="<?= e($screenUrl('settings')); ?>">Open settings</a>
                </div>
                <div class="admin-overview-activity">
                    <article class="admin-overview-activity__item">
                        <strong><?= e($releaseStatusTitle); ?></strong>
                        <span><?= e($releaseStatusDetail); ?></span>
                    </article>
                    <article class="admin-overview-activity__item">
                        <strong><?= e((string) ($releaseStatus['release_name'] !== '' ? $releaseStatus['release_name'] : (($releaseStatus['latest_version'] ?? '') !== '' ? 'Latest release ' . $releaseStatus['latest_version'] : 'GitHub release'))); ?></strong>
                        <span>
                            Repo <?= e((string) ($releaseStatus['repository'] !== '' ? $releaseStatus['repository'] : 'not configured')); ?>
                            <?php if ((string) ($releaseStatus['latest_version'] ?? '') !== ''): ?>
                                | Published <?= e($releasePublishedLabel); ?>
                            <?php endif; ?>
                            | Checked <?= e($releaseCheckedLabel); ?>
                        </span>
                        <?php if ((string) ($releaseStatus['summary'] ?? '') !== ''): ?>
                            <span><?= e((string) $releaseStatus['summary']); ?></span>
                        <?php endif; ?>
                        <?php if ((string) ($releaseStatus['release_url'] ?? '') !== ''): ?>
                            <a class="text-link" href="<?= e((string) $releaseStatus['release_url']); ?>" target="_blank" rel="noreferrer">Open release</a>
                        <?php endif; ?>
                    </article>
                </div>
            </article>

            <article class="admin-overview-panel">
                <div class="admin-overview-panel__header">
                    <div>
                        <span class="eyebrow">ACTIVITY</span>
                        <h3>Latest actions</h3>
                    </div>
                    <a class="text-link" href="<?= e($screenUrl('activity')); ?>">Open full log</a>
                </div>
                <div class="admin-overview-activity">
                    <?php foreach ($recentAdminActivity as $item): ?>
                        <article class="admin-overview-activity__item">
                            <strong><?= e((string) ($item['summary'] ?? 'Activity')); ?></strong>
                            <span><?= e((string) (($item['actor_name'] ?? '') !== '' ? $item['actor_name'] : ($item['actor_email'] ?? 'System'))); ?> • <?= e((string) ($item['created_at'] ?? '')); ?></span>
                        </article>
                    <?php endforeach; ?>
                    <?php if ($recentAdminActivity === []): ?>
                        <article class="admin-overview-activity__item">
                            <strong>No recent activity yet</strong>
                            <span>Admin actions will appear here as soon as the workspace is used.</span>
                        </article>
                    <?php endif; ?>
                </div>
            </article>
        </aside>
    </div>
</section>
