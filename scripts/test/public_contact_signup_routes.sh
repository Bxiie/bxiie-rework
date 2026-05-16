#!/bin/bash
set -euo pipefail

PROJECT_ROOT="/Users/bxiie/Dropbox/tcdev/artsfolio"
cd "${PROJECT_ROOT}"

php -l app/Http/Controllers/Tenant/ContactController.php
php -l app/Http/Controllers/Tenant/SignupController.php
php -l app/Http/Controllers/Tenant/HomeController.php
php -l public/index.php

echo "Syntax checks passed."
echo
echo "Manual browser verification:"
echo "  php -S 127.0.0.1:8080 -t public"
echo "  open http://127.0.0.1:8080/contact with Host bxiie.com via curl or local vhost"
echo
echo "Curl GET form:"
echo "  curl -H 'Host: bxiie.com' http://127.0.0.1:8080/contact"
