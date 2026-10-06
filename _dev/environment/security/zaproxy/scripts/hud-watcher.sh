#!/bin/sh
# Runs inside the HUD container alongside zap-webswing.sh (started by
# `ide:zap-hud`, mounted read-only at /zap/scripts), configuring every ZAP
# session over the API as it comes up.
#
# Webswing only starts ZAP once a browser opens /zap, and starts a fresh ZAP
# process (fresh session, no contexts, default options) for every new browser
# session - so a one-off setup at launch can't work. This polls instead.
#
# Active-scan limits, applied to any session whose thread count doesn't match
# (i.e. a fresh one): `-config ascan.threadPerHost` in ZAP_WEBSWING_OPTS
# doesn't stick (an extension-owned setting, same as in ide:zap-daemon), and
# a HUD scan at ZAP's default 8 threads ran 8 headless Firefoxes for the DOM
# XSS rule and got the JVM OOM-killed. Also enables only the database-specific
# rules for ZAP_ASCAN_DATABASES. Rules are resolved by name, with jq (shipped
# in the image), not pinned IDs.
#
# With a target context: imports it into any session that lacks it, and
# removes ZAP's built-in "Default Context" - in this ZAP version it starts
# with a poll-URL verification strategy but no poll URL, so Session Properties
# refuses to save anything until it's fixed or gone. Then locks the session to
# the target: every other host is excluded from the proxy (still passed
# through to the browser, never recorded), the context is set in scope, and
# Protected mode stops any scan or attack touching anything out of scope.
#
# Environment (set by ZapHudCommand):
#   ZAP_HUD_THREADS, ZAP_HUD_DELAY_MS, ZAP_HUD_DOMXSS_STRENGTH   always
#   ZAP_HUD_DB_ENABLE, ZAP_HUD_DB_DISABLE                        rule-name regexes, may be empty
#   ZAP_HUD_CONTEXT_NAME                                         empty = no target context
#   ZAP_HUD_CONTEXT_FILE, ZAP_HUD_PROXY_EXCLUDE                  URL-encoded, with a context

API=http://localhost:8090/JSON

# Comma-separated IDs of the active-scan rules whose name matches regex $1
ids() {
  curl -s "$API/ascan/view/scanners/" | jq -r --arg re "$1" '[.scanners[] | select(.name | test($re)) | .id] | join(",")'
}

call() {
  curl -s -o /dev/null "$API/$1"
}

apply_scan_limits() {
  call "ascan/action/setOptionThreadPerHost/?Integer=$ZAP_HUD_THREADS"
  call "ascan/action/setOptionDelayInMs/?Integer=$ZAP_HUD_DELAY_MS"
  call "ascan/action/setScannerAttackStrength/?id=$(ids '^Cross Site Scripting \(DOM Based\)$')&attackStrength=$ZAP_HUD_DOMXSS_STRENGTH"
  [ -n "$ZAP_HUD_DB_ENABLE" ] && call "ascan/action/enableScanners/?ids=$(ids "$ZAP_HUD_DB_ENABLE")"
  [ -n "$ZAP_HUD_DB_DISABLE" ] && call "ascan/action/disableScanners/?ids=$(ids "$ZAP_HUD_DB_DISABLE")"
}

apply_context() {
  call "context/action/removeContext/?contextName=Default%20Context"
  call "context/action/importContext/?contextFile=$ZAP_HUD_CONTEXT_FILE"
  call "context/action/setContextInScope/?contextName=$ZAP_HUD_CONTEXT_NAME&booleanInScope=true"
  call "core/action/excludeFromProxy/?regex=$ZAP_HUD_PROXY_EXCLUDE"
  call "core/action/setMode/?mode=protect"
}

while true; do
  # No response (ZAP not started yet) matches neither branch, so nothing runs
  threads=$(curl -s "$API/ascan/view/optionThreadPerHost/")
  case "$threads" in
    *\""$ZAP_HUD_THREADS"\"*) ;;
    *ThreadPerHost*) apply_scan_limits ;;
  esac

  if [ -n "$ZAP_HUD_CONTEXT_NAME" ]; then
    list=$(curl -s "$API/context/view/contextList/")
    case "$list" in
      *\""$ZAP_HUD_CONTEXT_NAME"\"*) ;;
      *contextList*) apply_context ;;
    esac
  fi

  sleep 3
done
