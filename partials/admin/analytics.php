<section class="catalog-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">ANALYTICS</span>
            <h2>Platform performance</h2>
        </div>
        <p>Track aggregate traffic, highlight the videos pulling the most attention, and see which creators are currently driving views.</p>
    </div>

    <div class="admin-summary-grid">
    <div class="admin-summary-grid">
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Views total</span>
            <strong><?= e((string) ($adminAnalyticsOverview['views_total'] ?? 0)); ?></strong>
            <div class="stat-pill accent">All time</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Trend 7d</span>
            <strong><?= e((string) ($adminAnalyticsOverview['views_7d'] ?? 0)); ?></strong>
            <div class="stat-pill success">Rising</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Trend 30d</span>
            <strong><?= e((string) ($adminAnalyticsOverview['views_30d'] ?? 0)); ?></strong>
            <div class="stat-pill accent">Monthly</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Unique 30d</span>
            <strong><?= e((string) ($adminAnalyticsOverview['unique_viewers_30d'] ?? 0)); ?></strong>
            <div class="stat-pill muted">Audience</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Videos active</span>
            <strong><?= e((string) ($adminAnalyticsOverview['active_videos_30d'] ?? 0)); ?></strong>
            <div class="stat-pill success">Live</div>
        </article>
        <article class="mini-stat">
            <span class="eyebrow eyebrow--small">Creators active</span>
            <strong><?= e((string) ($adminAnalyticsOverview['active_creators_30d'] ?? 0)); ?></strong>
            <div class="stat-pill accent">Partners</div>
        </article>
    </div>
    </div>

    <div class="studio-analytics-grid">
        <article class="compliance-card">
            <h3>Last 14 days</h3>
            <?php if ($adminAnalyticsSeries !== []): ?>
                <div class="creator-trend">
                    <?php foreach ($adminAnalyticsSeries as $point): ?>
                        <div class="creator-trend__row">
                            <span><?= e((string) $point['label']); ?></span>
                            <div class="creator-trend__bar">
                                <i style="width: <?= e((string) max(6, (int) round(((int) $point['views'] / $adminAnalyticsMaxTrendViews) * 100))); ?>%;"></i>
                            </div>
                            <strong><?= e((string) $point['views']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="form-note">Traffic will appear here after public videos start getting views.</p>
            <?php endif; ?>
        </article>

        <article class="compliance-card">
            <h3>Top videos</h3>
            <?php if ($adminAnalyticsTopVideos !== []): ?>
                <div class="creator-analytics-list">
                    <?php foreach ($adminAnalyticsTopVideos as $video): ?>
                        <div class="creator-analytics-list__row">
                            <div>
                                <strong><?= e((string) $video['title']); ?></strong>
                                <p>
                                    <?= e((string) (($video['creator_name'] ?? '') !== '' ? $video['creator_name'] : 'Unknown creator')); ?>
                                    / <?= e((string) $video['moderation_label']); ?>
                                    / <?= e((string) $video['access_label']); ?>
                                </p>
                            </div>
                            <div class="creator-analytics-list__stats">
                                <span><?= e((string) $video['total_views']); ?> total</span>
                                <span><?= e((string) $video['views_30d']); ?> / 30d</span>
                                <span><?= e((string) $video['last_view_label']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="form-note">Top-video rankings will appear here after public playback starts generating views.</p>
            <?php endif; ?>
        </article>
    </div>

    <div class="admin-screen-grid">
        <article class="compliance-card">
            <h3>Top creators</h3>
            <?php if ($adminAnalyticsTopCreators !== []): ?>
                <div class="creator-analytics-list">
                    <?php foreach ($adminAnalyticsTopCreators as $creator): ?>
                        <div class="creator-analytics-list__row">
                            <div>
                                <strong><?= e((string) $creator['name']); ?></strong>
                                <p><?= e((string) $creator['videos_with_views']); ?> videos with recorded views</p>
                                <?php if ((string) ($creator['channel_url'] ?? '') !== ''): ?>
                                    <a class="text-link" href="<?= e((string) $creator['channel_url']); ?>" target="_blank" rel="noreferrer">Open channel</a>
                                <?php endif; ?>
                            </div>
                            <div class="creator-analytics-list__stats">
                                <span><?= e((string) $creator['total_views']); ?> total</span>
                                <span><?= e((string) $creator['views_30d']); ?> / 30d</span>
                                <span><?= e((string) $creator['last_view_label']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="form-note">Creator rankings will appear here after the platform records channel traffic.</p>
            <?php endif; ?>
        </article>

        <div class="admin-sidebar-stack">
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">METRICS SCOPE</span>
                <p>Data derived from <code>video_views</code> (first-party traffic). Retention and attribution tracking are currently scheduled for the v1.1.x roadmap.</p>
            </article>
            <article class="compliance-card glass">
                <span class="eyebrow eyebrow--small">OPTIMIZATION</span>
                <p>Add per-video drilldown and acquisition segmentation as the audience data model expands.</p>
                <div class="stat-pill success">Ready</div>
            </article>
        </div>
    </div>
</section>
