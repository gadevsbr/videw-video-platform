            </div>
        </div>
    </main>

    <div id="age-gate-root"></div>

    <script<?= nonce_attr(); ?>>
        window.__VIDEW__ = <?= page_bootstrap(default_bootstrap_payload('admin')); ?>;
    </script>
    <?= gui_runtime_tags(); ?>
</body>
</html>
