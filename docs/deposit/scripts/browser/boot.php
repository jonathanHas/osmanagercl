<?php

/*
 * Boot the Laravel app for the browser-check scripts. Dev only: refuses to
 * run unless APP_ENV is local. See README.md.
 */

$root = dirname(__DIR__, 4);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
error_reporting(E_ALL & ~E_DEPRECATED);

if (! app()->environment('local')) {
    fwrite(STDERR, 'Refusing to run: APP_ENV is '.app()->environment().", not local.\n");
    exit(1);
}
