# Auth routing and external provider wiring

Platform local login is available at `GET /login` and accepts password submissions at both `POST /login/password` and `POST /login` for compatibility with branded forms and landing-page links.

Platform signup is available at `GET /signup` and `POST /signup`; the duplicate marketing signup route was removed so the branded tenant-creation form is the active page.

Google and Facebook routes are now mounted:

- `GET /auth/google`
- `GET /auth/google/callback`
- `GET /auth/facebook`
- `GET /auth/facebook/callback`

Provider startup requires these environment variables:

- `ARTSFOLIO_GOOGLE_CLIENT_ID`
- `ARTSFOLIO_GOOGLE_CLIENT_SECRET`
- `ARTSFOLIO_FACEBOOK_CLIENT_ID`
- `ARTSFOLIO_FACEBOOK_CLIENT_SECRET`

Callbacks currently fail closed with an explicit 501 configuration/completion page until the provider token exchange and tenant-creation callback finalization are completed.

# End of file.
