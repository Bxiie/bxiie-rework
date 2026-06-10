# Auth and artwork follow-up developer notes

This update fixes tenant logout session revocation, platform password reset route availability, artwork publish/unpublish button refresh behavior, and public price display for bare numeric prices.

Tenant logout now revokes the server-side session token before clearing browser cookies. Password reset on `artsfol.io/password/forgot` is mounted as a public platform route. Artwork publish/unpublish AJAX responses now include the next button label/action so the row reflects the new state immediately. Bare numeric artwork prices are displayed with a leading `$` marker.

# End of file.
