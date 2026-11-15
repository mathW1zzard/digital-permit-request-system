#!/usr/bin/env bash
# Stop every staging component (nginx, workers, scheduler, gateway, MySQL). Data stays in shared/.
source "$(dirname "$0")/common.sh"

"$NGINX_HOME/nginx.exe" -p "$SHARED/nginx/" -c "$SHARED/nginx/nginx.conf" -s quit 2>/dev/null || true
"$REPO/deploy/staging/workers.sh" stop
for port in 9000; do
    for pid in $(netstat -ano | awk -v p=":$port" '$2 ~ p"$" && $4 == "LISTENING" {print $5}' | sort -u); do
        taskkill //F //PID "$pid" >/dev/null 2>&1 || true
    done
done
# The scheduler is "php artisan schedule:work" started from current/backend, inside run.sh's loop.
rm -f "$SHARED/run/scheduler.run"
powershell -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" | Where-Object { \$_.CommandLine -like '*schedule:work*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }" || true
MYSQL_PWD="$(cat "$SHARED/secrets/mysql-root.txt")" \
    "$MYSQL_HOME/bin/mysqladmin.exe" --protocol=TCP -h 127.0.0.1 -P "$MYSQL_PORT" -u root shutdown 2>/dev/null || true
log "stop: staging stopped"
