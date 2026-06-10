# Auth session cookie notes

Tenant browser login must return the session cookie through the response headers. Calling the cookie helper without attaching the returned header to the response will create a redirect loop because `/admin/*` cannot resolve `artsfolio_session`.

`Response` supports repeated headers by accepting array values. This is required for multiple `Set-Cookie` headers used to clear stale host-only and `.artsfol.io` cookie variants before issuing the active session cookie.

<!-- End of file. -->
