<?php

return [
    'app' => [
        'name' => (string) env_value('VIDEW_APP_NAME', 'VIDEW'),
        'description' => (string) env_value('VIDEW_APP_DESCRIPTION', 'Video platform with free and premium access.'),
        'base_url' => rtrim((string) env_value('VIDEW_BASE_URL', ''), '/'),
        'support_email' => (string) env_value('VIDEW_SUPPORT_EMAIL', 'compliance@videw.local'),
        'brand_kicker' => (string) env_value('VIDEW_BRAND_KICKER', 'VIDEW'),
        'brand_title' => (string) env_value('VIDEW_BRAND_TITLE', ''),
        'age_gate_enabled' => env_flag('VIDEW_AGE_GATE_ENABLED', false),
        'exit_url' => (string) env_value('VIDEW_EXIT_URL', 'https://www.google.com'),
        'public_head_scripts' => (string) env_value('VIDEW_PUBLIC_HEAD_SCRIPTS', ''),
    ],
    'db' => [
        'host' => (string) env_value('VIDEW_DB_HOST', '127.0.0.1'),
        'port' => (int) env_value('VIDEW_DB_PORT', '3306'),
        'database' => (string) env_value('VIDEW_DB_DATABASE', 'videw'),
        'username' => (string) env_value('VIDEW_DB_USERNAME', 'root'),
        'password' => (string) env_value('VIDEW_DB_PASSWORD', ''),
        'charset' => (string) env_value('VIDEW_DB_CHARSET', 'utf8mb4'),
    ],
    'session' => [
        'name' => (string) env_value('VIDEW_SESSION_NAME', 'videw_session'),
        'cookie_lifetime' => env_int('VIDEW_SESSION_COOKIE_LIFETIME', 0),
        'cookie_secure' => env_flag('VIDEW_SESSION_SECURE_COOKIE', false),
        'cookie_http_only' => env_flag('VIDEW_SESSION_HTTP_ONLY', true),
        'cookie_same_site' => (string) env_value('VIDEW_SESSION_SAME_SITE', 'Lax'),
    ],
    'security' => [
        'expose_reset_links' => env_flag('VIDEW_DEBUG_EXPOSE_RESET_LINKS', false),
        'trusted_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env_value('VIDEW_TRUSTED_HOSTS', ''))
        ))),
        'trust_proxy_headers' => env_flag('VIDEW_TRUST_PROXY_HEADERS', false),
        'force_https' => env_flag('VIDEW_FORCE_HTTPS', false),
        'hsts_enabled' => env_flag('VIDEW_SECURITY_HSTS_ENABLED', false),
        'hsts_max_age' => env_int('VIDEW_SECURITY_HSTS_MAX_AGE', 31536000),
        'csp_enabled' => env_flag('VIDEW_SECURITY_CSP_ENABLED', true),
        'csp_report_only' => env_flag('VIDEW_SECURITY_CSP_REPORT_ONLY', false),
        'content_security_policy' => (string) env_value('VIDEW_SECURITY_CONTENT_SECURITY_POLICY', ''),
    ],
    'storage' => [
        'local_root' => project_path((string) env_value('VIDEW_LOCAL_STORAGE_ROOT', 'storage/uploads')),
        'local_public_base_url' => rtrim((string) env_value('VIDEW_LOCAL_STORAGE_BASE_URL', ''), '/'),
        'default_driver' => (string) env_value('VIDEW_STORAGE_DRIVER', 'local'),
        'wasabi_endpoint' => (string) env_value('VIDEW_WASABI_ENDPOINT', 'https://s3.wasabisys.com'),
        'wasabi_region' => (string) env_value('VIDEW_WASABI_REGION', 'us-east-1'),
        'wasabi_bucket' => (string) env_value('VIDEW_WASABI_BUCKET', ''),
        'wasabi_access_key' => (string) env_value('VIDEW_WASABI_ACCESS_KEY', ''),
        'wasabi_secret_key' => (string) env_value('VIDEW_WASABI_SECRET_KEY', ''),
        'wasabi_public_base_url' => rtrim((string) env_value('VIDEW_WASABI_PUBLIC_BASE_URL', ''), '/'),
        'wasabi_path_prefix' => trim((string) env_value('VIDEW_WASABI_PATH_PREFIX', 'videw'), '/'),
        'wasabi_private_bucket' => (string) env_value('VIDEW_WASABI_PRIVATE_BUCKET', '0'),
        'wasabi_signed_url_ttl_seconds' => (string) env_value('VIDEW_WASABI_SIGNED_URL_TTL_SECONDS', '900'),
        'wasabi_multipart_threshold_mb' => (string) env_value('VIDEW_WASABI_MULTIPART_THRESHOLD_MB', '64'),
        'wasabi_multipart_part_size_mb' => (string) env_value('VIDEW_WASABI_MULTIPART_PART_SIZE_MB', '16'),
    ],
    'billing' => [
        'stripe_secret_key' => (string) env_value('VIDEW_STRIPE_SECRET_KEY', ''),
        'stripe_publishable_key' => (string) env_value('VIDEW_STRIPE_PUBLISHABLE_KEY', ''),
        'stripe_webhook_secret' => (string) env_value('VIDEW_STRIPE_WEBHOOK_SECRET', ''),
        'premium_price_id' => (string) env_value('VIDEW_STRIPE_PREMIUM_PRICE_ID', ''),
        'premium_plan_name' => (string) env_value('VIDEW_STRIPE_PREMIUM_PLAN_NAME', 'Premium'),
        'premium_plan_copy' => (string) env_value('VIDEW_STRIPE_PREMIUM_PLAN_COPY', 'Unlock every premium video with one monthly membership.'),
        'premium_price_label' => (string) env_value('VIDEW_STRIPE_PREMIUM_PRICE_LABEL', '$9.99 / month'),
    ],
    'footer' => [
        'tagline' => (string) env_value('VIDEW_FOOTER_TAGLINE', (string) env_value('VIDEW_APP_DESCRIPTION', 'Video platform with free and premium access.')),
        'useful_title' => (string) env_value('VIDEW_FOOTER_USEFUL_TITLE', 'Useful links'),
        'legal_title' => (string) env_value('VIDEW_FOOTER_LEGAL_TITLE', 'Legal'),
        'support_title' => (string) env_value('VIDEW_FOOTER_SUPPORT_TITLE', 'Support'),
        'support_copy' => (string) env_value('VIDEW_FOOTER_SUPPORT_COPY', 'Questions, account help, legal notices, and takedown requests.'),
        'useful_links' => [
            [
                'label' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_1_LABEL', 'Browse'),
                'url' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_1_URL', 'browse.php'),
            ],
            [
                'label' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_2_LABEL', 'Premium'),
                'url' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_2_URL', 'premium.php'),
            ],
            [
                'label' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_3_LABEL', 'Support'),
                'url' => (string) env_value('VIDEW_FOOTER_USEFUL_LINK_3_URL', 'support.php'),
            ],
        ],
        'legal_links' => [
            [
                'label' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_1_LABEL', 'Platform rules'),
                'url' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_1_URL', 'rules.php'),
            ],
            [
                'label' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_2_LABEL', 'Terms of use'),
                'url' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_2_URL', 'terms.php'),
            ],
            [
                'label' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_3_LABEL', 'Privacy policy'),
                'url' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_3_URL', 'privacy.php'),
            ],
            [
                'label' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_4_LABEL', 'Cookie policy'),
                'url' => (string) env_value('VIDEW_FOOTER_LEGAL_LINK_4_URL', 'cookies.php'),
            ],
        ],
    ],
    'legal' => [
        'rules' => [
            'nav_label' => (string) env_value('VIDEW_RULES_NAV_LABEL', 'Rules'),
            'kicker' => (string) env_value('VIDEW_RULES_KICKER', 'Rules'),
            'title' => (string) env_value('VIDEW_RULES_PAGE_TITLE', 'How this site works'),
            'intro' => (string) env_value('VIDEW_RULES_PAGE_INTRO', 'Read the rules for age checks, uploads, member access, and account use.'),
            'items' => [
                [
                    'title' => (string) env_value('VIDEW_RULES_CARD_1_TITLE', 'Age check'),
                    'copy' => (string) env_value('VIDEW_RULES_CARD_1_TEXT', 'Only adults 18 or older may enter and use the site.'),
                ],
                [
                    'title' => (string) env_value('VIDEW_RULES_CARD_2_TITLE', 'Consent'),
                    'copy' => (string) env_value('VIDEW_RULES_CARD_2_TEXT', 'Every upload must follow the site consent and content rules.'),
                ],
                [
                    'title' => (string) env_value('VIDEW_RULES_CARD_3_TITLE', 'Access'),
                    'copy' => (string) env_value('VIDEW_RULES_CARD_3_TEXT', 'Free and Premium videos stay clearly separated across the library.'),
                ],
                [
                    'title' => (string) env_value('VIDEW_RULES_CARD_4_TITLE', 'Review'),
                    'copy' => (string) env_value('VIDEW_RULES_CARD_4_TEXT', 'Videos may be reviewed before they go live on the site.'),
                ],
            ],
        ],
        'terms' => [
            'kicker' => (string) env_value('VIDEW_TERMS_KICKER', 'Terms'),
            'title' => (string) env_value('VIDEW_TERMS_TITLE', 'Terms of Use'),
            'intro' => (string) env_value('VIDEW_TERMS_INTRO', 'Read these terms before using the site.'),
            'content' => (string) env_value('VIDEW_TERMS_CONTENT', "You must be 18 or older to access this site.\n\nYou are responsible for using the platform in compliance with your local laws.\n\nWe may suspend accounts, remove uploads, or limit access when these terms are broken."),
        ],
        'privacy' => [
            'kicker' => (string) env_value('VIDEW_PRIVACY_KICKER', 'Privacy'),
            'title' => (string) env_value('VIDEW_PRIVACY_TITLE', 'Privacy Policy'),
            'intro' => (string) env_value('VIDEW_PRIVACY_INTRO', 'This policy explains what information is collected and why.'),
            'content' => (string) env_value('VIDEW_PRIVACY_CONTENT', "We collect the minimum data needed to run accounts, secure sessions, and operate the site.\n\nWe may process account details, IP logs, moderation records, and support communications.\n\nYou can contact support for privacy-related requests using the email listed in the footer."),
        ],
        'cookies' => [
            'kicker' => (string) env_value('VIDEW_COOKIES_KICKER', 'Cookies'),
            'title' => (string) env_value('VIDEW_COOKIES_TITLE', 'Cookie Policy'),
            'intro' => (string) env_value('VIDEW_COOKIES_INTRO', 'This page explains how cookies and similar storage are used on the site.'),
            'content' => (string) env_value('VIDEW_COOKIES_CONTENT', "We use cookies or similar storage to keep your session active, remember preferences, and improve performance.\n\nSome cookies are necessary for login, age-gate state, and security features such as multi-factor authentication.\n\nYou can review this page at any time from the footer links."),
        ],
    ],
    'cookie_notice' => [
        'enabled' => env_flag('VIDEW_COOKIE_NOTICE_ENABLED', true),
        'title' => (string) env_value('VIDEW_COOKIE_NOTICE_TITLE', 'Cookie notice'),
        'text' => (string) env_value('VIDEW_COOKIE_NOTICE_TEXT', 'This site uses cookies or similar storage to keep your session active, remember your choices, and improve performance.'),
        'accept_label' => (string) env_value('VIDEW_COOKIE_NOTICE_ACCEPT_LABEL', 'Accept'),
        'link_label' => (string) env_value('VIDEW_COOKIE_NOTICE_LINK_LABEL', 'Read cookie policy'),
        'link_url' => (string) env_value('VIDEW_COOKIE_NOTICE_LINK_URL', 'cookies.php'),
    ],
    'copy' => require __DIR__ . '/copy.php',
];
