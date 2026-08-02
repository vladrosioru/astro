#!/usr/bin/env bash
#
# Fetch one URL from the live site through the host's WAF and write the body to
# a file. Prints the HTTP status code on stdout, everything else on stderr, so
# callers can do `S=$(fetch-site.sh "$BASE/en" resp.html)`.
#
# Why this exists: the host answers requests it doesn't recognise with an HTTP
# *200* "One moment, please…" JS challenge page. Three things get past it, and
# all three are needed:
#
#   1. Browser-shaped headers (`User-Agent` + explicit `Accept`). Without an
#      Accept header the WAF returns 415; with a bot UA it always challenges.
#   2. A **shared cookie jar**. The challenge is cleared by a cookie, so a fresh
#      curl per request is challenged from scratch — that is exactly how
#      test_dev's "wait for dev" loop spent 12 attempts on 12 challenge pages
#      (2026-08-02) while the cookie-carrying deploy hook sailed through in the
#      same minute. The jar defaults to one path per job, so the challenge is
#      solved once and every later step rides on it.
#   3. Reading the body. The challenge is a 200, so a status-only check treats a
#      site the deploy never reached as healthy.
#
# Usage: fetch-site.sh URL OUT_FILE [MAX_TIME]
# Env:   WAF_COOKIE_JAR, SITE_ATTEMPTS (default 6), SITE_RETRY_DELAY (default 5),
#        UA, WAF_CHALLENGE_MARKER
#
set -euo pipefail

url="${1:?url required}"
out="${2:?output file required}"
max_time="${3:-30}"

jar="${WAF_COOKIE_JAR:-${RUNNER_TEMP:-/tmp}/waf-cookies.txt}"
attempts="${SITE_ATTEMPTS:-6}"
delay="${SITE_RETRY_DELAY:-5}"
marker="${WAF_CHALLENGE_MARKER:-One moment, please}"
ua="${UA:-Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36}"

attempt=1
while :; do
    status=$(curl -s \
        -A "$ua" \
        -H "Accept: text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8" \
        -c "$jar" -b "$jar" \
        -o "$out" -w '%{http_code}' \
        --max-time "$max_time" \
        "$url" || echo "000")

    if ! grep -qF "$marker" "$out" 2>/dev/null; then
        printf '%s\n' "$status"
        exit 0
    fi

    if [ "$attempt" -ge "$attempts" ]; then
        echo "::error::${url} served the host WAF's challenge page on all ${attempts} attempts — the cookie never cleared it. Nothing was verified about the site." >&2
        printf '%s\n' "$status"
        exit 1
    fi

    echo "attempt ${attempt}/${attempts}: WAF challenge page, retrying in ${delay}s with the cookie it just set..." >&2
    sleep "$delay"
    attempt=$((attempt + 1))
done
