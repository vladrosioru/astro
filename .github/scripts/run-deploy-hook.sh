#!/usr/bin/env bash
#
# Call one of the token-guarded deploy hooks (public/extract.php,
# public/deploy.php) and verify the server actually ran it.
#
# Why this exists: the cPanel host sits behind a WAF that answers clients it
# doesn't recognise with an HTTP *200* "One moment, please…" JS challenge page
# instead of proxying the request to PHP. `curl -fsS` only fails on 4xx/5xx, so
# a challenged deploy step went green while nothing was extracted and the site
# kept serving the previous build. Three defences:
#
#   1. Send browser-shaped headers. The WAF passes those through — the same
#      request with a bot user-agent and `Accept: text/plain` gets challenged.
#      Necessary, but no longer sufficient: browser-shaped requests still get
#      challenged sometimes (observed 2026-08-02).
#   2. Behave like the browser the challenge expects. The challenge page sets a
#      cookie and its own JS reloads the page after 5 s; the reload carries the
#      cookie and is proxied through. So keep a cookie jar and ask again rather
#      than giving up on the first challenge.
#   3. Require the hook's own success marker in the body. Any other 200 — a
#      challenge page that never clears, an error page, a redirect to a parking
#      page — fails the step loudly instead of silently skipping the deploy.
#
# Retrying is safe for both hooks: extract.php re-extracts the same uploaded
# archive and deploy.php's migrate/cache steps are idempotent. A retry only
# happens when the marker is absent, i.e. when PHP was never reached at all.
#
# Usage: run-deploy-hook.sh URL MARKER TOKEN [MAX_TIME]
# Env:   HOOK_ATTEMPTS (default 4), HOOK_RETRY_DELAY seconds (default 15)
#
set -euo pipefail

url="${1:?hook url required}"
marker="${2:?success marker required}"
token="${3:?deploy token required}"
max_time="${4:-600}"

attempts="${HOOK_ATTEMPTS:-4}"
delay="${HOOK_RETRY_DELAY:-15}"

cookies="$(mktemp)"
trap 'rm -f "$cookies"' EXIT

attempt=1
while :; do
    # One retry policy for both hooks; extract.php is the slow one (unzip).
    # --retry covers transport errors and 5xx; the loop below covers the 200
    # challenge, which curl cannot see as a failure.
    body=$(curl -fsS --retry 3 --retry-delay 10 --max-time "$max_time" \
        -A "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36" \
        -H "Accept: text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8" \
        -H "X-Deploy-Token: ${token}" \
        -c "$cookies" -b "$cookies" \
        "$url")

    printf '%s\n' "$body"

    if printf '%s' "$body" | grep -qF "$marker"; then
        echo "Verified: ${url} reported \"${marker}\"."
        exit 0
    fi

    if [ "$attempt" -ge "$attempts" ]; then
        echo "::error::${url} answered 200 but not with its own output (expected \"${marker}\") on all ${attempts} attempts. The hook did not run — the body above is usually the host WAF's challenge page. The upload succeeded, so re-running this workflow is safe."
        exit 1
    fi

    echo "::warning::${url} answered 200 without \"${marker}\" (attempt ${attempt}/${attempts}) — that is the host WAF's challenge page. Waiting ${delay}s and retrying with the cookie it just set, which is what the challenge's own JS does."
    sleep "$delay"
    attempt=$((attempt + 1))
done
