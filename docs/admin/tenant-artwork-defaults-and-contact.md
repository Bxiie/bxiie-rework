# Tenant artwork defaults and contact context

Tenant administrators can choose whether newly uploaded artwork starts as an unpublished draft or is published immediately. The setting is under **Tenant Admin → Settings → Miscellaneous → New artwork defaults**. It affects only future uploads.

When a visitor opens the tenant contact form from an artwork page, ArtsFolio carries the artwork slug through the form. On submission, the server validates that the artwork belongs to the tenant and is published, then appends the public image URL to the stored contact message and notification email. Visitor-supplied URLs are not trusted.

The tenant dashboard Workbench actions use the same action-card treatment as Portfolio Sections.

<!-- End of file. -->
