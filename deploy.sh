#!/usr/bin/env bash
#
# Deploys Wedding Venue AI to the Plesk server.
#
# Run it from your laptop, from the project root, after pushing to GitHub:
#
#   ./deploy.sh              # build assets, upload them, update the server
#   ./deploy.sh --skip-build # reuse the public/build already on disk
#   ./deploy.sh --dry-run    # show what would happen, change nothing
#
# What it does:
#   1. checks that the working tree is clean and the branch is pushed
#   2. builds the frontend locally (the server has no Node) and uploads public/build
#   3. on the server: git pull, composer install, migrate, rebuild caches,
#      fix permissions and restart the queue worker
#
# Override the defaults with environment variables, e.g.
#   SSH_HOST=plesk APP_URL=https://example.com ./deploy.sh

set -euo pipefail

SSH_HOST="${SSH_HOST:-plesk}"
APP_DIR="${APP_DIR:-/var/www/vhosts/fts-tech.co.id/wedding-venue-ai.fts-tech.co.id}"
APP_USER="${APP_USER:-fts-tech.co.id_md8klw10xxd}"
APP_GROUP="${APP_GROUP:-psacln}"
PHP_BIN="${PHP_BIN:-/opt/plesk/php/8.4/bin/php}"
COMPOSER_PHAR="${COMPOSER_PHAR:-/usr/local/psa/var/modules/composer/composer.phar}"
BRANCH="${BRANCH:-master}"
APP_URL="${APP_URL:-https://wedding-venue-ai.fts-tech.co.id}"

SKIP_BUILD=false
DRY_RUN=false

for arg in "$@"; do
    case "$arg" in
        --skip-build) SKIP_BUILD=true ;;
        --dry-run) DRY_RUN=true ;;
        -h | --help)
            sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            echo "Unknown option: $arg (try --help)" >&2
            exit 1
            ;;
    esac
done

step() { printf '\n\033[1;35m==> %s\033[0m\n' "$1"; }
fail() { printf '\033[1;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

# Runs a command, or only prints it in dry-run mode.
run() {
    if $DRY_RUN; then
        printf '[dry-run] %s\n' "$*"
    else
        "$@"
    fi
}

cd "$(dirname "$0")"

step "Checking the repository"

[ -f artisan ] || fail "Run this from the project root (artisan not found)."

current_branch="$(git rev-parse --abbrev-ref HEAD)"
[ "$current_branch" = "$BRANCH" ] || fail "You are on '$current_branch'; deploys come from '$BRANCH'."

if [ -n "$(git status --porcelain)" ]; then
    fail "The working tree has uncommitted changes. Commit and push them first."
fi

git fetch --quiet origin "$BRANCH"
if [ "$(git rev-parse HEAD)" != "$(git rev-parse "origin/$BRANCH")" ]; then
    fail "Local '$BRANCH' and origin/$BRANCH differ. Push (or pull) first, the server deploys from GitHub."
fi

echo "Deploying $(git rev-parse --short HEAD): $(git log -1 --pretty=%s)"

if $SKIP_BUILD; then
    step "Skipping the frontend build"
    [ -d public/build ] || fail "public/build does not exist; run without --skip-build."
else
    step "Building the frontend"
    run npm ci --no-audit --no-fund --ignore-scripts
    run npm run build
fi

step "Uploading public/build"
run rsync -az --delete public/build/ "$SSH_HOST:$APP_DIR/public/build/"

step "Updating the server"

if $DRY_RUN; then
    echo "[dry-run] ssh $SSH_HOST: git pull, composer install, migrate, cache, permissions, queue restart"
else
    ssh "$SSH_HOST" bash -s -- "$APP_DIR" "$APP_USER" "$APP_GROUP" "$PHP_BIN" "$COMPOSER_PHAR" "$BRANCH" <<'REMOTE'
set -euo pipefail

app_dir="$1"; app_user="$2"; app_group="$3"; php_bin="$4"; composer_phar="$5"; branch="$6"

cd "$app_dir"

as_app() { sudo -u "$app_user" -H env COMPOSER_HOME="$app_dir/.composer" "$@"; }

echo "-- git pull"
as_app git pull --ff-only origin "$branch"

echo "-- composer install"
as_app "$php_bin" "$composer_phar" install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet

echo "-- migrate"
as_app "$php_bin" artisan migrate --force

echo "-- caches"
as_app "$php_bin" artisan optimize:clear --quiet
as_app "$php_bin" artisan config:cache --quiet
as_app "$php_bin" artisan route:cache --quiet
as_app "$php_bin" artisan view:cache --quiet

echo "-- permissions"
chown -R "$app_user:$app_group" "$app_dir" 2>/dev/null || true
find "$app_dir/public" -type d -exec chmod 755 {} + 2>/dev/null || true
find "$app_dir/public" -type f -exec chmod 644 {} + 2>/dev/null || true
chmod -R ug+rwX "$app_dir/storage" "$app_dir/bootstrap/cache"
chmod 640 "$app_dir/.env"

echo "-- queue restart"
as_app "$php_bin" artisan queue:restart --quiet
REMOTE
fi

step "Checking the live site"

if $DRY_RUN; then
    echo "[dry-run] curl $APP_URL/"
else
    status="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$APP_URL/")"
    [ "$status" = "200" ] || fail "$APP_URL/ answered HTTP $status after the deploy."
    echo "$APP_URL/ answered HTTP $status"
fi

printf '\n\033[1;32mDone.\033[0m\n'
