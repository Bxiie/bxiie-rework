# ArtsFolio HTTP Routes

Developer route documentation is available in the application at `/help/developer` after login.

This hotfix specifically verifies these browser routes:

- `GET /help`
- `GET /help/{article}`
- `GET /developer`
- `GET /pricing`
- `GET /directory`
- `POST /logout`
- `POST /login`
- `POST /login/password`

The public pricing page is served by `App\\Http\\Controllers\\Platform\\PricingController`.
The public directory page is served by `App\\Http\\Controllers\\Platform\\DirectoryController`.
The combined help/developer section is served by `App\\Http\\Controllers\\Platform\\HelpController`.

<!-- End of file. -->
