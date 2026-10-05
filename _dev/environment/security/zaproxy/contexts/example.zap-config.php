<?php

// Running `php my-sites-ide ide:zap-context <target>` scaffolds a copy of
// this file to <target>.zap-config.php (gitignored) automatically if one
// doesn't exist yet - edit the scaffolded copy's placeholder values, then
// re-run the same command. It starts a ZAP daemon itself if needed, rebuilds
// the named context from scratch each time (safe to re-run after editing),
// and exports it to reports/<target>.context, ready for
// `ide:zap-scan <url> --context=<target> --user=<name>`.

// The target app's root inside the fpm container (/opt/repos/<repo>/deploy).
// ide:zap-coverage runs security:coverage-diff there, and $artisan below
// generates the seed_urls/write_routes/livewire_actions manifests from it.
$appPath = '/opt/repos/example/deploy';

$artisan = static function (string $command) use ($appPath): array {
    $json = shell_exec("docker exec -w {$appPath} fpm php artisan {$command} 2>/dev/null");

    return json_decode(trim((string) $json), true) ?? [];
};

return [
    // The ZAP context name, and the site's base URL.
    'target' => 'example.test',

    'app_path' => $appPath,

    'login' => [
        // Submitted URL for the login form.
        'url' => 'https://example.test/login',
        // Where ZAP fetches the fresh CSRF/hidden-field values from before each
        // login attempt. Defaults to `url` above if omitted.
        // 'page_url' => 'https://example.test/login',
        // {%username%} / {%password%} are ZAP's reserved credential placeholders.
        // Any other {%name%} is scraped fresh from page_url's HTML on each login
        // (e.g. a Laravel-style CSRF token that changes per page load).
        'request_data' => 'email={%username%}&password={%password%}&_token={%_token%}',
    ],

    'indicator' => [
        // A regex matched against the response body. Prefer a POSITIVE logged-in
        // indicator (something present only when authenticated, e.g. a logout
        // link) over a logged-out one - less prone to false positives, which can
        // otherwise trigger repeated re-authentication and trip a login throttle.
        'logged_in' => 'action="https://example\.test/logout"',
        // 'logged_out' => '',
        // A URL ide:zap-context polls (checking the indicator above) to verify
        // the session is still alive, cached for 60s so it doesn't re-check on
        // every request. Required - without one, ZAP's own default verification
        // strategy crashes every auth check, and there's no other way to detect
        // a dead session without it. Pick a URL that's cheap and only reachable
        // when authenticated, e.g. the post-login landing page.
        'poll_url' => 'https://example.test/home',
    ],

    'scope' => [
        'include' => [
            'https://example\.test.*',
        ],
        'exclude' => [
            // Exclude the login/logout endpoints themselves from the attack
            // surface - there's no reason to actively fuzz them, and doing so
            // risks self-locking the scan's own session via any login throttle.
            'https://example\.test/login.*',
            'https://example\.test/logout.*',
        ],
    ],

    'user' => [
        // Label shown in the ZAP UI - not the login username.
        'name' => 'demo',
        // A dedicated, seeded test account - never a real user's credentials.
        'username' => 'demo@example.com',
        'password' => 'change-me',
    ],

    // Extra URLs to seed the scan with, beyond whatever the crawler finds by
    // following links. The crawler only follows plain <a href>/<form> markup -
    // it misses anything JS-only (fetch/AJAX calls with no matching link) and,
    // less obviously, links that ARE in the HTML but sit inside a collapsed
    // nav dropdown (verified empirically: consistently missed across repeated
    // runs even with a generous crawl duration).
    //
    // For a Laravel target, this is worth generating dynamically rather than
    // hand-maintaining a list that goes stale. Stockman / smart-kitchen ship a
    // `security:seed-urls` artisan command (uncomment the $artisan line below)
    // that filters to GET routes reachable by the scan's own user, and resolves
    // any {route-model-binding} parameter to a real database id (the highest-
    // value case: an /edit route's id flows straight into a query, making it
    // exactly where IDOR/injection bugs live - skipping every parameterized
    // route for simplicity would skip the most security-relevant pages).
    // 'seed_urls' => $artisan('security:seed-urls'),
    // or by hand:
    // 'seed_urls' => [
    //     'https://example.test/orders',
    //     'https://example.test/orders/42/edit',
    // ],

    // Companion to seed_urls, for PUT/PATCH/POST routes - a spider seed has
    // no body, so seed_urls can never reach a write-only route. This list
    // isn't scanned automatically; it's the checklist for the browser-based
    // write-verb walkthrough (see zaproxy/README.md Phase 5) and the input
    // to `ide:zap-coverage` once built. Generate the same way as seed_urls -
    // from the target app's own route table, not hand-maintained.
    // 'write_routes' => $artisan('security:seed-write-routes'),
    // or by hand:
    // 'write_routes' => [
    //     ['method' => 'POST', 'uri' => 'https://example.test/orders'],
    //     ['method' => 'PUT', 'uri' => 'https://example.test/orders/42'],
    // ],

    // A different kind of gap again - a framework with server-driven
    // components (Livewire, and similar patterns elsewhere) dispatches every
    // component action through one shared endpoint, with the method name
    // inside the request payload rather than as a route. No route table
    // enumeration, however extended, can ever see these - not scanned or
    // diffed automatically, just surfaced by `ide:zap-coverage` as a manual-
    // verification checklist (page URL + component/method it's attached to).
    // `security:seed-livewire-actions` is a working generator: it walks routes
    // whose action is a component class, resolves each component's default
    // view via the framework's own naming convention, then extracts real
    // wire:click/wire:submit calls from that view (excluding the framework's
    // own client-side-only directives, which have nothing server-side to test).
    // 'livewire_actions' => $artisan('security:seed-livewire-actions'),
    // or by hand:
    // 'livewire_actions' => [
    //     ['component' => 'App\\Livewire\\Orders\\OrderIndex', 'method' => 'delete', 'uri' => 'https://example.test/orders', 'blade' => 'resources/views/livewire/orders/order-index.blade.php:53'],
    // ],
];
