# Public Contact and Signup Routes Administration

## Current public routes

```text
GET  /contact
POST /contact
GET  /
POST /signup
```

## Current protections

```text
CSRF token
basic required-field validation
email format validation
```

## Production requirements

```text
reCAPTCHA or equivalent spam protection
rate limiting
abuse logging
privacy policy copy
consent language for email signup
admin moderation tools
CSV export
```

<!-- End of file. -->
