# Disaster Recovery Runbook — Brand Pharma

**Goal:** host dies → fully working site on a fresh VPS in ~30–60 minutes.

You need three things, all stored off the dead host:
1. **Code** — the `brand-pharma-site` GitHub repo (this repo).
2. **Database** — the latest encrypted dump in the `brand-pharma-backups` GitHub repo.
3. **Media** — the `uploads/` folder in your object storage (Backblaze B2 / S3 / Spaces).
4. **Your GPG private key** — kept somewhere safe and OFF the server (password manager,
   encrypted USB). Without it the DB backups cannot be decrypted. **This is the one
   irreplaceable secret — guard it.**

---

## Recovery steps

### 1. Provision a new VPS (1984 or any host)
- Image: **Debian Bookworm — LEMP (nginx + php8.2 + mariadb10.11)** (same as production).
- Add your SSH key. Note the new server's IP.

### 2. Create the database
```bash
mysql -e "CREATE DATABASE brandpharma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'brand'@'localhost' IDENTIFIED BY 'NEW_STRONG_PASSWORD';"
mysql -e "GRANT ALL ON brandpharma.* TO 'brand'@'localhost'; FLUSH PRIVILEGES;"
```

### 3. Get the code
```bash
cd /var/www && git clone git@github.com:vnovshad/brand-pharma-site.git brandpharma
```
WordPress core isn't in the repo — install it into the same root (download core,
or `wp core download`), keeping our `wp-content/themes/botiga-child` and
`wp-content/plugins/peptidestore-core` from the clone.

### 4. Recreate wp-config.php (NOT in git — secrets)
Copy `wp-config-sample.php` → `wp-config.php`, set DB name/user/password/host,
and paste fresh salts from https://api.wordpress.org/secret-key/1.1/salt/ .

### 5. Restore the database (decrypt → import)
```bash
git clone git@github.com:vnovshad/brand-pharma-backups.git
gpg --decrypt brand-pharma-backups/db/latest.sql.gz.gpg | gunzip | \
  mysql -u brand -p brandpharma
```
(Use the timestamped file instead of `latest` if you need an earlier point in time.)

### 6. Restore media
```bash
rclone copy backup:brand-uploads /var/www/brandpharma/wp-content/uploads
```

### 7. Reinstall third-party plugins
WooCommerce, OxaPay, Botiga (parent theme), etc. are not in git. Install them
(same versions) via wp-admin or `wp plugin install`. Their *settings* come back
with the database — only the plugin code needs reinstalling.

### 8. Point the domain
Update the domain's **A record** to the new server IP. Once DNS propagates,
re-issue HTTPS (`certbot --nginx`). Confirm checkout + a test order.

---

## Restore checklist
- [ ] Site loads over HTTPS
- [ ] Products + prices show, images load (media restored)
- [ ] Blog posts + featured/inline images present
- [ ] Checkout shows payment methods (Interac, OxaPay)
- [ ] Crypto 10% discount applies on the crypto method
- [ ] A test order completes

## Notes on the backups themselves
- DB backups run every few hours (`ops/backup-db.sh`), encrypted with your GPG
  **public** key. Decryptable only with your **private** key.
- Media syncs daily (`ops/sync-media.sh`).
- Worst-case data loss = time since the last DB backup. Tighten the cron interval
  for a busier store.
