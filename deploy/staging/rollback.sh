#!/usr/bin/env bash
# Roll staging back.
#
#   rollback.sh                    point current/ at the previous release and restart the workers.
#                                  The database is left as it is; use this when the new code is
#                                  faulty but its migrations are backward compatible.
#   rollback.sh --restore <file>   also restore the database from a backup.sh file. Use this when a
#                                  migration or data change must be undone. Writes made after the
#                                  backup are lost, so take a backup of the current state first.
source "$(dirname "$0")/common.sh"

CURRENT="$(cat "$SHARED/run/current-release")"
PREVIOUS="$(cat "$CURRENT/PREVIOUS_RELEASE" 2>/dev/null || true)"
[ -n "$PREVIOUS" ] && [ -d "$PREVIOUS" ] || { log "rollback: no previous release recorded"; exit 1; }

log "rollback: start $(basename "$CURRENT") -> $(basename "$PREVIOUS")"

if [ "${1:-}" = "--restore" ]; then
    FILE="${2:?rollback.sh --restore <backup file>}"
    "$REPO/deploy/staging/backup.sh" "before-restore" >/dev/null
    MYSQL_PWD="$(cat "$SHARED/secrets/mysql-root.txt")" mysql_cli -u root < "$FILE"
    log "rollback: database restored from $(basename "$FILE")"
fi

junction "$STAGING/current" "$PREVIOUS"
printf '%s\n' "$PREVIOUS" > "$SHARED/run/current-release"
"$REPO/deploy/staging/workers.sh" restart
restart_scheduler
for _ in $(seq 1 20); do code="$(health)"; [ "$code" = 200 ] && break; sleep 1; done
log "rollback: current -> $(basename "$PREVIOUS"), health $code"
[ "$code" = 200 ]
