# Tenant artwork defaults and contact context

Tenant administrators can choose whether newly uploaded artwork starts as an unpublished draft or is published immediately. The setting is under **Tenant Admin → Settings → Miscellaneous → New artwork defaults**. It affects only future uploads; existing artwork keeps its current publication state.

When a visitor opens the tenant contact form from an artwork page, ArtsFolio carries the artwork slug through the form. On submission, the server validates that the artwork belongs to the tenant and is published, then appends the public image URL to the stored contact message and notification email. Visitor-supplied URLs are not trusted.

The **Artworks** page presents **Upload artwork**, **Artwork placement matrix**, and **Section artwork order** as responsive action cards matching the Portfolio Sections interface.

The public platform contact form asks for a topic, preserves entered values when validation fails, and includes the topic in the platform-admin message workflow and notification email.

<!-- End of file. -->
