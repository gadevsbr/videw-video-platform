<?php

declare(strict_types=1);

$footerGroups = footer_navigation_groups();
$footerSupport = footer_support_panel();
$footerTagline = (string) config('footer.tagline', config('app.description', ''));
?>
<footer class="site-footer">
    <div class="site-footer__grid">
        <section class="site-footer__brand">
            <strong><?= e(config('app.name')); ?></strong>
            <p><?= e($footerTagline); ?></p>
        </section>

        <?php foreach ($footerGroups as $group): ?>
            <?php if (($group['links'] ?? []) === []): ?>
                <?php continue; ?>
            <?php endif; ?>
            <section class="site-footer__column">
                <span><?= e((string) ($group['title'] ?? '')); ?></span>
                <div class="site-footer__links">
                    <?php foreach ((array) ($group['links'] ?? []) as $link): ?>
                        <a class="text-link" href="<?= e((string) ($link['url'] ?? '')); ?>"><?= e((string) ($link['label'] ?? '')); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="site-footer__column">
            <span><?= e((string) ($footerSupport['title'] ?? 'Support')); ?></span>
            <?php if (!empty($footerSupport['copy'])): ?>
                <p><?= e((string) $footerSupport['copy']); ?></p>
            <?php endif; ?>
            <?php if (!empty($footerSupport['email']) && !empty($footerSupport['email_href'])): ?>
                <a class="text-link" href="<?= e((string) $footerSupport['email_href']); ?>"><?= e((string) $footerSupport['email']); ?></a>
            <?php endif; ?>
        </section>
    </div>
</footer>
