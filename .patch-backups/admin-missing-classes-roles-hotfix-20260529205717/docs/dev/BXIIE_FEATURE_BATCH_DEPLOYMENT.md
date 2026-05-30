# Bxiie Feature Batch Deployment

Apply these files on the development workstation repository only:

```text
/Users/bxiie/Dropbox/artsy/site/bxiie_rework
```

Then deploy through GitHub and production pull. Do not unzip/edit directly on the server.

## Local apply

Copy the update contents into the local repo, then run:

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git status
git add .
git commit -m "Add site settings, analytics, image editing, events editing, and newsletter modal"
export GH_TOKEN='paste-token-here'
git push origin main
```

## Production deploy

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' STORAGE_PATH='/var/lib/bxiie-cms/storage' php scripts/migrate_feature_batch.php
sudo apachectl configtest
sudo systemctl reload apache2
```

## Verification

```bash
curl -I https://bxiie.com/
curl -I https://bxiie.com/about
curl -I https://bxiie.com/contact
curl -I https://bxiie.com/admin/site
curl -I https://bxiie.com/admin/images
curl -I https://bxiie.com/admin/events
curl -I https://bxiie.com/admin/messages
curl -I https://bxiie.com/admin/stats
```

# End of file.
