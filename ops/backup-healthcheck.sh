#!/usr/bin/env bash
#
# backup-healthcheck.sh — runs hourly from cron. If the database backup hasn't
# succeeded in over 24 hours, write a WARNING to the backup log so the problem
# is visible (instead of silently going stale).
set -uo pipefail

STATE="/var/lib/brand-backups/last-db-success"
LOG="/var/log/brand-backups.log"
MAX_AGE=86400   # 24h

log() { echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') [health] $*" >> "$LOG"; }

now=$(date -u +%s)
if [ -f "$STATE" ]; then
  last=$(cat "$STATE" 2>/dev/null || echo 0)
  age=$(( now - last ))
  if [ "$age" -gt "$MAX_AGE" ]; then
    log "WARNING: no successful DB backup in $(( age / 3600 ))h (last: $(date -u -d "@$last" 2>/dev/null || date -u -r "$last" 2>/dev/null))"
  fi
else
  log "WARNING: no DB backup success recorded yet"
fi
