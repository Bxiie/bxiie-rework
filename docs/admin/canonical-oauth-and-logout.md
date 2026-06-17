# Canonical OAuth and logout

Google and Facebook login are configured once for the platform host:

```text
https://artsfol.io/auth/google/callback
https://artsfol.io/auth/facebook/callback
```

Tenant domains and tenant subdomains do not need to be added to Google or Meta
OAuth redirect settings. Tenant login buttons send users through the ArtsFolio
platform OAuth endpoint and then return them to the tenant admin area.

Use `https://artsfol.io/logout` to sign out of the platform session. Tenant
admin pages continue to show logout controls in their admin chrome.

// End of file.
