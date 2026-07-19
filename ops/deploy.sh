#!/usr/bin/env bash
#
# deploy.sh — pull the latest code from GitHub onto the live server + clear
# caches. Runs as ROOT (via `sudo` from the CI deploy, or by an admin).
#
# Because .gitignore only tracks our theme + plugin + ops + docs, the hard reset
# updates ONLY those files. WordPress core, third-party plugins, uploads/, and
# wp-config.php are untracked and are left completely untouched.
set -uo pipefail

WP_ROOT="/var/www/brandpharma"
BRANCH="main"
LOG="/var/log/brand-deploy.log"
log() { echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') $*" | tee -a "$LOG"; }

git config --system --add safe.directory "$WP_ROOT" 2>/dev/null || true
cd "$WP_ROOT" || { log "FATAL: $WP_ROOT missing"; exit 1; }

OLD=$(git rev-parse --short HEAD 2>/dev/null || echo none)
log "Deploy start (at $OLD) — fetching origin/$BRANCH"
if ! git fetch --quiet origin "$BRANCH" 2>>"$LOG"; then
  log "ERROR: git fetch failed"; exit 1
fi
git reset --hard --quiet "origin/$BRANCH" 2>>"$LOG"
NEW=$(git rev-parse --short HEAD)

# Keep our tracked code owned by the web user (reset writes as root).
chown -R www-data:www-data \
  "$WP_ROOT/wp-content/themes/botiga-child" \
  "$WP_ROOT/wp-content/plugins/peptidestore-core" 2>/dev/null || true

# Clear caches: WordPress object cache + PHP opcode cache.
sudo -u www-data wp --path="$WP_ROOT" cache flush >/dev/null 2>&1 || true
systemctl reload php8.2-fpm 2>/dev/null || true

if [ "$OLD" = "$NEW" ]; then
  log "Deploy done — already up to date ($NEW)"
else
  log "Deployed $OLD -> $NEW"
fi
