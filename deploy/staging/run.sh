#!/usr/bin/env bash
# Run one staging component in the foreground (each in its own terminal), or all of them in the background.
#   run.sh mysql | workers | nginx | scheduler | gateway | all
#   deploy/staging/stop.sh stops everything.
source "$(dirname "$0")/common.sh"

case "${1:?run.sh mysql|workers|nginx|scheduler|gateway|all}" in
mysql)
    log "run: mysql on 127.0.0.1:$MYSQL_PORT"
    exec "$MYSQL_HOME/bin/mysqld.exe" --defaults-file="$SHARED/my.ini" --console ;;
workers)
    exec "$REPO/deploy/staging/workers.sh" run ;;
nginx)
    log "run: nginx on $STAGING_URL"
    exec "$NGINX_HOME/nginx.exe" -p "$SHARED/nginx/" -c "$SHARED/nginx/nginx.conf" -g "daemon off;" ;;
scheduler)
    # Restart loop (defect S3): each start enters current/ afresh, so after a deploy or rollback
    # stops schedule:work, the next one runs the release current/ now points at.
    : > "$SHARED/run/scheduler.run"
    while [ -f "$SHARED/run/scheduler.run" ]; do
        log "run: scheduler (php artisan schedule:work) from $(cat "$SHARED/run/current-release")"
        ( cd "$STAGING/current/backend" && "$PHP" artisan schedule:work ) || true
        sleep 1
    done ;;
gateway)
    log "run: mock gateway on 127.0.0.1:9000 (HTTPS through nginx on 9444)"
    exec "$PHP" "$REPO/mock-gateway/start.php" ;;
all)
    for c in mysql workers nginx gateway scheduler; do
        nohup "$REPO/deploy/staging/run.sh" "$c" > "$SHARED/logs/run-$c.out" 2>&1 &
        sleep 2
    done
    for _ in $(seq 1 30); do [ "$(health)" = 200 ] && break; sleep 1; done
    echo "staging health: $(health)" ;;
esac
