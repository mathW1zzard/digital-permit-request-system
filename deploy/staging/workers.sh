#!/usr/bin/env bash
# The four PHP FastCGI workers behind nginx.
#   workers.sh run       run all four in the foreground, each restarted if it exits (supervisor loop)
#   workers.sh restart   stop the running php-cgi processes; the supervisor loops start fresh ones
#   workers.sh stop      stop the supervisor loops and the workers
source "$(dirname "$0")/common.sh"

pids_on() { netstat -ano | awk -v p=":$1" '$2 ~ p"$" && $4 == "LISTENING" {print $5}' | sort -u; }

kill_workers() {
    for port in $WORKER_PORTS; do
        for pid in $(pids_on "$port"); do taskkill //F //PID "$pid" >/dev/null 2>&1 || true; done
    done
}

case "${1:-run}" in
run)
    : > "$SHARED/run/workers.run"
    for port in $WORKER_PORTS; do
        (
            while [ -f "$SHARED/run/workers.run" ]; do
                # PHP_FCGI_MAX_REQUESTS=0: do not exit after 500 requests (the php-cgi default).
                PHP_FCGI_MAX_REQUESTS=0 PHP_FCGI_CHILDREN=0 \
                    "$PHP_HOME/php-cgi.exe" -c "$SHARED/php-staging.ini" -b "127.0.0.1:$port" || true
                [ -f "$SHARED/run/workers.run" ] && log "workers: worker on $port exited; restarting"
                sleep 1
            done
        ) &
    done
    log "workers: 4 workers on $WORKER_PORTS"
    wait
    ;;
restart)
    kill_workers
    for _ in $(seq 1 10); do [ -z "$(pids_on 9101)" ] && break; sleep 0.5; done
    for _ in $(seq 1 20); do [ -n "$(pids_on 9104)" ] && break; sleep 0.5; done
    ;;
stop)
    rm -f "$SHARED/run/workers.run"
    kill_workers
    log "workers: stopped"
    ;;
esac
