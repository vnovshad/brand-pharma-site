#!/usr/bin/env bash
#
# sync-media.sh — mirror the WordPress uploads/ folder into the private backups
# repo. Runs ON THE VPS from cron (daily). Uploads are public marketing images +
# COAs (already served on the site), so they are stored UNencrypted — unlike the
# database, which contains customer data and is GPG-encrypted by backup-db.sh.
#
# NOTE: GitHub rejects single files >100 MB and gets unhappy past ~1 GB total.
# This script aborts the commit if any upload exceeds 95 MB and logs a warning.
set -uo pipefail

WP_UPLOADS="/var/www/brandpharma/wp-content/uploads"
BACKUP_REPO="/root/brand-pharma-backups"
LOG="/var/log/brand-backups.log"
STATE_DIR="/var/lib/brand-backups"

log() { echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') [media] $*" >> "$LOG"; }
mkdir -p "$STATE_DIR"

cd "$BACKUP_REPO" || { log "FATAL: repo $BACKUP_REPO missing"; exit 1; }
git pull -q --rebase --autostash origin main 2>>"$LOG" || log "WARN: git pull failed (continuing)"

# Refuse any file too large for GitHub.
BIG=$(find "$WP_UPLOADS" -type f -size +95M 2>/dev/null | head -5)
if [ -n "$BIG" ]; then
  log "ERROR: file(s) over 95MB present — aborting (GitHub 100MB limit). Move media to object storage:"
  echo "$BIG" | while read -r f; do log "  too big: $f"; done
  exit 1
fi

# Mirror uploads (delete removed files; skip cache/staging).
rsync -a --delete \
  --exclude 'cache/**' --exclude 'blog-image-staging/**' \
  "$WP_UPLOADS"/ "$BACKUP_REPO/uploads"/ 2>>"$LOG"

git add uploads/ >/dev/null 2>&1
if git diff --cached --quiet; then
  log "media unchanged"
else
  git commit -q -m "Media sync $(date -u +%Y%m%d-%H%M%S)" 2>>"$LOG"
  if git push -q origin main 2>>"$LOG"; then
    log "pushed media sync"
  else
    log "ERROR: media push failed"; exit 1
  fi
fi

date -u +%s > "$STATE_DIR/last-media-success"
log "SUCCESS media sync"
