# PERFORMANCE.md — DGV7.0 Load-Speed Audit & Tuning

This documents what makes the site load slowly on a dedicated VPS and the
optimizations applied (plus the server-side settings you must enable to hit the
2–3 second target).

## What runs on EVERY page request (the fixed cost)

1. `func/bc-connect.php` — the heavy bootstrap, loaded by every page:
   - DB connection
   - **PHPMailer** classes (Exception, PHPMailer, SMTP) — large class files parsed per request
   - **`func/bc-func.php` — 322 KB** of functions
   - `bc-levelup.php` / `bc-integrity.php`, `bc-security.php`, `bc-url.php`,
     `bc-bulk-queue.php`, `bc-mail-queue.php`, `email-design.php`
   - `bc_verify_integrity()` license check
2. `func/bc-config.php` (web pages) — session, vendor resolution (cached in
   session), migration-version check (skipped after first run), vendor details
   (60 s session cache), logged-in user row.
3. The landing page `index.php` is already optimized (vendor details cached in a
   file for 5 min; the template itself runs 1 query).

**Conclusion:** the DB layer is NOT the bottleneck — the fixed cost is **PHP
parsing + a few small DB lookups per request**. On a dedicated VPS the #1 cause of
"sluggish" load is **OPcache being off**, so PHP re-parses 322 KB of bc-func.php +
PHPMailer on every single request.

## Optimizations applied (committed)

1. **`bc_verify_integrity()` — removed recurring network stalls.**
   - SAAS (`func/bc-levelup.php`): a validated license in the file cache is now
     trusted for the full **48 h** window (was 6 h) without calling the license API,
     so the remote call happens ~once per 48 h instead of every 6 h.
   - NON-SAAS (`func/bc-integrity.php`): timeout cut from **10 s → 4 s**, connect
     5 s → 3 s, and retries cut from **2 (+1 s sleep) → 1**. Worst case stall dropped
     from ~20 s to ~4 s, and a valid 48 h cache still passes on failure.
2. **`.htaccess` — compression + browser caching** (both codebases). `mod_deflate`
   compresses HTML/CSS/JS/JSON; `mod_expires` adds long cache headers for images,
   fonts, CSS and JS. Safe — wrapped in `IfModule` (no-ops if a module is absent).
3. **`cron/perf_report.php`** — CLI probe (both codebases). Run it to see PHP /
   OPcache / MySQL state, the bootstrap time, and the license-cache state:
   ```bash
   php cron/perf_report.php
   ```

## What you MUST enable on the server (not code)

### 1. OPcache (the biggest win)
In `php.ini` (cPanel → MultiPHP INI Editor, or SSH):
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60
; PHP 8 only:
opcache.jit=1255
```
Verify with `php cron/perf_report.php` — it prints whether OPcache is on. With
OPcache on, the 322 KB bc-func + PHPMailer parse cost effectively disappears.

### 2. MySQL tuning
```ini
[mysqld]
innodb_buffer_pool_size = 256M     ; ≥512M if the box has ≥4 GB RAM
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
```
The schema already creates performance indexes (`idx_vendor_lookup`,
`idx_v_user_status`, etc.) in `func/bc-config.php` migrations.

### 3. License cache
Confirm `func/cache/bc-core.cache` exists and is fresh. If `func/` is not writable,
PHP can't write the cache and will hit the license API more often — make `func/cache`
writable (e.g. `chmod 755` / `775`).

### 4. Verify the 2–3 s target
- Run `php cron/perf_report.php` — bootstrap should be well under ~500 ms with OPcache on.
- Open the site in a browser → DevTools → Network → check **TTFB** on the landing page.
  TTFB = bootstrap + query time; with OPcache + the integrity fix this is normally
  < 1 s, and total page load (including assets, now compressed + cached) lands in 2–3 s.

## Quick reference — slow-load triage

| Symptom | Likely cause | Fix |
|---|---|---|
| Every page slow, high CPU | OPcache off (re-parsing 322 KB bc-func + PHPMailer) | Enable OPcache |
| Intermittent 5–20 s stalls | `bc_verify_integrity` hitting the license API | Fixed in code (48 h cache + 4 s timeout); ensure `func/cache` writable |
| Slow after login / dashboard | Heavy DB query | MySQL slow log → add/missing index; check `sas_transactions` indexes |
| Slow assets (images/CSS/JS) | No compression / caching | Added `.htaccess` mod_deflate + mod_expires |
| First visit slow, repeat fast | Missing opcache/session cache warm-up | Normal; enable OPcache |
