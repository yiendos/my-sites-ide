# OWASP ZAP scanning

Automated and manual dynamic application security testing (DAST) against sites served by
this dev stack, using OWASP ZAP. Two ways to drive it:

- **CLI** (`ide:zap-*` commands): headless, repeatable, scriptable. Gives breadth.
- **Browser** (`ide:zap-hud`, the ZAP Desktop UI): interactive. Gives depth, and reaches
  what automation structurally can't (write-verb endpoints, Livewire actions).

Use both, in the order laid out under [Methodology](#methodology).

Written for: developers running security checks against a local site in this repo,
including ones who haven't used ZAP before.

## Contents

- [Architecture](#architecture)
- [First-time setup](#first-time-setup)
- [Prerequisites](#prerequisites)
- [Setup per target](#setup-per-target)
- [Methodology](#methodology)
- [Stopping and restarting](#stopping-and-restarting)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [Reports and sensitive files](#reports-and-sensitive-files)
- [Troubleshooting](#troubleshooting)
- [Known false positives](#known-false-positives)
- [Target-app requirements](#target-app-requirements)
- [Known gaps](#known-gaps)

## Architecture

```
host (my-sites-ide CLI)
  |- ide:zap-scan / ide:zap-context / ide:zap-coverage  --> docker compose exec zaproxy curl :8090/JSON/...
  |- ide:zap-daemon   --> docker compose run -d --rm zaproxy zap.sh -daemon ...
  |- ide:zap-hud      --> docker compose run --service-ports zaproxy zap-webswing.sh   (browser UI on :8080)
  |                       + scripts/hud-watcher.sh in the background, configuring each ZAP session via the API
  |- ide:zap-hud-fix  --> reads webswing.out inside the container, offers to kill stray ZAP processes
  |- ide:zap-install-manifests --> copies stubs/laravel/*.php.stub into the target app, edits its zap-config

zaproxy container (ghcr.io/zaproxy/zaproxy, pinned tag in docker-compose.yml)
  - reports/        bind-mounted to /zap/wrk (gitignored output)
  - scripts/        bind-mounted read-only to /zap/scripts (helpers run inside the container)
  - zap-home        named volume at /home/zap (addon state, and the ZAP home-dir lock)
  - network my-sites-ide, so it reaches target sites by their hostname
```

Key facts that shape everything else:

- **The ZAP API (port 8090) is only reachable from inside the container.** On macOS, host
  to container port forwarding doesn't present as 127.0.0.1 to ZAP, so ZAP's
  permitted-addresses check drops the connection ("Empty reply from server"). All API
  calls go through `docker compose exec zaproxy curl ...` (see `InteractsWithZapApi`).
- **Only one ZAP process can use the `zap-home` directory at a time.** The daemon and the
  HUD can't run together, and neither can two HUD sessions.
- **The container is memory and CPU capped.** An uncapped ZAP drove a shared 7.6 GiB VM
  to OOM and got the unrelated `db` container killed. The limits are part of the setup,
  not optional tuning.
- **`docker logs` shows almost nothing useful.** The real Webswing log is
  `/zap/webswing/webswing.out` inside the container. ZAP's own logs are under `/home/zap`.

## First-time setup

Several things have to be in place before the first scan works, and most of them fail in
ways that look like a credentials problem. Work through these in order. The examples use
`local.smart-kitchen.io`; substitute your target's hostname.

1. **Root `.env` values.** Add both to the root `.env` (gitignored), not `zaproxy/.env`:

   ```
   ZAP_TARGET_ALIAS=local.smart-kitchen.io
   ZAP_TARGET_PASSWORD=<demo user's password>
   ```

   `ZAP_TARGET_ALIAS` must be set explicitly. Its default (`default.test`) won't match
   your target, and a value left over from another target (`stockman.test`) fails the
   same way. See [Configuration](#configuration).

2. **Vhost `server_name`.** Check the target's nginx vhost lists the same hostname in
   `server_name`.

3. **Recreate nginx** so it registers the alias. A restart isn't enough:

   ```
   docker compose up -d nginx
   docker inspect nginx    # the hostname should appear under Aliases
   ```

4. **`/etc/hosts` on your Mac** (for your browser only, used in the HUD phases):

   ```
   127.0.0.1 local.smart-kitchen.io
   ```

5. **Demo user.** The target's database needs the scan user (Stockman / smart-kitchen:
   `demo@example.com` from `DatabaseSeeder`) with a password matching
   `ZAP_TARGET_PASSWORD`. Reseeding can delete it. See
   [Target-app requirements](#target-app-requirements).

6. **Reachability check from the container.** Expect the hostname to resolve to the nginx
   container (not `localhost`) and a `200`:

   ```
   docker compose exec -T zaproxy sh -c 'getent hosts local.smart-kitchen.io; curl -sk -o /dev/null -w "%{http_code}\n" https://local.smart-kitchen.io/login'
   ```

   A `000` or a `localhost` address means steps 1 to 3 aren't right yet.

7. **Build the context** with flags ([Option A](#option-a-flags-recommended)). This writes
   `contexts/local.smart-kitchen.io.zap-config.php` and `reports/local.smart-kitchen.io.context`:

   ```
   php my-sites-ide ide:zap-context local.smart-kitchen.io \
     --site-url=https://local.smart-kitchen.io \
     --login-url=/login \
     --login-data='email={%username%}&password={%password%}&_token={%_token%}' \
     --indicator='action="https://local\.smart-kitchen\.io/logout"' \
     --poll-url=/home \
     --username=demo@example.com
   ```

   A hostname containing a dot is used as-is; only a bare name like `stockman` gets
   `.test` appended. If a scan later reports `No context file at reports/<target>.context`,
   this step hasn't been run for that exact target name.

8. **Run the baseline scan** (Phase 1):

   ```
   php my-sites-ide ide:zap-scan https://local.smart-kitchen.io --context=local.smart-kitchen.io --user=demo
   ```

   `--user` takes the ZAP user's **label** (`demo`), not the login email. Passing
   `demo@example.com` fails with `No user named 'demo@example.com' found in context`.

If the scan finishes very quickly or the report is empty, go to
[Troubleshooting](#troubleshooting) before changing credentials.

## Prerequisites

- Docker Desktop running, with the `my-sites-ide` network up.
- The target site running and reachable on the `my-sites-ide` network.
- An entry in your Mac's `/etc/hosts` for the target hostname (`127.0.0.1 <target>.test`).
  This is for your host browser only. Container-to-container resolution uses the nginx
  network alias described under [Configuration](#configuration).
- For authenticated scans, a test user on the target app (see
  [Target-app requirements](#target-app-requirements)).

## Setup per target

Each target gets a context: ZAP's saved auth, scope and user configuration. Build it once,
then rebuild whenever the config changes. Rebuilding always starts from scratch, so it is
safe to re-run.

### Option A: flags (recommended)

Supply the details once on the command line. A complete config is written to
`contexts/<target>.zap-config.php`, and the context is built in the same run:

```
ZAP_TARGET_PASSWORD=<password> php my-sites-ide ide:zap-context <target> \
  --site-url=https://<target>.test \
  --login-url=/login \
  --login-data='email={%username%}&password={%password%}&_token={%_token%}' \
  --indicator='action="https://<target>\.test/logout"' \
  --poll-url=/home \
  --username=demo@example.com
```

- `--login-url`, `--poll-url` and `--logout-url` (default `/logout`) accept a path relative
  to `--site-url`, or a full URL.
- The scope (include/exclude) is derived from `--site-url` and the login/logout URLs.
- The password comes from `ZAP_TARGET_PASSWORD`, not a flag, so it stays out of shell
  history and process listings.

### Option B: scaffold and edit

Run `php my-sites-ide ide:zap-context <target>` with no flags. It copies
`contexts/example.zap-config.php` to `contexts/<target>.zap-config.php` with the
hostname substituted, then stops. The target is used as the hostname if it contains a dot
(`local.smart-kitchen.io`); otherwise `.test` is appended (`stockman` becomes
`stockman.test`). Fill in the login URL, indicator regex, poll URL, scope
and user, and run the command again.

### What the config holds

| Key | Purpose |
|---|---|
| `app_path` | The target app's root inside the `fpm` container (`/opt/repos/<repo>/deploy`). `ide:zap-coverage` runs `security:coverage-diff` there, and the config's `$artisan` helper generates the three manifests below from it |
| `login` | Form-based login: URL, request body (`{%username%}`, `{%password%}`, and any other name such as `{%_token%}` to scrape a fresh CSRF token before each attempt) |
| `indicator` | Regex that is present only when logged in (the logout form), plus a poll URL used to re-verify the session |
| `scope` | Include/exclude regexes for what ZAP may crawl |
| `user` | The ZAP user, with credentials read from `ZAP_TARGET_PASSWORD` or the config file |
| `seed_urls` | GET URLs to seed the spider with (generated by the target app, see below) |
| `write_routes` | Write-verb routes for the coverage checklist (generated by the target app) |
| `livewire_actions` | Livewire `wire:click`/`wire:submit` actions for the checklist (generated by the target app) |

`seed_urls`, `write_routes` and `livewire_actions` are shell-outs to the target app's own
artisan commands, so they stay current with its routes (see
[Route manifests](#route-manifests)). Config files are gitignored
(`contexts/*.zap-config.php`). `contexts/example.zap-config.php` is the tracked template.

### Route manifests

Without manifests, ZAP only finds what its spider can reach by following links from the
target URL. In practice that misses a lot: pages behind JavaScript (`fetch()` API calls,
links inside Alpine dropdowns), every route with a `{parameter}`, and anything that isn't
a GET. The manifests list those routes from the target app's own route table, so a scan
starts from what the app actually has rather than what the spider stumbles on.

| Manifest | Artisan command | What it lists | Used by |
|---|---|---|---|
| `seed_urls` | `security:seed-urls` | Every GET page, with `{parameters}` filled in | `ide:zap-scan --context`, which spiders the target plus every seed URL as the scan user |
| `write_routes` | `security:seed-write-routes` | Every POST/PUT/PATCH/DELETE route, with its method | `ide:zap-hud <target>` (checklist at launch) and `ide:zap-coverage` (covered vs not) |
| `livewire_actions` | `security:seed-livewire-actions` | Every `wire:click`/`wire:submit` action in a routed Livewire component, with its page and Blade line | `ide:zap-hud <target>` and `ide:zap-coverage`, as a checklist only |
| (none) | `security:coverage-diff` | Matches the traffic ZAP recorded against the write routes | `ide:zap-coverage`, which passes it the recorded traffic |

All four commands:

- **Fill `{parameters}` with real records owned by the scan user**, following Laravel's
  route-model binding names (`{order}` is an `App\Models\Order` id) and limited to rows
  with that user's `user_id`. That means scans hit pages the user can really open, which
  is where IDOR and injection bugs live. A route whose required parameter can't be
  resolved is skipped rather than seeded as a guaranteed 404.
- **Skip routes that aren't worth scanning:** `login`, `logout`, `up`, admin-only routes
  (`can:admin`, which would only 403 for the non-admin scan user) and infrastructure
  prefixes (`livewire-*`, `storage/`, `reset-password/`, `_boost`, `sanctum/`).
- **Default to** `--user=demo@example.com` and `--base-url=https://local.smart-kitchen.io`.
  Pass both if your scan user or hostname differs.

`security:seed-livewire-actions` only sees components that have their own route. A
component that only appears inside another page's view has no URL, so it's skipped.

**Turning them on.** One command copies the four commands into a Laravel target and
wires them into its config:

```
php my-sites-ide ide:zap-install-manifests <target>
```

- It copies the templates from `stubs/laravel/` into the app's `app/Console/Commands/`,
  filling in the scan user (from the config's `user.username`) and the base URL (from
  its login URL) as the commands' defaults. Override either with `--user` or
  `--base-url`.
- It never overwrites a file the app already has, because that copy may have local
  changes. `--force` overwrites.
- It adds whatever the config is missing: the `$artisan` helper, `app_path` and the three
  manifest keys. A config written by `ide:zap-context` has none of these, so on the first
  run pass `--app-path=/opt/repos/<repo>/deploy`. Anything already there is left alone,
  and a commented-out key counts as missing.
- It finishes by running `security:seed-urls` in the `fpm` container and reporting how
  many URLs it found.

Re-running it is safe. After installing, check the skipped route prefixes and the
admin-middleware check in the app's `ResolvesRouteModelBindings.php`; the template's
defaults suit a typical Laravel app but may not match this one.

The check matters because `$artisan` returns an empty list on any failure, without an
error. A missing or broken manifest looks exactly like a target with no routes, and an
earlier full scan ran with no seed URLs because these config lines were commented out.
To check a manifest by hand:

```
docker exec -w /opt/repos/smart-kitchen-io/deploy fpm php artisan security:seed-urls
```

**The templates** in `stubs/laravel/` are the reference copies. smart-kitchen keeps its
own, so a fix made there needs copying back into the template. The templates declare
each command's signature with the `$signature` property rather than the newer
`#[Signature]` attribute, so they work on older Laravel versions too. For a non-Laravel
target, any command that prints the same JSON shape works.

## Methodology

Run these phases in order. Each one is cheap to skip, but skipping makes the next one
meaningless.

### Phase 1: CLI baseline (passive only)

```
php my-sites-ide ide:zap-scan https://<target>.test --context=<target> --user=<name>
```

Passive checks only: headers, cookies, disclosed information, obvious misconfiguration.
Takes minutes. Run on every change.

### Phase 2: CLI full active scan

```
php my-sites-ide ide:zap-scan https://<target>.test --context=<target> --user=<name> --full
```

Adds the Active Scanner: SQLi, XSS, path traversal, and so on, run against everything the
spider and `seed_urls` reached. Expect roughly 15 to 25 minutes. Run it on a cadence
(pre-release or nightly), not on every commit.

Scanner rules run one after another, not in parallel, so one slow rule holds up the rest.
DOM-based XSS (scanner 40026) is capped to `LOW` by default (`ZAP_ASCAN_DOMXSS_STRENGTH`)
because it was the bottleneck: about 929k requests, still 91% done after two hours, and it
blocked the other 77 rules. For deeper DOM XSS coverage, scope a manual Active Scan to one
page through the HUD (Phase 4). The cost scales with the DOM sinks in scope times the
payload variants, so a single page is affordable at higher strength and a whole site is not.

**Seeding limit:** `seed_urls` only contains GET routes. A spider seed has no body, so a
bare PUT, PATCH, DELETE or POST gets a 405 or 422 before reaching app code. Write-verb
endpoints reached only by JavaScript `fetch()` are therefore not crawled by Phases 1 and 2.
Phase 5 covers them.

### Phase 3: read the evidence, don't trust labels

Open the report and go finding by finding through every High and Medium. Use the
`traditional-html-plus` report, which includes the captured request and response for each
finding, not only the description. Shortlist only the findings whose captured response
actually shows the claimed effect. See [Known false positives](#known-false-positives).

### Phase 4: browser (HUD), validate the shortlist

```
php my-sites-ide ide:zap-hud <target>
```

Passing the target prints its write-route and Livewire checklist before the launch
confirmation, and imports the context exported by `ide:zap-context`
(`reports/<target>.context`) into the HUD automatically. The import happens a few seconds
after ZAP finishes starting in the browser, and again if a reloaded tab starts a fresh ZAP
session. It also removes ZAP's empty Default Context, which otherwise blocks Session
Properties from saving, and locks the session to the target: every other host is excluded
from the proxy (still loads in the browser, but never appears in the Sites tree), the
context is set in scope, and ZAP switches to Protected mode so nothing out of scope can be
attacked. Omit the target to launch with none of this.

With or without a target, each new ZAP session in the HUD also gets the same Active Scan
limits as the daemon (`ZAP_ASCAN_THREADS_PER_HOST`, `ZAP_ASCAN_DELAY_MS`,
`ZAP_ASCAN_DOMXSS_STRENGTH`), applied through the API a few seconds after ZAP starts. At
ZAP's default of 8 threads, a HUD active scan ran 8 headless Firefox instances for the DOM
XSS rule and the JVM was OOM-killed. The DOM XSS cap applies to the Default Policy, so pick
that policy in the Active Scan dialog.

To import a context by hand instead, use **File → Import Context** and type
`/zap/wrk/<target>.context` into File Name. The dialog opens in ZAP's own contexts folder,
not the `reports/` mount.

To start from a clean ZAP session, see [Stopping and restarting](#stopping-and-restarting).

The ZAP Desktop UI opens at `http://localhost:8080/zap`. Then:

1. Proxy a real browser through ZAP, scoped to the target host only. FoxyProxy works well.
   Browsers never proxy `*.localhost`, so the hostname must not end in `.localhost`. Use
   the target's real hostname, such as `local.smart-kitchen.io` or `<target>.test`.
2. Sanity check: the site's certificate issuer should be ZAP's MITM CA, not the site's
   own certificate. If it's the site's own, traffic isn't going through ZAP.
3. For each shortlisted finding, replay it through the proxied browser. Pivot the
   parameter, chain it with something the scanner wouldn't try, and confirm it is real
   before reporting or demoing it.

Close the HUD tab and stop ZAP when finished (see
[Stopping and restarting](#stopping-and-restarting)). See
[Troubleshooting](#troubleshooting) for the "Session ended" loop.

### Phase 5: browser (HUD), cover write-verb endpoints

This is the only route to write-verb endpoints. Using the same HUD session, click through
every real UI action on the checklist: add to a shopping list, submit a recipe URL, edit a
quantity, delete an item. The JavaScript client builds the real request with real field
names and real IDs, so no body has to be guessed. ZAP records each proxied request in its
session, and Active Scan can then mutate those messages like any spidered GET.

The coverage check:

```
php my-sites-ide ide:zap-coverage <target>
```

It reads every message ZAP recorded this session and reports which manifest routes were
exercised (covered) and which were not (uncovered). An uncovered route is one nobody
clicked, shown as a visible gap rather than a silent one.

**What the checklist can and can't see:**

- `write_routes` comes from the route table, so it covers traditional write endpoints
  (auth and account flows, the `api/*` create endpoints). It can be matched against
  recorded traffic and gives verified coverage.
- `livewire_actions` are Livewire component actions. They all share one endpoint and
  have no route of their own, so they can't be matched against traffic. They are printed
  as an unconditional checklist (method, page URL, and the Blade line the action comes
  from). Use it as a list to work through, not as proof of coverage.
- ZAP scans Livewire requests exactly like any other POST, so the checklist limits what
  can be seen as uncovered, not what can be scanned.

### Phase 6: fix, then re-run as regression

Once a finding is fixed, re-run Phase 1 (or Phase 2 if it was payload-based) to confirm
it's gone. For a write-verb finding from Phase 5, repeat that walkthrough, since there is no
CLI regression path for it yet. Apply the Phase 3 scrutiny to a clean result as well:
confirm what was actually covered before trusting the absence of an alert.

## Stopping and restarting

### Stopping

```
php my-sites-ide ide:zap-stop
```

Stops the HUD and the daemon, whichever are running. Both are started with `--rm`, so
stopping also removes the container. The ZAP session itself is kept in the `zap-home`
volume until you prune it.

Stop ZAP after every HUD session. Closing the browser tab isn't enough: Webswing sessions
never time out, so the ZAP process behind the tab keeps running, holds the `zap-home` lock
and keeps the single browser slot taken.

### Restarting

```
php my-sites-ide ide:zap-stop
php my-sites-ide ide:zap-hud <target>
```

Every launch starts a fresh ZAP session with the target context imported. Restart:

- **Between big scans.** JVM memory stays high after a scan (about half of the budget
  while idle), so the next scan starts with less headroom.
- **When switching browser or Chrome profile.** Webswing allows one browser client
  (`maxClients: 1`), tied to the browser that opened `/zap`. Any other browser, profile or
  incognito window is refused until the HUD is restarted.
- **When the HUD shows "Session ended" in a loop.** Try `ide:zap-hud-fix` first (see
  [Troubleshooting](#troubleshooting)).

### Starting from a clean slate

To start with no history, alerts or saved sessions from earlier runs:

```
php my-sites-ide ide:zap-stop
php my-sites-ide ide:zap-prune --logs
php my-sites-ide ide:zap-hud <target>
```

`ide:zap-prune` lists the saved sessions and asks before deleting them. Add `--dry-run`
to only list them. If you also want fresh credentials and seed URLs, run
`ide:zap-context <target>` before `ide:zap-hud`. Leave `contexts/<target>.zap-config.php`
in place: it's written by hand and isn't regenerated.

Then open `http://localhost:8080/zap` in one browser profile only. Use a dedicated Chrome
profile for the proxied browser: if the target's parent domain sends HSTS with
`includeSubDomains` (prod `smart-kitchen.io` does), Chrome won't let you click through
ZAP's certificate. If that profile has already cached the policy, delete the parent
domain's entry in `chrome://net-internals/#hsts`.

## Command reference

| Command | What it does |
|---|---|
| `ide:zap-context <target> [flags]` | Build or rebuild the auth context. Starts the daemon if needed. Scaffolds or writes the config. |
| `ide:zap-scan <url> [--context=] [--user=] [--full]` | Scan. With `--context`, uses the API-driven authenticated path. Without it, uses the unauthenticated wrapper scripts. |
| `ide:zap-daemon` | Start the headless daemon if it isn't running, reusing one that is. Used by the two commands above, rarely needed directly. |
| `ide:zap-hud [<target>]` | Launch the interactive browser UI. Stops a conflicting daemon or HUD first. Optional target prints the checklist and auto-imports its context. |
| `ide:zap-hud-fix` | Diagnose a stuck "Session ended" loop. Shows the lock error and the PIDs it finds, and asks before killing anything. |
| `ide:zap-prune [--keep=N] [--older-than=DAYS] [--logs] [--dry-run]` | Reclaim `zap-home` volume space. Lists every saved session (both `sessions/`, auto-created per HUD session, and `session/`, saved by name) with size and age, then deletes them after confirmation. Never deletes the session ZAP has open. `--logs` also removes rotated `zap.log.N` files. A 75k-request scan's session is about 2 GB. |
| `ide:zap-coverage <target>` | Diff recorded traffic against `write_routes`. Print the `livewire_actions` checklist. |
| `ide:zap-install-manifests <target> [--app-path=] [--user=] [--base-url=] [--force]` | Copy the `security:*` manifest commands into a Laravel target and add the manifest keys to its config. See [Route manifests](#route-manifests). |
| `ide:zap-stop` | Stop the HUD and daemon containers, ending the current ZAP session. Required after a HUD session, and the way to free Webswing's single browser slot ("Maximum number of clients reached"). Both are `--rm`, so stopping also removes them. |

## Configuration

Defaults live in the tracked `zaproxy/.env` and in `docker-compose.yml`. Overrides go in
the root `.env`, which is gitignored. The root `env-example` lists the override points.

| Variable | Default | Effect |
|---|---|---|
| `ZAP_ASCAN_THREADS_PER_HOST` | `2` | Active Scan threads per host. Applied through the API, then read back to confirm. |
| `ZAP_ASCAN_DELAY_MS` | `0` | Delay between Active Scan requests. |
| `ZAP_ASCAN_DOMXSS_STRENGTH` | `LOW` | DOM XSS attack strength. Resolved by scanner name, not id. |
| `ZAP_ASCAN_DATABASES` | (empty) | Database-specific injection rules to keep, comma-separated: `mysql`, `postgresql`, `oracle`, `mssql`, `hypersonic`, `sqlite`, `mongodb`. The rest are disabled, since their time-based probes can't find anything against another database and are slow. Empty runs them all. The generic SQL Injection rule always runs. Applied by `ide:zap-daemon` and in each HUD session, matched by rule name. |
| `ZAP_ASCAN_TIMEOUT_SECONDS` | `1800` | How long the CLI waits for an active scan. The scan keeps running server-side regardless. |
| `ZAP_TARGET_PASSWORD` | (unset) | Password for flag-based context generation. Keep it in the root `.env`, not `zaproxy/.env`. |
| `ZAP_TARGET_ALIAS` | `default.test` | Network alias nginx registers, so the container can resolve the target. Must be the exact hostname the context targets. Read by `servers/nginx/docker-compose.yml`. |
| `ZAP_MEM_LIMIT` | `5g` | Container memory cap. |
| `ZAP_CPUS` / `ZAP_CPUSET` | `4` / `0,1,2,3` | CPU quota and affinity. Both are needed. See below. |

**Resource limits.** All three, `mem_limit`, `cpus` and `cpuset`, are needed:

- `cpus` alone is a CFS time-share quota. It doesn't change what `nproc` or
  `Runtime.availableProcessors()` reports, so ZAP sizes its thread pools for the host's
  full core count. `cpuset` fixes that. Check with `docker exec zaproxy nproc`.
- Two cores couldn't keep up with on-the-fly TLS certificate signing during a full scan.
  The result was a backlog, then memory growth, then Webswing's heartbeat watchdog ending
  the session. Four cores is the tested setting.
- `JAVA_TOOL_OPTIONS=-Xss256k` (in `zaproxy/.env`) caps per-thread stack size. Without it,
  thousands of threads were consuming more memory in stacks than in heap.

**Settings that don't stick if passed on the command line.** `-config key=value` on
`zap.sh` is unreliable for anything an extension owns, such as Active Scan and Insights.
Core options like `api.disablekey` are fine. For extension settings, `ide:zap-daemon` sets
the value through the API after the daemon is up, then reads it back.

**Addons.** `ide:zap-daemon` uninstalls the Insights addon before starting the daemon.
Insights crashed under sustained load. The uninstall has to happen in a separate pass
against the persistent `zap-home` volume. Passing `-addonuninstall` alongside `-daemon` on
the same command line is silently ignored.

## Reports and sensitive files

Reports are written to `reports/<target-or-hostname>/report-<timestamp>.html`. The folder
is created before the scan. Everything under `reports/` is gitignored except `.gitkeep`.

- The `traditional-html-plus` template includes the captured request and response for each
  finding. It is roughly 60 times the size of the plain template, about 14.6 MB against
  240 KB in one measured case. It is always used.
- A `-plus` report is two pieces: `<name>.html` and a `<name>/` directory holding shared
  assets. Delete both together. Deleting only the HTML leaves an orphaned asset folder.
- Captured evidence includes live session cookies. They are encrypted Laravel payloads,
  not plaintext, but they are still real session tokens. Treat reports as sensitive.
- Exported contexts (`reports/<target>.context`) contain the user's credentials
  base64-encoded, which is not encryption. Decoding them takes one command. Never share
  or commit them.
- If a report doesn't appear where expected, `docker cp zaproxy:/home/zap/<file> .` pulls
  it from the container. ZAP's transparent save isn't reliable.

## Troubleshooting

**The HUD shows "Session ended" → "New session" in a loop.** Three different causes
produce the same symptom. Run `php my-sites-ide ide:zap-hud-fix` first. It reads the real
log and identifies which one applies.

1. **Daemon and HUD both running.** They share the `zap-home` lock. `ide:zap-hud` stops
   a running daemon before it launches, so this only happens if you start the daemon
   while the HUD is open.
2. **Two HUD sessions.** A second `ide:zap-hud` fails with "port is already allocated",
   or a stale container holds the lock. `ide:zap-hud` stops a running HUD container
   before launching.
3. **A stray ZAP process from an old browser tab.** Webswing sessions never time out, so
   closing a tab or switching Chrome profiles can leave a ZAP process holding the lock.
   The container still looks healthy to Docker, so nothing catches this automatically.
   `ide:zap-hud-fix` finds it and offers to kill it.

**HUD: "Too many connections" / "Maximum number of clients reached [1]" in
`webswing.out`.** The image's `webswing.config` sets `maxClients: 1`, and sessions are tied
to the browser that opened them (`CONTINUE_FOR_BROWSER`). Opening `/zap` from another
Chrome profile, browser or incognito window while a session is running counts as a second
client. Go back to the original browser, or run `ide:zap-stop` and relaunch with
`ide:zap-hud`. Don't raise `maxClients`: each client starts its own ZAP JVM, which doubles
memory, and both share the `zap-home` lock, which brings back the "Session ended" loop.

**HUD: "Failed to save the options: The URL to Poll must be specified for context Default
Context".** ZAP's built-in Default Context starts with a poll-URL verification strategy and
no poll URL, and Session Properties validates every context on save. Delete Default
Context (right-click it in the Sites tree). `ide:zap-hud <target>` removes it for you.

**HUD: no target context in the Sites tree.** Each HUD browser session is a fresh ZAP
session. Launch with `ide:zap-hud <target>` so it's imported, and check
`reports/<target>.context` exists. If it doesn't, run `ide:zap-context <target>` first.

**"Empty reply from server" when calling the API from the host.** Expected. Use the
`ide:*` commands, which go through `docker compose exec`.

**`Poll URL is not set` / authentication failures in the log.** A fresh context defaults
to a poll-based check with no poll URL. Re-run `ide:zap-context` with `--poll-url`. A
context that was never round-tripped through XML import has no `<pollurl>` element at all,
which the rebuild handles.

**A scan finishes suspiciously fast.** Check the demo user exists in the target database
and that its password matches the config. ZAP keeps trying to log in as a missing user,
and every request looks unauthenticated. Also check the log for repeated
`Authenticating user:` lines.

**429s in captured traffic.** Stockman's API and Livewire upload endpoints are
rate-limited. Scans need the limits relaxed. Set `SECURITY_API_RATE_LIMIT=0`,
`SECURITY_AI_API_RATE_LIMIT=0` and `SECURITY_LIVEWIRE_UPLOAD_RATE_LIMIT=0` in the target's
`.env`, clear the config cache, and restore them after the scan. This is a manual toggle
for now.

**Scan logs `Authentication failed for user: demo` and the report is empty, or the
active scan says `URL Not Found in the Scan Tree`.** This looks like a credentials problem,
but usually the spider never reached the site. ZAP's own log shows the failed login, and
the site tree is empty (`docker compose exec -T zaproxy curl -s http://localhost:8090/JSON/core/view/sites/`
returns `{"sites":[]}`). Check reachability before touching the user:

```
docker compose exec -T zaproxy sh -c 'getent hosts <target>; curl -sk -o /dev/null -w "%{http_code}\n" https://<target>/login'
```

A hostname that resolves to `localhost` or nothing, or a status of `000`, means the alias
is wrong. Fix it with the steps below, then rerun the scan.

**Target not reachable from the container.** The container can only reach the target
through the nginx network alias. Check three things:

1. `ZAP_TARGET_ALIAS` in the root `.env` is the exact hostname the context targets (for
   `local.smart-kitchen.io`, not `stockman.test` left over from another target). The
   default in `servers/nginx/docker-compose.yml` applies only when the variable is unset.
2. The target's vhost `server_name` includes that hostname.
3. The nginx container was recreated after changing the alias
   (`docker compose up -d nginx`). Changing the alias requires recreating the container, not
   restarting it. Check the result with `docker inspect nginx`, looking for the alias under
   `Aliases`.

Once the check above returns 200 from `/login`, confirm the demo login works from the
container before rerunning the scan. Logging in with the demo credentials should redirect
to `/home`.

**Chrome shows the site's own certificate, not ZAP's.** The browser is bypassing the
proxy. This is almost always a `*.localhost` hostname. Use a non-`.localhost` hostname.

**Chrome won't let you past a certificate warning.** HSTS was cached for the host. Delete
the entry at `chrome://net-internals/#hsts`. The dev vhost no longer sends HSTS.

If deleting the target's own entry doesn't help, the policy is on a **parent domain**. A
local hostname under a real domain (`local.smart-kitchen.io`) inherits production's
`Strict-Transport-Security: ...; includeSubDomains` once that browser profile has visited
the live site. Query the parent (`smart-kitchen.io`) on the same page, look for
`dynamic_sts_include_subdomains: true`, and delete the parent domain. Visiting production
again re-applies it, so use a dedicated browser profile for ZAP work.

## Known false positives

Scanner output is only a lead until its captured response confirms it.

- **Boolean SQLi on `_token`.** Laravel's CSRF middleware rejects any invalid token before
  it reaches app code. Any payload in `_token` returns a 419, which looks like a
  true/false difference to a boolean-SQLi detector. Check the response code. Token-like
  fields (CSRF, session IDs) produce this pattern.
- **"403 bypass" with a header like `x-original-url`.** The rule compares status codes
  across different URLs and doesn't check the body. The captured response was the normal
  authenticated homepage, not the content it claimed to unlock.

The general rule: read the captured response body, not just the risk label.

## Target-app requirements

A target needs:

- **A test user** that the context can log in as, with a password matching its config.
  Stockman uses `demo@example.com` from `DatabaseSeeder`. Reseeding can delete this user,
  which silently breaks authenticated scans.
- **A login form** with a logout form (or other element) that's present only when logged
  in, to use as the indicator.
- **The manifest commands** if you want `seed_urls`, `write_routes` and
  `livewire_actions`. smart-kitchen has `security:seed-urls`,
  `security:seed-write-routes`, `security:seed-livewire-actions` and
  `security:coverage-diff` (see [Route manifests](#route-manifests)). Without them, the
  spider starts from the target URL alone.
- **A dev vhost** that sends no HSTS header, so a browser can accept the ZAP certificate.

## Known gaps

- **Phase 5 is manual.** No CLI path exercises write-verb endpoints or Livewire actions
  yet. A browser walkthrough is needed for them.
- **Livewire coverage is best-effort.** The manifest only covers components that are
  routed directly. A component embedded only in another page's view has no URL to visit.
- **Rate-limit overrides are manual.** Relaxing the limits during a scan is a manual `.env`
  edit.
- **Config generation covers only the auth fields.** `ide:zap-context` doesn't add the
  manifests; run `ide:zap-install-manifests` afterwards.
- **Auth-form generation is not automated.** Deriving the login URL and indicator from the
  target's own routes is possible but not built. Credentials should stay a deliberate
  manual step.
- **The AJAX Spider isn't wired in.** It could drive edit actions that Livewire hides
  behind JavaScript, but it wouldn't handle create forms or `wire:confirm` dialogs.
