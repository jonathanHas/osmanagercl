<?php

/*
 * Mint a logged-in web session for one user (default 1). Dev only.
 *
 * Plain: print three lines, the session cookie name, its encrypted value, and
 * APP_URL (the .mjs scripts read these).
 *
 * --playwright [path]: write a Playwright storage-state file instead (default
 * ~/.cache/osmanagercl/playwright-state.json, mode 0600), the login the
 * Playwright MCP in .mcp.json starts from, and print its path and expiry.
 *
 * Usage: php docs/deposit/scripts/browser/mint.php [user_id] [--playwright [path]]
 */

require __DIR__.'/boot.php';

$args = array_slice($argv, 1);
$playwright = false;
$statePath = null;
$userId = 1;
foreach ($args as $i => $arg) {
    if ($arg === '--playwright') {
        $playwright = true;
        $next = $args[$i + 1] ?? null;
        $statePath = $next !== null && ! ctype_digit($next) ? $next : null;
    } elseif (ctype_digit($arg)) {
        $userId = (int) $arg;
    }
}

$user = App\Models\User::findOrFail($userId);

$store = app('session')->driver();
$store->start();
Illuminate\Support\Facades\Auth::guard('web')->login($user);
$store->put('_token', Illuminate\Support\Str::random(40));
$store->save();

$name = config('session.cookie');
$encrypter = app('encrypter');
$value = $encrypter->encrypt(Illuminate\Cookie\CookieValuePrefix::create($name, $encrypter->getKey()).$store->getId(), false);
$appUrl = rtrim(config('app.url'), '/');

if (! $playwright) {
    echo $name."\n".$value."\n".$appUrl."\n";
    exit(0);
}

$statePath ??= getenv('HOME').'/.cache/osmanagercl/playwright-state.json';
$expires = time() + (int) config('session.lifetime') * 60;

if (! is_dir(dirname($statePath))) {
    mkdir(dirname($statePath), 0700, true);
}
file_put_contents($statePath, json_encode([
    'cookies' => [[
        'name' => $name,
        'value' => $value,
        'domain' => parse_url($appUrl, PHP_URL_HOST),
        'path' => '/',
        'expires' => $expires,
        'httpOnly' => true,
        'secure' => false,
        'sameSite' => 'Lax',
    ]],
    'origins' => [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
chmod($statePath, 0600);

echo "Playwright storage state for user {$user->id}: {$statePath}\n";
echo 'Cookie expires: '.date('Y-m-d H:i:s', $expires)." (SESSION_LIFETIME); mint again after that, then browser_close.\n";
