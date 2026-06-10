# Tenant public forms

Tenant contact and signup forms use ArtsFolio first-party CAPTCHA. The checkbox is progressively enhanced by `/assets/tenant-forms.js`, but the server is authoritative: tokens are signed, session-bound, one-time capable, time-limited, and checked with a hidden honeypot and dwell-time requirement.

The checkbox is no longer rendered server-disabled. JavaScript may temporarily disable it for the first two seconds, but if the script is cached or blocked, the visitor can still submit after the server-side dwell time. Honeypot hiding is applied inline and in CSS so custom tenant styles cannot expose it.

Enhanced form submissions request JSON and show success or errors on the same page. Non-JavaScript submissions receive branded fallback pages.

# End of file.
