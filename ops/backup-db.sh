#!/usr/bin/env bash
#
# backup-db.sh — encrypted database backup, pushed to the private backups repo.
# Runs ON THE VPS from cron (every 6 hours). Dumps brandpharma, gzips, encrypts
# with the backups GPG PUBLIC key (server cannot decrypt), commits + pushes to
# github.com/vnovshad/brand-pharma-backups, keeps 30 days, logs to
# /var/log/brand-backups.log.
set -uo pipefail

DB_SECRETS="/root/brand-secrets.txt"
BACKUP_REPO="/root/brand-pharma-backups"
GPG_RECIPIENT="backups@brandpharma.is"
RETAIN_DAYS=30
LOG="/var/log/brand-backups.log"
STATE_DIR="/var/lib/brand-backups"
STAMP="$(date -u +%Y%m%d-%H%M%S)"

log() { echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') [db] $*" >> "$LOG"; }

mkdir -p "$STATE_DIR"

[ -f "$DB_SECRETS" ] || { log "FATAL: $DB_SECRETS missing"; exit 1; }
set -a; . "$DB_SECRETS"; set +a   # DB_NAME, DB_USER, DB_PASS

cd "$BACKUP_REPO" || { log "FATAL: repo $BACKUP_REPO missing"; exit 1; }
OUT_DIR="$BACKUP_REPO/db"; mkdir -p "$OUT_DIR"
OUT="$OUT_DIR/brand-$STAMP.sql.gz.gpg"

# Stay current (server is sole writer, but be safe against a stale tree).
git pull -q --rebase --autostash origin main 2>>"$LOG" || log "WARN: git pull failed (continuing)"

# Dump -> gzip -> encrypt (MYSQL_PWD avoids the password-on-cmdline warning).
if MYSQL_PWD="$DB_PASS" mysqldump --no-tablespaces --single-transaction --quick \
       --default-character-set=utf8mb4 -u"$DB_USER" "$DB_NAME" 2>>"$LOG" \
   | gzip -9 \
   | gpg --batch --yes --trust-model always --encrypt --recipient "$GPG_RECIPIENT" -o "$OUT" 2>>"$LOG"; then
  log "encrypted dump created: $(basename "$OUT") ($(du -h "$OUT" | cut -f1))"
else
  log "ERROR: dump/encrypt failed for $STAMP"; rm -f "$OUT"; exit 1
fi

cp -f "$OUT" "$OUT_DIR/latest.sql.gz.gpg"   # stable pointer to newest

# Prune working-tree dumps older than RETAIN_DAYS (git history still retains them).
find "$OUT_DIR" -name 'brand-*.sql.gz.gpg' -type f -mtime +"$RETAIN_DAYS" -delete 2>>"$LOG"

git add db/ >/dev/null 2>&1
if git diff --cached --quiet; then
  log "nothing to commit"
else
  git commit -q -m "DB backup $STAMP" 2>>"$LOG"
  if git push -q origin main 2>>"$LOG"; then
    log "pushed DB backup $STAMP"
  else
    log "ERROR: git push failed for $STAMP"; exit 1
  fi
fi

date -u +%s > "$STATE_DIR/last-db-success"
log "SUCCESS db backup $STAMP"
