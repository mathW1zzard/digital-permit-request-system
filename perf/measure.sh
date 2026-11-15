#!/usr/bin/env bash
# One measured run: records the environment, samples CPU and memory, runs the load model,
# and saves health before and after.
#   LOAD_PASSWORD=... perf/measure.sh <label> <api-base> <model.json> <out-dir> [sample-seconds]
set -euo pipefail
LABEL="$1"; BASE="$2"; MODEL="$3"; OUT="$4"; SECS="${5:-0}"
REPO="$(cd "$(dirname "$0")/.." && pwd -W)"
CURL=(curl -s --ssl-no-revoke --cacert "$REPO/certs/dev-ca.pem")
mkdir -p "$OUT"

{
  echo "label: $LABEL"
  echo "api: $BASE"
  echo "model: $MODEL"
  echo "repo commit: $(git -C "$REPO" rev-parse --short HEAD)"
  echo "machine: $(powershell -NoProfile -Command "(Get-CimInstance Win32_Processor).Name.Trim() + ', ' + [Environment]::ProcessorCount + ' logical cores, ' + [math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory/1GB,1) + ' GB RAM'")"
  echo "started (UTC): $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "health before: $("${CURL[@]}" "$BASE/health")"
} > "$OUT/00-run-info.txt"

if [ "$SECS" -gt 0 ]; then
  powershell -NoProfile -ExecutionPolicy Bypass -File "$REPO/perf/sample-resources.ps1" -Seconds "$SECS" -Out "$(cygpath -w "$OUT")\\resources.csv" &
  SAMPLER=$!
fi

node "$REPO/perf/loadgen.mjs" --base "$BASE" --model "$MODEL" --out "$OUT" --ca "$REPO/certs/dev-ca.pem" --label "$LABEL" > "$OUT/console.txt" 2>&1 || true

echo "health after: $("${CURL[@]}" "$BASE/health")" >> "$OUT/00-run-info.txt"
echo "ended (UTC): $(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$OUT/00-run-info.txt"
[ -n "${SAMPLER:-}" ] && wait "$SAMPLER" || true
cat "$OUT/summary.txt" 2>/dev/null || cat "$OUT/console.txt"
