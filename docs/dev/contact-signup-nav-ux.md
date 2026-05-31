# Contact, Signup, and Navigation UX

Tenant contact and email signup submissions now return users to the branded tenant page with inline success or error notices. Forms show an activity indicator while submitting, successful submissions clear form fields by rendering a fresh page, and reCAPTCHA failures stay on the branded page.

Tenant public reCAPTCHA keys are tenant-specific only. Do not rely on the platform key for tenant or custom-domain pages because Google validates the hostname configured for the site key. Configure tenant keys in Tenant Admin for each tenant domain that needs spam protection.

Tenant contact pages include an email list signup form. Public tenant pages also show an email-list signup dialog after one minute unless the visitor has already dismissed it in local browser storage.

Navigation styling now reserves stable tab width on tenant and platform public pages so active links and optional links do not cause the main tabs to jump between pages.

# End of file.
