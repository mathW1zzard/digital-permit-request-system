#!/usr/bin/env bash
# Deploy a git commit, branch or tag to staging as a new release, then switch to it.
#
#   deploy/staging/deploy.sh <git-ref> [--skip-web]
#
# Steps (each logged to shared/logs/ops.log):
#   1. back up the staging database (backup.sh)
#   2. export <ref> into releases/<time>-<sha>/ with git archive (no working-copy changes)
#   3. composer install --no-dev --optimize-autoloader; storage -> shared/storage; copy shared/.env
#   4. Flutter Web release build for https://localhost:8444/api (or copy the current one with --skip-web)
#   5. php artisan migrate --force, config:cache, route:cache
#   6. point current/ at the new release, restart the PHP workers (OPcache does not watch files)
#   7. check GET /api/health; if it is not 200 the script stops and tells you to run rollback.sh
source "$(dirname "$0")/common.sh"

REF="${1:?usage: deploy.sh <git-ref> [--skip-web]}"
SKIP_WEB="${2:-}"
SHA="$(git -C "$REPO" rev-parse --short=7 "$REF^{commit}")"
REL="$STAGING/releases/$(date +%Y%m%d-%H%M%S)-$SHA"

log "deploy: start $REF ($SHA) -> $(basename "$REL")"
if [ -f "$SHARED/run/current-release" ]; then
    "$REPO/deploy/staging/backup.sh" "pre-deploy-$SHA"
fi

mkdir -p "$REL"
git -C "$REPO" archive "$SHA" backend client | tar -x -C "$REL"
printf '%s\n' "$SHA" > "$REL/REVISION"
git -C "$REPO" log -1 --format='%H %cI %s' "$SHA" >> "$REL/REVISION"

( cd "$REL/backend"
  "$PHP" "$LARAGON/composer/composer.phar" install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
  rm -rf storage && junction storage "$SHARED/storage"
  cp "$SHARED/.env" .env )
log "deploy: backend installed (no dev packages)"

if [ "$SKIP_WEB" = "--skip-web" ] && [ -d "$STAGING/current/web" ]; then
    cp -r "$STAGING/current/web" "$REL/web"
    log "deploy: web build copied from the current release (--skip-web)"
else
    ( cd "$REL/client" && flutter pub get >/dev/null && \
      flutter build web --release --dart-define=API_BASE_URL="$STAGING_URL/api" >/dev/null )
    mv "$REL/client/build/web" "$REL/web"
    log "deploy: web build made for $STAGING_URL/api"
fi
rm -rf "$REL/client"

( cd "$REL/backend"
  "$PHP" artisan migrate --force --no-interaction --no-ansi | tail -n 3
  "$PHP" artisan config:cache --no-interaction >/dev/null
  "$PHP" artisan route:cache --no-interaction >/dev/null )
log "deploy: migrations applied, config and routes cached"

PREVIOUS="$(cat "$SHARED/run/current-release" 2>/dev/null || true)"
junction "$STAGING/current" "$REL"
printf '%s\n' "$PREVIOUS" > "$REL/PREVIOUS_RELEASE"
printf '%s\n' "$REL" > "$SHARED/run/current-release"
"$REPO/deploy/staging/workers.sh" restart
log "deploy: current -> $(basename "$REL"), workers restarted"

for _ in $(seq 1 20); do code="$(health)"; [ "$code" = 200 ] && break; sleep 1; done
log "deploy: health check $code"
if [ "$code" != 200 ]; then
    log "deploy: FAILED health check. Roll back with: deploy/staging/rollback.sh"
    exit 1
fi
log "deploy: done $SHA"
