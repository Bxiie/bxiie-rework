# Portfolio Sections and Tenant CSS Update

## What changed

- Portfolio sections can now be created and edited from `/admin/portfolio`.
- Image creation and image editing now include a multi-select portfolio section control.
- Image/section links are tenant-validated before saving.
- The default public CSS file is now formatted for human editing and includes useful comments.
- The tenant CSS seeding script now defaults to `tenant_css`, not the obsolete `custom_css` key.
- CSS content stored in the database has trailing `/* End of file. */` comments removed.

## Apply from workstation

```bash
cd /Users/bxiie/Dropbox/artsy/site/bxiie_rework
unzip /path/to/bxiie_portfolio_sections_css_update.zip
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git add .
git commit -m "Add portfolio section editing and readable tenant CSS"
export GH_TOKEN='your-token'
git push origin main
```

## Deploy on production

```bash
ssh bxiie@bxiie.com
sudo /usr/local/bin/bxiie-git-pull
cd /var/www/bxiie-cms
sudo -u www-data DATABASE_PATH='/var/lib/bxiie-cms/database/bxiie.sqlite' php scripts/migrate_portfolio_sections_and_tenant_css.php --force
sudo apachectl configtest
sudo systemctl reload apache2
```

## Verification

```bash
curl -ks https://bxiie.com/tenant.css | head -40
curl -I https://bxiie.com/admin/portfolio
curl -I https://bxiie.com/admin/images
```

Then check:

- `/admin/portfolio` has Add and Edit controls.
- `/admin/images` has a multi-select section chooser on image upload.
- `/admin/images/edit?id=...` has a multi-select section chooser on image edit.
- `/admin/site` shows readable tenant CSS without `End of file` comments.

# End of file.
