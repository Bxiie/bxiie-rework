# Browser Login

Routes:

```text
GET  /login
POST /login
GET  /logout
```

Cookie:

```text
artsfolio_session
```

Manual verification:

```bash
php scripts/test/browser_login_route.php
php -S 127.0.0.1:8080 -t public public/index.php
```

<!-- End of file. -->
