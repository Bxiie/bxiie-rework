# Bxiie reCAPTCHA, tenant CSS, slugs, subscribers, and stats deployment

Apply this package on the workstation repo only:

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
unzip /path/to/bxiie_recaptcha_css_stats_update.zip
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git add .
git commit -m "Add reCAPTCHA, tenant CSS, subscriber export, and location stats"
export GH_TOKEN='your-token'
git push origin main
```

Deploy production:

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/migrate_recaptcha_css_stats.php
sudo apachectl configtest
sudo systemctl reload apache2
```

Admin paths:

- `/admin/site`: reCAPTCHA keys, tenant CSS, tab labels, public slugs, about/contact image size.
- `/admin/subscribers`: collected emails.
- `/admin/subscribers/export`: CSV export.
- `/admin/stats`: location search and aggregation.

Location stats are populated from proxy geolocation headers first, then from request-IP lookup with a local hash-keyed cache in `ip_geolocations`.

# End of file.
