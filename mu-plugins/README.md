# TMW MU Plugins Layout

```text
mu-plugins/
├── tmw-loader.php
└── tmw-modules/
    ├── admin.example-tools.php
    ├── frontend.example-hero-audit.php
    ├── CODEX_AUDIT_smoke.php
    ├── tmw-inline-mobile-hero-audit.php
    ├── tmw-lostpass-finder.php
    ├── tmw-php-deprecations.php
    ├── tmw-rankmath-fix-v3.php
    ├── tmw-rankmath-robots-override.php
    ├── tmw-reset-safemode.php
    ├── tmw-run-mobile-hero-audit.php
    └── tmw-slot-diagnostic.php
```

Notes:
- Keep only `tmw-loader.php` in `mu-plugins` root.
- Place all standalone modules in `tmw-modules/`.
- Module files should define classes/functions and register on `tmw_mu_modules_register`.
- Module files should not execute work at include-time.
