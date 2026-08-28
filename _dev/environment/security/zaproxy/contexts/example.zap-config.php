<?php

// Copy this file to <target>.zap-config.php (gitignored) and run:
//   php my-sites-ide ide:zap-context <target>
// against a running `ide:zap-hud` container. Rebuilds the named ZAP context
// from scratch each time (safe to re-run after editing this file) and exports
// it to reports/<target>.context, ready for `ide:zap-scan -n -U`.

return [
    // The ZAP context name, and the site's base URL.
    'target' => 'example.test',

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
];
