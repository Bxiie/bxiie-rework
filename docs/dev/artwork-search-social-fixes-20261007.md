# Artwork display, search and Instagram posting — 7 October 2026

Implemented and tested in the local checkout. Not deployed. The specific Curves CAD Study image still requires source-media investigation/restoration; the full database preflight is also blocked by the absent local MariaDB service.

## Findings and changes

### Artwork image bounds

The live `https://bxiie.com/artwork/curves-cad-study` page was inspected in Chromium. Its image response has intrinsic dimensions **640 × 640**; the browser rendered it at **720 × 720**, with `object-fit: contain`, no clip path, and visible overflow on the paragraph and main container. The entire served square is visible. The composition is already cut off at the file edges. This is not evidence of a CSS cover crop on that page. Production media files/database records are absent from this checkout, so whether the uploaded source, original-variant mapping, or cached derivative introduced that crop is unconfirmed.

Added a shared main-display class and stylesheet to public detail and admin editing views. It uses intrinsic proportions, contain, automatic width/height, a viewport-height limit, and square image corners. The public image now uses a figure instead of a prose paragraph. Directory thumbnails, site-image pickers, and explicit Instagram crop modes retain their intentional crop styles.

Audit: the current media endpoint defaults to `original`; regular variant resizing scales by the largest dimension and copies the entire source canvas. Watermark rendering retains the artwork canvas dimensions; transparent-canvas trimming operates on the watermark graphic. Legacy public/admin previews use proportional sizing. Regression tests check all four source corners after resizing and actual browser geometry for portrait, landscape and square images.

**Remaining:** inspect media UUID `fe58c426-b6ed-11f1-8ba2-cef86f42fc2c` in production, compare the media asset path and original variant record with the uncropped upload, and restore/rebind the correct original if necessary. Rebuild affected variants and invalidate the affected watermark/browser cache after correcting the source. CSS cannot restore pixels missing from the delivered file.

### Search

Public portfolio pagination had no name-query input or repository filter. It now applies a parameterized title search to both the count and data queries before pagination. Section navigation, page links and page-size changes preserve the search; clearing it preserves section/page size and resets the page. Empty results have search-specific copy. Out-of-range pages clamp to the filtered result count; the existing 100-item selector is now honored instead of silently capping at 96.

The admin artworks page already searched titles, medium and descriptions. Its UI now identifies name search, offers a separate Clear search link that preserves filters/sort/page size, and retains Reset all filters. Repeated SQL placeholders were replaced with distinct parameters for compatibility with native PDO prepares. `%`, `_`, and the escape character are treated literally on both pages. The repeated-placeholder issue is a compatibility defect, not a confirmed cause of a production search failure; the current connection does not explicitly disable emulated prepares.

### Instagram posting

The previous request parser silently selected scheduling whenever the submit-button `action` was missing. A submitter-less form serialization can therefore turn an immediate-post request into the reported blank-schedule 422. Explicit `post_now` already bypassed the date parser. The exact failed production request was not available, so the reason its action was absent is not confirmed.

Publishing mode is now a named select independent of the submit button. New posts default to Post now, existing-post editing defaults to Schedule for later, and explicit legacy button actions remain accepted. Missing/invalid modes are rejected instead of guessed. Immediate posts ignore any schedule field, save UTC now, and wake the publisher. Scheduling requires exactly one matching future UTC instant; invalid dates, past/current times, DST gaps and repeated times are rejected. Fixed-offset timezones are also handled without a PHP warning. The existing selected timezone (`UserTimezoneContext`, the signed-in tenant user's preference) remains the authority for both the displayed label and conversion; no separate tenant timezone setting was introduced.

The generic 422 page incorrectly hardcoded “Could not create site.” It now uses neutral submission wording while preserving the specific validation message. The signup regression check was updated accordingly.

## Files changed for this task

- `app/Http/Controllers/Tenant/HomeController.php`
- `app/Tenant/Artwork/ArtworkReadRepository.php`
- `app/Http/Controllers/Tenant/Admin/ArtworksController.php`
- `app/Http/Controllers/Tenant/SocialFrontController.php`
- `app/Http/View/AdminLayout.php`
- `app/Http/View/TenantAdminLayout.php`
- `app/Http/View/ErrorPage.php`
- `public/assets/artwork-display.css` (new)
- `public/assets/social-publishing.js`
- `scripts/test/artwork_name_search.php` (new)
- `scripts/test/artwork_full_bounds.php` (new)
- `scripts/test/social_post_timing.php` (new)
- `scripts/test/social_post_requests.php` (new)
- `scripts/test/artwork_display_social_form.cjs` (new)
- `scripts/test/signup_owner_role_and_branding_static.php`
- `scripts/test/preflight.sh`
- This report and `artwork-search-social-existing-static-failures.txt`.

Pre-existing edits, including changes in overlapping files, were preserved. Other files shown by git status were not changed for this task.

## Validation

Passed:

- New PHP regressions: `artwork_name_search.php`, `artwork_full_bounds.php`, `social_post_timing.php`, `social_post_requests.php`.
- Search tests execute public repository queries and both public/admin controllers against isolated SQLite data, including count/order/pagination, visibility, tenant isolation, special characters, navigation and clearing. MariaDB aggregate syntax is adapted only in the fixture.
- Social tests exercise actual controller/repository persistence, account binding, carousel rows, publisher wakeup, invalid-request isolation, rendered compose controls, UTC conversion, DST gaps/folds, fixed/fractional offsets, and legacy explicit actions. SQLite adapts aggregate/advisory-lock syntax; these tests do not contact Instagram or validate MariaDB locking semantics.
- Browser tests: 12 main-image geometry cases across phone/desktop widths, actual rendered compose HTML, submitter-independent form serialization, required/disabled scheduling controls, and preserved directory/Instagram crops.
- Existing regressions: archived artwork visibility, artwork detail edit/section links, portfolio section headings, pagination, tenant admin layouts, Instagram subsystem static checks, artwork AJAX pagination/page-size controls, artwork SQL stability, branded errors and signup branding.
- PHP syntax scan: 734 files passed; the subsequently added social request test and subsequently edited tests also passed syntax checks. JavaScript syntax, preflight shell syntax, and `git diff --check` passed.
- Static suite: **277 of 310 passed** after updating the obsolete site-creation wording expectation. All 33 remaining failures also fail in an isolated checkout of HEAD; names are in the companion file.

Limitations:

- `scripts/test/admin_layout.php` fails with an existing array-to-string warning and obsolete stylesheet expectation. Reproduced on HEAD.
- `bash scripts/test/preflight.sh` stops at `resolve_tenant.php`: local MariaDB connection refused, confirmed outside the sandbox. Full database/migration checks could not run.
- No live Instagram post was sent and no production database/media was modified.

Run the new checks from the repository root:

```sh
php scripts/test/artwork_name_search.php
php scripts/test/artwork_full_bounds.php
php scripts/test/social_post_timing.php
php scripts/test/social_post_requests.php
NODE_PATH="$PWD/artsfolio-video-factory/node_modules" node scripts/test/artwork_display_social_form.cjs
```

## Deployment

No dependencies or schema migrations are required. Deploy the changed PHP files and both assets together; their HTML references include new cache versions. Review overlapping pre-existing edits before packaging. Run the full preflight against an isolated configured MariaDB database, then verify search and posting in staging. Source-media restoration for the named artwork remains a separate required step before declaring the original clipping report resolved.

## Release preparation

Prepared on top of remote main `6038696`, preserving its newer portfolio sort control, homepage hero, artwork image replacement and export features. Search and clearing now explicitly preserve the visitor-selected sort. The corresponding sort regression was updated, and SQLite-dependent fixtures are skipped with an explicit message on production hosts without pdo_sqlite.

Release-checkout validation passed: all four new PHP regressions, portfolio public sort, admin Home Page filter, home hero, social publishing, pagination/page sizes, error/signup branding, archived visibility, detail edit/section links, changed-file PHP syntax, JavaScript/shell syntax and the browser geometry/form/crop tests.

Additional changed file: `scripts/test/portfolio_public_sort_static.php`.

Production access attempt using the documented `bxiie@bxiie.com` login failed with `Permission denied (publickey,password)`. The deployment script has not been run. A working production SSH connection is required.

## Follow-up: large-catalog form truncation

The repeated validation error revealed the confirmed cause of the missing publishing mode: Compose emitted four crop/order fields for every carousel candidate, including unselected artwork. Production has 345 candidates, so those 1,380 fields exceed PHP's default `max_input_vars=1000`. PHP discards the mode/date controls at the end. The earlier small-catalog tests did not cover this case.

Each image's crop/order controls now live inside a fieldset disabled on the server for unselected artwork. JavaScript enables/disables that fieldset when selection or restored snapshot state changes. Only the selected images contribute these fields, keeping even a ten-image carousel well under the limit without changing server limits. Explicit crop choices survive deselection/reselection. New script cache version: `20261008-catalog-inputs`.

`scripts/test/social_large_catalog.cjs` renders the real Compose controller with 500 candidate artworks and sends browser FormData through PHP `parse_str` with `max_input_vars=1000`. It reproduced truncation before the fix and passes afterward, including the server-rendered form before JavaScript, ten selections near the end of the catalog, immediate and scheduled modes, the schedule date, and crop persistence. Existing social controller/timing/static and browser regressions also passed. No real Instagram post is sent by these tests.
