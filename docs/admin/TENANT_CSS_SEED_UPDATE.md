# Tenant CSS Seed Update

This update adds a migration/helper script that populates editable tenant-specific CSS from the existing checked-in CSS file.

## File added

```text
scripts/populate_tenant_css_from_existing.php
```

## Local workflow

Apply this package from the workstation repository:

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
unzip /path/to/bxiie_populate_tenant_css_update.zip
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git add .
git commit -m "Populate tenant CSS from existing site CSS"
export GH_TOKEN='your-token'
git push origin main
```

## Production deployment

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/populate_tenant_css_from_existing.php --tenant=bxiie
sudo apachectl configtest
sudo systemctl reload apache2
```

## If CSS already exists

The script will not overwrite existing tenant CSS unless forced.

To overwrite:

```bash
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/populate_tenant_css_from_existing.php --tenant=bxiie --force
```

## If the script cannot find CSS

Pass the file explicitly:

```bash
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/populate_tenant_css_from_existing.php --tenant=bxiie --css=/var/www/bxiie-cms/public/assets/css/site.css
```

# End of file.
