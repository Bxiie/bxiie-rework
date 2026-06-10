# Artwork placement and auth/email repair

Migration `0033_homepage_artwork_assignments.sql` adds explicit home-page artwork assignment/order support.

The alternate artwork placement UI lives in `App\Http\Controllers\Tenant\Admin\ArtworkPlacementController`.

Global password reset routes are registered in `public/index.php` so they work on `artsfol.io`, tenant subdomains, and custom tenant domains.

Email logo branding depends on `BrandedEmail` using `/assets/logo_2.png` and `SmtpEmailSender` sending multipart/alternative when `body_html` exists.

<!-- End of file. -->
