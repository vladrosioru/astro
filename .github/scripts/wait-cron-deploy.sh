#!/usr/bin/env bash
#
# Wait for cPanel's scheduled cron-deploy.php to process the uploaded app.zip
# and write `deploy-result.txt` (`OK <deploy_id>` or `FAIL <deploy_id>`),
# polling exclusively over FTPS (never HTTP, so Imunify360 cannot interfere).
#
# Usage: wait-cron-deploy.sh FTP_HOST FTP_USER FTP_PASS DEPLOY_ID [TIMEOUT_SEC]
#
set -euo pipefail

ftp_host="${1:?ftp_host required}"
ftp_user="${2:?ftp_user required}"
ftp_pass="${3:?ftp_pass required}"
deploy_id="${4:?deploy_id required}"
timeout_sec="${5:-150}"

result_file="${RUNNER_TEMP:-/tmp}/deploy-result-${deploy_id}.txt"
deadline=$(( $(date +%s) + timeout_sec ))

echo "Waiting up to ${timeout_sec}s for cPanel cron deploy (${deploy_id})..."

while :; do
    rm -f "$result_file"
    if curl -sS --ssl-reqd --connect-timeout 15 --max-time 20 \
        "ftp://${ftp_host}/deploy-result.txt" \
        --user "${ftp_user}:${ftp_pass}" \
        -o "$result_file" 2>/dev/null; then
        first_line=$(head -n 1 "$result_file" | tr -d '\r')
        if [ "$first_line" = "OK ${deploy_id}" ]; then
            cat "$result_file"
            exit 0
        fi
        if [ "$first_line" = "FAIL ${deploy_id}" ]; then
            cat "$result_file"
            echo "::error::cPanel cron deploy failed for ${deploy_id}" >&2
            exit 1
        fi
    fi

    if [ "$(date +%s)" -ge "$deadline" ]; then
        echo "::error::Timed out after ${timeout_sec}s waiting for cPanel cron deploy (${deploy_id}). Check that cron-deploy.php is scheduled every minute in cPanel Cron Jobs." >&2
        exit 1
    fi

    sleep 5
done
