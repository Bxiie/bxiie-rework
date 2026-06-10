# Custom-domain deploy safety

Production preflight and deployment must not delete, disable, or rewrite tenant custom-domain rows as a side effect of testing. Tenant custom domains are live artist sites, not disposable fixtures.

The migration `0020_preserve_bxiie_custom_domains.sql` repairs the canonical Bxiie mappings for `bxiie.artsfol.io`, `bxiie.com`, and `www.bxiie.com` without deleting any other tenant domain. If a hostname collision exists, the migration leaves it alone and lets preflight fail so the operator can inspect the data intentionally.

Core routing tests may verify that important tenant hostnames resolve, but they must be read-only. Tests should not mutate `tenant_domains` except in explicitly isolated development fixtures.

# End of file.
