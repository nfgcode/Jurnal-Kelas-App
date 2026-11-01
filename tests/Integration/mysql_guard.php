<?php

// Deliberately before Composer/Laravel/DB. A name alone never proves disposal.
if (getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'mysql'
    || ! preg_match('/\Ajurnal_disposable_jurusan_test_[a-f0-9]{16}\z/D', (string) getenv('DB_DATABASE'))
    || (string) getenv('DB_URL') !== '' || (string) getenv('DATABASE_URL') !== ''
    || (string) getenv('APP_CONFIG_CACHE') !== '') {
    throw new RuntimeException('Unsafe MySQL integration: testing, exact disposable prefix, no DB URL/config cache required.');
}

// Fase 2A.1 does not provision/authorize a dedicated disposable MySQL container.
// Keep this path closed even with plausible environment variables.
throw new RuntimeException('Unsafe MySQL integration: dedicated disposable container not verified; NOT RUN.');
