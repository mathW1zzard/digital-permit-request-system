#!/usr/bin/env bash
# Week 17: watch staging during a trial and raise an alert, so a problem is noticed while it happens
# (in Week 16, defect S2 showed "degraded" for hours and nobody saw it).
#
#   deploy/staging/watch.sh [interval-seconds]      default 30; stop with Ctrl+C
#
# Rules (from docs/week16/32-monitoring-and-incidents.md):
#   ALERT  /api/health answers 503 or does not answer at all       (users are affected now)
#   WARN   /api/health stays "degraded" for 10 minutes             (background work has stopped)
#   WARN   new "exception.unhandled" lines in the application log   (a fault, even if users see none)
# Each alert is printed, written to shared/logs/alerts.log, and sounds the terminal bell.
source "$(dirname "$0")/common.sh"

INTERVAL="${1:-30}"
LOG="$SHARED/storage/logs/permit.jsonl"
ALERTS="$SHARED/logs/alerts.log"
degraded_since=""
seen_exceptions="$(grep -c '"exception.unhandled"' "$LOG" 2>/dev/null || echo 0)"

alert() { # alert <level> <message>
    local line; line="$(date -u +%Y-%m-%dT%H:%M:%SZ) $1 $2"
    printf '\a%s\n' "$line"; printf '%s\n' "$line" >> "$ALERTS"
}

echo "Watching $STAGING_URL/api/health every ${INTERVAL}s. Alerts go to $ALERTS. Ctrl+C to stop."
while true; do
    body="$(curl -s --max-time 15 --ssl-no-revoke --cacert "$REPO/certs/dev-ca.pem" -w '\n%{http_code}' "$STAGING_URL/api/health" || true)"
    code="$(printf '%s' "$body" | tail -n 1)"
    status="$(printf '%s' "$body" | head -n 1 | sed -n 's/.*"status":"\([a-z_]*\)".*/\1/p' | head -n 1)"

    if [ "$code" != 200 ]; then
        alert ALERT "readiness ${code:-no answer}${status:+ ($status)}: users are affected"
        degraded_since=""
    elif [ "$status" = degraded ]; then
        [ -z "$degraded_since" ] && degraded_since="$(date +%s)"
        mins=$(( ($(date +%s) - degraded_since) / 60 ))
        if [ "$mins" -ge 10 ]; then
            alert WARN "degraded for ${mins} min: $(printf '%s' "$body" | head -n 1 | grep -o '"[a-z_]*":{"status":"\(stale\|backlog\|fail\)"' | tr '\n' ' ')"
            degraded_since="$(date +%s)"   # repeat at most every 10 minutes
        fi
    else
        degraded_since=""
    fi

    now_exceptions="$(grep -c '"exception.unhandled"' "$LOG" 2>/dev/null || echo 0)"
    if [ "$now_exceptions" -gt "$seen_exceptions" ]; then
        alert WARN "$((now_exceptions - seen_exceptions)) new unhandled exception(s): $(grep '"exception.unhandled"' "$LOG" | tail -n 1 | sed -n 's/.*"exception":"\([^"]*\)".*/\1/p')"
        seen_exceptions="$now_exceptions"
    fi
    sleep "$INTERVAL"
done
