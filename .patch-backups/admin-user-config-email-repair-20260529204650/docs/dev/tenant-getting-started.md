# Tenant Getting Started

## Purpose

New tenant signups redirect to:

```text
/admin/getting-started
```

The page gives the first tenant admin a short launch checklist:

```text
create portfolio sections
upload first artwork
choose site identity
test contact/signup plumbing
```

## Route

```text
GET /admin/getting-started
```

This route is mounted only inside the tenant route block.

## Signup behavior

Platform signup now attempts to create a browser session for the new admin and redirect directly to the getting-started page.

Production URL:

```text
https://<slug>.artsfol.io/admin/getting-started
```

Local URL when `APP_ENV=local` and `ARTSFOLIO_LOCAL_DEV_PORT=8080`:

```text
http://<slug>.artsfol.io:8080/admin/getting-started
```

<!-- End of file. -->
