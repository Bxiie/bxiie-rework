# Platform Signup Local Redirects

## Problem

Production signup redirects to:

```text
https://<slug>.artsfol.io/login
```

Local development usually runs the PHP built-in server over HTTP with a port, for example:

```text
http://127.0.0.1:8080
```

A production-style HTTPS redirect causes browser errors during local smoke tests.

## Local environment variables

Set these when running local browser tests:

```bash
APP_ENV=local
ARTSFOLIO_LOCAL_DEV_PORT=8080
ARTSFOLIO_ENV_FILE=.env.local
```

## Local server command

```bash
APP_ENV=local ARTSFOLIO_LOCAL_DEV_PORT=8080 ARTSFOLIO_ENV_FILE=.env.local \
  php -S 127.0.0.1:8080 -t public public/index.php
```

## Local hosts entry

For a test tenant slug such as `testsite`, add:

```text
127.0.0.1 testsite.artsfol.io
```

Then visit:

```text
http://testsite.artsfol.io:8080/login
```

## Production behavior

Production remains:

```text
https://<slug>.artsfol.io/login
```

<!-- End of file. -->
