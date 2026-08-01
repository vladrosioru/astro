#!/usr/bin/env bash
#
# Emit a production .env to stdout, filled from environment variables that the
# workflow injects from the current GitHub Environment (dev or production).
#
# Usage (from a deploy job): bash .github/scripts/make-env.sh > release/.env
#
# Values are double-quoted so passwords with spaces survive. Avoid a literal
# double-quote (") in any secret value.
set -euo pipefail

cat <<EOF
APP_NAME="AstroTherapia"
APP_ENV=production
APP_KEY=${APP_KEY:?APP_KEY is required}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:?APP_URL is required}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE="${DB_DATABASE:?DB_DATABASE is required}"
DB_USERNAME="${DB_USERNAME:?DB_USERNAME is required}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD is required}"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

# Shared host disables symlink()/exec(); serve uploads from a real public/storage
PUBLIC_DISK_IN_DOCROOT=true

# Post-deploy hook auth (public/deploy.php)
DEPLOY_TOKEN=${DEPLOY_TOKEN:?DEPLOY_TOKEN is required}

# Admin Database page. Restore is fail-closed: only the dev GitHub environment
# sets DB_RESTORE_ENABLED=true, so prod returns 404 for the restore route.
# MEDIA_FALLBACK_URL is prod's origin, so restored content on dev loads prod's
# images (media URLs are stored root-relative and the files stay on prod).
DB_RESTORE_ENABLED=${DB_RESTORE_ENABLED:-false}
MEDIA_FALLBACK_URL="${MEDIA_FALLBACK_URL:-}"

# Copy prod -> dev button (dev environment only). Read-only prod DB creds so
# dev can dump live prod content. Absent on prod => the button 404s/hides.
PROD_DB_HOST="${PROD_DB_HOST:-127.0.0.1}"
PROD_DB_PORT="${PROD_DB_PORT:-3306}"
PROD_DB_DATABASE="${PROD_DB_DATABASE:-}"
PROD_DB_USERNAME="${PROD_DB_USERNAME:-}"
PROD_DB_PASSWORD="${PROD_DB_PASSWORD:-}"

MAIL_MAILER=smtp
MAIL_HOST=${MAIL_HOST:-localhost}
MAIL_PORT=${MAIL_PORT:-587}
MAIL_USERNAME="${MAIL_USERNAME:-}"
MAIL_PASSWORD="${MAIL_PASSWORD:-}"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-contact-form@astrotherapia.com}"
MAIL_FROM_NAME="\${APP_NAME}"
EOF
