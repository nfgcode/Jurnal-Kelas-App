# Safe testing foundation (2A.1)

Run from repository root:

```sh
php tests/run.php tests/Unit/SafetyFoundationTest.php tests/Feature/SafetyApplicationTest.php
```

Runner invokes `php -d sys_temp_dir=<owned-scratch>/tmp vendor/bin/phpunit` directly. No Artisan/config:clear. `php vendor/bin/phpunit` without owned scratch deliberately fails before autoload/Laravel. Composer `test`/`quality` use this runner; no arguments means full Unit/Feature suite, not MySQL integration. Full suite is NOT certified by the foundation-only verification.

Scratch must exist outside the repository. Set `TMPDIR` explicitly if needed. Windows default: `%LOCALAPPDATA%/hermes/cache/scratch`; container default `/tmp`. Each invocation owns a random directory, marker, storage, compiled views, framework manifests/config/routes caches, PHP temporary files. PHPUnit result caching is disabled. QA exports are disabled. Parent shutdown removes only its owned directory, also after child premature termination. SIGKILL/host loss/forced parent termination cannot guarantee shutdown cleanup; no shared-tree sweeping is performed.

Container non-DB safety verification (existing PHP container only):

```sh
docker exec -e APP_ENV=testing -e TMPDIR=/tmp jurnal-kelas-app php tests/run.php tests/Unit/SafetyFoundationTest.php tests/Feature/SafetyApplicationTest.php
```

`tests/Integration/jurusan_mysql.php` is hard-disabled before Composer/Laravel/DB. Guard rejects non-testing environment, wrong exact database-name pattern (`jurnal_disposable_jurusan_test_[a-f0-9]{16}`), DB_URL/DATABASE_URL, config-cache override. Even matching values remain blocked: a dedicated disposable MySQL container has not been verified. Do not point integration at the existing primary MySQL container.

Verified environments: local PHP 8.4.0; container PHP 8.3.32. Both PDO SQLite/MySQL, sqlite3, DOM, mbstring, XML, ZIP present. Container additionally intl/sodium/pcntl/posix; local lacks those. No dependencies installed.
