# Signup-code trial duration

A signup code with free-access months sets both:

- `complimentary_until`
- `current_period_ends_at`

to the same calculated expiration timestamp.

Platform tenant billing details prefer `complimentary_until` while billing is
in `trial` status. This also corrects the display for tenants created before
the provisioning fix, provided their complimentary expiration was stored
correctly.

A three-month signup code therefore displays a three-month trial and a billing
start countdown based on that date, rather than the default one-month period.

<!-- End of file. -->
