#!/usr/bin/env bash
#
# Call one of the token-guarded deploy hooks (public/extract.php,
# public/deploy.php) and verify the server actually ran it.
#
# Why this exists: the cPanel host sits behind a WAF that answers clients it
# doesn't recognise with an HTTP *200* "One moment, please…" JS challenge page
# instead of proxying the request to PHP. `curl -fsS` only fails on 4xx/5xx, so
# a challenged deploy step went green while nothing was extracted and the site
# kept serving the previous build. Two defences:
#
#   1. Send browser-shaped headers. The WAF passes those through — the same
#      request with a bot user-agent and `Accept: text/plain` gets challenged.
#   2. Require the hook's own success marker in the body. Any other 200 — a
#      challenge page, an error page, a redirect to a parking page — fails the
#      step loudly instead of silently skipping the deploy.
#
# Usage: run-deploy-hook.sh URL MARKER TOKEN [MAX_TIME]
#
set -euo pipefail

url="${1:?hook url required}"
marker="${2:?success marker required}"
token="${3:?deploy token required}"
max_time="${4:-600}"

# One retry policy for both hooks; extract.php is the slow one (unzip).
body=$(curl -fsS --retry 3 --retry-delay 10 --max-time "$max_time" \
    -A "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36" \
    -H "Accept: text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.8" \
    -H "X-Deploy-Token: ${token}" \
    "$url")

printf '%s\n' "$body"

if ! printf '%s' "$body" | grep -qF "$marker"; then
    echo "::error::${url} answered 200 but not with its own output (expected \"${marker}\"). The hook did not run — the body above is usually the host WAF's challenge page. The upload succeeded, so re-running this workflow is safe."
    exit 1
fi

echo "Verified: ${url} reported \"${marker}\"."
