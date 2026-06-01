# Tenant form JSON responses

Tenant contact and email-list forms are progressively enhanced with `/assets/tenant-forms.js`.

The public tenant `ContactController` and `SignupController` must return JSON when the request includes `Accept: application/json` or `X-Requested-With: XMLHttpRequest`. Ordinary browser posts still receive a 303 redirect with the same query-string status markers.

This prevents the JavaScript form handler from following the redirect back to a complete tenant page and displaying the entire page text inside the inline error result box.

Manual verification:

```bash
php -l Http/Controllers/Tenant/ContactController.php
php -l Http/Controllers/Tenant/SignupController.php
php -l Http/Controllers/Tenant/HomeController.php
php -l Http/Controllers/Tenant/Admin/UsersController.php
php -l Http/Controllers/Platform/Admin/UsersController.php
php -l Platform/Identity/AdminUserRepository.php
php -l public/index.php
./scripts/test/preflight.sh
```

# End of file.
