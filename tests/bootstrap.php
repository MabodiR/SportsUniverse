<?php

declare(strict_types=1);

// A cached local/production configuration can otherwise override phpunit.xml
// and point RefreshDatabase at PostgreSQL. Tests must always boot from their
// isolated environment variables instead.
$cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($cachedConfig)) {
    unlink($cachedConfig);
}

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__).'/vendor/autoload.php';
