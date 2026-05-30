# Bxiie Exhibitions and Contact Messages Cleanup

## Summary

This update removes the global newsletter modal from every public page, adds editable text/table display settings for recent exhibitions, and adds contact-message deletion from the admin area.

## Workstation deployment

Apply this package in the local repository:

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
unzip /path/to/bxiie_exhibitions_messages_cleanup.zip
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git add .
git commit -m "Clean up newsletter modal and improve exhibitions/messages admin"
export GH_TOKEN='your-token'
git push origin main
```

## Production deployment

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/migrate_exhibitions_messages_cleanup.php
sudo apachectl configtest
sudo systemctl reload apache2
```

## Verification

```bash
curl -I https://bxiie.com/about
curl -I https://bxiie.com/admin/messages
curl -I https://bxiie.com/admin/site
```

# End of file.
