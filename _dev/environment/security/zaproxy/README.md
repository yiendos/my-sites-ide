# ZAP scanning methodology

Two ways to drive OWASP ZAP against a target: **command line** (headless, repeatable,
scriptable) and **browser** (the ZAP Desktop UI, via `ide:zap-hud`). Neither replaces
the other — CLI gives breadth and repeatability, the browser gives depth and covers
what automation structurally can't reach. Use them together, in this order.

## One-time setup per target

```
php my-sites-ide ide:zap-context <target>
```

First run scaffolds `contexts/<target>.zap-config.php` from `example.zap-config.php` —
edit the placeholder login URL, indicator regex, poll URL, scope and user credentials,
then re-run the same command. Rebuilds the context from scratch every time, so it's
always safe to re-run after editing. Exports to `reports/<target>.context`.

## Phase 1 — CLI: fast baseline (passive only)

```
php my-sites-ide ide:zap-scan https://<host> --context=<target> --user=<name>
```

No `--full`. Passive-only — headers, cookies, disclosed info, obvious misconfig.
Minutes, not tens of minutes. Run this on every change; it's cheap enough to be routine.

## Phase 2 — CLI: full active scan (attack payloads)

```
php my-sites-ide ide:zap-scan https://<host> --context=<target> --user=<name> --full
``` 

Adds the Active Scanner — SQLi, XSS, path traversal, etc. — against everything the
spider plus `seed_urls` reached. Slower (~10–15 min at current tuning); run on a
cadence (pre-release, nightly) rather than every commit.

**Coverage note — `seed_urls` is GET-only by design, not by oversight.** It feeds
the spider, and a spider seed has no body — a bare request to a PUT/PATCH/DELETE/POST
route just 405s or 422s before reaching app code. So any endpoint hit only via a JS
`fetch()` with no matching `<a href>`/`<form>` markup is invisible to Phase 1/2
**if it's a write verb**. GET-shaped JS-fetch endpoints (lookups, `/edit`-style
param routes) *are* covered — `seed_urls` resolves `{route-model-binding}` params to
real ids specifically so those get seeded. Write verbs are the actual gap; see Phase 4.

## Phase 3 — CLI: read the evidence, don't trust labels

Open the `traditional-html-plus` report (has per-finding request/response, not just
description/solution text) and go finding-by-finding on anything High/Medium.
**Scanner findings need scrutiny before being treated as real** — confirmed false
positives so far: a boolean-SQLi flag on Laravel's `_token` CSRF field (differential
419-vs-200 response, never touched SQL), and a "403 bypass" whose captured response
was just the normal authenticated homepage, not the bypassed content it claimed.
Shortlist only findings whose captured response actually shows the claimed effect.

## Phase 4 — Browser (HUD): validate the shortlist live

```
php my-sites-ide ide:zap-hud
```

Opens the ZAP Desktop UI (webswing) at `http://localhost:8080/zap`. Proxy a real
browser through it (FoxyProxy scoped to the target host only — check the cert
*Issuer* is ZAP's MITM cert, not the site's own, as the sanity check that traffic
is actually proxied; note Chrome/Firefox never proxy `*.localhost` regardless of
config — use a non-reserved TLD like `<target>.test` for anything going through ZAP).

For each shortlisted finding from Phase 3, replay it through this proxied browser —
same payload, but live: pivot the parameter, chain it with something the scanner
wouldn't try, confirm it's real before reporting or demoing it.

## Phase 5 — Browser (HUD): cover write-verb endpoints

This is the step that actually plugs the Phase 2 gap. `seed_urls` cannot seed a
PUT/PATCH/DELETE/POST route (no body to send), so those endpoints never get
crawled, never get into ZAP's history, and never get Active-Scanned — no CLI
command reaches them. The browser is currently the *only* way to close this:

Using the same HUD session as Phase 4, click through the real UI actions that
trigger each write verb — add to shopping list, submit a recipe URL, edit a stock
quantity, delete an item. The JS client builds the real request with real field
names and real ids, sidestepping the problem of guessing a body that would pass
validation. ZAP records every proxied request into its session as a side effect;
Active Scan can then mutate those recorded messages exactly as it would a spidered
GET.

**Today this is ad hoc and unverified** — there is no checklist, so a missed write
route is a silent gap, the same way the spider silently missed dropdown-nested
links before `seed_urls` existed. Don't imply full endpoint coverage from a Phase 5
pass until the target state below exists.

### Target state — planned, not yet built

1. **Non-GET manifest generator** — a companion to `SecuritySeedUrlsCommand`'s
   `isSeedable()` (`Repos/stockman`), reusing the same route-model-binding
   resolution and admin/login/infra exclusion filters, but emitting `{method, uri}`
   pairs for every PUT/PATCH/DELETE/POST route instead of visitable URLs.
2. That manifest becomes the actual input to Phase 5's walkthrough — a checklist,
   not a vibe — whether driven by a human in the HUD or, later, a scripted journey
   (Selenium/Playwright; check first whether Stockman already has Dusk set up
   before adding new browser-automation infra).
3. **Coverage diff, not a hope**: after the walkthrough, diff what ZAP actually
   recorded (`core/view/messages`, filtered by method+URL) against the manifest.
   Anything with zero matching recorded messages is a route nobody exercised —
   a visible gap instead of a silent one.

## Phase 6 — Fix → re-run as regression

Once a finding is fixed, re-run Phase 1 (or Phase 2, if it was payload-based) to
confirm it's gone (if it was a write-verb finding surfaced via Phase 5, re-run
Phase 5's manual walkthrough too — there's no CLI regression path for those yet).
Same evidence-scrutiny discipline from Phase 3 applies to a "clean" result too —
don't trust the absence of an alert without checking what was actually covered.

## Command reference

```
php my-sites-ide ide:zap-context <target>              # build/rebuild auth context
php my-sites-ide ide:zap-scan <url> --context=<target> --user=<name>          # baseline
php my-sites-ide ide:zap-scan <url> --context=<target> --user=<name> --full   # full active scan
php my-sites-ide ide:zap-hud                            # interactive browser (webswing)
docker compose stop zaproxy                             # tear down when done
```
