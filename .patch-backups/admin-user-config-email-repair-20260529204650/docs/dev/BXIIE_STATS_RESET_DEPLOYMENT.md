# Bxiie Stats Reset Deployment

## Purpose

Adds tenant-scoped usage-stat reset support and displays the start time for the current stats reporting window on `/admin/stats`.

## Apply locally

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
unzip /path/to/bxiie_stats_reset_update.zip
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git add .
git commit -m "Add usage stats reset controls"
export GH_TOKEN='your-token'
git push origin main
```

## Deploy production

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/migrate_stats_reset.php
sudo apachectl configtest
sudo systemctl reload apache2
```

## Verify

```bash
curl -I https://bxiie.com/admin/stats
```

Then visit `/admin/stats`, confirm the stats start date is shown, and test reset by typing `RESET` in the reset form.

## Rollback

Revert the Git commit and redeploy. If stats were reset, page view rows cannot be recovered unless restored from database backup.

# End of file.
